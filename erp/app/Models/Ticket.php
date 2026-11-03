<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

/**
 * @property string|null $trip_type 'two-way' = a round-trip route (guarded migration 2026_10_18_000002)
 */
class Ticket extends Model
{
    use HasFactory, \Illuminate\Database\Eloquent\SoftDeletes;

    protected $table = 'tickets';

    protected $fillable = [
        'title',
        'vendor_id',
        'portal_id',
        'airline_id',
        'from_airport_id',
        'to_airport_id',
        'trip_type',
        'price',
        'qty',
        'total_sale_qty',
        'total_sale_amount',
        'status',
    ];

    /** @return BelongsTo<User, $this> */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id', 'id');
    }

    /** @return BelongsTo<Portal, $this> */
    public function portal(): BelongsTo
    {
        return $this->belongsTo(Portal::class, 'portal_id', 'id');
    }

    /** @return BelongsTo<Airline, $this> */
    public function airline(): BelongsTo
    {
        return $this->belongsTo(Airline::class, 'airline_id', 'id');
    }

    /** @return BelongsTo<Airport, $this> */
    public function from_airport(): BelongsTo
    {
        return $this->belongsTo(Airport::class, 'from_airport_id', 'id');
    }

    /** @return BelongsTo<Airport, $this> */
    public function to_airport(): BelongsTo
    {
        return $this->belongsTo(Airport::class, 'to_airport_id', 'id');
    }

    /** @return HasMany<TicketSale, $this> */
    public function ticket_sales(): HasMany
    {
        return $this->hasMany(TicketSale::class);
    }

    /** @return HasMany<TicketPurchase, $this> */
    public function ticket_purchases(): HasMany
    {
        return $this->hasMany(TicketPurchase::class);
    }

    /* ── Legs ──────────────────────────────────────────────────────────
       A route with stops (DAC → IST → ECN) is one leg per sector, in order,
       each with its own default cost (owner, 2026-09-21). A direct route has
       no legs and counts as one ticket. Guarded: on a server where the
       ticket_route_legs migration has not run, every route reads as direct
       rather than the page failing. */

    /** @return HasMany<TicketRouteLeg, $this> */
    public function routeLegs(): HasMany
    {
        return $this->hasMany(TicketRouteLeg::class)->orderBy('sequence');
    }

    private static ?bool $legsTableExists = null;

    public static function legsAvailable(): bool
    {
        if (self::$legsTableExists === null) {
            try {
                self::$legsTableExists = Schema::hasTable('ticket_route_legs');
            } catch (\Throwable) {
                self::$legsTableExists = false;
            }
        }

        return self::$legsTableExists;
    }

    /* ── Round trip (owner, 2026-10-18) ────────────────────────────────
       From / To stay origin → destination. Return on the same ticket(s): no
       extra legs, every ticket of the route is a round-trip ticket. Separate
       return: the return tickets (destination → origin) follow the outbound
       ones in ticket_route_legs — legsArray() marks them 'direction' => 'return'.
       Guarded: before the migration every route reads as one-way. */

    private static ?bool $tripColumnExists = null;

    public static function roundTripAvailable(): bool
    {
        if (self::$tripColumnExists === null) {
            try {
                self::$tripColumnExists = Schema::hasColumn('tickets', 'trip_type');
            } catch (\Throwable) {
                self::$tripColumnExists = false;
            }
        }

        return self::$tripColumnExists;
    }

    public function isRoundTrip(): bool
    {
        return self::roundTripAvailable() && $this->trip_type === 'two-way';
    }

    /** Round trip with its return as separate ticket(s) — the route carries return legs. */
    public function hasSeparateReturn(): bool
    {
        return $this->isRoundTrip() && collect($this->legsArray())->contains('direction', 'return');
    }

    /** The outbound legs only (every leg on a one-way route). */
    public function outboundLegs(): array
    {
        return array_values(array_filter($this->legsArray(), fn ($l) => ($l['direction'] ?? 'out') === 'out'));
    }

    /** The legs as plain arrays (id, sequence, codes, names, cost); [] when direct. */
    public function legsArray(): array
    {
        if (! self::legsAvailable()) {
            return [];
        }
        try {
            $this->loadMissing('routeLegs.fromAirport.country', 'routeLegs.toAirport.country', 'routeLegs.airline', 'routeLegs.vendor', 'routeLegs.portal');
            // on a round trip, the legs after the one that reaches the destination are the return
            $out = true;
            $dest = (int) $this->to_airport_id;
            $round = $this->isRoundTrip();
            $direction = function ($l) use (&$out, $dest, $round) {
                $d = $out ? 'out' : 'return';
                if ($round && $out && (int) $l->to_airport_id === $dest) {
                    $out = false;
                }

                return $d;
            };

            return $this->routeLegs->map(fn ($l) => [
                'direction' => $direction($l),
                'id' => $l->id, 'sequence' => $l->sequence,
                // each leg is its own ticket: its own airline, seller and price
                'airline_id' => $l->airline_id, 'airline' => $l->airline?->name,
                'vendor_id' => $l->vendor_id, 'vendor' => $l->vendor?->name,
                'portal_id' => $l->portal_id, 'portal' => $l->portal?->name,
                'from_id' => $l->from_airport_id, 'to_id' => $l->to_airport_id,
                'from_code' => $l->fromAirport?->code, 'to_code' => $l->toAirport?->code,
                'from_name' => $l->fromAirport?->name, 'to_name' => $l->toAirport?->name,
                // country flag keys (public/flags/{cc}.png) for the UI
                'from_flag' => strtolower($l->fromAirport?->country?->code ?? ''), 'to_flag' => strtolower($l->toAirport?->country?->code ?? ''),
                'sector' => $l->sector, 'cost' => $l->cost,
                // transit airports inside this ticket (2026-10-11) and the full path for display
                'stops' => $stops = self::stopAirports($l->stops ?? []),
                'path' => implode(' → ', array_filter(array_merge([$l->fromAirport?->code], array_column($stops, 'code'), [$l->toAirport?->code]))),
            ])->values()->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /** [{id, code, name, flag}] for a list of airport ids, in the given order. */
    private static function stopAirports($ids): array
    {
        $ids = array_values(array_filter(array_map('intval', (array) $ids)));
        if (! $ids) {
            return [];
        }
        try {
            $rows = Airport::with('country')->whereIn('id', $ids)->get()->keyBy('id');
        } catch (\Throwable) {
            return [];
        }

        return array_values(array_filter(array_map(fn ($id) => isset($rows[$id]) ? [
            'id' => $id, 'code' => $rows[$id]->code, 'name' => $rows[$id]->name,
            'flag' => strtolower($rows[$id]->country?->code ?? ''),
        ] : null, $ids)));
    }

    /** How many tickets the route takes — one, or one per leg. */
    public function legCount(): int
    {
        return max(1, count($this->legsArray()));
    }

    /** The via airport id of a two-leg route, null when direct (outbound — a return ticket is no via). */
    public function viaAirportId(): ?int
    {
        $legs = $this->outboundLegs();

        return count($legs) > 1 ? ($legs[0]['to_id'] ?? null) : null;
    }

    /** "via IST" / "via IST, DXB" for a route with stops, '' when direct (outbound stops only). */
    public function viaLabel(): string
    {
        $legs = $this->outboundLegs();
        if (count($legs) < 2) {
            return '';
        }
        $stops = array_slice(array_map(fn ($l) => $l['to_code'] ?? '?', $legs), 0, -1);

        return 'via '.implode(', ', $stops);
    }
}
