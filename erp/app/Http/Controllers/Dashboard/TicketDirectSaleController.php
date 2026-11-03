<?php

namespace App\Http\Controllers\Dashboard;

use App\Exceptions\TicketChangeRefused;
use App\Http\Controllers\TicketController;
use App\Jobs\SendTicketBookingNotification;
use App\Models\Account;
use App\Models\Airline;
use App\Models\Airport;
use App\Models\Bank;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\PassportHolder;
use App\Models\PassportHolderCategory;
use App\Models\PaymentSchedule;
use App\Models\Portal;
use App\Models\PortalBalance;
use App\Models\Ticket;
use App\Models\TicketBooking;
use App\Models\TicketChange;
use App\Models\TicketLeg;
use App\Models\TicketPurchase;
use App\Models\TicketRefund;
use App\Models\TicketReissue;
use App\Models\TicketSale;
use App\Models\TicketSaleItem;
use App\Models\Transaction;
use App\Models\User;
use App\Services\PartyAssignmentService;
use App\Services\ReturnCancel;
use App\Services\TicketBaggage;
use App\Services\TicketRouteDefaults;
use App\Support\TicketCompanyScope;
use App\Support\TravelCompany;
use App\Traits\PostsPartyLedger;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * TicketDirectSaleController
 *
 * The multi-passenger PURCHASE builder — the front door of the merged
 * "Ticket Purchase" screen (routes/web.php gives it the ticket-purchase.index
 * and ticket-purchase.create names too; the "ticket-direct-sale" route
 * family and the "direct_sale" ticket_purchases.source value are what remain
 * of its earlier, no-longer-true name — it never books a sale). One
 * submission creates one ticket_purchases row per passenger row (cost side
 * only); nothing is written to ticket_sales here. A confirmed purchase is
 * sold later from Manage Sales (TicketSaleController).
 *
 * show()/update()/destroy() for an existing row live on
 * TicketPurchaseController — the single implementation both route families
 * use — along with the make_payment() due-settlement flow. This class keeps
 * only what is unique to the builder: bulk create, the quick "add a route"
 * helper, invoice lookup, and refund/re-issue.
 *
 * Accounting rules enforced (create only):
 *   Purchase recognition : Dr TicketCost / Cr Bank-or-Portal (paid) / Cr AP (due)
 *   Sale recognition     : Dr Bank (received) / Dr AR (due) / Cr Revenue — refund/re-issue only
 *   AP settlement        : Dr AP / Cr Bank   — via TicketPurchaseController::make_payment only
 *   AR settlement        : Dr Bank / Cr AR   — via TicketSaleController::make_payment only
 */
