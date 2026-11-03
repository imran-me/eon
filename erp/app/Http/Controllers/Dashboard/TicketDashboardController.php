<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\PaymentSchedule;
use App\Models\Ticket;
use App\Models\TicketPurchase;
use App\Support\TicketCompanyScope;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ticket Dashboard (owner, 2026-10-04: "make a ticket dashboard, like we have
 * done in the contract flight").
 *
 * Built around how a BSP ticketing desk actually works, not around the
 * tables: the things that cost money if missed are on top —
 *
 *   holds running out      a hold dies at its time limit (TicketBooking::expires_at)
 *   void window            a BSP ticket voids free only until midnight of its issue day
 *   flying soon            ticket legs departing in the next 72 hours
 *   supplier payments      vendor payables overdue / due in 7 days (payment_schedules)
 *   idle tickets           bought, but neither sold nor on a contract flight — cost, no revenue
 *   cancellation cost      what the airlines kept this month (amount − refund)
 *
 * then a departures board, then insights (trend, airlines, suppliers, sector
 * fares, where tickets went), then the register with the standard filter bar.
 *
 * Read-only: nothing here writes. Every figure is traced to a column — the
 * page's comments name it. Tickets of the last 365 days are sent to the page
 * as plain rows and the insights are counted in the browser per period, so
 * switching 30 / 90 / 365 days needs no reload. Open supplier dues are summed
 * over ALL time server-side, because a payable does not stop being owed at
 * 365 days.
 */
class TicketDashboardController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view ticket purchase|view all ticket purchase|view ticket direct sale|view all ticket direct sale'),
        ];
    }

    public function index(Request $request)
    {
        // Same company reading as Ticket Orders (TicketDirectSaleController::index).
        $companyId = TicketCompanyScope::resolve($request, auth()->user());
        $now = now();
        $since = $now->copy()->subDays(365)->startOfDay();

        $tickets = $this->ticketRows($companyId, $since);
        $holds = $this->holdRows($companyId);

        return view('dashboard.ticket_dashboard.index', [
            'tickets' => $tickets,
            'holds' => $holds,
            'kpis' => $this->kpis($companyId, $tickets, $holds, $now),
            'radar' => $this->radar($companyId, $tickets, $holds, $now),
            'portals' => $this->portalWallets(),
            'now' => $now->toIso8601String(),
        ]);
    }

    /**
     * One flat row per ticket_purchases row issued in the window. A via route
     * is two purchases (leg_sequence 1 / 2); each row carries ITS leg's
     * sector and airline, read from the route's legs, because leg 2 is often
     * another carrier (DAC→IST Gulf Air, IST→ECN Turkish).
     */
    private function ticketRows(?int $companyId, CarbonInterface $since): array
    {
        $rows = DB::table('ticket_purchases as p')
            ->leftJoin('passport_holders as ph', 'ph.id', '=', 'p.passport_holder_id')
            ->leftJoin('users as v', 'v.id', '=', 'p.vendor_id')
            ->leftJoin('portals as po', 'po.id', '=', 'p.portal_id')
            ->whereNull('p.deleted_at')
            ->when($companyId, fn ($q) => $q->where('p.company_id', $companyId))
            ->where(fn ($q) => $q->where('p.purchase_date', '>=', $since->toDateString())->orWhere('p.created_at', '>=', $since))
            ->orderByDesc('p.purchase_date')->orderByDesc('p.id')
            ->get([
                'p.id', 'p.ticket_id', 'p.passport_holder_id', 'p.ticket_no', 'p.leg_sequence', 'p.order_group', 'p.purchase_date', 'p.created_at',
                'p.amount', 'p.paid_amount', 'p.due_amount', 'p.status', 'p.ticket_class', 'p.vendor_id', 'p.portal_id',
                Schema::hasColumn('ticket_purchases', 'refund_amount') ? 'p.refund_amount' : DB::raw('NULL as refund_amount'),
                // the entry's own Departure (2026-10-04) beats the leg date when set
                Schema::hasColumn('ticket_purchases', 'departure_at') ? 'p.departure_at' : DB::raw('NULL as departure_at'),
                'ph.name as passenger', 'ph.passport_no', 'v.name as vendor_name', 'po.name as portal_name', 'po.vendor_id as portal_vendor_id',
            ]);

        if ($rows->isEmpty()) {
            return [];
        }
        $ids = $rows->pluck('id')->all();

        // Travel date = the earliest leg date entered on the purchase.
        $travel = DB::table('ticket_legs')->whereIn('ticket_purchase_id', $ids)->whereNotNull('travel_date')
            ->groupBy('ticket_purchase_id')->selectRaw('ticket_purchase_id, MIN(travel_date) d')->pluck('d', 'ticket_purchase_id');

        // Where the ticket went: a contract flight passenger, a sale invoice, or nowhere yet.
        $flights = [];
        $cfInfo = [];   // airline flight no. and agent, for the board's journey header
        if (Schema::hasTable('contract_flight_passenger_tickets')) {
            foreach (DB::table('contract_flight_passenger_tickets as t')
                ->join('contract_flight_passengers as cp', 'cp.id', '=', 't.contract_flight_passenger_id')
                ->leftJoin('contract_flights as f', 'f.id', '=', 'cp.contract_flight_id')
                ->leftJoin('users as ag', 'ag.id', '=', 'cp.agent_id')
                ->whereIn('t.ticket_purchase_id', $ids)
                ->get(['t.ticket_purchase_id', 'f.flight_number', Schema::hasColumn('contract_flights', 'airline_flight_no') ? 'f.airline_flight_no' : DB::raw('NULL as airline_flight_no'), 'ag.name as agent']) as $l) {
                $flights[$l->ticket_purchase_id] = $l->flight_number ?: 'Contract flight';
                $cfInfo[$l->ticket_purchase_id] = ['flight_no' => $l->airline_flight_no, 'agent' => $l->agent];
            }
        }
        $sales = DB::table('ticket_sale_items as i')->join('ticket_sales as s', 's.id', '=', 'i.ticket_sale_id')
            ->whereIn('i.ticket_purchase_id', $ids)->whereNull('s.deleted_at')
            ->pluck('s.invoice_no', 'i.ticket_purchase_id');

        $routes = Ticket::withTrashed()->with(['airline', 'from_airport.country', 'to_airport.country'])
            ->whereIn('id', $rows->pluck('ticket_id')->filter()->unique())->get()->keyBy('id');
        $legsOf = [];
        $role = request()->route('role');

        return $rows->map(function ($r) use ($travel, $flights, $cfInfo, $sales, $routes, &$legsOf, $role) {
            $route = $r->ticket_id ? ($routes[$r->ticket_id] ?? null) : null;
            $legs = $route ? ($legsOf[$route->id] ??= $route->legsArray()) : [];
            $leg = ($r->leg_sequence && $legs) ? ($legs[$r->leg_sequence - 1] ?? null) : null;

            if ($leg) {
                $from = ['code' => $leg['from_code'], 'name' => $leg['from_name'], 'flag' => $leg['from_flag']];
                $to = ['code' => $leg['to_code'], 'name' => $leg['to_name'], 'flag' => $leg['to_flag']];
                $airline = $leg['airline'] ?: $route?->airline?->name;
            } else {
                $from = $this->airport($route?->from_airport);
                $to = $this->airport($route?->to_airport);
                $airline = $route?->airline?->name;
            }

            $cancelled = $r->status === 'cancelled';
            $refund = $cancelled && $r->refund_amount !== null ? (float) $r->refund_amount : null;
            $use = isset($flights[$r->id]) ? ['kind' => 'cf', 'label' => $flights[$r->id]]
                : (isset($sales[$r->id]) ? ['kind' => 'sale', 'label' => $sales[$r->id]] : ['kind' => 'idle', 'label' => null]);

            return [
                'id' => $r->id,
                // one passenger on one route = one group; legs 1, 2 sit together in the register
                'group' => $r->passport_holder_id.'|'.$r->ticket_id,
                'seq' => (int) ($r->leg_sequence ?: 1),
                'pnr' => $r->ticket_no,
                'passenger' => $r->passenger,
                'passport' => $r->passport_no,
                'issued' => $r->purchase_date ? substr((string) $r->purchase_date, 0, 10) : substr((string) $r->created_at, 0, 10),
                'issued_at' => $r->created_at ? Carbon::parse($r->created_at)->toIso8601String() : null,
                'travel' => $r->departure_at ? Carbon::parse($r->departure_at)->format('Y-m-d H:i') : (isset($travel[$r->id]) ? Carbon::parse($travel[$r->id])->format('Y-m-d H:i') : null),
                'airline' => $airline,
                'from' => $from,
                'to' => $to,
                'via' => ! $leg && count($legs) > 1 ? $route->viaLabel() : null,
                'leg' => $leg ? ($r->leg_sequence.'/'.count($legs)) : null,
                'class' => $r->ticket_class,
                'seller' => $r->vendor_name ?: $r->portal_name,
                'portal' => ! $r->vendor_name && $r->portal_name ? 1 : 0,
                // EP-VN-139 — the party code Party Statement knows the seller by (a portal shows its linked vendor's)
                'seller_id' => $r->vendor_id ?: $r->portal_vendor_id,
                'seller_code' => ($sid = $r->vendor_id ?: $r->portal_vendor_id) ? 'EP-VN-'.str_pad((string) $sid, 3, '0', STR_PAD_LEFT) : null,
                'cost' => (float) $r->amount,
                'paid' => (float) $r->paid_amount,
                'due' => $cancelled ? 0.0 : (float) $r->due_amount,
                'status' => $cancelled ? 'cancelled' : ($r->status === 'confirm' ? 'purchased' : $r->status),
                'refund' => $refund,
                'penalty' => $refund !== null ? max(0.0, round((float) $r->amount - $refund, 2)) : null,
                'use' => $cancelled ? ['kind' => 'cancelled', 'label' => null] : $use,
                'flight_no' => $cfInfo[$r->id]['flight_no'] ?? null,
                'agent' => $cfInfo[$r->id]['agent'] ?? null,
                'url' => $role ? route('role.ticket-direct-sale.edit', ['role' => $role, 'ticket_direct_sale' => $r->id]) : null,
            ];
        })->values()->all();
    }

    /** Holds not yet turned into a purchase — draft, pending, booked or expired. */
    private function holdRows(?int $companyId): array
    {
        if (! Schema::hasTable('ticket_bookings')) {
            return [];
        }
        $role = request()->route('role');
        $rows = DB::table('ticket_bookings as b')
            ->leftJoin('passport_holders as ph', 'ph.id', '=', 'b.passport_holder_id')
            ->leftJoin('tickets as t', 't.id', '=', 'b.ticket_id')
            ->leftJoin('airlines as al', 'al.id', '=', 't.airline_id')
            ->leftJoin('users as v', 'v.id', '=', 'b.vendor_id')
            ->leftJoin('portals as po', 'po.id', '=', 'b.portal_id')
            ->leftJoin('airports as fa', 'fa.id', '=', DB::raw('COALESCE(b.from_airport_id, t.from_airport_id)'))
            ->leftJoin('airports as ta', 'ta.id', '=', DB::raw('COALESCE(b.to_airport_id, t.to_airport_id)'))
            ->leftJoin('countries as fc', 'fc.id', '=', 'fa.country_id')
            ->leftJoin('countries as tc', 'tc.id', '=', 'ta.country_id')
            ->whereNull('b.deleted_at')->whereNull('b.confirmed_purchase_id')
            ->whereIn('b.status', ['draft', 'pending', 'booked', 'expired'])
            ->when($companyId, fn ($q) => $q->where(fn ($x) => $x->where('b.company_id', $companyId)->orWhereNull('b.company_id')))
            ->orderBy('b.expires_at')
            ->get([
                'b.id', 'b.booking_reference', 'b.pnr', 'b.status', 'b.expires_at', 'b.departure_at', 'b.created_at', 'b.quoted_amount',
                'ph.name as passenger', 'al.name as airline', 'v.name as vendor_name', 'po.name as portal_name',
                'fa.code as from_code', 'fa.name as from_name', 'fc.code as from_cc',
                'ta.code as to_code', 'ta.name as to_name', 'tc.code as to_cc',
            ]);

        return $rows->map(fn ($b) => [
            'id' => $b->id,
            'ref' => $b->booking_reference,
            'pnr' => $b->pnr,
            'passenger' => $b->passenger,
            'status' => $b->status,
            'expires' => $b->expires_at ? Carbon::parse($b->expires_at)->toIso8601String() : null,
            'travel' => $b->departure_at ? Carbon::parse($b->departure_at)->format('Y-m-d H:i') : null,
            'issued' => substr((string) $b->created_at, 0, 10),
            'airline' => $b->airline,
            'from' => ['code' => $b->from_code, 'name' => $b->from_name, 'flag' => strtolower((string) $b->from_cc)],
            'to' => ['code' => $b->to_code, 'name' => $b->to_name, 'flag' => strtolower((string) $b->to_cc)],
            'seller' => $b->vendor_name ?: $b->portal_name,
            'cost' => (float) $b->quoted_amount,
            'url' => $role ? route('role.ticket-bookings.show', ['role' => $role, 'ticket_booking' => $b->id]) : null,
        ])->values()->all();
    }

    private function kpis(?int $companyId, array $tickets, array $holds, CarbonInterface $now): array
    {
        $monthStart = $now->copy()->startOfMonth()->toDateString();
        $today = $now->toDateString();
        // Last month over the SAME days (1st → today's day number), never a
        // part-month against a whole one.
        $prevStart = $now->copy()->subMonthNoOverflow()->startOfMonth();
        $prevEnd = $prevStart->copy()->day(min($now->day, $prevStart->daysInMonth))->toDateString();
        $prevStart = $prevStart->toDateString();

        $mtd = array_filter($tickets, fn ($t) => $t['issued'] >= $monthStart && $t['issued'] <= $today);
        $prev = array_filter($tickets, fn ($t) => $t['issued'] >= $prevStart && $t['issued'] <= $prevEnd);
        $weekEnd = $now->copy()->addDays(7)->format('Y-m-d H:i');
        $nowStr = $now->copy()->startOfDay()->format('Y-m-d H:i');

        return [
            'issued_mtd' => count($mtd),
            'issued_prev' => count($prev),
            // Net cost: a cancelled ticket costs only what the airline kept.
            'cost_mtd' => round(array_sum(array_map(fn ($t) => $t['status'] === 'cancelled' ? (float) ($t['penalty'] ?? $t['cost']) : $t['cost'], $mtd)), 2),
            // Every open payable, any age — not limited to the 365-day rows.
            'supplier_due' => (float) DB::table('ticket_purchases')->whereNull('deleted_at')->where('status', '!=', 'cancelled')
                ->when($companyId, fn ($q) => $q->where('company_id', $companyId))->sum('due_amount'),
            'holds' => count(array_filter($holds, fn ($h) => $h['status'] === 'booked')),
            'flying_7d' => count(array_filter($tickets, fn ($t) => $t['status'] !== 'cancelled' && $t['travel'] && $t['travel'] >= $nowStr && $t['travel'] <= $weekEnd)),
        ];
    }

    private function radar(?int $companyId, array $tickets, array $holds, CarbonInterface $now): array
    {
        $in24 = $now->copy()->addDay();
        $booked = array_values(array_filter($holds, fn ($h) => $h['status'] === 'booked' && $h['expires']));
        $soon = array_values(array_filter($booked, fn ($h) => Carbon::parse($h['expires'])->between($now, $in24)));
        $today = $now->toDateString();
        $voidable = array_values(array_filter($tickets, fn ($t) => $t['status'] === 'purchased'
            && $t['issued_at'] && Carbon::parse($t['issued_at'])->toDateString() === $today));

        $from = $now->copy()->startOfDay()->format('Y-m-d H:i');
        $to = $now->copy()->addHours(72)->format('Y-m-d H:i');
        $flying = array_values(array_filter($tickets, fn ($t) => $t['status'] !== 'cancelled' && $t['travel'] && $t['travel'] >= $from && $t['travel'] <= $to));
        usort($flying, fn ($a, $b) => strcmp($a['travel'], $b['travel']));

        $idle = array_values(array_filter($tickets, fn ($t) => $t['status'] === 'purchased' && $t['use']['kind'] === 'idle'));
        $monthStart = $now->copy()->startOfMonth()->toDateString();
        $cancelled = array_values(array_filter($tickets, fn ($t) => $t['status'] === 'cancelled' && $t['issued'] >= $monthStart));

        return [
            'holds_24h' => count($soon),
            'holds_next' => $booked[0]['expires'] ?? null,
            'holds_all' => count($booked),
            'void_count' => count($voidable),
            'void_amount' => round(array_sum(array_column($voidable, 'cost')), 2),
            'void_deadline' => $now->copy()->endOfDay()->toIso8601String(),
            'flying_count' => count($flying),
            'flying_next' => $flying[0]['travel'] ?? null,
            'payables' => $this->payables($companyId, $now),
            'idle_count' => count($idle),
            'idle_amount' => round(array_sum(array_column($idle, 'cost')), 2),
            'cancel_count' => count($cancelled),
            'cancel_refund' => round(array_sum(array_map(fn ($t) => (float) ($t['refund'] ?? 0), $cancelled)), 2),
            'cancel_penalty' => round(array_sum(array_map(fn ($t) => (float) ($t['penalty'] ?? 0), $cancelled)), 2),
        ];
    }

    /** Ticket vendor payables on the payment schedule — what is overdue, and what falls due within 7 days. */
    private function payables(?int $companyId, CarbonInterface $now): array
    {
        try {
            $open = DB::table('payment_schedules')
                ->where('schedulable_type', TicketPurchase::class)->where('type', 'pay')
                ->whereIn('status', PaymentSchedule::OPEN_STATUSES)
                ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
                ->get(['amount', 'paid_amount', 'scheduled_date']);
        } catch (\Throwable) {
            return ['overdue' => 0.0, 'overdue_count' => 0, 'week' => 0.0, 'week_count' => 0];
        }
        $today = $now->toDateString();
        $week = $now->copy()->addDays(7)->toDateString();
        $left = fn ($s) => max(0.0, (float) $s->amount - (float) $s->paid_amount);
        $over = $open->filter(fn ($s) => $s->scheduled_date < $today);
        $soon = $open->filter(fn ($s) => $s->scheduled_date >= $today && $s->scheduled_date <= $week);

        return [
            'overdue' => round($over->sum($left), 2),
            'overdue_count' => $over->count(),
            'week' => round($soon->sum($left), 2),
            'week_count' => $soon->count(),
        ];
    }

    /** Portal wallets — a negative balance is money the portal is owed. */
    private function portalWallets(): array
    {
        try {
            return DB::table('portals')->whereNull('deleted_at')->where('status', 'active')
                ->orderBy('name')->get(['name', 'balance'])
                ->map(fn ($p) => ['name' => $p->name, 'balance' => (float) $p->balance])->all();
        } catch (\Throwable) {
            return [];
        }
    }

    private function airport($a): array
    {
        return [
            'code' => $a?->code,
            'name' => $a?->name,
            'flag' => strtolower((string) ($a?->country?->code ?? '')),
        ];
    }
}
