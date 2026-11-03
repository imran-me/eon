<?php

namespace App\Models;

use App\Services\FlightPackageTicketBilling;
use App\Services\TicketBaggage;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * One move of a passenger between flights (owner, 2026-09-26): taken off a
 * flight into Cancelled, then put on another as a reissue or a reschedule,
 * or put back where he was.
 *
 * Each row carries its own charge and bearer. A charge the agent bears is
 * posted to the agent's Party Statement under LEDGER_TYPE with invoice_id =
 * this row's id — not the passenger's — so two moves are two charges and
 * neither can overwrite the other (ReconcilesContractFlightChangeCharge).
 */
class ContractFlightPassengerChange extends Model
{
    /** Transaction::type / journal source of a change charge billed to the agent. */
    public const LEDGER_TYPE = 'contract_flight_passenger_change';

    /**
     * Airline penalty / fare difference billed to the agent (2026-09-28).
     * invoice_id = this change's id; each posting is its own row, never
     * edited — see PostsContractFlightAdjustments.
     */
    public const PENALTY_LEDGER_TYPE = 'contract_flight_airline_penalty';

    /**
     * Cancel return (owner, 2026-10-14): the agent's package lowered because his
     * passenger's return was cancelled — a CREDIT row, invoice_id = the change,
     * journal Dr flight revenue / Cr AR. Its own type, never the package
     * reversal: reconcileAgentLedger() re-levels that one to the package on
     * every save and would charge the reduction back.
     */
    public const PRICE_REVISION_TYPE = 'contract_flight_price_revision';

    public const FARE_LEDGER_TYPE = 'contract_flight_fare_difference';

    /** Journal sources of the Company Ledger postings for a change. */
    public const PENALTY_COMPANY_SOURCE = 'contract_flight_penalty_company';

    public const FARE_COMPANY_SOURCE = 'contract_flight_fare_company';

    /**
     * Reissue / date-change fee on a ticket (owner, 2026-09-30): the ticket's
     * vendor charges it to us whoever bears it — vendor ledger row of this
     * type (invoice_id = the ticket purchase) + journal Dr 5173 / Cr AP
     * (source = this, source_id = the change). When the agent pays, his own
     * change-charge line recovers it (Cr 4183).
     */
    public const REISSUE_FEE_TYPE = 'contract_flight_reissue_fee';

    /** The fare-difference part of a per-ticket re-issue, as its own seller row (2026-10-06). Its journal keeps REISSUE_FEE_TYPE as source. */
    public const REISSUE_FARE_TYPE = 'contract_flight_reissue_fare';

    /**
     * Journal of a correction to a change's agent line (2026-10-04): the
     * correction is an ADDED row of LEDGER_TYPE (invoice_id = this change)
     * and this journal (source_id = this change). Never an edit.
     */
    public const FIX_SOURCE = 'contract_flight_passenger_change_fix';

    /** Agent-ledger rows that are charges or their corrections — never money received. */
    public const NOT_RECEIPT_TYPES = [
        'party_discount', 'party_discount_reversal',
        FlightPackageTicketBilling::TYPE,
        TicketBaggage::COST,
        TicketBaggage::CHARGE,
        ContractFlightPassenger::AGENT_REVERSAL_TYPE,
        self::LEDGER_TYPE,
        self::PENALTY_LEDGER_TYPE,
        self::FARE_LEDGER_TYPE,
        ContractFlightPassenger::REISSUE_LEDGER_TYPE,
        // An agent can also buy plain tickets: a Ticket Orders refund takes
        // his agent price back as a credit — not money received (2026-10-06).
        TicketChange::SALE_REVERSAL_TYPE,
        // A cancelled return's price revision is a credit, not money received (2026-10-14).
        self::PRICE_REVISION_TYPE,
    ];

    /**
     * PNR → its earlier travel dates, oldest first (owner, 2026-09-30: every
     * screen shows the new date with the old one beneath). Read from the
     * "New dates: PNR old → new" line Re-assign writes on each move; one
     * query per request, and empty rather than an error on any failure.
     *
     * @return array<string, array<int, array{from: ?string, to: string, move: string}>>
     */
    public static function dateHistoryByPnr(): array
    {
        static $map = null;
        if ($map !== null) {
            return $map;
        }
        $map = [];
        try {
            if (! self::installed()) {
                return $map;
            }
            $rows = self::with(['fromFlight:id,flight_number', 'toFlight:id,flight_number'])
                ->where('note', 'like', '%New dates:%')->orderBy('id')->get();
            foreach ($rows as $c) {
                if (! preg_match('/New dates: (.+?)(?: · |$)/u', (string) $c->note, $m)) {
                    continue;
                }
                foreach (explode(', ', $m[1]) as $part) {
                    if (preg_match('/^(.+) (\S+) → (\d{4}-\d{2}-\d{2})$/u', trim($part), $x)) {
                        $map[$x[1]][] = ['from' => $x[2] === '—' ? null : $x[2], 'to' => $x[3],
                            'move' => trim(($c->fromFlight?->flight_number ?? '').' → '.($c->toFlight?->flight_number ?? ''), ' →')];
                    }
                }
            }
        } catch (\Throwable) {
            $map = [];
        }

        return $map;
    }