class TicketDirectSaleController implements HasMiddleware
{
    use PostsPartyLedger;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view ticket direct sale|view all ticket direct sale|view ticket purchase|view all ticket purchase', only: ['index', 'show']),
            new Middleware('permission:create ticket direct sale|create ticket purchase', only: ['create', 'store']),
            new Middleware('permission:edit ticket direct sale|edit ticket purchase', only: ['edit', 'update']),
            new Middleware('permission:delete ticket direct sale|delete ticket purchase', only: ['destroy']),
            // Sold to "+": the Customer screen's own permission (CustomerRoleController::store)
            new Middleware('permission:create customer', only: ['quickCreateClient']),
        ];
    }

    /** Ticketing is EPAL TRAVELS' module: a client made from Ticket Entry belongs to it. */
    private const TRAVELS_COMPANY_ID = TravelCompany::ID;

    // =========================================================================
    // INDEX
    // =========================================================================

    public function index(Request $request)
    {
        $companyId = TicketCompanyScope::resolve($request, auth()->user());
        $bookings = DB::table('ticket_bookings as b')
            ->leftJoin('passport_holders as ph', 'ph.id', '=', 'b.passport_holder_id')->leftJoin('tickets as t', 't.id', '=', 'b.ticket_id')
            ->leftJoin('users as v', 'v.id', '=', 'b.vendor_id')->leftJoin('portals as po', 'po.id', '=', 'b.portal_id')->leftJoin('users as cu', 'cu.id', '=', 'b.created_by')
            ->whereNull('b.deleted_at')->whereNull('b.confirmed_purchase_id')->when($companyId, fn ($q) => $q->where(fn ($x) => $x->where('b.company_id', $companyId)->orWhereNull('b.company_id')))
            ->leftJoin('airports as fa', 'fa.id', '=', 'b.from_airport_id')->leftJoin('airports as ta', 'ta.id', '=', 'b.to_airport_id')
            ->selectRaw("'booking' order_type, b.id record_id, b.booking_reference reference, b.pnr secondary_reference, ph.name passenger_name, t.title route_name, b.ticket_id, COALESCE(v.name,po.name) supplier_name, b.created_at order_date, b.status, b.expires_at, b.quoted_amount amount, b.currency, cu.name created_by_name, NULL paid_amount, NULL due_amount, NULL payment_status, COALESCE(b.order_group, CONCAT('b', b.id)) order_group, b.leg_sequence, CONCAT_WS(' → ', fa.code, ta.code) sector");
        $purchases = DB::table('ticket_purchases as p')
            ->leftJoin('passport_holders as ph', 'ph.id', '=', 'p.passport_holder_id')->leftJoin('tickets as t', 't.id', '=', 'p.ticket_id')
            ->leftJoin('users as v', 'v.id', '=', 'p.vendor_id')->leftJoin('portals as po', 'po.id', '=', 'p.portal_id')->leftJoin('users as cu', 'cu.id', '=', 'p.created_by')->leftJoin('ticket_bookings as b', 'b.confirmed_purchase_id', '=', 'p.id')
            ->whereNull('p.deleted_at')->when($companyId, fn ($q) => $q->where('p.company_id', $companyId))
            ->selectRaw("'purchase' order_type, p.id record_id, COALESCE(b.booking_reference,p.ticket_no) reference, p.ticket_no secondary_reference, ph.name passenger_name, t.title route_name, p.ticket_id, COALESCE(v.name,po.name) supplier_name, p.created_at order_date, CASE WHEN p.status='confirm' THEN 'purchased' ELSE p.status END status, NULL expires_at, p.amount, p.currency, cu.name created_by_name, p.paid_amount, p.due_amount, p.payment_status, COALESCE(p.order_group, b.order_group, CONCAT('p', p.id)) order_group, COALESCE(p.leg_sequence, b.leg_sequence) leg_sequence, NULL sector");
        if (TicketBaggage::ready() && TicketBaggage::ready('ticket_bookings')) {
            foreach (TicketBaggage::FIELDS as $field) {
                $bookings->addSelect('b.'.$field);
                $purchases->addSelect('p.'.$field);
            }
        }
        // Why a contract flight cancelled a ticket (2026-09-26) — shown under
        // its Cancelled badge. Both halves of the union get the column, and
        // only once it exists, so a server that has not migrated is unchanged.
        if (Schema::hasColumn('ticket_purchases', 'cancel_note')) {
            $bookings->addSelect(DB::raw('NULL as cancel_note'));
            $purchases->addSelect('p.cancel_note');
        }
        // What the airline refunded on a cancelled ticket (2026-09-28) — the
        // row keeps its amount, so the refund is shown instead of "Due".
        if (Schema::hasColumn('ticket_purchases', 'refund_amount')) {
            $bookings->addSelect(DB::raw('NULL as refund_amount'));
            $purchases->addSelect('p.refund_amount');
        }
        // Agent price and profit per ticket (owner, 2026-10-05: "the tables
        // should show profit, like we are doing in the contract flight"). The
        // price is what its live sale bills the agent; a ticket on a contract
        // flight is billed per head on the flight, so it names the flight instead.
        $bookings->addSelect(DB::raw('NULL as sale_price, NULL as sale_buyer, NULL as sale_buyer_id, NULL as cf_flight, NULL as cf_flight_id, NULL as cf_agent, NULL as cf_agent_id'));
        $purchases->addSelect(
            DB::raw("(SELECT SUM(si.price) FROM ticket_sale_items si JOIN ticket_sales s ON s.id = si.ticket_sale_id WHERE si.ticket_purchase_id = p.id AND s.deleted_at IS NULL AND s.status <> 'cancelled') as sale_price"),
            DB::raw("(SELECT u.name FROM ticket_sale_items si JOIN ticket_sales s ON s.id = si.ticket_sale_id JOIN users u ON u.id = s.client_id WHERE si.ticket_purchase_id = p.id AND s.deleted_at IS NULL AND s.status <> 'cancelled' ORDER BY si.id DESC LIMIT 1) as sale_buyer"),
            DB::raw("(SELECT s.client_id FROM ticket_sale_items si JOIN ticket_sales s ON s.id = si.ticket_sale_id WHERE si.ticket_purchase_id = p.id AND s.deleted_at IS NULL AND s.status <> 'cancelled' ORDER BY si.id DESC LIMIT 1) as sale_buyer_id"),
            DB::raw('(SELECT f.flight_number FROM contract_flight_passenger_tickets x JOIN contract_flight_passengers cp ON cp.id = x.contract_flight_passenger_id LEFT JOIN contract_flights f ON f.id = cp.contract_flight_id WHERE x.ticket_purchase_id = p.id LIMIT 1) as cf_flight'),
            DB::raw('(SELECT cp.contract_flight_id FROM contract_flight_passenger_tickets x JOIN contract_flight_passengers cp ON cp.id = x.contract_flight_passenger_id WHERE x.ticket_purchase_id = p.id LIMIT 1) as cf_flight_id'),
            DB::raw('(SELECT u.name FROM contract_flight_passenger_tickets x JOIN contract_flight_passengers cp ON cp.id = x.contract_flight_passenger_id JOIN users u ON u.id = cp.agent_id WHERE x.ticket_purchase_id = p.id LIMIT 1) as cf_agent'),
            DB::raw('(SELECT cp.agent_id FROM contract_flight_passenger_tickets x JOIN contract_flight_passengers cp ON cp.id = x.contract_flight_passenger_id WHERE x.ticket_purchase_id = p.id LIMIT 1) as cf_agent_id')
        );
        // The redesigned rows (2026-10-05): passport, e-ticket, flight and
        // date, the airline for the logo pill, and the seller's id for its
        // EP-VN code. Each column only once it exists, so a server that has
        // not migrated still renders.
        $hasCol = fn (string $t, string $c) => Schema::hasColumn($t, $c);
        $bookings->addSelect('ph.passport_no', 'b.vendor_id', 'b.portal_id');
        $purchases->addSelect('ph.passport_no', 'p.vendor_id', 'p.portal_id');
        foreach (['name_ticket_no' => 'eticket', 'flight_no' => 'flight_no', 'departure_at' => 'departure_at'] as $col => $as) {
            $bookings->addSelect($hasCol('ticket_bookings', $col) ? DB::raw("b.{$col} as {$as}") : DB::raw("NULL as {$as}"));
            $purchases->addSelect($hasCol('ticket_purchases', $col) ? DB::raw("p.{$col} as {$as}") : DB::raw("NULL as {$as}"));
        }
        $legAir = fn (string $a) => Ticket::legsAvailable()
            ? "(SELECT al.name FROM ticket_route_legs rl JOIN airlines al ON al.id = rl.airline_id WHERE rl.ticket_id = {$a}.ticket_id AND rl.sequence = {$a}.leg_sequence LIMIT 1)"
            : 'NULL';
        $vcAir = fn (string $t, string $a) => $hasCol($t, 'validating_carrier_id')
            ? "(SELECT al.name FROM airlines al WHERE al.id = {$a}.validating_carrier_id)" : 'NULL';
        $bookings->addSelect(DB::raw('COALESCE('.$legAir('b').', '.$vcAir('ticket_bookings', 'b').', (SELECT al.name FROM airlines al WHERE al.id = t.airline_id)) as airline'));
        $purchases->addSelect(DB::raw('COALESCE('.$legAir('p').', '.$vcAir('ticket_purchases', 'p').', (SELECT al.name FROM airlines al WHERE al.id = t.airline_id)) as airline'));
        // What happened to each ticket after it was bought (2026-10-06): the
        // scenario tiles and the row's history line read these. A change made
        // from Ticket Orders (ticket_changes), from a contract flight's card
        // (its change rows name the ticket in previous_ticket_purchase_ids —
        // free text, so matched by REGEXP: one malformed row must not break
        // the page the way JSON_CONTAINS would) or the old invoice re-issue.
        // Each source only once its table exists.
        $hasTable = fn (string $t) => Schema::hasTable($t);
        $tc = $hasTable('ticket_changes');
        $cfc = $hasTable('contract_flight_passenger_changes');
        $tcHas = fn (string $kinds) => $tc ? "EXISTS (SELECT 1 FROM ticket_changes tc WHERE tc.ticket_purchase_id = p.id AND tc.kind IN ({$kinds}))" : '0';
        $cfHas = fn (string $kinds) => $cfc ? "EXISTS (SELECT 1 FROM contract_flight_passenger_changes cc WHERE cc.kind IN ({$kinds}) AND cc.previous_ticket_purchase_ids REGEXP CONCAT('(^|[^0-9])', p.id, '([^0-9]|$)'))" : '0';
        $legacyReissue = $hasTable('ticket_reissues')
            ? 'EXISTS (SELECT 1 FROM ticket_reissues lr WHERE lr.original_purchase_id = p.id'.($hasCol('ticket_reissues', 'deleted_at') ? ' AND lr.deleted_at IS NULL' : '').')'
            : '0';
        $tcSum = fn (string $col) => $tc ? "(SELECT COALESCE(SUM(tc.{$col}), 0) FROM ticket_changes tc WHERE tc.ticket_purchase_id = p.id)" : '0';
        // Earlier dates moved from Ticket Orders. A change routed through a
        // contract flight's Re-Issue already writes its own "New dates:" note
        // (wasDatesLine), so only the ones without a flight change row count.
        $bookings->addSelect(DB::raw('NULL as chg_was'));
        $purchases->addSelect(DB::raw($tc
            ? "(SELECT GROUP_CONCAT(DATE_FORMAT(tc.old_travel_at, '%d %b %Y') ORDER BY tc.id DESC SEPARATOR ' · ') FROM ticket_changes tc WHERE tc.ticket_purchase_id = p.id AND tc.old_travel_at IS NOT NULL AND tc.contract_flight_passenger_change_id IS NULL) as chg_was"
            : 'NULL as chg_was'));
        $bookings->addSelect(DB::raw('NULL as cf_pax_id, 0 as chg_reissue, 0 as chg_reschedule, 0 as chg_refund, 0 as chg_void, 0 as chg_cost, 0 as chg_charged, 0 as chg_reversed'));
        $purchases->addSelect(
            DB::raw('(SELECT x.contract_flight_passenger_id FROM contract_flight_passenger_tickets x WHERE x.ticket_purchase_id = p.id LIMIT 1) as cf_pax_id'),
            DB::raw("CASE WHEN {$tcHas("'reissue'")} OR {$cfHas("'ticket_reissue','ticket_reissue_addition'")} OR {$legacyReissue} THEN 1 ELSE 0 END as chg_reissue"),
            DB::raw("CASE WHEN {$tcHas("'reschedule'")} OR {$cfHas("'reschedule'")} THEN 1 ELSE 0 END as chg_reschedule"),
            // Cancelled splits in two, by what the airline kept: Refunded =
            // a penalty was kept, Void = everything came back. The two add
            // up to Cancelled, from whichever screen the ticket was cancelled.
            // Refunded = every other cancellation (an old one with no refund on
            // record too), so the two tiles always add up to Cancelled.
            DB::raw("CASE WHEN p.status = 'cancelled' AND NOT (".($hasCol('ticket_purchases', 'refund_amount') ? 'COALESCE(p.refund_amount >= p.amount, 0)' : '0')." OR {$tcHas("'void'")}) THEN 1 ELSE 0 END as chg_refund"),
            DB::raw("CASE WHEN p.status = 'cancelled' AND (".($hasCol('ticket_purchases', 'refund_amount') ? 'p.refund_amount >= p.amount' : '0')." OR {$tcHas("'void'")}) THEN 1 ELSE 0 END as chg_void"),
            DB::raw($tcSum('cost_added').' as chg_cost'),
            DB::raw($tcSum('charged').' as chg_charged'),
            DB::raw($tcSum('sale_reversed').' as chg_reversed')
        );
        $union = $bookings->unionAll($purchases);
        // group_date = newest ticket of the entry, so an entry's tickets sort together
        $groups = DB::query()->fromSub(clone $union, 'o2')->selectRaw('o2.order_group, MAX(o2.order_date) group_date')->groupBy('o2.order_group');
        $base = DB::query()->fromSub($union, 'orders')->joinSub($groups, 'g', 'g.order_group', '=', 'orders.order_group')->select('orders.*', 'g.group_date');
        // Every tile in one pass over the register.
        $counts = DB::query()->fromSub($union, 'o')->selectRaw("
            COUNT(*) a,
            SUM(o.order_type = 'booking' AND o.status = 'booked') bk,
            SUM(o.order_type = 'purchase') pu,
            SUM(o.order_type = 'purchase' AND o.status = 'purchased') iss,
            SUM(o.order_type = 'purchase' AND o.status = 'purchased' AND o.departure_at < NOW()) dep,
            SUM(o.status = 'expired') ex,
            SUM(o.status = 'cancelled') ca,
            SUM(o.chg_reissue = 1) ri,
            SUM(o.chg_reschedule = 1) rs,
            SUM(o.chg_refund = 1) rf,
            SUM(o.chg_void = 1) vo,
            SUM(o.order_type = 'purchase' AND o.status = 'purchased' AND o.due_amount > 0) du,
            SUM(CASE WHEN o.order_type = 'purchase' AND o.status = 'purchased' THEN o.due_amount ELSE 0 END) du_amt,
            SUM(o.order_type = 'purchase' AND o.status = 'purchased' AND o.cf_pax_id IS NULL AND o.sale_price IS NULL) ns,
            SUM(o.cf_pax_id IS NOT NULL) cf")->first();
        $stats = ['all' => (int) $counts->a, 'booking' => (int) $counts->bk, 'purchase' => (int) $counts->pu, 'issued' => (int) $counts->iss, 'departed' => (int) $counts->dep, 'expired' => (int) $counts->ex, 'cancelled' => (int) $counts->ca,
            'reissued' => (int) $counts->ri, 'rescheduled' => (int) $counts->rs, 'refunded' => (int) $counts->rf, 'void' => (int) $counts->vo,
            'due' => (int) $counts->du, 'due_amount' => (float) $counts->du_amt, 'unsold' => (int) $counts->ns, 'cf' => (int) $counts->cf];
        $view = $request->input('view', 'all');
        // Issued = a live purchased ticket; Departed = one whose travel date
        // has passed (owner, 2026-10-07). No departure_at = not departed.
        $scenario = [
            'issued' => fn ($q) => $q->where('order_type', 'purchase')->where('status', 'purchased'),
            'departed' => fn ($q) => $q->where('order_type', 'purchase')->where('status', 'purchased')->where('departure_at', '<', now()),
            'reissued' => fn ($q) => $q->where('chg_reissue', 1),
            'rescheduled' => fn ($q) => $q->where('chg_reschedule', 1),
            'refunded' => fn ($q) => $q->where('chg_refund', 1),
            'void' => fn ($q) => $q->where('chg_void', 1),
            'due' => fn ($q) => $q->where('order_type', 'purchase')->where('status', 'purchased')->where('due_amount', '>', 0),
            'unsold' => fn ($q) => $q->where('order_type', 'purchase')->where('status', 'purchased')->whereNull('cf_pax_id')->whereNull('sale_price'),
            'cf' => fn ($q) => $q->whereNotNull('cf_pax_id'),
        ];
        $orders = $base->when(isset($scenario[$view]), $scenario[$view] ?? fn ($q) => $q)
            ->when($view === 'booking', fn ($q) => $q->where('order_type', 'booking')->where('status', 'booked'))->when($view === 'purchase', fn ($q) => $q->where('order_type', 'purchase'))->when(in_array($view, ['expired', 'cancelled']), fn ($q) => $q->where('status', $view))
            ->when($request->filled('search'), fn ($q) => $this->searchAnyColumn($q, (string) $request->search, $hasCol('ticket_purchases', 'cancel_note')))
            ->when($request->filled('ticket_id'), fn ($q) => $q->where('ticket_id', $request->ticket_id))->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            // The bar's From / To: the day the ticket was entered (2026-10-06 — the dates were shown but never applied).
            ->when($request->filled('from_date'), fn ($q) => $q->whereDate('order_date', '>=', $request->from_date))->when($request->filled('to_date'), fn ($q) => $q->whereDate('order_date', '<=', $request->to_date))
            // newest entry first; the tickets of one entry stay together, ticket 1 above ticket 2
            ->orderByDesc('group_date')->orderBy('order_group')->orderBy('leg_sequence')->orderBy('order_date')
            ->paginate(15)->withQueryString();
        // a purchased leg's sector comes from the route's legs (a booking stores its own)
        $legTickets = Ticket::with(['from_airport:id,code', 'to_airport:id,code'])->whereIn('id', $orders->pluck('ticket_id')->filter()->unique())->get()->keyBy('id');
        // Cancel return (2026-10-14): the window / cancelled-return line and the return's
        // refund, for this page's tickets only — one query, none before the migration.
        $returns = ReturnCancel::ready()
            ? TicketPurchase::whereIn('id', $orders->where('order_type', 'purchase')->pluck('record_id'))
                ->where(fn ($q) => $q->whereNotNull('return_cancel_by')->orWhereNotNull('return_cancelled_at'))
                ->get(['id', 'status', 'return_cancel_by', 'return_cancelled_at', 'return_refund_amount'])->keyBy('id')
            : collect();
        foreach ($orders as $o) {
            $r = $o->order_type === 'purchase' ? ($returns[$o->record_id] ?? null) : null;
            $o->ret_badge = $r ? ReturnCancel::rowBadge($r) : null;
            // a live round trip whose return half was cancelled: its refund comes off the cost
            $o->ret_refund = $r && $r->status !== 'cancelled' && $r->return_cancelled_at ? (float) ($r->return_refund_amount ?? 0) : null;
            $tk = $o->ticket_id ? ($legTickets[$o->ticket_id] ?? null) : null;
            $o->leg_total = $tk ? $tk->legCount() : 1;
            if ($o->leg_sequence && ! $o->sector && $tk) {
                $o->sector = $tk->legsArray()[$o->leg_sequence - 1]['sector'] ?? null;
            }
            // a direct route's sector is the route's own two airports
            if (! $o->sector && $tk && $tk->from_airport?->code && $tk->to_airport?->code) {
                $o->sector = $tk->from_airport->code.' → '.$tk->to_airport->code;
            }
        }
        $tickets = Ticket::where('status', 1)->orderBy('title')->get();
        // Row "Change" button + its dialog: only once the table and routes are there.
        $changeOn = TicketChange::available() && (bool) auth()->user()?->canAny(['edit ticket purchase', 'edit ticket direct sale']);

        return view('dashboard.ticket_direct_sales.index', compact('orders', 'stats', 'tickets', 'view', 'changeOn'));

        /* Legacy purchase-only register retained below for reference; unreachable. */
        $query = TicketPurchase::with(['passportHolder', 'ticket', 'vendor', 'portal', 'bank']);

        $query->when($request->filled('ticket_no'), fn ($q) => $q->where('ticket_no', 'like', "%{$request->ticket_no}%"));
        $query->when($request->filled('purchase_date'), fn ($q) => $q->whereDate('purchase_date', $request->purchase_date));
        // The list bar's range. purchase_date (one day) above is kept for any
        // bookmarked link that still carries it.
        $query->when($request->filled('from_date'), fn ($q) => $q->whereDate('purchase_date', '>=', $request->from_date));
        $query->when($request->filled('to_date'), fn ($q) => $q->whereDate('purchase_date', '<=', $request->to_date));
        $query->when($request->filled('status'), fn ($q) => $q->where('status', $request->status));
        $query->when($request->filled('ticket_id'), fn ($q) => $q->where('ticket_id', $request->ticket_id));
        // The row's own value is 'direct_sale' or NULL — never the literal
        // string 'legacy'. A bare where('source','legacy') matched nothing;
        // whereNull() is what the "Legacy" chip/filter actually means.
        $query->when($request->filled('source'), function ($q) use ($request) {
            $request->source === 'legacy' ? $q->whereNull('source') : $q->where('source', $request->source);
        });

        $data['datas'] = $query->latest()->paginate(10)->withQueryString();
        $data['tickets'] = Ticket::where('status', 1)->get();

        $mtdPurchases = TicketPurchase::whereMonth('purchase_date', now()->month)->whereYear('purchase_date', now()->year);

        $data['stats'] = [
            'total_purchases_mtd' => (clone $mtdPurchases)->count(),
            'cost_mtd' => (clone $mtdPurchases)->sum('amount'),
            'total_due' => TicketPurchase::where('status', '!=', 'cancelled')->where('payment_status', '!=', 'paid')->sum('due_amount'),
            // Confirmed and not yet on any invoice — what Manage Sales still
            // has to work with.
            'ready_to_sell' => TicketPurchase::where('status', 'confirm')
                ->whereNotExists(function ($q) {
                    $q->selectRaw(1)->from('ticket_sale_items')
                        ->whereColumn('ticket_sale_items.ticket_purchase_id', 'ticket_purchases.id');
                })
                ->count(),
        ];

        return view('dashboard.ticket_direct_sales.index', $data);
    }

    /**
     * Ticket Orders search (owner, 2026-10-06: "anything, any text from the
     * table is searchable"). Every word must match somewhere in the row:
     * PNR / reference, e-ticket, passenger, passport, route, flight no,
     * airline, status and its note, the seller, the buyer, the contract
     * flight and its agent, who entered it; a party code "EP-VN-139" / "139"
     * finds that vendor, buyer or agent; "69,000" finds the amounts.
     */
    private function searchAnyColumn($q, string $search, bool $hasNote)
    {
        $cols = ['reference', 'secondary_reference', 'passenger_name', 'passport_no', 'eticket', 'route_name', 'sector',
            'flight_no', 'airline', 'supplier_name', 'sale_buyer', 'cf_agent', 'cf_flight', 'status', 'created_by_name'];
        if ($hasNote) {
            $cols[] = 'cancel_note';
        }
        // What the screen shows for a status, back to what is stored.
        $shown = ['reserved' => 'booked', 'purchased' => 'purchased'];
        foreach (preg_split('/\s+/', trim($search)) as $w) {
            if ($w === '') {
                continue;
            }
            $q->where(function ($x) use ($w, $cols, $shown) {
                foreach ($cols as $c) {
                    $x->orWhere($c, 'like', '%'.$w.'%');
                }
                if (isset($shown[strtolower($w)])) {
                    $x->orWhere('status', $shown[strtolower($w)]);
                }
                $id = preg_replace('/^EP-(VN|AG|CU)-0*/i', '', $w);
                if ($id !== '' && ctype_digit($id)) {
                    $x->orWhere('vendor_id', (int) $id)->orWhere('sale_buyer_id', (int) $id)->orWhere('cf_agent_id', (int) $id);
                }
                $amount = str_replace([',', '৳'], '', $w);
                if (is_numeric($amount)) {
                    $x->orWhere('amount', 'like', $amount.'%')->orWhere('sale_price', 'like', $amount.'%');
                }
            });
        }

        return $q;
    }

    // =========================================================================
    // CREATE
    // =========================================================================

    public function create(Request $request)
    {
        // ?ops=1 — the sidebar's "Ticket Services" door: Refund / Re-Issue / Void / EMD tabs only
        $opsMode = $request->boolean('ops');
        $banks = Bank::where('status', 1)->get();
        // from / to airports' countries: the route picker shows their flags (2026-10-07)
        $tickets = Ticket::where('status', 1)->with(['from_airport.country', 'to_airport.country'])->get();
        $vendors = User::role('vendor')->orderBy('name')->get();
        $agents = User::role('agent')->orderBy('name')->get();
        // who a ticket can be sold to (agent price): agents, then Manage Sales' customers
        try {
            $customers = User::role('customer')->orderBy('name')->get(['id', 'name']);
        } catch (\Throwable) {
            $customers = collect();
        }
        $portals = Portal::where('status', 'active')->orderBy('name')->get();
        $passport_holders = PassportHolder::where('status', 1)->get();
        $ph_categories = PassportHolderCategory::orderBy('name')->get();
        // + country: the "+" route modal's airports carry their flags (via airports, 2026-10-17)
        $airports = Airport::with('country')->orderBy('name')->get();
        $airlines = Airline::orderBy('name')->get();
        $bookingRecipients = User::where('status', 'active')
            ->where('company_id', self::TRAVELS_COMPANY_ID)
            ->orderBy('name')->get();
        $bookingBoss = User::where('name', 'Md Mohsin')->where('status', 'active')->first();
        if ($bookingBoss && ! $bookingRecipients->contains('id', $bookingBoss->id)) {
            $bookingRecipients->push($bookingBoss);
        }
        $bookingToConvert = null;
        $bookingConversionData = null;
        if ($request->filled('booking_id')) {
            $bookingToConvert = TicketBooking::with(['passenger', 'ticket', 'vendor', 'portal'])
                ->whereKey($request->integer('booking_id'))
                ->where('company_id', self::TRAVELS_COMPANY_ID)
                ->whereNull('confirmed_purchase_id')
                ->whereIn('status', ['booked', 'expired'])
                ->firstOrFail();
            $bookingConversionData = [
                'passport_holder_id' => $bookingToConvert->passport_holder_id,
                'ticket_id' => $bookingToConvert->ticket_id,
                'vendor_id' => $bookingToConvert->vendor_id,
                'portal_id' => $bookingToConvert->portal_id,
                'trip_type' => $bookingToConvert->trip_type,
                'ticket_class' => $bookingToConvert->ticket_class,
                'purchase_date' => $bookingToConvert->booking_at->format('Y-m-d'),
                'ticket_no' => $bookingToConvert->pnr,
                'name_ticket_no' => $bookingToConvert->name_ticket_no ?? null,
                'cost_amount' => (float) $bookingToConvert->quoted_amount,
                'currency' => $bookingToConvert->currency,
                'leg_sequence' => $bookingToConvert->leg_sequence,
                'expires_at' => $bookingToConvert->expires_at?->format('Y-m-d\TH:i'),
                'notification_recipient_id' => $bookingToConvert->notification_recipient_id,
            ] + TicketPurchase::entryDetailAttributes('ticket_bookings', $bookingToConvert->getAttributes());
        }

        $lastPurchase = TicketPurchase::latest('id')->first();
        $lastPNum = $lastPurchase ? (int) substr($lastPurchase->ticket_no, 2) : 0;
        $ticketNo = 'TP'.str_pad($lastPNum + 1, 5, '0', STR_PAD_LEFT);

        // Smart checks on the entry page (2026-10-04), all in the browser:
        // passport expiry per holder, and the live tickets already on file so a
        // second ticket for the same person, route and leg is flagged before Save.
        $entryChecks = ['expiry' => [], 'tickets' => []];
        try {
            $entryChecks['expiry'] = $passport_holders->filter(fn ($p) => $p->expiry_date)
                ->mapWithKeys(fn ($p) => [$p->id => substr((string) $p->expiry_date, 0, 10)])->all();
            $entryChecks['tickets'] = DB::table('ticket_purchases as p')
                ->leftJoin('ticket_legs as l', 'l.ticket_purchase_id', '=', 'p.id')
                ->whereNull('p.deleted_at')->where('p.status', '!=', 'cancelled')
                ->where('p.purchase_date', '>=', now()->subDays(400)->toDateString())
                ->groupBy('p.id', 'p.passport_holder_id', 'p.ticket_id', 'p.leg_sequence', 'p.ticket_no', 'p.purchase_date', 'p.order_group')
                ->selectRaw("p.passport_holder_id ph, p.ticket_id t, COALESCE(p.leg_sequence, 1) s, p.ticket_no pnr, COALESCE(MIN(l.travel_date), p.purchase_date) d, p.order_group g, 'purchase' k, p.id id, (SELECT COUNT(*) FROM contract_flight_passenger_tickets x WHERE x.ticket_purchase_id = p.id) cf")
                ->get()->all();
            // live holds too (2026-10-05): a leg may be booked now and its other
            // leg imported later — the import connects the two by order_group
            $entryChecks['tickets'] = array_merge($entryChecks['tickets'], DB::table('ticket_bookings as b')
                ->whereNull('b.deleted_at')->whereNull('b.confirmed_purchase_id')
                ->whereNotIn('b.status', ['cancelled', 'confirmed', 'expired'])
                ->where('b.created_at', '>=', now()->subDays(400))
                ->selectRaw('b.passport_holder_id ph, b.ticket_id t, COALESCE(b.leg_sequence, 1) s, b.pnr pnr, COALESCE(b.departure_at, b.created_at) d, b.order_group g, b.status k, b.id id')
                ->get()->all());
        } catch (\Throwable) {
            // checks are a convenience — the page works without them
        }

        return view('dashboard.ticket_direct_sales.create', compact(
            'banks', 'tickets', 'vendors', 'agents', 'portals',
            'passport_holders', 'ph_categories', 'ticketNo',
            'airports', 'airlines', 'bookingToConvert', 'bookingConversionData',
            'bookingRecipients', 'bookingBoss', 'opsMode', 'entryChecks', 'customers'
        ));
    }

    // =========================================================================
    // QUICK CREATE TICKET ROUTE (AJAX)
    // =========================================================================

    public function quickCreateTicket(Request $request)
    {
        // Via airports (owner, 2026-10-17: "this feature in the add ticket, the + icon") — the
        // same rows and the same server code as Manage Tickets → Add New Ticket: route_stops[]
        // (transit, or where a new ticket starts) + legs[] (each ticket's airline / seller /
        // price). With stops, the route's own airline / seller / price come from its tickets.
        $tickets = app(TicketController::class);
        $tickets->mergeViaDefaults($request);

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'price' => 'nullable|numeric|min:0',
            'airline_id' => 'nullable|exists:airlines,id',
            'from_airport_id' => 'nullable|exists:airports,id',
            'to_airport_id' => 'nullable|exists:airports,id',
            'vendor_id' => 'nullable|exists:users,id',
            'portal_id' => 'nullable|exists:portals,id',
            'route_stops' => 'nullable|array',
            'route_stops.*.airport_id' => 'nullable|exists:airports,id',
            'legs' => 'nullable|array',
            'legs.*.airline_id' => 'nullable|exists:airlines,id',
            'legs.*.vendor_id' => 'nullable|exists:users,id',
            'legs.*.portal_id' => 'nullable|exists:portals,id',
            'legs.*.cost' => 'nullable|numeric|min:0',
            // round trip (owner, 2026-10-18): the same rows as Manage Tickets — Return on the same
            // ticket(s), or separate return ticket(s) cut by return_stops[]
            'trip_type' => 'nullable|in:one-way,two-way',
            'return_mode' => 'nullable|in:same,separate',
            'return_stops' => 'nullable|array',
            'return_stops.*.airport_id' => 'nullable|exists:airports,id',
        ]);
        // a return needs both ends: it flies To → From
        $validator->after(function ($v) use ($request) {
            if ($request->input('trip_type') === 'two-way' && (! $request->filled('from_airport_id') || ! $request->filled('to_airport_id'))) {
                $v->errors()->add('to_airport_id', 'Choose From and To for a round trip.');
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()->toArray(),
            ], 422);
        }

        $defaults = app(TicketRouteDefaults::class)->values();
        $ticket = DB::transaction(function () use ($request, $defaults, $tickets) {
            $ticket = Ticket::create([
                'title' => $request->title,
                'airline_id' => $request->airline_id ?: ($defaults['airline_id'] ?? null),
                'from_airport_id' => $request->from_airport_id ?: ($defaults['from_airport_id'] ?? null),
                'to_airport_id' => $request->to_airport_id ?: ($defaults['to_airport_id'] ?? null),
                'vendor_id' => $request->vendor_id ?: null,
                'portal_id' => $request->portal_id ?: null,
                'price' => (float) ($request->price ?? 0),
                'qty' => 0,
                'status' => 1,
            ] + TicketController::tripAttributes($request));
            if ($request->filled('via_airport_id') || $request->filled('route_stops') || TicketController::separateReturn($request)) {
                $tickets->syncRouteLegs($ticket, $request);
            }

            return $ticket->fresh();
        });

        return response()->json([
            'success' => true,
            'ticket' => ['id' => $ticket->id, 'title' => $ticket->title, 'price' => (float) $ticket->price,
                'vendor_id' => $ticket->vendor_id, 'portal_id' => $ticket->portal_id,
                // round trip: the card sets Round Trip (same ticket) or adds the return ticket(s) (2026-10-18)
                'trip_type' => $ticket->isRoundTrip() ? 'two-way' : 'one-way',
                // the origin / destination flags, so a route made here shows them at once (2026-10-07)
                'ff' => strtolower((string) $ticket->from_airport?->country?->code),
                'tf' => strtolower((string) $ticket->to_airport?->country?->code),
                // its tickets (with transits) — the card expands a route of several tickets, as it does a Manage Tickets one
                'legs' => $ticket->legsArray()],
        ]);
    }

    /**
     * Ticket Entry: a passenger entered through the passport holder "+" becomes a
     * client (owner, 2026-10-25: "after entering new passport, he should be added
     * automatically to the client list, then can be selected from the Sold to") — for
     * someone who buys directly, not via any agent. The same record Party Management →
     * Customer creates (CustomerRoleController::store: role customer, Travels through
     * company_party, service "ticket"), so it is listed under Clients in every Sold to
     * and on Party Statement's Client tab, and its sale posts exactly like an agent's.
     * A client already on file under the same name is returned, never made twice.
     */
    public function quickCreateClient(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255|unique:users,email',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        // one spelling: a no-break space (copied names carry them) is a space
        $name = trim((string) preg_replace('/[\s\x{00A0}]+/u', ' ', (string) $request->input('name')));
        $key = static fn (?string $n): string => mb_strtolower((string) preg_replace('/[\s\x{00A0}]+/u', '', (string) $n));
        $existing = User::role('customer')->get(['id', 'name'])->first(fn (User $u) => $key($u->name) === $key($name));
        if ($existing) {
            return response()->json(['success' => true, 'existing' => true, 'data' => ['id' => $existing->id, 'name' => $existing->name]]);
        }

        try {
            $client = DB::transaction(function () use ($request, $name) {
                $client = User::create([
                    'name' => $name,
                    'company_id' => null,
                    'phone' => $request->filled('phone') ? trim((string) $request->input('phone')) : null,
                    'email' => $request->filled('email') ? trim((string) $request->input('email')) : null,
                    'service_categories' => ['ticket'],
                    'password' => Hash::make(Str::random(40)),
                ]);
                $client->assignRole('customer');
                PartyAssignmentService::sync($client, 'client', [self::TRAVELS_COMPANY_ID]);

                return $client;
            });
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => collect($e->errors())->flatten()->first() ?: 'Client not saved.'], 422);
        }

        return response()->json(['success' => true, 'existing' => false, 'data' => ['id' => $client->id, 'name' => $client->name]]);
    }

    // =========================================================================
    // STORE
    // =========================================================================

    /**
     * Replace one ticket purchase's route legs with whatever its row's
     * repeater submitted — see TicketPurchaseController::syncLegs() for why
     * delete-and-recreate is safe here (a leg carries no state of its own to
     * preserve across an edit).
     */
    private function syncLegs(TicketPurchase $ticketPurchase, array $legs): void
    {
        // + each sector's optional airline (owner, 2026-10-07; see TicketLeg::syncFor)
        TicketLeg::syncFor($ticketPurchase, $legs);
    }

    /** The status a ticket row was entered with: purchased | booked | pending | draft | cancelled. */
    /**
     * The existing order a row asks to join (passengers.*.order_group), when
     * a live ticket or hold of the SAME passenger on the SAME route already
     * carries it — never someone else's order. Null otherwise.
     */
    private static function joinableOrderGroup(array $row): ?string
    {
        $group = trim((string) ($row['order_group'] ?? ''));
        if ($group === '' || empty($row['passport_holder_id']) || empty($row['ticket_id'])) {
            return null;
        }
        try {
            $owns = fn (string $table, string $deadStatus) => DB::table($table)->whereNull('deleted_at')
                ->where('order_group', $group)->where('status', '!=', $deadStatus)
                ->where('passport_holder_id', $row['passport_holder_id'])->where('ticket_id', $row['ticket_id'])->exists();

            return ($owns('ticket_purchases', 'cancelled') || $owns('ticket_bookings', 'cancelled')) ? $group : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function entryStatus(array $row, Request $request): string
    {
        $st = $row['status'] ?? $request->input('status') ?? 'purchased';

        return $st === 'confirm' ? 'purchased' : $st;
    }

    /**
     * A ticket entered as Draft / Pending / Booked / Cancelled: a ticket_bookings
     * row. No ledger, no stock. The hold logic (deadline, reminders, expiry) is
     * TicketBooking's own and unchanged — it only runs for status = booked.
     */
    private function storeBookingRow(array $row, int $index, Request $request, string $status, string $orderGroup, ?int $legSeq, $companyId, array &$storedAttachments): TicketBooking
    {
        $attachmentPath = null;
        $attachment = $request->file('passengers')[$index]['attachment'] ?? null;
        if ($attachment) {
            $attachmentPath = $attachment->store('ticket_booking_attachments', 'public');
            $storedAttachments[] = $attachmentPath;
        }
        $ticket = ! empty($row['ticket_id']) ? Ticket::find($row['ticket_id']) : null;
        $leg = ($ticket && $legSeq) ? ($ticket->legsArray()[$legSeq - 1] ?? null) : null;
        $reference = DB::transaction(function () {
            $last = TicketBooking::withTrashed()->lockForUpdate()->latest('id')->value('id') ?? 0;

            return 'TB'.str_pad($last + 1, 7, '0', STR_PAD_LEFT);
        });
        $bookedAt = ($row['booking_at'] ?? null) ?: (($row['purchase_date'] ?? null) ?: now());

        return TicketBooking::create(TicketPurchase::nameTicketNoAttributes(
            'ticket_bookings', $row['name_ticket_no'] ?? null
        ) + [
            'company_id' => $companyId,
            'passport_holder_id' => $row['passport_holder_id'],
            'ticket_id' => $ticket?->id,
            'vendor_id' => $row['vendor_id'] ?? null,
            'portal_id' => $row['portal_id'] ?? null,
            'notification_recipient_id' => $row['notification_recipient_id'] ?? null,
            'booking_reference' => $reference,
            'order_group' => $orderGroup,
            'leg_sequence' => $legSeq,
            'from_airport_id' => $leg['from_id'] ?? null,
            'to_airport_id' => $leg['to_id'] ?? null,
            // Holds share the purchases' letters rather than running a
            // sequence of their own: Ticket Orders lists both kinds of row
            // side by side, so two rows reading "JK2564VB (a)" would be two
            // different passengers. Carried through unchanged when the hold is
            // later confirmed — allocateTicketNo() keeps a value nothing else
            // holds, and the booking excludes itself at that point.
            'pnr' => TicketPurchase::allocateTicketNo(
                $row['ticket_no'] ?? null,
                $row['passport_holder_id'] ?? null
            ),
            'trip_type' => TicketPurchase::storableTripType($row['trip_type'] ?? null),
            'ticket_class' => $row['ticket_class'] ?? 'economy',
            'booking_at' => $status === 'booked' ? $bookedAt : null,
            'expires_at' => $status === 'booked' ? ($row['expires_at'] ?? null) : null,
            'departure_at' => ($row['departure_at'] ?? null) ?: null,
            'return_at' => ($row['return_at'] ?? null) ?: null,
            'quoted_amount' => (float) ($row['cost_amount'] ?? 0),
            'currency' => $request->currency ?? 'BDT',
            'status' => $status,
            'notes' => $row['notes'] ?? null,
            'attachment' => $attachmentPath,
            'notify_in_app' => ! isset($row['notify_in_app']) || (bool) $row['notify_in_app'],
            'notify_email' => ! isset($row['notify_email']) || (bool) $row['notify_email'],
            'notify_sms' => ! isset($row['notify_sms']) || (bool) $row['notify_sms'],
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ] + TicketPurchase::entryDetailAttributes('ticket_bookings', $row));
    }

    public function store(Request $request, $role)
    {
        $validator = Validator::make($request->all(), [
            // Per-passenger rows
            'passengers' => 'required|array|min:1',
            // Ticket orders (owner, 2026-09-21): one entry screen, each ticket
            // carries its own status. Nothing is mandatory at entry except the
            // person — a ticket can be purchased or booked later. Only a row
            // marked Purchased must be a complete purchase (PNR, seller, cost),
            // because that is the one that hits the ledger.
            'passengers.*.passport_holder_id' => 'required|exists:passport_holders,id',
            'passengers.*.ticket_id' => 'nullable|exists:tickets,id',
            'passengers.*.vendor_id' => 'prohibits:passengers.*.portal_id|nullable|exists:users,id',
            'passengers.*.portal_id' => 'prohibits:passengers.*.vendor_id|nullable|exists:portals,id',
            'passengers.*.purchase_bank_id' => 'nullable|exists:banks,id',
            'passengers.*.ticket_type' => 'nullable|in:air,bus,train,other',
            'passengers.*.trip_type' => 'nullable|in:one-way,two-way,multi-city',
            'passengers.*.ticket_class' => 'nullable|in:economy,business,first',
            'passengers.*.purchase_date' => 'nullable|date',
            'passengers.*.leg_sequence' => 'nullable|integer|min:1|max:9',
            'passengers.*.expires_at' => 'nullable|date',
            'passengers.*.booking_at' => 'nullable|date',
            'passengers.*.departure_at' => 'nullable|date',
            'passengers.*.return_at' => 'nullable|date|after_or_equal:passengers.*.departure_at',
            'passengers.*.notification_recipient_id' => 'nullable|exists:users,id',
            'passengers.*.notes' => 'nullable|string|max:5000',
            'passengers.*.order_group' => 'nullable|string|max:64',
            // agent price (2026-10-05): who the ticket is sold to, and for how much
            'passengers.*.sale_client_id' => 'nullable|exists:users,id',
            'passengers.*.sale_price' => 'nullable|numeric|min:0',
            // refund → new ticket (2026-10-07): the refunded ticket this one replaces
            'passengers.*.replaces_purchase_id' => 'nullable|integer|exists:ticket_purchases,id',
            'passengers.*.replace_fare_bearer' => 'nullable|in:customer,company',
            'passengers.*.replace_fee' => 'nullable|numeric|min:0',
            // NO uniqueness guard here, deliberately (owner, 2026-09-22). One
            // airline PNR covers everyone booked together, so an agent's group
            // of five passports is five cards carrying the SAME PNR — the rule
            // that used to sit here rejected the commonest real entry there is.
            // Rows are kept apart by TicketPurchase::allocateTicketNo(), which
            // appends " (a)", " (b)" … as each one saves.
            'passengers.*.ticket_no' => 'nullable|string|max:200',
            'passengers.*.name_ticket_no' => 'nullable|string|max:100',
            'passengers.*.cost_amount' => 'nullable|numeric|min:0',
            'passengers.*.purchase_paid' => 'nullable|numeric|min:0',
            'passengers.*.purchase_pay_method' => 'nullable|string',
            'passengers.*.purchase_pay_status' => 'nullable|in:due,paid,partial',
            'passengers.*.cost_due_date' => 'nullable|date',
            'passengers.*.status' => 'nullable|in:purchased,confirm,booked,pending,draft,cancelled',
            'passengers.*.attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png',
            // Standard ticket detail (2026-10-04) — all optional, informational.
            'passengers.*.base_fare' => 'nullable|numeric|min:0',
            'passengers.*.tax_amount' => 'nullable|numeric|min:0',
            'passengers.*.tax_breakdown' => 'nullable|string|max:255',
            'passengers.*.commission_pct' => 'nullable|numeric|min:0|max:100',
            'passengers.*.commission_amount' => 'nullable|numeric|min:0',
            'passengers.*.validating_carrier_id' => 'nullable|exists:airlines,id',
            'passengers.*.flight_no' => 'nullable|string|max:40',
            'passengers.*.arrival_at' => 'nullable|date',
            'passengers.*.rbd' => 'nullable|string|max:4',
            'passengers.*.baggage' => 'nullable|string|max:40',
            ...TicketBaggage::rules('passengers.*.'),
            'passengers.*.return_flight_no' => 'nullable|string|max:40',
            // Cancel return (2026-10-14): a family ticket, and the airline's window for its return
            'passengers.*.family_return' => 'nullable|boolean',
            'passengers.*.return_cancel_by' => 'nullable|date',
            'passengers.*.legs' => 'nullable|array',
            'passengers.*.legs.*.from_location' => 'nullable|string|max:255',
            'passengers.*.legs.*.to_location' => 'nullable|string|max:255',
            'passengers.*.legs.*.travel_date' => 'nullable|date',
            'passengers.*.legs.*.cost' => 'nullable|numeric|min:0',
            'passengers.*.legs.*.airline_id' => 'nullable|integer',
            'currency' => 'required|string|max:5',
            'status' => 'nullable|in:pending,confirm,cancelled,draft',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png',
            'booking_id' => 'nullable|integer|exists:ticket_bookings,id',
        ]);

        $validator->after(function ($v) use ($request) {
            $passengers = $request->passengers ?? [];

            if ($request->filled('booking_id') && count($passengers) !== 1) {
                $v->errors()->add('passengers', 'A booking conversion must create exactly one ticket purchase.');
            }
            if ($request->filled('booking_id') && self::entryStatus($passengers[array_key_first($passengers)] ?? [], $request) !== 'purchased') {
                $v->errors()->add('passengers', 'To confirm a booking, set the ticket status to Purchased.');
            }

            // Per-row checks
            foreach ($passengers as $i => $row) {
                $cost = (float) ($row['cost_amount'] ?? 0);
                $paid = (float) ($row['purchase_paid'] ?? 0);
                $st = self::entryStatus($row, $request);

                if ($st === 'purchased') {
                    if (empty($row['ticket_id'])) {
                        $v->errors()->add("passengers.{$i}.ticket_id", 'Ticket '.($i + 1).': a purchased ticket needs its route.');
                    }
                    if (empty($row['ticket_no'])) {
                        $v->errors()->add("passengers.{$i}.ticket_no", 'Ticket '.($i + 1).': a purchased ticket needs its PNR / ticket no.');
                    }
                    if (empty($row['vendor_id']) && empty($row['portal_id'])) {
                        $v->errors()->add("passengers.{$i}.vendor_id", 'Ticket '.($i + 1).': a purchased ticket needs a vendor or portal.');
                    }
                    if (! isset($row['cost_amount']) || $row['cost_amount'] === '') {
                        $v->errors()->add("passengers.{$i}.cost_amount", 'Ticket '.($i + 1).': a purchased ticket needs its cost.');
                    }
                }
                if ($st === 'booked' && empty($row['expires_at'])) {
                    $v->errors()->add("passengers.{$i}.expires_at", 'Ticket '.($i + 1).': a booked ticket needs the hold deadline.');
                }

                if ($paid > $cost) {
                    $v->errors()->add("passengers.{$i}.purchase_paid", 'Cost paid cannot exceed cost price.');
                }
                $hasPrice = isset($row['sale_price']) && $row['sale_price'] !== '' && $row['sale_price'] !== null;
                if ($hasPrice && empty($row['sale_client_id'])) {
                    $v->errors()->add("passengers.{$i}.sale_client_id", 'Ticket '.($i + 1).': choose who the ticket is sold to, or clear the agent price.');
                }
                if (! empty($row['sale_client_id']) && ! $hasPrice) {
                    $v->errors()->add("passengers.{$i}.sale_price", 'Ticket '.($i + 1).': type the agent price, or clear "Sold to".');
                }
                if (($hasPrice || ! empty($row['sale_client_id'])) && $st !== 'purchased') {
                    $v->errors()->add("passengers.{$i}.sale_price", 'Ticket '.($i + 1).': only a Purchased ticket can be sold — clear the agent price for a hold or draft.');
                }
                if ($paid > 0 && empty($row['purchase_bank_id']) && empty($row['portal_id'])) {
                    $v->errors()->add("passengers.{$i}.purchase_bank_id", 'Bank or portal required when cost has an immediate payment.');
                }
            }
        });

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()]);
        }

        $passengers = $request->passengers;
        $companyId = self::TRAVELS_COMPANY_ID;

        DB::beginTransaction();
        try {
            $createdPurchases = [];
            $storedAttachments = [];
            $bookingToConvert = null;
            if ($request->filled('booking_id')) {
                $bookingToConvert = TicketBooking::query()->lockForUpdate()
                    ->whereKey($request->integer('booking_id'))
                    ->where('company_id', self::TRAVELS_COMPANY_ID)
                    ->firstOrFail();
                if ($bookingToConvert->confirmed_purchase_id || $bookingToConvert->status === 'confirmed') {
                    throw new \RuntimeException('This booking has already been converted to a purchase.');
                }
            }

            // ── 1. ONE RECORD PER TICKET ROW ─────────────────────────────────
            // Purchased → ticket_purchases (ledger, stock, schedule as always).
            // Draft / Pending / Booked / Cancelled → ticket_bookings, no money.
            // The tickets of one passenger's via route share an order_group.
            $createdBookings = [];
            $orderGroups = [];
            foreach ($passengers as $index => $row) {
                $groupKey = ($row['passport_holder_id'] ?? '').'|'.($row['ticket_id'] ?? '');
                // A leg entered later joins the order its other leg was saved
                // under (PDF import, 2026-10-05) — accepted only when that order
                // really is this passenger's on this route; otherwise a new one.
                if (! isset($orderGroups[$groupKey]) && ($joined = self::joinableOrderGroup($row))) {
                    $orderGroups[$groupKey] = $joined;
                }
                $orderGroup = $orderGroups[$groupKey] ??= ('TO'.now()->format('ymdHis').strtoupper(substr(uniqid(), -4)).'-'.(count($orderGroups) + 1));
                $legSeq = ! empty($row['leg_sequence']) ? (int) $row['leg_sequence'] : null;
                $entryStatus = self::entryStatus($row, $request);

                if ($entryStatus !== 'purchased') {
                    $booking = $this->storeBookingRow($row, $index, $request, $entryStatus, $orderGroup, $legSeq, $companyId, $storedAttachments);
                    $createdBookings[] = $booking;

                    continue;
                }

                // Several cards of one group legitimately carry the same PNR.
                // This is what makes them distinguishable once saved, and it
                // is the value every downstream reference below must use —
                // writing $row['ticket_no'] to the ledger would put a PNR on
                // the vendor's statement that matches no ticket row.
                $pnr = TicketPurchase::allocateTicketNo(
                    $row['ticket_no'] ?? null,
                    $row['passport_holder_id'] ?? null,
                    ['ignore_booking_id' => $bookingToConvert?->id]
                );

                $costAmount = (float) ($row['cost_amount'] ?? 0);
                $purchasePaid = min((float) ($row['purchase_paid'] ?? 0), $costAmount);
                $purchaseDue = $costAmount - $purchasePaid;
                $purchasePSt = $purchaseDue <= 0 ? 'paid' : ($purchasePaid > 0 ? 'partial' : 'due');
                // Some embedded callers (notably Contract Flight's
                // "Create & link" PNR form) historically omitted status. The
                // insert explicitly writes this value, so leaving it null
                // bypasses the database default and violates the NOT NULL
                // constraint. A PNR issued through this endpoint is confirmed
                // by default, matching the standalone Ticketing form.
                $status = 'confirm';
                $attachment = $request->file('passengers')[$index]['attachment'] ?? null;

                // Preserve older callers that send one top-level attachment.
                if (! $attachment) {
                    $attachment = $request->file('attachment');
                }

                $attachmentPath = null;
                if ($attachment) {
                    $attachmentPath = $attachment->store('ticket_attachments', 'public');
                    $storedAttachments[] = $attachmentPath;
                }

                $ticketPurchase = TicketPurchase::create(TicketPurchase::nameTicketNoAttributes(
                    'ticket_purchases', $row['name_ticket_no'] ?? null
                ) + TicketPurchase::entryDetailAttributes('ticket_purchases', $row) + [
                    'passport_holder_id' => $row['passport_holder_id'],
                    'ticket_id' => $row['ticket_id'],
                    'vendor_id' => $row['vendor_id'] ?? null,
                    'portal_id' => $row['portal_id'] ?? null,
                    'bank_id' => $row['purchase_bank_id'] ?? null,
                    'ticket_type' => $row['ticket_type'] ?? 'air',
                    'trip_type' => TicketPurchase::storableTripType($row['trip_type'] ?? null),
                    'ticket_class' => $row['ticket_class'] ?? null,
                    'purchase_date' => $row['purchase_date'] ?? now()->toDateString(),
                    'ticket_no' => $pnr,
                    'order_group' => $orderGroup,
                    'leg_sequence' => $legSeq,
                    'status' => $status,
                    'source' => 'direct_sale',
                    'amount' => $costAmount,
                    'paid_amount' => $purchasePaid,
                    'due_amount' => $purchaseDue,
                    'payment_status' => $purchasePSt,
                    'currency' => $request->currency,
                    'company_id' => $companyId,
                    'created_by' => Auth::id(),
                    'attachment' => $attachmentPath,
                ]);

                $this->syncLegs($ticketPurchase, $row['legs'] ?? []);

                // Cancel return (2026-10-14): the Family tick and the airline's window for the return.
                if ((! empty($row['family_return']) || ! empty($row['return_cancel_by'])) && ReturnCancel::ready()) {
                    ReturnCancel::setWindow($ticketPurchase, $row['return_cancel_by'] ?? null, ! empty($row['family_return']));
                }

                // Liability recognition on the vendor's party statement — the
                // full cost owed, separate from the paid-portion credit row
                // below, so the statement shows what's owed even before
                // anything is paid. Mirrors the debit-row-on-invoice-creation
                // pattern already used on the sale/AR side.
                if (! empty($row['vendor_id']) && $costAmount > 0) {
                    $this->reconcilePartyLedgerRow(
                        $row['vendor_id'], 'ticket_purchase', $ticketPurchase->id, true, $costAmount,
                        [
                            'account_id' => null,
                            'payment_date' => $ticketPurchase->purchase_date,
                            'reference_no' => $pnr,
                            'remarks' => 'Ticket purchase cost — '.($pnr ?? $ticketPurchase->id),
                        ]
                    );
                }

                // Bank or portal payment for this row's cost
                $bankId = $row['purchase_bank_id'] ?? null;
                $portalId = $row['portal_id'] ?? null;

                if ($bankId && $purchasePaid > 0) {
                    $this->debitFromBank(
                        $bankId, $ticketPurchase->id, 'ticket_purchase',
                        $row['vendor_id'] ?? null, $ticketPurchase->purchase_date,
                        null, $row['purchase_pay_method'] ?? 'cash', $purchasePaid
                    );
                } elseif ($portalId && $purchasePaid > 0) {
                    $this->debitFromPortal($portalId, $ticketPurchase->id, $row['purchase_pay_method'] ?? 'cash', $purchasePaid);
                }

                // Stock: purchase confirmed → +1 qty
                $ticket = Ticket::find($row['ticket_id']);
                if ($ticket && $status === 'confirm') {
                    $ticket->increment('qty');
                }

                $this->createPurchaseJournal($ticketPurchase, $bankId, $portalId);
                $this->postCommissionJournal($ticketPurchase);

                // Cost payable schedule — only when vendor-based (not portal) and there is a due
                if ($purchaseDue > 0 && ! $portalId) {
                    $schedDate = ! empty($row['cost_due_date']) ? $row['cost_due_date'] : $ticketPurchase->purchase_date;
                    PaymentSchedule::create([
                        'company_id' => $companyId,
                        'schedulable_type' => TicketPurchase::class,
                        'schedulable_id' => $ticketPurchase->id,
                        'type' => 'pay',
                        'party_type' => 'vendor',
                        'party_id' => $row['vendor_id'] ?? null,
                        'party_name' => $ticketPurchase->vendor?->name ?? 'Vendor (unassigned)',
                        'source_label' => $pnr,
                        'amount' => $purchaseDue,
                        'paid_amount' => 0,
                        'scheduled_date' => $schedDate,
                        'status' => 'pending',
                        'created_by' => Auth::id(),
                    ]);
                }

                $createdPurchases[] = ['purchase' => $ticketPurchase, 'row' => $row, 'ticket' => $ticket];
            }

            // ── 2. AGENT PRICE → the sale, in the same save (owner, 2026-10-05:
            // "agent price … Agent − net cost = Profit", in ticketing everywhere).
            // Posted by Manage Sales' own store() — its invoice, the buyer's
            // ledger row and the sale journal — one sale per buyer. Inside this
            // transaction: if the sale fails, the tickets are not saved either.
            // Never for a contract-flight ticket: that passenger is billed per
            // head on the flight (the page does not offer it there).
            $toSell = [];
            foreach ($createdPurchases as $c) {
                $price = $c['row']['sale_price'] ?? null;
                $buyer = $c['row']['sale_client_id'] ?? null;
                if ($buyer && $price !== null && $price !== '') {
                    $toSell[(int) $buyer][$c['purchase']->id] = (float) $price;
                }
            }
            $sold = [];
            if ($toSell && ! auth()->user()?->can('create ticket sale')) {
                throw new \RuntimeException('Selling to an agent needs the "create ticket sale" permission — leave the agent price blank, or ask for it.');
            }
            foreach ($toSell as $buyer => $prices) {
                $firstSoldPurchase = collect($createdPurchases)
                    ->first(fn ($created) => array_key_exists($created['purchase']->id, $prices));
                $saleDate = $firstSoldPurchase['purchase']->purchase_date ?? now()->toDateString();
                $saleRequest = Request::create($request->url(), 'POST', [
                    'client_id' => $buyer,
                    'status' => 'confirm',
                    'currency' => $request->currency,
                    // A sale generated from Ticket Entry belongs on the same
                    // ledger date as the ticket's selected Issue Date.
                    'sale_date' => $saleDate,
                    'ticket_purchase_ids' => array_keys($prices),
                    'sale_prices' => $prices,
                    'paid_amount' => 0,
                    'payment_method' => 'cash',
                    'payment_status' => 'due',
                ]);
                $saleRequest->setUserResolver(fn () => $request->user());
                $res = app(TicketSaleController::class)->store($saleRequest, $role)->getData(true);
                if (empty($res['success'])) {
                    throw new \RuntimeException('Agent price not posted: '.($res['message'] ?? 'sale refused'));
                }
                $sold[] = $res['message'] ?? 'sold';
            }

            // Refund → new ticket (owner, 2026-10-07): a card that names the
            // passenger's refunded ticket posts the fare difference (new − old)
            // and our fee against it — TicketChangeController's own code. A
            // refusal rolls the whole entry back.
            $replaced = [];
            foreach ($createdPurchases as $c) {
                $oldId = (int) ($c['row']['replaces_purchase_id'] ?? 0);
                if (! $oldId) {
                    continue;
                }
                try {
                    $chg = app(TicketChangeController::class)->settleFromEntry($oldId, $c['purchase']->fresh(),
                        $c['row']['replace_fare_bearer'] ?? null, (float) ($c['row']['replace_fee'] ?? 0),
                        ! empty($c['row']['sale_client_id']) ? (int) $c['row']['sale_client_id'] : null, $c['row']['notes'] ?? null);
                } catch (TicketChangeRefused $e) {
                    throw new \RuntimeException($e->getMessage());
                }
                $replaced[] = 'fare difference ৳'.number_format((float) $chg->fare_difference, 2).' recorded against the refund';
            }

            foreach ($createdPurchases as $created) {
                app(TicketBaggage::class)->sync($created['purchase']);
            }

            if ($bookingToConvert) {
                // the purchase created for this booking's own ticket (leg), not just the first row
                $match = collect($createdPurchases)->first(fn ($c) => ! $bookingToConvert->leg_sequence || (int) ($c['purchase']->leg_sequence ?? 0) === (int) $bookingToConvert->leg_sequence)
                    ?? ($createdPurchases[0] ?? null);
                if (! $match) {
                    throw new \RuntimeException('Mark this ticket as Purchased to confirm the booking.');
                }
                $match['purchase']->update(['order_group' => $bookingToConvert->order_group ?: $match['purchase']->order_group]);
                $bookingToConvert->update([
                    'status' => 'confirmed',
                    'confirmed_purchase_id' => $match['purchase']->id,
                    'confirmed_at' => now(),
                    'updated_by' => Auth::id(),
                ]);
            }

            DB::commit();

            if ($bookingToConvert) {
                SendTicketBookingNotification::dispatch($bookingToConvert->id, 'confirmed');
            }
            foreach ($createdBookings as $b) {
                if ($b->status === 'booked' && $b->notification_recipient_id) {
                    SendTicketBookingNotification::dispatch($b->id, 'created');
                }
            }

            $count = count($createdPurchases);
            $bCount = count($createdBookings);
            $parts = [];
            if ($count) {
                $parts[] = "{$count} ticket purchase(s)";
            }
            if ($bCount) {
                $parts[] = "{$bCount} booking / draft ticket(s)";
            }

            return response()->json([
                'success' => true,
                'message' => implode(' and ', $parts).' saved.'.($sold ? ' Sold to the agent: '.implode(' ', $sold) : ($count ? ' Sell confirmed tickets anytime from Manage Sales.' : '')),
                'ticket_purchases' => array_map(fn ($created) => $created['purchase']->flightTicketDetails(), $createdPurchases),
                'ticket_bookings' => array_map(fn ($b) => $b->flightHoldDetails($role), $createdBookings),
                'redirect_url' => $bookingToConvert
                    ? route('role.ticket-bookings.show', ['role' => $role, 'ticket_booking' => $bookingToConvert->id])
                    : null,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            foreach ($storedAttachments ?? [] as $storedAttachment) {
                Storage::disk('public')->delete($storedAttachment);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed: '.$e->getMessage(),
            ]);
        }
    }

    // show(), update() and destroy() for a ticket_purchases row are no longer
    // handled here — routes/web.php sends ticket-direct-sale.{show,update,destroy}
    // to TicketPurchaseController, which is the single, more complete
    // implementation (vendor re-linking, contract-flight resync, the
    // sold/linked-passenger guards on delete). Keeping a second copy here
    // would drift out of sync with the field names the edit view now sends
    // (amount/paid_amount/bank_id, not cost_amount/purchase_paid/purchase_bank_id)
    // the moment someone touches one but not the other.

    // =========================================================================
    // EDIT
    // =========================================================================

    public function edit($role, $ticket_direct_sale)
    {
        $ticketPurchase = TicketPurchase::with('legs')->findOrFail($ticket_direct_sale);

        $banks = Bank::where('status', 1)->get();
        $tickets = Ticket::where('status', 1)->with(['from_airport.country', 'to_airport.country'])->get();
        $vendors = User::role('vendor')->orderBy('name')->get();
        $portals = Portal::where('status', 'active')->orderBy('name')->get();
        $passport_holders = PassportHolder::where('status', 1)->get();
        // Same fields as Ticket Entry (2026-10-07): carrier, buyer, and the live sale
        $airlines = Airline::orderBy('name')->get();
        $agents = User::role('agent')->orderBy('name')->get();
        try {
            $customers = User::role('customer')->orderBy('name')->get(['id', 'name']);
        } catch (\Throwable) {
            $customers = collect();
        }
        $saleItem = TicketSaleItem::where('ticket_purchase_id', $ticketPurchase->id)
            ->whereHas('sale', fn ($q) => $q->where('status', '<>', 'cancelled'))
            ->with('sale.client:id,name', 'sale.items')->latest('id')->first();
        $onFlight = DB::table('contract_flight_passenger_tickets')->where('ticket_purchase_id', $ticketPurchase->id)->exists();

        return view('dashboard.ticket_direct_sales.edit', compact(
            'ticketPurchase', 'banks', 'tickets', 'vendors', 'portals', 'passport_holders',
            'airlines', 'agents', 'customers', 'saleItem', 'onFlight'
        ));
    }

    // =========================================================================
    // INVOICE LOOKUP (AJAX)
    // =========================================================================

    public function invoiceLookup(Request $request)
    {
        $invoiceNo = trim($request->invoice_no ?? '');
        $pnr = trim($request->pnr ?? '');

        if ($invoiceNo) {
            $sale = TicketSale::with(['items.ticketPurchase.passportHolder', 'items.ticketPurchase.ticket', 'items.ticketPurchase.vendor', 'items.ticketPurchase.portal'])
                ->where('invoice_no', $invoiceNo)
                ->first();

            if (! $sale) {
                return response()->json(['success' => false, 'message' => "Invoice '{$invoiceNo}' not found."]);
            }
        } elseif ($pnr) {
            // Typing the airline's PNR must find the booking even though the
            // stored value may carry our own " (a)" suffix — and typing the
            // suffixed value must still work. Matched against the whole group,
            // since one PNR can cover several passengers.
            $base = TicketPurchase::basePnr($pnr) ?? $pnr;
            $saleItem = TicketSaleItem::with(['sale', 'ticketPurchase'])
                ->whereHas('ticketPurchase', fn ($q) => $q->where(fn ($w) => $w
                    ->where('ticket_no', $base)
                    ->orWhere('ticket_no', 'like', addcslashes($base, '%_\\').' (%')))
                ->latest()
                ->first();

            if (! $saleItem) {
                return response()->json(['success' => false, 'message' => "PNR '{$pnr}' not found."]);
            }

            $sale = $saleItem->sale;

            if (! $sale) {
                return response()->json(['success' => false, 'message' => "No sale found for PNR '{$pnr}'."]);
            }

            $sale->load(['items.ticketPurchase.passportHolder', 'items.ticketPurchase.ticket', 'items.ticketPurchase.vendor', 'items.ticketPurchase.portal']);
        } else {
            return response()->json(['success' => false, 'message' => 'Invoice number or PNR required.']);
        }

        $passengers = $sale->items->map(function ($item) {
            $p = $item->ticketPurchase;

            return [
                'purchase_id' => $p?->id,
                'passenger' => $p?->passportHolder?->name ?? '—',
                'pnr' => $p?->ticket_no ?? '—',
                'route' => $p?->ticket?->title ?? '—',
                'purchase_date' => $p?->purchase_date ?? '—',
                'org_cost' => (float) ($p?->amount ?? 0),
                'sale_price' => (float) ($item->price ?? 0),
                'vendor_id' => $p?->vendor_id,
                'portal_id' => $p?->portal_id,
            ];
        });

        return response()->json([
            'success' => true,
            'sale_id' => $sale->id,
            'invoice_no' => $sale->invoice_no,
            'status' => $sale->status,
            'currency' => $sale->currency,
            'passengers' => $passengers,
        ]);
    }

    // =========================================================================
    // STORE REFUND
    // =========================================================================

    public function storeRefund(Request $request, $role)
    {
        $validator = Validator::make($request->all(), [
            'agent_id' => 'required|exists:users,id',
            'refund_date' => 'required|date',
            'original_invoice' => 'required|string',
            'status' => 'required|in:confirm,processing,pending_airline,completed',
            'org_cost' => 'required|numeric|min:0',
            'airline_refund' => 'required|numeric|min:0',
            'penalty' => 'nullable|numeric|min:0',
            'service_charge' => 'nullable|numeric|min:0',
            'org_sale' => 'required|numeric|min:0',
            'net_refund' => 'required|numeric|min:0',
            'paid_amount' => 'nullable|numeric|min:0',
            'pay_method' => 'nullable|string',
            'payment_status' => 'required|in:due,partial,paid',
            'bank_id' => 'nullable|exists:banks,id',
            'vendor_id' => 'nullable|exists:users,id',
            'portal_id' => 'nullable|exists:portals,id',
            'refund_ref_no' => 'nullable|string',
            'currency' => 'required|string|max:5',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()]);
        }

        $sale = TicketSale::where('invoice_no', $request->original_invoice)->first();
        if (! $sale) {
            return response()->json(['success' => false, 'message' => "Invoice '{$request->original_invoice}' not found."]);
        }

        $netRefund = (float) $request->net_refund;
        $paidAmount = min((float) ($request->paid_amount ?? 0), $netRefund);
        $dueAmount = $netRefund - $paidAmount;

        DB::beginTransaction();
        try {
            $attachmentPath = null;
            if ($request->hasFile('attachment')) {
                $attachmentPath = $request->file('attachment')->store('ticket_attachments', 'public');
            }

            $refund = TicketRefund::create([
                'company_id' => self::TRAVELS_COMPANY_ID,
                'ticket_sale_id' => $sale->id,
                'agent_id' => $request->agent_id,
                'vendor_id' => $request->vendor_id,
                'portal_id' => $request->portal_id,
                'bank_id' => $request->bank_id,
                'original_invoice' => $request->original_invoice,
                'refund_ref_no' => $request->refund_ref_no,
                'refund_date' => $request->refund_date,
                'org_cost' => $request->org_cost,
                'airline_refund' => $request->airline_refund,
                'penalty' => $request->penalty ?? 0,
                'service_charge' => $request->service_charge ?? 0,
                'org_sale' => $request->org_sale,
                'net_refund' => $netRefund,
                'paid_amount' => $paidAmount,
                'due_amount' => $dueAmount,
                'payment_status' => $dueAmount <= 0 ? 'paid' : ($paidAmount > 0 ? 'partial' : 'due'),
                'pay_method' => $request->pay_method,
                'status' => $request->status,
                'currency' => $request->currency,
                'attachment' => $attachmentPath,
                'created_by' => Auth::id(),
            ]);

            // Refund payout: money leaves the bank
            if ($request->bank_id && $paidAmount > 0) {
                $this->debitFromBank(
                    $request->bank_id, $refund->id, 'ticket_refund',
                    $request->agent_id, $request->refund_date,
                    $request->refund_ref_no, $request->pay_method ?? 'cash', $paidAmount
                );
            }

            // Airline refund restores portal balance
            if ($request->portal_id && (float) $request->airline_refund > 0) {
                $this->creditToPortal($request->portal_id, $refund->id, (float) $request->airline_refund);
            }

            // Accounting journal
            $this->createRefundJournal($refund);

            DB::commit();

            return response()->json(['success' => true, 'message' => "Refund processed for invoice {$request->original_invoice}."]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['success' => false, 'message' => 'Refund failed: '.$e->getMessage()]);
        }
    }

    // =========================================================================
    // STORE REISSUE
    // =========================================================================

    public function storeReissue(Request $request, $role)
    {
        $validator = Validator::make($request->all(), [
            'agent_id' => 'required|exists:users,id',
            'reissue_date' => 'required|date',
            'original_invoice' => 'required|string',
            'new_ticket_id' => 'required|exists:tickets,id',
            'new_trip_type' => 'required|in:one-way,two-way',
            'new_ticket_class' => 'required|in:economy,business,first',
            'new_ticket_no' => 'required|string|unique:ticket_purchases,ticket_no',
            'new_name_ticket_no' => 'nullable|string|max:100',
            'new_vendor_id' => 'nullable|exists:users,id',
            'new_portal_id' => 'nullable|exists:portals,id',
            'new_travel_date' => 'nullable|date',
            'new_purchase_date' => 'required|date',
            'org_cost' => 'nullable|numeric|min:0',
            'penalty' => 'required|numeric|min:0',
            'fare_diff' => 'required|numeric',
            'new_cost' => 'required|numeric|min:0',
            'service_charge' => 'nullable|numeric|min:0',
            'new_sale_price' => 'required|numeric|min:0',
            'bearer' => ['nullable', Rule::in(array_keys(TicketReissue::REISSUE_BEARERS))],
            'paid_amount' => 'nullable|numeric|min:0',
            'pay_method' => 'nullable|string',
            'payment_status' => 'required|in:due,partial,paid',
            'bank_id' => 'nullable|exists:banks,id',
            'currency' => 'required|string|max:5',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()]);
        }

        $sale = TicketSale::with(['items.ticketPurchase'])->where('invoice_no', $request->original_invoice)->first();
        if (! $sale) {
            return response()->json(['success' => false, 'message' => "Invoice '{$request->original_invoice}' not found."]);
        }

        $originalPurchase = $sale->items()->first()?->ticketPurchase;

        $bearer = $request->bearer ?: 'agent';

        // 'company' means we absorb the re-issue — the agent is never billed
        // for it, so no sale price, payment or due is posted to him at all.
        // Same rule as ContractFlightPassenger::reissue_bearer.
        $newSalePrice = $bearer === 'company' ? 0.0 : (float) $request->new_sale_price;
        $paidAmount = $bearer === 'company' ? 0.0 : min((float) ($request->paid_amount ?? 0), $newSalePrice);
        $dueAmount = $newSalePrice - $paidAmount;

        DB::beginTransaction();
        try {
            $attachmentPath = null;
            if ($request->hasFile('attachment')) {
                $attachmentPath = $request->file('attachment')->store('ticket_attachments', 'public');
            }

            // Create new TicketPurchase for the re-issued ticket
            $newCost = (float) $request->new_cost;
            $newPurchase = TicketPurchase::create(TicketPurchase::nameTicketNoAttributes(
                'ticket_purchases', $request->new_name_ticket_no
            ) + [
                'passport_holder_id' => $originalPurchase?->passport_holder_id,
                'ticket_id' => $request->new_ticket_id,
                'vendor_id' => $request->new_vendor_id,
                'portal_id' => $request->new_portal_id,
                'ticket_type' => $originalPurchase?->ticket_type ?? 'air',
                'trip_type' => $request->new_trip_type,
                'ticket_class' => $request->new_ticket_class ?: $originalPurchase?->ticket_class,
                'ticket_no' => $request->new_ticket_no,
                'purchase_date' => $request->new_purchase_date,
                'amount' => $newCost,
                'paid_amount' => 0,
                'due_amount' => $newCost,
                'payment_status' => 'due',
                'status' => 'confirm',
                'currency' => $request->currency,
                'company_id' => self::TRAVELS_COMPANY_ID,
                'created_by' => Auth::id(),
                'attachment' => $attachmentPath,
            ]);

            TicketReissue::create([
                'company_id' => self::TRAVELS_COMPANY_ID,
                'original_sale_id' => $sale->id,
                'original_purchase_id' => $originalPurchase?->id,
                'agent_id' => $request->agent_id,
                'bearer' => $bearer,
                'new_ticket_id' => $request->new_ticket_id,
                'new_vendor_id' => $request->new_vendor_id,
                'new_portal_id' => $request->new_portal_id,
                'bank_id' => $request->bank_id,
                'original_invoice' => $request->original_invoice,
                'new_ticket_no' => $request->new_ticket_no,
                'new_trip_type' => $request->new_trip_type,
                'reissue_date' => $request->reissue_date,
                'new_travel_date' => $request->new_travel_date,
                'new_purchase_date' => $request->new_purchase_date,
                'org_cost' => $request->org_cost ?? 0,
                'penalty' => $request->penalty,
                'fare_diff' => $request->fare_diff,
                'new_cost' => $newCost,
                'service_charge' => $request->service_charge ?? 0,
                'new_sale_price' => $newSalePrice,
                'paid_amount' => $paidAmount,
                'due_amount' => $dueAmount,
                'payment_status' => $dueAmount <= 0 ? 'paid' : ($paidAmount > 0 ? 'partial' : 'due'),
                'pay_method' => $request->pay_method,
                'status' => 'confirm',
                'currency' => $request->currency,
                'created_by' => Auth::id(),
            ]);

            // Additional payment received from agent into bank — never posted
            // when the company bears the re-issue, since the agent owes nothing.
            if ($bearer === 'agent' && $request->bank_id && $paidAmount > 0) {
                $this->creditToBank(
                    $request->bank_id, $sale->id, 'ticket_reissue',
                    $request->agent_id, $request->reissue_date,
                    null, $request->pay_method ?? 'cash', $paidAmount
                );
            }

            DB::commit();

            return response()->json(['success' => true, 'message' => "Re-issue saved. New ticket: {$request->new_ticket_no}."]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['success' => false, 'message' => 'Re-issue failed: '.$e->getMessage()]);
        }
    }

    // =========================================================================
    // PRIVATE PAYMENT HELPERS
    // =========================================================================

    /**
     * Outgoing bank payment (purchase). Decrements bank balance.
     * Transaction: credit = amount, debit = 0 (bank is credited when paying out).
     */
    private function debitFromBank($bankId, $invoiceId, $type, $userId, $date, $refNo, $method, float $amount)
    {
        $acc = Bank::findOrFail($bankId);
        $bank_new_balance = (float) $acc->balance - $amount;

        // `old_balance`/`balance` are the vendor's own running party-ledger
        // balance, not the bank's — the bank's balance is tracked separately
        // below.
        $party_old_balance = $userId
            ? (float) (Transaction::where('user_id', $userId)->orderByDesc('id')->value('balance') ?? 0)
            : 0.0;
        $party_new_balance = $party_old_balance - $amount;

        Transaction::create([
            'user_id' => $userId,
            'user_type' => 'supplier',
            'type' => $type,
            'account_id' => $bankId,
            'payment_date' => $date ?? now(),
            'reference_no' => $refNo,
            'payment_method' => $method,
            'invoice_id' => $invoiceId,
            'old_balance' => $party_old_balance,
            'debit' => 0,
            'credit' => $amount,
            'balance' => $party_new_balance,
            'remarks' => 'Direct ticket purchase payment.',
        ]);

        $acc->update(['balance' => $bank_new_balance]);
    }

    /**
     * Incoming bank receipt (sale). Increments bank balance.
     * Transaction: debit = amount, credit = 0 (bank is debited when receiving).
     */
    private function creditToBank($bankId, $invoiceId, $type, $userId, $date, $refNo, $method, float $amount)
    {
        $acc = Bank::findOrFail($bankId);
        $opening_balance = (float) $acc->balance;
        $new_balance = $opening_balance + $amount;

        Transaction::create([
            'user_id' => $userId,
            'user_type' => 'customer',
            'type' => $type,
            'account_id' => $bankId,
            'payment_date' => $date ?? now(),
            'reference_no' => $refNo,
            'payment_method' => $method,
            'invoice_id' => $invoiceId,
            'old_balance' => $opening_balance,
            'debit' => $amount,
            'credit' => 0,
            'balance' => $new_balance,
            'remarks' => 'Direct ticket sale receipt.',
        ]);

        $acc->update(['balance' => $new_balance]);
    }

    /** Outgoing portal payment (purchase). Decrements portal balance. */
    private function debitFromPortal($portalId, $invoiceId, $method, float $amount)
    {
        $acc = Portal::findOrFail($portalId);
        $opening_balance = (float) $acc->balance;
        $new_balance = $opening_balance - $amount;

        PortalBalance::create([
            'invoice_id' => $invoiceId,
            'portal_id' => $portalId,
            'type' => 'ticket_purchase',
            'old_balance' => $opening_balance,
            'payment_method' => $method,
            'debit' => 0,
            'credit' => $amount,
            'current_balance' => $new_balance,
            'remarks' => 'Direct ticket purchase via portal.',
            'created_by' => Auth::id(),
        ]);

        $acc->update(['balance' => $new_balance]);
    }

    // =========================================================================
    // JOURNAL HELPERS — Purchase (recognition only)
    // =========================================================================

    private function createPurchaseJournal(TicketPurchase $tp, $bankId, $portalId)
    {
        $costAccount = Account::where('code', config('accounts.ticket_purchase_cost'))->first();
        $payableAccount = Account::where('code', config('accounts.accounts_payable'))->first();

        if (! $costAccount) {
            throw new \Exception('Ticket Purchase Cost account (code 5001) not found. Please create it in Chart of Accounts.');
        }
        if ($tp->due_amount > 0 && ! $payableAccount) {
            throw new \Exception('Accounts Payable account (code '.config('accounts.accounts_payable').') not found. Please create it in Chart of Accounts.');
        }

        $journal = JournalEntry::create([
            'company_id' => $tp->company_id ?? 2,
            'created_by' => auth()->id(),
            'date' => $tp->purchase_date,
            'reference' => $tp->ticket_no,
            'source' => 'ticket_purchase',
            'source_id' => $tp->id,
            'description' => 'Direct ticket purchase — '.$tp->ticket_no,
            'beneficiary' => $tp->vendor?->name,
            'beneficiary_party_id' => $tp->vendor_id,
        ]);

        $items = [[
            'account_id' => $costAccount?->id,
            'debit' => $tp->amount,
            'credit' => 0,
            'note' => 'Ticket acquired — '.$tp->ticket_no,
        ]];

        if ($tp->paid_amount > 0) {
            if ($bankId) {
                $bank = Bank::find($bankId);
                if (! $bank || ! $bank->account_id) {
                    throw new \Exception('Bank not linked to chart of accounts.');
                }
                Bank::assertCanCover((int) $bank->account_id, (float) $tp->paid_amount, $bank->name);
                $items[] = ['account_id' => $bank->account_id, 'debit' => 0, 'credit' => $tp->paid_amount, 'note' => 'Paid via bank'];
            } elseif ($portalId) {
                $portal = Portal::find($portalId);
                if (! $portal || ! $portal->account_id) {
                    throw new \Exception('Portal not linked to chart of accounts.');
                }
                Bank::assertCanCover((int) $portal->account_id, (float) $tp->paid_amount, $portal->name);
                $items[] = ['account_id' => $portal->account_id, 'debit' => 0, 'credit' => $tp->paid_amount, 'note' => 'Paid via portal'];
            }
        }

        if ($tp->due_amount > 0) {
            $items[] = ['account_id' => $payableAccount?->id, 'debit' => 0, 'credit' => $tp->due_amount, 'note' => 'Amount owed to vendor', 'party_type' => 'vendor', 'party_id' => $tp->vendor_id];
        }

        foreach ($items as $item) {
            JournalItem::create([...$item, 'journal_entry_id' => $journal->id]);
        }
    }

    /**
     * The airline commission on its own line (owner, 2026-10-07). The seller
     * is billed the net (amount), so the purchase journal stays as it is; this
     * entry grosses the cost up to the full fare and books the commission as
     * income: Dr 5110 Ticket Cost / Cr 4171 IATA Air Ticket Commission. Profit
     * and the seller's balance are unchanged — the commission just shows.
     * A separate entry (source ticket_commission) so no edit or settlement
     * path that rebuilds the purchase journal can drop or double it.
     */
    private function postCommissionJournal(TicketPurchase $tp): void
    {
        $tp->syncCommissionJournal();   // one implementation, shared with the edit page
    }

    /**
     * Update the recognition journal for a purchase.
     *
     * If settlement journals exist (make_payment was called), only update metadata —
     * never recreate items, which would corrupt the historical recognition amounts.
     * A payment made on this edit is then a settlement in its own right and is
     * posted as one (Dr Accounts Payable / Cr bank or portal), the same entry
     * Make Payment writes — otherwise the cash left the bank statement above
     * and the trial balance never heard about it.
     */
    private function updatePurchaseJournal(TicketPurchase $tp, $bankId, $portalId, float $newPayment = 0.0)
    {
        if ($tp->status === 'cancelled') {
            JournalEntry::where('source', 'ticket_purchase')
                ->where('source_id', $tp->id)->get()->each(function ($entry) {
                    $entry->items()->delete();
                    $entry->delete();
                });

            return;
        }

        $journalCount = JournalEntry::where('source', 'ticket_purchase')
            ->where('source_id', $tp->id)->count();

        $journal = JournalEntry::where('source', 'ticket_purchase')
            ->where('source_id', $tp->id)->orderBy('id')->first();

        // Settlement journals exist → only touch metadata, preserve item amounts
        if ($journalCount > 1) {
            if ($journal) {
                $journal->update([
                    'date' => $tp->purchase_date,
                    'reference' => $tp->ticket_no,
                    'description' => 'Direct ticket purchase (edited) — '.$tp->ticket_no,
                    'beneficiary' => $tp->vendor?->name,
                    'beneficiary_party_id' => $tp->vendor_id,
                ]);
            }
            if ($newPayment > 0) {
                $this->postPurchaseSettlementJournal($tp, $bankId, $portalId, $newPayment);
            }

            return;
        }

        // No settlements yet → safe to recreate items
        if ($journal) {
            $journal->items()->delete();
            $journal->update([
                'date' => $tp->purchase_date,
                'description' => 'Direct ticket purchase (edited) — '.$tp->ticket_no,
            ]);
        } else {
            $journal = JournalEntry::create([
                'company_id' => $tp->company_id ?? 2,
                'created_by' => auth()->id(),
                'date' => $tp->purchase_date,
                'reference' => $tp->ticket_no,
                'source' => 'ticket_purchase',
                'source_id' => $tp->id,
                'description' => 'Direct ticket purchase (edited) — '.$tp->ticket_no,
                'beneficiary' => $tp->vendor?->name,
                'beneficiary_party_id' => $tp->vendor_id,
            ]);
        }

        $costAccount = Account::where('code', config('accounts.ticket_purchase_cost'))->first();
        $payableAccount = Account::where('code', config('accounts.accounts_payable'))->first();

        $items = [['account_id' => $costAccount?->id, 'debit' => $tp->amount, 'credit' => 0, 'note' => 'Ticket acquired']];

        if ($tp->paid_amount > 0) {
            if ($bankId) {
                $bank = Bank::find($bankId);
                if ($bank?->account_id) {
                    Bank::assertCanCover((int) $bank->account_id, (float) $tp->paid_amount, $bank->name);
                    $items[] = ['account_id' => $bank->account_id, 'debit' => 0, 'credit' => $tp->paid_amount, 'note' => 'Paid via bank'];
                }
            } elseif ($portalId) {
                $portal = Portal::find($portalId);
                if ($portal?->account_id) {
                    Bank::assertCanCover((int) $portal->account_id, (float) $tp->paid_amount, $portal->name);
                    $items[] = ['account_id' => $portal->account_id, 'debit' => 0, 'credit' => $tp->paid_amount, 'note' => 'Paid via portal'];
                }
            }
        }

        if ($tp->due_amount > 0) {
            $items[] = ['account_id' => $payableAccount?->id, 'debit' => 0, 'credit' => $tp->due_amount, 'note' => 'Amount owed to vendor', 'party_type' => 'vendor', 'party_id' => $tp->vendor_id];
        }

        foreach ($items as $item) {
            JournalItem::create([...$item, 'journal_entry_id' => $journal->id]);
        }
    }

    /**
     * One settlement journal for a payment made against an already-settled
     * purchase: Dr Accounts Payable / Cr the bank or portal it came from.
     * Same shape as TicketPurchaseController::make_payment() posts, so the
     * GL reads identically whichever screen the money went out from.
     */
    private function postPurchaseSettlementJournal(TicketPurchase $tp, $bankId, $portalId, float $amount): void
    {
        $payableAccount = Account::where('code', config('accounts.accounts_payable'))->first();
        if (! $payableAccount) {
            throw new \Exception('Accounts Payable account (code '.config('accounts.accounts_payable').') not found. Please create it in Chart of Accounts.');
        }

        if ($bankId) {
            $source = Bank::find($bankId);
            $label = 'bank';
        } elseif ($portalId) {
            $source = Portal::find($portalId);
            $label = 'portal';
        } else {
            throw new \Exception('A bank or portal is required to record the payment.');
        }
        if (! $source || ! $source->account_id) {
            throw new \Exception(ucfirst($label).' not linked to chart of accounts.');
        }

        Bank::assertCanCover((int) $source->account_id, $amount, $source->name);

        $journal = JournalEntry::create([
            'company_id' => $tp->company_id ?? 2,
            'created_by' => auth()->id(),
            'date' => $tp->purchase_date,
            'reference' => $tp->ticket_no,
            'source' => 'ticket_purchase',
            'source_id' => $tp->id,
            'description' => 'Ticket purchase payment made (edit) — '.$tp->ticket_no,
            'beneficiary' => $tp->vendor?->name,
            'beneficiary_party_id' => $tp->vendor_id,
        ]);

        JournalItem::create([
            'journal_entry_id' => $journal->id,
            'account_id' => $payableAccount->id,
            'debit' => $amount,
            'credit' => 0,
            'note' => 'Payable cleared — '.$tp->ticket_no,
            'party_type' => 'vendor',
            'party_id' => $tp->vendor_id,
        ]);
        JournalItem::create([
            'journal_entry_id' => $journal->id,
            'account_id' => $source->account_id,
            'debit' => 0,
            'credit' => $amount,
            'note' => 'Paid via '.$label,
        ]);
    }

    // =========================================================================
    // JOURNAL HELPERS — Sale (recognition only)
    // =========================================================================

    private function createSaleJournal(TicketSale $ts, $bankId)
    {
        $revenueAccount = Account::where('code', config('accounts.ticket_sales_revenue'))->first();
        $receivableAccount = Account::where('code', config('accounts.accounts_receivable'))->first();

        if (! $revenueAccount) {
            throw new \Exception('Ticket Sales Revenue account (code '.config('accounts.ticket_sales_revenue').') not found. Please create it in Chart of Accounts.');
        }
        if ($ts->due_amount > 0 && ! $receivableAccount) {
            throw new \Exception('Accounts Receivable account (code '.config('accounts.accounts_receivable').') not found. Please create it in Chart of Accounts.');
        }

        $journal = JournalEntry::create([
            'company_id' => $ts->company_id ?? 2,
            'created_by' => auth()->id(),
            'date' => $ts->sale_date,
            'reference' => $ts->invoice_no,
            'source' => 'ticket_sale',
            'source_id' => $ts->id,
            'description' => 'Direct ticket sale — '.$ts->invoice_no,
            'beneficiary' => $ts->client?->name,
            'beneficiary_party_id' => $ts->client_id,
        ]);

        $items = [];

        if ($ts->paid_amount > 0 && $bankId) {
            $bank = Bank::find($bankId);
            if (! $bank || ! $bank->account_id) {
                throw new \Exception('Bank not linked to chart of accounts.');
            }
            $items[] = ['account_id' => $bank->account_id, 'debit' => $ts->paid_amount, 'credit' => 0, 'note' => 'Cash received'];
        }

        if ($ts->due_amount > 0) {
            $items[] = ['account_id' => $receivableAccount?->id, 'debit' => $ts->due_amount, 'credit' => 0, 'note' => 'Amount due from agent', 'party_type' => 'agent', 'party_id' => $ts->client_id];
        }

        $items[] = ['account_id' => $revenueAccount?->id, 'debit' => 0, 'credit' => $ts->total_amount, 'note' => 'Ticket sales revenue'];

        foreach ($items as $item) {
            JournalItem::create([...$item, 'journal_entry_id' => $journal->id]);
        }
    }

    /**
     * Update the recognition journal for a sale.
     *
     * Same settlement-detection guard as updatePurchaseJournal.
     */
    private function updateSaleJournal(TicketSale $ts, $bankId)
    {
        if ($ts->status === 'cancelled') {
            JournalEntry::where('source', 'ticket_sale')
                ->where('source_id', $ts->id)->get()->each(function ($entry) {
                    $entry->items()->delete();
                    $entry->delete();
                });

            return;
        }

        $journalCount = JournalEntry::where('source', 'ticket_sale')
            ->where('source_id', $ts->id)->count();

        $journal = JournalEntry::where('source', 'ticket_sale')
            ->where('source_id', $ts->id)->orderBy('id')->first();

        // Settlement journals exist → only touch metadata
        if ($journalCount > 1) {
            if ($journal) {
                $journal->update([
                    'date' => $ts->sale_date,
                    'description' => 'Direct ticket sale (edited) — '.$ts->invoice_no,
                    'beneficiary' => $ts->client?->name,
                    'beneficiary_party_id' => $ts->client_id,
                ]);
            }

            return;
        }

        // No settlements yet → safe to recreate items
        if ($journal) {
            $journal->items()->delete();
            $journal->update([
                'date' => $ts->sale_date,
                'description' => 'Direct ticket sale (edited) — '.$ts->invoice_no,
            ]);
        } else {
            $journal = JournalEntry::create([
                'company_id' => $ts->company_id ?? 2,
                'created_by' => auth()->id(),
                'date' => $ts->sale_date,
                'reference' => $ts->invoice_no,
                'source' => 'ticket_sale',
                'source_id' => $ts->id,
                'description' => 'Direct ticket sale (edited) — '.$ts->invoice_no,
                'beneficiary' => $ts->client?->name,
                'beneficiary_party_id' => $ts->client_id,
            ]);
        }

        $revenueAccount = Account::where('code', config('accounts.ticket_sales_revenue'))->first();
        $receivableAccount = Account::where('code', config('accounts.accounts_receivable'))->first();
        $items = [];

        if ($ts->paid_amount > 0 && $bankId) {
            $bank = Bank::find($bankId);
            if ($bank?->account_id) {
                $items[] = ['account_id' => $bank->account_id, 'debit' => $ts->paid_amount, 'credit' => 0, 'note' => 'Cash received'];
            }
        }

        if ($ts->due_amount > 0) {
            $items[] = ['account_id' => $receivableAccount?->id, 'debit' => $ts->due_amount, 'credit' => 0, 'note' => 'Amount due from agent', 'party_type' => 'agent', 'party_id' => $ts->client_id];
        }

        $items[] = ['account_id' => $revenueAccount?->id, 'debit' => 0, 'credit' => $ts->total_amount, 'note' => 'Ticket sales revenue'];

        foreach ($items as $item) {
            JournalItem::create([...$item, 'journal_entry_id' => $journal->id]);
        }
    }

    // =========================================================================
    // JOURNAL HELPERS — Refund
    // =========================================================================

    /**
     * Accounting journal for a ticket refund.
     *
     * Portal ticket:
     *   DR  Ticket Revenue        org_sale        (reverse original sale)
     *   DR  Portal Account        airline_refund  (airline returns money to portal)
     *   CR  Ticket Purchase Cost  org_cost        (reverse original cost)
     *   CR  Bank                  net paid        (customer refund paid from bank)
     *   CR  AR                    net due         (remaining customer refund due)
     *   CR  Service Charge Income service_charge  (our fee)
     *
     * Vendor ticket (no portal):
     *   DR  Ticket Revenue        org_sale
     *   CR  Bank                  net paid
     *   CR  AR                    net due
     *   CR  Service Charge Income service_charge
     *
     * Penalty is implicitly absorbed — it causes airline_refund < org_cost and
     * net_refund < org_sale, so the entry balances without a separate penalty line.
     */
    private function createRefundJournal(TicketRefund $refund): void
    {
        $revenueAccount = Account::where('code', config('accounts.ticket_sales_revenue'))->first();
        $costAccount = Account::where('code', config('accounts.ticket_purchase_cost'))->first();
        $arAccount = Account::where('code', config('accounts.accounts_receivable'))->first();
        $scAccount = Account::where('code', config('accounts.service_charge_income'))->first();

        if (! $revenueAccount) {
            throw new \Exception('Ticket Sales Revenue account (code '.config('accounts.ticket_sales_revenue').') not found.');
        }

        $journal = JournalEntry::create([
            'company_id' => $refund->company_id,
            'created_by' => auth()->id(),
            'date' => $refund->refund_date,
            'reference' => 'REF-'.$refund->id,
            'source' => 'ticket_refund',
            'source_id' => $refund->id,
            'description' => 'Ticket refund — '.$refund->original_invoice,
        ]);

        $items = [];

        // DR: Reverse original sale revenue
        $items[] = [
            'account_id' => $revenueAccount->id,
            'debit' => $refund->org_sale,
            'credit' => 0,
            'note' => 'Reverse ticket sale — '.$refund->original_invoice,
        ];

        // DR: Portal balance restored by airline (portal ticket only)
        if ($refund->portal_id && (float) $refund->airline_refund > 0) {
            $portal = Portal::find($refund->portal_id);
            if ($portal?->account_id) {
                $items[] = [
                    'account_id' => $portal->account_id,
                    'debit' => (float) $refund->airline_refund,
                    'credit' => 0,
                    'note' => 'Airline refund credited to portal',
                ];
            }

            // CR: Reverse original purchase cost (portal ticket only)
            if ($costAccount) {
                $items[] = [
                    'account_id' => $costAccount->id,
                    'debit' => 0,
                    'credit' => (float) $refund->org_cost,
                    'note' => 'Reverse ticket purchase cost',
                ];
            }
        }

        // CR: Bank — customer refund already paid out
        if ((float) $refund->paid_amount > 0 && $refund->bank_id) {
            $bank = Bank::find($refund->bank_id);
            if ($bank?->account_id) {
                Bank::assertCanCover(
                    (int) $bank->account_id,
                    (float) $refund->paid_amount,
                    $bank->name
                );

                $items[] = [
                    'account_id' => $bank->account_id,
                    'debit' => 0,
                    'credit' => (float) $refund->paid_amount,
                    'note' => 'Customer refund paid from bank',
                ];
            }
        }

        // CR: AR — remaining refund still owed to customer
        if ((float) $refund->due_amount > 0 && $arAccount) {
            $items[] = [
                'account_id' => $arAccount->id,
                'debit' => 0,
                'credit' => (float) $refund->due_amount,
                'note' => 'Customer refund due',
            ];
        }

        // CR: Service charge income
        if ((float) $refund->service_charge > 0 && $scAccount) {
            $items[] = [
                'account_id' => $scAccount->id,
                'debit' => 0,
                'credit' => (float) $refund->service_charge,
                'note' => 'Service charge on refund',
            ];
        }

        foreach ($items as $item) {
            JournalItem::create([...$item, 'journal_entry_id' => $journal->id]);
        }
    }

    /** Incoming airline refund restores portal balance. */
    private function creditToPortal(int $portalId, int $invoiceId, float $amount): void
    {
        $portal = Portal::findOrFail($portalId);
        $opening_balance = (float) $portal->balance;
        $new_balance = $opening_balance + $amount;

        PortalBalance::create([
            'invoice_id' => $invoiceId,
            'portal_id' => $portalId,
            'type' => 'ticket_refund',
            'old_balance' => $opening_balance,
            'payment_method' => 'airline_refund',
            'debit' => $amount,
            'credit' => 0,
            'current_balance' => $new_balance,
            'remarks' => 'Airline refund credited back to portal.',
            'created_by' => Auth::id(),
        ]);

        $portal->update(['balance' => $new_balance]);
    }
}
