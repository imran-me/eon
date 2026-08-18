<?php

namespace App\Services;

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
    /**
     * Net movement of every row dated before $before — i.e. the balance
     * carried into a statement window. Positive is Dr (the party owes us),
     * negative is Cr (we owe the party).
     *
     * Returns 0 when no window start is given, since nothing precedes the
     * beginning of the ledger.
     */
    public function openingBalance(int $partyId, ?string $before = null, ?string $type = null): float
    {
        if (!$before) {
            return 0.0;
        }

        return (float) Transaction::where('user_id', $partyId)
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
    public function statement(int $partyId, ?string $from = null, ?string $to = null, ?string $type = null): array
    {
        $type = ($type && $type !== 'all') ? $type : null;

        $rows = Transaction::where('user_id', $partyId)
            ->when($from, fn ($q) => $q->whereDate('payment_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('payment_date', '<=', $to))
            ->when($type, fn ($q) => $q->where('type', $type))
            ->orderBy('payment_date')
            ->orderBy('id')
            ->get();

        $opening = $this->openingBalance($partyId, $from, $type);
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
    public function currentBalance(int $partyId): float
    {
        return (float) Transaction::where('user_id', $partyId)
            ->selectRaw('COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) as net')
            ->value('net');
    }

    /**
     * currentBalance() for many parties in one query, keyed by user_id.
     * Parties with no transactions are absent from the result.
     */
    public function currentBalances($partyIds): Collection
    {
        return Transaction::whereIn('user_id', $partyIds)
            ->groupBy('user_id')
            ->selectRaw('user_id, COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) as net')
            ->pluck('net', 'user_id')
            ->map(fn ($net) => (float) $net);
    }
}
