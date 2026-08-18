<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractFlightBookingItem extends Model
{
    protected $fillable = [
        'contract_flight_booking_id',
        'contract_flight_id',
        'seats',
        'unit_price',
        'total_amount',
    ];

    protected $casts = [
        'seats' => 'integer',
        'unit_price' => 'float',
        'total_amount' => 'float',
    ];

    public function booking()
    {
        return $this->belongsTo(ContractFlightBooking::class, 'contract_flight_booking_id');
    }

    public function contractFlight()
    {
        return $this->belongsTo(ContractFlight::class);
    }
}
