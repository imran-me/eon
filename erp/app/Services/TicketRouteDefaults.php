<?php

namespace App\Services;

use App\Models\Airline;
use App\Models\Airport;

class TicketRouteDefaults
{
    public function values(): array
    {
        if (!config('ticket-route-defaults.enabled')) {
            return [];
        }

        return [
            'airline_id' => Airline::where('name', config('ticket-route-defaults.airline_name'))->where('status', 1)->value('id'),
            'from_airport_id' => Airport::where('code', config('ticket-route-defaults.from_code'))->value('id'),
            'to_airport_id' => Airport::where('code', config('ticket-route-defaults.to_code'))->value('id'),
        ];
    }
}
