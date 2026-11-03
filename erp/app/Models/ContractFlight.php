<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class ContractFlight extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'flight_number',
        'flight_category_id',
        'flight_category_type_id',
        'handling_type',
        'ticket_class',
        'ticket_id',
        'airline_flight_no',
        'departure_at',
        'arrival_at',
        'total_seats',
        'seats_sold',
        'cost_price',
        'due_amount',
        'cost_paid_amount',
        'purchase_date',
        'payable_date',
        'revenue',
        'agent_id',
        'vendor_id',
        'portal_id',
        'cost_bank_id',
        'boarding_officer_id',
        'immigration_officer_id',
        'ticket_cost_per_pax',
        'manpower_cost_per_pax',
        'boarding_cost_per_pax',
        'immigration_cost_per_pax',
        'sale_price_per_pax',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'departure_at' => 'datetime',
        'arrival_at' => 'datetime',
        'total_seats' => 'integer',
        'seats_sold' => 'integer',
        'cost_price' => 'float',
        'due_amount' => 'float',
        'cost_paid_amount' => 'float',
        'purchase_date' => 'date',
        'payable_date' => 'date',
        'revenue' => 'float',
        'ticket_cost_per_pax' => 'float',
        'manpower_cost_per_pax' => 'float',
        'boarding_cost_per_pax' => 'float',
        'immigration_cost_per_pax' => 'float',
        'sale_price_per_pax' => 'float',
    ];

    protected $appends = [
        'airline',
        'route',
        'profit',
        'seats_available',
        'agent_summary',
        'total_agent_charge',
        'total_travel_cost',
    ];

    /** A mixed batch can also appear in its passenger exception sections. */
    public function dashboardSections(CarbonInterface $now): array
    {
        if (! in_array($this->status, ['open', 'boarding', 'departed'], true)) {
            return [];
        }

        $sections = [];
        if ($this->passengers->contains('exception_flag', 'flight_missed')) {
            $sections[] = 'missed';
        }
        if ($this->passengers->contains(fn ($p) => in_array($p->exception_flag, [
            'boarding_denied', 'immigration_offload', 'deported', ContractFlightPassenger::DEPORTED_BORNE_FLAG,
        ], true))) {
            $sections[] = 'offloaded';
        }
        $travelling = $this->passengers->reject(fn ($p) => in_array($p->exception_flag, [
            'flight_missed', 'boarding_denied', 'immigration_offload', 'deported',
            ContractFlightPassenger::DEPORTED_BORNE_FLAG, 'flight_cancelled', ContractFlightPassenger::OFF_FLIGHT_FLAG,
        ], true));
        if ($this->status === 'departed' || ($travelling->isNotEmpty()
            && $travelling->every(fn ($p) => in_array($p->stage, ContractFlightPassenger::FLOWN_STAGES, true)))) {
            $sections[] = 'departed';
        } elseif (in_array($this->status, ['open', 'boarding'], true)
            && ($this->passengers->isEmpty() || $travelling->isNotEmpty())) {
            $sections[] = $this->departure_at && $this->departure_at->lt($now) ? 'overdue' : 'active';
        }

        return $sections;
    }

    public function flightCategory()
    {
        return $this->belongsTo(FlightCategory::class);
    }

    public function categoryType()
    {
        return $this->belongsTo(FlightCategoryType::class, 'flight_category_type_id');
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class)->withTrashed();   // a deleted route still names old flights
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function vendor()
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function portal()
    {
        return $this->belongsTo(Portal::class);
    }

    public function costBank()
    {
        return $this->belongsTo(Bank::class, 'cost_bank_id');
    }

    public function paymentSchedules()
    {
        return $this->morphMany(PaymentSchedule::class, 'schedulable');
    }

    public function boardingOfficer()
    {
        return $this->belongsTo(FlightOfficer::class, 'boarding_officer_id');
    }

    public function immigrationOfficer()
    {
        return $this->belongsTo(FlightOfficer::class, 'immigration_officer_id');
    }

    public function passengers(): HasMany
    {
        return $this->hasMany(ContractFlightPassenger::class);
    }

    /**
     * Re-derive revenue and cost_price from the passenger rows. The same two
     * sums ManageFlightController::applyPassengerDerivedTotals() writes on a
     * flight save — kept in step with it — but callable from outside that
     * controller, because a linked PNR edited on the Ticket Purchase screen
     * changes a passenger's cost without the flight ever being saved.
     */
    public function syncMoneyFromPassengers(): void
    {
        $passengers = $this->passengers()->with('ticketPurchases')->get();
        $this->update([
            // A voided package (cancelled, deported on our side) was reversed
            // on the agent's statement — it is not this flight's revenue.
            // package_charged also takes off a cancelled return's price revision (2026-10-14).
            'revenue' => (float) $passengers->sum(fn ($p) => $p->package_charged + $p->baggageTotals()['charge'] + $p->suspendWithdrawTotals()['charge']),
            'cost_price' => (float) $passengers->sum(fn ($p) => $p->total_travel_cost),
        ]);
    }

    public function bookings()
    {
        return $this->hasMany(ContractFlightBooking::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable')->latest();
    }

    /**
     * The airline is derived from the linked ticket route (no duplicate storage).
     */
    public function getAirlineAttribute()
    {
        return $this->ticket?->airline;
    }

    /**
     * The route label is derived from the linked ticket's airports, e.g. "DAC → JED".
     */
    public function getRouteAttribute()
    {
        if (! $this->ticket) {
            return null;
        }

        $from = $this->ticket->from_airport?->code;
        $to = $this->ticket->to_airport?->code;

        return ($from && $to) ? "{$from} → {$to}" : ($this->ticket->title ?? null);
    }

    /**
     * Real profit, summed from each passenger's own agent charge minus their
     * own travel cost. Passenger rows remain the authority even though the
     * flight stores synchronized revenue and cost rollups.
     */
    public function getProfitAttribute(): float
    {
        return $this->passengers->sum('profit');
    }

    /** Sum of what every passenger's own agent is charged — the real receivable, not the flight's sale-price estimate. */
    public function getTotalAgentChargeAttribute(): float
    {
        // less a cancelled return's price revision (2026-10-14)
        return (float) $this->passengers->sum(fn ($p) => $p->chargedPackage() + $p->baggageTotals()['charge']);
    }

    /** Sum of what it actually cost to deliver every passenger's travel — ticket (real PNR price) + manpower + boarding + card fee. */
    public function getTotalTravelCostAttribute(): float
    {
        return (float) $this->passengers->sum('total_travel_cost');
    }

    public function getSeatsAvailableAttribute(): int
    {
        return max(0, $this->total_seats - $this->seats_sold);
    }

    /**
     * Agent now lives per passenger, not on the flight batch — a batch
     * commonly carries several agents' passengers at once. This is the
     * short label the flights list shows in the one "Agent" column it has.
     */
    public function getAgentSummaryAttribute(): string
    {
        $names = $this->passengers
            ->map(fn (ContractFlightPassenger $p) => $p->agent?->name)
            ->filter()
            ->unique()
            ->values();

        if ($names->isEmpty()) {
            return 'Direct';
        }

        return $names->count() > 1
            ? $names->first().' +'.($names->count() - 1).' more'
            : $names->first();
    }

    /**
     * The categories actually on board. Category moved to the passenger — a
     * batch commonly carries a Work Permit, a Student and an Umrah traveller
     * at once — so the flights list shows what is really there rather than
     * the batch default it was created from. Mirrors getAgentSummaryAttribute().
     */
    public function getCategorySummaryAttribute(): string
    {
        $names = $this->passengers
            ->map(fn (ContractFlightPassenger $p) => $p->flightCategory?->name)
            ->filter()
            ->unique()
            ->values();

        if ($names->isEmpty()) {
            return $this->flightCategory?->name ?? '—';
        }

        return $names->count() > 1
            ? $names->first().' +'.($names->count() - 1).' more'
            : $names->first();
    }

    /**
     * How ready this batch is to fly — ticketing progress, exception flags,
     * and whether an immigration officer is still missing for someone who
     * needs one. Built for the Flight Schedule board (the "who flies soon
     * and what's missing" view), reading only what's already eager-loaded on
     * the flight so this never adds a query per row.
     */
    public function getReadinessSummaryAttribute(): array
    {
        $total = $this->passengers->count();
        $ticketed = $this->passengers->filter(fn (ContractFlightPassenger $p) => $p->ticketPurchases->isNotEmpty())->count();
        $flagged = $this->passengers->filter(fn (ContractFlightPassenger $p) => (bool) $p->exception_flag)->count();

        return [
            'total' => $total,
            'ticketed' => $ticketed,
            'unticketed' => $total - $ticketed,
            'flagged' => $flagged,
            'needs_immigration_officer' => ! $this->immigration_officer_id
                && $this->passengers->contains('handling_type', 'immigration_wise'),
        ];
    }

    public static function nextFlightNumber(): string
    {
        $last = static::withTrashed()->latest('id')->first();
        $next = $last ? ($last->id + 1) : 1;

        return 'CF-'.str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }

    /* ── "Nearest flight first" ──────────────────────────────────────────
       The default order of every flight list (owner, 2026-09-21): the
       flights still to depart, soonest first; then the ones already flown,
       most recent first; undated batches last. A plain date sort puts a
       July batch above next week's departure, which is never what the desk
       is looking for. Query and collection forms below — keep them agreeing. */

    public function scopeNearestFirst($query, $column = 'departure_at')
    {
        $now = now()->toDateTimeString();

        return $query
            ->orderByRaw("({$column} IS NULL) ASC")
            ->orderByRaw("({$column} < ?) ASC", [$now])
            ->orderByRaw("CASE WHEN {$column} >= ? THEN {$column} END ASC", [$now])
            ->orderByDesc($column)
            ->orderByDesc('id');
    }

    /**
     * Sort key for a collection: upcoming rank by seconds-to-departure,
     * past by seconds-since-departure after every upcoming one, undated last.
     */
    public static function nearestFirstKey($departureAt): float
    {
        if (! $departureAt) {
            return INF;
        }
        $seconds = Carbon::parse($departureAt)->getTimestamp() - now()->getTimestamp();

        return $seconds >= 0 ? $seconds : 1e12 - $seconds;
    }

    /**
     * The route as a list of airports — [{code, flag}, …] — one entry per
     * hop, so DAC-IST-ECN is three and a direct route is two.
     *
     * The via airport exists only on the legs: from_airport/to_airport skip
     * straight past it, which is why anything drawing a route off those two
     * alone cannot flag the middle of a journey. Lives on the model because
     * both the dashboard payload and the Flights & Passengers rows need the
     * same answer — two copies would drift.
     *
     * legsArray() carries its own guard, so a server without
     * ticket_route_legs simply reads every route as direct.
     */
    public function routeHops(): array
    {
        $hops = [];

        foreach ($this->ticket?->legsArray() ?? [] as $i => $leg) {
            if ($i === 0) {
                $hops[] = ['code' => $leg['from_code'] ?? null, 'flag' => $leg['from_flag'] ?? null];
            }
            $hops[] = ['code' => $leg['to_code'] ?? null, 'flag' => $leg['to_flag'] ?? null];
        }

        if (! $hops) {
            $flag = fn ($airport) => strtolower($airport?->country?->code ?? '') ?: null;
            $hops = [
                ['code' => $this->ticket?->from_airport?->code, 'flag' => $flag($this->ticket?->from_airport)],
                ['code' => $this->ticket?->to_airport?->code, 'flag' => $flag($this->ticket?->to_airport)],
            ];
        }

        return array_values(array_filter($hops, fn ($hop) => ! empty($hop['code'])));
    }

    /** "DAC → IST → ECN", or null on a direct route where from → to says it all. */
    public function routePath(): ?string
    {
        $hops = $this->routeHops();

        return count($hops) > 2 ? implode(' → ', array_column($hops, 'code')) : null;
    }

    /**
     * Exactly what the passenger sheet needs to draw its header — the same
     * keys the dashboard payload already uses, so one sheet builder serves
     * the dashboard ledger and the Flights & Passengers table.
     */
    public function sheetFlightPayload(): array
    {
        $airport = function ($a) {
            return $a ? ['code' => $a->code, 'name' => $a->name,
                'country' => strtolower($a->country?->code ?? '') ?: null] : null;
        };

        return [
            'flight_number' => $this->flight_number,
            'airline' => $this->ticket?->airline?->name,
            'status' => $this->status,
            'from' => $airport($this->ticket?->from_airport),
            'to' => $airport($this->ticket?->to_airport),
            'route_hops' => $this->routeHops(),
            'route_path' => $this->routePath(),
            'departure_at' => $this->departure_at?->toIso8601String(),
            'arrival_at' => $this->arrival_at?->toIso8601String(),
        ];
    }
}
