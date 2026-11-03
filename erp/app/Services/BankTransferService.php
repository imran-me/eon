<?php

namespace App\Services;

use App\Models\Bank;
use App\Models\BankTransfer;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;

/**
 * Executing a bank transfer: the two Transaction rows, the two `balance`
 * columns and the contra journal entry.
 *
 * ── WHY THIS IS A SERVICE ─────────────────────────────────────────────────
 * Lifted verbatim out of BankTransferController's private processTransfer()
 * and createTransferJournal() when the Liability desk gained "settle by bank".
 * Settling an inter-company debt IS a bank transfer — Construction's account
 * to Travels' account — and the alternative was a second implementation of
 * money movement living in FinancingController.
 *
 * Two implementations of "move money between two accounts" is how the ledger
 * and the bank register start disagreeing, and the disagreement is only found
 * when a month is closed. One path, two callers.
 *
 * This is a pure extraction: the code below is what the bank transfer desk has
 * always run, moved, not rewritten. If it behaves differently for that desk,
 * that is a bug in the move, not an intended change.
 *
 * ── THIS DOES POST ────────────────────────────────────────────────────────
 * Unlike the Liability book itself, which records who owes whom and touches
 * nothing, a transfer is real money leaving one account and arriving in
 * another. It debits, it credits, and both bank balances move — exactly as it
 * does when raised from Manage Banks.
 */
class BankTransferService
{
    /**
     * Post a completed transfer: both sides of the movement and the journal.
     *
     * Caller is responsible for the surrounding DB::transaction. The balance
     * guard lives here so every caller checks under the same account lock and a
     * refusal rolls back the transfer row, register rows and journal together.
     */
    public function post(BankTransfer $transfer, Bank $fromBank): void
    {
        // Lock both register rows in a stable order. The ledger account lock in
        // assertCanCover() protects the real balance; these locks also prevent
        // concurrent transfers from losing updates in the legacy banks.balance
        // cache and transaction running-balance fields.
        $lockedBanks = Bank::query()
            ->whereIn('id', [$fromBank->id, $transfer->to_bank_id])
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $fromBank = $lockedBanks->get($fromBank->id);
        $toBank = $lockedBanks->get($transfer->to_bank_id);

        if (! $fromBank || ! $toBank) {
            throw new \RuntimeException('Source or destination bank account was not found.');
        }

        $chargeAmount = round((float) ($transfer->charge_amount ?? 0), 2);
        $vatAmount = round((float) ($transfer->vat_amount ?? 0), 2);
        $bankCut = round($chargeAmount + $vatAmount, 2);
        $sourceOutflow = round((float) $transfer->amount + $bankCut, 2);
        Bank::assertCanCover($fromBank->account_id, $sourceOutflow, $fromBank->name);
        $cutNote = $bankCut > 0
            ? ' | charge/VAT: ' . number_format($bankCut, 2)
            : '';

        // ── Debit: source bank ──────────────────────────────────────────────
        Transaction::create([
            'user_id'        => Auth::id(),
            'type'           => 'bank_transfer',
            'user_type'      => 'supplier',
            'account_id'     => $transfer->from_bank_id,
            'payment_date'   => $transfer->payment_date,
            'reference_no'   => $transfer->reference_no,
            'payment_method' => $transfer->payment_method,
            'invoice_id'     => $transfer->id,
            'old_balance'    => $fromBank->balance,
            'debit'          => $sourceOutflow,
            'credit'         => 0,
            'balance'        => $fromBank->balance - $sourceOutflow,
            'remarks'        => 'Transfer to: ' . $toBank->name . $cutNote . ' | ' . $transfer->remarks,
        ]);

        $fromBank->decrement('balance', $sourceOutflow);

        // ── Credit: destination bank ─────────────────────────────────────────
        $toBank->refresh();

        Transaction::create([
            'user_id'        => Auth::id(),
            'type'           => 'bank_transfer',
            'user_type'      => 'customer',
            'account_id'     => $transfer->to_bank_id,
            'payment_date'   => $transfer->payment_date,
            'reference_no'   => $transfer->reference_no,
            'payment_method' => $transfer->payment_method,
            'invoice_id'     => $transfer->id,
            'old_balance'    => $toBank->balance,
            'debit'          => 0,
            'credit'         => $transfer->amount,
            'balance'        => $toBank->balance + $transfer->amount,
            'remarks'        => 'Transfer from: ' . $fromBank->name . ' | ' . $transfer->remarks,
        ]);

        $toBank->increment('balance', $transfer->amount);

        $this->createTransferJournal($transfer, $fromBank, $toBank, $chargeAmount, $vatAmount, $sourceOutflow);
    }

