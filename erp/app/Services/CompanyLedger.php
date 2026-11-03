<?php

namespace App\Services;

use App\Models\Account;
use App\Models\ContractFlightPassengerChange;
use App\Models\ContractFlightSuspendWithdrawal;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The Company Ledger (owner, 2026-09-28): what contract-flight changes cost
 * the company and what they earned it, passenger by passenger.
 *
 *   Dr  what we paid out — the airline penalty kept on a cancelled ticket,
 *       a fare difference on a reissue           (5170 group: 5171, 5172, 5173)
 *   Cr  what came back / what we earned — the penalty recovered from the
 *       agent (4181, 4182) and our own service fee (4183)
 *   Profit = Cr − Dr.  Penalty recovered cancels its own cost; the fee is profit.
 *
 * Not a party. The company's side lives in the journal, on every account
 * under the two groups. Read-only; every entry point degrades to "not
 * installed" rather than throw — the dashboard card calls it on every visit.
 */
class CompanyLedger
{
    /** ['borne' => [id => Account], 'recovered' => [id => Account]] or null when the heads are missing. */
    public static function accounts(): ?array
    {
        try {
            $borne = self::leavesUnder((string) config('accounts.company_borne_group'));
            $recovered = self::leavesUnder((string) config('accounts.company_recoveries_group'));
        } catch (\Throwable) {
            return null;
        }
        if (! $borne && ! $recovered) {
            return null;
        }

        return ['borne' => $borne, 'recovered' => $recovered];
    }

    public static function available(): bool
    {
        return self::accounts() !== null;
    }

