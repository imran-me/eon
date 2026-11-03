<?php

namespace App\Services\TicketTrail;

use App\Models\TicketChange;
use App\Models\TicketPurchase;
use App\Models\TicketSale;
use App\Services\TicketBaggage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Everything one Ticketing ticket has posted, read straight from the books
 * (owner, 2026-10-26: "where posted what amounts"): its seller's ledger rows,
 * the agent's rows, portal wallet rows, every journal entry with its account
 * lines, and the payment schedules — each tagged with the event that posted
 * it (the purchase, a payment to the seller, the sale, a change).
 *
 * Read-only. The keys it emits are what Rewriter compares before and after a
 * correction, so a row that comes back at the same amount reads "unchanged"
 * even though its id is new.
 */
class Footprint
{
    /** Party-ledger row types a Ticketing change posts, keyed on the change id. */
    public const CHANGE_TX_TYPES = [TicketChange::FEE_TYPE, TicketChange::FARE_TYPE, TicketChange::CHARGE_TYPE, TicketChange::SALE_REVERSAL_TYPE];

    /** Journal sources a Ticketing change posts, keyed on the change id. */
    public const CHANGE_JE_SOURCES = [TicketChange::FEE_TYPE, TicketChange::CHARGE_TYPE, TicketChange::SALE_REVERSAL_TYPE, TicketChange::PENALTY_SOURCE];

    /**
     * A contract flight posts per PASSENGER, and one column means three things:
     * invoice_id / source_id is the passenger on the package lines, the change on
     * the change lines and the service row on trainer / consultant costs. Each
     * type is read with its own key — mixing them pulls in other passengers' rows.
     */
    public const CF_PAX_TYPES = ['contract_flight_passenger_agent', 'contract_flight_passenger_agent_reversal', 'contract_flight_passenger_reissue'];

    public const CF_CHANGE_TYPES = ['contract_flight_passenger_change', 'contract_flight_airline_penalty', 'contract_flight_fare_difference',
        'contract_flight_price_revision', 'contract_flight_reissue_fee', 'contract_flight_reissue_fare', 'contract_flight_deport_cost',
        'contract_flight_service_refund', 'contract_flight_penalty_company', 'contract_flight_fare_company', 'contract_flight_passenger_change_fix'];

    public const CF_SERVICE_TYPE = 'contract_flight_service_cost';

    /** The passenger(s) this ticket is or was on, their flight changes and services. Empty when none. */
    public static function flightKeys(TicketPurchase $t): array
    {
        $none = ['pax' => [], 'changes' => [], 'services' => [], 'flight' => null];
        try {
            if (! Schema::hasTable('contract_flight_passenger_tickets')) {
                return $none;
            }
            $pax = DB::table('contract_flight_passenger_tickets')->where('ticket_purchase_id', $t->id)->pluck('contract_flight_passenger_id');
            // a cancelled ticket is unlinked — its passenger is still named on the change rows
            if (Schema::hasTable('ticket_changes')) {
                $pax = $pax->merge(DB::table('ticket_changes')->where('ticket_purchase_id', $t->id)->whereNotNull('contract_flight_passenger_id')->pluck('contract_flight_passenger_id'));
            }
            if (Schema::hasColumn('contract_flight_passenger_changes', 'previous_ticket_purchase_ids')) {
                $pax = $pax->merge(DB::table('contract_flight_passenger_changes')->whereJsonContains('previous_ticket_purchase_ids', (int) $t->id)->pluck('contract_flight_passenger_id'));
            }
            $pax = $pax->map(fn ($x) => (int) $x)->unique()->values()->all();
            if (! $pax) {
                return $none;
            }

            return [
                'pax' => $pax,
                'changes' => DB::table('contract_flight_passenger_changes')->whereIn('contract_flight_passenger_id', $pax)->pluck('id')->map(fn ($x) => (int) $x)->all(),
                'services' => Schema::hasTable('contract_flight_passenger_service_costs')
                    ? DB::table('contract_flight_passenger_service_costs')->whereIn('contract_flight_passenger_id', $pax)->pluck('id')->map(fn ($x) => (int) $x)->all() : [],
                'flight' => DB::table('contract_flight_passengers as p')->leftJoin('contract_flights as f', 'f.id', '=', 'p.contract_flight_id')->whereIn('p.id', $pax)->value('f.flight_number'),
            ];
        } catch (\Throwable) {
            return $none;
        }
    }