    private function createTransferJournal(BankTransfer $transfer, Bank $fromBank, Bank $toBank, float $chargeAmount, float $vatAmount, float $sourceOutflow): void
    {
        if (!$fromBank->account_id) {
            throw new \Exception("Source bank '{$fromBank->name}' is not linked to a chart-of-accounts account.");
        }
        if (!$toBank->account_id) {
            throw new \Exception("Destination bank '{$toBank->name}' is not linked to a chart-of-accounts account.");
        }

        $journal = JournalEntry::create([
            // Left exactly as the bank desk has always written it, fallback and
            // all. bank_transfers has no company_id column, so this is always 2
            // in practice — a pre-existing quirk of that desk, not something
            // this extraction should quietly change while nobody is looking.
            'company_id'  => $transfer->company_id ?? 2,
            'created_by'  => auth()->id(),
            'date'        => $transfer->payment_date,
            'entry_time'  => $transfer->entry_time,
            'reference'   => $transfer->reference_no,
            'source'      => 'bank_transfer',
            'source_id'   => $transfer->id,
            'description' => 'Bank transfer — ' . $fromBank->name . ' → ' . $toBank->name
                . (($chargeAmount + $vatAmount) > 0 ? ' including charge/VAT' : ''),
        ]);

        // Debit: Destination bank (money received)
        JournalItem::create([
            'journal_entry_id' => $journal->id,
            'account_id'       => $toBank->account_id,
            'debit'            => $transfer->amount,
            'credit'           => 0,
            'note'             => 'Transfer received from ' . $fromBank->name,
        ]);

        // Credit: Source bank (money sent)
        JournalItem::create([
            'journal_entry_id' => $journal->id,
            'account_id'       => $fromBank->account_id,
            'debit'            => 0,
            'credit'           => $sourceOutflow,
            'note'             => 'Transfer sent to ' . $toBank->name,
        ]);

        foreach ([
            ['amount' => $chargeAmount, 'account' => $transfer->charge_account_id, 'label' => 'Bank charge'],
            ['amount' => $vatAmount, 'account' => $transfer->vat_account_id, 'label' => 'VAT'],
        ] as $extra) {
            if ($extra['amount'] <= 0) {
                continue;
            }

            JournalItem::create([
                'journal_entry_id' => $journal->id,
                'account_id'       => $extra['account'],
                'debit'            => $extra['amount'],
                'credit'           => 0,
                'note'             => $extra['label'] . ' on bank transfer from ' . $fromBank->name,
            ]);
        }
    }

    /**
     * The next free reference for a settlement raised from the Liability desk.
     * Prefixed so these are recognisable in the bank register as inter-company
     * settlements rather than ordinary transfers, and suffixed until unique
     * because reference_no carries a unique index.
     */
    public function settlementReference(int $liabilityId): string
    {
        $base = 'ICL-' . $liabilityId . '-' . now()->format('ymd');
        $ref = $base;
        $n = 1;

        while (BankTransfer::withTrashed()->where('reference_no', $ref)->exists()) {
            $ref = $base . '-' . (++$n);
        }

        return $ref;
    }
}
