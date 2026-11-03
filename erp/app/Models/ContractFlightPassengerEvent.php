<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractFlightPassengerEvent extends Model
{
    protected $fillable = ['type', 'code', 'label', 'note', 'occurred_at', 'created_by'];
    protected $casts = ['occurred_at' => 'datetime'];

    public function passenger()
    {
        return $this->belongsTo(ContractFlightPassenger::class, 'contract_flight_passenger_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
