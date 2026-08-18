<?php

namespace App\Traits;

use App\Models\JournalEntry;
use App\Models\JournalItem;

/**
 * Posts a standard two-line balanced journal entry — one line debiting
 * $debitAccountId, one line crediting $creditAccountId, both for the same
 * $amount, under one JournalEntry header. Every settlement shape
 * PaymentScheduleController posts (ad-hoc AP/AR, employee salary/leave
 * encashment payoff, generic due-balance settlement) follows this exact
 * shape — the only things that ever differ between call sites are which
 * two accounts, the reference/description/notes, and the party. Kept as
 * its own trait (not folded into PostsSalaryJournal) since that trait
 * hardcodes the debit side to salary_expense specifically, which doesn't
 * fit ad-hoc AP/AR or settlement-side (already-expensed) postings.
 */
trait PostsBalancedJournal
{
    protected function postBalancedJournal(
        string $source,
        int $sourceId,
        ?int $companyId,
        $date,
        string $reference,
        string $description,
        int $debitAccountId,
        int $creditAccountId,
        float $amount,
        string $debitNote,
        string $creditNote,
        ?string $partyType = null,
        ?int $partyId = null
    ): JournalEntry {
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
            'account_id'       => $debitAccountId,
            'debit'            => $amount,
            'credit'           => 0,
            'note'             => $debitNote,
            'party_type'       => $partyType,
            'party_id'         => $partyId,
        ]);

        JournalItem::create([
            'journal_entry_id' => $journal->id,
            'account_id'       => $creditAccountId,
            'debit'            => 0,
            'credit'           => $amount,
            'note'             => $creditNote,
            'party_type'       => $partyType,
            'party_id'         => $partyId,
        ]);

        return $journal;
    }
}
