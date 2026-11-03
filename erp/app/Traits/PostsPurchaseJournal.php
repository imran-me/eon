<?php

namespace App\Traits;

use App\Models\Account;
use App\Models\Bank;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\Portal;
use App\Models\PortalBalance;
use App\Models\Transaction;
use App\Models\User;

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
        string $costAccountCode,
        ?string $partyType = null,
        ?int $partyId = null
    ): void {
        $partyName = $partyId ? User::whereKey($partyId)->value('name') : null;

        $journal = JournalEntry::create([
            'company_id'  => $companyId,
            'created_by'  => auth()->id(),
            'date'        => $date,
            'reference'   => $reference,
            'source'      => $source,
            'source_id'   => $sourceId,
            'description' => $description,
            'beneficiary' => $partyName,
            'beneficiary_party_id' => $partyId,
        ]);

        $this->syncPurchaseJournalItems($journal, $reference, $costAmount, $paidAmount, $dueAmount, $paymentAccountId, $costAccountCode, $partyType, $partyId);
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
        bool $cancelled = false,
        ?string $partyType = null,
        ?int $partyId = null
    ): void {
        // The ORIGINAL purchase journal is the lowest id. Later journals with
        // the same source/source_id are settlement postings from a "pay
        // vendor" action (postPurchaseJournalPayment) and must not be
        // rewritten as if they were the purchase itself.
        $journal = JournalEntry::where('source', $source)->where('source_id', $sourceId)->orderBy('id')->first();

        if ($cancelled) {
            JournalEntry::where('source', $source)->where('source_id', $sourceId)->get()->each(function ($entry) {
                $entry->items()->delete();
                $entry->delete();
            });
            return;
        }

        $partyName = $partyId ? User::whereKey($partyId)->value('name') : null;

        if ($journal) {
            $journal->items()->delete();
            $journal->update([
                'company_id' => $companyId,
                'date' => $date,
                'reference' => $reference,
                'description' => $description,
                'beneficiary' => $partyName,
                'beneficiary_party_id' => $partyId,
            ]);
        } else {
            $journal = JournalEntry::create([
                'company_id'  => $companyId,
                'created_by'  => auth()->id(),
                'date'        => $date,
                'reference'   => $reference,
                'source'      => $source,
                'source_id'   => $sourceId,
                'description' => $description,
                'beneficiary' => $partyName,
                'beneficiary_party_id' => $partyId,
            ]);
        }

        $this->syncPurchaseJournalItems($journal, $reference, $costAmount, $paidAmount, $dueAmount, $paymentAccountId, $costAccountCode, $partyType, $partyId);
    }

    /**
     * Remove a purchase's journal entry entirely (e.g. on delete).
     */
    protected function deletePurchaseJournal(string $source, int $sourceId): void
    {
        // Every journal for this source goes — the purchase's own AND any
        // settlement journals posted against it later. Removing only the
        // first left "Payable cleared" postings in the GL with no purchase
        // behind them once a settled purchase was deleted.
        JournalEntry::where('source', $source)->where('source_id', $sourceId)->get()->each(function ($journal) {
            $journal->items()->forceDelete();
            $journal->forceDelete();
        });
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
        int $paymentAccountId,
        ?string $partyType = null,
        ?int $partyId = null
    ): void {
        $payableAccount = Account::where('code', config('accounts.accounts_payable'))->firstOrFail();
        $paymentAccount = Account::findOrFail($paymentAccountId);
        $partyName = $partyId ? User::whereKey($partyId)->value('name') : null;

        Bank::assertCanCover(
            $paymentAccountId,
            $paymentAmount,
            $paymentAccount->name
        );

        $journal = JournalEntry::create([
            'company_id'  => $companyId,
            'created_by'  => auth()->id(),
            'date'        => $date,
            'reference'   => $reference,
            'source'      => $source,
            'source_id'   => $sourceId,
            'description' => $description,
            'beneficiary' => $partyName,
            'beneficiary_party_id' => $partyId,
        ]);

        JournalItem::create([
            'journal_entry_id' => $journal->id,
            'account_id'       => $payableAccount->id,
            'debit'            => $paymentAmount,
            'credit'           => 0,
            'note'             => 'Payable cleared — ' . $reference,
            'party_type'       => $partyType,
            'party_id'         => $partyId,
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
        string $costAccountCode,
        ?string $partyType = null,
        ?int $partyId = null
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
            $paymentAccount = Account::findOrFail($paymentAccountId);
            Bank::assertCanCover($paymentAccountId, $paidAmount, $paymentAccount->name);

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
                'party_type' => $partyType,
                'party_id'   => $partyId,
            ];
        }

        foreach ($items as $item) {
            JournalItem::create([...$item, 'journal_entry_id' => $journal->id]);
        }
    }
}
