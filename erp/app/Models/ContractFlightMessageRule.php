<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Who is told what, when — one row per messaging scenario. See ContractFlightMessenger::SCENARIOS. */
class ContractFlightMessageRule extends Model
{
    protected $fillable = ['scenario', 'auto', 'to_passenger', 'to_agent', 'via_sms', 'via_email', 'passenger_template', 'agent_template'];

    protected $casts = ['auto' => 'bool', 'to_passenger' => 'bool', 'to_agent' => 'bool', 'via_sms' => 'bool', 'via_email' => 'bool'];
}
