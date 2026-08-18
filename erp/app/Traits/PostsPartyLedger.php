<?php

namespace App\Traits;

use App\Models\Bank;
use App\Models\Transaction;
use App\Models\User;

/**
 * Posts/reconciles a sale's rows on the shared party-statement ledger
 * (the `transactions` table), the same mechanism TicketSaleController
 * already uses for ticket sales, generalized for sale types that only
 * ever need two ledger rows per sale (one debit for the invoice total,
 * one credit for the cumulative amount paid) rather than one row per
 * payment event.
 */
trait PostsPartyLedger
{
    protected function resolvePartyUserType(User $party): string
    {
        return $party->hasRole('vendor') ? 'supplier' : 'customer';
    }

    protected function postPartyLedgerRow(int $partyId, array $attrs): Transaction
    {
        $last = Transaction::where('user_id', $partyId)->orderByDesc('id')->lockForUpdate()->first();
        $oldBalance = $last ? (float) $last->balance : 0;
        $newBalance = $oldBalance + (float) ($attrs['debit'] ?? 0) - (float) ($attrs['credit'] ?? 0);

        $party = User::find($partyId);

        return Transaction::create(array_merge([
            'user_id'     => $partyId,
            'user_type'   => $this->resolvePartyUserType($party),
            'old_balance' => $oldBalance,
            'balance'     => $newBalance,
        ], $attrs));
    }

    /**
     * Keep a sale's single debit (invoice total) or credit (amount paid) row
     * in sync with its current amount, cascading any change to every later
     * transaction on the same party's ledger so the running balance stays
     * correct. Creates the row if it doesn't exist yet and the amount is > 0.
     */
    protected function reconcilePartyLedgerRow(
        int $partyId,
        string $type,
        int $invoiceId,
        bool $isDebitRow,
        float $newAmount,
        array $extraAttrs = []
    ): void {
        $column = $isDebitRow ? 'debit' : 'credit';

        $row = Transaction::where('invoice_id', $invoiceId)
            ->where('type', $type)
            ->where($column, '>', 0)
            ->first();

        if (!$row) {
            if ($newAmount > 0) {
                $this->postPartyLedgerRow($partyId, array_merge([
                    'type'       => $type,
                    'account_id' => null,
                    'invoice_id' => $invoiceId,
                    'debit'      => $isDebitRow ? $newAmount : 0,
                    'credit'     => $isDebitRow ? 0 : $newAmount,
                ], $extraAttrs));
            }
            return;
        }

        $delta = $newAmount - (float) $row->$column;
        if ($delta == 0.0) {
            return;
        }

        $row->update(array_merge([
            $column   => $newAmount,
            'balance' => (float) $row->balance + ($isDebitRow ? $delta : -$delta),
        ], $extraAttrs));

        $shift = $isDebitRow ? $delta : -$delta;
        Transaction::where('user_id', $partyId)->where('id', '>', $row->id)->increment('old_balance', $shift);
        Transaction::where('user_id', $partyId)->where('id', '>', $row->id)->increment('balance', $shift);
    }

    /**
     * Keep a bank's running balance in sync with a sale's paid-amount ledger
     * row. Reverses whatever the previous credit row's bank/amount was, then
     * applies the new bank/amount — handles amount edits and bank changes in
     * one call. Call before reconcilePartyLedgerRow() overwrites the row.
     */
    protected function reconcilePartyBankBalance(string $type, int $invoiceId, ?int $newBankId, float $newPaidAmount): void
    {
        $creditRow = Transaction::where('invoice_id', $invoiceId)->where('type', $type)->where('credit', '>', 0)->first();
        $oldBankId = $creditRow->account_id ?? null;
        $oldAmount = $creditRow ? (float) $creditRow->credit : 0.0;

        if ($oldBankId && $oldAmount > 0) {
            Bank::find($oldBankId)?->decrement('balance', $oldAmount);
        }

        if ($newBankId && $newPaidAmount > 0) {
            Bank::find($newBankId)?->increment('balance', $newPaidAmount);
        }
    }

    protected function reversePartyLedger(string $type, int $invoiceId): void
    {
        $creditRow = Transaction::where('invoice_id', $invoiceId)->where('type', $type)->where('credit', '>', 0)->first();
        if ($creditRow && $creditRow->account_id && (float) $creditRow->credit > 0) {
            Bank::find($creditRow->account_id)?->decrement('balance', (float) $creditRow->credit);
        }

        Transaction::where('invoice_id', $invoiceId)->where('type', $type)->delete();
    }
}
