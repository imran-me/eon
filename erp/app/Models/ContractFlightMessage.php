<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** The log: one row per message per recipient per channel, sent or not. */
class ContractFlightMessage extends Model
{
    protected $fillable = [
        'scenario', 'trigger', 'contract_flight_id', 'contract_flight_passenger_id', 'recipient_type', 'recipient_id',
        'recipient_name', 'channel', 'to', 'subject', 'body', 'status', 'error', 'sent_by',
    ];

    public function flight()
    {
        return $this->belongsTo(ContractFlight::class, 'contract_flight_id');
    }

    public function passenger()
    {
        return $this->belongsTo(ContractFlightPassenger::class, 'contract_flight_passenger_id');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
