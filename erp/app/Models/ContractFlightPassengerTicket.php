<?php

namespace App\Models;

use App\Services\FlightPackageTicketBilling;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Facades\DB;

class ContractFlightPassengerTicket extends Pivot
{
    protected $table = 'contract_flight_passenger_tickets';

    protected static function booted(): void
    {
        static::saved(fn (self $pivot) => app(FlightPackageTicketBilling::class)->forTicket((int) $pivot->ticket_purchase_id));
        static::deleted(fn (self $pivot) => app(FlightPackageTicketBilling::class)->forTicket((int) $pivot->ticket_purchase_id));
    }

    public function save(array $options = [])
    {
        return DB::transaction(fn () => parent::save($options));
    }

    public function delete()
    {
        return DB::transaction(fn () => parent::delete());
    }
}
