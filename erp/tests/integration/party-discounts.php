<?php

// Isolated MySQL integration check. Reads fixture rows from the configured DB;
// all writes and migrations run in a freshly created disposable database.
use App\Http\Controllers\Dashboard\PartyDiscountController;
use App\Models\ContractFlightPassenger;
use App\Models\JournalEntry;
use App\Models\PartyDiscount;
use App\Models\PartyInvoice;
use App\Models\PaymentSchedule;
use App\Models\TicketSale;
use App\Models\Transaction;
use App\Models\User;
use App\Services\PartyDiscountService;
use App\Services\PartyInvoiceAllocation;
use App\Services\PartyLedgerService;
use App\Support\StatementRows;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$source = DB::connection();
$config = $source->getConfig();
if ($config['driver'] !== 'mysql') {
    throw new RuntimeException('Requires MySQL with existing application fixture tables.');
}
$temporary = 'codex_discount_test_'.bin2hex(random_bytes(6));
$tables = ['accounts', 'companies', 'users', 'roles', 'model_has_roles', 'transactions', 'journal_entries', 'journal_items', 'party_invoices', 'payment_schedules', 'ticket_sales', 'ticket_sale_items', 'ticket_purchases', 'contract_flight_passengers', 'contract_flight_passenger_tickets', 'contract_flight_passenger_changes', 'contract_flights'];
$party = (array) $source->table('users')->find(68);
$saleFixture = (array) $source->table('ticket_sales')->first();
$passengerFixture = (array) $source->table('contract_flight_passengers')->first();
if (! $party || ! $saleFixture || ! $passengerFixture) {
    throw new RuntimeException('Missing integration fixtures. No data changed.');
}
$source->statement('CREATE DATABASE `'.$temporary.'`');
$failure = null;
$checks = 0;
try {
    foreach ($tables as $table) {
        $source->statement('CREATE TABLE `'.$temporary.'`.`'.$table.'` LIKE `'.$config['database'].'`.`'.$table.'`');
    }
    config(['database.connections.discount_test' => array_merge($config, ['database' => $temporary, 'name' => 'discount_test']), 'services.party_ledger.notify' => false, 'cache.default' => 'array']);
    DB::setDefaultConnection('discount_test');
    Model::setConnectionResolver(app('db'));
    $db = DB::connection();
    foreach ([User::class, PartyInvoice::class, PartyDiscount::class, TicketSale::class, ContractFlightPassenger::class, JournalEntry::class, Transaction::class] as $class) {
        if ((new $class)->getConnection()->getDatabaseName() !== $temporary) {
            throw new RuntimeException('Model connection isolation failed.');
        }
    }
    foreach ($source->table('accounts')->whereIn('code', [config('accounts.accounts_receivable'), config('accounts.ticket_sales_revenue')])->get() as $account) {
        $db->table('accounts')->insert((array) $account);
    }
    $db->table('users')->insert($party);
    if ($company = $source->table('companies')->find(2)) {
        $db->table('companies')->insert((array) $company);
    }
    foreach ($source->table('roles')->get() as $role) {
        $db->table('roles')->insert((array) $role);
    }
    foreach ($source->table('model_has_roles')->where('model_id', 68)->get() as $role) {
        $db->table('model_has_roles')->insert((array) $role);
    }
    (require __DIR__.'/../../database/migrations/2026_10_18_200000_create_party_discounts_table.php')->up();
    $actor = (new User)->forceFill(['id' => 68, 'is_super_admin' => true, 'company_id' => 2]);
    $service = app(PartyDiscountService::class);
    $check = function (bool $condition, string $label) use (&$checks) {
        if (! $condition) {
            throw new RuntimeException('FAILED: '.$label);
        }
        $checks++;
        echo 'PASS '.$label.PHP_EOL;
    };
    auth()->setUser($actor);
    $script = view('party-statement.partials.scripts', ['role' => 'super-admin', 'type' => 'agent'])->render();
    $process = proc_open(['node', '--check'], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
    if (! is_resource($process)) {
        throw new RuntimeException('Node is required for the rendered ledger JavaScript syntax check.');
    }
    fwrite($pipes[0], $script);
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $check(proc_close($process) === 0, 'Rendered shared-ledger JavaScript parses: '.$output);
    $modal = view('party-statement.partials.modals', ['role' => 'super-admin', 'type' => 'agent', 'banks' => collect(), 'countries' => collect()])->render();
    $check(str_contains($modal, 'id="discountForm"') && ! str_contains($modal, 'id="discountHistory"'), 'Shared discount creation modal stays focused on posting');
    $reject = function (callable $action, string $label) use ($check) {
        try {
            $action();
        } catch (ValidationException|HttpException $e) {
            $check(true, $label);

            return;
        }
        $check(false, $label);
    };
    $invoice = PartyInvoice::create(['company_id' => 2, 'user_id' => 68, 'invoice_number' => 'TEST-DISC-1', 'invoice_date' => '2026-10-01', 'amount' => 50000, 'paid_amount' => 0, 'due_amount' => 50000, 'payment_status' => 'due', 'created_by' => 68]);
    $charge = Transaction::create(['company_id' => 2, 'user_id' => 68, 'type' => 'party_invoice', 'user_type' => 'customer', 'invoice_id' => $invoice->id, 'payment_date' => '2026-10-01', 'reference_no' => 'TEST-DISC-1', 'debit' => 50000, 'credit' => 0]);
    $schedule = PaymentSchedule::create(['company_id' => 2, 'schedulable_type' => $invoice->getMorphClass(), 'schedulable_id' => $invoice->id, 'type' => 'receive', 'party_type' => 'agent', 'party_id' => 68, 'party_name' => 'Test Agent', 'source_label' => 'Discount test', 'amount' => 50000, 'paid_amount' => 0, 'scheduled_date' => '2026-10-18', 'status' => 'pending', 'created_by' => 68]);
    $check($invoice->getConnection()->getDatabaseName() === $temporary, 'Hydrated documents remain isolated');
    $key = (string) Str::uuid();
    $discount = $service->post(68, 'party_invoice', $invoice->id, 5000, '2026-10-18', 'Commercial discount test', $key, $actor, 2);
    $check((float) $invoice->fresh()->due_amount === 45000.0, '50000 less 5000 discount leaves 45000 due');
    $check((float) $invoice->fresh()->paid_amount === 0.0, 'Discount never increases paid amount');
    $check((float) $schedule->fresh()->amount === 45000.0, 'Schedule amount reduced without a payment');
    $journal = JournalEntry::findOrFail($discount->journal_entry_id);
    $check($journal->is_balanced && $journal->total_debit === 5000.0, 'Discount journal is balanced');
    $check(Transaction::where('type', 'party_discount')->whereNotNull('account_id')->count() === 0, 'No bank/cash ledger entry');
    $check($journal->items()->whereHas('account', fn ($q) => $q->where('code', config('accounts.sales_discount')))->where('debit', 5000)->exists(), 'Discount debits contra-revenue');
    $check($journal->items()->where('party_id', 68)->where('credit', 5000)->exists(), 'Discount credits agent receivable');
    [$statementRows, $statementOpening] = app(PartyLedgerService::class)->statement(68);
    $sharedRows = app(PartyLedgerService::class)->activeRows($statementRows, $statementOpening);
    $check($sharedRows->where('type', 'party_discount')->count() === 1, 'Valid discount remains on active/shared statements');
    $presented = StatementRows::build($sharedRows, collect(), collect())->firstWhere('id', Transaction::where('type', 'party_discount')->value('id'));
    $check($presented['kind'] === 'Discount allowed' && ! $presented['is_payment'], 'PDF identifies discount as a credit, not received money');
    $same = $service->post(68, 'party_invoice', $invoice->id, 5000, '2026-10-18', 'Commercial discount test', $key, $actor, 2);
    $check($same->id === $discount->id && PartyDiscount::count() === 1, 'Request retry cannot duplicate discount');
    $reject(fn () => $service->post(68, 'party_invoice', $invoice->id, 45000.01, '2026-10-18', 'Too much discount', (string) Str::uuid(), $actor, 2), 'Over-discount rejected');
    $reject(fn () => $service->post(68, 'party_invoice', $invoice->id, 1, '2026-09-01', 'Before charge date', (string) Str::uuid(), $actor, 2), 'Discount before invoice date rejected');
    $reject(fn () => $invoice->fresh()->update(['amount' => 60000]), 'Financial edits require discount reversal');
    $reject(fn () => $invoice->fresh()->delete(), 'Cannot delete discounted document');
    $otherActor = (new User)->forceFill(['id' => 68, 'company_id' => 1, 'is_super_admin' => false]);
    $reject(fn () => $service->post(68, 'party_invoice', $invoice->id, 1, '2026-10-18', 'Wrong company test', (string) Str::uuid(), $otherActor, null), 'Cross-company posting rejected');
    $request = Request::create('/discounts', 'GET', ['party_id' => 68]);
    $request->setUserResolver(fn () => $otherActor);
    $reject(fn () => app(PartyDiscountController::class)->index($request, $service), 'Controller rejects unauthorized users');
    app(PartyInvoiceAllocation::class)->allocateMany(68, 10000, 'manual', [['key' => 'party_invoice:'.$invoice->id, 'amount' => 10000]], 'sale', null, 'cash', '2026-10-18', 'TEST-PAY');
    $check((float) $invoice->fresh()->due_amount === 35000.0 && (float) $invoice->fresh()->paid_amount === 10000.0, 'Payment after discount keeps net due correct');
    $check((float) $schedule->fresh()->amount === 35000.0, 'Schedule can collect only remaining due, not already received money');
    $service->reverse($discount->id, 'Incorrect discount amount', $actor, 2);
    $check((float) $invoice->fresh()->due_amount === 40000.0 && (float) $invoice->fresh()->paid_amount === 10000.0, 'Reversal restores due while retaining actual payment');
    $check((float) $schedule->fresh()->amount === 40000.0, 'Reversal restores schedule remaining due');
    $count = JournalEntry::count();
    $service->reverse($discount->id, 'Repeated reverse request', $actor, 2);
    $check(JournalEntry::count() === $count, 'Reversal retry is idempotent');
    $reject(fn () => $service->reverse($discount->id, 'Wrong company reversal', $otherActor, null), 'Cross-company reversal rejected');
    [$rows, $opening] = app(PartyLedgerService::class)->statement(68);
    $active = app(PartyLedgerService::class)->activeRows($rows, $opening);
    $check(! $active->contains(fn ($row) => str_starts_with($row->type, 'party_discount')), 'Reversed discount pair omitted from active/shared statements');
    $check($active->last()->balance === 40000.0, 'Active statement preserves correct closing balance');
    $secondSchedule = $schedule->replicate();
    $secondSchedule->save();
    $beforeFailure = JournalEntry::count();
    $reject(fn () => $service->post(68, 'party_invoice', $invoice->id, 100, '2026-10-18', 'Multiple instalment test', (string) Str::uuid(), $actor, 2), 'Unsupported split schedules fail safely');
    $check(JournalEntry::count() === $beforeFailure && (float) $invoice->fresh()->due_amount === 40000.0, 'Failed schedule adjustment rolls back journal, credit note and invoice');
    $secondSchedule->delete();
    $db->table('ticket_sales')->insert(array_merge($saleFixture, ['id' => 1, 'client_id' => 68, 'company_id' => 2, 'total_amount' => 100, 'paid_amount' => 0, 'due_amount' => 100, 'status' => 'confirm', 'deleted_at' => null]));
    Transaction::create(['company_id' => 2, 'user_id' => 68, 'type' => 'ticket_sale', 'user_type' => 'customer', 'invoice_id' => 1, 'payment_date' => '2026-10-01', 'reference_no' => 'TEST-TICKET', 'debit' => 100, 'credit' => 0]);
    $ticketDiscount = $service->post(68, 'ticket_sale', 1, 10, '2026-10-18', 'Ticket discount test', (string) Str::uuid(), $actor, 2);
    $check((float) TicketSale::find(1)->due_amount === 90.0, 'Ticket discount updates due');
    $reject(fn () => TicketSale::find(1)->update(['status' => 'cancelled']), 'Discount must be reversed before ticket cancellation');
    app(PartyInvoiceAllocation::class)->allocateMany(68, 90, 'manual', [['key' => 'ticket_sale:1', 'amount' => 90]], 'sale', null, 'cash', '2026-10-18', 'TICKET-PAY');
    $check((float) TicketSale::find(1)->due_amount === 0.0 && (float) TicketSale::find(1)->paid_amount === 90.0, 'Discounted ticket settles without inflating receipts');
    $service->reverse($ticketDiscount->id, 'Restore original ticket due', $actor, 2);
    $check((float) TicketSale::find(1)->due_amount === 10.0, 'Reversing settled ticket discount reopens due');
    $db->table('transactions')->insert(['company_id' => 2, 'user_id' => 68, 'type' => 'ticket_package_inclusion', 'user_type' => 'customer', 'invoice_id' => 1, 'debit' => 0, 'credit' => 100]);
    $reject(fn () => $service->post(68, 'ticket_sale', 1, 1, '2026-10-18', 'Included ticket must not be discounted twice', (string) Str::uuid(), $actor, 2), 'Package-included ticket cannot receive a duplicate discount');
    $db->table('contract_flight_passengers')->insert(array_merge($passengerFixture, ['id' => 1, 'agent_id' => 68, 'agent_price' => 500, 'agent_paid_amount' => 0, 'exception_flag' => null]));
    Transaction::create(['company_id' => 2, 'user_id' => 68, 'type' => ContractFlightPassenger::AGENT_LEDGER_TYPE, 'user_type' => 'customer', 'invoice_id' => 1, 'payment_date' => '2026-10-01', 'reference_no' => 'TEST-PACKAGE', 'debit' => 500, 'credit' => 0]);
    $packageDiscount = $service->post(68, ContractFlightPassenger::AGENT_LEDGER_TYPE, 1, 50, '2026-10-18', 'Package discount test', (string) Str::uuid(), $actor, 2);
    $check(ContractFlightPassenger::find(1)->agent_due === 450.0, 'Package discount lowers agent due, not internal ticket/vendor cost');
    $service->reverse($packageDiscount->id, 'Restore package discount', $actor, 2);
    $check(ContractFlightPassenger::find(1)->agent_due === 500.0, 'Package discount reversal restores due');
    foreach (JournalEntry::with('items')->get() as $entry) {
        $check($entry->is_balanced, 'Journal '.$entry->id.' balances');
    }
    echo $checks.' checks passed.'.PHP_EOL;
} catch (Throwable $error) {
    $failure = $error;
    fwrite(STDERR, get_class($error).': '.$error->getMessage().PHP_EOL.$error->getTraceAsString().PHP_EOL);
} finally {
    DB::purge('discount_test');
    DB::setDefaultConnection($source->getName());
    if (preg_match('/^codex_discount_test_[a-f0-9]{12}$/', $temporary)) {
        $source->statement('DROP DATABASE `'.$temporary.'`');
    }
}
exit($failure ? 1 : 0);
