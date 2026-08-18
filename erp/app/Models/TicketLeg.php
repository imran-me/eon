<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketLeg extends Model
{
    protected $table = 'ticket_legs';

    protected $fillable = [
        'ticket_purchase_id',
        'from_location',
        'to_location',
        'travel_date',
        'seat_number',
        'attachment',
    ];

    public function purchase()
    {
        return $this->belongsTo(TicketPurchase::class);
    }
}
