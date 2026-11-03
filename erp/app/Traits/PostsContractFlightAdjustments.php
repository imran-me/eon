<?php

namespace App\Traits;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Append-only postings for contract flight changes (owner, 2026-09-28: "no
 * data should be deleted at all, upon cancellation, will be reversed, by
 * addition or minusing the amount").
 *
 * Every call ADDS a row and a journal; nothing here updates or removes an
 * earlier one. A correction is another call with the opposite sign.
 *
 *   postAgentAdjustment()  agent's Party Statement row + journal
 *                          Dr AR (agent) / Cr $contraCode     when $signed > 0
 *                          Dr $contraCode / Cr AR (agent)     when $signed < 0
 *   postCompanyJournal()   journal only — Dr $drCode / Cr $crCode. Used to
 *                          move an airline penalty or a fare difference out of
 *                          ticket cost (5110) onto the Company Ledger heads.
 *
 * The host class must also use PostsPartyLedger.
 */
trait PostsContractFlightAdjustments
{
    protected function postAgentAdjustment(
        int $agentId,
        string $type,
        int $invoiceId,
        float $signed,
        string $remarks,
        string $reference,
        ?string $contraCode,
        ?int $companyId
    ): ?Transaction {
        $signed = round($signed, 2);
        if (abs($signed) < 0.005) {
            return null;
        }
        $row = $this->postPartyLedgerRow($agentId, [
            'type'         => $type,
            'invoice_id'   => $invoiceId,
            'account_id'   => null,
            'payment_date' => now(),
            'reference_no' => $reference,
            'debit'        => $signed > 0 ? $signed : 0,
            'credit'       => $signed < 0 ? -$signed : 0,
            'remarks'      => mb_substr($remarks, 0, 250),
        ]);

        $ar = Account::where('code', config('accounts.accounts_receivable'))->value('id');
        $contra = $contraCode ? Account::where('code', $contraCode)->value('id') : null;
        if ($ar && $contra) {
            $amount = abs($signed);
            $entry = $this->newAdjustmentEntry($type, (int) $row->id, $companyId, $reference, $remarks, $agentId);
            JournalItem::create(['journal_entry_id' => $entry->id, 'account_id' => $signed > 0 ? $ar : $contra,
                'debit' => $amount, 'credit' => 0, 'note' => mb_substr($remarks, 0, 250),
                'party_type' => $signed > 0 ? 'agent' : null, 'party_id' => $signed > 0 ? $agentId : null]);
            JournalItem::create(['journal_entry_id' => $entry->id, 'account_id' => $signed > 0 ? $contra : $ar,
                'debit' => 0, 'credit' => $amount, 'note' => mb_substr($remarks, 0, 250),
                'party_type' => $signed > 0 ? null : 'agent', 'party_id' => $signed > 0 ? null : $agentId]);
        }

        return $row;
    }

    protected function postCompanyJournal(
        string $source,
        int $sourceId,
        ?string $drCode,
        ?string $crCode,
        float $amount,
        string $description,
        string $reference,
        ?int $companyId,
        ?int $agentId = null
    ): void {
        $amount = round($amount, 2);
        if ($amount < 0.005) {
            return;
        }
        $dr = $drCode ? Account::where('code', $drCode)->value('id') : null;
        $cr = $crCode ? Account::where('code', $crCode)->value('id') : null;
        if (! $dr || ! $cr) {
            return; // heads not set up on this server — the change itself still saves
        }
        $entry = $this->newAdjustmentEntry($source, $sourceId, $companyId, $reference, $description, $agentId);
        JournalItem::create(['journal_entry_id' => $entry->id, 'account_id' => $dr, 'debit' => $amount, 'credit' => 0,
            'note' => mb_substr($description, 0, 250), 'party_type' => $agentId ? 'agent' : null, 'party_id' => $agentId]);
        JournalItem::create(['journal_entry_id' => $entry->id, 'account_id' => $cr, 'debit' => 0, 'credit' => $amount,
            'note' => mb_substr($description, 0, 250)]);
    }

    private function newAdjustmentEntry(string $source, int $sourceId, ?int $companyId, string $reference, string $description, ?int $partyId): JournalEntry
    {
        return JournalEntry::create([
            'company_id'  => $companyId ?? Auth::user()?->company_id ?? 2,
            'created_by'  => Auth::id(),
            'date'        => now()->toDateString(),
            'entry_time'  => now()->format('H:i:s'),
            'reference'   => mb_substr($reference, 0, 190),
            'source'      => $source,
            'source_id'   => $sourceId,
            'description' => mb_substr($description, 0, 250),
            'beneficiary' => $partyId ? User::whereKey($partyId)->value('name') : null,
            'beneficiary_party_id' => $partyId,
        ]);
    }
}
