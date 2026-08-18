<?php

namespace App\Traits;

use App\Models\Account;
use App\Models\Bank;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\Portal;
use App\Models\PortalBalance;
use App\Models\Transaction;

/**
 * Posts/reconciles the double-entry chart-of-accounts journal for a purchase
 * / vendor cost (debit a cost account, credit the paying account and/or
 * Accounts Payable). Generalized from the pattern TicketPurchaseController
 * originally hand-rolled, so any purchase-side module — ticket, visa, and
 * future ones — can share the same posting logic instead of re-implementing
 * it. Mirrors PostsSaleJournal but for the AP side.
 *
 * $paymentAccountId is an already-resolved chart-of-accounts account id for
 * whatever funded the paid portion (a bank's account_id, a portal's
 * account_id, etc) — callers resolve their own payment source so this trait
 * never needs to know about Bank/Portal models.
 */
trait PostsPurchaseJournal
{
    /**
     * Create the journal entry for a newly created purchase.
     */
    protected function createPurchaseJournal(
        string $source,
        int $sourceId,
        ?int $companyId,
        $date,
        string $reference,
        string $description,
        float $costAmount,
        float $paidAmount,
        float $dueAmount,
        ?int $paymentAccountId,
        string $costAccountCode
    ): void {
        $journal = JournalEntry::create([
            'company_id'  => $companyId,
            'created_by'  => auth()->id(),
            'date'        => $date,
            'reference'   => $reference,
            'source'      => $source,
            'source_id'   => $sourceId,
            'description' => $description,
        ]);

        $this->syncPurchaseJournalItems($journal, $reference, $costAmount, $paidAmount, $dueAmount, $paymentAccountId, $costAccountCode);
    }

    /**
     * Keep an existing purchase's journal entry in sync with its current
     * amounts. Creates the journal if it doesn't exist yet, or deletes it
     * entirely when $cancelled is true.
     */
    protected function updatePurchaseJournal(
        string $source,
        int $sourceId,
        ?int $companyId,
        $date,
        string $reference,
        string $description,
        float $costAmount,
        float $paidAmount,
        float $dueAmount,
        ?int $paymentAccountId,
        string $costAccountCode,
        bool $cancelled = false
    ): void {
        $journal = JournalEntry::where('source', $source)->where('source_id', $sourceId)->first();

        if ($cancelled) {
            if ($journal) {
                $journal->items()->delete();
                $journal->delete();
            }
            return;
        }

        if ($journal) {
            $journal->items()->delete();
            $journal->update(['date' => $date, 'description' => $description]);
        } else {
            $journal = JournalEntry::create([
                'company_id'  => $companyId,
                'created_by'  => auth()->id(),
                'date'        => $date,
                'reference'   => $reference,
                'source'      => $source,
                'source_id'   => $sourceId,
                'description' => $description,
            ]);
        }

        $this->syncPurchaseJournalItems($journal, $reference, $costAmount, $paidAmount, $dueAmount, $paymentAccountId, $costAccountCode);
    }

    /**
     * Remove a purchase's journal entry entirely (e.g. on delete).
     */
    protected function deletePurchaseJournal(string $source, int $sourceId): void
    {
        $journal = JournalEntry::where('source', $source)->where('source_id', $sourceId)->first();
        if ($journal) {
            $journal->items()->forceDelete();
            $journal->forceDelete();
        }
    }

    /**
     * Post a standalone debit-AP / credit-payment-account journal for
     * settling an existing purchase's due balance (e.g. a "pay vendor"
     * action).
     */
    protected function postPurchaseJournalPayment(
        string $source,
        int $sourceId,
        ?int $companyId,
        $date,
        string $reference,
        string $description,
        float $paymentAmount,
        int $paymentAccountId
    ): void {
        $payableAccount = Account::where('code', config('accounts.accounts_payable'))->firstOrFail();

        $journal = JournalEntry::create([
            'company_id'  => $companyId,
            'created_by'  => auth()->id(),
            'date'        => $date,
            'reference'   => $reference,
            'source'      => $source,
            'source_id'   => $sourceId,
            'description' => $description,
        ]);

        JournalItem::create([
            'journal_entry_id' => $journal->id,
            'account_id'       => $payableAccount->id,
            'debit'            => $paymentAmount,
            'credit'           => 0,
            'note'             => 'Payable cleared — ' . $reference,
        ]);

        JournalItem::create([
            'journal_entry_id' => $journal->id,
            'account_id'       => $paymentAccountId,
            'debit'            => 0,
            'credit'           => $paymentAmount,
            'note'             => 'Payment sent',
        ]);
    }

