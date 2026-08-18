<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractFlightPassenger extends Model
{
    protected $fillable = ['contract_flight_id', 'passport_holder_id', 'document_statuses'];
    protected $casts = ['document_statuses' => 'array'];
    public function contractFlight()
    {
        return $this->belongsTo(ContractFlight::class);
    }
    public function passportHolder()
    {
        return $this->belongsTo(PassportHolder::class);
    }
}
