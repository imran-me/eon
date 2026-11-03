<?php

namespace App\Http\Controllers\Dashboard;

use App\Exceptions\StalePassengerException;
use App\Models\Account;
use App\Models\ContractFlight;
use App\Models\ContractFlightPassenger;
use App\Models\ContractFlightPassengerChange;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\PassportHolder;
use App\Models\Portal;
use App\Models\PortalBalance;
use App\Models\TicketBooking;
use App\Models\TicketChange;
use App\Models\TicketPurchase;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ContractFlightAgentReceipts;
use App\Services\ContractFlightMessenger;
use App\Services\ReturnCancel;
use App\Services\TicketBaggage;
use App\Support\TravelCompany;
use App\Traits\PostsPartyLedger;
use App\Traits\PostsPurchaseJournal;
use App\Traits\PostsSaleJournal;
use App\Traits\RebooksContractFlightPassengers;
use App\Traits\ReconcilesContractFlightAgent;
use App\Traits\ReconcilesContractFlightChangeCharge;
use App\Traits\ReconcilesContractFlightReissue;
use App\Traits\ReconcilesContractFlightServiceCosts;
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
 * Per-passenger pipeline, document and money fields on a Contract Flight —
 * split out from ManageFlightController because these are edited one
 * passenger at a time (by Nayeem/Fahim during data entry) rather than as
 * part of saving the whole flight batch.
 */
class ContractFlightPassengerController implements HasMiddleware
{
    use PostsPartyLedger, PostsPurchaseJournal, PostsSaleJournal;
    use RebooksContractFlightPassengers, ReconcilesContractFlightChangeCharge;
    use ReconcilesContractFlightAgent, ReconcilesContractFlightReissue, ReconcilesContractFlightServiceCosts;

