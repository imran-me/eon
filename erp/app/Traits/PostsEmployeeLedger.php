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

    /**
     * Rewrites old_balance/balance straight down a user's ledger, in id order.
     *
     * The running balance is stored on every row instead of being derived, so
     * the moment a row is edited or removed each later row still carries the
     * arithmetic of the old one — and the employee profile's "Company Owes"
     * reads the last row's balance. A payslip deleted on 2026-07-30 left
     * ৳85,750 of a since-removed row baked into an employee's opening balance
     * permanently, which is the failure this exists to stop.
     *
     * Call it after changing or deleting an existing row. Appending a new row
     * through postEmployeeLedgerRow() already carries the balance forward and
     * does not need it.
     */
    protected function rebuildEmployeeLedgerBalances(int $userId): void
    {
        $balance = 0.0;

        $rows = EmployeeLedger::where('user_id', $userId)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($rows as $row) {
            $openingBalance = $balance;
            $balance = round($openingBalance + (float) $row->debit - (float) $row->credit, 2);

            if ((float) $row->old_balance === $openingBalance && (float) $row->balance === $balance) {
                continue;
            }

            $row->forceFill([
                'old_balance' => $openingBalance,
                'balance'     => $balance,
            ])->saveQuietly();
        }
    }

    /**
     * Keeps the ledger row a source document owns in step with that document.
     *
     * Editing a payslip used to rewrite the salary row, its journal entry and
     * its payment schedule while leaving the ledger sitting on the original
     * figure, so from that point on the employee's balance and their payslips
     * disagreed. A missing row — deleted, or from before the ledger existed —
     * is appended rather than treated as an error, so an edit can never fail
     * because of ledger history that isn't there.
     */
    protected function syncEmployeeLedgerRow(Model $source, string $type, int $userId, array $attrs): void
    {
        $row = EmployeeLedger::where('source_type', $source->getMorphClass())
            ->where('source_id', $source->getKey())
            ->where('type', $type)
            ->orderBy('id')
            ->first();

        if (! $row) {
            $this->postEmployeeLedgerRow($userId, array_merge(['type' => $type], $attrs), $source);

            return;
        }

        $previousUserId = (int) $row->user_id;
        $row->update(array_merge(['user_id' => $userId], $attrs));

        $this->rebuildEmployeeLedgerBalances($userId);

        // Moving a payslip to a different employee leaves a hole in the old
        // one's running balance too.
        if ($previousUserId !== $userId) {
            $this->rebuildEmployeeLedgerBalances($previousUserId);
        }
    }
}