    /** "was 26 Sep 2026" (+ earlier ones) for a PNR, or null when it never moved. */
    public static function wasDatesLine(?string $pnr): ?string
    {
        $h = $pnr ? (self::dateHistoryByPnr()[$pnr] ?? []) : [];
        $olds = array_values(array_filter(array_map(fn ($e) => $e['from'], $h)));
        if (! $olds) {
            return null;
        }

        return 'was '.implode(' · ', array_map(fn ($d) => Carbon::parse($d)->format('d M Y'), array_reverse($olds)));
    }

    /** Who caused the change — and so who bears the airline's penalty. */
    public const FAULTS = [
        'company' => 'Our side — we bear it',
        'agent' => 'Passenger / agent side — bill the agent',
    ];

    public const KINDS = [
        'cancelled' => 'Taken off flight',
        'reissue' => 'Reissued onto flight',
        'reschedule' => 'Rescheduled onto flight',
        'restored' => 'Put back on flight',
        'flagged' => 'Exception on flight',
        // One ticket, same flight (from live 2026-09-29, posted our way
        // 2026-09-30). Kept under 20 characters: no column change needed.
        'ticket_reissue' => 'Ticket re-issued (same PNR)',
        'ticket_refund' => 'Ticket refunded (airline kept a loss)',
        // A family passenger's unused return cancelled (2026-10-14, cancelReturn()).
        'return_cancel' => 'Return cancelled',
    ];

    /**
     * The two ways onto another flight (owner, 2026-09-26):
     *  - reschedule: same ticket, new date — his live PNRs go with him and
     *    still count as his ticket.
     *  - reissue: a new ticket — every live PNR he holds is cancelled through
     *    Ticketing (amount 0, cancel_note), stays linked as his history and
     *    stays listed in Ticket Orders; the new PNR is issued on the new
     *    flight's card. A cancelled PNR is neither his cost nor his ticket.
     */
    public const MOVE_KINDS = ['reschedule', 'reissue'];

    /** Why a passenger is taken off a flight. */
    public const CANCEL_REASONS = [
        'documents' => 'Documents not ready',
        'not_travelling' => 'Passenger not travelling',
        'visa' => 'Visa / permit issue',
        'medical' => 'Medical',
        'agent_request' => 'Agent request',
        'other' => 'Other',
        // Case closed (owner, 2026-09-30): package reversed, the airline's
        // refund and penalty posted. Reversible (owner, 2026-10-26): its row
        // keeps Re-assign / Undo, which re-bill the package as a new line.
        'will_not_fly' => 'Will not fly — closed',
        // Refund (Cancel) / New Ticket onto a brand-new flight: his old tickets
        // are cancelled here, then Add Flight re-books him (its Re-booking box).
        'new_ticket' => 'Refunded — new ticket on a new flight',
    ];

    /**
     * Offload / Deported from Re-assign (owner, 2026-10-07): the event is the
     * change's reason and the passenger's exception flag. Recorded as a
     * 'flagged' change, like the edit modal's money flags.
     */
    public const OFFLOAD_EVENTS = [
        'immigration_offload' => 'Immigration offload',
        'boarding_denied' => 'Boarding offload',
        'deported' => 'Deported',
    ];

    /** What sending a deported passenger home cost — each with its own bearer. */
    public const DEPORT_COSTS = [
        'return_ticket' => 'Return ticket to BD',
        'foreign_airport' => 'Charges at the deporting airport',
        'local_airport' => 'Charges at our airport',
    ];

    /** The Offload / Deported option: available() plus its own route. */
    public static function offloadAvailable(): bool
    {
        try {
            return self::available() && Route::has('role.contract-flight-passengers.offload');
        } catch (\Throwable) {
            return false;
        }
    }

    /** Who carries a change charge — same choice the reissue field offers. */
    public const BEARERS = [
        'agent' => 'Bill the agent',
        'company' => 'We bear it',
    ];

    protected $fillable = [
        'contract_flight_passenger_id',
        'kind',
        'from_flight_id',
        'to_flight_id',
        'reason',
        'note',
        'charge',
        'bearer',
        'agent_id',
        'previous_ticket_purchase_ids',
        'previous_flag',
        'fault',
        'airline_penalty',
        'price_revision', // cancelled return: the agent's package lowered by this (2026-10-14)
        'fare_difference',
        'fare_bearer',
        'agent_fare_amount',
        'created_by',
    ];

    protected $casts = [
        'charge' => 'float',
        'airline_penalty' => 'float',
        'fare_difference' => 'float',
        'previous_ticket_purchase_ids' => 'array',
    ];

    public function passenger(): BelongsTo
    {
        return $this->belongsTo(ContractFlightPassenger::class, 'contract_flight_passenger_id');
    }

    public function fromFlight(): BelongsTo
    {
        return $this->belongsTo(ContractFlight::class, 'from_flight_id');
    }