    /**
     * Journal lines on the company heads, oldest first, each with the
     * running profit after it (Σ Cr − Σ Dr) and the passenger it belongs to.
     * $from/$to are Y-m-d or null; lines before $from fold into 'opening'.
     */
    public static function entries(?string $from = null, ?string $to = null): array
    {
        $acc = self::accounts();
        if (! $acc) {
            return ['installed' => false, 'rows' => collect(), 'opening' => 0.0, 'totals' => self::zero(), 'passengers' => collect()];
        }
        $feeCode = (string) config('accounts.cf_change_recovered');
        $side = [];
        foreach ($acc['borne'] as $id => $a) {
            $side[$id] = 'borne';
        }
        foreach ($acc['recovered'] as $id => $a) {
            $side[$id] = $a->code === $feeCode ? 'fee' : 'recovered';
        }
        $names = collect($acc['borne'] + $acc['recovered'])->map(fn ($a) => $a->code.' '.$a->name);

        $originalHeads = array_keys($side);
        $baggageHeads = Account::whereIn('code', [config('accounts.ticket_purchase_cost'), config('accounts.ticket_sales_revenue')])->get();
        foreach ($baggageHeads as $head) {
            $side[$head->id] = $head->code === config('accounts.ticket_purchase_cost') ? 'borne' : 'recovered';
            $names[$head->id] = $head->code.' '.$head->name;
        }

        $base = fn () => DB::table('journal_items as i')
            ->join('journal_entries as e', 'e.id', '=', 'i.journal_entry_id')
            ->where(fn ($q) => $q->whereIn('i.account_id', $originalHeads)
                ->orWhere(fn ($b) => $b->where('e.company_id', TicketingProfit::COMPANY_ID)
                    ->whereIn('e.source', [TicketBaggage::COST, TicketBaggage::CHARGE])->whereIn('i.account_id', $baggageHeads->pluck('id'))))
            ->whereNull('i.deleted_at')
            ->whereNull('e.deleted_at');

        $opening = 0.0;
        if ($from) {
            foreach ($base()->where('e.date', '<', $from)->groupBy('i.account_id')
                ->selectRaw('i.account_id, SUM(i.debit) d, SUM(i.credit) c')->get() as $r) {
                $opening += (float) $r->d - (float) $r->c; // net cost: borne − recovered
            }
        }

        $q = $base()->select('i.id', 'i.account_id', 'i.debit', 'i.credit', 'i.note', 'i.party_id',
            'e.id as entry_id', 'e.date', 'e.entry_time', 'e.reference', 'e.source', 'e.source_id',
            'e.description', 'e.reversed_journal_entry_id', 'e.beneficiary_party_id');
        if ($from) {
            $q->where('e.date', '>=', $from);
        }
        if ($to) {
            $q->where('e.date', '<=', $to);
        }
        $lines = $q->orderBy('e.date')->orderBy('e.id')->orderBy('i.id')->get();

        $pax = self::passengersFor($lines);
        $partyNames = User::whereIn('id', $lines->map(fn ($l) => $l->party_id ?: $l->beneficiary_party_id)->filter()->unique())->pluck('name', 'id');

        $running = $opening;
        $totals = self::zero();
        $rows = $lines->map(function ($l) use (&$running, &$totals, $side, $names, $partyNames, $pax) {
            $s = $side[$l->account_id];
            $d = (float) $l->debit;
            $c = (float) $l->credit;
            $running += $d - $c; // net cost so far (borne − recovered)
            $totals['dr'] += $d;
            $totals['cr'] += $c;
            $totals[$s] += $s === 'borne' ? $d - $c : $c - $d;
            $who = $pax[$l->entry_id] ?? null;
            $partyId = $l->party_id ?: $l->beneficiary_party_id;

            return [
                'id' => $l->id,
                'entry_id' => $l->entry_id,
                'date' => $l->date,
                'time' => $l->entry_time,
                'reference' => $l->reference,
                'details' => $l->note ?: $l->description,
                'head' => $names[$l->account_id] ?? '',
                'side' => $s === 'borne' ? 'borne' : 'recovered',
                'passenger' => $who['name'] ?? null,
                'pax_id' => $who['id'] ?? null,
                'flight' => $who['flight'] ?? null,
                'party' => $partyId ? ($partyNames[$partyId] ?? null) : null,
                'reversal' => (bool) $l->reversed_journal_entry_id,
                'dr' => $d,
                'cr' => $c,
                // The table's own view (owner, 2026-09-28: "the previous
                // version was great"): Borne / Recovered / Net cost. Our fee
                // is money recovered, so it sits under Recovered here; profit
                // is shown in Profit & Funds below, not in this table.
                'borne' => $s === 'borne' ? $d - $c : 0.0,
                'recovered' => $s === 'borne' ? 0.0 : $c - $d,
                'fee' => $s === 'fee',
                'balance' => $running,
            ];
        });
        $totals['profit'] = $totals['cr'] - $totals['dr'];
        $totals['recovered'] += $totals['fee'];
        $totals['net'] = $totals['borne'] - $totals['recovered'];

        // Passenger-wise: what each passenger's changes cost (Dr), brought
        // back (penalty recovered + our fee, Cr), and left as profit.
        $passengers = $rows->groupBy(fn ($r) => $r['pax_id'] ?: 'none')->map(function ($g) {
            $first = $g->first();
            $dr = $g->sum('dr');
            $cr = $g->sum('cr');

            return [
                'passenger' => $first['passenger'] ?: 'Not linked to a passenger',
                'flight' => $g->pluck('flight')->filter()->unique()->implode(', '),
                'party' => $g->pluck('party')->filter()->unique()->implode(', '),
                'borne' => $g->where('side', 'borne')->sum(fn ($r) => $r['dr'] - $r['cr']),
                'recovered' => $g->where('side', 'recovered')->where('fee', false)->sum(fn ($r) => $r['cr'] - $r['dr']),
                'fee' => $g->where('fee', true)->sum(fn ($r) => $r['cr'] - $r['dr']),
                'dr' => $dr,
                'cr' => $cr,
                'profit' => $cr - $dr,
                'last' => $g->max('date'),
            ];
        })->sortByDesc('last')->values();

        return ['installed' => true, 'rows' => $rows, 'opening' => $opening, 'totals' => $totals, 'passengers' => $passengers];
    }

