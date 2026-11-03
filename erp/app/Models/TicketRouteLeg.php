<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

/**
 * One leg of a ticket route with a via (DAC → IST → ECN): one row per
 * sector, in order, each with its own airline, vendor/portal and price —
 * it is bought as its own ticket. A direct route has none.
 * See Ticket::routeLegs() / legsArray() / legCount().
 */
class TicketRouteLeg extends Model
{
    protected $fillable = ['ticket_id', 'sequence', 'airline_id', 'vendor_id', 'portal_id', 'from_airport_id', 'to_airport_id', 'stops', 'cost'];

    // stops = transit airport ids inside this ticket, in order (2026-10-11); null = none
    protected $casts = ['sequence' => 'integer', 'cost' => 'float', 'stops' => 'array'];

    /** stops exists only once its migration ran — writers ask first. */
    public static function hasStops(): bool
    {
        static $has = null;
        try {
            return $has ??= Schema::hasColumn('ticket_route_legs', 'stops');
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class)->withTrashed();
    }

    /** @return BelongsTo<Airline, $this> */
    public function airline(): BelongsTo
    {
        return $this->belongsTo(Airline::class);
    }

    /** @return BelongsTo<User, $this> */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    /** @return BelongsTo<Portal, $this> */
    public function portal(): BelongsTo
    {
        return $this->belongsTo(Portal::class);
    }

    /** @return BelongsTo<Airport, $this> */
    public function fromAirport(): BelongsTo
    {
        return $this->belongsTo(Airport::class, 'from_airport_id');
    }

    /** @return BelongsTo<Airport, $this> */
    public function toAirport(): BelongsTo
    {
        return $this->belongsTo(Airport::class, 'to_airport_id');
    }

    /** "DAC → IST" */
    public function getSectorAttribute(): string
    {
        return ($this->fromAirport?->code ?? '—').' → '.($this->toAirport?->code ?? '—');
    }
}
