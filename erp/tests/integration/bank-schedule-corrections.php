<?php

// Every write runs in a disposable MySQL database; source data is read-only.
use App\Http\Controllers\Dashboard\BankControllerV2;
use App\Models\Account;
use App\Models\Bank;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\PaymentSchedule;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BankScheduleCorrection;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$source = DB::connection();
$config = $source->getConfig();
if ($config['driver'] !== 'mysql') {
    throw new RuntimeException('Requires MySQL fixtures.');
}
$bankFixture = $source->table('banks')->where('company_id', 2)->whereNotNull('account_id')->whereNull('deleted_at')->first();
$partyFixtures = User::role(['agent', 'vendor', 'customer'])->limit(2)->get();
if (! $bankFixture || $partyFixtures->count() !== 2) {
    throw new RuntimeException('Missing bank or party fixtures.');
}
$temporary = 'codex_schedule_test_'.bin2hex(random_bytes(6));
$source->statement('CREATE DATABASE `'.$temporary.'`');
$checks = 0;
try {
    $tables = ['accounts', 'companies', 'banks', 'users', 'roles', 'permissions', 'model_has_permissions', 'role_has_permissions', 'model_has_roles', 'journal_entries', 'journal_items',
        'transactions', 'payment_schedules', 'journal_entry_detail_edits', 'bank_transaction_amount_edits', 'journal_entry_date_edits'];
    foreach ($tables as $table) {
        $source->statement('CREATE TABLE `'.$temporary.'`.`'.$table.'` LIKE `'.$config['database'].'`.`'.$table.'`');
    }
    config(['database.connections.schedule_test' => array_merge($config, ['database' => $temporary, 'name' => 'schedule_test']),
        'cache.default' => 'array', 'services.party_ledger.notify' => false]);
    DB::setDefaultConnection('schedule_test');
    Model::setConnectionResolver(app('db'));
    $db = DB::connection();
    foreach ([JournalEntry::class, JournalItem::class, PaymentSchedule::class, Bank::class, User::class, Transaction::class] as $model) {
        if ((new $model)->getConnection()->getDatabaseName() !== $temporary) {
            throw new RuntimeException('Model connection isolation failed.');
        }
    }
    foreach (['accounts', 'companies', 'roles'] as $table) {
        foreach ($source->table($table)->get() as $row) {
            $db->table($table)->insert((array) $row);
        }
    }
    $userIds = $partyFixtures->pluck('id')->push(1)->unique();
    foreach ($source->table('users')->whereIn('id', $userIds)->get() as $row) {
        $db->table('users')->insert((array) $row);
    }
    foreach ($source->table('model_has_roles')->whereIn('model_id', $userIds)->get() as $row) {
        $db->table('model_has_roles')->insert((array) $row);
    }
    $db->table('banks')->insert((array) $bankFixture);
    $actor = User::findOrFail(1);
    auth()->setUser($actor);
    Gate::before(fn ($user) => (int) $user->id === 1 ? true : null);
    $partyA = User::findOrFail($partyFixtures[0]->id);
    $partyB = User::findOrFail($partyFixtures[1]->id);
    $bank = Bank::findOrFail($bankFixture->id);
    $chargeAccount = Account::where('code', '8510')->firstOrFail();
    $ar = Account::where('code', config('accounts.accounts_receivable'))->firstOrFail();
    $ap = Account::where('code', config('accounts.accounts_payable'))->firstOrFail();
    $controller = app(BankControllerV2::class);
    $service = app(BankScheduleCorrection::class);
    $request = function (array $data = []) use ($actor, $app) {
        $request = Request::create('/', 'POST', $data);
        $request->setUserResolver(fn () => $actor);
        $app->instance('request', $request);

        return $request;
    };
    $assert = function (bool $ok, string $label) use (&$checks) {
        if (! $ok) {
            throw new RuntimeException($label);
        }
        $checks++;
    };
    $opening = JournalEntry::create(['company_id' => 2, 'created_by' => 1, 'date' => '2026-08-01', 'source' => 'test', 'reference' => 'TEST-OPEN', 'description' => 'Fixture funds']);
    JournalItem::create(['journal_entry_id' => $opening->id, 'account_id' => $bank->account_id, 'debit' => 1000000, 'credit' => 0]);
    JournalItem::create(['journal_entry_id' => $opening->id, 'account_id' => $ar->id, 'debit' => 0, 'credit' => 1000000]);
    foreach (['receive', 'pay'] as $direction) {
        $receive = $direction === 'receive';
        $schedule = PaymentSchedule::create(['company_id' => 2, 'type' => $direction, 'party_type' => 'other', 'party_name' => 'SALEH',
            'source_label' => 'OTHERS', 'amount' => 50000, 'paid_amount' => 50000, 'status' => 'paid', 'scheduled_date' => '2026-08-31', 'paid_date' => '2026-08-31', 'created_by' => 1]);
        $entry = JournalEntry::create(['company_id' => 2, 'created_by' => 1, 'date' => '2026-08-31', 'entry_time' => '12:00:00',
            'source' => 'schedule_payment_settlement', 'source_id' => $schedule->id, 'reference' => 'ADHOC-'.($receive ? 'RCV' : 'PAY').'-PAID-'.$schedule->id, 'description' => 'OTHERS — SALEH']);
        JournalItem::create(['journal_entry_id' => $entry->id, 'account_id' => $bank->account_id, 'debit' => $receive ? 50000 : 0, 'credit' => $receive ? 0 : 50000]);
        JournalItem::create(['journal_entry_id' => $entry->id, 'account_id' => $receive ? $ar->id : $ap->id, 'debit' => $receive ? 0 : 50000, 'credit' => $receive ? 50000 : 0]);
        $originalItems = $entry->items()->get()->toJson();
        $beforeBank = $bank->availableBalance();
        $details = ['remarks' => 'Matched payment', 'beneficiary_party_id' => $partyA->id, 'correction_reason' => 'Match original sender', 'pay_method' => 'bank'];
        $controller->updateRemarks($request($details), 'super-admin', $entry);
        $controller->updateRemarks($request($details), 'super-admin', $entry->fresh());
        $ledger = fn ($party) => (float) Transaction::where('invoice_id', $entry->id)->where('user_id', $party->id)->selectRaw('COALESCE(SUM(credit-debit),0) as paid')->value('paid');
        $assert($ledger($partyA) === 50000.0, $direction.' party link does not duplicate money');
        $amount = ['amount' => 55000, 'charge_amount' => 100, 'charge_account_id' => $chargeAccount->id, 'vat_amount' => 15, 'vat_account_id' => $chargeAccount->id, 'reason' => 'Correct bank statement amount', 'confirmation' => 'CONFIRM'];
        $controller->updateTransactionAmount($request($amount), 'super-admin', $entry->fresh());
        $journalCount = JournalEntry::count();
        $controller->updateTransactionAmount($request($amount), 'super-admin', $entry->fresh());
        $assert(JournalEntry::count() === $journalCount, $direction.' duplicate confirmation is idempotent');
        $assert($ledger($partyA) === 55000.0, $direction.' principal only reaches party');
        $assert((float) $schedule->fresh()->paid_amount === 55000.0, $direction.' schedule actual payment synchronized');
        $assert(abs($bank->availableBalance() - $beforeBank - ($receive ? 4885 : -5115)) < .005, $direction.' bank principal and fees agree');
        $details['beneficiary_party_id'] = $partyB->id;
        $controller->updateRemarks($request($details), 'super-admin', $entry->fresh());
        $assert($ledger($partyA) === 0.0 && $ledger($partyB) === 55000.0, $direction.' party reassignment preserves history');
        $amount['amount'] = 49000;
        $amount['charge_amount'] = $amount['vat_amount'] = 0;
        $controller->updateTransactionAmount($request($amount), 'super-admin', $entry->fresh());
        $assert($ledger($partyB) === 49000.0, $direction.' reduction and charge removal synchronize');
        $assert(abs($bank->availableBalance() - $beforeBank - ($receive ? -1000 : 1000)) < .005, $direction.' removed charges leave no residual');
        $assert($entry->items()->get()->toJson() === $originalItems, $direction.' original journal remains unchanged');
        $payload = $controller->transactionAmountDetails($request(), 'super-admin', $entry->fresh())->getData(true);
        $assert($payload['is_schedule'] && $payload['amount'] == 49000 && $payload['charge_amount'] == 0, $direction.' modal reports current corrected values');
        $assert(abs($payload['transaction']['total_debit'] - $payload['transaction']['total_credit']) < .005, $direction.' modal journal totals balance');
        $assert($payload['transaction']['total_debit'] == 49000, $direction.' overview shows effective totals without audit duplication');
        $audit = DB::table('journal_entry_detail_edits')->where('journal_entry_id', $entry->id)->orderByDesc('id')->first();
        $assert(json_decode($audit->new_data, true)['note'] === 'Matched payment', 'Purpose note preserved in audit');
        $controller->updateJournalDate($request(['new_date' => '2026-09-01', 'time' => '14:30', 'reason' => 'Match bank posting time']), 'super-admin', $entry->fresh());
        $assert(JournalEntry::where('source', 'schedule_payment_correction')->where('source_id', $entry->id)->whereDate('date', '!=', '2026-09-01')->count() === 0, 'Date correction moves all adjustment journals');
        $assert(Transaction::where('invoice_id', $entry->id)->where('type', 'schedule_payment_correction')->whereDate('payment_date', '!=', '2026-09-01')->count() === 0, 'Date correction moves linked ledger rows');
        $guarded = $request();
        $guarded->setUserResolver(fn () => new User(['company_id' => 999]));
        try {
            $service->parts($guarded, $entry->fresh());
            throw new RuntimeException('Cross-company guard failed.');
        } catch (HttpException $error) {
            $assert($error->getStatusCode() === 403, 'Cross-company correction rejected');
        }
        $bad = $amount;
        $bad['confirmation'] = 'WRONG';
        try {
            $controller->updateTransactionAmount($request($bad), 'super-admin', $entry->fresh());
            throw new RuntimeException('Confirmation guard failed.');
        } catch (ValidationException $error) {
            $assert(isset($error->errors()['confirmation']), 'Money change requires typed confirmation');
        }
        $bad = $amount;
        $bad['charge_amount'] = 100;
        $bad['charge_account_id'] = $bank->account_id;
        $rejected = $controller->updateTransactionAmount($request($bad), 'super-admin', $entry->fresh());
        $assert($rejected->getStatusCode() === 422, 'Bank cannot be used as charge account');
        $bad = $amount;
        $bad['charge_amount'] = 100;
        $bad['charge_account_id'] = $chargeAccount->id;
        $bad['reason'] = '';
        try {
            $controller->updateTransactionAmount($request($bad), 'super-admin', $entry->fresh());
            throw new RuntimeException('Audit reason guard failed.');
        } catch (ValidationException $error) {
            $assert(isset($error->errors()['reason']), 'Money change requires audit reason');
        }
        $bad = $amount;
        $beforeRefusal = JournalEntry::count();
        if ($receive) {
            $bad['charge_amount'] = 60000;
            try {
                $controller->updateTransactionAmount($request($bad), 'super-admin', $entry->fresh());
                throw new RuntimeException('Excess receipt charge guard failed.');
            } catch (HttpException $error) {
                $assert($error->getStatusCode() === 422, 'Receipt charges cannot exceed principal');
            }
        } else {
            $bad['amount'] = 2000000;
            $rejected = $controller->updateTransactionAmount($request($bad), 'super-admin', $entry->fresh());
            $assert($rejected->getStatusCode() === 422, 'Insufficient bank funds rejected');
        }
        $assert(JournalEntry::count() === $beforeRefusal && (float) $schedule->fresh()->paid_amount === 49000.0, 'Failed correction is fully rolled back');
        $schedule->update(['schedulable_type' => 'App\\Models\\PartyInvoice']);
        try {
            $service->parts($request(), $entry->fresh());
            throw new RuntimeException('Linked invoice guard failed.');
        } catch (HttpException $error) {
            $assert($error->getStatusCode() === 422, 'Linked document payment rejected');
        }
    }
    foreach (['receive', 'pay'] as $direction) {
        $receive = $direction === 'receive';
        $reference = 'MANUAL-'.strtoupper($direction);
        $entry = JournalEntry::create(['company_id' => 2, 'created_by' => 1, 'date' => '2026-09-19', 'entry_time' => '13:50:00',
            'source' => 'bank_transaction', 'source_id' => $bank->id, 'reference' => $reference,
            'description' => ($receive ? 'Deposit to ' : 'Withdrawal from ').$bank->name.' — PARTY PAYMENT',
            'beneficiary' => $partyA->name, 'beneficiary_party_id' => $partyA->id]);
        JournalItem::create(['journal_entry_id' => $entry->id, 'account_id' => $bank->account_id,
            'debit' => $receive ? 100200 : 0, 'credit' => $receive ? 0 : 100200, 'note' => 'PARTY PAYMENT']);
        JournalItem::create(['journal_entry_id' => $entry->id, 'account_id' => $receive ? $ar->id : $ap->id,
            'debit' => $receive ? 0 : 100200, 'credit' => $receive ? 100200 : 0, 'note' => 'PARTY PAYMENT',
            'party_type' => 'agent', 'party_id' => $partyA->id]);
        $previous = Transaction::where('company_id', 2)->where('user_id', $partyA->id)->latest('id')->first();
        $oldBalance = (float) ($previous?->balance ?? 0);
        $balance = $oldBalance + ($receive ? -100200 : 100200);
        $ledger = Transaction::create(['company_id' => 2, 'user_id' => $partyA->id, 'user_type' => 'customer',
            'type' => 'party_payment', 'account_id' => $bank->id, 'invoice_id' => $entry->id,
            'payment_date' => '2026-09-19', 'reference_no' => $reference, 'old_balance' => $oldBalance,
            'debit' => $receive ? 0 : 100200, 'credit' => $receive ? 100200 : 0, 'balance' => $balance, 'remarks' => 'PARTY PAYMENT']);
        $response = $controller->updateTransactionAmount($request(['amount' => 100000, 'charge_amount' => 50,
            'charge_account_id' => $chargeAccount->id, 'vat_amount' => 0, 'reason' => 'Correct manual party payment',
            'confirmation' => 'CONFIRM']), 'super-admin', $entry);
        $assert($response->getStatusCode() === 200, $direction.' direct party payment amount is editable');
        $ledger->refresh();
        $assert((float) ($receive ? $ledger->credit : $ledger->debit) === 100000.0, $direction.' party principal synchronized');
        $expectedBalance = $oldBalance + ($receive ? -100000 : 100000);
        $assert(abs((float) $ledger->balance - $expectedBalance) < .005, $direction.' party row balance synchronized');
        $entry->refresh()->load('items');
        $assert(abs((float) $entry->items->sum('debit') - (float) $entry->items->sum('credit')) < .005, $direction.' corrected direct journal balances');
    }
    // Legacy data sometimes stored the schedule/journal under Travels while
    // its selected bank belonged to another group company. The linked account
    // still identifies the bank; super admins must be able to open and repair it.
    $bank->update(['company_id' => 4]);
    $legacySchedule = PaymentSchedule::create(['company_id' => 2, 'type' => 'receive', 'party_type' => 'other',
        'party_name' => 'SALEH', 'source_label' => 'OTHERS', 'amount' => 60000, 'paid_amount' => 60000,
        'status' => 'paid', 'scheduled_date' => '2026-08-31', 'paid_date' => '2026-08-31', 'created_by' => 1]);
    $legacyEntry = JournalEntry::create(['company_id' => 2, 'created_by' => 1, 'date' => '2026-08-31',
        'source' => 'schedule_payment_settlement', 'source_id' => $legacySchedule->id,
        'reference' => 'LEGACY-CROSS-COMPANY', 'description' => 'OTHERS — SALEH']);
    JournalItem::create(['journal_entry_id' => $legacyEntry->id, 'account_id' => $bank->account_id, 'debit' => 60000, 'credit' => 0]);
    JournalItem::create(['journal_entry_id' => $legacyEntry->id, 'account_id' => $ar->id, 'debit' => 0, 'credit' => 60000]);
    $assert($service->parts($request(), $legacyEntry)['bank']->is($bank), 'Super admin resolves legacy cross-company bank by account');
    $bank->update(['company_id' => 2]);
    foreach (JournalEntry::with('items')->get() as $journal) {
        $assert(abs((float) $journal->items->sum('debit') - (float) $journal->items->sum('credit')) < .005, 'Every new journal balances');
    }
    foreach ([$partyA, $partyB] as $party) {
        $running = 0.0;
        foreach (Transaction::where('user_id', $party->id)->orderBy('id')->get() as $row) {
            $assert(abs((float) $row->old_balance - $running) < .005, 'Party old balance chains');
            $running += (float) $row->debit - (float) $row->credit;
            $assert(abs((float) $row->balance - $running) < .005, 'Party closing balance chains');
        }
    }
    echo 'PASS: '.$checks." schedule correction checks; source database untouched.\n";
} finally {
    DB::disconnect('schedule_test');
    DB::setDefaultConnection($config['name'] ?? 'mysql');
    $source->statement('DROP DATABASE `'.$temporary.'`');
}