    /** Dashboard card: this month's figures, all-time profit, last five lines. */
    public static function summary(): array
    {
        try {
            $all = self::entries();
            if (! $all['installed']) {
                return ['installed' => false];
            }
            $monthStart = now()->startOfMonth()->toDateString();
            $month = $all['rows']->filter(fn ($r) => (string) $r['date'] >= $monthStart);

            return [
                'installed' => true,
                'month' => ['borne' => $month->sum('borne'), 'recovered' => $month->sum('recovered')],
                'net' => $all['totals']['net'],
                'count' => $all['rows']->count(),
                'recent' => $all['rows']->reverse()->take(5)->values(),
            ];
        } catch (\Throwable) {
            return ['installed' => false];
        }
    }

    /**
     * entry id → passenger, read from what posted the entry:
     *   change-keyed sources (charge, penalty / fare reclass): source_id = change id
     *   agent adjustment rows (old separate penalty / fare rows): source_id =
     *     the Transaction, whose invoice_id is the change id
     */
    private static function passengersFor($lines): array
    {
        $changeSources = [
            ContractFlightPassengerChange::LEDGER_TYPE,
            ContractFlightPassengerChange::PENALTY_COMPANY_SOURCE,
            ContractFlightPassengerChange::FARE_COMPANY_SOURCE,
            ContractFlightPassengerChange::REISSUE_FEE_TYPE,
            ContractFlightPassengerChange::FIX_SOURCE,
        ];
        $rowSources = [ContractFlightPassengerChange::PENALTY_LEDGER_TYPE, ContractFlightPassengerChange::FARE_LEDGER_TYPE];
        $entryToChange = [];
        $txIds = [];
        foreach ($lines as $l) {
            if (in_array($l->source, $changeSources, true)) {
                $entryToChange[$l->entry_id] = (int) $l->source_id;
            } elseif (in_array($l->source, $rowSources, true)) {
                $txIds[$l->entry_id] = (int) $l->source_id;
            }
        }
        if ($txIds) {
            $inv = DB::table('transactions')->whereIn('id', array_values($txIds))->pluck('invoice_id', 'id');
            foreach ($txIds as $entryId => $txId) {
                if (isset($inv[$txId])) {
                    $entryToChange[$entryId] = (int) $inv[$txId];
                }
            }
        }
        $out = self::ticketChangePassengers($lines);
        // Suspend withdraw (2026-11-02): source_id = the withdrawal, which names its passenger.
        $sw = $lines->filter(fn ($line) => $line->source === ContractFlightSuspendWithdrawal::VENDOR_TYPE);
        if ($sw->isNotEmpty()) {
            try {
                $who = DB::table('contract_flight_suspend_withdrawals as w')
                    ->join('contract_flight_passengers as p', 'p.id', '=', 'w.contract_flight_passenger_id')
                    ->leftJoin('passport_holders as h', 'h.id', '=', 'p.passport_holder_id')
                    ->leftJoin('contract_flights as f', 'f.id', '=', 'w.contract_flight_id')
                    ->whereIn('w.id', $sw->pluck('source_id')->unique()->all())
                    ->get(['w.id', 'p.id as pax_id', 'h.name', 'f.flight_number'])->keyBy('id');
                foreach ($sw as $line) {
                    if ($i = $who->get($line->source_id)) {
                        $out[$line->entry_id] = ['id' => (int) $i->pax_id, 'name' => $i->name ?: 'Passenger #'.$i->pax_id, 'flight' => $i->flight_number];
                    }
                }
            } catch (\Throwable) {
                // table missing on a lagging server — the lines stay unlinked
            }
        }
        $baggage = $lines->filter(fn ($line) => in_array($line->source, [TicketBaggage::COST, TicketBaggage::CHARGE], true));
        if ($baggage->isNotEmpty()) {
            $holders = DB::table('ticket_purchases as p')->leftJoin('passport_holders as h', 'h.id', '=', 'p.passport_holder_id')
                ->whereIn('p.id', $baggage->pluck('source_id'))->get(['p.id', 'h.id as holder_id', 'h.name', 'p.ticket_no'])->keyBy('id');
            foreach ($baggage as $line) {
                if ($holder = $holders->get($line->source_id)) {
                    $out[$line->entry_id] = ['id' => -1 * (int) ($holder->holder_id ?: $holder->id), 'name' => $holder->name ?: 'Passenger', 'flight' => $holder->ticket_no];
                }
            }
        }
        if (! $entryToChange) {
            return $out;
        }
        try {
            $info = DB::table('contract_flight_passenger_changes as c')
                ->join('contract_flight_passengers as p', 'p.id', '=', 'c.contract_flight_passenger_id')
                ->leftJoin('passport_holders as h', 'h.id', '=', 'p.passport_holder_id')
                ->leftJoin('contract_flights as f', 'f.id', '=', 'c.from_flight_id')
                ->leftJoin('contract_flights as t', 't.id', '=', 'c.to_flight_id')
                ->whereIn('c.id', array_unique(array_values($entryToChange)))
                ->get(['c.id', 'p.id as pax_id', 'h.name', 'f.flight_number as from_no', 't.flight_number as to_no'])
                ->keyBy('id');
        } catch (\Throwable) {
            return $out;
        }
        foreach ($entryToChange as $entryId => $changeId) {
            if ($i = $info->get($changeId)) {
                $out[$entryId] = ['id' => (int) $i->pax_id, 'name' => $i->name ?: 'Passenger #'.$i->pax_id,
                    'flight' => $i->to_no ?: $i->from_no];
            }
        }

        return $out;
    }

