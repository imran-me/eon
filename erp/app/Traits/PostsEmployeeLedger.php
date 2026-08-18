<?php

namespace App\Traits;

use App\Models\EmployeeLedger;
use Illuminate\Database\Eloquent\Model;

/**
 * Posts rows to an employee's running-balance ledger (the `employee_ledger`
 * table) — salary earned, loan/advance disbursed, salary/loan/advance
 * recovered or paid out. Mirrors PostsPartyLedger's running-balance
 * mechanic, kept as a separate table/trait so it can't affect the existing
 * vendor/customer `transactions` ledger or its reports.
 */
trait PostsEmployeeLedger
{
    protected function postEmployeeLedgerRow(int $userId, array $attrs, ?Model $source = null): EmployeeLedger
    {
        $last = EmployeeLedger::where('user_id', $userId)->orderByDesc('id')->lockForUpdate()->first();
        $oldBalance = $last ? (float) $last->balance : 0;
        $newBalance = $oldBalance + (float) ($attrs['debit'] ?? 0) - (float) ($attrs['credit'] ?? 0);

        return EmployeeLedger::create(array_merge([
            'user_id'     => $userId,
            'old_balance' => $oldBalance,
            'balance'     => $newBalance,
            'created_by'  => auth()->id(),
        ], $attrs, $source ? [
            'source_type' => $source->getMorphClass(),
            'source_id'   => $source->getKey(),
        ] : []));
    }
}
