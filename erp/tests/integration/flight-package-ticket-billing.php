<?php

// Explicit local integration check. Clones table STRUCTURES and a few fixture
// rows into a new disposable MySQL database; never writes application records.
use App\Models\ContractFlightPassenger;
use App\Models\ContractFlightPassengerChange;
use App\Models\ContractFlightPassengerTicket;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\TicketPurchase;
use App\Models\TicketSale;
use App\Models\Transaction;
use App\Services\FlightPackageTicketBilling;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$source = DB::connection();
$config = $source->getConfig();
if ($config['driver'] !== 'mysql') {
    throw new RuntimeException('This isolated integration check requires MySQL.');
}
$temporary = 'codex_package_test_'.bin2hex(random_bytes(6));
$tables = ['accounts', 'transactions', 'journal_entries', 'journal_items', 'ticket_sales',
    'ticket_sale_items', 'ticket_purchases', 'contract_flight_passengers',
    'contract_flight_passenger_tickets', 'payment_schedules'];
$fixtures = [];
foreach (['transactions', 'journal_entries', 'journal_items', 'ticket_sales', 'ticket_sale_items', 'ticket_purchases', 'contract_flight_passengers'] as $table) {
    $fixtures[$table] = (array) $source->table($table)->first();
    if (! $fixtures[$table]) {
        throw new RuntimeException('Missing fixture for '.$table);
    }
}
$accountRows = $source->table('accounts')->whereIn('code', [config('accounts.accounts_receivable'), config('accounts.ticket_sales_revenue')])->get();
$source->statement('CREATE DATABASE `'.$temporary.'`');
$failure = null;
try {
    foreach ($tables as $table) {
        $source->statement('CREATE TABLE `'.$temporary.'`.`'.$table.'` LIKE `'.$config['database'].'`.`'.$table.'`');
    }
    config(['database.connections.package_test' => array_merge($config, ['database' => $temporary, 'name' => 'package_test']),
        'services.party_ledger.notify' => false]);
    DB::setDefaultConnection('package_test');
    Model::setConnectionResolver(app('db'));
    foreach ([ContractFlightPassenger::class, TicketSale::class, Transaction::class, ContractFlightPassengerTicket::class] as $model) {
        if ((new $model)->getConnection()->getDatabaseName() !== $temporary) {
            throw new RuntimeException('Model isolation failed for '.$model);
        }
    }
    $db = DB::connection();
    foreach ($accountRows as $account) {
        $db->table('accounts')->insert((array) $account);
    }
    $seed = function (string $table, array $overrides) use ($fixtures, $db) {
        $db->table($table)->insert(array_merge($fixtures[$table], $overrides));
    };
    $seed('ticket_sales', ['id' => 1, 'client_id' => 68, 'company_id' => 2,
        'total_amount' => 100, 'paid_amount' => 0, 'due_amount' => 100, 'status' => 'confirm', 'deleted_at' => null]);
    $seed('ticket_purchases', ['id' => 1, 'status' => 'confirm', 'deleted_at' => null]);
    $seed('ticket_sale_items', ['id' => 1, 'ticket_sale_id' => 1, 'ticket_purchase_id' => 1, 'price' => 100]);
    $seed('contract_flight_passengers', ['id' => 1, 'agent_id' => 68, 'agent_price' => 500]);
    $seed('transactions', ['id' => 1, 'user_id' => 68, 'company_id' => 2, 'invoice_id' => 1,
        'type' => 'ticket_sale', 'debit' => 100, 'credit' => 0]);
    $seed('transactions', ['id' => 2, 'user_id' => 68, 'company_id' => 2, 'invoice_id' => 1,
        'type' => 'contract_flight_passenger_agent', 'debit' => 500, 'credit' => 0]);
    $seed('journal_entries', ['id' => 1, 'source' => 'ticket_sale', 'source_id' => 1,
        'reversed_journal_entry_id' => null, 'deleted_at' => null]);
    $seed('journal_items', ['id' => 1, 'journal_entry_id' => 1, 'debit' => 100, 'credit' => 0, 'deleted_at' => null]);
    $seed('journal_items', ['id' => 2, 'journal_entry_id' => 1, 'debit' => 0, 'credit' => 100, 'deleted_at' => null]);
    $service = app(FlightPackageTicketBilling::class);
    $check = function (bool $condition, string $description) {
        if (! $condition) {
            throw new RuntimeException($description);
        }
        echo 'PASS '.$description.PHP_EOL;
    };
    $service->sync(1);
    $check(FlightPackageTicketBilling::covered(1, 68) === 0.0, 'Standalone ticket retains its charge');
    $passenger = ContractFlightPassenger::findOrFail(1);
    if ($passenger->getConnection()->getDatabaseName() !== $temporary || $passenger->ticketPurchases()->getRelated()->getConnection()->getDatabaseName() !== $temporary) {
        throw new RuntimeException('Hydrated relationship isolation failed');
    }
    $passenger->ticketPurchases()->attach(1);
    $check(FlightPackageTicketBilling::covered(1, 68) === 100.0, 'Linking adds exactly one ticket-charge credit');
    $check((float) Transaction::sum('debit') - (float) Transaction::sum('credit') === 500.0, '100 ticket plus 500 package nets to 500');
    $check((float) TicketSale::find(1)->due_amount === 0.0, 'Included ticket is not collectible a second time');
    $count = Transaction::count();
    $service->sync(1);
    $check(Transaction::count() === $count, 'Repeated reconciliation is idempotent');
    $passenger->ticketPurchases()->detach(1);
    $check(FlightPackageTicketBilling::covered(1, 68) === 0.0, 'Unlink restores standalone ticket charge');
    $check((float) TicketSale::find(1)->due_amount === 100.0, 'Standalone invoice due is restored');
    $passenger->ticketPurchases()->attach(1);
    $sale = TicketSale::find(1);
    $sale->paid_amount = 30;
    $sale->save();
    $check((float) $sale->fresh()->paid_amount === 30.0, 'Existing real payment is preserved');
    $check((float) $sale->fresh()->due_amount === 0.0, 'Inclusion never creates negative invoice due');
    $seed('ticket_purchases', ['id' => 2, 'ticket_no' => 'PACKAGE-TEST-SECOND', 'status' => 'confirm', 'deleted_at' => null]);
    $seed('ticket_sale_items', ['id' => 2, 'ticket_sale_id' => 1, 'ticket_purchase_id' => 2, 'price' => 40]);
    $sale->total_amount = 140;
    $sale->save();
    $check((float) $sale->fresh()->due_amount === 10.0, 'Mixed invoice retains standalone ticket due after actual payment');
    $check(FlightPackageTicketBilling::covered(1, 68) === 100.0, 'Only the linked ticket is offset on a mixed invoice');
    $passenger->agent_id = 999;
    $passenger->save();
    $check(FlightPackageTicketBilling::covered(1, 68) === 0.0, 'A different package agent does not receive the ticket offset');
    $passenger->agent_id = 68;
    $passenger->save();
    $ticket = TicketPurchase::find(1);
    $ticket->status = 'cancelled';
    $ticket->save();
    $check(FlightPackageTicketBilling::covered(1, 68) === 0.0, 'Ticket cancellation clears inclusion before normal sale-reversal accounting');
    $ticket->status = 'confirm';
    $ticket->save();
    $check(FlightPackageTicketBilling::covered(1, 68) === 100.0, 'Restoring the linked ticket restores inclusion once');
    $sale->status = 'cancelled';
    $sale->save();
    $check(FlightPackageTicketBilling::covered(1, 68) === 0.0, 'Cancelled sale has no outstanding package inclusion credit');
    $seed('ticket_sales', ['id' => 2, 'invoice_no' => 'PKG-NEW-SALE', 'client_id' => 68, 'company_id' => 2,
        'total_amount' => 70, 'paid_amount' => 0, 'due_amount' => 70, 'status' => 'confirm', 'deleted_at' => null]);
    $seed('ticket_sale_items', ['id' => 3, 'ticket_sale_id' => 2, 'ticket_purchase_id' => 1, 'price' => 70]);
    Transaction::create(['user_id' => 68, 'company_id' => 2, 'invoice_id' => 2,
        'type' => 'ticket_sale', 'user_type' => 'customer', 'debit' => 70, 'credit' => 0]);
    $check(FlightPackageTicketBilling::covered(2, 68) === 0.0, 'New sale waits for its balanced recognition journal');
    $recognition = JournalEntry::create(['source' => 'ticket_sale', 'source_id' => 2,
        'company_id' => 2, 'date' => now()->toDateString(), 'reference' => 'PKG-NEW-SALE', 'description' => 'Test recognition']);
    JournalItem::create(['journal_entry_id' => $recognition->id, 'account_id' => $accountRows[0]->id, 'debit' => 70, 'credit' => 0]);
    JournalItem::create(['journal_entry_id' => $recognition->id, 'account_id' => $accountRows[1]->id, 'debit' => 0, 'credit' => 70]);
    $check(FlightPackageTicketBilling::covered(2, 68) === 70.0, 'Selling an already linked ticket is offset automatically');
    $check((float) TicketSale::find(2)->due_amount === 0.0, 'New linked-ticket invoice cannot collect the package ticket twice');
    $passenger->delete();
    $check(FlightPackageTicketBilling::covered(2, 68) === 0.0, 'Removing the package restores the standalone ticket charge');
    $check(! array_diff([FlightPackageTicketBilling::TYPE], ContractFlightPassengerChange::NOT_RECEIPT_TYPES), 'Package offsets are excluded from cash receipts');
    foreach (JournalEntry::where('source', FlightPackageTicketBilling::TYPE)->with('items')->get() as $journal) {
        $check($journal->is_balanced, 'Adjustment journal '.$journal->id.' balances');
    }
} catch (Throwable $error) {
    $failure = $error;
    fwrite(STDERR, $error->getMessage().PHP_EOL);
} finally {
    DB::purge('package_test');
    DB::setDefaultConnection($source->getName());
    // This exact name was generated and created above, never an app database.
    if (preg_match('/^codex_package_test_[a-f0-9]{12}$/', $temporary)) {
        $source->statement('DROP DATABASE `'.$temporary.'`');
    }
}
exit($failure ? 1 : 0);