    /**
     * Ticket Orders → Change entries (2026-10-06): source_id = ticket_changes.id;
     * the passenger is the ticket's holder, the "flight" its PNR.
     */
    private static function ticketChangePassengers($lines): array
    {
        $sources = ['ticket_change_fee', 'ticket_change_charge', 'ticket_change_penalty'];
        $map = [];
        foreach ($lines as $l) {
            if (in_array($l->source, $sources, true)) {
                $map[$l->entry_id] = (int) $l->source_id;
            }
        }
        if (! $map) {
            return [];
        }
        try {
            $info = DB::table('ticket_changes as c')
                ->join('ticket_purchases as p', 'p.id', '=', 'c.ticket_purchase_id')
                ->leftJoin('passport_holders as h', 'h.id', '=', 'p.passport_holder_id')
                ->whereIn('c.id', array_unique(array_values($map)))
                ->get(['c.id', 'h.id as holder_id', 'h.name', 'p.ticket_no'])->keyBy('id');
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($map as $entryId => $changeId) {
            if ($i = $info->get($changeId)) {
                // Negative id keeps a ticket holder apart from a flight passenger id.
                $out[$entryId] = ['id' => -1 * (int) ($i->holder_id ?: $changeId), 'name' => $i->name ?: 'Passenger', 'flight' => $i->ticket_no];
            }
        }

        return $out;
    }

    private static function zero(): array
    {
        return ['dr' => 0.0, 'cr' => 0.0, 'borne' => 0.0, 'recovered' => 0.0, 'fee' => 0.0, 'profit' => 0.0, 'net' => 0.0];
    }

    /** Every account under $code (inclusive), keyed by id. */
    private static function leavesUnder(string $code): array
    {
        $root = $code !== '' ? Account::where('code', $code)->first() : null;
        if (! $root) {
            return [];
        }
        $out = [$root->id => $root];
        $frontier = [$root->id];
        while ($frontier) {
            $kids = Account::whereIn('parent_id', $frontier)->get();
            $frontier = [];
            foreach ($kids as $k) {
                if (! isset($out[$k->id])) {
                    $out[$k->id] = $k;
                    $frontier[] = $k->id;
                }
            }
        }

        return $out;
    }
}