    /** The flight's rows of those passengers, each type on its own key. $type / $id: the table's columns. */
    private static function flightWhere($q, array $cf, string $type, string $id): void
    {
        if ($cf['pax']) {
            $q->orWhere(fn ($w) => $w->whereIn($type, self::CF_PAX_TYPES)->whereIn($id, $cf['pax']));
        }
        if ($cf['changes']) {
            $q->orWhere(fn ($w) => $w->whereIn($type, self::CF_CHANGE_TYPES)->whereIn($id, $cf['changes']));
        }
        if ($cf['services']) {
            $q->orWhere(fn ($w) => $w->where($type, self::CF_SERVICE_TYPE)->whereIn($id, $cf['services']));
        }
    }

    /** $alsoSales: invoices this ticket was just taken off — kept in view so a correction shows them reduced, not gone. */
    public static function of(TicketPurchase $t, array $alsoSales = []): array
    {
        $changes = Schema::hasTable('ticket_changes')
            ? TicketChange::where('ticket_purchase_id', $t->id)->orderBy('id')->get()
            : collect();
        $changeIds = $changes->pluck('id')->all();
        $ordinal = $changes->values()->mapWithKeys(fn ($c, $i) => [$c->id => $i + 1])->all();
        $item = DB::table('ticket_sale_items as i')->join('ticket_sales as s', 's.id', '=', 'i.ticket_sale_id')
            ->where('i.ticket_purchase_id', $t->id)->whereNull('s.deleted_at')
            ->orderByDesc('i.id')->first(['s.id', 's.invoice_no', 's.client_id', 's.total_amount', 's.paid_amount', 'i.price']);
        $saleId = $item ? (int) $item->id : null;
        $saleIds = array_values(array_unique(array_filter(array_merge([$saleId], array_map('intval', $alsoSales)))));
        // The refund rows are keyed on the purchase, not the change — they belong to
        // whichever change cancelled it (or to the Edit page's own cancel).
        $refundEvent = optional($changes->whereIn('kind', ['refund', 'void', 'return_cancel'])->last())->id;
        $eventOfChange = fn ($id) => 'change:'.($ordinal[$id] ?? $id);
        $cf = self::flightKeys($t);
        $cfType = fn (string $type) => in_array($type, self::CF_PAX_TYPES, true) || in_array($type, self::CF_CHANGE_TYPES, true) || $type === self::CF_SERVICE_TYPE;

        // ── party ledger rows ────────────────────────────────────────────
        $tx = DB::table('transactions')
            ->where(function ($q) use ($t, $changeIds, $saleIds, $cf) {
                $q->where(fn ($w) => $w->whereIn('type', ['ticket_purchase', 'ticket_purchase_refund', TicketBaggage::COST, TicketBaggage::CHARGE])->where('invoice_id', $t->id));
                if ($changeIds) {
                    $q->orWhere(fn ($w) => $w->whereIn('type', self::CHANGE_TX_TYPES)->whereIn('invoice_id', $changeIds));
                }
                if ($saleIds) {
                    $q->orWhere(fn ($w) => $w->where('type', 'ticket_sale')->whereIn('invoice_id', $saleIds));
                }
                self::flightWhere($q, $cf, 'type', 'invoice_id');
            })
            ->orderBy('payment_date')->orderBy('id')->get();

        // ── portal wallet rows ───────────────────────────────────────────
        $pb = Schema::hasTable('portal_balances') ? DB::table('portal_balances')
            ->where(function ($q) use ($t, $changeIds, $cf) {
                $q->where(fn ($w) => $w->whereIn('type', ['ticket_purchase', 'ticket_purchase_refund', TicketBaggage::COST, TicketBaggage::CHARGE])->where('invoice_id', $t->id));
                if ($changeIds) {
                    $q->orWhere(fn ($w) => $w->whereIn('type', [TicketChange::FEE_TYPE, TicketChange::FARE_TYPE])->whereIn('invoice_id', $changeIds));
                }
                if ($cf['changes']) {
                    $q->orWhere(fn ($w) => $w->whereIn('type', self::CF_CHANGE_TYPES)->whereIn('invoice_id', $cf['changes']));
                }
            })->orderBy('id')->get() : collect();

        // ── journals ─────────────────────────────────────────────────────
        $je = DB::table('journal_entries')->whereNull('deleted_at')
            ->where(function ($q) use ($t, $changeIds, $saleIds, $cf) {
                $q->where(fn ($w) => $w->whereIn('source', [TicketBaggage::COST, TicketBaggage::CHARGE, 'ticket_purchase', 'ticket_purchase_cancel', 'ticket_commission'])->where('source_id', $t->id));
                if ($changeIds) {
                    $q->orWhere(fn ($w) => $w->whereIn('source', self::CHANGE_JE_SOURCES)->whereIn('source_id', $changeIds));
                }
                if ($saleIds) {
                    $q->orWhere(fn ($w) => $w->where('source', 'ticket_sale')->whereIn('source_id', $saleIds));
                }
                self::flightWhere($q, $cf, 'source', 'source_id');
            })->orderBy('date')->orderBy('id')->get();
        $lines = $je->isEmpty() ? collect() : DB::table('journal_items as ji')->leftJoin('accounts as a', 'a.id', '=', 'ji.account_id')
            ->whereIn('ji.journal_entry_id', $je->pluck('id'))->whereNull('ji.deleted_at')
            ->orderBy('ji.id')->get(['ji.id', 'ji.journal_entry_id', 'ji.account_id', 'ji.debit', 'ji.credit', 'ji.note', 'ji.party_type', 'ji.party_id', 'a.code', 'a.name'])
            ->groupBy('journal_entry_id');
        $firstPurchaseJe = $je->where('source', 'ticket_purchase')->min('id');

        // ── names ────────────────────────────────────────────────────────
        $userIds = $tx->pluck('user_id')->merge($lines->flatten(1)->pluck('party_id'))->filter()->unique();
        $users = $userIds->isEmpty() ? collect() : DB::table('users')->whereIn('id', $userIds)->pluck('name', 'id');
        $banks = DB::table('banks')->whereIn('id', $tx->pluck('account_id')->filter()->unique())->pluck('name', 'id');
        $portals = $pb->isEmpty() ? collect() : DB::table('portals')->whereIn('id', $pb->pluck('portal_id')->unique())->pluck('name', 'id');

        $rows = [];
        foreach ($tx as $r) {
            $event = match (true) {
                $r->type === TicketBaggage::COST || $r->type === TicketBaggage::CHARGE => 'baggage',
                $r->type === 'ticket_purchase' && ! $r->account_id => 'purchase',
                $r->type === 'ticket_purchase' => 'payment',
                $r->type === 'ticket_purchase_refund' => $refundEvent ? $eventOfChange($refundEvent) : 'cancel',
                $r->type === 'ticket_sale' => $r->account_id ? 'receipt' : 'sale',
                $cfType($r->type) => 'flight',
                default => $eventOfChange((int) $r->invoice_id),
            };
            $rows[] = [
                'src' => 'tx', 'id' => (int) $r->id, 'event' => $event, 'type' => $r->type,
                'change_id' => in_array($r->type, self::CHANGE_TX_TYPES, true) ? (int) $r->invoice_id : ($r->type === 'ticket_purchase_refund' ? $refundEvent : null),
                'party_id' => $r->user_id ? (int) $r->user_id : null,
                'ledger' => 'party:'.$r->user_id, 'ledger_name' => $users[$r->user_id] ?? ('#'.$r->user_id),
                'ledger_kind' => $r->user_type === 'supplier' ? 'seller' : 'agent',
                'date' => substr((string) $r->payment_date, 0, 10), 'time' => self::time($r->transaction_time, $r->created_at),
                'ref' => $r->reference_no, 'remarks' => $r->remarks, 'bank' => $r->account_id ? ($banks[$r->account_id] ?? '#'.$r->account_id) : null,
                'bank_id' => $r->account_id ? (int) $r->account_id : null, 'method' => $r->payment_method,
                'debit' => round((float) $r->debit, 2), 'credit' => round((float) $r->credit, 2),
                // a sale row is the WHOLE invoice — this ticket is one line of it
                // (a flight's rows are the whole passenger's)
                'shared' => $r->type === 'ticket_sale' || $cfType($r->type),
            ];
        }
        foreach ($pb as $r) {
            $event = match (true) {
                $r->type === 'ticket_purchase' => 'purchase',
                $r->type === 'ticket_purchase_refund' => $refundEvent ? $eventOfChange($refundEvent) : 'cancel',
                $cfType($r->type) => 'flight',
                default => $eventOfChange((int) $r->invoice_id),
            };
            $rows[] = [
                'src' => 'pb', 'id' => (int) $r->id, 'event' => $event, 'type' => $r->type,
                'change_id' => $r->type === 'ticket_purchase' ? null : ($r->type === 'ticket_purchase_refund' ? $refundEvent : (int) $r->invoice_id),
                'party_id' => null, 'portal_id' => (int) $r->portal_id,
                'ledger' => 'portal:'.$r->portal_id, 'ledger_name' => ($portals[$r->portal_id] ?? '#'.$r->portal_id).' (wallet)',
                'ledger_kind' => 'portal',
                'date' => substr((string) ($r->date ?: $r->created_at), 0, 10), 'time' => self::time(null, $r->created_at),
                'ref' => $r->reference, 'remarks' => $r->remarks, 'bank' => null, 'bank_id' => null, 'method' => $r->payment_method,
                'debit' => round((float) $r->debit, 2), 'credit' => round((float) $r->credit, 2), 'shared' => $cfType($r->type),
            ];
        }

        $journals = [];
        foreach ($je as $j) {
            $event = match (true) {
                $j->source === 'ticket_purchase' && (int) $j->id === (int) $firstPurchaseJe => 'purchase',
                $j->source === 'ticket_purchase' => 'payment',
                $j->source === 'ticket_commission' => 'purchase',
                $j->source === TicketBaggage::COST || $j->source === TicketBaggage::CHARGE => 'baggage',
                $j->source === 'ticket_purchase_cancel' => $refundEvent ? $eventOfChange($refundEvent) : 'cancel',
                $j->source === 'ticket_sale' => 'sale',
                $cfType($j->source) => 'flight',
                default => $eventOfChange((int) $j->source_id),
            };
            $journals[] = [
                'id' => (int) $j->id, 'event' => $event, 'source' => $j->source,
                'change_id' => in_array($j->source, self::CHANGE_JE_SOURCES, true) ? (int) $j->source_id : ($j->source === 'ticket_purchase_cancel' ? $refundEvent : null),
                'date' => substr((string) $j->date, 0, 10), 'time' => $j->entry_time ? substr((string) $j->entry_time, 0, 5) : self::time(null, $j->created_at),
                'ref' => $j->reference, 'description' => $j->description, 'shared' => $j->source === 'ticket_sale' || $cfType($j->source),
                'lines' => $lines->get($j->id, collect())->map(fn ($l) => [
                    'account' => $l->code ? $l->code.' '.$l->name : '#'.$l->account_id, 'code' => $l->code,
                    'debit' => round((float) $l->debit, 2), 'credit' => round((float) $l->credit, 2),
                    'party' => $l->party_id ? ($users[$l->party_id] ?? '#'.$l->party_id) : null,
                ])->values()->all(),
            ];
        }

        $schedules = DB::table('payment_schedules')
            ->where(fn ($q) => $q->where(fn ($w) => $w->where('schedulable_type', TicketPurchase::class)->where('schedulable_id', $t->id))
                ->when($saleIds, fn ($q2) => $q2->orWhere(fn ($w) => $w->where('schedulable_type', TicketSale::class)->whereIn('schedulable_id', $saleIds))))
            ->orderBy('id')->get(['id', 'type', 'party_name', 'amount', 'paid_amount', 'scheduled_date', 'status', 'schedulable_type'])
            ->map(fn ($s) => [
                'id' => (int) $s->id, 'type' => $s->type, 'party' => $s->party_name, 'amount' => round((float) $s->amount, 2),
                'paid' => round((float) $s->paid_amount, 2), 'date' => substr((string) $s->scheduled_date, 0, 10), 'status' => $s->status,
                'shared' => $s->schedulable_type !== TicketPurchase::class,
            ])->all();

        return [
            'rows' => $rows,
            'journals' => $journals,
            'schedules' => $schedules,
            'invoice' => $item ? ['id' => (int) $item->id, 'no' => $item->invoice_no, 'client_id' => (int) $item->client_id,
                'total' => round((float) $item->total_amount, 2), 'paid' => round((float) $item->paid_amount, 2), 'price' => round((float) $item->price, 2),
                'tickets' => (int) DB::table('ticket_sale_items')->where('ticket_sale_id', $item->id)->count()] : null,
            'events' => array_merge(self::events($t, $changes),
                $je->whereIn('source', [TicketBaggage::COST, TicketBaggage::CHARGE])->isNotEmpty() ? [['key' => 'baggage', 'change_id' => null, 'kind' => 'baggage', 'label' => 'Extra baggage', 'at' => null]] : [],
                $cf['pax'] ? [['key' => 'flight', 'change_id' => null, 'kind' => 'flight', 'label' => 'Flight '.($cf['flight'] ?: '—').' · whole passenger', 'at' => null]] : []),
            'flight' => $cf['pax'] ? ['passengers' => $cf['pax'], 'flight' => $cf['flight'], 'changes' => count($cf['changes'])] : null,
        ];
    }

