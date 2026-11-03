<?php

namespace App\Services;

use App\Models\JournalEntry;
use App\Models\Transaction;
use Illuminate\Support\Collection;

/**
 * Derives a party's running ledger balance from the debit/credit columns
 * rather than trusting the stored `transactions.balance` snapshot.
 *
 * `balance`/`old_balance` are written once, at insert time, by chaining off
 * the party's highest-`id` row — but statements are read back ordered by
 * `payment_date`. Those two orderings disagree the moment anything is
 * back-dated (a late opening balance, an edited sale, a re-posted payment),
 * and a row that lands out of sequence is silently dropped from the chain:
 * its amount still shows in the column totals but never reaches the running
 * balance, so the statement contradicts its own header figures.
 *
 * debit/credit are the only per-row values that cannot drift, so every
 * balance shown to a user is summed from them here. Insertion order stops
 * mattering, which makes the whole class of desync impossible instead of
 * merely repaired.
 */
class PartyLedgerService
{
    /** Match explicit bank-journal reversals; never infer a cancellation by amount alone. */
    public function markReversalPairs(Collection $rows): Collection
    {
        foreach ($rows as $row) {
            $row->reversal_pair_id = null;
        }
        // Explicit ledger-only correction of a duplicate receipt; the GL had
        // one receipt already, so no second journal is appropriate here.
        foreach ($rows->where('type', 'duplicate_posting_reversal') as $correction) {
            if (! preg_match('/^DUP-REV-(\d+)$/', (string) $correction->reference_no, $match)) {
                continue;
            }
            $original = $rows->firstWhere('id', (int) $match[1]);
            if ($original && ! $original->reversal_pair_id
                && (int) $original->user_id === (int) $correction->user_id
                && (int) $original->company_id === (int) $correction->company_id
                && $original->account_id == $correction->account_id
                && $original->invoice_id == $correction->invoice_id
                && $original->expense_department_id == $correction->expense_department_id
                && abs((float) $original->debit - (float) $correction->credit) < .005
                && abs((float) $original->credit - (float) $correction->debit) < .005) {
                $original->reversal_pair_id = $original->id;
                $correction->reversal_pair_id = $original->id;
            }
        }
        // A contract-flight package reversed (taken off / moved, 2026-09-28) and the
        // charge it cancels form a pair: Active shows only the live charge (owner,
        // 2026-10-26 — seeing the old CF-027 charge beside the new one read as "charged twice").
        foreach ($rows->where('type', 'contract_flight_passenger_agent_reversal')->where('credit', '>', 0)->sortBy('id') as $rev) {
            $charge = $rows->filter(fn ($r) => ! $r->reversal_pair_id && $r->id < $rev->id
                && (int) $r->invoice_id === (int) $rev->invoice_id && (int) $r->user_id === (int) $rev->user_id
                && in_array($r->type, ['contract_flight_passenger_agent', 'contract_flight_passenger_agent_reversal'], true)
                && abs((float) $r->debit - (float) $rev->credit) < .005)->sortBy('id')->first();
            if ($charge && ! $rev->reversal_pair_id) {
                $charge->reversal_pair_id = $charge->id;
                $rev->reversal_pair_id = $charge->id;
            }
        }
        $journals = JournalEntry::whereIn('reference', $rows->pluck('reference_no')->filter())
            ->get(['id', 'reference', 'company_id', 'reversed_journal_entry_id']);
        foreach ($journals->whereNotNull('reversed_journal_entry_id') as $reversal) {
            $original = $journals->firstWhere('id', $reversal->reversed_journal_entry_id);
            if (! $original) {
                continue;
            }
            // Legacy bank postings sometimes wrote the party row under a
            // different company from the journal. Use the explicit journal IDs
            // plus references, and require the two party rows to share scope.
            $originalRows = $rows->filter(fn ($row) => ! $row->reversal_pair_id
                && $row->reference_no === $original->reference
                && (int) $row->invoice_id === (int) $original->id);
            $reversalRows = $rows->filter(fn ($row) => $row->reference_no === $reversal->reference
                && (int) $row->invoice_id === (int) $reversal->id);
            foreach ($originalRows as $row) {
                $opposite = $reversalRows->first(fn ($candidate) => ! $candidate->reversal_pair_id
                    && (int) $candidate->user_id === (int) $row->user_id
                    && (int) $candidate->company_id === (int) $row->company_id
                    && $candidate->expense_department_id == $row->expense_department_id
                    && $candidate->account_id == $row->account_id
                    && abs((float) $candidate->debit - (float) $row->credit) < .005
                    && abs((float) $candidate->credit - (float) $row->debit) < .005);
                if ($opposite) {
                    $row->reversal_pair_id = $row->id;
                    $opposite->reversal_pair_id = $row->id;
                }
            }
        }

        return $rows;
    }