    public function toFlight(): BelongsTo
    {
        return $this->belongsTo(ContractFlight::class, 'to_flight_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Whether the changes migration has run. Everything that reads this
     * table checks first: a server that lags the repo must still render
     * every contract flight screen, only without the Cancelled pool.
     */
    public static function installed(): bool
    {
        static $installed = null;
        if ($installed === null) {
            try {
                // Both halves of the one migration: the history table and the
                // note column on ticket_purchases. A half-applied run leaves
                // the feature switched off, never half working.
                $installed = Schema::hasTable('contract_flight_passenger_changes')
                    && Schema::hasColumn('ticket_purchases', 'cancel_note');
            } catch (\Throwable) {
                $installed = false;
            }
        }

        return $installed;
    }

    /**
     * Whether Cancel / Re-assign can be offered on screen: the migration has
     * run AND the routes are registered. A server whose route cache was not
     * refreshed after deploy lacks the route names, and route() on a missing
     * name throws — which would take Flights & Passengers down with it. With
     * this false, every screen renders exactly as it did before the feature.
     */
    /** Whether the 2026-09-28 fault / penalty / fare-difference columns exist. */
    public static function hasFaultColumns(): bool
    {
        static $has = null;
        if ($has === null) {
            try {
                $has = self::installed()
                    && Schema::hasColumn('contract_flight_passenger_changes', 'fare_bearer');
            } catch (\Throwable) {
                $has = false;
            }
        }

        return $has;
    }

    /** Re-Issue / Refund on one ticket: the ticket rides in previous_ticket_purchase_ids[0]. */
    public const TICKET_ADJUSTMENT_KINDS = ['ticket_reissue', 'ticket_refund', 'return_cancel'];

    /** Guarded like available(): the buttons only render when the route is registered. */
    public static function ticketAdjustmentAvailable(): bool
    {
        try {
            return self::installed() && Route::has('role.contract-flight-passengers.ticket-adjustment');
        } catch (\Throwable) {
            return false;
        }
    }

    public static function available(): bool
    {
        static $available = null;
        if ($available === null) {
            try {
                $available = self::installed()
                    && collect(['cancel-from-flight', 'reassign-flights', 'reassign', 'restore'])
                        ->every(fn ($n) => Route::has('role.contract-flight-passengers.'.$n))
                    && Route::has('role.contract-flight-passenger-changes.charge')
                    && Route::has('role.contract-flight-holders.off-flight');
            } catch (\Throwable) {
                $available = false;
            }
        }

        return $available;
    }

    /** A null share preserves the historic all-or-nothing bearer. */
    public function agentFareAmount(): float
    {
        if (! $this->agent_id || $this->fare_bearer !== 'agent') {
            return 0.0;
        }

        return round((float) ($this->agent_fare_amount ?? $this->fare_difference ?? 0), 2);
    }

    public static function splitFareAvailable(): bool
    {
        static $available = null;

        return $available ??= self::hasFaultColumns() && Schema::hasColumn('contract_flight_passenger_changes', 'agent_fare_amount');
    }

    /** Billed to the agent — the only case that touches the ledger. */
    public function isBilled(): bool
    {
        return $this->bearer === 'agent' && (float) ($this->charge ?? 0) > 0 && $this->agent_id;
    }

    /** Whether this move carries a charge that can be corrected afterwards. */
    public function isChargeable(): bool
    {
        // Moves onto a flight, and a cancellation charge (2026-09-27).
        return $this->kind === 'cancelled' || in_array($this->kind, self::MOVE_KINDS, true)
            || ($this->kind === 'flagged' && isset(self::OFFLOAD_EVENTS[$this->reason]));
    }

    /** One line of history, for the edit modal and the Cancelled list. */
    public function toCard(): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'kind_label' => self::KINDS[$this->kind] ?? $this->kind,
            'ticket_purchase_id' => in_array($this->kind, self::TICKET_ADJUSTMENT_KINDS, true)
                ? ((array) ($this->previous_ticket_purchase_ids ?? []))[0] ?? null : null,
            'fault' => $this->fault,
            'from_flight' => $this->fromFlight?->flight_number,
            'to_flight' => $this->toFlight?->flight_number,
            'reason_label' => $this->reason ? (self::CANCEL_REASONS[$this->reason] ?? self::OFFLOAD_EVENTS[$this->reason] ?? $this->reason) : null,
            'note' => $this->note,
            'charge' => $this->charge,
            'bearer' => $this->bearer,
            'bearer_label' => $this->bearer ? (self::BEARERS[$this->bearer] ?? $this->bearer) : null,
            'fault_label' => $this->fault ? (self::FAULTS[$this->fault] ?? $this->fault) : null,
            'airline_penalty' => $this->airline_penalty,
            'fare_difference' => $this->fare_difference,
            'fare_bearer_label' => $this->agent_fare_amount !== null ? 'Agent ৳'.number_format($this->agentFareAmount(), 2).' / We bear ৳'.number_format((float) $this->fare_difference - $this->agentFareAmount(), 2) : ($this->fare_bearer ? (self::BEARERS[$this->fare_bearer] ?? $this->fare_bearer) : null),
            'chargeable' => $this->isChargeable(),
            'by' => $this->creator?->name,
            'at' => $this->created_at?->format('d M Y, h:i A'),
        ];
    }
}