    /**
     * Keep the informal bank/portal balance movement for a purchase's paid
     * portion in sync — separate from the formal journal above, this is the
     * same `Transaction`/`PortalBalance` ledger every other module already
     * uses for party statements. Reverses whatever was previously recorded
     * for this source+id (crediting the balance back), then re-applies
     * against the current bank/portal and amount. Safe to call on every
     * create/update — idempotent, unlike a raw one-shot debit.
     */
    protected function reconcileCostPayment(
        string $source,
        int $sourceId,
        ?int $bankId,
        ?int $portalId,
        float $paidAmount,
        ?int $partyId,
        $date,
        string $method = 'cash'
    ): void {
        $this->reversePurchaseCostPayment($source, $sourceId);

        if ($paidAmount <= 0) {
            return;
        }

        if ($bankId) {
            $bank = Bank::findOrFail($bankId);
            $bankNewBalance = (float) $bank->balance - $paidAmount;

            // `old_balance`/`balance` are the VENDOR's own running party-ledger
            // balance (what PartyStatementController displays) — not the
            // bank's balance. The bank's own balance is tracked separately,
            // below.
            $partyOldBalance = $partyId
                ? (float) (Transaction::where('user_id', $partyId)->orderByDesc('id')->value('balance') ?? 0)
                : 0.0;
            $partyNewBalance = $partyOldBalance - $paidAmount;

            Transaction::create([
                'user_id'        => $partyId,
                'user_type'      => 'supplier',
                'type'           => $source,
                'account_id'     => $bankId,
                'payment_date'   => $date,
                'payment_method' => $method,
                'invoice_id'     => $sourceId,
                'old_balance'    => $partyOldBalance,
                'debit'          => 0,
                'credit'         => $paidAmount,
                'balance'        => $partyNewBalance,
                'remarks'        => 'Cost payment via bank.',
            ]);

            $bank->update(['balance' => $bankNewBalance]);
        } elseif ($portalId) {
            $portal = Portal::findOrFail($portalId);
            $opening = (float) $portal->balance;
            $new = $opening - $paidAmount;

            PortalBalance::create([
                'portal_id'       => $portalId,
                'invoice_id'      => $sourceId,
                'date'            => $date,
                'type'            => $source,
                'old_balance'     => $opening,
                'debit'           => 0,
                'credit'          => $paidAmount,
                'current_balance' => $new,
                'payment_method'  => $method,
                'remarks'         => 'Cost payment via portal.',
                'created_by'      => auth()->id(),
            ]);

            $portal->update(['balance' => $new]);
        }
    }

    /**
     * Reverse a purchase's recorded bank/portal cost payment, restoring the
     * balance and removing the row (e.g. on delete, or before re-applying
     * an edited payment in reconcileCostPayment()).
     */
    protected function reversePurchaseCostPayment(string $source, int $sourceId): void
    {
        // Scoped to the credit (payment) row only — a debit (liability) row
        // for the same invoice_id+type may also exist (see
        // reconcilePartyLedgerRow() callers) and must never be touched here.
        $txn = Transaction::where('invoice_id', $sourceId)->where('type', $source)->where('credit', '>', 0)->first();
        if ($txn) {
            Bank::find($txn->account_id)?->increment('balance', (float) $txn->credit);
            $txn->delete();
        }

        $pb = PortalBalance::where('invoice_id', $sourceId)->where('type', $source)->first();
        if ($pb) {
            Portal::find($pb->portal_id)?->increment('balance', (float) $pb->credit);
            $pb->delete();
        }
    }

    private function syncPurchaseJournalItems(
        JournalEntry $journal,
        string $reference,
        float $costAmount,
        float $paidAmount,
        float $dueAmount,
        ?int $paymentAccountId,
        string $costAccountCode
    ): void {
        $costAccount    = Account::where('code', $costAccountCode)->firstOrFail();
        $payableAccount = Account::where('code', config('accounts.accounts_payable'))->firstOrFail();

        $items = [];

        $items[] = [
            'account_id' => $costAccount->id,
            'debit'      => $costAmount,
            'credit'     => 0,
            'note'       => 'Cost incurred — ' . $reference,
        ];

        if ($paidAmount > 0 && $paymentAccountId) {
            $items[] = [
                'account_id' => $paymentAccountId,
                'debit'      => 0,
                'credit'     => $paidAmount,
                'note'       => 'Paid — ' . $reference,
            ];
        }

        if ($dueAmount > 0) {
            $items[] = [
                'account_id' => $payableAccount->id,
                'debit'      => 0,
                'credit'     => $dueAmount,
                'note'       => 'Amount owed — ' . $reference,
            ];
        }

        foreach ($items as $item) {
            JournalItem::create([...$item, 'journal_entry_id' => $journal->id]);
        }
    }
}