    /** Omit only complete neutral pairs; preserve period-edge adjustments. */
    public function activeRows(Collection $rows, float $opening = 0): Collection
    {
        $active = $this->markReversalPairs($rows)->filter(fn ($row) => ! $row->reversal_pair_id)->values();
        $running = $opening;
        foreach ($active as $row) {
            $row->old_balance = $running;
            $running += (float) $row->debit - (float) $row->credit;
            $row->balance = $running;
        }

        return $active;
    }

    /** First calendar day on which this scoped party ledger has an entry. */
    public function firstTransactionDate(int $partyId, ?string $type = null, ?int $companyId = null, ?int $projectId = null): ?string
    {
        $type = ($type && $type !== 'all') ? $type : null;

        $value = Transaction::where('user_id', $partyId)
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->when($projectId, fn ($q) => $q->where('expense_department_id', $projectId))
            ->when($type, fn ($q) => $q->where('type', $type))
            ->min('payment_date');

        return $value ? substr((string) $value, 0, 10) : null;
    }

    /**
     * Net movement of every row dated before $before — i.e. the balance
     * carried into a statement window. Positive is Dr (the party owes us),
     * negative is Cr (we owe the party).
     *
     * Returns 0 when no window start is given, since nothing precedes the
     * beginning of the ledger.
     */
    public function openingBalance(int $partyId, ?string $before = null, ?string $type = null, ?int $companyId = null, ?int $projectId = null): float
    {
        if (! $before) {
            return 0.0;
        }

        return (float) Transaction::where('user_id', $partyId)
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->when($projectId, fn ($q) => $q->where('expense_department_id', $projectId))
            ->whereDate('payment_date', '<', $before)
            ->when($type, fn ($q) => $q->where('type', $type))
            ->selectRaw('COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) as net')
            ->value('net');
    }

    /**
     * A party's ledger rows for the given window, each carrying a freshly
     * computed `old_balance`/`balance` pair, plus the window's opening and
     * closing balances.
     *
     * Rows come back in the same order the balances were accumulated
     * (`payment_date`, then `id` to break ties), so `->last()->balance` is
     * always the closing figure. The recomputed values are set on the model
     * instances for display only and are never persisted.
     *
     * @return array{0: Collection, 1: float, 2: float} [$rows, $opening, $closing]
     */
    public function statement(int $partyId, ?string $from = null, ?string $to = null, ?string $type = null, ?int $companyId = null, ?int $projectId = null): array
    {
        $type = ($type && $type !== 'all') ? $type : null;

        $rows = Transaction::where('user_id', $partyId)
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->when($projectId, fn ($q) => $q->where('expense_department_id', $projectId))
            ->when($from, fn ($q) => $q->whereDate('payment_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('payment_date', '<=', $to))
            ->when($type, fn ($q) => $q->where('type', $type))
            ->orderBy('payment_date')
            ->orderByRaw('COALESCE(transaction_time, TIME(created_at))')
            ->orderBy('id')
            ->get();

        $opening = $this->openingBalance($partyId, $from, $type, $companyId, $projectId);
        $running = $opening;

        foreach ($rows as $row) {
            $row->old_balance = $running;
            $running += (float) $row->debit - (float) $row->credit;
            $row->balance = $running;
        }

        return [$rows, $opening, $running];
    }

    /**
     * A party's balance across their whole ledger, ignoring any date window.
     */
    public function currentBalance(int $partyId, ?int $companyId = null): float
    {
        return (float) Transaction::where('user_id', $partyId)
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->selectRaw('COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) as net')
            ->value('net');
    }

    /**
     * currentBalance() for many parties in one query, keyed by user_id.
     * Parties with no transactions are absent from the result.
     */
    public function currentBalances($partyIds, ?int $companyId = null): Collection
    {
        return Transaction::whereIn('user_id', $partyIds)
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->groupBy('user_id')
            ->selectRaw('user_id, COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) as net')
            ->pluck('net', 'user_id')
            ->map(fn ($net) => (float) $net);
    }
}
