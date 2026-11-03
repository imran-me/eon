<?php

namespace App\Traits;

use App\Models\Account;
use App\Models\Bank;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\User;

/**
 * Posts/reconciles the double-entry chart-of-accounts journal for a sale
 * (debit the paying bank's linked account and/or Accounts Receivable,
 * credit the sale type's revenue account). Generalized from the pattern
 * TicketSaleController originally hand-rolled, so any sale module — ticket,
 * flight, file, visa, and future ones — can share the same posting logic
 * instead of re-implementing it.
 */
trait PostsSaleJournal
{
    /**
     * Create the journal entry for a newly created sale.
     */
    protected function createSaleJournal(
        string $source,
        int $sourceId,
        ?int $companyId,
        $date,
        string $reference,
        string $description,
        float $totalAmount,
        float $paidAmount,
        float $dueAmount,
        ?int $bankId,
        string $revenueAccountCode,
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

        $this->syncSaleJournalItems($journal, $reference, $totalAmount, $paidAmount, $dueAmount, $bankId, $revenueAccountCode, $partyType, $partyId);
    }

    /**
     * Keep an existing sale's journal entry in sync with its current amounts.
     * Creates the journal if it doesn't exist yet, or deletes it entirely
     * when $cancelled is true.
     */
    protected function updateSaleJournal(
        string $source,
        int $sourceId,
        ?int $companyId,
        $date,
        string $reference,
        string $description,
        float $totalAmount,
        float $paidAmount,
        float $dueAmount,
        ?int $bankId,
        string $revenueAccountCode,
        bool $cancelled = false,
        ?string $partyType = null,
        ?int $partyId = null
    ): void {
        // The first entry is the sale recognition. Later entries for the same
        // source are receipts which clear AR and must not be rewritten on edit.
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

        $this->syncSaleJournalItems($journal, $reference, $totalAmount, $paidAmount, $dueAmount, $bankId, $revenueAccountCode, $partyType, $partyId);
    }

    /**
     * Remove a sale's journal entry entirely (e.g. on delete).
     */
    protected function deleteSaleJournal(string $source, int $sourceId): void
    {
        JournalEntry::where('source', $source)->where('source_id', $sourceId)->get()->each(function ($journal) {
            $journal->items()->forceDelete();
            $journal->forceDelete();
        });
    }

    /**
     * Post a standalone debit-bank / credit-AR journal for a later due
     * payment against an existing sale (e.g. a "make payment" action).
     */
    protected function postSaleJournalPayment(
        string $source,
        int $sourceId,
        ?int $companyId,
        $date,
        string $reference,
        string $description,
        float $paymentAmount,
        int $bankId,
        ?string $partyType = null,
        ?int $partyId = null
    ): JournalEntry {
        $bank = Bank::find($bankId);
        if (!$bank || !$bank->account_id) {
            throw new \Exception('Bank is not linked to a chart-of-accounts account.');
        }

        $receivableAccount = Account::where('code', config('accounts.accounts_receivable'))->firstOrFail();
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

        JournalItem::create([
            'journal_entry_id' => $journal->id,
            'account_id'       => $bank->account_id,
            'debit'            => $paymentAmount,
            'credit'           => 0,
            'note'             => 'Payment received',
        ]);

        JournalItem::create([
            'journal_entry_id' => $journal->id,
            'account_id'       => $receivableAccount->id,
            'debit'            => 0,
            'credit'           => $paymentAmount,
            'note'             => 'AR cleared — ' . $reference,
            'party_type'       => $partyType,
            'party_id'         => $partyId,
        ]);

        return $journal;
    }

    private function syncSaleJournalItems(
        JournalEntry $journal,
        string $reference,
        float $totalAmount,
        float $paidAmount,
        float $dueAmount,
        ?int $bankId,
        string $revenueAccountCode,
        ?string $partyType = null,
        ?int $partyId = null
    ): void {
        $revenueAccount    = Account::where('code', $revenueAccountCode)->firstOrFail();
        $receivableAccount = Account::where('code', config('accounts.accounts_receivable'))->firstOrFail();

        $items = [];

        if ($paidAmount > 0 && $bankId) {
            $bank = Bank::find($bankId);
            if (!$bank || !$bank->account_id) {
                throw new \Exception('Bank is not linked to a chart-of-accounts account.');
            }
            $items[] = [
                'account_id' => $bank->account_id,
                'debit'      => $paidAmount,
                'credit'     => 0,
                'note'       => 'Cash received — ' . $reference,
            ];
        }

        if ($dueAmount > 0) {
            $items[] = [
                'account_id' => $receivableAccount->id,
                'debit'      => $dueAmount,
                'credit'     => 0,
                'note'       => 'Amount due — ' . $reference,
                'party_type' => $partyType,
                'party_id'   => $partyId,
            ];
        }

        $items[] = [
            'account_id' => $revenueAccount->id,
            'debit'      => 0,
            'credit'     => $totalAmount,
            'note'       => 'Sales revenue — ' . $reference,
        ];

        foreach ($items as $item) {
            JournalItem::create([...$item, 'journal_entry_id' => $journal->id]);
        }
    }
}