    /** Totals per ledger (party / wallet) and per account head — what the right side compares. */
    public static function totals(array $fp): array
    {
        $ledgers = [];
        foreach ($fp['rows'] as $r) {
            $k = $r['ledger'];
            $ledgers[$k] ??= ['ledger' => $k, 'name' => $r['ledger_name'], 'kind' => $r['ledger_kind'], 'debit' => 0.0, 'credit' => 0.0];
            $ledgers[$k]['debit'] = round($ledgers[$k]['debit'] + $r['debit'], 2);
            $ledgers[$k]['credit'] = round($ledgers[$k]['credit'] + $r['credit'], 2);
        }
        $accounts = [];
        foreach ($fp['journals'] as $j) {
            foreach ($j['lines'] as $l) {
                $k = $l['account'];
                $accounts[$k] ??= ['account' => $k, 'code' => $l['code'], 'debit' => 0.0, 'credit' => 0.0];
                $accounts[$k]['debit'] = round($accounts[$k]['debit'] + $l['debit'], 2);
                $accounts[$k]['credit'] = round($accounts[$k]['credit'] + $l['credit'], 2);
            }
        }
        ksort($accounts);

        return ['ledgers' => array_values($ledgers), 'accounts' => array_values($accounts)];
    }

    /** The ticket's events in order, as the Trail names them. */
    private static function events(TicketPurchase $t, Collection $changes): array
    {
        $out = [];
        foreach ($changes->values() as $i => $c) {
            $out[] = ['key' => 'change:'.($i + 1), 'change_id' => (int) $c->id, 'kind' => $c->kind,
                'label' => TicketChange::KINDS[$c->kind] ?? $c->kind, 'at' => $c->created_at?->format('Y-m-d H:i:s')];
        }
        // Cancelled from the Edit page (or a contract flight) with no change row behind it.
        if ($t->status === 'cancelled' && ! $changes->whereIn('kind', ['refund', 'void', 'return_cancel'])->count()) {
            $out[] = ['key' => 'cancel', 'change_id' => null, 'kind' => 'cancel', 'label' => 'Cancelled', 'at' => $t->cancelled_at ? Carbon::parse($t->cancelled_at)->format('Y-m-d H:i:s') : null];
        }

        return $out;
    }

    private static function time($explicit, $createdAt): ?string
    {
        if ($explicit) {
            return substr((string) $explicit, 0, 5);
        }

        return $createdAt ? substr((string) $createdAt, 11, 5) : null;
    }
}