    /**
     * Flags that cost money (2026-09-28). Raising one from the edit modal
     * arrives with money_event: cancel his ticket or not, what the airline
     * refunds per ticket, and whose side caused it. Same postings as Cancel
     * from flight — tickets through Ticketing, penalty to the Company Ledger
     * or billed to the agent — recorded as a 'flagged' change row.
     */
    private const MONEY_FLAGS = ['flight_cancelled', 'flight_missed', 'boarding_denied', 'immigration_offload', 'deported', ContractFlightPassenger::DEPORTED_BORNE_FLAG];

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view contract flight|view all contract flight', only: ['index', 'board', 'searchFlights', 'lanes']),
            new Middleware('permission:edit contract flight', only: ['update', 'linkTicket', 'unlinkTicket', 'updateStage', 'moveFlight', 'ticketAdjustment', 'laneNewTicket', 'correctRefund', 'undoChange', 'cancelReturn']),
            // holderTicketPurchases only reads a passport holder's own PNRs to
            // fill the Add Flight modal's picker, so it rides with the create
            // permission the modal itself already requires.
            new Middleware('permission:create contract flight|edit contract flight', only: ['holderTicketPurchases', 'offFlightForHolder']),
        ];
    }

    /** Full passenger list for one flight, for the view-modal's passenger table. */
    public function index(Request $request, string $role, ContractFlight $contractFlight)
    {
        $passengers = $contractFlight->passengers()
            ->with(['passportHolder:id,name,passport_no,phone', 'agent:id,name', 'contractFlight:id,flight_number,ticket_id', 'flightCategory.categoryType', 'ticketPurchases.ticket', 'ticketPurchases.vendor', 'ticketPurchases.portal', 'ticketPurchases.bank', 'ticketPurchases.legs', 'ticketPurchases.schedules', 'serviceCosts.vendor:id,name', 'serviceCosts.bank:id,name', 'events.creator:id,name'])
            ->get()
            ->map(fn (ContractFlightPassenger $p) => $this->present($p));

        return response()->json(['success' => true, 'passengers' => $passengers]);
    }

    /**
     * Kanban board across every passenger, columned by pipeline stage — the
     * cross-flight view of "who's where" that the per-flight View modal can't
     * give, since that one only ever shows a single flight's passengers.
     * Modeled directly on VisaProcessingController::index()'s board (same
     * .kb-* markup/CSS, same drag-and-drop mechanics) per the owner's request
     * to copy that existing board rather than invent a new pattern.
     */
    public function board(Request $request)
    {
        $query = ContractFlightPassenger::with([
            'contractFlight.ticket.from_airport',
            'contractFlight.ticket.to_airport',
            'passportHolder:id,name,passport_no,phone',
            'agent:id,name',
            'ticketPurchases:id,ticket_no,status,amount',
        ]);

        if ($request->filled('agent_id')) {
            $query->where('agent_id', $request->agent_id);
        }
        if ($request->filled('contract_flight_id')) {
            $query->where('contract_flight_id', $request->contract_flight_id);
        }
        if ($request->filled('exception_flag')) {
            $query->where('exception_flag', $request->exception_flag);
        }

        $passengers = $query->get();
        $grouped = $passengers->groupBy('stage');

        $agents = User::orderBy('name')->role('agent')->get(['id', 'name']);
        $flights = ContractFlight::orderByDesc('id')->get(['id', 'flight_number']);

        return view('contract-flights.board', compact('passengers', 'grouped', 'agents', 'flights'));
    }

    /**
     * Slim, board-only stage change — a drag-and-drop move is a single field,
     * not the full passenger form update() demands (BMET/manpower/cost are
     * all required there). Sets stage directly, both directions: a board
     * move is a manual correction like the edit modal's dropdown, not the
     * one-way advance linkTicket()/contract-confirm trigger.
     */
    public function updateStage(Request $request, string $role, ContractFlightPassenger $contractFlightPassenger)
    {
        $validated = $request->validate([
            'stage' => ['required', Rule::in(array_keys(ContractFlightPassenger::stages()))],
        ]);

        $oldStage = $contractFlightPassenger->stage;
        $contractFlightPassenger->update(['stage' => $validated['stage']]);
        if ($oldStage !== $validated['stage']) {
            $this->recordEvent($contractFlightPassenger, 'stage', $validated['stage'], ContractFlightPassenger::stageLabel($validated['stage']));
            // Tell the passenger / agent, if the Messages rules say so. Never throws.
            ContractFlightMessenger::auto(ContractFlightMessenger::scenarioForStage($validated['stage']), $contractFlightPassenger);
        }

        return response()->json(['success' => true]);
    }

    /** The money side of a flag raised in the edit modal — see MONEY_FLAGS. */
    private function applyMoneyEvent(ContractFlightPassenger $p, array $event, ?string $oldFlag, ?string $note): void
    {
        $p->loadMissing(['contractFlight.creator', 'passportHolder']);
        $flight = $p->contractFlight;
        // Deported: whose side decides the package (owner, 2026-09-28) —
        // agent side = he repays, the charge stands and the case closes;
        // our side = we bear it, the package is reversed.
        if (in_array($p->exception_flag, ['deported', ContractFlightPassenger::DEPORTED_BORNE_FLAG], true) && ! empty($event['fault'])) {
            $p->update(['exception_flag' => $event['fault'] === 'company' ? ContractFlightPassenger::DEPORTED_BORNE_FLAG : 'deported']);
        }
        $label = ContractFlightPassenger::EXCEPTION_FLAGS[$p->exception_flag] ?? $p->exception_flag;
        $cancelled = ['ids' => [], 'labels' => [], 'penalty' => 0.0];
        if (! empty($event['cancel_ticket'])) {
            $cancelled = $this->cancelPassengerTickets($p,
                ($p->passportHolder?->name ?: 'passenger').' — '.$label.' on '.($flight?->flight_number ?: 'his flight'),
                null, $event['refunds'] ?? []);
        }
        $penalty = (float) ($cancelled['penalty'] ?? 0);
        $fault = $event['fault'] ?? null;
        if ($fault === 'agent' && ! $p->agent_id) {
            $fault = 'company';
        }
        if (! $cancelled['ids'] && ! $fault) {
            return; // nothing happened to money beyond the package reversal
        }
        $change = ContractFlightPassengerChange::create([
            'contract_flight_passenger_id' => $p->id,
            'kind' => 'flagged',
            'from_flight_id' => $flight?->id,
            'note' => trim($label.($note ? ' — '.$note : '')),
            'agent_id' => $p->agent_id,
            'previous_ticket_purchase_ids' => $cancelled['ids'] ?: null,
            'previous_flag' => $oldFlag,
            'created_by' => Auth::id(),
        ] + (ContractFlightPassengerChange::hasFaultColumns() ? [
            'fault' => $fault ?: ($penalty > 0 ? 'company' : null),
            'airline_penalty' => $penalty > 0 ? $penalty : null,
        ] : []));
        $this->reconcileChangeCharge($change);
        $this->postChangeCosts($change, $p, $flight);
        if ($cancelled['labels']) {
            $this->recordEvent($p, 'exception', 'ticket_cancelled', 'Ticket cancelled',
                implode(', ', $cancelled['labels']).($penalty > 0 ? ' · airline penalty ৳'.number_format($penalty, 2).' — '.($change->fault === 'agent' ? 'billed to the agent' : 'borne by us') : ' · full refund'));
        }
        if ($cancelled['ids'] && ($back = $p->stepBackIfUnticketed())) {
            $this->recordEvent($p, 'stage', $back, ContractFlightPassenger::stageLabel($back), 'Ticket cancelled — issue a new one');
        }
        $flight?->syncMoneyFromPassengers();
    }

    public function update(Request $request, string $role, ContractFlightPassenger $contractFlightPassenger)
    {
        $validated = $request->validate([
            'stage' => ['required', Rule::in(array_keys(ContractFlightPassenger::stages()))],
            'ticket_class' => ['nullable', Rule::in(array_keys(ContractFlightPassenger::TICKET_CLASSES))],
            'handling_type' => ['nullable', Rule::in(array_keys(ContractFlightPassenger::HANDLING_TYPES))],
            'flight_category_id' => ['nullable', 'exists:flight_categories,id'],
            'exception_flag' => ['nullable', Rule::in(array_keys(ContractFlightPassenger::EXCEPTION_FLAGS))],
            'contract_confirmed' => ['required', 'boolean'],
            'has_bmet' => ['required', 'boolean'],
            'has_manpower_card' => ['required', 'boolean'],
            'boarding_training' => ['nullable', 'boolean'],
            'manpower_card_fee' => ['nullable', 'numeric', 'min:0'],
            'agent_id' => ['required', 'exists:users,id'],
            'agent_price' => ['nullable', 'numeric', 'min:0'],
            'agent_paid_amount' => ['nullable', 'numeric', 'min:0', function ($attribute, $value, $fail) use ($request, $contractFlightPassenger) {
                // Billed = package + every change charge / penalty / fare
                // difference billed to the agent — receipts are allocated
                // against all of it (ContractFlightAgentReceipts), so paid can
                // rightly exceed the package alone.
                $voided = in_array($request->input('exception_flag', $contractFlightPassenger->exception_flag), ContractFlightPassenger::AGENT_CHARGE_VOIDING_FLAGS, true);
                $billed = ($voided ? 0.0 : max(0.0, (float) $request->input('agent_price', 0) - $contractFlightPassenger->priceRevision()))
                    + (float) ($contractFlightPassenger->penaltyTotals()['billed'] ?? 0);
                if ((float) $value > $billed + 0.005) {
                    $fail('Payment received cannot exceed the amount billed to the agent.');
                }
            }],
            'manpower_cost' => ['nullable', 'numeric', 'min:0'],
            'boarding_cost' => ['nullable', 'numeric', 'min:0'],
            'immigration_cost' => ['nullable', 'numeric', 'min:0'],
            'reissue_cost' => ['nullable', 'numeric', 'min:0'],
            'reissue_bearer' => ['nullable', Rule::in(array_keys(ContractFlightPassenger::REISSUE_BEARERS))],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'tickets_required' => ['nullable', 'integer', 'min:1', 'max:5'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'journey_event' => ['nullable', Rule::in(array_keys(ContractFlightPassenger::EVENT_TYPES))],
            'journey_event_note' => ['nullable', 'string', 'max:2000'],
            'journey_event_at' => ['nullable', 'date'],
            'money_event' => ['nullable', 'array'],
            'money_event.cancel_ticket' => ['nullable', 'boolean'],
            'money_event.fault' => ['nullable', Rule::in(array_keys(ContractFlightPassengerChange::FAULTS))],
            'money_event.refunds' => ['nullable', 'array'],
            'money_event.refunds.*' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ]);

        // "Off flight" belongs to Cancel / Re-assign alone
        // (ContractFlightRebookController): it means the passenger has no
        // flight, so this form may neither set it on someone who has one nor
        // clear it from someone who has none — the flag and the missing
        // flight must never disagree.
        if ($contractFlightPassenger->contract_flight_id === null) {
            $validated['exception_flag'] = ContractFlightPassenger::OFF_FLIGHT_FLAG;
        } elseif (($validated['exception_flag'] ?? null) === ContractFlightPassenger::OFF_FLIGHT_FLAG) {
            return response()->json(['success' => false, 'message' => 'Use "Cancel from flight" to take a passenger off his flight.'], 422);
        }

        DB::transaction(function () use ($validated, $contractFlightPassenger) {
            $oldStage = $contractFlightPassenger->stage;
            $oldFlag = $contractFlightPassenger->exception_flag;
            $oldPaid = (float) ($contractFlightPassenger->agent_paid_amount ?? 0);
            $oldAgentId = $contractFlightPassenger->agent_id ? (int) $contractFlightPassenger->agent_id : null;
            $contractFlightPassenger->update([
                'stage' => $validated['stage'],
                // Absent means "this form did not offer the field" — keep what
                // the passenger already flies, never silently downgrade to economy.
                'ticket_class' => $validated['ticket_class'] ?? $contractFlightPassenger->ticket_class,
                'handling_type' => $validated['handling_type'] ?? $contractFlightPassenger->handling_type,
                'flight_category_id' => $validated['flight_category_id'] ?? $contractFlightPassenger->flight_category_id,
                'exception_flag' => $validated['exception_flag'] ?? null,
                'contract_confirmed_at' => $validated['contract_confirmed']
                    ? ($contractFlightPassenger->contract_confirmed_at ?? now())
                    : null,
                'has_bmet' => $validated['has_bmet'],
                'has_manpower_card' => $validated['has_manpower_card'],
                // A card fee only ever applies to a card we arranged ourselves.
                'manpower_card_fee' => $validated['has_manpower_card'] ? null : ($validated['manpower_card_fee'] ?? null),
                'agent_id' => $validated['agent_id'] ?? null,
                'agent_price' => $validated['agent_price'] ?? null,
                'agent_paid_amount' => $validated['agent_paid_amount'] ?? 0,
                'agent_paid_at' => (float) ($validated['agent_paid_amount'] ?? 0) > 0
                    ? (((float) $validated['agent_paid_amount'] !== $oldPaid) ? now() : $contractFlightPassenger->agent_paid_at) : null,
                'manpower_cost' => $validated['manpower_cost'] ?? null,
                'boarding_training' => array_key_exists('boarding_training', $validated)
                    ? (bool) $validated['boarding_training'] : (bool) $contractFlightPassenger->boarding_training,
                'boarding_cost' => (array_key_exists('boarding_training', $validated) ? (bool) $validated['boarding_training'] : (bool) $contractFlightPassenger->boarding_training)
                    ? ($validated['boarding_cost'] ?? null) : null,
                'immigration_cost' => $validated['immigration_cost'] ?? null,
                'reissue_cost' => $validated['reissue_cost'] ?? null,
                'reissue_bearer' => $validated['reissue_bearer'] ?? null,
                'contact_phone' => $validated['contact_phone'] ?? null,
                'tickets_required' => max(1, (int) ($validated['tickets_required'] ?? $contractFlightPassenger->tickets_required ?? 1)),
                'remarks' => $validated['remarks'] ?? null,
            ]);

            if ($oldStage !== $contractFlightPassenger->stage) {
                $this->recordEvent($contractFlightPassenger, 'stage', $contractFlightPassenger->stage, ContractFlightPassenger::stageLabel($contractFlightPassenger->stage));
                ContractFlightMessenger::auto(ContractFlightMessenger::scenarioForStage($contractFlightPassenger->stage), $contractFlightPassenger);
            }
            if ($oldFlag !== $contractFlightPassenger->exception_flag && $contractFlightPassenger->exception_flag
                && isset(ContractFlightMessenger::FLAG_SCENARIOS[$contractFlightPassenger->exception_flag])) {
                // A newly raised flag (rescheduled, cancelled, offloaded…) is
                // exactly the kind of news people must hear at once.
                ContractFlightMessenger::auto(ContractFlightMessenger::FLAG_SCENARIOS[$contractFlightPassenger->exception_flag], $contractFlightPassenger, $validated['journey_event_note'] ?? null);
            }
            if ($oldFlag !== $contractFlightPassenger->exception_flag
                && in_array($contractFlightPassenger->exception_flag, self::MONEY_FLAGS, true)
                && ! empty($validated['money_event'])
                && ContractFlightPassengerChange::installed()) {
                $this->applyMoneyEvent($contractFlightPassenger, $validated['money_event'], $oldFlag, $validated['journey_event_note'] ?? null);
            }
            if ($oldFlag !== $contractFlightPassenger->exception_flag) {
                $this->recordEvent(
                    $contractFlightPassenger, 'exception', $contractFlightPassenger->exception_flag,
                    $contractFlightPassenger->exception_flag
                        ? ContractFlightPassenger::EXCEPTION_FLAGS[$contractFlightPassenger->exception_flag]
                        : 'Exception cleared',
                    $contractFlightPassenger->exception_flag ? null : (ContractFlightPassenger::EXCEPTION_FLAGS[$oldFlag] ?? $oldFlag)
                );
            }
            if ((float) $contractFlightPassenger->agent_paid_amount !== $oldPaid) {
                $this->recordEvent($contractFlightPassenger, 'finance', 'agent_payment', 'Agent payment updated', 'Received: '.number_format((float) $contractFlightPassenger->agent_paid_amount, 2));
            }
            if (! empty($validated['journey_event'])) {
                $eventType = in_array($validated['journey_event'], ['refund_requested', 'refund_received'], true)
                    ? 'finance' : ($validated['journey_event'] === 'note' ? 'note' : 'exception');
                $this->recordEvent(
                    $contractFlightPassenger, $eventType, $validated['journey_event'],
                    ContractFlightPassenger::EVENT_TYPES[$validated['journey_event']],
                    $validated['journey_event_note'] ?? null,
                    $validated['journey_event_at'] ?? null
                );
            }

            if ($validated['contract_confirmed']) {
                // Contract confirmed = the Agent stage's work is done. Skip
                // the Manpower/BMET conditional stage when the passenger
                // already has BMET clearance. advanceStageTo() is a no-op
                // once the passenger is already further along, so it's safe
                // to call on every save — not just the first confirmation.
                if ($contractFlightPassenger->advanceStageTo('information_entered')) {
                    $this->recordEvent($contractFlightPassenger, 'stage', 'information_entered', ContractFlightPassenger::stageLabel('information_entered'));
                }
            }

            app(TicketBaggage::class)->forPassenger($contractFlightPassenger);
            $this->reconcileAgentLedger($contractFlightPassenger, $oldAgentId);
            $this->reconcileReissueLedger($contractFlightPassenger, $oldAgentId);
            $this->reconcilePassengerServiceCosts($contractFlightPassenger);

            // Whatever this edit filled in (category, price, ...) moves the
            // pipeline forward by itself — the dropdown above is only for
            // the physical stages the data cannot prove.
            $contractFlightPassenger->unsetRelation('ticketPurchases');
            if ($moved = $contractFlightPassenger->syncDerivedStage()) {
                $this->recordEvent($contractFlightPassenger, 'stage', $moved, ContractFlightPassenger::stageLabel($moved), 'Reached automatically from the details entered');
            }
        });
        $contractFlightPassenger->contractFlight?->syncMoneyFromPassengers();

        return response()->json([
            'success' => true,
            'message' => 'Passenger updated successfully.',
            'passenger' => $this->present($contractFlightPassenger->fresh(['passportHolder', 'agent', 'contractFlight', 'flightCategory.categoryType', 'ticketPurchases', 'serviceCosts.vendor', 'serviceCosts.bank', 'events.creator'])),
        ]);
    }

    /**
     * Flights for the "Move to another flight" picker — by flight number,
     * excluding the passenger's current flight so it can never be offered
     * as its own destination.
     */
    public function searchFlights(Request $request, string $role)
    {
        $matches = ContractFlight::query()
            ->with(['ticket.from_airport', 'ticket.to_airport'])
            ->when($request->filled('q'), fn ($q) => $q->where('flight_number', 'like', '%'.$request->input('q').'%'))
            ->when($request->filled('exclude'), fn ($q) => $q->whereKeyNot($request->integer('exclude')))
            ->orderByDesc('departure_at')
            ->limit(20)
            ->get(['id', 'flight_number', 'departure_at', 'ticket_id'])
            ->map(fn (ContractFlight $f) => [
                'id' => $f->id,
                'flight_number' => $f->flight_number,
                'route' => $f->route,
                'departure_at' => $f->departure_at?->toIso8601String(),
            ]);

        return response()->json(['success' => true, 'flights' => $matches]);
    }

    /**
     * Move a passenger to a different flight batch — for a rescheduled/
     * cancelled flight where the physical departure (aircraft, date) has
     * changed, so the passenger is no longer really part of the old batch.
     *
     * Deliberately does NOT touch agent_price, reissue_cost, service costs,
     * or any ledger row: the agent charge and any reissue billing already
     * posted (ReconcilesContractFlightAgent / ReconcilesContractFlightReissue)
     * stay exactly as they are — this only changes which flight the
     * passenger's card belongs to and refreshes the existing ledger rows'
     * text so they name the new flight instead of the old one. Re-running
     * the reconcilers here would risk re-deciding an amount that a human
     * already settled; a flight move is not a money event.
     */
    /**
     * Re-Issue or Refund ONE linked ticket, same flight (feature from live,
     * 2026-09-29; money posted OUR way, 2026-09-30):
     *
     *  reissue — same PNR; airline penalty + fare difference = one fee.
     *    The ticket's vendor / portal is owed it (postReissueFee: vendor row
     *    or wallet + Dr 5173 / Cr payable); if the agent pays, his statement
     *    carries it (reconcileChangeCharge: Dr 1311 / Cr 4183). A new travel
     *    date overwrites the ticket's leg date, the old one kept in the note.
     *  refund — Ticketing's own cancel with the airline's refund, then the
     *    penalty onto the Company Ledger, billed to the agent if his side —
     *    exactly the Cancel-from-flight path (cancelPassengerTickets,
     *    postChangeCosts).
     *
     * Every save ADDS rows; nothing is edited or deleted.
     */
    public function ticketAdjustment(Request $request, string $role, ContractFlightPassenger $contractFlightPassenger)
    {
        if (! ContractFlightPassengerChange::ticketAdjustmentAvailable()) {
            return response()->json(['success' => false, 'message' => 'This feature is not installed on this server yet.'], 422);
        }
        $validated = $request->validate([
            'ticket_purchase_id' => ['required', 'integer'],
            'type' => ['required', Rule::in(['reissue', 'refund'])],
            'penalty' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'fare_diff' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'refund' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            // Our own charge (owner, 2026-10-13): always the agent's, never the
            // seller's — rides on his ONE line beside the airline's penalty.
            'fee' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'new_travel_date' => ['nullable', 'date'],
            'bearer' => ['required', Rule::in(array_keys(ContractFlightPassengerChange::BEARERS))],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        $p = $contractFlightPassenger;
        $isRefund = $validated['type'] === 'refund';
        $t = $p->ticketPurchases()->where('ticket_purchases.id', (int) $validated['ticket_purchase_id'])->first();
        if (! $t) {
            return response()->json(['success' => false, 'message' => 'That ticket is not linked to this passenger.'], 422);
        }
        if ($t->status === 'cancelled') {
            return response()->json(['success' => false, 'message' => 'That ticket is already cancelled — adjust its replacement instead.'], 422);
        }
        // "Agent pays" on a passenger with no agent would bill nobody.
        $bearer = $validated['bearer'] === 'agent' && ! $p->agent_id ? 'company' : $validated['bearer'];
        $pnr = $t->ticket_no ?: ('#'.$t->id);
        $p->loadMissing(['contractFlight.creator', 'passportHolder']);
        $flight = $p->contractFlight;
        $userNote = $validated['note'] ?? null;
        $ours = round((float) ($validated['fee'] ?? 0), 2);
        if ($ours > 0 && ! $p->agent_id) {
            return response()->json(['success' => false, 'message' => 'He has no agent to bill our charge to. Clear it, or set his agent first.'], 422);
        }

        if ($isRefund) {
            $refund = round(min((float) ($validated['refund'] ?? 0), (float) $t->amount), 2);
            try {
                DB::transaction(function () use ($p, $t, $flight, $refund, $bearer, $pnr, $userNote, $ours) {
                    $this->lockPassenger($p);
                    if ($t->fresh()?->status === 'cancelled') {
                        throw new StalePassengerException('Ticket '.$pnr.' was just refunded by someone else. Reload the page.');
                    }
                    $cancelled = $this->cancelPassengerTickets($p,
                        ($p->passportHolder?->name ?: 'passenger').' — refunded on '.($flight?->flight_number ?: 'his flight').($userNote ? ' · '.$userNote : ''),
                        [(int) $t->id], [$t->id => $refund]);
                    $penalty = (float) ($cancelled['penalty'] ?? 0);
                    $change = ContractFlightPassengerChange::create([
                        'contract_flight_passenger_id' => $p->id,
                        'kind' => 'ticket_refund',
                        'from_flight_id' => $flight?->id,
                        'note' => trim('Refund — '.$pnr.': airline refund ৳'.number_format($refund, 2)
                            .($penalty > 0 ? ', kept ৳'.number_format($penalty, 2) : ', full refund')
                            .($ours > 0 ? ', our charge ৳'.number_format($ours, 2) : '').($userNote ? ' · '.$userNote : '')),
                        // our charge = the change's own charge, billed to the agent (4183)
                        'charge' => $ours > 0 ? $ours : null,
                        'bearer' => $ours > 0 ? 'agent' : null,
                        'agent_id' => $p->agent_id,
                        'previous_ticket_purchase_ids' => [(int) $t->id],
                        'created_by' => Auth::id(),
                    ] + (ContractFlightPassengerChange::hasFaultColumns() ? [
                        'fault' => $penalty > 0 ? $bearer : null,
                        'airline_penalty' => $penalty > 0 ? $penalty : null,
                    ] : []));
                    $this->reconcileChangeCharge($change);
                    $this->postChangeCosts($change, $p, $flight, ['penalty']);
                    if ($back = $p->stepBackIfUnticketed()) {
                        $this->recordEvent($p, 'stage', $back, ContractFlightPassenger::stageLabel($back), $pnr.' refunded — issue the new ticket');
                    }
                    $this->recordEvent($p, 'exception', 'ticket_cancelled', 'Ticket refunded',
                        $pnr.' · refund ৳'.number_format($refund, 2).($penalty > 0 ? ' · airline kept ৳'.number_format($penalty, 2).' — '.($bearer === 'agent' ? 'billed to the agent' : 'borne by us') : ' · full refund'));
                });
            } catch (StalePassengerException $e) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
        } else {
            $penalty = round((float) ($validated['penalty'] ?? 0), 2);
            $fareDiff = round((float) ($validated['fare_diff'] ?? 0), 2);
            $fee = round($penalty + $fareDiff, 2);   // the seller's side
            if ($fee + $ours <= 0) {
                return response()->json(['success' => false, 'message' => 'Enter the airline re-issue penalty, a fare difference or our charge.'], 422);
            }
            if ($ours > 0 && $fee > 0 && $bearer !== 'agent') {
                return response()->json(['success' => false, 'message' => 'Our charge is billed to the agent — post it with the agent’s share, not with ours.'], 422);
            }
            if ($ours > 0) {
                $bearer = 'agent';
            }
            if (! $flight) {
                return response()->json(['success' => false, 'message' => 'He is not on a flight — use Re-assign.'], 422);
            }
            try {
                DB::transaction(function () use ($p, $t, $flight, $penalty, $fareDiff, $fee, $ours, $bearer, $pnr, $userNote, $validated) {
                    $this->lockPassenger($p);
                    // A new date overwrites the ticket's leg date — the one every
                    // screen reads — and the old one is kept in the note, which
                    // is where the "was …" lines come from.
                    $dates = null;
                    if (! empty($validated['new_travel_date'])) {
                        $new = substr((string) $validated['new_travel_date'], 0, 10);
                        $oldDay = substr((string) $t->travelAt(), 0, 10);
                        // Leg and the ticket's departure_at move together, keeping
                        // the time of day (2026-10-06) — Ticket Orders reads the latter.
                        if ($oldDay !== $new && $t->moveTravelDate($new)) {
                            $dates = 'New dates: '.$pnr.' '.($oldDay ?: '—').' → '.$new;
                        }
                    }
                    // Same shape as Re-assign's per-ticket charge, so every reader
                    // (penaltyTotals, labels, history) treats both alike.
                    $change = ContractFlightPassengerChange::create([
                        'contract_flight_passenger_id' => $p->id,
                        'kind' => 'ticket_reissue',
                        'from_flight_id' => $flight->id,
                        'to_flight_id' => $flight->id,
                        'note' => implode(' · ', array_filter([
                            'Charge per ticket — '.($bearer === 'agent' ? 'agent pays' : 'we bear').': '
                                .implode(' + ', array_filter([$fee > 0 ? $pnr.' ৳'.number_format($fee, 2) : null, $ours > 0 ? 'our charge ৳'.number_format($ours, 2) : null])),
                            $fee > 0 ? 'airline penalty ৳'.number_format($penalty, 2).' + fare difference ৳'.number_format($fareDiff, 2) : null,
                            $dates,
                            $userNote,
                        ])),
                        'charge' => round($fee + $ours, 2),
                        'bearer' => $bearer,
                        'agent_id' => $p->agent_id,
                        'previous_ticket_purchase_ids' => [(int) $t->id],
                        'created_by' => Auth::id(),
                    ]);
                    if ($bearer === 'agent') {
                        $this->reconcileChangeCharge($change);
                    }
                    // Two seller rows (owner, 2026-10-06): the airline's re-issue charge
                    // (5173 Rebooking & Change Fees) and the fare difference (5172).
                    $this->postReissueFee($change, $p, $flight, (int) $t->id, $fee, $bearer, [
                        ['Re-issue charge', $penalty, ContractFlightPassengerChange::REISSUE_FEE_TYPE, config('accounts.cf_change_company')],
                        ['Fare difference', $fareDiff, ContractFlightPassengerChange::REISSUE_FARE_TYPE, config('accounts.cf_fare_diff_company')],
                    ]);
                    $this->recordEvent($p, 'exception', 'reissue', 'Ticket re-issued',
                        $pnr.' · ৳'.number_format($fee + $ours, 2).' — '.($bearer === 'agent' ? 'agent pays' : 'we bear')
                        .($ours > 0 ? ' (our charge ৳'.number_format($ours, 2).')' : '').($dates ? ' · '.$dates : ''));
                });
            } catch (StalePassengerException $e) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
        }
        $flight?->syncMoneyFromPassengers();

        // The flight card follows the FIRST leg (owner, 2026-10-04). A later
        // leg can take any date and he stays; leg 1 on a day other than his
        // flight's means the flight he actually takes has changed — say so.
        $notice = null;
        if (! $isRefund && ! empty($validated['new_travel_date']) && $flight?->departure_at) {
            $slots = $p->fresh(['contractFlight.ticket', 'ticketPurchases.legs'])->ticketSlots();
            $isFirst = (int) ($slots[0]['purchase_id'] ?? 0) === (int) $t->id;
            $newDay = substr((string) $validated['new_travel_date'], 0, 10);
            if ($isFirst && $newDay !== $flight->departure_at->format('Y-m-d')) {
                $notice = 'Leg 1 ('.e($pnr).') now flies on <b>'.e(Carbon::parse($newDay)->format('d M Y'))
                    .'</b>, but he is still on <b>'.e($flight->flight_number).'</b> ('.e($flight->departure_at->format('d M Y')).').'
                    .' If his flight has changed, use <b>Re-assign</b> to put him on the matching flight.';
            }
        }

        $fresh = $p->fresh(['passportHolder', 'agent', 'flightCategory.categoryType', 'ticketPurchases']);

        return response()->json([
            'success' => true,
            'notice' => $notice,
            'message' => $isRefund ? 'Refund saved and posted.' : 'Re-issue saved and posted.',
            'passenger' => $this->present($fresh),
        ]);
    }

    /**
     * Cancel return (owner, 2026-10-14) — a family passenger flew out (DAC → ECN)
     * and stays on; his unused return (ECN → DAC) is cancelled within the
     * airline's window. Family categories only (App\Services\ReturnCancel).
     *
     *   round trip     only its return half: the airline's refund posted beside
     *                  the live ticket (TicketPurchaseController::refundReturnPart);
     *                  the penalty is what the airline kept for that half, typed
     *   return ticket  the whole ticket, exactly as the lane's Refund
     *                  (cancelPassengerTickets); penalty = cost − refund
     *
     * Then, as every change here, ONE row each, nothing edited:
     *   penalty     Dr 5171 / Cr 5110 (postChangeCosts) — billed to the agent
     *               when his side, inside his ONE line with our charge
     *               (reconcileChangeCharge: Dr AR / Cr 4181 / Cr 4183)
     *   package     kept, or lowered — a typed new total or an amount given back —
     *               as its own credit row PRICE_REVISION_TYPE (Dr revenue / Cr AR);
     *               the package row itself keeps the quoted price.
     */
    public function cancelReturn(Request $request, string $role, ContractFlightPassenger $contractFlightPassenger)
    {
        if (! ReturnCancel::ready()) {
            return response()->json(['success' => false, 'message' => 'Cancel return is not installed on this server yet.'], 422);
        }
        $v = $request->validate([
            'ticket_purchase_id' => ['required', 'integer'],
            'refund' => ['required', 'numeric', 'min:0', 'max:99999999'],
            // round trip: what the airline kept for the return half
            'penalty' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'bearer' => ['nullable', Rule::in(array_keys(ContractFlightPassengerChange::BEARERS))],
            'fee' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'agent_mode' => ['required', Rule::in(['keep', 'total', 'giveback'])],
            'agent_value' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        $p = $contractFlightPassenger;
        $p->loadMissing(['contractFlight.creator', 'contractFlight.ticket.from_airport', 'passportHolder', 'flightCategory']);
        $t = $p->ticketPurchases()->where('ticket_purchases.id', (int) $v['ticket_purchase_id'])->first();
        $fail = fn (string $m) => response()->json(['success' => false, 'message' => $m], 422);
        if (! $t) {
            return $fail('That ticket is not linked to this passenger.');
        }
        $state = ReturnCancel::state($t, $p);
        if (! $state['family']) {
            return $fail('Cancel return is for family passengers only — his category is '.($p->flightCategory?->name ?: 'not set').'.');
        }
        if (! $state['shape']) {
            return $fail('This ticket has no return part to cancel.');
        }
        if ($state['done'] || $t->status !== 'confirm') {
            return $fail('The return on this ticket is already cancelled.');
        }
        $round = $state['shape'] === 'round_trip';
        $amount = round((float) $t->amount, 2);
        $refund = round(min((float) $v['refund'], $amount), 2);
        $penalty = $round ? round((float) ($v['penalty'] ?? 0), 2) : round($amount - $refund, 2);
        if ($round && $refund + $penalty > $amount + 0.005) {
            return $fail('The refund and the penalty together (৳'.number_format($refund + $penalty, 2).') are more than the ticket (৳'.number_format($amount, 2).').');
        }
        $bearer = $penalty > 0 ? ($v['bearer'] ?? null) : null;
        if ($penalty > 0 && ! $bearer) {
            return $fail('Choose who bears the airline penalty (৳'.number_format($penalty, 2).').');
        }
        $ours = round((float) ($v['fee'] ?? 0), 2);
        if (($bearer === 'agent' || $ours > 0) && ! $p->agent_id) {
            return $fail('He has no agent to bill. Set his agent first, or choose "We bear it" and clear our charge.');
        }
        // The agent's total: kept, or lowered to a typed total / by a typed amount.
        $current = $p->chargedPackage();
        $value = round((float) ($v['agent_value'] ?? 0), 2);
        $reduce = match ($v['agent_mode']) {
            'total' => round($current - $value, 2),
            'giveback' => $value,
            default => 0.0,
        };
        if ($v['agent_mode'] !== 'keep' && ($reduce <= 0 || $reduce > $current)) {
            return $fail($v['agent_mode'] === 'total'
                ? 'The new total must be less than his current total (৳'.number_format($current, 2).').'
                : 'The amount to give back must be more than 0 and at most his current total (৳'.number_format($current, 2).').');
        }
        if ($reduce > 0 && ! $p->agent_id) {
            return $fail('He has no agent whose total could be lowered.');
        }

        $pnr = $t->ticket_no ?: ('#'.$t->id);
        $flight = $p->contractFlight;
        $flightNo = $flight?->flight_number ?: 'contract flight';
        $name = $p->passportHolder?->name ?: 'passenger';
        $userNote = $v['note'] ?? null;
        try {
            DB::transaction(function () use ($p, $t, $flight, $flightNo, $name, $pnr, $round, $refund, $penalty, $bearer, $ours, $reduce, $userNote) {
                $this->lockPassenger($p);
                $fresh = TicketPurchase::whereKey($t->id)->lockForUpdate()->first();
                if (! $fresh || $fresh->return_cancelled_at || $fresh->status !== 'confirm') {
                    throw new StalePassengerException('The return on '.$pnr.' was just changed by someone else. Reload the page.');
                }
                $who = $name.' ('.$flightNo.')'.($userNote ? ' · '.$userNote : '');
                if ($round) {
                    app(TicketPurchaseController::class)->refundReturnPart($fresh, $refund, $who, null, $penalty);
                } else {
                    $this->cancelPassengerTickets($p, 'return cancelled — '.$who, [(int) $t->id], [$t->id => $refund]);
                    TicketPurchase::whereKey($t->id)->update(['return_cancelled_at' => now()]);
                    // The return was one of the tickets his card asked for: it no longer
                    // needs one, so "1 / 2 tickets issued" must not leave him pending.
                    $live = $p->ticketPurchases()->where('ticket_purchases.status', 'confirm')->count();
                    if ((int) $p->tickets_required > 1 && $live === (int) $p->tickets_required - 1) {
                        $p->forceFill(['tickets_required' => (int) $p->tickets_required - 1])->saveQuietly();
                    }
                }
                $change = ContractFlightPassengerChange::create([
                    'contract_flight_passenger_id' => $p->id,
                    'kind' => 'return_cancel',
                    'from_flight_id' => $flight?->id,
                    'to_flight_id' => $flight?->id,
                    'note' => trim('Return cancelled — '.$pnr.($round ? ' (return half of the round trip)' : '')
                        .': airline refund ৳'.number_format($refund, 2)
                        .($penalty > 0 ? ', kept ৳'.number_format($penalty, 2).' ('.($bearer === 'agent' ? 'agent pays' : 'we bear').')' : '')
                        .($ours > 0 ? ', our charge ৳'.number_format($ours, 2) : '')
                        .($reduce > 0 ? ', package lowered ৳'.number_format($reduce, 2) : ', package kept')
                        .($userNote ? ' · '.$userNote : '')),
                    // our charge = the change's own charge, billed to the agent (4183)
                    'charge' => $ours > 0 ? $ours : null,
                    'bearer' => $ours > 0 ? 'agent' : null,
                    'agent_id' => $p->agent_id,
                    'previous_ticket_purchase_ids' => [(int) $t->id],
                    'price_revision' => $reduce > 0 ? $reduce : null,
                    'created_by' => Auth::id(),
                ] + (ContractFlightPassengerChange::hasFaultColumns() ? [
                    'fault' => $penalty > 0 ? $bearer : null,
                    'airline_penalty' => $penalty > 0 ? $penalty : null,
                ] : []));
                $this->reconcileChangeCharge($change);
                $this->postChangeCosts($change, $p, $flight, ['penalty']);
                if ($reduce > 0) {
                    $this->postAgentAdjustment((int) $p->agent_id, ContractFlightPassengerChange::PRICE_REVISION_TYPE, $change->id, -$reduce,
                        'Return cancelled — package lowered · '.$name.' ('.$flightNo.')',
                        ($flight?->flight_number ?: 'CF').'-'.$p->id.'-C'.$change->id,
                        config('accounts.flight_sales_revenue'), TravelCompany::ID);
                    ContractFlightPassenger::forgetRevisions();
                    ContractFlightAgentReceipts::sync((int) $p->agent_id);
                }
                $this->recordEvent($p, 'exception', 'return_cancelled', 'Return cancelled',
                    $pnr.' · refund ৳'.number_format($refund, 2)
                    .($penalty > 0 ? ' · airline kept ৳'.number_format($penalty, 2).' — '.($bearer === 'agent' ? 'billed to the agent' : 'borne by us') : '')
                    .($reduce > 0 ? ' · package lowered ৳'.number_format($reduce, 2) : ''));
            });
        } catch (StalePassengerException $e) {
            return $fail($e->getMessage());
        }
        $flight?->syncMoneyFromPassengers();

        $fresh = $p->fresh(['passportHolder', 'agent', 'flightCategory.categoryType', 'ticketPurchases']);

        return response()->json([
            'success' => true,
            'message' => 'Return cancelled and posted.',
            'passenger' => $this->present($fresh),
        ]);
    }

    /**
     * One lane per ticket on his route, each with its own history (owner, 2026-10-08:
     * "DAC-BAH-IST one ticket, IST-ECN another; each may be re-issued, refunded,
     * refunded and re-bought, on one date or many — my mind gets messed up").
     *
     * Read-only. Built from what is already recorded — the tickets ever linked to him
     * (live, and refunded ones kept in his change rows), his change rows and Ticketing's
     * own change register — so nothing new is stored and every older entry shows too.
     * A ticket's lane = the slot its route leg / first sector matches, else its
     * leg_sequence, else lane 1. The live ticket of a lane is its newest one not cancelled.
     *
     * The passenger stays on the flight his FIRST ticket refers to (owner, 2026-10-08):
     * `move` says when lane 1's live date and his flight's date differ.
     */
    public function lanes(string $role, ContractFlightPassenger $contractFlightPassenger)
    {
        $p = $contractFlightPassenger->loadMissing(['contractFlight.ticket', 'ticketPurchases.legs']);
        $slots = $p->ticketSlots();
        $changes = ContractFlightPassengerChange::with(['fromFlight', 'toFlight'])->where('contract_flight_passenger_id', $p->id)->orderBy('id')->get();

        // every ticket that ever belonged to him: linked now, or named by a change row
        $ids = $p->ticketPurchases->pluck('id')->map(fn ($i) => (int) $i)->all();
        foreach ($changes as $c) {
            foreach ((array) ($c->previous_ticket_purchase_ids ?? []) as $i) {
                $ids[] = (int) $i;
            }
        }
        $tickets = TicketPurchase::with(['legs', 'vendor', 'portal'])->whereIn('id', array_unique($ids))->orderBy('id')->get();

        // A live ticket's lane is its slot. Both legs of a via route share one
        // route id, so the route only decides when exactly one slot has it
        // (2026-11-01: every leg-2 ticket of DAC-IST-ECN landed in lane 1).
        $routeSlots = array_count_values(array_filter(array_map(fn ($s) => (int) $s['leg_ticket_id'], $slots)));
        $laneOf = function (TicketPurchase $t) use ($slots, $routeSlots): int {
            foreach ($slots as $s) {
                if ((int) $s['purchase_id'] === (int) $t->id) {
                    return (int) $s['index'];
                }
            }
            $seq = (int) ($t->leg_sequence ?? 0);
            if ($seq >= 1 && $seq <= count($slots)) {
                return $seq;
            }
            foreach ($slots as $s) {
                if ($s['leg_ticket_id'] && (int) $t->ticket_id === (int) $s['leg_ticket_id'] && ($routeSlots[(int) $t->ticket_id] ?? 0) === 1) {
                    return (int) $s['index'];
                }
            }
            $first = $t->legs->first();
            if ($first) {
                foreach ($slots as $s) {
                    if ($s['from_code'] && strcasecmp((string) $first->from_location, (string) $s['from_code']) === 0) {
                        return (int) $s['index'];
                    }
                }
                // Re-issued from another airport but to the same place (2026-10-10): match its end.
                $last = $t->legs->last();
                foreach ($slots as $s) {
                    if ($s['to_code'] && strcasecmp((string) $last->to_location, (string) $s['to_code']) === 0) {
                        return (int) $s['index'];
                    }
                }
            }

            return 1;
        };

        $register = collect();
        try {
            if (Schema::hasTable('ticket_changes')) {
                $register = TicketChange::whereIn('ticket_purchase_id', $tickets->pluck('id'))
                    ->whereNull('contract_flight_passenger_change_id')->orderBy('id')->get();
            }
        } catch (\Throwable) {
            $register = collect();
        }

        $day = fn ($v) => $v ? substr((string) $v, 0, 10) : null;
        $kindLabel = [
            'ticket_reissue' => 'Re-issued', 'reissue' => 'Re-issued', 'reschedule' => 'Date moved',
            'ticket_refund' => 'Refunded', 'cancelled' => 'Cancelled from flight', 'flagged' => 'Charged',
            'restored' => 'Restored', 'newticket' => 'New ticket', 'return_cancel' => 'Return cancelled',
        ];
        $lanes = [];
        foreach ($slots as $s) {
            $lanes[(int) $s['index']] = [
                'n' => (int) $s['index'], 'label' => $s['label'], 'sector' => $s['sector'] ?? null,
                'from' => $s['from_code'] ?? null, 'to' => $s['to_code'] ?? null,
                'live' => null, 'events' => [],
                // A booking on hold for this leg (Ticketing → Booked), shown while nothing is purchased.
                'hold' => $s['hold'] ?? null,
            ];
        }
        // A separate return ticket cancelled by Cancel return (2026-10-14) is unlinked, so it
        // fills no slot: it gets a lane of its own after his tickets — "Return" — rather
        // than landing in lane 1 as a "New ticket".
        $returnLane = [];
        foreach ($tickets as $t) {
            if ($t->status !== 'cancelled' || ! $t->return_cancelled_at) {
                continue;
            }
            $k = (int) max(array_keys($lanes) ?: [0]) + 1;
            $from = $t->legs->first()?->from_location;
            $to = $t->legs->last()?->to_location;
            $lanes[$k] = ['n' => $k, 'label' => 'Return', 'sector' => ($from && $to) ? $from.' → '.$to : 'Return',
                'from' => $from, 'to' => $to, 'live' => null, 'events' => [], 'hold' => null, 'is_return' => true];
            $returnLane[(int) $t->id] = $k;
        }
        $seen = [];
        // The date a ticket was first issued for: its first "PNR yyyy-mm-dd → …" in the change notes
        // (every date move writes one); a ticket never moved flies on its own date (2026-10-10:
        // "Issued EFGH1 · flies 19 Oct" showed today's date on the issue line).
        $isFlightMove = fn ($c) => $c->kind === 'reschedule' && $c->from_flight_id && $c->to_flight_id
            && (int) $c->from_flight_id !== (int) $c->to_flight_id && ! (float) ($c->charge ?? 0);
        $firstDay = function (TicketPurchase $t) use ($changes, $day) {
            if ($t->ticket_no) {
                foreach ($changes as $c) {
                    if (preg_match('/'.preg_quote($t->ticket_no, '/').'\s+(\d{4}-\d{2}-\d{2})[^→,·]*→/u', (string) $c->note, $m)) {
                        return $m[1];
                    }
                }
            }

            return $day($t->travelAt());
        };
        foreach ($tickets as $t) {
            $n = $returnLane[(int) $t->id] ?? $laneOf($t);
            if (! isset($lanes[$n])) {
                $n = 1;
            }
            $sector = $t->legs->isNotEmpty() ? $t->legs->first()->from_location.'→'.$t->legs->last()->to_location : null;
            $isFirstInLane = empty($seen[$n]);
            $seen[$n] = true;
            $lanes[$n]['events'][] = [
                'at' => optional($t->created_at)->toDateTimeString(), 'type' => $isFirstInLane ? 'issued' : 'new',
                'label' => $isFirstInLane ? 'Issued' : 'New ticket', 'pnr' => $t->ticket_no, 'travel' => $firstDay($t),
                'amount' => (float) $t->amount, 'note' => $sector,
            ];
            if ($t->status === 'cancelled' && ! $changes->contains(fn ($c) => $c->reason !== self::LANE_NEW_TICKET_REASON && in_array((int) $t->id, array_map('intval', (array) $c->previous_ticket_purchase_ids), true) && in_array($c->kind, ['ticket_refund', 'cancelled', 'reissue', 'flagged', 'return_cancel'], true))
                && ! $register->contains(fn ($r) => (int) $r->ticket_purchase_id === (int) $t->id && in_array($r->kind, ['refund', 'void', 'return_cancel'], true))) {
                $lanes[$n]['events'][] = [
                    'at' => $t->cancelled_at ?? optional($t->updated_at)->toDateTimeString(), 'type' => 'refund', 'label' => 'Refunded', 'pnr' => $t->ticket_no,
                    'travel' => null, 'amount' => $t->refund_amount !== null ? (float) $t->refund_amount : null, 'note' => $t->cancel_note ?? null,
                ];
            }
            foreach ($changes as $c) {
                $refIds = array_map('intval', (array) $c->previous_ticket_purchase_ids);
                if (! in_array((int) $t->id, $refIds, true)) {
                    continue;
                }
                if ($isFlightMove($c)) {
                    continue;
                }   // shown once, on lane 1, below
                // A row naming several tickets whose note spells out which PNR it moved
                // ("New dates: WCXXY … → …") belongs only to that PNR's lane.
                $note = (string) $c->note;
                if (count($refIds) > 1 && $t->ticket_no && str_contains($note, 'New dates:')) {
                    $named = $tickets->whereIn('id', $refIds)->filter(fn ($o) => $o->ticket_no && str_contains($note, $o->ticket_no));
                    if ($named->isNotEmpty() && ! $named->contains('id', $t->id)) {
                        continue;
                    }
                }
                $moved = null;
                if ($t->ticket_no && preg_match('/'.preg_quote($t->ticket_no, '/').'\s+[^→,·]*→\s*(\d{4}-\d{2}-\d{2})/u', $note, $m)) {
                    $moved = $m[1];
                }
                $type = in_array($c->kind, ['ticket_refund', 'cancelled', 'return_cancel'], true) ? 'refund' : (in_array($c->kind, ['ticket_reissue', 'reissue', 'reschedule'], true) ? 'reissue' : 'other');
                // A lane's "New ticket" row: the fare difference against this refunded ticket.
                $isLaneNew = $c->reason === self::LANE_NEW_TICKET_REASON;
                $lanes[$n]['events'][] = [
                    'at' => optional($c->created_at)->toDateTimeString(), 'type' => $isLaneNew ? 'new' : $type,
                    'label' => ($c->reason === self::UNDONE_REASON ? 'Undone · ' : '').($isLaneNew ? ((float) $c->fare_difference < 0
                            ? 'Cheaper new ticket · '.($c->fare_bearer === 'agent' ? 'saving to agent' : 'we keep it')
                            : 'Fare difference · '.($c->toCard()['fare_bearer_label'] ?? ''))
                        : ($kindLabel[$c->kind] ?? ucfirst(str_replace('_', ' ', (string) $c->kind)))), 'pnr' => $t->ticket_no,
                    // Undo shows only where it would work (undoProblem): the latest entry on that ticket.
                    'undo_id' => $this->undoProblem($p, $c) === null ? $c->id : null,
                    'travel' => $moved,
                    // a cancelled return shows what the airline gave back (its note carries the rest)
                    'amount' => $isLaneNew ? abs((float) $c->fare_difference)
                        : ($c->kind === 'return_cancel' ? (float) ($t->status === 'cancelled' ? $t->refund_amount : $t->return_refund_amount)
                        : ($c->charge !== null ? (float) $c->charge : ($c->airline_penalty ?? null))),
                    'note' => $c->note,
                    // A single-ticket Refund row can have its refund corrected later (correctRefund()).
                    'refund_edit' => (in_array($c->kind, ['ticket_refund', 'cancelled', 'reissue'], true) && $t->status === 'cancelled' && $t->refund_amount !== null
                            && $c->reason !== self::UNDONE_REASON && $c->reason !== self::LANE_NEW_TICKET_REASON)
                        ? ['change_id' => $c->id, 'ticket_id' => $t->id, 'refund' => (float) $t->refund_amount, 'cost' => (float) $t->amount, 'fault' => $c->fault] : null,
                ];
            }
            foreach ($register->where('ticket_purchase_id', $t->id) as $r) {
                $lanes[$n]['events'][] = [
                    'at' => optional($r->created_at)->toDateTimeString(), 'type' => in_array($r->kind, ['refund', 'void', 'return_cancel'], true) ? 'refund' : 'reissue',
                    'label' => ['reissue' => 'Re-issued', 'reschedule' => 'Date moved', 'refund' => 'Refunded', 'void' => 'Voided', 'newticket' => 'New ticket after refund', 'return_cancel' => 'Return cancelled'][$r->kind] ?? ucfirst((string) $r->kind),
                    'pnr' => $t->ticket_no, 'travel' => $day($r->new_travel_at), 'amount' => (float) ($r->cost_added ?: $r->refund_amount ?: 0), 'note' => 'from Ticket Orders',
                ];
            }
            if ($t->status !== 'cancelled' && $p->ticketPurchases->contains('id', $t->id)) {
                $lanes[$n]['live'] = [
                    'id' => $t->id, 'pnr' => $t->ticket_no, 'travel' => $day($t->travelAt()), 'amount' => (float) $t->amount,
                    'seller' => $t->vendor?->name ?? $t->portal?->name, 'status' => $t->status,
                    'end' => $t->legs->isNotEmpty() ? strtoupper((string) $t->legs->last()->to_location) : null,
                    // Cancel return (2026-10-14): family + a return on this ticket; null before the migration
                    'ret' => ReturnCancel::state($t, $p),
                ];
                $lanes[$n]['live']['ret_badge'] = ReturnCancel::badge($lanes[$n]['live']['ret']);
            }
            // A round trip whose return was cancelled stays live; the event says so on its lane.
            if ($t->status !== 'cancelled' && $t->return_cancelled_at
                && ! $changes->contains(fn ($c) => $c->kind === 'return_cancel' && in_array((int) $t->id, array_map('intval', (array) $c->previous_ticket_purchase_ids), true))
                && ! $register->contains(fn ($r) => (int) $r->ticket_purchase_id === (int) $t->id && $r->kind === 'return_cancel')) {
                $lanes[$n]['events'][] = [
                    'at' => $t->return_cancelled_at->toDateTimeString(), 'type' => 'refund', 'label' => 'Return cancelled', 'pnr' => $t->ticket_no,
                    'travel' => null, 'amount' => $t->return_refund_amount !== null ? (float) $t->return_refund_amount : null, 'note' => null,
                ];
            }
        }
        // Moves between flights (Move him / Re-assign with no ticket charge): the passenger's,
        // so ONE line on lane 1 — the lane that decides his flight — never one per ticket.
        foreach ($changes->filter($isFlightMove) as $c) {
            $lanes[1]['events'][] = [
                'at' => optional($c->created_at)->toDateTimeString(), 'type' => 'other',
                'label' => 'Moved to '.($c->toFlight?->flight_number ?: 'another flight'), 'pnr' => '',
                'travel' => $c->toFlight?->departure_at ? $c->toFlight->departure_at->format('Y-m-d') : null,
                'amount' => null, 'note' => trim(($c->fromFlight?->flight_number ? 'from '.$c->fromFlight->flight_number : '').($c->note ? ' · '.$c->note : ''), ' ·'),
            ];
        }

        // The newest refunded ticket of each lane with no new ticket entered against it
        // yet: the lane's "New ticket" works out the fare difference against that one.
        $settled = $changes->where('reason', self::LANE_NEW_TICKET_REASON)
            ->flatMap(fn ($c) => array_map('intval', (array) $c->previous_ticket_purchase_ids))->all();
        foreach ($tickets->where('status', 'cancelled')->sortByDesc('id') as $t) {
            if (isset($returnLane[(int) $t->id])) {
                continue;
            }   // a cancelled return is not replaced
            $n = $laneOf($t);
            if (! isset($lanes[$n])) {
                $n = 1;
            }
            if (! array_key_exists('refunded', $lanes[$n]) && ! in_array((int) $t->id, $settled, true)) {
                $lanes[$n]['refunded'] = ['id' => $t->id, 'pnr' => $t->ticket_no, 'amount' => (float) $t->amount];
            }
        }
        foreach ($lanes as &$lane) {
            $lane['refunded'] = $lane['refunded'] ?? null;
            usort($lane['events'], fn ($a, $b) => strcmp((string) $a['at'], (string) $b['at']));
        }
        unset($lane);

        // One through-ticket replacing several legs (DAC→ECN for DAC→IST + IST→ECN, 2026-10-10):
        // a later lane with no ticket of its own is COVERED when an earlier lane's live ticket
        // runs on to this lane's destination — it is not missing, and needs no New ticket.
        $ordered = array_keys($lanes);
        foreach ($ordered as $i => $k) {
            $lanes[$k]['covered_by'] = null;
            if ($lanes[$k]['live'] || ! $lanes[$k]['to'] || ! empty($lanes[$k]['is_return'])) {
                continue;
            }
            for ($j = $i - 1; $j >= 0; $j--) {
                $earlier = $lanes[$ordered[$j]]['live'] ?? null;
                if ($earlier && $earlier['end'] && strcasecmp($earlier['end'], (string) $lanes[$k]['to']) === 0) {
                    $lanes[$k]['covered_by'] = $earlier['pnr'];
                    break;
                }
            }
        }

        // He stays on the flight his first ticket refers to: lane 1's live date decides.
        $flight = $p->contractFlight;
        $leg1 = $lanes[1]['live']['travel'] ?? null;
        $flightDay = $flight?->departure_at?->format('Y-m-d');
        $move = ($leg1 && $flightDay && $leg1 !== $flightDay) ? [
            'day' => $leg1, 'day_label' => Carbon::parse($leg1)->format('d M Y'),
            'flight' => $flight->flight_number, 'flight_day' => $flight->departure_at->format('d M Y'),
            'ticket_id' => $flight->ticket_id,
        ] : null;

        // A later ticket that departs before the one before it (2026-10-10): ticket 1
        // pushed to 19 Oct while ticket 2 still flies 12 Oct leaves him stranded at the
        // transit. Same day is allowed (a same-day connection). Warning only — no money.
        $clashes = [];
        $ordered = array_values($lanes);
        for ($i = 1; $i < count($ordered); $i++) {
            $prev = $ordered[$i - 1]['live']['travel'] ?? null;
            $cur = $ordered[$i]['live']['travel'] ?? null;
            if ($prev && $cur && $cur < $prev) {
                $clashes[] = [
                    'lane' => $ordered[$i]['n'], 'prev_lane' => $ordered[$i - 1]['n'],
                    'pnr' => $ordered[$i]['live']['pnr'], 'prev_pnr' => $ordered[$i - 1]['live']['pnr'],
                    'day' => Carbon::parse($cur)->format('d M Y'),
                    'prev_day' => Carbon::parse($prev)->format('d M Y'),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'clashes' => $clashes,
            'lanes' => array_values($lanes),
            // his package as it stands — Cancel return's "keep / new total / give back" starts here
            'package' => round($p->chargedPackage(), 2),
            'flight' => $flight ? ['id' => $flight->id, 'number' => $flight->flight_number, 'day' => $flightDay] : null,
            'move' => $move,
        ]);
    }

    /**
     * A lane's ticket was refunded and he has bought a new one (owner, 2026-10-07:
     * "fare difference is for refunded clients ... new ticket vs old, who bears it
     * + charges"). One press on the lane: links the new PNR, records a change row
     * against the refunded ticket (reason LANE_NEW_TICKET_REASON) and posts the fare
     * difference = new − old (never below 0) to whoever bears it — the same
     * postChangeCosts(['fare']) Re-assign uses — plus our optional fee, billed to
     * the agent through reconcileChangeCharge(). He stays on his flight; if lane 1's
     * date now differs, the lanes banner offers the move. One per refunded ticket.
     */
    public const LANE_NEW_TICKET_REASON = 'lane_new_ticket';

    public function laneNewTicket(Request $request, string $role, ContractFlightPassenger $contractFlightPassenger)
    {
        $p = $contractFlightPassenger;
        if (! ContractFlightPassengerChange::hasFaultColumns()) {
            return response()->json(['success' => false, 'message' => 'This feature is not installed on this server yet.'], 422);
        }
        $validated = $request->validate([
            'old_ticket_purchase_id' => ['required', 'integer'],
            'ticket_purchase_id' => ['required', 'integer', Rule::exists('ticket_purchases', 'id')->where('passport_holder_id', $p->passport_holder_id)],
            'fare_bearer' => ['required', Rule::in(array_keys(ContractFlightPassengerChange::BEARERS))],
            'agent_fare_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'charge' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'note' => ['nullable', 'string', 'max:2000'],
        ], ['ticket_purchase_id.exists' => 'That ticket does not belong to this passenger.']);

        $old = TicketPurchase::with('legs')->find((int) $validated['old_ticket_purchase_id']);
        $belonged = $old && ((int) $old->passport_holder_id === (int) $p->passport_holder_id);
        if (! $belonged || $old->status !== 'cancelled') {
            return response()->json(['success' => false, 'message' => 'The old ticket must be one of his refunded tickets.'], 422);
        }
        $already = ContractFlightPassengerChange::where('contract_flight_passenger_id', $p->id)
            ->where('reason', self::LANE_NEW_TICKET_REASON)->get()
            ->contains(fn ($c) => in_array((int) $old->id, array_map('intval', (array) $c->previous_ticket_purchase_ids), true));
        if ($already) {
            return response()->json(['success' => false, 'message' => 'A new ticket was already entered against '.($old->ticket_no ?: 'this refund').'.'], 422);
        }
        $newId = (int) $validated['ticket_purchase_id'];
        if ($newId === (int) $old->id) {
            return response()->json(['success' => false, 'message' => 'Pick the NEW ticket, not the refunded one.'], 422);
        }
        if ($problem = self::ticketLinkProblem($p, $newId)) {
            return response()->json(['success' => false, 'message' => $problem], 422);
        }
        $new = TicketPurchase::find($newId);
        // Signed: dearer > 0 (bearer pays it), cheaper < 0 (owner, 2026-10-10: "option of
        // who takes it" — fare_bearer 'agent' = the agent gets the saving back as a credit,
        // 'company' = we keep it and nothing posts).
        $diff = round((float) $new->amount - (float) $old->amount, 2);
        $agentFare = $validated['agent_fare_amount'] ?? null;
        if ($agentFare !== null && (! ContractFlightPassengerChange::splitFareAvailable() || ! $p->agent_id || $validated['fare_bearer'] !== 'agent' || $diff <= 0 || round((float) $agentFare, 2) > $diff)) {
            return response()->json(['success' => false, 'message' => 'The agent share must be between zero and the positive fare difference, with an agent selected.'], 422);
        }
        $fee = round((float) ($validated['charge'] ?? 0), 2);
        $flight = $p->contractFlight;
        $p->loadMissing(['passportHolder', 'contractFlight.creator']);

        $change = DB::transaction(function () use ($p, $old, $new, $diff, $fee, $flight, $validated) {
            $this->lockPassenger($p);
            if (ContractFlightPassengerChange::where('contract_flight_passenger_id', $p->id)->where('reason', self::LANE_NEW_TICKET_REASON)->get()->contains(fn ($c) => in_array((int) $old->id, array_map('intval', (array) $c->previous_ticket_purchase_ids), true))) {
                throw ValidationException::withMessages(['old_ticket_purchase_id' => 'A new ticket was already posted against this refund.']);
            }
            $p->ticketPurchases()->syncWithoutDetaching([$new->id => ['linked_by' => Auth::id()]]);
            $change = ContractFlightPassengerChange::create([
                'contract_flight_passenger_id' => $p->id,
                'kind' => 'reissue',
                'reason' => self::LANE_NEW_TICKET_REASON,
                'from_flight_id' => $flight?->id,
                'to_flight_id' => $flight?->id,
                'note' => trim('New ticket '.($new->ticket_no ?: '#'.$new->id).' after refund of '.($old->ticket_no ?: '#'.$old->id)
                    .($validated['note'] ? ' — '.$validated['note'] : '')),
                'charge' => $fee > 0 ? $fee : null,
                'bearer' => $fee > 0 ? 'agent' : null,
                'agent_id' => $p->agent_id,
                'previous_ticket_purchase_ids' => [(int) $old->id],
                'fare_difference' => $diff,  // negative = a saving
                'fare_bearer' => $validated['fare_bearer'],
                'created_by' => Auth::id(),
            ]);
            if (isset($validated['agent_fare_amount'])) {
                $change->update(['agent_fare_amount' => round((float) $validated['agent_fare_amount'], 2)]);
            }
            $this->reconcileChangeCharge($change);
            if ($diff > 0) {
                $this->postChangeCosts($change, $p, $flight, ['fare']);
            } elseif ($diff < 0 && $validated['fare_bearer'] === 'agent' && $p->agent_id) {
                // The saving back to the agent: a CREDIT row of the same fare-difference
                // type (every reader sums debit − credit), journal Dr 4182 / Cr AR.
                $this->postAgentAdjustment((int) $p->agent_id, ContractFlightPassengerChange::FARE_LEDGER_TYPE, $change->id, $diff,
                    'Cheaper new ticket — saving returned to agent — '.($p->passportHolder?->name ?: 'passenger').' ('.($flight?->flight_number ?: 'contract flight').')',
                    ($flight?->flight_number ?: 'contract flight').'-'.$p->id.'-C'.$change->id, config('accounts.cf_fare_diff_recovered'),
                    TravelCompany::ID);
            }

            return $change;
        });

        $this->logRebookEvent($p, 'finance', 'fare_difference', 'New ticket after refund',
            'New '.($new->ticket_no ?: '#'.$new->id).' ৳'.number_format((float) $new->amount, 2)
            .' − refunded '.($old->ticket_no ?: '#'.$old->id).' ৳'.number_format((float) $old->amount, 2)
            .($diff >= 0 ? ' = fare difference ৳'.number_format($diff, 2) : ' = cheaper by ৳'.number_format(-$diff, 2))
            .($diff > 0 ? ' — '.$change->toCard()['fare_bearer_label'] : '')
            .($diff < 0 ? ($validated['fare_bearer'] === 'agent' ? ' — returned to the agent' : ' — we keep it') : '')
            .($fee > 0 ? ' · our fee ৳'.number_format($fee, 2).' billed to the agent' : ''));

        $p->load('ticketPurchases');
        if ($moved = $p->syncDerivedStage()) {
            $this->recordEvent($p, 'stage', $moved, ContractFlightPassenger::stageLabel($moved), 'Reached automatically from the details entered');
        }
        $flight?->syncMoneyFromPassengers();

        return response()->json([
            'success' => true,
            'message' => 'New ticket linked. '.($diff > 0
                ? 'Fare difference ৳'.number_format($diff, 2).' — '.$change->toCard()['fare_bearer_label']
                : ($diff < 0 ? 'Cheaper by ৳'.number_format(-$diff, 2).($validated['fare_bearer'] === 'agent' ? ' — credited to the agent' : ' — we keep it') : 'Same fare, nothing to bear')).'.',
            'change_id' => $change->id,
        ]);
    }

    /**
     * Correct the airline refund of one refunded ticket (owner, 2026-10-10: refunds post
     * immediately with the amount typed, "editable later"). Only ever ADDS rows:
     *  - seller: Ticketing's correctRefundAmount() — a refund-difference row + journal;
     *  - penalty (cost − refund) moves the other way: company journal for the
     *    difference on the same heads as the original (Dr 5171 / Cr 5110, or reversed);
     *  - his agent line: correctChangeCharge() adds one correction row when the agent
     *    bears the penalty.
     * Works on a single-ticket Refund row (kind ticket_refund) from the card / lanes.
     */
    public function correctRefund(Request $request, string $role, ContractFlightPassenger $contractFlightPassenger)
    {
        $p = $contractFlightPassenger;
        if (! ContractFlightPassengerChange::hasFaultColumns()) {
            return response()->json(['success' => false, 'message' => 'This feature is not installed on this server yet.'], 422);
        }
        $validated = $request->validate([
            'change_id' => ['required', 'integer'],
            // A refund that covered several tickets (Cancel from flight / Re-assign) names which one.
            'ticket_purchase_id' => ['nullable', 'integer'],
            'refund' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'bearer' => ['nullable', Rule::in(array_keys(ContractFlightPassengerChange::BEARERS))],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        $change = ContractFlightPassengerChange::where('id', (int) $validated['change_id'])
            ->where('contract_flight_passenger_id', $p->id)->whereIn('kind', ['ticket_refund', 'cancelled', 'reissue'])->first();
        $ids = array_map('intval', (array) ($change?->previous_ticket_purchase_ids ?? []));
        $pick = (int) ($validated['ticket_purchase_id'] ?? 0) ?: (count($ids) === 1 ? $ids[0] : 0);
        if (! $change || ! $ids || ! in_array($pick, $ids, true)) {
            return response()->json(['success' => false, 'message' => 'Pick which ticket of this refund to correct.'], 422);
        }
        $t = TicketPurchase::find($pick);
        if (! $t || $t->status !== 'cancelled') {
            return response()->json(['success' => false, 'message' => 'That ticket is not refunded.'], 422);
        }
        $amount = (float) $t->amount;
        $newRefund = round(min((float) $validated['refund'], $amount), 2);
        $oldRefund = (float) $t->refund_amount;
        $oldPenalty = round((float) ($change->airline_penalty ?? 0), 2);
        $newPenalty = round(max(0.0, $oldPenalty - ($newRefund - $oldRefund)), 2);
        if (abs($newRefund - $oldRefund) < 0.005) {
            return response()->json(['success' => false, 'message' => 'That is the refund already recorded.'], 422);
        }
        // Who bears the penalty: kept from the refund unless it is new (was a full refund).
        $bearer = $change->fault ?: ($validated['bearer'] ?? null);
        if ($newPenalty > 0 && ! $bearer) {
            return response()->json(['success' => false, 'message' => 'The airline now keeps ৳'.number_format($newPenalty, 2).' — choose who bears it.', 'need_bearer' => true], 422);
        }
        if ($bearer === 'agent' && ! $p->agent_id) {
            $bearer = 'company';
        }
        $pnr = $t->ticket_no ?: '#'.$t->id;
        $why = trim(($validated['note'] ?? '') ?: 'airline confirmed a different refund');
        $p->loadMissing(['contractFlight.creator', 'passportHolder']);
        $flight = $p->contractFlight;

        try {
            DB::transaction(function () use ($p, $t, $change, $newRefund, $oldRefund, $newPenalty, $oldPenalty, $bearer, $pnr, $why, $flight) {
                $this->lockPassenger($p);
                $delta = app(TicketPurchaseController::class)->correctRefundAmount($t->fresh(), $newRefund, $why);
                if ($delta === null) {
                    throw new StalePassengerException('Ticket '.$pnr.' was paid through a portal (or was never posted to its seller) — its refund cannot be corrected here yet.');
                }
                $penDelta = round($newPenalty - $oldPenalty, 2);
                $name = $p->passportHolder?->name ?: 'passenger';
                $ref = ($flight?->flight_number ?: 'contract flight').'-'.$p->id.'-C'.$change->id;
                $companyId = TravelCompany::ID;
                $text = 'Refund corrected — '.$pnr.': penalty ৳'.number_format($oldPenalty, 2).' → ৳'.number_format($newPenalty, 2).' — '.$name;
                // Same heads as postChangeCosts' penalty, reversed when the penalty came down.
                $this->postCompanyJournal(ContractFlightPassengerChange::PENALTY_COMPANY_SOURCE, $change->id,
                    $penDelta > 0 ? config('accounts.cf_penalty_company') : config('accounts.ticket_purchase_cost'),
                    $penDelta > 0 ? config('accounts.ticket_purchase_cost') : config('accounts.cf_penalty_company'),
                    abs($penDelta), $text, $ref, $companyId, $p->agent_id ? (int) $p->agent_id : null);
                $change->update([
                    'airline_penalty' => $newPenalty > 0 ? $newPenalty : null,
                    'fault' => $newPenalty > 0 ? $bearer : null,
                    'note' => mb_substr($change->note.' · corrected '.now()->format('d M Y').': refund ৳'.number_format($oldRefund, 2).' → ৳'.number_format($newRefund, 2), 0, 2000),
                ]);
                $this->correctChangeCharge($change->fresh(), 'refund on '.$pnr.' corrected to ৳'.number_format($newRefund, 2));
                $this->recordEvent($p, 'exception', 'refund_corrected', 'Refund corrected',
                    $pnr.' · refund ৳'.number_format($oldRefund, 2).' → ৳'.number_format($newRefund, 2)
                    .' · airline keeps ৳'.number_format($newPenalty, 2).($newPenalty > 0 ? ' — '.($bearer === 'agent' ? 'billed to the agent' : 'borne by us') : '').' · '.$why);
            });
        } catch (StalePassengerException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
        $flight?->syncMoneyFromPassengers();

        return response()->json(['success' => true, 'message' => 'Refund corrected to ৳'.number_format($newRefund, 2).' — the difference was added; nothing was deleted.']);
    }

    /** A change row whose entry was undone: kept as history, posts nothing any more. */
    public const UNDONE_REASON = 'undone';

    /**
     * Undo a wrong lane entry (owner, 2026-10-10: "solve all"). Nothing is deleted —
     * every posting gets a REVERSING row beside it, so each ledger nets to zero:
     *  - Re-Issue (ticket_reissue): the seller's charge rows + their journals reversed,
     *    the agent's line corrected to 0 (correctChangeCharge), the date moved back;
     *  - New ticket after refund (lane_new_ticket): the new PNR unlinked, the fare
     *    difference reversed on both sides, our fee corrected to 0.
     * The change row stays in the register with reason "undone" and its original note.
     * Only the LATEST entry on that ticket can be undone, so nothing after it is left
     * standing on a basis that no longer exists. A refund is not undone here: a cancelled
     * ticket cannot be reopened (owner) — Edit refund corrects its amount.
     */
    public function undoChange(Request $request, string $role, ContractFlightPassenger $contractFlightPassenger)
    {
        $p = $contractFlightPassenger;
        $validated = $request->validate(['change_id' => ['required', 'integer'], 'note' => ['nullable', 'string', 'max:500']]);
        $change = ContractFlightPassengerChange::where('id', (int) $validated['change_id'])->where('contract_flight_passenger_id', $p->id)->first();
        $problem = $change ? $this->undoProblem($p, $change) : 'That entry was not found on this passenger.';
        if ($problem) {
            return response()->json(['success' => false, 'message' => $problem], 422);
        }
        $why = trim($validated['note'] ?? '') ?: 'entered by mistake';
        $p->loadMissing(['contractFlight.creator', 'passportHolder']);
        $flight = $p->contractFlight;
        $isNew = $change->reason === self::LANE_NEW_TICKET_REASON;

        try {
            DB::transaction(function () use ($p, $change, $why, $flight, $isNew) {
                $this->lockPassenger($p);
                if ($change->fresh()->reason === self::UNDONE_REASON) {
                    throw new StalePassengerException('That entry was just undone by someone else. Reload the page.');
                }
                $text = 'Undone — '.$why;
                // 1. Every journal this change posted (seller charge, fare difference, penalty) reversed.
                $entries = JournalEntry::with('items')->whereIn('source', [
                    ContractFlightPassengerChange::REISSUE_FEE_TYPE, ContractFlightPassengerChange::FARE_COMPANY_SOURCE,
                ])->where('source_id', $change->id)->whereNull('reversed_journal_entry_id')->get();
                foreach ($entries as $e) {
                    $rev = $e->replicate(['created_at', 'updated_at']);
                    $rev->fill(['date' => now()->toDateString(), 'entry_time' => now()->format('H:i:s'), 'created_by' => Auth::id(),
                        'description' => mb_substr($text.' · '.$e->description, 0, 250), 'reversed_journal_entry_id' => $e->id]);
                    $rev->save();
                    foreach ($e->items as $it) {
                        JournalItem::create(['journal_entry_id' => $rev->id, 'account_id' => $it->account_id,
                            'debit' => $it->credit, 'credit' => $it->debit, 'note' => mb_substr($text.' · '.$it->note, 0, 250),
                            'party_type' => $it->party_type, 'party_id' => $it->party_id]);
                    }
                }
                // 2. The seller's rows of a Re-Issue (vendor ledger / portal wallet), one reversal each.
                if ($change->kind === 'ticket_reissue') {
                    $tid = (int) (((array) $change->previous_ticket_purchase_ids)[0] ?? 0);
                    foreach ($entries->where('source', ContractFlightPassengerChange::REISSUE_FEE_TYPE) as $e) {
                        $amount = (float) $e->items->sum('debit');
                        $isFare = $e->items->contains(fn ($it) => (int) $it->account_id === (int) Account::where('code', config('accounts.cf_fare_diff_company'))->value('id') && $it->debit > 0);
                        $type = $isFare ? ContractFlightPassengerChange::REISSUE_FARE_TYPE : ContractFlightPassengerChange::REISSUE_FEE_TYPE;
                        if ($e->beneficiary_party_id) {
                            $this->postPartyLedgerRow((int) $e->beneficiary_party_id, ['type' => $type, 'invoice_id' => $tid, 'account_id' => null,
                                'payment_date' => now(), 'reference_no' => $e->reference, 'debit' => 0, 'credit' => $amount,
                                'remarks' => mb_substr($text.' · '.$e->description, 0, 250)]);
                        } elseif ($t = TicketPurchase::find($tid)) {
                            if ($t->portal_id && ($portal = Portal::find($t->portal_id))) {
                                $opening = (float) $portal->balance;
                                PortalBalance::create(['invoice_id' => $tid, 'portal_id' => $portal->id, 'type' => $type,
                                    'old_balance' => $opening, 'debit' => $amount, 'credit' => 0, 'current_balance' => $opening + $amount,
                                    'remarks' => mb_substr($text.' · '.$e->description, 0, 250), 'created_by' => Auth::id()]);
                                $portal->update(['balance' => $opening + $amount]);
                            }
                        }
                    }
                    // the date goes back to what it was before this re-issue
                    if ($tid && ($t = TicketPurchase::find($tid)) && $t->ticket_no
                        && preg_match('/'.preg_quote($t->ticket_no, '/').'\s+(\d{4}-\d{2}-\d{2}[^→,·]*?)\s*→\s*(\d{4}-\d{2}-\d{2})/u', (string) $change->note, $m)
                        && substr((string) $t->travelAt(), 0, 10) === $m[2]) {
                        $t->moveTravelDate(trim($m[1]));
                    }
                }
                // 3. A New ticket: the PNR comes off him, the fare difference to the agent reversed.
                if ($isNew) {
                    if (preg_match('/^New ticket (\S+(?: \([a-z]{1,2}\))?) after refund/u', (string) $change->note, $m)
                        && ($new = $p->ticketPurchases()->where('ticket_purchases.ticket_no', $m[1])->first())) {
                        $p->ticketPurchases()->detach($new->id);
                    }
                    $fareNet = (float) Transaction::where('type', ContractFlightPassengerChange::FARE_LEDGER_TYPE)
                        ->where('invoice_id', $change->id)->selectRaw('COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) n')->value('n');
                    if (abs($fareNet) >= 0.005 && $p->agent_id) {
                        $this->postAgentAdjustment((int) $p->agent_id, ContractFlightPassengerChange::FARE_LEDGER_TYPE, $change->id, -$fareNet,
                            $text.' — fare difference — '.($p->passportHolder?->name ?: 'passenger'),
                            ($flight?->flight_number ?: 'contract flight').'-'.$p->id.'-C'.$change->id, config('accounts.cf_fare_diff_recovered'),
                            TravelCompany::ID);
                    }
                }
                // 4. The register row: kept, marked undone, posting nothing — then the agent's line follows it to 0.
                $change->update([
                    'reason' => self::UNDONE_REASON,
                    'charge' => null,
                    'fare_difference' => $isNew ? 0 : $change->fare_difference,
                    'note' => mb_substr($change->note.' · UNDONE '.now()->format('d M Y').' — '.$why, 0, 2000),
                ]);
                $this->correctChangeCharge($change->fresh(), 'entry undone — '.$why);
                $this->recordEvent($p, 'exception', 'undone', 'Entry undone', mb_substr((string) $change->note, 0, 200));
            });
        } catch (StalePassengerException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
        $p->load('ticketPurchases');
        if ($back = $p->stepBackIfUnticketed()) {
            $this->recordEvent($p, 'stage', $back, ContractFlightPassenger::stageLabel($back), 'New ticket undone — issue the new one');
        }
        $flight?->syncMoneyFromPassengers();

        return response()->json(['success' => true, 'message' => 'Undone. Reversing rows were added beside the originals — nothing was deleted.']);
    }

    /** Why this change cannot be undone, or null. Shared with lanes() so the button shows only when it would work. */
    private function undoProblem(ContractFlightPassenger $p, ContractFlightPassengerChange $c): ?string
    {
        if ($c->reason === self::UNDONE_REASON) {
            return 'That entry is already undone.';
        }
        $isNew = $c->reason === self::LANE_NEW_TICKET_REASON;
        if ($c->kind !== 'ticket_reissue' && ! $isNew) {
            return 'Only a Re-Issue or a New ticket can be undone here.';
        }
        $ids = array_map('intval', (array) ($c->previous_ticket_purchase_ids ?? []));
        if (count($ids) !== 1) {
            return 'This entry covers several tickets — it cannot be undone here.';
        }
        // the latest entry naming that ticket
        $later = ContractFlightPassengerChange::where('contract_flight_passenger_id', $p->id)->where('id', '>', $c->id)
            ->where(fn ($q) => $q->whereNull('reason')->orWhere('reason', '!=', self::UNDONE_REASON))->get()
            ->first(fn ($x) => in_array($ids[0], array_map('intval', (array) ($x->previous_ticket_purchase_ids ?? [])), true));
        if ($later) {
            return 'A later entry on this ticket exists — undo that one first.';
        }
        if ($c->kind === 'ticket_reissue') {
            $t = TicketPurchase::find($ids[0]);
            if (! $t || $t->status === 'cancelled') {
                return 'That ticket has been refunded since — the re-issue can no longer be undone.';
            }
        }
        if ($isNew && preg_match('/^New ticket (\S+(?: \([a-z]{1,2}\))?) after refund/u', (string) $c->note, $m)) {
            $new = $p->ticketPurchases()->where('ticket_purchases.ticket_no', $m[1])->first();
            if (! $new) {
                return 'The new ticket is no longer linked to him.';
            }
            $after = ContractFlightPassengerChange::where('contract_flight_passenger_id', $p->id)->where('id', '>', $c->id)->get()
                ->contains(fn ($x) => in_array((int) $new->id, array_map('intval', (array) ($x->previous_ticket_purchase_ids ?? [])), true)
                    && $x->reason !== self::UNDONE_REASON);
            if ($after) {
                return 'The new ticket has its own later entries — undo those first.';
            }
        }

        return null;
    }

    public function moveFlight(Request $request, string $role, ContractFlightPassenger $contractFlightPassenger)
    {
        $validated = $request->validate([
            'contract_flight_id' => ['required', 'exists:contract_flights,id', function ($attribute, $value, $fail) use ($contractFlightPassenger) {
                if ((int) $value === (int) $contractFlightPassenger->contract_flight_id) {
                    $fail('The passenger is already on that flight.');
                }
                // An off-flight passenger goes back through Re-assign, which
                // clears his flag and restores his charge; a bare move would
                // leave him voided on a flight.
                if ($contractFlightPassenger->contract_flight_id === null) {
                    $fail('This passenger is under Cancelled — use Re-assign.');
                }
                // Re-assign replaced the bare move wherever it is installed:
                // it keeps to open flights on his route and records the move.
                if (ContractFlightPassengerChange::available()) {
                    $fail('Use Re-assign on his row to move him to another flight.');
                }
                // One row per passport per flight (unique key) — say so
                // instead of failing on the database.
                if (ContractFlightPassenger::where('contract_flight_id', (int) $value)
                    ->where('passport_holder_id', $contractFlightPassenger->passport_holder_id)->exists()) {
                    $fail('This passport is already on that flight.');
                }
            }],
        ]);

        DB::transaction(function () use ($validated, $contractFlightPassenger) {
            $contractFlightPassenger->loadMissing(['contractFlight', 'passportHolder']);
            $oldFlight = $contractFlightPassenger->contractFlight;
            $newFlight = ContractFlight::findOrFail($validated['contract_flight_id']);

            $contractFlightPassenger->update(['contract_flight_id' => $newFlight->id]);

            // Text-only refresh — the amounts and balances on these rows are
            // untouched, only the flight number they mention.
            $name = $contractFlightPassenger->passportHolder?->name ?: 'passenger';
            Transaction::where('invoice_id', $contractFlightPassenger->id)
                ->whereIn('type', [ContractFlightPassenger::AGENT_LEDGER_TYPE, ContractFlightPassenger::REISSUE_LEDGER_TYPE])
                ->where('debit', '>', 0)
                ->get()
                ->each(function (Transaction $row) use ($newFlight, $name) {
                    $isReissue = $row->type === ContractFlightPassenger::REISSUE_LEDGER_TYPE;
                    $row->update([
                        'remarks' => ($isReissue ? 'Reissue cost billed to agent — ' : 'Charged to agent — ')
                            .$name.' ('.$newFlight->flight_number.')',
                    ]);
                });

            $this->recordEvent(
                $contractFlightPassenger, 'note', 'moved_flight', 'Moved to Another Flight',
                'From '.($oldFlight?->flight_number ?: 'no flight').' to '.$newFlight->flight_number
            );

            $oldFlight?->syncMoneyFromPassengers();
            $newFlight->syncMoneyFromPassengers();
        });

        return response()->json([
            'success' => true,
            'message' => 'Passenger moved to the new flight.',
            'passenger' => $this->present($contractFlightPassenger->fresh(['passportHolder', 'agent', 'contractFlight', 'flightCategory.categoryType', 'ticketPurchases', 'serviceCosts.vendor', 'serviceCosts.bank', 'events.creator'])),
        ]);
    }

    /** Ticket purchases for this passenger's own passport holder — never a stranger's. */
    public function searchTicketPurchases(Request $request, string $role, ContractFlightPassenger $contractFlightPassenger)
    {
        $linked = $contractFlightPassenger->ticketPurchases()->pluck('ticket_purchases.id');

        $matches = TicketPurchase::where('passport_holder_id', $contractFlightPassenger->passport_holder_id)
            ->where('company_id', TravelCompany::ID)
            ->whereNotIn('id', $linked)
            ->when($request->filled('q'), fn ($q) => $q->where('ticket_no', 'like', '%'.$request->input('q').'%'))
            ->orderByDesc('purchase_date')
            ->limit(20)
            ->get(['id', 'ticket_no', 'status', 'amount', 'purchase_date']);

        return response()->json(['success' => true, 'ticket_purchases' => $matches]);
    }

    public function linkTicket(Request $request, string $role, ContractFlightPassenger $contractFlightPassenger)
    {
        $validated = $request->validate([
            'ticket_purchase_id' => [
                'required',
                Rule::exists('ticket_purchases', 'id')
                    ->where('passport_holder_id', $contractFlightPassenger->passport_holder_id)
                    ->where('company_id', TravelCompany::ID),
            ],
        ], [
            'ticket_purchase_id.exists' => 'That ticket purchase does not belong to this passenger.',
        ]);

        if ($problem = self::ticketLinkProblem($contractFlightPassenger, (int) $validated['ticket_purchase_id'])) {
            return response()->json(['success' => false, 'message' => $problem], 422);
        }

        return DB::transaction(function () use ($contractFlightPassenger, $validated) {
            $contractFlightPassenger->ticketPurchases()->syncWithoutDetaching([
                $validated['ticket_purchase_id'] => ['linked_by' => Auth::id()],
            ]);
            app(TicketBaggage::class)->forPassenger($contractFlightPassenger);
            ContractFlightAgentReceipts::sync((int) $contractFlightPassenger->agent_id);
            // A reissue left with its fare difference blank settles here, once
            // his new ticket(s) are all linked.
            $this->settlePendingFareDifference($contractFlightPassenger);

            // Ticketing is done once every leg's PNR is linked (DAC → ECN is two
            // tickets) — then advance the stage so the tracker reflects it without
            // a second, separate dropdown update. Never regresses (see
            // advanceStageTo()), so this is safe even for a reissue's 2nd PNR.
            $contractFlightPassenger->load('ticketPurchases');
            $moved = $contractFlightPassenger->syncDerivedStage();
            if ($moved) {
                $this->recordEvent($contractFlightPassenger, 'stage', $moved, ContractFlightPassenger::stageLabel($moved), 'Reached automatically from the details entered');
            }
            // Ticket news goes out whether or not the stage moved: a second PNR
            // on a reissue is still a ticket the passenger needs to know about.
            ContractFlightMessenger::auto('ticket_issued', $contractFlightPassenger->fresh(['ticketPurchases', 'passportHolder', 'agent', 'contractFlight']));
            $contractFlightPassenger->contractFlight?->syncMoneyFromPassengers();

            return response()->json([
                'success' => true,
                'message' => 'Ticket linked.',
                'passenger' => $this->present($contractFlightPassenger->fresh(['passportHolder', 'agent', 'flightCategory.categoryType', 'ticketPurchases'])),
            ]);
        });
    }

    /**
     * Why this PNR cannot be linked to this passenger, or null. "Fully
     * ticketed" counts confirmed PNRs against tickets_required (and its SQL
     * twin does the same), so a link that is not a real, separate leg would
     * make a passenger look ticketed while a leg is missing — or put one
     * ticket's cost on two passengers. Shared with the flight builder.
     */
    public static function ticketLinkProblem(ContractFlightPassenger $p, int $ticketPurchaseId, array $alsoLinked = []): ?string
    {
        $t = TicketPurchase::with('legs')->find($ticketPurchaseId);
        if (! $t) {
            return null;
        }
        $pnr = $t->ticket_no ?: ('#'.$t->id);
        if ((int) $t->company_id !== TravelCompany::ID) {
            return 'Ticket '.$pnr.' does not belong to EPAL TRAVELS & CONSULTANCY.';
        }
        if ($t->status === 'cancelled') {
            return 'Ticket '.$pnr.' is cancelled — it cannot cover a leg.';
        }
        $other = DB::table('contract_flight_passenger_tickets')
            ->where('ticket_purchase_id', $t->id)->where('contract_flight_passenger_id', '!=', $p->id ?: 0)->exists();
        if ($other) {
            return 'Ticket '.$pnr.' is already linked to another contract flight passenger record.';
        }
        $sector = ContractFlightPassenger::ticketSector($t);
        if ($sector && $t->status === 'confirm') {
            $held = ($p->id ? $p->ticketPurchases()->with('legs')->where('ticket_purchases.status', 'confirm')->where('ticket_purchases.id', '!=', $t->id)->get() : collect())
                ->merge(TicketPurchase::with('legs')->whereIn('id', array_diff($alsoLinked, [$t->id]))->where('status', 'confirm')->get());
            $dup = $held->first(fn ($h) => ContractFlightPassenger::ticketSector($h) === $sector);
            if ($dup) {
                return 'Ticket '.$pnr.' covers '.$sector.', which ticket '.($dup->ticket_no ?: '#'.$dup->id)
                    .' already covers. Refund or re-issue that one instead of linking a second ticket for the same leg.';
            }
        }

        return null;
    }

    public function unlinkTicket(Request $request, string $role, ContractFlightPassenger $contractFlightPassenger, TicketPurchase $ticketPurchase)
    {
        $contractFlightPassenger->ticketPurchases()->detach($ticketPurchase->id);
        $contractFlightPassenger->contractFlight?->syncMoneyFromPassengers();

        return response()->json([
            'success' => true,
            'message' => 'Ticket unlinked.',
            'passenger' => $this->present($contractFlightPassenger->fresh(['passportHolder', 'agent', 'flightCategory.categoryType', 'ticketPurchases'])),
        ]);
    }

    /**
     * Every PNR already on file for one passport holder, for the Add/Edit
     * Flight modal's per-passenger ticket picker.
     *
     * searchTicketPurchases() above does the same job once a
     * ContractFlightPassenger row exists. It cannot serve the Add Flight
     * modal, where the flight — and therefore the passenger — has not been
     * saved yet and only a passport holder has been chosen. Both are fenced
     * to one holder's own purchases; neither can surface a stranger's PNR.
     */
    public function holderTicketPurchases(Request $request, string $role, PassportHolder $passportHolder)
    {
        $matches = TicketPurchase::with(['ticket', 'vendor', 'portal', 'bank', 'legs', 'schedules'])
            ->where('passport_holder_id', $passportHolder->id)
            ->where('company_id', TravelCompany::ID)
            ->when($request->filled('q'), fn ($q) => $q->where('ticket_no', 'like', '%'.$request->input('q').'%'))
            ->orderByDesc('purchase_date')
            ->limit(20)
            ->get();

        // Their tickets that exist in Ticket Orders but are not purchased yet
        // (holds, drafts, pending). Guarded: the bookings table may not exist
        // on a lagging server, and that must not break PNR lookup.
        $holds = collect();
        try {
            $holds = TicketBooking::open()->where('passport_holder_id', $passportHolder->id)
                ->where('company_id', TravelCompany::ID)
                ->with(['ticket', 'vendor', 'portal', 'fromAirport', 'toAirport'])->orderByDesc('id')->limit(20)->get()
                ->map(fn ($b) => $b->flightHoldDetails($role))->values();
        } catch (\Throwable) {
            $holds = collect();
        }

        return response()->json([
            'success' => true,
            'ticket_purchases' => $matches->map(fn (TicketPurchase $t) => $t->flightTicketDetails())->values(),
            'ticket_bookings' => $holds,
        ]);
    }

    /**
     * Is this passport waiting under Cancelled? For the passenger builder
     * (owner, 2026-09-26): picking his passport on a new flight's card
     * fills the card from his previous one — "just search his name and all
     * info will be filled, just the new ticket has to be done again" — and
     * the card then carries rebook_passenger_id so the save moves that
     * passenger here instead of creating a second one. Null when he is not
     * off a flight, or the feature is not installed.
     */
    public function offFlightForHolder(Request $request, string $role, PassportHolder $passportHolder)
    {
        if (! ContractFlightPassengerChange::available()) {
            return response()->json(['success' => true, 'passenger' => null]);
        }
        $p = ContractFlightPassenger::whereNull('contract_flight_id')
            ->where('exception_flag', ContractFlightPassenger::OFF_FLIGHT_FLAG)
            ->where('passport_holder_id', $passportHolder->id)
            ->with(['passportHolder:id,name,passport_no,phone', 'agent:id,name', 'flightCategory.categoryType', 'ticketPurchases.ticket', 'ticketPurchases.vendor', 'ticketPurchases.portal', 'ticketPurchases.bank', 'ticketPurchases.legs', 'ticketPurchases.schedules', 'serviceCosts.vendor:id,name', 'serviceCosts.bank:id,name', 'events.creator:id,name', 'lastCancellation.fromFlight'])
            ->latest('updated_at')
            ->first();
        if (! $p) {
            return response()->json(['success' => true, 'passenger' => null]);
        }
        $cancel = $p->lastCancellation;

        return response()->json(['success' => true, 'passenger' => $this->present($p) + ['rebook' => [
            'passenger_id' => $p->id,
            'from_flight' => $cancel?->fromFlight?->flight_number,
            'reason' => $cancel?->reason ? (ContractFlightPassengerChange::CANCEL_REASONS[$cancel->reason] ?? $cancel->reason) : null,
            'reason_key' => $cancel?->reason,
            'at' => $cancel?->created_at?->format('d M Y'),
            'live_tickets' => $p->ticketPurchases->where('status', '!=', 'cancelled')->pluck('ticket_no')->filter()->values(),
            'cancelled_tickets' => $p->ticketPurchases->where('status', 'cancelled')->pluck('ticket_no')->filter()->values(),
        ]]]);
    }

    private function present(ContractFlightPassenger $p): array
    {
        return [
            'id' => $p->id,
            'contract_flight_id' => $p->contract_flight_id,
            'flight_number' => $p->contractFlight?->flight_number,
            'passport_holder_id' => $p->passport_holder_id,
            'ticket_class' => $p->ticket_class,
            'ticket_class_label' => ContractFlightPassenger::TICKET_CLASSES[$p->ticket_class] ?? $p->ticket_class,
            'handling_type' => $p->handling_type,
            'handling_label' => ContractFlightPassenger::HANDLING_TYPES[$p->handling_type] ?? $p->handling_type,
            'flight_category_id' => $p->flight_category_id,
            'flight_category_name' => $p->flightCategory?->name,
            // Type is read through the category rather than stored twice.
            'flight_category_type' => $p->flightCategory?->categoryType?->name,
            'name' => $p->passportHolder?->name,
            'passport_no' => $p->passportHolder?->passport_no,
            'phone' => $p->phone,
            'stage' => $p->stage,
            'stage_label' => ContractFlightPassenger::stageLabel($p->stage),
            'exception_flag' => $p->exception_flag,
            'exception_label' => $p->exception_flag ? (ContractFlightPassenger::EXCEPTION_FLAGS[$p->exception_flag] ?? $p->exception_flag) : null,
            'contract_confirmed' => (bool) $p->contract_confirmed_at,
            'contract_confirmed_at' => $p->contract_confirmed_at?->toDateTimeString(),
            'has_bmet' => (bool) $p->has_bmet,
            'has_manpower_card' => (bool) $p->has_manpower_card,
            'boarding_training' => (bool) ($p->boarding_training ?? true),
            'manpower_card_fee' => $p->manpower_card_fee,
            'agent_id' => $p->agent_id,
            'agent_name' => $p->agent?->name,
            'agent_price' => $p->agent_price,
            'agent_paid_amount' => $p->agent_paid_amount,
            'agent_due' => $p->agent_due,
            // Penalties for moves between flights (2026-09-27).
            'penalty_billed' => $p->penaltyTotals()['billed'],
            'penalty_borne' => $p->penaltyTotals()['borne'],
            'penalty_parts' => $p->penaltyTotals()['parts'] ?? null,
            'previous_flights' => $p->previousFlights(),
            'reissue_money' => $p->reissueMoney(),
            'agent_total' => $p->agent_total,
            'package_charged' => $p->package_charged,
            'baggage_cost' => $p->baggageTotals()['cost'],
            'baggage_charge' => $p->baggageTotals()['charge'],
            'manpower_cost' => $p->manpower_cost,
            'boarding_cost' => $p->boarding_cost,
            'immigration_cost' => $p->immigration_cost,
            'other_title' => $p->other_title,
            'other_cost' => $p->other_cost,
            'service_costs' => $p->serviceCosts->mapWithKeys(fn ($cost) => [
                $cost->cost_type => [
                    'id' => $cost->id,
                    'cost_type' => $cost->cost_type,
                    'title' => $cost->title,
                    'amount' => $cost->amount,
                    'vendor_id' => $cost->vendor_id,
                    'vendor_name' => $cost->vendor?->name,
                    'payment_status' => $cost->payment_status,
                    'paid_amount' => $cost->paid_amount,
                    'due_amount' => $cost->due_amount,
                    'bank_id' => $cost->bank_id,
                    'bank_name' => $cost->bank?->name,
                    'incurred_on' => $cost->incurred_on?->toDateString(),
                    'payment_date' => $cost->payment_date?->toDateString(),
                    'due_date' => $cost->due_date?->toDateString(),
                    'reference_no' => $cost->reference_no,
                ],
            ]),
            'reissue_cost' => $p->reissue_cost,
            'reissue_bearer' => $p->reissue_bearer,
            'reissue_bearer_label' => $p->reissue_bearer ? (ContractFlightPassenger::REISSUE_BEARERS[$p->reissue_bearer] ?? $p->reissue_bearer) : null,
            'contact_phone' => $p->contact_phone,
            'tickets_required' => $p->ticketsRequired(),
            'ticket_slots' => $p->ticketSlots(),
            'tickets_confirmed' => $p->confirmedTicketCount(),
            'remarks' => $p->remarks,
            'document_statuses' => $p->document_statuses,
            'ticket_purchases' => $p->ticketPurchases->map(fn (TicketPurchase $t) => $t->flightTicketDetails())->values(),
            'off_flight' => $p->contract_flight_id === null,
            'flight_changes' => $this->flightChanges($p),
            // Both computed on the model (ContractFlightPassenger) so the
            // flight-level totals and this per-passenger view never drift.
            'total_travel_cost' => $p->total_travel_cost,
            'profit' => $p->profit,
            'journey_events' => $p->events->map(fn ($event) => [
                'id' => $event->id, 'type' => $event->type, 'code' => $event->code,
                'label' => $event->label, 'note' => $event->note,
                'occurred_at' => $event->occurred_at?->toIso8601String(),
                'created_by' => $event->creator?->name,
            ])->values(),
        ];
    }

    /** His moves between flights, newest first — empty until the changes migration has run. */
    private function flightChanges(ContractFlightPassenger $p): array
    {
        if (! ContractFlightPassengerChange::installed()) {
            return [];
        }
        try {
            $p->loadMissing(['flightChanges.fromFlight:id,flight_number', 'flightChanges.toFlight:id,flight_number', 'flightChanges.creator:id,name']);

            return $p->flightChanges->map->toCard()->values()->all();
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    private function recordEvent(ContractFlightPassenger $passenger, string $type, ?string $code, string $label, ?string $note = null, $occurredAt = null): void
    {
        $passenger->events()->create([
            'type' => $type, 'code' => $code, 'label' => $label, 'note' => $note,
            'occurred_at' => $occurredAt ?: now(), 'created_by' => Auth::id(),
        ]);
    }
}
