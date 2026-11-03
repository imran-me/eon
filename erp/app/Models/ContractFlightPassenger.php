<?php

namespace App\Models;

use App\Services\FlightPackageTicketBilling;
use App\Services\PartyDiscountService;
use App\Services\ReturnCancel;
use App\Traits\HasPartyDiscounts;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ContractFlightPassenger extends Model
{
    use HasPartyDiscounts;

    protected array $packageBillingTickets = [];

    protected static function booted(): void
    {
        static::saved(fn (self $passenger) => app(FlightPackageTicketBilling::class)->forPassenger($passenger));
        static::deleting(function (self $passenger) {
            $passenger->packageBillingTickets = $passenger->ticketPurchases()->pluck('ticket_purchases.id')->all();
        });
        static::deleted(function (self $passenger) {
            foreach ($passenger->packageBillingTickets as $ticketId) {
                app(FlightPackageTicketBilling::class)->forTicket((int) $ticketId);
            }
        });
    }

    /** The normal operational journey. Incidents and money are separate. */
    public const STAGES = [
        'passenger_received' => 'Passenger Received',
        'information_entered' => 'Information Entered',
        'documents_received' => 'Documents Received',
        'documents_verified' => 'Documents Verified',
        'service_defined' => 'Service / Category Defined',
        'route_confirmed' => 'Route Confirmed',
        'ticket_issued' => 'Ticket Issued',
        'ready_for_travel' => 'Ready for Travel',
        'confirmation_call' => 'Confirmation Call',
        'preflight_coordination' => 'Pre-flight Coordination',
        'airport_processing' => 'Airport Processing',
        'immigration' => 'Immigration',
        'boarding' => 'Boarding',
        'departed' => 'Departed',
        'transit' => 'Transit Handling',
        'landed' => 'Landed',
        'arrival_confirmed' => 'Arrival Confirmed',
        'case_closed' => 'Case Closed',
    ];

    public const CONDITIONAL_STAGES = ['transit'];

    /** Stages that mean the person has flown — counted as Departed on the KPI cards. */
    public const FLOWN_STAGES = ['departed', 'transit', 'landed', 'arrival_confirmed', 'case_closed'];

    /**
     * The live pipeline, editable from the Flight Dashboard. STAGES above is
     * the shipped default and the fallback for any install where the
     * contract_flight_stages migration has not run yet — a missing table
     * must never break a passenger screen. Resolved once per request.
     */
    private static ?array $stageRouteCache = null;

    /** Ordered rows: id, key, label, icon, sort_order, is_conditional, is_system. */
    public static function stageRoute(bool $fresh = false): array
    {
        if ($fresh || self::$stageRouteCache === null) {
            $rows = [];
            try {
                if (Schema::hasTable('contract_flight_stages')) {
                    $rows = ContractFlightStage::orderBy('sort_order')->orderBy('id')->get()
                        ->map(fn ($s) => [
                            'id' => $s->id, 'key' => $s->key, 'label' => $s->label, 'icon' => $s->icon,
                            'sort_order' => $s->sort_order, 'is_conditional' => (bool) $s->is_conditional,
                            'is_system' => (bool) $s->is_system,
                        ])->all();
                }
            } catch (\Throwable) {
                $rows = [];
            }
            self::$stageRouteCache = $rows ?: ContractFlightStage::defaultRows();
        }

        return self::$stageRouteCache;
    }

    /** key => label, in pipeline order — the drop-in for the old STAGES constant. */
    public static function stages(): array
    {
        return array_column(self::stageRoute(), 'label', 'key');
    }

    public static function stageLabel(?string $key): string
    {
        return self::stages()[$key] ?? ucfirst(str_replace('_', ' ', (string) $key));
    }

    public const EVENT_TYPES = [
        'rescheduled' => 'Flight Rescheduled', 'time_changed' => 'Flight Time Changed',
        'flight_cancelled' => 'Flight Cancelled', 'flight_missed' => 'Flight Missed',
        'ticket_cancelled' => 'Ticket Cancelled', 'reissue' => 'Ticket Reissued',
        'refund_requested' => 'Refund Requested', 'refund_received' => 'Refund Received',
        'immigration_issue' => 'Immigration Issue', 'boarding_denied' => 'Boarding Denied',
        'transit_issue' => 'Transit Issue', 'deported' => 'Deported', 'note' => 'Operational Note',
        'moved_flight' => 'Moved to Another Flight',
    ];

    /**
     * Transaction::type used when an agent's price is posted to their Party
     * Statement ledger — kept distinct from ManageFlightController's
     * 'contract_flight' (the flight batch's own vendor cost).
     */
    public const AGENT_LEDGER_TYPE = 'contract_flight_passenger_agent';

    /**
     * Transaction::type of a package reversal (owner, 2026-09-28: nothing is
     * deleted or zeroed on a cancel). The package row keeps its amount; a
     * cancel/deport ADDS a credit row of this type, and putting him back on a
     * flight adds a debit row that charges it again. The agent's net for a
     * passenger = package row − Σ(credit − debit) of these rows.
     */
    public const AGENT_REVERSAL_TYPE = 'contract_flight_passenger_agent_reversal';

    /**
     * Transaction::type used when a reissue is billed to the agent —
     * distinct from AGENT_LEDGER_TYPE so the two charges reconcile
     * independently (moving/clearing one never touches the other's row).
     * Read by ReconcilesContractFlightReissue.
     */
    public const REISSUE_LEDGER_TYPE = 'contract_flight_passenger_reissue';

    /**
     * Who pays for a reissue — set once someone has judged whose fault it
     * was. Null means "recorded, not judged yet": reissue_cost can be filled
     * in before anyone decides who bears it.
     */
    public const REISSUE_BEARERS = [
        'company' => 'We bear it',
        'agent' => 'Bill the agent',
    ];

    /**
     * Overlay flags — not part of the stage sequence, can attach at any stage
     * (a flight can be rescheduled while a passenger sits in Ticketing; a
     * deportation only ever happens after Entry).
     */
    public const EXCEPTION_FLAGS = [
        'flight_cancelled' => 'Flight Cancelled',
        'flight_missed' => 'Flight Missed',
        'rescheduled' => 'Rescheduled',
        'time_changed' => 'Time Changed',
        'transit' => 'Transit',
        'boarding_denied' => 'Boarding Denied',
        // Stopped by immigration at departure (owner, 2026-10-07).
        'immigration_offload' => 'Immigration Offload',
        'deported' => 'Deported',
        // Deported, and on OUR side: we bear it, the package is reversed
        // (owner, 2026-09-28: "depends on the reason — the agent may repay
        // and case close, or we bear"). Plain 'deported' = the agent pays.
        self::DEPORTED_BORNE_FLAG => 'Deported — we bear it',
        self::OFF_FLIGHT_FLAG => 'Cancelled — off flight',
    ];

    /**
     * The flag of a passenger taken off his flight (owner, 2026-09-26):
     * contract_flight_id is null and he waits under Cancelled to be put on
     * another flight. Set and cleared ONLY by ContractFlightRebookController —
     * the edit modal never offers it, and update() will not set or clear it,
     * so the flag and "has no flight" can never disagree.
     */
    public const OFF_FLIGHT_FLAG = 'pax_cancelled';

    /**
     * Exception flags that void what the agent is charged. Rescheduled and
     * Transit are deliberately absent — the passenger still travels on our
     * arrangement, so the agent still owes for him. Read by
     * ReconcilesContractFlightAgent when it posts the Party Statement row.
     */
    public const AGENT_CHARGE_VOIDING_FLAGS = ['cancelled', 'flight_cancelled', self::DEPORTED_BORNE_FLAG, self::OFF_FLIGHT_FLAG];

    /** See EXCEPTION_FLAGS — a deportation the company bears. */
    public const DEPORTED_BORNE_FLAG = 'deported_we_bear';

    /** Offloads that happen at departure in our country. */
    public const OFFLOAD_KPI_FLAGS = ['boarding_denied', 'immigration_offload'];

    /** Flags shown on the Flights & Passengers Deported KPI. */
    public const DEPORTED_KPI_FLAGS = ['deported', self::DEPORTED_BORNE_FLAG];

    /** Cabin classes a passenger can be ticketed in. */
    public const TICKET_CLASSES = [
        'economy' => 'Economy',
        'business' => 'Business',
        'first' => 'First Class',
    ];

    /**
     * How much of the departure we handle for this person. Decided per
     * passenger: one agent's group routinely mixes both, so the batch only
     * carries a default.
     */
    public const HANDLING_TYPES = [
        'manpower_wise' => 'Boarding only',
        'immigration_wise' => 'Boarding + immigration',
    ];

    protected $fillable = [
        'contract_flight_id',
        'passport_holder_id',
        'ticket_class',
        'handling_type',
        'flight_category_id',
        'document_statuses',
        'stage',
        'exception_flag',
        'contract_confirmed_at',
        'has_bmet',
        'has_manpower_card',
        'boarding_training',
        'manpower_card_fee',
        'agent_id',
        'agent_price',
        'agent_paid_amount',
        'agent_paid_at',
        'manpower_cost',
        'boarding_cost',
        'immigration_cost',
        'other_title',
        'other_cost',
        'reissue_cost',
        'reissue_bearer',
        'contact_phone',
        'remarks',
        'tickets_required',
    ];

    protected $casts = [
        'document_statuses' => 'array',
        'contract_confirmed_at' => 'datetime',
        'has_bmet' => 'boolean',
        'has_manpower_card' => 'boolean',
        'boarding_training' => 'boolean',
        'manpower_card_fee' => 'float',
        'agent_price' => 'float',
        'agent_paid_amount' => 'float',
        'agent_paid_at' => 'datetime',
        'manpower_cost' => 'float',
        'boarding_cost' => 'float',
        'immigration_cost' => 'float',
        'other_cost' => 'float',
        'reissue_cost' => 'float',
    ];

    public function contractFlight(): BelongsTo
    {
        return $this->belongsTo(ContractFlight::class);
    }

    public function passportHolder(): BelongsTo
    {
        return $this->belongsTo(PassportHolder::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    /**
     * Why this person is flying — Cyprus Work Permit, Umrah, and so on. Its
     * type comes through the category itself (FlightCategory owns
     * flight_category_type_id), so no second column can drift from it.
     */
    public function flightCategory()
    {
        return $this->belongsTo(FlightCategory::class, 'flight_category_id');
    }

    /**
     * Every PNR linked to this passenger. Deliberately many — a passenger can
     * carry more than one purchase at once (legs booked separately, or an old
     * PNR still open while a reissue is in flight).
     */
    /**
     * What his package was lowered by when returns were cancelled (2026-10-14,
     * ContractFlightPassengerChange::price_revision). The package row keeps the
     * quoted price; the reduction is its own credit row, so every total that
     * reads agent_price takes this off. 0 before the migration.
     */
    public function priceRevision(): float
    {
        if (! ReturnCancel::ready()) {
            return 0.0;
        }
        // package_charged is read on every list: one query per request names the few
        // passengers with any revision; everyone else answers 0 without a query.
        self::$revisedIds ??= ContractFlightPassengerChange::whereNotNull('price_revision')->pluck('contract_flight_passenger_id')->flip()->all();
        if (! isset(self::$revisedIds[$this->id])) {
            return 0.0;
        }

        // the loaded change rows when a reader already has them (CompanyFunds), else one query
        return (float) ($this->relationLoaded('flightChanges')
            ? $this->flightChanges->sum('price_revision')
            : $this->flightChanges()->reorder()->sum('price_revision'));
    }

    /** Passengers with any price revision, for this request (priceRevision). */
    private static ?array $revisedIds = null;

    /** After posting a revision: the next read looks again. */
    public static function forgetRevisions(): void
    {
        self::$revisedIds = null;
    }

    /** The package after any return-cancellation revision — what the flight earns from him. */
    public function chargedPackage(): float
    {
        return round(max(0.0, (float) ($this->agent_price ?? 0) - $this->priceRevision()), 2);
    }

    /** @return BelongsToMany<TicketPurchase, $this> */
    public function ticketPurchases(): BelongsToMany
    {
        return $this->belongsToMany(TicketPurchase::class, 'contract_flight_passenger_tickets')
            ->using(ContractFlightPassengerTicket::class)
            ->withPivot(['linked_by'])
            ->withTimestamps();
    }

    public function events()
    {
        return $this->hasMany(ContractFlightPassengerEvent::class)
            ->orderByDesc('occurred_at')->orderByDesc('id');
    }

    /** Vendor/payment metadata for each non-ticket service cost on this passenger. */
    public function serviceCosts(): HasMany
    {
        return $this->hasMany(ContractFlightPassengerServiceCost::class);
    }

    /** Every move between flights, newest first. Read only after ContractFlightPassengerChange::installed(). */
    public function flightChanges()
    {
        return $this->hasMany(ContractFlightPassengerChange::class)->orderByDesc('id');
    }

    /** The move that took him off his last flight — where he came from, and why. */
    public function lastCancellation()
    {
        return $this->hasOne(ContractFlightPassengerChange::class)->ofMany(['id' => 'max'], fn ($q) => $q->where('kind', 'cancelled'));
    }

    /** Taken off his flight and waiting under Cancelled. */
    public function isOffFlight(): bool
    {
        return $this->contract_flight_id === null && $this->exception_flag === self::OFF_FLIGHT_FLAG;
    }

    /**
     * Which Flights & Passengers KPI card this passenger belongs to, for the
     * row tint on that page — the first match in the order below wins, so
     * an exception outranks money which outranks progress. Mirrors
     * ManageFlightController::passengerKpiScopes(); change one, change the
     * other, or the tint and the card count will disagree. Reads only
     * loaded relations (contractFlight, ticketPurchases).
     */
    public function kpiKey(): ?string
    {
        $flightStatus = $this->contractFlight?->status;
        $flag = $this->exception_flag;
        $flown = $flightStatus === 'departed' || in_array($this->stage, self::FLOWN_STAGES, true);
        $live = in_array($flightStatus, ['open', 'boarding'], true) && ! $flown;
        $ticketed = $this->isFullyTicketed();
        $voided = in_array($flag, self::AGENT_CHARGE_VOIDING_FLAGS, true);

        if ($flag === 'flight_cancelled' || $flag === self::OFF_FLIGHT_FLAG || $flightStatus === 'cancelled') {
            return 'cancelled';
        }
        if (in_array($flag, self::OFFLOAD_KPI_FLAGS, true)) {
            return 'offloaded';
        }
        if (in_array($flag, self::DEPORTED_KPI_FLAGS, true)) {
            return 'deported';
        }
        if (in_array($flag, ['rescheduled', 'time_changed', 'transit'], true)) {
            return 'flagged';
        }
        // Package + change charges / penalties billed to the agent (billedChangesSql twin).
        if (! $voided && $this->chargedPackage() + (float) ($this->penaltyTotals()['billed'] ?? 0) > (float) ($this->agent_paid_amount ?? 0) + 0.005) {
            return 'due';
        }
        if ($flown) {
            return 'departed';
        }
        if ($live && ! $ticketed) {
            return 'pending';
        }
        if ($live) {
            return 'ticketed';
        }

        return null;
    }

    /** How many tickets this passenger's route takes — never below 1. */
    public function ticketsRequired(): int
    {
        return max(1, (int) ($this->tickets_required ?? 1));
    }

    /** Confirmed PNRs linked to this passenger (reads the loaded relation). */
    public function confirmedTicketCount(): int
    {
        return $this->ticketPurchases->where('status', 'confirm')->count();
    }

    /**
     * The tickets this passenger needs, one slot per ticket, each with the
     * PNR that fills it. Per head, NOT from the flight (owner, 2026-10-13):
     * on a multi-leg route the flight's route is only a label — the slot
     * count is his own tickets_required, and his PNRs may be any mix of
     * sectors (direct, transit, multi). PNRs fill slots in travel order and
     * each slot shows the sector of the PNR in it.
     *
     * @return array<int, array{index:int, label:string, sector:?string, cost:?float, ticket_no:?string, status:?string, done:bool}>
     */
    public function ticketSlots(): array
    {
        $purchases = $this->relationLoaded('ticketPurchases') ? $this->ticketPurchases : $this->ticketPurchases()->with('legs')->get();
        // A cancelled PNR fills no slot. It stays linked as the record of what
        // was booked, and taking a slot would hide the PNR that replaced it.
        $purchases = $purchases->reject(fn ($t) => $t->status === 'cancelled')->values();
        $count = max($this->ticketsRequired(), $purchases->count(), 1);
        $slots = [];
        for ($i = 0; $i < $count; $i++) {
            $slots[] = [
                'index' => $i + 1,
                'label' => $count > 1 ? 'Ticket '.($i + 1) : 'Ticket',
                'sector' => null, 'from_code' => null, 'to_code' => null,
                'from_flag' => null, 'to_flag' => null,
                'travel_date' => null, 'cost' => null,
                'leg_ticket_id' => null, 'title' => null, 'vendor' => null, 'airline' => null,
                'ticket_no' => null, 'status' => null, 'done' => false, 'purchase_id' => null,
                // a Ticket Orders booking / draft for this ticket, when no purchase covers it yet
                'hold' => null,
            ];
        }
        foreach (self::slotOrder($purchases, $count) as $k => $t) {
            $slots[$k] = $this->fillSlot($slots[$k], $t);
        }
        // Holds: this passenger's open Ticket Orders rows on the flight's route,
        // by leg. Not a ticket — the slot stays undone — but the tracker can say
        // "on hold till …" instead of nothing. Guarded like legsArray().
        try {
            $routeId = $this->contractFlight?->ticket_id;
            if ($routeId && $this->passport_holder_id && in_array(null, array_column($slots, 'purchase_id'), true)) {
                $holds = TicketBooking::open()->where('passport_holder_id', $this->passport_holder_id)
                    ->where('ticket_id', $routeId)->orderByDesc('id')->get();
                foreach ($holds as $h) {
                    foreach ($slots as $k => $slot) {
                        if ($slot['purchase_id'] !== null || $slot['hold'] !== null) {
                            continue;
                        }
                        if ($h->leg_sequence && (int) $h->leg_sequence !== (int) $slot['index']) {
                            continue;
                        }
                        $slots[$k]['hold'] = ['id' => $h->id, 'status' => $h->status, 'reference' => $h->booking_reference,
                            'expires_at' => $h->expires_at?->format('d M, h:i A'), 'pnr' => $h->pnr];
                        if (! $slots[$k]['travel_date'] && $h->departure_at) {
                            $slots[$k]['travel_date'] = $h->departure_at;
                        }
                        break;
                    }
                }
            }
        } catch (\Throwable) {
            // no bookings table yet — slots simply carry no hold
        }

        return $slots;
    }

    /**
     * Which live ticket is Ticket 1, 2, … (owner, 2026-11-01): a ticket's place
     * is its LEG, never its date. A ticket issued as "Ticket n of m" (leg_sequence)
     * holds slot n whatever day it flies — leg 2 re-issued to a day before leg 1
     * is still leg 2, and leg 1 refunded leaves slot 1 empty instead of leg 2
     * sliding up into it. Tickets without a leg number (a direct ticket, a new
     * ticket after a refund) fill the free slots in journey order: a ticket that
     * starts where another ends goes after it; otherwise the earlier date first.
     * Same reading as the builder's slots (passenger-builder-script, legOf()).
     *
     * @return array<int, TicketPurchase> slot position (0-based) => ticket
     */
    public static function slotOrder(Collection $live, int $count): array
    {
        $legsOf = fn ($t) => $t->relationLoaded('legs') ? $t->legs : $t->legs()->get();
        $placed = [];
        $rest = [];
        foreach ($live->sortBy('id') as $t) {
            $seq = (int) ($t->leg_sequence ?? 0);
            if ($seq >= 1 && $seq <= $count && ! isset($placed[$seq - 1])) {
                $placed[$seq - 1] = $t;
            } else {
                $rest[] = $t;
            }
        }
        // journey order for the rest: pick the one no other remaining ticket leads into
        $rest = collect($rest)->sortBy(fn ($t) => [$legsOf($t)->first()?->travel_date ?? '9999', $t->id])->values()->all();
        $ordered = [];
        while ($rest) {
            $pick = 0;
            foreach ($rest as $i => $t) {
                $from = strtoupper((string) $legsOf($t)->first()?->from_location);
                $led = $from !== '' && collect($rest)->contains(fn ($o) => $o !== $t
                    && strtoupper((string) $legsOf($o)->last()?->to_location) === $from);
                if (! $led) {
                    $pick = $i;
                    break;
                }
            }
            $ordered[] = $rest[$pick];
            array_splice($rest, $pick, 1);
        }
        for ($k = 0; $ordered; $k++) {
            if (! isset($placed[$k])) {
                $placed[$k] = array_shift($ordered);
            }
        }
        ksort($placed);

        return $placed;
    }

    private function fillSlot(array $slot, TicketPurchase $t): array
    {
        $legs = $t->relationLoaded('legs') ? $t->legs : $t->legs()->get();
        $firstLeg = $legs->first();
        $from = $firstLeg?->from_location ?: null;
        $to = $legs->last()?->to_location ?: null;

        return ['purchase_id' => $t->id,
            'from_code' => $from, 'to_code' => $to, 'sector' => ($from && $to) ? $from.' → '.$to : null,
            'leg_ticket_id' => $t->ticket_id, 'ticket_no' => $t->ticket_no, 'status' => $t->status, 'done' => $t->status === 'confirm',
            'travel_date' => $firstLeg?->travel_date ?: $slot['travel_date']] + $slot;
    }

    /**
     * Ticketed means every leg is covered: a DAC → ECN passenger with only
     * the DAC-IST PNR confirmed is still pending, not ticketed.
     */
    public function isFullyTicketed(): bool
    {
        return $this->confirmedTicketCount() >= $this->ticketsRequired();
    }

    /**
     * SQL twin of isFullyTicketed() for the KPI scopes — the count of
     * confirmed PNRs on the link table against tickets_required.
     */
    public static function fullyTicketedSql(): string
    {
        return '(SELECT COUNT(*) FROM contract_flight_passenger_tickets cfpt'
            .' JOIN ticket_purchases tp ON tp.id = cfpt.ticket_purchase_id'
            .' WHERE cfpt.contract_flight_passenger_id = contract_flight_passengers.id'
            ." AND tp.status = 'confirm') >= GREATEST(COALESCE(contract_flight_passengers.tickets_required, 1), 1)";
    }

    /**
     * SQL twin of priceRevision(): what cancelled returns took off his package
     * (2026-10-14). "0" before the migration.
     */
    public static function priceRevisionSql(): string
    {
        if (! ReturnCancel::ready()) {
            return '0';
        }

        return '(SELECT COALESCE(SUM(r.price_revision), 0) FROM contract_flight_passenger_changes r'
            .' WHERE r.contract_flight_passenger_id = contract_flight_passengers.id)';
    }

    /**
     * SQL twin of penaltyTotals()['billed']: every change charge, airline
     * penalty and fare difference billed to the agent for this passenger.
     * "0" until the changes migration has run, so the scopes never break.
     */
    public static function billedChangesSql(): string
    {
        if (! ContractFlightPassengerChange::installed()) {
            return '0';
        }
        $fareAmount = ContractFlightPassengerChange::splitFareAvailable() ? 'COALESCE(c.agent_fare_amount, c.fare_difference, 0)' : 'COALESCE(c.fare_difference, 0)';
        $fault = ContractFlightPassengerChange::hasFaultColumns()
            ? " + CASE WHEN c.fault = 'agent' THEN COALESCE(c.airline_penalty, 0) ELSE 0 END"
              ." + CASE WHEN c.fare_bearer = 'agent' THEN {$fareAmount} ELSE 0 END"
            : '';

        return "(SELECT COALESCE(SUM(CASE WHEN c.bearer = 'agent' THEN COALESCE(c.charge, 0) ELSE 0 END{$fault}), 0)"
            .' FROM contract_flight_passenger_changes c WHERE c.contract_flight_passenger_id = contract_flight_passengers.id)';
    }

    /**
     * The flight(s) he was moved off, newest first — "was CF-015 · 26 Sep
     * 2026, 09:55 AM" under the current flight on every screen (2026-09-30).
     *
     * @return array<int, array{flight: ?string, departure: ?string}>
     */
    public function previousFlights(): array
    {
        if (! ContractFlightPassengerChange::installed()) {
            return [];
        }
        try {
            $this->loadMissing('flightChanges.fromFlight');

            return $this->flightChanges->whereIn('kind', ContractFlightPassengerChange::MOVE_KINDS)
                // a later leg changed on the same flight is not a previous flight
                ->filter(fn ($c) => $c->from_flight_id && (int) $c->from_flight_id !== (int) $c->to_flight_id)->sortByDesc('id')
                // one move can carry two change rows (agent's and our share)
                ->unique(fn ($c) => $c->from_flight_id.'-'.$c->to_flight_id)
                ->map(fn ($c) => ['flight' => $c->fromFlight?->flight_number,
                    'departure' => $c->fromFlight?->departure_at?->format('d M Y, h:i A')])
                ->values()->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /** "DAC-IST" — the sector a ticket covers (its first leg), for matching a new ticket to the one it replaces. */
    public static function ticketSector(TicketPurchase $t): ?string
    {
        $leg = $t->relationLoaded('legs') ? $t->legs->first() : $t->legs()->first();

        return $leg && $leg->from_location && $leg->to_location
            ? strtoupper(trim($leg->from_location)).'-'.strtoupper(trim($leg->to_location)) : null;
    }

    /**
     * His last refund and what replaces it (owner, 2026-09-30) — read by the
     * flight card's money area and by the fare-difference settlement, so the
     * two can never disagree:
     *  - old tickets: what was refunded (with vendor / portal / trip / price,
     *    which also pre-fill Issue New Ticket);
     *  - penalty kept, split by the side chosen per leg;
     *  - new tickets: live tickets on the SAME sectors as the refunded ones —
     *    a leg that was kept is never counted as "new";
     *  - the pending move's fare difference and who bears it.
     */
    public function reissueMoney(): ?array
    {
        if (! ContractFlightPassengerChange::installed()) {
            return null;
        }
        try {
            $this->loadMissing('flightChanges');
            $rows = $this->flightChanges->sortByDesc('id');
            $refund = $rows->first(fn ($c) => in_array($c->kind, ['cancelled', 'flagged', 'reissue', 'ticket_refund'], true)
                && ! empty($c->previous_ticket_purchase_ids));
            if (! $refund) {
                return null;
            }
            $oldIds = array_map('intval', (array) $refund->previous_ticket_purchase_ids);
            $old = TicketPurchase::with('legs')->whereIn('id', $oldIds)->get();
            $sectors = $old->map(fn ($t) => self::ticketSector($t))->filter()->unique()->values();
            $penRows = $rows->filter(fn ($c) => $c->id === $refund->id || str_contains((string) $c->note, 'same move as change #'.$refund->id));
            $move = $rows->first(fn ($c) => $c->kind === 'reissue' && $c->id >= $refund->id && $c->fare_bearer);
            $new = $this->ticketPurchases()->with('legs')->where('ticket_purchases.status', '!=', 'cancelled')->get()
                ->reject(fn ($t) => in_array((int) $t->id, $oldIds, true))
                ->filter(fn ($t) => $sectors->isEmpty() || $sectors->contains(self::ticketSector($t)));

            return [
                'refund_change_id' => $refund->id,
                'change_id' => $move?->id,
                'old_tickets' => $old->map(fn ($t) => [
                    'id' => $t->id, 'no' => $t->ticket_no, 'sector' => self::ticketSector($t), 'leg_sequence' => $t->leg_sequence,
                    'vendor_id' => $t->vendor_id, 'portal_id' => $t->portal_id, 'trip_type' => $t->trip_type,
                    'ticket_class' => $t->ticket_class, 'amount' => (float) $t->amount, 'refund' => $t->refund_amount !== null ? (float) $t->refund_amount : null,
                ])->values()->all(),
                'old_cost' => round((float) $old->sum('amount'), 2),
                'penalty_agent' => round((float) $penRows->where('fault', 'agent')->sum('airline_penalty'), 2),
                'penalty_ours' => round((float) $penRows->where('fault', '!=', 'agent')->sum('airline_penalty'), 2),
                'new_cost' => round((float) $new->sum('amount'), 2),
                'new_count' => $new->count(),
                'fare_difference' => $move?->fare_difference !== null ? (float) $move->fare_difference : null,
                'fare_bearer' => $move?->fare_bearer,
                'agent_fare_amount' => $move?->agent_fare_amount,
                'settled' => $move && $move->fare_difference !== null,
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /** [change ids, reissue fees] — penaltyTotals() runs several times per row. */
    protected ?array $penaltyFeeCache = null;

    /**
     * Every money line his changes produced, one per ticket and event, for the
     * cost breakdown (owner, 2026-10-10: "which ticket, who bears — EFGH1 ·
     * reissue 1 · ৳x, reissue 2 · ৳y"). Read from the same change rows
     * penaltyTotals() sums, so the lines always add up to its figures:
     *  - cost   = money that leaves us (seller's reissue charge, what the airline
     *             kept, a charge we bear) — these lines sum to penaltyTotals()['borne'];
     *  - agent  = what of it the agent is billed for — sums to ['billed'].
     * A fare difference on a new ticket is inside that ticket's price, so it is
     * shown with its bearer but is not a separate cost (cost = false).
     *
     * @return array<int, array{pnr: string, label: string, amount: float, bearer: string, cost: bool, agent: float}>
     */
    public function changeLines(): array
    {
        if (! ContractFlightPassengerChange::installed()) {
            return [];
        }
        try {
            $this->loadMissing('flightChanges');
            $rows = $this->flightChanges->sortBy('id');
        } catch (\Throwable) {
            return [];
        }
        $ids = $rows->flatMap(fn ($c) => array_map('intval', (array) ($c->previous_ticket_purchase_ids ?? [])))->unique()->all();
        $pnrs = $ids ? TicketPurchase::whereIn('id', $ids)->pluck('ticket_no', 'id')->all() : [];
        $lastPnr = '—';
        $pnrOf = function ($c) use ($pnrs, &$lastPnr) {
            $names = array_map(fn ($i) => $pnrs[(int) $i] ?? ('#'.$i), (array) ($c->previous_ticket_purchase_ids ?? []));
            // A row naming several tickets whose note names the one it charged
            // ("Charge per ticket — agent pays: EFGH1 ৳…") belongs to that one only.
            if (count($names) > 1) {
                $named = array_values(array_filter($names, fn ($n) => $n !== '' && str_contains((string) $c->note, $n.' ৳')));
                if ($named) {
                    $names = $named;
                }
            }
            if (! $names) {
                // A re-issue onto a new ticket carries no ids of its own: it replaced the
                // ticket(s) the change just before it cancelled.
                return $lastPnr === '—' ? '—' : $lastPnr.' → new ticket';
            }

            return $lastPnr = implode(', ', $names);
        };
        $count = [];
        $num = function (string $pnr, string $what) use (&$count) {
            $k = $pnr.'|'.$what;
            $count[$k] = ($count[$k] ?? 0) + 1;

            return $what.' '.$count[$k];
        };
        $lines = [];
        foreach ($rows as $c) {
            $pnr = $pnrOf($c);
            $charge = round((float) ($c->charge ?? 0), 2);
            $perTicket = str_contains((string) $c->note, 'Charge per ticket');
            if ($charge > 0 && $perTicket) {
                // The seller's reissue charge: we owe it whoever bears it; the agent is billed when his.
                // One change can charge several tickets — "…: J3P97Q2 ৳23,494.00 + TVEMQC2 ৳2,815.00" —
                // so each PNR gets its own line and amount.
                $bearer = $c->bearer === 'agent' ? 'agent' : 'company';
                $parts = [];
                if (preg_match('/Charge per ticket[^:]*:\s*(.+?)(?:\s·|$)/u', (string) $c->note, $m)) {
                    foreach (explode(' + ', $m[1]) as $bit) {
                        if (preg_match('/^(.+?)\s৳([\d,]+(?:\.\d+)?)$/u', trim($bit), $b)) {
                            $parts[] = [trim($b[1]), (float) str_replace(',', '', $b[2])];
                        }
                    }
                }
                if (! $parts || abs(array_sum(array_column($parts, 1)) - $charge) > 0.5) {
                    $parts = [[$pnr, $charge]];
                }
                foreach ($parts as [$one, $amt]) {
                    // "our charge ৳…" (owner, 2026-10-13) is our fee on the reissue, not the seller's
                    if (strcasecmp($one, 'our charge') === 0) {
                        $lines[] = ['pnr' => $pnr, 'label' => 'Our charge', 'amount' => round($amt, 2), 'bearer' => 'agent',
                            'cost' => false, 'agent' => round($amt, 2)];

                        continue;
                    }
                    $lines[] = ['pnr' => $one, 'label' => $num($one, 'Reissue'), 'amount' => round($amt, 2), 'bearer' => $bearer,
                        'cost' => true, 'agent' => $bearer === 'agent' ? round($amt, 2) : 0.0];
                }
            } elseif ($charge > 0) {
                // Our own fee for the change (agent) or a charge we absorb (company).
                $what = match ($c->kind) {
                    'cancelled', 'flagged' => 'Cancellation fee', 'ticket_refund' => 'Refund fee', default => 'Change fee'
                };
                $lines[] = ['pnr' => $pnr, 'label' => $what, 'amount' => $charge, 'bearer' => $c->bearer === 'agent' ? 'agent' : 'company',
                    'cost' => $c->bearer !== 'agent', 'agent' => $c->bearer === 'agent' ? $charge : 0.0];
            }
            $kept = round((float) ($c->airline_penalty ?? 0), 2);
            // Rows recorded before 2026-09-30 name no ticket — only what happened, in the note.
            $legacy = in_array($c->kind, ['ticket_reissue_addition', 'ticket_refund_loss'], true);
            $where = $legacy ? 'No PNR recorded' : $pnr;
            $why = $legacy && $c->note ? ' — '.mb_strimwidth((string) $c->note, 0, 60, '…') : '';
            if ($kept > 0) {
                // A same-ticket re-issue's airline charge (older rows) vs what the airline kept on a refund.
                $label = $c->kind === 'ticket_reissue_addition' ? $num($where, 'Reissue').' · airline charge'.$why : $num($where, 'Refund').' · airline kept'.$why;
                $lines[] = ['pnr' => $where, 'label' => $label, 'amount' => $kept, 'bearer' => $c->fault === 'agent' ? 'agent' : 'company',
                    'cost' => true, 'agent' => $c->fault === 'agent' ? $kept : 0.0];
            }
            $fare = round((float) ($c->fare_difference ?? 0), 2);
            if (abs($fare) >= 0.005 && $c->fare_bearer) {
                $legacy = $c->kind === 'ticket_reissue_addition';
                $lines[] = ['pnr' => $legacy ? $where : $pnr, 'label' => $legacy ? $num($where, 'Reissue').' · fare difference'.$why : ($fare < 0 ? 'New ticket cheaper' : 'Fare difference').($c->agent_fare_amount !== null ? ' (agent ৳'.number_format($c->agentFareAmount(), 2).' / us ৳'.number_format($fare - $c->agentFareAmount(), 2).')' : ''), 'amount' => abs($fare),
                    'bearer' => $c->fare_bearer === 'agent' ? 'agent' : 'company', 'cost' => $legacy,
                    'agent' => $c->agentFareAmount()];
            }
        }

        return $lines;
    }

    /**
     * Penalties for his moves between flights (2026-09-27): what the agent is
     * billed (cancellation / reissue / reschedule charge, bearer agent) and
     * what we bear. Reads the changes relation once; 0 while the changes
     * migration has not run.
     *
     * @return array{billed: float, borne: float, fees?: float}
     */
    public function penaltyTotals(): array
    {
        if (! ContractFlightPassengerChange::installed()) {
            return ['billed' => 0.0, 'borne' => 0.0];
        }
        try {
            $this->loadMissing('flightChanges');
            $rows = $this->flightChanges;
        } catch (\Throwable) {
            return ['billed' => 0.0, 'borne' => 0.0];
        }
        // Per-ticket reissue fees (2026-09-30) are owed to the ticket's vendor
        // whoever bears them, so the whole fee is our cost; the agent's share
        // comes back through 'billed'. Our share's change row ("Charge per
        // ticket — we bear …") is that same money, so it is not counted twice.
        $fees = 0.0;
        $ids = $rows->pluck('id')->all();
        $key = implode(',', $ids);
        if ($this->penaltyFeeCache !== null && $this->penaltyFeeCache[0] === $key) {
            $fees = $this->penaltyFeeCache[1];
        } elseif ($ids) {
            try {
                // Net on the expense heads (5173 / 5172), not "every debit": an Undo adds a
                // reversing journal under the same source (Dr payable / Cr expense), and summing
                // all debits would count the fee twice instead of zero (2026-10-10).
                $expense = Account::whereIn('code', array_filter([config('accounts.cf_change_company'), config('accounts.cf_fare_diff_company')]))->pluck('id');
                $fees = (float) DB::table('journal_items')
                    ->join('journal_entries', 'journal_entries.id', '=', 'journal_items.journal_entry_id')
                    ->where('journal_entries.source', ContractFlightPassengerChange::REISSUE_FEE_TYPE)
                    ->whereIn('journal_entries.source_id', $ids)
                    ->whereIn('journal_items.account_id', $expense)
                    ->selectRaw('COALESCE(SUM(journal_items.debit),0) - COALESCE(SUM(journal_items.credit),0) n')->value('n');
            } catch (\Throwable) {
                $fees = 0.0;
            }
            $this->penaltyFeeCache = [$key, $fees];
        }
        $ourOther = $rows->where('bearer', 'company')
            // Anywhere in the note — a move that also changed dates writes
            // "New dates: …" first, and starts-with missed it (2026-09-30:
            // the fee was counted twice, as this charge and as its journal).
            ->reject(fn ($c) => str_contains((string) $c->note, 'Charge per ticket'))->sum('charge');

        // What the airline kept on a cancelled ticket (2026-09-30): the
        // cancelled ticket leaves his ticket cost, so the loss comes back in
        // as cost — on a refund, a reissue onto a new ticket, or a cancel.
        // Billed to the agent too when it was his side. A fare difference is
        // already inside the new ticket's cost; only the agent's share is
        // added, as charge.
        $loss = (float) $rows->sum('airline_penalty');
        $lossBilled = (float) $rows->where('fault', 'agent')->sum('airline_penalty');
        $fareBilled = (float) $rows->sum(fn ($c) => $c->agentFareAmount());
        // Live's older same-PNR re-issue rows (ticket_reissue_addition, before
        // 2026-09-30): their fare difference was paid to the airline but sits
        // in no ticket's price, so it is his cost. (A reissue MOVE's fare
        // difference is already inside the new ticket's price — not added.)
        $legacyFare = (float) $rows->where('kind', 'ticket_reissue_addition')->sum('fare_difference');

        return [
            'billed' => (float) $rows->where('bearer', 'agent')->sum('charge') + $lossBilled + $fareBilled,
            'borne' => (float) $ourOther + $fees + $loss + $legacyFare,
            'fees' => $fees,
            'loss' => $loss,
            // Each part on its own, for the card's cost breakdown (2026-09-30).
            'parts' => [
                'our_charges' => round((float) $ourOther, 2),          // change charges we bear (not per-ticket)
                'reissue_fees' => round($fees, 2),                     // per-ticket fees owed to vendor / portal
                'penalties_kept' => round($loss, 2),                   // what airlines kept on cancelled tickets
                'legacy_fare' => round($legacyFare, 2),                // older same-PNR fare differences
                'charges_billed' => round((float) $rows->where('bearer', 'agent')->sum('charge'), 2),
                'penalties_billed' => round($lossBilled, 2),
                'fare_billed' => round($fareBilled, 2),
            ],
        ];
    }

    /**
     * The package as it stands on the agent's statement: the price, or 0
     * once it has been reversed (cancelled, off flight, deported on our side).
     * Every figure below reads this, never agent_price directly, so a
     * reversed package can never show as revenue or profit (2026-10-04).
     */
    public function getPackageChargedAttribute(): float
    {
        // A cancelled return's price revision (2026-10-14) comes off: it is its own
        // credit on his statement, so the package he is charged is the price less it.
        return in_array($this->exception_flag, self::AGENT_CHARGE_VOIDING_FLAGS, true) ? 0.0 : $this->chargedPackage();
    }

    /** Package + every penalty billed to the agent — what he is charged in total for this person. */
    public function getAgentTotalAttribute(): float
    {
        return max(0, $this->package_charged + $this->penaltyTotals()['billed'] + $this->baggageTotals()['charge'] + $this->suspendWithdrawTotals()['charge']
            - PartyDiscountService::total(self::AGENT_LEDGER_TYPE, (int) $this->id));
    }

    public function getAgentDueAttribute(): float
    {
        return max(0, $this->agent_total - (float) ($this->agent_paid_amount ?? 0));
    }

    /** Read-only phone fallback: the ops sheet's contact may differ from the passport holder's own file. */
    public function getPhoneAttribute()
    {
        return $this->contact_phone ?: $this->passportHolder?->phone;
    }

    /**
     * What it costs us to deliver this passenger's travel: every linked
     * PNR's ticket price + manpower + boarding + immigration + the
     * manpower-card fee when we're the one arranging it. agent_price is what
     * we charge the agent — revenue, not part of this cost figure — so it's
     * deliberately excluded.
     *
     * reissue_cost stays out too: it is an exception cost recorded against
     * the passenger for reporting, not part of the delivery cost the margin
     * was quoted on. Folding it in would let a reissue silently rewrite the
     * profit of a trip that was already sold.
     */
    public function getTotalTravelCostAttribute(): float
    {
        // A cancelled PNR stays linked (it is the record of what was booked)
        // but its amount is no longer a cost we carry — the purchase's own
        // cancel path has already reversed the bank/vendor postings for it.
        // A cancelled return half (2026-10-14) comes off its live round trip: the
        // refund came back and the penalty is in penaltyTotals()'s "airline kept".
        return (float) $this->ticketPurchases->where('status', '!=', 'cancelled')->sum(fn ($t) => (float) $t->amount - $t->returnTakenOff())
            + $this->baggageTotals()['cost']
            + (float) ($this->manpower_cost ?? 0)
            + (float) ($this->boarding_cost ?? 0)
            + (float) ($this->immigration_cost ?? 0)
            + (float) ($this->other_cost ?? 0)
            + (float) ($this->manpower_card_fee ?? 0)
            + $this->suspendWithdrawTotals()['cost'];
    }

    /** @var array{cost: float, charge: float}|null */
    private ?array $suspendWithdrawCache = null;

    /**
     * Suspend withdraw (2026-11-02): the vendor's cost is ours to pay (cost), what the agent is billed
     * (cost he bears + our charge) is charge. Zero when the table is missing — never throws.
     *
     * @return array{cost: float, charge: float}
     */
    public function suspendWithdrawTotals(): array
    {
        if ($this->suspendWithdrawCache !== null) {
            return $this->suspendWithdrawCache;
        }
        try {
            $row = Schema::hasTable('contract_flight_suspend_withdrawals') && $this->id
                ? DB::table('contract_flight_suspend_withdrawals')->where('contract_flight_passenger_id', $this->id)
                    ->selectRaw('COALESCE(SUM(cost),0) c, COALESCE(SUM(agent_total),0) a')->first()
                : null;
            $totals = ['cost' => round((float) ($row->c ?? 0), 2), 'charge' => round((float) ($row->a ?? 0), 2)];
        } catch (\Throwable) {
            $totals = ['cost' => 0.0, 'charge' => 0.0];
        }

        return $this->suspendWithdrawCache = $totals;
    }

    public function baggageTotals(): array
    {
        $cost = (float) $this->ticketPurchases->sum('extra_baggage_cost');
        $charge = (float) $this->ticketPurchases->where('extra_baggage_bearer', 'customer')->sum('extra_baggage_agent_charge');

        return ['cost' => $cost, 'charge' => $charge, 'profit' => $charge - $cost];
    }

    /** agent_price (receivable from the agent) minus what it cost us to deliver. */
    public function getProfitAttribute(): float
    {
        // A penalty the agent pays is income, one we bear is cost (2026-09-27).
        $pen = $this->penaltyTotals();

        return $this->agent_total - $this->total_travel_cost - $pen['borne'];
    }

    /**
     * Move to $targetStage only if it's further along the pipeline than the
     * current stage — never regresses progress. A ticket link is a one-way
     * signal ("at least this far along"); it must not undo a passenger who
     * has already reached Airport, say, because a second PNR got linked
     * during a reissue. Returns whether it actually advanced.
     */
    /**
     * The furthest stage the passenger's own data proves. Nothing has to be
     * entered at once: a card saved with just the passport holder sits at
     * Passenger Received, and each later edit (category, price, route, PNR)
     * carries the pipeline forward by itself. Stages after Ticket Issued
     * are physical events and stay manual (tracker, board, edit modal).
     */
    public function derivedStage(): string
    {
        $flight = $this->relationLoaded('contractFlight') ? $this->contractFlight : $this->contractFlight()->first();
        $this->loadMissing('ticketPurchases');

        $stage = 'passenger_received';
        if ($this->flight_category_id || $this->contact_phone) {
            $stage = 'information_entered';
        }
        if ($this->agent_price !== null && (float) $this->agent_price > 0) {
            $stage = 'service_defined';
        }
        if ($stage === 'service_defined' && $flight?->ticket_id) {
            $stage = 'route_confirmed';
        }
        if ($this->isFullyTicketed()) {
            $stage = 'ticket_issued';
        }

        return $stage;
    }

    /**
     * Advance to the derived stage if the data has moved past the current
     * one. Never regresses, never touches a manually-set later stage.
     * Returns the new stage key when it moved, null otherwise.
     */
    public function syncDerivedStage(): ?string
    {
        $target = $this->derivedStage();

        return $this->advanceStageTo($target) ? $target : null;
    }

    /**
     * A leg lost its ticket (refund / cancel) while the stage still claims
     * him ticketed: step back to what his data proves — but only before he
     * is physically travelling. From Airport Processing on, the stage he
     * reached is a fact about the journey and stands (e.g. leg 2 refunded
     * while he is in transit). Returns the new stage, or null.
     */
    public function stepBackIfUnticketed(): ?string
    {
        $order = array_keys(self::stages());
        $at = array_search($this->stage, $order, true);
        $issued = array_search('ticket_issued', $order, true);
        // The pipeline is editable (stages may be renamed or removed), so
        // "travelling" starts at the earliest physical stage that exists.
        $airport = collect(['airport_processing', 'boarding', 'immigration', 'departed', 'transit', 'landed', 'arrival_confirmed', 'case_closed'])
            ->map(fn ($k) => array_search($k, $order, true))->filter(fn ($i) => $i !== false)->min();
        $this->unsetRelation('ticketPurchases');
        if ($at === false || $issued === false || $at < $issued || ($airport !== null && $at >= $airport) || $this->isFullyTicketed()) {
            return null;
        }
        $target = $this->derivedStage();
        if ($target === $this->stage) {
            return null;
        }
        $this->update(['stage' => $target]);

        return $target;
    }

    public function advanceStageTo(string $targetStage): bool
    {
        $order = array_keys(self::stages());
        $currentIndex = array_search($this->stage, $order, true);
        $targetIndex = array_search($targetStage, $order, true);

        if ($targetIndex === false || $targetIndex <= $currentIndex) {
            return false;
        }

        $this->update(['stage' => $targetStage]);

        return true;
    }
}
