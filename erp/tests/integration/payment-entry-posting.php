<?php

use App\Http\Controllers\Dashboard\BankControllerV2;
use App\Models\Account;
use App\Models\Bank;
use App\Models\ExpenseDepartment;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$actor = User::where('is_super_admin', 1)->firstOrFail();
$department = ExpenseDepartment::active()->firstOrFail();
$bank = Bank::where('status', 1)->whereNotNull('account_id')->where('company_id', $department->company_id)->firstOrFail();
$expense = Account::where('type', 'expense')->where('status', 1)->whereNotIn('id', Bank::whereNotNull('account_id')->select('account_id'))->firstOrFail();
$controller = app(BankControllerV2::class);
$checks = 0;
$assert = function (bool $condition, string $message) use (&$checks): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
    $checks++;
};

auth()->setUser($actor);
Gate::before(fn (User $user) => (int) $user->id === (int) $actor->id ? true : null);
DB::beginTransaction();

try {
    $post = function (string $type, float $amount, string $reference, array $override = []) use ($actor, $app, $bank, $controller, $department, $expense) {
        $request = Request::create('/', 'POST', array_merge([
            'type' => $type,
            'entry_context' => 'payment_entry',
            'amount' => $amount,
            'date' => now()->toDateString(),
            'time' => '12:00',
            'contra_account_id' => $expense->id,
            'expense_department_id' => $type === 'withdraw' ? $department->id : null,
            'reference' => $reference,
            'purpose' => 'Payment Entry integration check',
        ], $override));
        $request->setUserResolver(fn () => $actor);
        $app->instance('request', $request);

        return $controller->recordTransaction($request, 'super-admin', $bank)->getData(true);
    };

    // Seed disposable funds inside the same transaction, then exercise both
    // directions through the production controller. The final rollback keeps
    // the configured database byte-for-byte unchanged.
    $opening = JournalEntry::create([
        'company_id' => $bank->company_id ?: 2,
        'created_by' => $actor->id,
        'date' => now()->toDateString(),
        'source' => 'test',
        'reference' => 'PAYMENT-ENTRY-TEST-OPEN',
        'description' => 'Disposable payment-entry funds',
    ]);
    JournalItem::create(['journal_entry_id' => $opening->id, 'account_id' => $bank->account_id, 'debit' => 1000, 'credit' => 0]);
    JournalItem::create(['journal_entry_id' => $opening->id, 'account_id' => $expense->id, 'debit' => 0, 'credit' => 1000]);

    foreach ([['withdraw', 100.0], ['deposit', 40.0]] as [$type, $amount]) {
        $reference = 'PAYMENT-ENTRY-TEST-'.strtoupper($type);
        $result = $post($type, $amount, $reference);
        $assert(($result['success'] ?? false) === true, $type.' was refused');

        $entry = JournalEntry::where('reference', $reference)->with('items')->firstOrFail();
        $debits = round((float) $entry->items->sum('debit'), 2);
        $credits = round((float) $entry->items->sum('credit'), 2);
        $assert($debits === $credits, $type.' journal is not balanced');
        $assert($entry->items->contains('account_id', $bank->account_id), $type.' did not hit the selected money account');
        $assert($entry->items->contains('account_id', $expense->id), $type.' did not hit the selected balance account');
        $expenseLine = $entry->items->firstWhere('account_id', $expense->id);
        $assert(
            (int) ($expenseLine?->expense_department_id ?? 0) === ($type === 'withdraw' ? (int) $department->id : 0),
            $type.' stored the wrong department context'
        );
    }

    // A CA may manually select AP and a vendor. The central desk must retain
    // that head and post an unallocated party payment without forcing an
    // invoice/allocation choice or replacing AP with Supplier Advance.
    $vendor = User::role('vendor')->firstOrFail();
    $ap = Account::where('code', config('accounts.accounts_payable'))->firstOrFail();
    $manualReference = 'PAYMENT-ENTRY-TEST-MANUAL-AP';
    $manual = $post('withdraw', 25, $manualReference, [
        'contra_account_id' => $ap->id,
        'expense_department_id' => null,
        'beneficiary_party_id' => $vendor->id,
        'beneficiary' => $vendor->name,
        'allocation_mode' => 'unallocated',
    ]);
    $assert(($manual['success'] ?? false) === true, 'manual AP vendor payment was refused');
    $manualEntry = JournalEntry::where('reference', $manualReference)->with('items')->firstOrFail();
    $assert($manualEntry->items->contains('account_id', $ap->id), 'manual AP head was replaced');

    echo "Payment Entry integration checks passed: {$checks}\n";
} finally {
    DB::rollBack();
}
