<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class TicketLeg extends Model
{
    protected $table = 'ticket_legs';

    protected $fillable = [
        'ticket_purchase_id',
        'from_location',
        'to_location',
        'airline_id',
        'travel_date',
        'seat_number',
        'cost',
        'attachment',
    ];

    protected $casts = [
        'cost' => 'float',
    ];

    public function purchase()
    {
        return $this->belongsTo(TicketPurchase::class);
    }

    public function airline()
    {
        return $this->belongsTo(Airline::class);
    }

    /** airline_id exists only once its migration ran — every writer asks first. */
    public static function hasAirline(): bool
    {
        static $has;
        try {
            return $has ??= Schema::hasColumn('ticket_legs', 'airline_id');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Rebuild a ticket's legs from submitted rows. A row that carries no
     * airline_id key (an editor that does not show the field) keeps the airline
     * its same sector had, so editing elsewhere never wipes it.
     */
    public static function syncFor(TicketPurchase $purchase, array $rows, bool $withSeat = false): void
    {
        $withAirline = self::hasAirline();
        $kept = [];
        if ($withAirline) {
            foreach ($purchase->legs()->get(['from_location', 'to_location', 'airline_id']) as $old) {
                $kept[strtoupper(trim((string) $old->from_location)) . '-' . strtoupper(trim((string) $old->to_location))] ??= $old->airline_id;
            }
        }
        $purchase->legs()->delete();

        foreach ($rows as $leg) {
            if (empty($leg['from_location']) && empty($leg['to_location'])) {
                continue; // an empty row the repeater left behind
            }
            $data = [
                'from_location' => $leg['from_location'] ?? null,
                'to_location'   => $leg['to_location'] ?? null,
                'travel_date'   => $leg['travel_date'] ?? null,
                'cost'          => $leg['cost'] ?? null,
            ];
            if ($withSeat) {
                $data['seat_number'] = $leg['seat_number'] ?? null;
            }
            if ($withAirline) {
                $key = strtoupper(trim((string) $data['from_location'])) . '-' . strtoupper(trim((string) $data['to_location']));
                $data['airline_id'] = array_key_exists('airline_id', $leg)
                    ? ((int) $leg['airline_id'] ?: null)
                    : ($kept[$key] ?? null);
            }
            $purchase->legs()->create($data);
        }
    }
}
