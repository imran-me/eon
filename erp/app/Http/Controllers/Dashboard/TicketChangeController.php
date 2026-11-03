<?php

namespace App\Http\Controllers\Dashboard;

use App\Exceptions\TicketChangeRefused;
use App\Models\Account;
use App\Models\ContractFlightPassenger;
use App\Models\ContractFlightPassengerChange;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\Portal;
use App\Models\PortalBalance;
use App\Models\TicketChange;
use App\Models\TicketLeg;
use App\Models\TicketPurchase;
use App\Models\TicketTrailLog;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ReturnCancel;
use App\Services\TicketTrail\Timeline;
use App\Support\TravelCompany;
use App\Support\UserRoleSetting\Access;
use App\Traits\PostsPartyLedger;
use App\Traits\ResolvesPartyType;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Ticket Orders → Change (owner, 2026-10-06): re-issue, reschedule, refund or
 * void ONE ticket from Ticketing — "much more smoother and easy and automated,
 * ledger connected, should hit every journal it is related to, should not
 * happen any duplicate entry due to the contract flight".
 *
 * Two roads, never both:
 *
 *  1. The ticket belongs to a contract-flight passenger → the contract
 *     flight's own per-ticket Re-Issue / Refund (ContractFlightPassengerController::
 *     ticketAdjustment) does the work, exactly as from his card: Ticketing's
 *     cancel + refund, the penalty to the Company Ledger or his agent, the
 *     reissue fee to the ticket's seller, his stage and history. This class
 *     only records the change in the ticket's register. He stays on his
 *     flight; Re-assign on Flights & Passengers moves him to another one.
 *
 *  2. Any other ticket → Ticketing's own heads, every row ADDED, none edited:
 *
 *     refund / void   Ticketing's cancel (TicketPurchaseController::
 *                     cancelFromContractFlight → postCancellationReversal):
 *                       vendor row Cr refund · Dr AP (or portal) / Cr 5110 refund
 *                     what the airline kept stays in ticket cost (5110).
 *                     Sold to an agent → his agent price is taken back:
 *                       agent row Cr price · Dr 4110 / Cr 1311
 *     reissue /       the airline's penalty + fare difference is owed to the
 *     reschedule      ticket's seller, whoever pays it in the end:
 *                       vendor row Dr fee · Dr 5110 / Cr AP
 *                       (portal: wallet Cr fee · Dr 5110 / Cr portal account)
 *                     and the date moves (leg + departure_at, time kept).
 *     customer pays   ONE line on his statement — penalty (+ fare difference)
 *                     + our service fee: agent row Dr · Dr 1311 / Cr 4110.
 *     we bear it      nothing more: the cost already sits in 5110.
 */
class TicketChangeController implements HasMiddleware
{
    use PostsPartyLedger, ResolvesPartyType;

    /**
     * The day a replayed change posts on (Ticket Trail, 2026-10-26). Null on every
     * request: postings then take today, exactly as before.
     */
    private ?Carbon $clock = null;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view ticket purchase|view all ticket purchase|view ticket direct sale|view all ticket direct sale|create ticket direct sale|create ticket purchase', only: ['context', 'refundedFor']),
            new Middleware('permission:edit ticket purchase|edit ticket direct sale', only: ['store', 'correctRefund']),
            // The cancel-return window lives on the ticket; a contract-flight clerk sets it from the lane.
            new Middleware('permission:edit ticket purchase|edit ticket direct sale|edit contract flight', only: ['returnWindow']),
        ];
    }

    /** Everything the Change dialog needs about one ticket. */
    public function context(Request $request, string $role, int $ticketPurchase)
    {
        $t = TicketPurchase::with(['passportHolder:id,name,passport_no', 'ticket:id,title', 'vendor:id,name', 'portal:id,name'])->findOrFail($ticketPurchase);
        $pax = $this->passengerFor($t);
        $sale = $this->saleFor($t);
        $travelAt = $t->travelAt();
        $cfFlight = $pax?->contractFlight;
        $refundChange = ! $pax && $t->status === 'cancelled'
            ? TicketChange::where('ticket_purchase_id', $t->id)->whereIn('kind', ['refund', 'void'])->latest('id')->first()
            : null;

        return response()->json([
            'success' => true,
            'ticket' => [
                'id' => $t->id,
                'pnr' => $t->ticket_no ?: ('#'.$t->id),
                'passenger' => $t->passportHolder?->name,
                'passport' => $t->passportHolder?->passport_no,
                'route' => $t->ticket?->title,
                'amount' => (float) $t->amount,
                'paid' => (float) $t->paid_amount,
                'due' => (float) $t->due_amount,
                'status' => $t->status,
                'seller' => $t->vendor?->name ?: ($t->portal?->name ? $t->portal->name.' (portal)' : null),
                'travel_at' => $travelAt,
            ],
            'cf' => $pax ? [
                'passenger_id' => $pax->id,
                'flight' => $cfFlight?->flight_number,
                'flight_date' => $cfFlight?->departure_at?->format('d M Y'),
                'off_flight' => ! $pax->contract_flight_id,
                'agent' => $pax->agent?->name,
                'can' => ContractFlightPassengerChange::ticketAdjustmentAvailable() && (bool) $request->user()?->can('edit contract flight'),
                'url' => $this->flightsUrl($role, $t),
            ] : null,
            'sale' => $sale ? ['client_id' => $sale->client_id, 'client' => $sale->client_name, 'price' => (float) $sale->price, 'invoice' => $sale->invoice_no] : null,
            // Refund → new ticket (2026-10-07). A contract flight's ticket goes back through Re-assign instead.
            'refund_amount' => $t->status === 'cancelled' && $t->refund_amount !== null ? (float) $t->refund_amount : null,
            'was_cf' => $t->status === 'cancelled' && $this->wasContractFlight($t),
            'can_new' => (bool) ($request->user()?->can('create ticket direct sale') || $request->user()?->can('create ticket purchase')),
            // Cancel return (2026-10-14): family? what would go? the window; and the agent's total now.
            'ret' => ReturnCancel::state($t, $pax),
            'agent_total' => $pax ? round($pax->chargedPackage(), 2) : $this->agentTotalFor($t, $sale),
            'refund_correction' => $refundChange && $sale ? [
                'change_id' => $refundChange->id,
                'airline_refund' => (float) $refundChange->refund_amount,
                'airline_penalty' => (float) $refundChange->penalty,
                'penalty_bearer' => $refundChange->bearer,
                'service_fee' => (float) $refundChange->service_fee,
                'agent_refund' => (float) $refundChange->sale_reversed,
                'agent_sale_price' => (float) $sale->price,
                'agent' => $sale->client_name,
            ] : null,
            'history' => TicketChange::with('creator:id,name')->where('ticket_purchase_id', $t->id)->latest('id')->get()
                ->map(fn (TicketChange $c) => [
                    'code' => $c->kind,
                    'kind' => TicketChange::KINDS[$c->kind] ?? $c->kind,
                    'when' => $c->created_at?->format('d M Y, h:i A'),
                    'by' => $c->creator?->name,
                    'note' => $c->note,
                ]),
            // All of the ticket's history, newest first (owner, 2026-10-26: "All History"),
            // and whether this user may open its Trail ("All Trails and Edit").
            'timeline' => $this->timelineFor($t),
            'trail' => Access::allows('travels.ticketing', 'e') && TicketTrailLog::available(),
        ]);
    }

    /**
     * Correct the customer side of an already-posted Ticketing refund. The
     * original rows remain as audit history; balanced delta rows bring the
     * agent ledger and journals to the corrected totals.
     */
    public function correctRefund(Request $request, string $role, int $ticketPurchase)
    {
        $v = $request->validate([
            'agent_refund' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'service_fee' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'confirm' => ['required', 'in:CONFIRM'],
            'token' => ['required', 'string', 'max:64'],
        ]);

        if ($done = TicketChange::where('token', $v['token'])->first()) {
            return response()->json(['success' => true, 'message' => 'Correction already saved.', 'change_id' => $done->id]);
        }

        try {
            $result = DB::transaction(function () use ($ticketPurchase, $v) {
                $t = TicketPurchase::whereKey($ticketPurchase)->lockForUpdate()->firstOrFail();
                if ($t->status !== 'cancelled' || $this->passengerFor($t)) {
                    $this->refuse('Only a refunded Ticketing ticket can be corrected here.');
                }
                $sale = $this->saleFor($t);
                if (! $sale) {
                    $this->refuse('This refunded ticket has no agent sale to correct.');
                }
                $change = TicketChange::where('ticket_purchase_id', $t->id)
                    ->whereIn('kind', ['refund', 'void'])->lockForUpdate()->latest('id')->first();
                if (! $change) {
                    $this->refuse('The original refund record was not found.');
                }

                $newRefund = round((float) $v['agent_refund'], 2);
                $salePrice = round((float) $sale->price, 2);
                if ($newRefund > $salePrice) {
                    $this->refuse('The agent refund cannot exceed the original agent price (৳'.number_format($salePrice, 2).').');
                }
                $penalty = round((float) $change->penalty, 2);
                // The airline kept this amount before returning $newRefund. The difference
                // between the original agent price and that refund already leaves the
                // retained amount on the agent's ledger; posting it again double-charges him.
                $newPenalty = 0.0;
                $newFee = round((float) $v['service_fee'], 2);
                $oldRefund = round((float) $change->sale_reversed, 2);
                $oldFee = round((float) $change->service_fee, 2);
                $oldPenalty = max(0, round((float) $change->charged - $oldFee, 2));
                $reversalPosting = Transaction::where('invoice_id', $change->id)
                    ->where('type', TicketChange::SALE_REVERSAL_TYPE)->latest('id')->first(['payment_date', 'transaction_time', 'created_at']);
                $chargePosting = Transaction::where('invoice_id', $change->id)
                    ->where('type', TicketChange::CHARGE_TYPE)->latest('id')->first(['payment_date', 'transaction_time', 'created_at']);
                $name = $t->passportHolder?->name ?: 'passenger';
                $pnr = $t->ticket_no ?: ('#'.$t->id);

                $audit = TicketChange::create([
                    'company_id' => $t->company_id, 'ticket_purchase_id' => $t->id,
                    'kind' => 'refund_correction', 'source' => 'ticketing', 'token' => $v['token'],
                    'client_id' => $sale->client_id, 'service_fee' => 0, 'charged' => 0, 'sale_reversed' => 0,
                    'note' => 'Refund corrected — agent refund ৳'.number_format($oldRefund, 2).' → ৳'.number_format($newRefund, 2)
                        .'; airline penalty ৳'.number_format($penalty, 2).' included in the reduced agent refund'
                        .'; our charge ৳'.number_format($oldFee, 2).' → ৳'.number_format($newFee, 2).' · '.$v['reason'],
                    'created_by' => Auth::id(),
                ]);

                $this->postCustomerDelta($audit, $t, (int) $sale->client_id, TicketChange::SALE_REVERSAL_TYPE,
                    $newRefund - $oldRefund, 'ticket_sales_revenue', 'Agent refund corrected — '.$pnr.' · '.$name, true,
                    $reversalPosting?->payment_date, $this->postingTime($reversalPosting));
                $this->postCustomerDelta($audit, $t, (int) $sale->client_id, TicketChange::CHARGE_TYPE,
                    $newPenalty - $oldPenalty, 'cf_penalty_recovered', 'Airline penalty corrected — '.$pnr.' · '.$name, false,
                    $chargePosting?->payment_date, $this->postingTime($chargePosting));
                $this->postCustomerDelta($audit, $t, (int) $sale->client_id, TicketChange::CHARGE_TYPE,
                    $newFee - $oldFee, 'cf_change_recovered', 'Service charge corrected — '.$pnr.' · '.$name, false,
                    $chargePosting?->payment_date, $this->postingTime($chargePosting));

                $change->update([
                    'bearer' => $penalty > 0 ? 'customer' : null,
                    'service_fee' => $newFee,
                    'sale_reversed' => $newRefund,
                    'charged' => $newFee,
                    'note' => 'Refunded — airline refund ৳'.number_format((float) $change->refund_amount, 2)
                        .', kept ৳'.number_format($penalty, 2).' (included in reduced agent refund)'
                        .($newFee > 0 ? ', service fee ৳'.number_format($newFee, 2) : ''),
                ]);

                return ['change_id' => $audit->id, 'message' => 'Refund corrected. Agent refund ৳'.number_format($newRefund, 2)
                    .'; airline-kept ৳'.number_format($penalty, 2).' was not charged twice; service fee ৳'.number_format($newFee, 2).'.'];
            });
        } catch (TicketChangeRefused $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true] + $result);
    }

    public function store(Request $request, string $role, int $ticketPurchase)
    {
        if (! TicketChange::available()) {
            return response()->json(['success' => false, 'message' => 'This feature is not installed on this server yet.'], 422);
        }
        $v = $request->validate([
            'kind' => ['required', Rule::in(array_diff(array_keys(TicketChange::KINDS), ['refund_correction']))],
            'penalty' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'fare_difference' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'service_fee' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'refund' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            // Void (owner, 2026-10-11): the agent price to reverse, when not the full sale price
            'agent_reversal' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'new_travel_at' => ['nullable', 'date'],
            // Who bears each cost (owner, 2026-10-06: "an option to choose,
            // for each cost, who bears it"). `bearer` is the old one-for-all.
            'penalty_bearer' => ['nullable', Rule::in(array_keys(TicketChange::BEARERS))],
            'fare_bearer' => ['nullable', Rule::in(array_keys(TicketChange::BEARERS))],
            'bearer' => ['nullable', Rule::in(array_keys(TicketChange::BEARERS))],
            'note' => ['nullable', 'string', 'max:1000'],
            // Refund → new ticket (2026-10-07)
            'new_pnr' => ['nullable', 'string', 'max:200'],
            'new_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'sale_price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            // Cancel return (2026-10-14): the agent's total kept, or lowered to a new total / by an amount given back
            'agent_mode' => ['nullable', Rule::in(['keep', 'total', 'giveback'])],
            'agent_value' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'token' => ['required', 'string', 'max:64'],
        ]);
        $v['penalty_bearer'] = $v['penalty_bearer'] ?? $v['bearer'] ?? null;
        $v['fare_bearer'] = $v['fare_bearer'] ?? $v['bearer'] ?? null;

        // A double click, or Confirm pressed again after a slow answer.
        if ($done = TicketChange::where('token', $v['token'])->first()) {
            return response()->json(['success' => true, 'message' => 'Already saved.', 'change_id' => $done->id]);
        }

        $t = TicketPurchase::findOrFail($ticketPurchase);
        $pax = $this->passengerFor($t);

        try {
            $result = match (true) {
                $v['kind'] === 'newticket' => DB::transaction(fn () => $this->newTicketAfterRefund($request, $role, $t, $v)),
                $v['kind'] === 'return_cancel' => $pax
                    ? $this->returnCancelThroughFlight($request, $role, $t, $pax, $v)
                    : DB::transaction(fn () => $this->returnCancelOnTicketing($t, $v)),
                (bool) $pax => $this->throughContractFlight($request, $role, $t, $pax, $v),
                default => DB::transaction(fn () => $this->onTicketing($t, $v)),
            };
        } catch (TicketChangeRefused $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (UniqueConstraintViolationException) {
            // Same token raced in twice: the first one won and holds the postings.
            return response()->json(['success' => true, 'message' => 'Already saved.']);
        }

        return response()->json(['success' => true] + $result);
    }

    // ── Road 1: a contract-flight passenger's ticket ────────────────────────

    private function throughContractFlight(Request $request, string $role, TicketPurchase $t, ContractFlightPassenger $pax, array $v): array
    {
        if (! ContractFlightPassengerChange::ticketAdjustmentAvailable()) {
            $this->refuse('This ticket belongs to a contract flight passenger, and that part is not installed on this server yet.');
        }
        if (! $request->user()?->can('edit contract flight')) {
            $this->refuse('This ticket belongs to a contract flight passenger — changing it needs the "edit contract flight" permission.');
        }
        if ($t->status !== 'confirm') {
            $this->refuse('Only a purchased ticket can be changed. This one is '.($t->status === 'cancelled' ? 'already cancelled' : $t->status).'.');
        }
        // Our charge (owner, 2026-10-13): passed to the flight's own Re-Issue /
        // Refund, which bills it to the agent on his ONE line (Cr 4183).
        $ours = round((float) ($v['service_fee'] ?? 0), 2);
        if ($ours > 0 && ! $pax->agent_id) {
            $this->refuse('This passenger has no agent to bill our charge to — clear it.');
        }

        $kind = $v['kind'];
        $penalty = round((float) ($v['penalty'] ?? 0), 2);
        $fare = round((float) ($v['fare_difference'] ?? 0), 2);
        $amount = (float) $t->amount;
        $pnr = $t->ticket_no ?: ('#'.$t->id);
        $oldAt = $t->travelAt();
        $newAt = ! empty($v['new_travel_at']) ? (string) $v['new_travel_at'] : null;
        $cfBearer = fn (?string $b) => $b === 'customer' ? 'agent' : 'company';

        // Reschedule with nothing to pay: only the date moves. The contract
        // flight's buttons always carry a charge, so this one small step is
        // done here — and logged on his card like every other change.
        if ($kind === 'reschedule' && $penalty + $fare + $ours <= 0) {
            if (! $newAt) {
                $this->refuse('Choose the new travel date.');
            }

            return DB::transaction(function () use ($t, $pax, $v, $newAt, $pnr, $role) {
                $locked = TicketPurchase::whereKey($t->id)->lockForUpdate()->first();
                if (! $locked || $locked->status !== 'confirm') {
                    $this->refuse('This ticket was just changed by someone else. Reload the page.');
                }
                $moved = $locked->moveTravelDate($newAt);
                if (! $moved) {
                    $this->refuse('That is already the ticket\'s travel date.');
                }
                $pax->events()->create([
                    'type' => 'exception', 'code' => 'rescheduled', 'label' => 'Ticket rescheduled',
                    'note' => $pnr.' · '.$this->day($moved[0]).' → '.$this->day($moved[1]).' · no charge (from Ticket Orders)'.(! empty($v['note']) ? ' · '.$v['note'] : ''),
                    'occurred_at' => now(), 'created_by' => Auth::id(),
                ]);
                $c = $this->record($locked, $v, [
                    'source' => 'contract_flight', 'contract_flight_passenger_id' => $pax->id,
                    'old_travel_at' => $moved[0], 'new_travel_at' => $moved[1],
                    'note' => trim('Rescheduled '.$this->day($moved[0]).' → '.$this->day($moved[1]).', no charge · '.($v['note'] ?? ''), ' ·'),
                ]);

                return ['message' => 'Rescheduled. '.$pnr.' now flies '.$this->day($moved[1]).'.', 'change_id' => $c->id,
                    'notice' => $this->leg1Notice($pax->fresh(['contractFlight']), $t, $moved[1], $role)];
            });
        }

        // Everything else is the contract flight's own Re-Issue / Refund —
        // one call per bearer: the flight's Re-Issue takes one "who pays" per
        // charge, so a split (penalty to the agent, fare difference ours) is
        // two of its own postings, exactly as two presses of its button.
        $calls = [];
        $isRefund = in_array($kind, ['refund', 'void'], true);
        if ($isRefund) {
            // void: the vendor refund typed on the dialog, the full cost when left blank
            $refund = isset($v['refund']) ? (float) $v['refund'] : ($kind === 'void' ? $amount : null);
            if ($refund === null) {
                $this->refuse('Enter what the airline refunds.');
            }
            if ($refund < $amount && empty($v['penalty_bearer'])) {
                $this->refuse('Choose who bears what the airline keeps (৳'.number_format($amount - $refund, 2).').');
            }
            $calls[] = ['type' => 'refund', 'refund' => min($refund, $amount), 'bearer' => $cfBearer($v['penalty_bearer']), 'fee' => $ours,
                'note' => $kind === 'void' ? trim('Void · '.($v['note'] ?? ''), ' ·') : ($v['note'] ?? null)];
        } else {
            if ($penalty + $fare + $ours <= 0) {
                $this->refuse('Enter the airline’s re-issue penalty, a fare difference or our charge.');
            }
            if ($penalty > 0 && empty($v['penalty_bearer'])) {
                $this->refuse('Choose who bears the airline penalty.');
            }
            if ($fare > 0 && empty($v['fare_bearer'])) {
                $this->refuse('Choose who bears the fare difference.');
            }
            if (! $pax->contract_flight_id) {
                $this->refuse($pax->passportHolder?->name.' is off his flight (under Cancelled). Put him on a flight with Re-assign on Flights & Passengers — the new ticket is chosen there.');
            }
            foreach (['agent', 'company'] as $side) {
                $p = $penalty > 0 && $cfBearer($v['penalty_bearer']) === $side ? $penalty : 0.0;
                $f = $fare > 0 && $cfBearer($v['fare_bearer']) === $side ? $fare : 0.0;
                $o = $side === 'agent' ? $ours : 0.0;   // our charge rides with the agent's share
                if ($p + $f + $o > 0) {
                    $calls[] = ['type' => 'reissue', 'penalty' => $p, 'fare_diff' => $f, 'fee' => $o, 'bearer' => $side, 'note' => $v['note'] ?? null];
                }
            }
            // The date moves once, with the first posting.
            $calls[0]['new_travel_date'] = $newAt ? substr($newAt, 0, 10) : null;
        }

        $before = (int) ContractFlightPassengerChange::where('contract_flight_passenger_id', $pax->id)->max('id');

        return DB::transaction(function () use ($request, $role, $pax, $t, $v, $kind, $penalty, $fare, $oldAt, $newAt, $before, $calls, $isRefund, $amount) {
            $notice = null;
            $message = null;
            foreach ($calls as $payload) {
                $sub = Request::create($request->url(), 'POST', $payload + ['ticket_purchase_id' => $t->id]);
                $sub->setUserResolver(fn () => $request->user());
                $sub->headers->set('Accept', 'application/json');
                // Fresh each time: the flight's lock compares him to the row.
                $p = ContractFlightPassenger::with(['contractFlight', 'passportHolder'])->findOrFail($pax->id);
                $data = app(ContractFlightPassengerController::class)->ticketAdjustment($sub, $role, $p)->getData(true);
                if (empty($data['success'])) {
                    $this->refuse($data['message'] ?? 'The contract flight refused this change.');
                }
                $notice = $notice ?: ($data['notice'] ?? null);
                $message = $message ?: ($data['message'] ?? null);
            }
            $t->refresh();
            $changeIds = ContractFlightPassengerChange::where('contract_flight_passenger_id', $pax->id)->where('id', '>', $before)->orderBy('id')->pluck('id');
            $refund = $isRefund ? (float) ($t->refund_amount ?? $calls[0]['refund']) : null;
            $nowAt = $t->travelAt();
            $kept = $isRefund ? max(0, $amount - (float) $refund) : 0.0;
            $c = $this->record($t, $v, [
                'source' => 'contract_flight',
                'contract_flight_passenger_id' => $pax->id,
                'contract_flight_passenger_change_id' => $changeIds->first(),
                'penalty' => $isRefund ? $kept : $penalty,
                'fare_difference' => $isRefund ? 0 : $fare,
                'refund_amount' => $refund,
                'bearer' => $isRefund ? ($kept > 0 ? $v['penalty_bearer'] : null)
                    : $this->bearerOf([[$penalty, $v['penalty_bearer']], [$fare, $v['fare_bearer']]]),
                'old_travel_at' => ! $isRefund && $newAt && $nowAt !== $oldAt ? $oldAt : null,
                'new_travel_at' => ! $isRefund && $newAt && $nowAt !== $oldAt ? $nowAt : null,
                'note' => trim(($kind === 'void' ? 'Voided' : ($isRefund ? 'Refunded' : TicketChange::KINDS[$kind])).' through '.($pax->contractFlight?->flight_number ?: 'the contract flight')
                    .($changeIds->isNotEmpty() ? ' (change #'.$changeIds->implode(', #').')' : '').' · '.($v['note'] ?? ''), ' ·'),
            ]);
            if ($isRefund) {
                $name = e($pax->passportHolder?->name ?: 'He');
                $notice = $name.' is still on <b>'.e($pax->contractFlight?->flight_number ?: 'his flight').'</b> without this ticket.'
                    .' To put him on another flight, use <b>Re-assign</b> on his row in <a href="'.e($this->flightsUrl($role, $t)).'" style="color:#4f46e5;font-weight:700;">Flights &amp; Passengers</a>.';
            } elseif ($notice) {
                $notice .= ' <a href="'.e($this->flightsUrl($role, $t)).'" style="color:#4f46e5;font-weight:700;">Open Flights &amp; Passengers</a>';
            }

            return ['message' => $message ?: 'Saved and posted.', 'change_id' => $c->id, 'notice' => $notice];
        });
    }

    // ── Road 2: a Ticketing-only ticket ─────────────────────────────────────

    private function onTicketing(TicketPurchase $t, array $v): array
    {
        // Lock the ticket for the whole posting: two clerks, or a double
        // submit with two tokens, must not both refund it.
        $t = TicketPurchase::whereKey($t->id)->lockForUpdate()->first();
        if (! $t || $t->status !== 'confirm') {
            $this->refuse('Only a purchased ticket can be changed. This one is '.($t?->status === 'cancelled' ? 'already cancelled' : ($t?->status ?: 'missing')).'.');
        }
        $kind = $v['kind'];
        $amount = round((float) $t->amount, 2);
        $pnr = $t->ticket_no ?: ('#'.$t->id);
        $sale = $this->saleFor($t);
        $fee = round((float) ($v['service_fee'] ?? 0), 2);
        $name = $t->passportHolder?->name ?: 'passenger';
        $nobody = 'This ticket is not sold to anyone, so there is nobody to bill. Choose "We bear it".';

        if ($fee > 0 && ! $sale) {
            $this->refuse('Our service fee is billed to the customer, and this ticket is not sold to anyone — clear the fee.');
        }

        if (in_array($kind, ['refund', 'void'], true)) {
            // void (owner, 2026-10-11): two figures — the vendor price refunded (full cost
            // when blank) and the agent price reversed (full sale price when blank)
            $refund = isset($v['refund']) ? round(min((float) $v['refund'], $amount), 2) : ($kind === 'void' ? $amount : null);
            if ($refund === null) {
                $this->refuse('Enter what the airline refunds.');
            }
            $kept = round($amount - $refund, 2);
            $keptBy = $kept > 0 ? ($v['penalty_bearer'] ?? null) : null;
            if ($kept > 0 && ! $keptBy) {
                $this->refuse('Choose who bears what the airline keeps (৳'.number_format($kept, 2).').');
            }
            if ($keptBy === 'customer' && ! $sale) {
                $this->refuse($nobody);
            }
            $label = $kind === 'void' ? 'Voided' : 'Refunded';
            $c = $this->record($t, $v, [
                'refund_amount' => $refund,
                'penalty' => $kept,
                'bearer' => $keptBy,
                'client_id' => $sale?->client_id,
                'note' => trim($label.' — airline refund ৳'.number_format($refund, 2)
                    .($kept > 0 ? ', kept ৳'.number_format($kept, 2).' ('.($keptBy === 'customer' ? 'customer pays' : 'we bear').')' : '')
                    .($fee > 0 ? ', service fee ৳'.number_format($fee, 2) : '').' · '.($v['note'] ?? ''), ' ·'),
            ]);

            // 1. Ticketing's own cancel: the ticket stays at its amount, the
            //    refund is posted beside it, the seat goes back, the payable
            //    schedule closes. Same code the contract flight uses.
            app(TicketPurchaseController::class)->cancelFromContractFlight($t,
                $label.' — '.$name.' · airline refund ৳'.number_format($refund, 2).($kept > 0 ? ' · airline kept ৳'.number_format($kept, 2) : '')
                .(! empty($v['note']) ? ' · '.$v['note'] : '').' · '.$this->clock()->format('d M Y'), $refund, $this->clock?->copy());

            // 1b. What the airline kept is a cost the change caused: it moves
            //     from ticket cost (5110) onto the Company Ledger — Dr 5171
            //     Airline Penalty (Borne) / Cr 5110, whoever bears it; the
            //     agent's share comes back below (Cr 4181 Recovered). Same as
            //     the contract flight's postChangeCosts().
            if ($kept > 0) {
                $this->postCompanyReclass($c, $t, $kept, 'cf_penalty_company', 'ticket_purchase_cost',
                    'Airline penalty kept — '.$pnr.' · '.$name.' · '.($keptBy === 'customer' ? 'agent pays — recovered' : 'we bear'));
            }

            // 2. Sold → the agent price comes back off his ledger (he is not
            //    charged for a ticket that did not fly).
            $reversed = 0.0;
            if ($sale && (float) $sale->price > 0) {
                $reversed = round((float) $sale->price, 2);
                // A refund's own figure only comes from a Trail replay: a corrected refund
                // (correctRefund) gave the agent back less than the whole price.
                if (($kind === 'void' || ! empty($v['replay'])) && isset($v['agent_reversal']) && $v['agent_reversal'] !== '') {
                    $reversed = round(min((float) $v['agent_reversal'], (float) $sale->price), 2);
                }
            }
            if ($reversed > 0) {
                $this->postCustomer($c, $t, (int) $sale->client_id, TicketChange::SALE_REVERSAL_TYPE, 0, $reversed,
                    'Ticket '.strtolower($label).' — agent price reversed · '.$pnr.' · '.$name.($sale->invoice_no ? ' ('.$sale->invoice_no.')' : ''),
                    [['ticket_sales_revenue', $reversed, 'Ticket sale reversed — '.$pnr]]);
            }

            // 3. ONE line on his statement: the parts he bears + our fee. A corrected
            //    refund that already kept the penalty out of his refund (owner,
            //    2026-10-19) does not bill it again — Trail replays say so.
            $penaltyHis = $keptBy === 'customer' && ($v['penalty_billed'] ?? true) ? $kept : 0.0;
            $charged = 0.0;
            if ($penaltyHis + $fee > 0) {
                $charged = round($penaltyHis + $fee, 2);
                $this->postCustomer($c, $t, (int) $sale->client_id, TicketChange::CHARGE_TYPE, $charged, 0,
                    // one figure, no split on the agent's statement (owner, 2026-10-13)
                    ($kind === 'void' ? 'Void' : 'Refund').' penalty — '.$pnr.' · '.$name,
                    array_values(array_filter([
                        $penaltyHis > 0 ? ['cf_penalty_recovered', $penaltyHis, 'Airline penalty recovered — '.$pnr] : null,
                        $fee > 0 ? ['cf_change_recovered', $fee, 'Our charge (profit) — '.$pnr] : null,
                    ])));
            }
            $c->update(['sale_reversed' => $reversed, 'charged' => $charged]);

            return ['change_id' => $c->id, 'message' => $label.' and posted: refund ৳'.number_format($refund, 2)
                .($reversed > 0 ? ', agent price ৳'.number_format($reversed, 2).' reversed' : '')
                .($charged > 0 ? ', ৳'.number_format($charged, 2).' charged to '.$sale->client_name : '').'.'];
        }

        // reissue / reschedule
        $penalty = round((float) ($v['penalty'] ?? 0), 2);
        $fare = round((float) ($v['fare_difference'] ?? 0), 2);
        $cost = round($penalty + $fare, 2);
        $newAt = ! empty($v['new_travel_at']) ? (string) $v['new_travel_at'] : null;
        if ($kind === 'reissue' && $cost <= 0) {
            $this->refuse('Enter the airline\'s re-issue penalty or a fare difference.');
        }
        if ($kind === 'reschedule' && ! $newAt) {
            $this->refuse('Choose the new travel date.');
        }
        $penBy = $penalty > 0 ? ($v['penalty_bearer'] ?? null) : null;
        $fareBy = $fare > 0 ? ($v['fare_bearer'] ?? null) : null;
        if ($penalty > 0 && ! $penBy) {
            $this->refuse('Choose who bears the airline penalty.');
        }
        if ($fare > 0 && ! $fareBy) {
            $this->refuse('Choose who bears the fare difference.');
        }
        if (($penBy === 'customer' || $fareBy === 'customer') && ! $sale) {
            $this->refuse($nobody);
        }
        if ($cost > 0 && ! $t->vendor_id && ! $t->portal_id) {
            $this->refuse('This ticket has no seller (vendor or portal) to owe the fee to. Set it on the ticket first.');
        }
        $moved = $newAt ? $t->moveTravelDate($newAt) : null;
        if ($kind === 'reschedule' && ! $moved && $cost <= 0 && $fee <= 0) {
            $this->refuse('That is already the ticket\'s travel date.');
        }
        $label = TicketChange::KINDS[$kind];
        $penaltyHis = $penBy === 'customer' ? $penalty : 0.0;
        $fareHis = $fareBy === 'customer' ? $fare : 0.0;
        $ours = round($cost - $penaltyHis - $fareHis, 2);
        $split = implode(', ', array_filter([
            $penaltyHis + $fareHis > 0 ? 'customer pays ৳'.number_format($penaltyHis + $fareHis, 2) : null,
            $ours > 0 ? 'we bear ৳'.number_format($ours, 2) : null,
        ]));
        $c = $this->record($t, $v, [
            'penalty' => $penalty,
            'fare_difference' => $fare,
            'bearer' => $this->bearerOf([[$penalty, $penBy], [$fare, $fareBy]]),
            'client_id' => $sale?->client_id,
            'old_travel_at' => $moved[0] ?? null,
            'new_travel_at' => $moved[1] ?? null,
            'note' => trim($label.($moved ? ' '.$this->day($moved[0]).' → '.$this->day($moved[1]) : '')
                .($cost > 0 ? ' · airline ৳'.number_format($cost, 2).' ('.$split.')' : '')
                .($fee > 0 ? ' · service fee ৳'.number_format($fee, 2) : '').' · '.($v['note'] ?? ''), ' ·'),
        ]);

        // The airline's penalty + fare difference is owed to the seller,
        // whoever ends up bearing it.
        if ($cost > 0) {
            $moveTxt = $moved ? ' · '.$this->day($moved[0]).' → '.$this->day($moved[1]) : '';
            $this->postSellerFee($c, $t, [
                [$label.' charge — '.$pnr.' · '.$name.$moveTxt.' · '.($penBy === 'customer' ? 'agent pays — recovered' : 'we bear'), $penalty, TicketChange::FEE_TYPE, 'cf_change_company'],
                ['Fare difference — '.$pnr.' · '.$name.$moveTxt.' · '.($fareBy === 'customer' ? 'agent pays — recovered' : 'we bear'), $fare, TicketChange::FARE_TYPE, 'cf_fare_diff_company'],
            ]);
        }
        // ONE line on his statement: the parts he bears + our fee.
        $charged = 0.0;
        if ($penaltyHis + $fareHis + $fee > 0) {
            $charged = round($penaltyHis + $fareHis + $fee, 2);
            $this->postCustomer($c, $t, (int) $sale->client_id, TicketChange::CHARGE_TYPE, $charged, 0,
                // one figure, no split on the agent's statement (owner, 2026-10-13)
                $label.' penalty — '.$pnr.' · '.$name,
                array_values(array_filter([
                    // owner, 2026-10-07: penalty recovered (4181) apart from our charge = profit (4183)
                    $penaltyHis > 0 ? ['cf_penalty_recovered', $penaltyHis, 'Airline penalty recovered — '.$pnr] : null,
                    $fareHis > 0 ? ['cf_fare_diff_recovered', $fareHis, 'Fare difference recovered — '.$pnr] : null,
                    $fee > 0 ? ['cf_change_recovered', $fee, 'Our charge (profit) — '.$pnr] : null,
                ])));
        }
        $c->update(['cost_added' => $cost, 'charged' => $charged]);

        return ['change_id' => $c->id, 'message' => $label.' saved'.($cost > 0 || $charged > 0 ? ' and posted' : '')
            .($moved ? ': '.$pnr.' now flies '.$this->day($moved[1]) : '')
            .($cost > 0 ? ' · ৳'.number_format($cost, 2).' owed to the seller' : '')
            .($charged > 0 ? ' · ৳'.number_format($charged, 2).' charged to '.$sale->client_name : '').'.'];
    }

    /**
     * Refund → new ticket (owner, 2026-10-07): "if they refund a ticket that
     * gets cancelled, and then if he purchases another ticket in another date
     * then we calculate the new tickets vs old."
     *
     * The new ticket is issued by Ticket Entry's own store() — same passenger,
     * route and seller; the new PNR, date and cost; the agent price as its
     * sale — so it posts exactly what typing it there would. On top, recorded
     * on the OLD ticket, the fare difference (new cost − old cost) is treated
     * like the airline's kept penalty on a refund:
     *   always      Dr 5172 Fare Difference (Borne) / Cr 5110 — Company Ledger
     *   agent pays  ONE line: fare difference + our fee · Dr 1311 / Cr 4182 / Cr 4183
     */
    private function newTicketAfterRefund(Request $request, string $role, TicketPurchase $old, array $v): array
    {
        $old = TicketPurchase::whereKey($old->id)->lockForUpdate()->first();
        if (! $old || $old->status !== 'cancelled') {
            $this->refuse('A new ticket is made from a refunded or voided ticket. Refund this one first.');
        }
        if ($this->wasContractFlight($old)) {
            $this->refuse('This was a contract flight passenger\'s ticket — his new ticket is made with Re-assign on Flights & Passengers.');
        }
        if (! ($request->user()?->can('create ticket direct sale') || $request->user()?->can('create ticket purchase'))) {
            $this->refuse('Making a new ticket needs the "create ticket purchase" permission.');
        }
        if (TicketChange::where('ticket_purchase_id', $old->id)->where('kind', 'newticket')->exists()) {
            $this->refuse('A new ticket has already been made for this refund.');
        }
        $pnr = trim((string) ($v['new_pnr'] ?? ''));
        $newAt = ! empty($v['new_travel_at']) ? (string) $v['new_travel_at'] : null;
        if ($pnr === '') {
            $this->refuse('Enter the new ticket\'s PNR.');
        }
        if (! $newAt) {
            $this->refuse('Choose the new ticket\'s travel date.');
        }
        if (! isset($v['new_cost'])) {
            $this->refuse('Enter the new ticket\'s cost.');
        }
        if (! $old->ticket_id || (! $old->vendor_id && ! $old->portal_id)) {
            $this->refuse('The old ticket has no route or seller to copy — enter the new ticket on Ticket Entry.');
        }
        $newCost = round((float) $v['new_cost'], 2);
        $oldCost = round((float) $old->amount, 2);
        $fare = round(max(0, $newCost - $oldCost), 2);
        $fee = round((float) ($v['service_fee'] ?? 0), 2);
        $price = isset($v['sale_price']) ? round((float) $v['sale_price'], 2) : null;
        $sale = $this->saleFor($old);
        $fareBy = $fare > 0 ? ($v['fare_bearer'] ?? null) : null;
        if ($fare > 0 && ! $fareBy) {
            $this->refuse('Choose who bears the fare difference (৳'.number_format($fare, 2).').');
        }
        if (($fareBy === 'customer' || $fee > 0 || $price !== null) && ! $sale) {
            $this->refuse('The old ticket was not sold to anyone, so there is nobody to bill. Clear the agent price and fee, and choose "We bear it".');
        }

        // 1. The new ticket — Ticket Entry's own store(), nothing parallel.
        $withAirline = TicketLeg::hasAirline();   // each sector's airline carries over too
        $legs = $old->legs()->orderBy('id')->get(array_merge(['from_location', 'to_location'], $withAirline ? ['airline_id'] : []))
            ->map(fn ($l) => ['from_location' => $l->from_location, 'to_location' => $l->to_location]
                + ($withAirline ? ['airline_id' => $l->airline_id] : []))->all();
        $row = [
            'passport_holder_id' => $old->passport_holder_id,
            'ticket_id' => $old->ticket_id,
            'vendor_id' => $old->vendor_id ?: null,
            'portal_id' => $old->vendor_id ? null : $old->portal_id,
            'ticket_type' => $old->ticket_type ?: 'air',
            'trip_type' => $old->trip_type,
            'ticket_class' => $old->ticket_class,
            'ticket_no' => $pnr,
            'cost_amount' => $newCost,
            'purchase_paid' => 0,
            'purchase_pay_status' => 'due',
            'departure_at' => $newAt,
            'status' => 'purchased',
            'notes' => 'New ticket after refund of '.($old->ticket_no ?: '#'.$old->id).(! empty($v['note']) ? ' · '.$v['note'] : ''),
            'legs' => $legs,
        ];
        if ($price !== null) {
            $row['sale_client_id'] = $sale->client_id;
            $row['sale_price'] = $price;
        }
        $entry = Request::create($request->url(), 'POST', ['currency' => $old->currency ?: 'BDT', 'passengers' => [$row]]);
        $entry->setUserResolver(fn () => $request->user());
        $res = app(TicketDirectSaleController::class)->store($entry, $role)->getData(true);
        $newId = $res['ticket_purchases'][0]['id'] ?? null;
        if (empty($res['success']) || ! $newId) {
            $this->refuse('The new ticket was not saved: '.preg_replace('/^Failed: /', '', (string) ($res['message'] ?? 'Ticket Entry refused it.')));
        }
        $new = TicketPurchase::findOrFail($newId);
        $new->moveTravelDate($newAt);  // leg 1 and departure_at together
        $newPnr = $new->ticket_no ?: $pnr;
        $c = $this->postReplacement($old, $new, $fareBy, $fee, $sale ? (int) $sale->client_id : null, $v['token'], $v['note'] ?? null, $price);
        $charged = (float) $c->charged;

        return ['change_id' => $c->id, 'message' => 'New ticket '.$newPnr.' saved for '.$this->day($new->travelAt())
            .($price !== null ? ', sold to '.$sale->client_name.' at ৳'.number_format($price, 2) : '')
            .($fare > 0 ? ' · fare difference ৳'.number_format($fare, 2).($fareBy === 'customer' ? ' billed' : ' borne') : '')
            .($charged > 0 ? ' · ৳'.number_format($charged, 2).' charged to '.$sale->client_name : '').'.'];
    }

    /**
     * Ticket Entry's side of refund → new ticket (owner, 2026-10-07: "after
     * refund, if we entry like a new ticket, the system detects the elder
     * refund, and the fare difference appears, and who is bearing + charges").
     * Called from TicketDirectSaleController::store() inside its transaction,
     * after the new purchase (and its sale) are saved. Throws
     * TicketChangeRefused — store() rolls the whole entry back on it.
     */
    public function settleFromEntry(int $oldId, TicketPurchase $new, ?string $fareBy, float $fee, ?int $buyerId, ?string $note): TicketChange
    {
        if (! TicketChange::available()) {
            $this->refuse('Refund → new ticket is not installed on this server yet.');
        }
        $old = TicketPurchase::whereKey($oldId)->lockForUpdate()->first();
        if (! $old || $old->status !== 'cancelled') {
            $this->refuse('The refunded ticket chosen on this card is no longer refunded.');
        }
        if ((int) $old->passport_holder_id !== (int) $new->passport_holder_id) {
            $this->refuse('The refunded ticket chosen on this card belongs to another passenger.');
        }
        if ($this->wasContractFlight($old)) {
            $this->refuse('That refund was a contract flight passenger\'s ticket — his new ticket is made with Re-assign on Flights & Passengers.');
        }
        if (TicketChange::where('ticket_purchase_id', $old->id)->where('kind', 'newticket')->exists()) {
            $this->refuse('A new ticket has already been made for refund '.($old->ticket_no ?: '#'.$old->id).'.');
        }
        $fare = round(max(0, (float) $new->amount - (float) $old->amount), 2);
        $fareBy = $fare > 0 ? $fareBy : null;
        if ($fare > 0 && ! in_array($fareBy, array_keys(TicketChange::BEARERS), true)) {
            $this->refuse('Choose who bears the fare difference (৳'.number_format($fare, 2).').');
        }
        // bill whoever this ticket is sold to; else whoever the refunded one was
        $buyerId = $buyerId ?: ($this->saleFor($old)?->client_id ? (int) $this->saleFor($old)->client_id : null);
        if (($fareBy === 'customer' || $fee > 0) && ! $buyerId) {
            $this->refuse('Nobody to bill the fare difference / fee to — choose "Sold to", or "We bear it".');
        }

        return $this->postReplacement($old, $new, $fareBy, round($fee, 2), $buyerId, 'E'.$new->id.'-'.bin2hex(random_bytes(6)), $note, null);
    }

    /**
     * Records refund → new ticket on the OLD ticket and posts the fare
     * difference (new cost − old cost) and our fee:
     *   always      Dr 5172 Fare Difference (Borne) / Cr 5110 — Company Ledger
     *   agent pays  ONE line: fare difference + our fee · Dr 1311 / Cr 4182 / Cr 4183
     */
    private function postReplacement(TicketPurchase $old, TicketPurchase $new, ?string $fareBy, float $fee, ?int $clientId, string $token, ?string $note, ?float $price): TicketChange
    {
        $newCost = round((float) $new->amount, 2);
        $oldCost = round((float) $old->amount, 2);
        $fare = round(max(0, $newCost - $oldCost), 2);
        $newPnr = $new->ticket_no ?: ('#'.$new->id);
        $oldPnr = $old->ticket_no ?: ('#'.$old->id);
        $name = $old->passportHolder?->name ?: 'passenger';

        $c = $this->record($old, ['kind' => 'newticket', 'token' => $token, 'service_fee' => $fee], [
            'fare_difference' => $fare,
            'bearer' => $fareBy,
            'client_id' => $clientId,
            'new_travel_at' => $new->travelAt(),
            'note' => trim('New ticket '.$newPnr.' · '.$this->day($new->travelAt()).' · cost ৳'.number_format($newCost, 2).' (old ৳'.number_format($oldCost, 2).')'
                .($fare > 0 ? ' · fare difference ৳'.number_format($fare, 2).' ('.($fareBy === 'customer' ? 'customer pays' : 'we bear').')' : '')
                .($price !== null ? ' · agent price ৳'.number_format($price, 2) : '')
                .($fee > 0 ? ' · service fee ৳'.number_format($fee, 2) : '').' · '.($note ?? ''), ' ·'),
        ]);

        if ($fare > 0) {
            $this->postCompanyReclass($c, $new, $fare, 'cf_fare_diff_company', 'ticket_purchase_cost',
                'Fare difference — new ticket '.$newPnr.' vs '.$oldPnr.' · '.$name.' · '.($fareBy === 'customer' ? 'agent pays — recovered' : 'we bear'));
        }
        $fareHis = $fareBy === 'customer' ? $fare : 0.0;
        $charged = 0.0;
        if ($fareHis + $fee > 0) {
            $charged = round($fareHis + $fee, 2);
            $this->postCustomer($c, $new, (int) $clientId, TicketChange::CHARGE_TYPE, $charged, 0,
                'New ticket charge — '.$newPnr.' (was '.$oldPnr.') · '.$name,   // one figure, no split (owner, 2026-10-13)
                array_values(array_filter([
                    $fareHis > 0 ? ['cf_fare_diff_recovered', $fareHis, 'Fare difference recovered — '.$newPnr] : null,
                    $fee > 0 ? ['cf_change_recovered', $fee, 'Our charge (profit) — '.$newPnr] : null,
                ])));
        }
        $c->update(['charged' => $charged]);

        return $c;
    }

    // ── Cancel return (owner, 2026-10-14, App\Services\ReturnCancel) ────────

    /**
     * The window and the Family tick: "the return can be cancelled until …".
     * No money moves; the reminders (tickets:return-cancel-reminders) read it.
     */
    public function returnWindow(Request $request, string $role, int $ticketPurchase)
    {
        if (! ReturnCancel::ready()) {
            return response()->json(['success' => false, 'message' => 'Cancel return is not installed on this server yet.'], 422);
        }
        $v = $request->validate([
            'cancel_by' => ['nullable', 'date'],
            'family' => ['nullable', 'boolean'],
        ]);
        $t = TicketPurchase::findOrFail($ticketPurchase);
        $pax = $this->passengerFor($t);
        ReturnCancel::setWindow($t, $v['cancel_by'] ?? null, array_key_exists('family', $v) ? (bool) $v['family'] : null);
        $state = ReturnCancel::state($t->fresh(), $pax);

        return response()->json(['success' => true, 'message' => $state['cancel_by'] ? 'Window saved — reminders 3 days and 1 day before, and on the day.' : 'Window cleared.', 'ret' => $state]);
    }

    /** What the agent pays for this ticket now: its sale price less what was already taken back. */
    private function agentTotalFor(TicketPurchase $t, ?object $sale): float
    {
        if (! $sale) {
            return 0.0;
        }
        $back = (float) TicketChange::where('ticket_purchase_id', $t->id)->sum('sale_reversed');

        return round(max(0.0, (float) $sale->price - $back), 2);
    }

    /** The agent's total: kept, or lowered to a typed total / by a typed amount. Returns the reduction. */
    private function agentReduction(array $v, float $current): float
    {
        $value = round((float) ($v['agent_value'] ?? 0), 2);
        $reduce = match ($v['agent_mode'] ?? 'keep') {
            'total' => round($current - $value, 2),
            'giveback' => $value,
            default => 0.0,
        };
        if (($v['agent_mode'] ?? 'keep') !== 'keep' && ($reduce <= 0 || $reduce > $current)) {
            $this->refuse(($v['agent_mode'] ?? '') === 'total'
                ? 'The new total must be less than the agent\'s current total (৳'.number_format($current, 2).').'
                : 'The amount to give back must be more than 0 and at most the agent\'s current total (৳'.number_format($current, 2).').');
        }

        return max(0.0, $reduce);
    }

    /**
     * Cancel return on a Ticketing-only ticket. Every row ADDED, none edited:
     *   round trip     the return half's refund beside the live ticket
     *                  (TicketPurchaseController::refundReturnPart)
     *   return ticket  Ticketing's own cancel, exactly as Refund
     *   penalty        Dr 5171 / Cr 5110 (Company Ledger, Borne) — the agent's
     *                  share billed on his ONE line with our fee (Dr 1311 /
     *                  Cr 4181 / Cr 4183)
     *   agent total    kept, or lowered: agent row Cr · Dr 4110 / Cr 1311
     *                  (SALE_REVERSAL_TYPE, as a void's partial reversal)
     */
    private function returnCancelOnTicketing(TicketPurchase $t, array $v): array
    {
        $t = TicketPurchase::whereKey($t->id)->lockForUpdate()->first();
        $state = $t ? ReturnCancel::state($t) : null;
        if (! $state) {
            $this->refuse('Cancel return is not installed on this server yet.');
        }
        if (! $state['family']) {
            $this->refuse('Cancel return is for family tickets only. Tick "Family" on its window first.');
        }
        if (! $state['shape']) {
            $this->refuse('This ticket has no return part to cancel.');
        }
        if ($state['done'] || $t->status !== 'confirm') {
            $this->refuse('The return on this ticket is already cancelled.');
        }
        $round = $state['shape'] === 'round_trip';
        $amount = round((float) $t->amount, 2);
        $pnr = $t->ticket_no ?: ('#'.$t->id);
        $name = $t->passportHolder?->name ?: 'passenger';
        $sale = $this->saleFor($t);
        $fee = round((float) ($v['service_fee'] ?? 0), 2);
        if (! isset($v['refund'])) {
            $this->refuse('Enter what the airline refunds for the return.');
        }
        $refund = round(min((float) $v['refund'], $amount), 2);
        $penalty = $round ? round((float) ($v['penalty'] ?? 0), 2) : round($amount - $refund, 2);
        if ($round && $refund + $penalty > $amount + 0.005) {
            $this->refuse('The refund and the penalty together (৳'.number_format($refund + $penalty, 2).') are more than the ticket (৳'.number_format($amount, 2).').');
        }
        $keptBy = $penalty > 0 ? ($v['penalty_bearer'] ?? null) : null;
        if ($penalty > 0 && ! $keptBy) {
            $this->refuse('Choose who bears the airline penalty (৳'.number_format($penalty, 2).').');
        }
        if (($keptBy === 'customer' || $fee > 0) && ! $sale) {
            $this->refuse('This ticket is not sold to anyone, so there is nobody to bill. Choose "We bear it" and clear our charge.');
        }
        $reduce = $this->agentReduction($v, $this->agentTotalFor($t, $sale));

        $c = $this->record($t, $v, [
            'refund_amount' => $refund,
            'penalty' => $penalty,
            'bearer' => $keptBy,
            'client_id' => $sale?->client_id,
            'note' => trim('Return cancelled'.($round ? ' (return half of the round trip)' : '').' — airline refund ৳'.number_format($refund, 2)
                .($penalty > 0 ? ', kept ৳'.number_format($penalty, 2).' ('.($keptBy === 'customer' ? 'customer pays' : 'we bear').')' : '')
                .($fee > 0 ? ', service fee ৳'.number_format($fee, 2) : '')
                .($sale ? ($reduce > 0 ? ', agent total lowered ৳'.number_format($reduce, 2) : ', agent total kept') : '')
                .' · '.($v['note'] ?? ''), ' ·'),
        ]);

        // 1. The airline's refund for the return.
        $who = $name.(! empty($v['note']) ? ' · '.$v['note'] : '').' · '.$this->clock()->format('d M Y');
        if ($round) {
            app(TicketPurchaseController::class)->refundReturnPart($t, $refund, $who, $this->clock?->copy(), $penalty);
        } else {
            app(TicketPurchaseController::class)->cancelFromContractFlight($t, 'Return cancelled — '.$who, $refund, $this->clock?->copy());
            TicketPurchase::whereKey($t->id)->update(['return_cancelled_at' => $this->clock()]);
        }
        // 2. What the airline kept: out of ticket cost onto the Company Ledger.
        if ($penalty > 0) {
            $this->postCompanyReclass($c, $t, $penalty, 'cf_penalty_company', 'ticket_purchase_cost',
                'Airline penalty kept (return) — '.$pnr.' · '.$name.' · '.($keptBy === 'customer' ? 'agent pays — recovered' : 'we bear'));
        }
        // 3. The agent's total lowered, when chosen.
        if ($reduce > 0) {
            $this->postCustomer($c, $t, (int) $sale->client_id, TicketChange::SALE_REVERSAL_TYPE, 0, $reduce,
                'Return cancelled — price revised · '.$pnr.' · '.$name.($sale->invoice_no ? ' ('.$sale->invoice_no.')' : ''),
                [['ticket_sales_revenue', $reduce, 'Return cancelled — price revised · '.$pnr]]);
        }
        // 4. ONE line on his statement: the penalty he bears + our fee.
        $penaltyHis = $keptBy === 'customer' ? $penalty : 0.0;
        $charged = 0.0;
        if ($penaltyHis + $fee > 0) {
            $charged = round($penaltyHis + $fee, 2);
            $this->postCustomer($c, $t, (int) $sale->client_id, TicketChange::CHARGE_TYPE, $charged, 0,
                'Return cancellation penalty — '.$pnr.' · '.$name,
                array_values(array_filter([
                    $penaltyHis > 0 ? ['cf_penalty_recovered', $penaltyHis, 'Airline penalty recovered — '.$pnr] : null,
                    $fee > 0 ? ['cf_change_recovered', $fee, 'Our charge (profit) — '.$pnr] : null,
                ])));
        }
        $c->update(['sale_reversed' => $reduce, 'charged' => $charged]);

        return ['change_id' => $c->id, 'message' => 'Return cancelled and posted: refund ৳'.number_format($refund, 2)
            .($reduce > 0 ? ', agent total lowered ৳'.number_format($reduce, 2) : '')
            .($charged > 0 ? ', ৳'.number_format($charged, 2).' charged to '.$sale->client_name : '').'.'];
    }

    /** A contract-flight passenger's ticket: the flight's own cancelReturn does it all. */
    private function returnCancelThroughFlight(Request $request, string $role, TicketPurchase $t, ContractFlightPassenger $pax, array $v): array
    {
        if (! $request->user()?->can('edit contract flight')) {
            $this->refuse('This ticket belongs to a contract flight passenger — cancelling its return needs the "edit contract flight" permission.');
        }
        $before = (int) ContractFlightPassengerChange::where('contract_flight_passenger_id', $pax->id)->max('id');
        $payload = [
            'ticket_purchase_id' => $t->id,
            'refund' => $v['refund'] ?? null,
            'penalty' => $v['penalty'] ?? null,
            'bearer' => ($v['penalty_bearer'] ?? null) === 'customer' ? 'agent' : (($v['penalty_bearer'] ?? null) ? 'company' : null),
            'fee' => $v['service_fee'] ?? null,
            'agent_mode' => $v['agent_mode'] ?? 'keep',
            'agent_value' => $v['agent_value'] ?? null,
            'note' => $v['note'] ?? null,
        ];

        return DB::transaction(function () use ($request, $role, $t, $pax, $v, $payload, $before) {
            $sub = Request::create($request->url(), 'POST', $payload);
            $sub->setUserResolver(fn () => $request->user());
            $sub->headers->set('Accept', 'application/json');
            $p = ContractFlightPassenger::with(['contractFlight', 'passportHolder'])->findOrFail($pax->id);
            try {
                $data = app(ContractFlightPassengerController::class)->cancelReturn($sub, $role, $p)->getData(true);
            } catch (ValidationException $e) {
                $this->refuse(collect($e->errors())->flatten()->first() ?: 'Check the figures.');
            }
            if (empty($data['success'])) {
                $this->refuse($data['message'] ?? 'The contract flight refused this.');
            }
            $t->refresh();
            $change = ContractFlightPassengerChange::where('contract_flight_passenger_id', $pax->id)->where('id', '>', $before)->orderBy('id')->first();
            $c = $this->record($t, $v, [
                'source' => 'contract_flight',
                'contract_flight_passenger_id' => $pax->id,
                'contract_flight_passenger_change_id' => $change?->id,
                'refund_amount' => (float) ($payload['refund'] ?? 0),
                'penalty' => (float) ($change?->airline_penalty ?? 0),
                'bearer' => $v['penalty_bearer'] ?? null,
                'note' => trim('Return cancelled through '.($p->contractFlight?->flight_number ?: 'the contract flight')
                    .($change ? ' (change #'.$change->id.')' : '').' · '.($v['note'] ?? ''), ' ·'),
            ]);

            return ['message' => $data['message'] ?? 'Return cancelled and posted.', 'change_id' => $c->id];
        });
    }

    /** Ticket Entry: this passenger's refunded tickets still waiting for their new ticket. */
    public function refundedFor(Request $request, string $role, int $holder)
    {
        if (! TicketChange::available()) {
            return response()->json(['success' => true, 'tickets' => []]);
        }
        $rows = TicketPurchase::with(['ticket:id,title', 'vendor:id,name', 'portal:id,name'])
            ->where('passport_holder_id', $holder)->where('status', 'cancelled')
            ->whereNotExists(fn ($q) => $q->from('ticket_changes')->whereColumn('ticket_changes.ticket_purchase_id', 'ticket_purchases.id')->where('kind', 'newticket'))
            ->latest('id')->limit(10)->get()
            ->reject(fn (TicketPurchase $t) => $this->wasContractFlight($t))
            ->map(function (TicketPurchase $t) {
                $sale = $this->saleFor($t);

                return [
                    'id' => $t->id, 'pnr' => $t->ticket_no ?: ('#'.$t->id), 'route' => $t->ticket?->title,
                    'amount' => (float) $t->amount, 'refund' => $t->refund_amount !== null ? (float) $t->refund_amount : null,
                    'travel_at' => $t->travelAt(), 'seller' => $t->vendor?->name ?: $t->portal?->name,
                    'client_id' => $sale ? (int) $sale->client_id : null, 'client' => $sale?->client_name, 'price' => $sale ? (float) $sale->price : null,
                ];
            })->values();

        return response()->json(['success' => true, 'tickets' => $rows]);
    }

    /** Was this (now cancelled) ticket a contract-flight passenger's? A cancel unlinks it, so the history is asked too. */
    private function wasContractFlight(TicketPurchase $t): bool
    {
        try {
            if (TicketChange::where('ticket_purchase_id', $t->id)->whereNotNull('contract_flight_passenger_id')->exists()
                || DB::table('contract_flight_passenger_tickets')->where('ticket_purchase_id', $t->id)->exists()) {
                return true;
            }

            return Schema::hasColumn('contract_flight_passenger_changes', 'previous_ticket_purchase_ids')
                && DB::table('contract_flight_passenger_changes')->whereJsonContains('previous_ticket_purchase_ids', (int) $t->id)->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    /** customer / company / mixed over the parts that carry money; null when none does. */
    private function bearerOf(array $parts): ?string
    {
        $sides = collect($parts)->filter(fn ($p) => (float) $p[0] > 0 && $p[1])->pluck(1)->unique()->values();

        return $sides->count() > 1 ? 'mixed' : $sides->first();
    }

    /**
     * Seller's side of a re-issue / reschedule (owner, 2026-10-06): one seller
     * row + one journal PER PART — the airline's charge and the fare
     * difference are two entries on his statement. The cost lands on the
     * Company Ledger like the contract flight's (Dr 5173 Rebooking & Change
     * Fees / Dr 5172 Fare Difference — Borne), Cr AP; a portal ticket is paid
     * from the wallet (wallet Cr, Cr the portal's account).
     * $parts = [[why, amount, row type, Dr account config key], ...].
     */
    private function postSellerFee(TicketChange $c, TicketPurchase $t, array $parts): void
    {
        $parts = array_values(array_filter($parts, fn ($x) => round((float) $x[1], 2) >= 0.005));
        if (! $parts) {
            return;
        }
        $cr = Account::where('code', config('accounts.accounts_payable'))->value('id');
        $vendorId = $t->vendor_id ? (int) $t->vendor_id : null;
        $portal = null;
        if (! $vendorId) {
            $portal = Portal::whereKey($t->portal_id)->lockForUpdate()->first();
            if (! $portal || ! $portal->account_id) {
                $this->refuse('The ticket\'s portal is not linked to an account, so its fee cannot be posted.');
            }
            $cr = $portal->account_id;
        }
        $pnr = (string) ($t->ticket_no ?: ('#'.$t->id));
        foreach ($parts as [$why, $amount, $rowType, $drKey]) {
            $amount = round((float) $amount, 2);
            $dr = Account::where('code', config('accounts.'.$drKey))->value('id');
            if (! $dr || ! $cr) {
                $this->refuse('Account heads missing ('.config('accounts.'.$drKey).' / '.config('accounts.accounts_payable').') — nothing was posted.');
            }
            if ($vendorId) {
                $this->postPartyLedgerRow($vendorId, [
                    'type' => $rowType, 'invoice_id' => $c->id, 'account_id' => null,
                    'payment_date' => $this->clock(), 'reference_no' => $pnr, 'debit' => $amount, 'credit' => 0,
                    'remarks' => mb_substr($why, 0, 250),
                ]);
            } else {
                $portal->refresh();
                $opening = (float) $portal->balance;
                PortalBalance::create([
                    'invoice_id' => $c->id, 'portal_id' => $portal->id, 'type' => $rowType,
                    'old_balance' => $opening, 'debit' => 0, 'credit' => $amount, 'current_balance' => $opening - $amount,
                    'remarks' => mb_substr($why, 0, 250), 'created_by' => Auth::id(),
                ]);
                $portal->update(['balance' => $opening - $amount]);
            }
            $entry = JournalEntry::create([
                'company_id' => TravelCompany::ID,
                'created_by' => Auth::id(),
                'date' => $this->clock()->toDateString(),
                'entry_time' => $this->clock()->format('H:i:s'),
                'reference' => mb_substr($pnr.'-T'.$c->id, 0, 190),
                'source' => TicketChange::FEE_TYPE,
                'source_id' => $c->id,
                'description' => mb_substr($why, 0, 250),
                'beneficiary' => $vendorId ? User::whereKey($vendorId)->value('name') : null,
                'beneficiary_party_id' => $vendorId,
            ]);
            JournalItem::create(['journal_entry_id' => $entry->id, 'account_id' => $dr, 'debit' => $amount, 'credit' => 0, 'note' => mb_substr($why, 0, 250)]);
            JournalItem::create(['journal_entry_id' => $entry->id, 'account_id' => $cr, 'debit' => 0, 'credit' => $amount, 'note' => mb_substr($why, 0, 250),
                'party_type' => $vendorId ? 'vendor' : null, 'party_id' => $vendorId]);
        }
    }

    /** A cost moved between heads, no party: Dr $drKey / Cr $crKey (account config keys). */
    private function postCompanyReclass(TicketChange $c, TicketPurchase $t, float $amount, string $drKey, string $crKey, string $why): void
    {
        $dr = Account::where('code', config('accounts.'.$drKey))->value('id');
        $cr = Account::where('code', config('accounts.'.$crKey))->value('id');
        if (! $dr || ! $cr) {
            $this->refuse('Account heads missing ('.config('accounts.'.$drKey).' / '.config('accounts.'.$crKey).') — nothing was posted.');
        }
        $pnr = (string) ($t->ticket_no ?: ('#'.$t->id));
        $entry = JournalEntry::create([
            'company_id' => TravelCompany::ID,
            'created_by' => Auth::id(),
            'date' => $this->clock()->toDateString(),
            'entry_time' => $this->clock()->format('H:i:s'),
            'reference' => mb_substr($pnr.'-T'.$c->id, 0, 190),
            'source' => TicketChange::PENALTY_SOURCE,
            'source_id' => $c->id,
            'description' => mb_substr($why, 0, 250),
        ]);
        JournalItem::create(['journal_entry_id' => $entry->id, 'account_id' => $dr, 'debit' => round($amount, 2), 'credit' => 0, 'note' => mb_substr($why, 0, 250)]);
        JournalItem::create(['journal_entry_id' => $entry->id, 'account_id' => $cr, 'debit' => 0, 'credit' => round($amount, 2), 'note' => mb_substr($why, 0, 250)]);
    }

    /**
     * The customer's side: one ledger row and its journal. $debit > 0 bills
     * him (Dr 1311 / Cr each part's head — 4181 / 4182 / 4183 recovered on the
     * Company Ledger); $credit > 0 takes his agent price back (Dr 4110 / Cr
     * 1311). $parts = [[account config key, amount, note], ...] and always
     * sums to the row's amount, so the entry balances.
     */
    private function postCustomer(TicketChange $c, TicketPurchase $t, int $clientId, string $type, float $debit, float $credit, string $why, array $parts, ?string $effectiveDate = null, ?string $effectiveTime = null): void
    {
        $ar = Account::where('code', config('accounts.accounts_receivable'))->value('id');
        $heads = [];
        foreach ($parts as [$key]) {
            $heads[$key] = Account::where('code', config('accounts.'.$key))->value('id');
            if (! $heads[$key]) {
                $this->refuse('Account head missing ('.config('accounts.'.$key).') — nothing was posted.');
            }
        }
        if (! $ar) {
            $this->refuse('Account head missing ('.config('accounts.accounts_receivable').') — nothing was posted.');
        }
        $client = User::find($clientId);
        $pnr = (string) ($t->ticket_no ?: ('#'.$t->id));
        $effectiveDate = $effectiveDate ?: $this->clock()->toDateString();
        $this->postPartyLedgerRow($clientId, [
            'type' => $type, 'invoice_id' => $c->id, 'account_id' => null, 'payment_date' => $effectiveDate,
            'transaction_time' => $effectiveTime,
            'reference_no' => $pnr, 'debit' => $debit, 'credit' => $credit, 'remarks' => mb_substr($why, 0, 250),
        ]);
        $entry = JournalEntry::create([
            'company_id' => TravelCompany::ID,
            'created_by' => Auth::id(),
            'date' => $effectiveDate,
            'entry_time' => $effectiveTime ?: $this->clock()->format('H:i:s'),
            'reference' => mb_substr($pnr.'-T'.$c->id, 0, 190),
            'source' => $type,
            'source_id' => $c->id,
            'description' => mb_substr($why, 0, 250),
            'beneficiary' => $client?->name,
            'beneficiary_party_id' => $clientId,
        ]);
        $partyType = $this->resolvePartyType($client);
        $total = round($debit + $credit, 2);
        $billing = $debit > 0;
        JournalItem::create(['journal_entry_id' => $entry->id, 'account_id' => $ar,
            'debit' => $billing ? $total : 0, 'credit' => $billing ? 0 : $total,
            'note' => mb_substr($why, 0, 250), 'party_type' => $partyType, 'party_id' => $clientId]);
        foreach ($parts as [$key, $amt, $note]) {
            JournalItem::create(['journal_entry_id' => $entry->id, 'account_id' => $heads[$key],
                'debit' => $billing ? 0 : $amt, 'credit' => $billing ? $amt : 0, 'note' => mb_substr($note, 0, 250)]);
        }
    }

    /** Post only the difference between an old and corrected customer amount. */
    private function postCustomerDelta(TicketChange $c, TicketPurchase $t, int $clientId, string $type, float $delta, string $accountKey, string $why, bool $positiveIsCredit = false, ?string $effectiveDate = null, ?string $effectiveTime = null): void
    {
        $delta = round($delta, 2);
        if (abs($delta) < 0.005) {
            return;
        }
        $amount = abs($delta);
        $credit = ($delta > 0) === $positiveIsCredit;
        $this->postCustomer(
            $c,
            $t,
            $clientId,
            $type,
            $credit ? 0 : $amount,
            $credit ? $amount : 0,
            $why,
            [[$accountKey, $amount, $why]],
            $effectiveDate,
            $effectiveTime
        );
    }

    /** Keep a correction beside its original row, including when it is back-dated. */
    private function postingTime(?Transaction $posting): ?string
    {
        if (! $posting) {
            return null;
        }

        return $posting->transaction_time
            ?: $posting->created_at?->format('H:i:s');
    }

    // ── Ticket Trail replay (2026-10-26) ────────────────────────────────────

    /**
     * Post one change again on $at's day, through the same code Change uses —
     * called only by the Ticket Trail after it removed the ticket's change
     * postings, inside its transaction. Refuses (TicketChangeRefused) exactly
     * where Change would. Returns the change it recorded.
     */
    public function replay(TicketPurchase $t, array $v, Carbon $at): TicketChange
    {
        $this->clock = $at->copy();
        try {
            $v['replay'] = true;
            $res = $v['kind'] === 'return_cancel' ? $this->returnCancelOnTicketing($t, $v) : $this->onTicketing($t, $v);

            return TicketChange::findOrFail($res['change_id']);
        } finally {
            $this->clock = null;
        }
    }

    /** Refund → new ticket's fare difference and fee, posted again for an existing new ticket (Trail replay). */
    public function replayNewTicket(TicketPurchase $old, TicketPurchase $new, ?string $fareBy, float $fee, ?int $clientId, string $token, ?string $note, Carbon $at): TicketChange
    {
        $this->clock = $at->copy();
        try {
            return $this->postReplacement($old, $new, $fareBy, round($fee, 2), $clientId, $token, $note, null);
        } finally {
            $this->clock = null;
        }
    }

    /** The ticket's whole history for the dialog's History box, newest first; empty if it cannot be read. */
    private function timelineFor(TicketPurchase $t): array
    {
        try {
            return array_reverse(Timeline::of($t));
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    /** Today — or the replayed change's day. */
    private function clock(): Carbon
    {
        return $this->clock ? $this->clock->copy() : now();
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    private function record(TicketPurchase $t, array $v, array $attrs): TicketChange
    {
        return TicketChange::create(array_merge([
            'company_id' => $t->company_id,
            'ticket_purchase_id' => $t->id,
            'kind' => $v['kind'],
            'source' => 'ticketing',
            'service_fee' => round((float) ($v['service_fee'] ?? 0), 2),
            'token' => $v['token'],
            'created_by' => Auth::id(),
        ], $attrs));
    }

    /** The contract-flight passenger this ticket is linked to, if any. */
    private function passengerFor(TicketPurchase $t): ?ContractFlightPassenger
    {
        try {
            $id = DB::table('contract_flight_passenger_tickets')->where('ticket_purchase_id', $t->id)->value('contract_flight_passenger_id');

            return $id ? ContractFlightPassenger::with(['contractFlight', 'passportHolder', 'agent:id,name'])->find($id) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** The live sale this ticket is on: buyer and the price billed for THIS ticket. */
    private function saleFor(TicketPurchase $t): ?object
    {
        return DB::table('ticket_sale_items as i')
            ->join('ticket_sales as s', 's.id', '=', 'i.ticket_sale_id')
            ->leftJoin('users as u', 'u.id', '=', 's.client_id')
            ->where('i.ticket_purchase_id', $t->id)->whereNull('s.deleted_at')->where('s.status', '!=', 'cancelled')
            ->whereNotNull('s.client_id')
            ->orderByDesc('i.id')
            ->first(['s.id', 's.invoice_no', 's.client_id', 'u.name as client_name', 'i.price']);
    }

    /** Flights & Passengers searched to this passport — his row, with Re-assign on it. */
    private function flightsUrl(string $role, TicketPurchase $t): string
    {
        $t->loadMissing('passportHolder:id,passport_no,name');

        return route('role.contract-flights.index', ['role' => $role, 'search' => $t->passportHolder?->passport_no ?: $t->passportHolder?->name ?: $t->ticket_no]);
    }

    private function leg1Notice(ContractFlightPassenger $pax, TicketPurchase $t, string $newAt, string $role): ?string
    {
        $flight = $pax->contractFlight;
        if (! $flight?->departure_at) {
            return null;
        }
        $slots = $pax->fresh(['contractFlight.ticket', 'ticketPurchases.legs'])->ticketSlots();
        if ((int) ($slots[0]['purchase_id'] ?? 0) !== (int) $t->id || substr($newAt, 0, 10) === $flight->departure_at->format('Y-m-d')) {
            return null;
        }

        return 'Leg 1 ('.e($t->ticket_no).') now flies on <b>'.e($this->day($newAt)).'</b>, but he is still on <b>'.e($flight->flight_number)
            .'</b> ('.e($flight->departure_at->format('d M Y')).'). If his flight has changed, use <b>Re-assign</b> on his row in <a href="'
            .e($this->flightsUrl($role, $t)).'" style="color:#4f46e5;font-weight:700;">Flights &amp; Passengers</a>.';
    }

    private function day(?string $at): string
    {
        if (! $at) {
            return '—';
        }
        $c = Carbon::parse($at);

        return $c->format('d M Y').($c->format('H:i') !== '00:00' ? ', '.$c->format('H:i') : '');
    }

    private function refuse(string $message): never
    {
        throw new TicketChangeRefused($message);
    }
}
