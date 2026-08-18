<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeadAirTicket extends Model
{
    use HasFactory;

    protected $table = 'lead_air_tickets';

    protected $fillable = [
        'lead_id',
        'route_from',
        'route_to',
        'travel_date',
        'pax_count',
        'ticket_class',
        'preferred_airline',
        'quoted_price',
        'payment_status',
    ];

    protected $casts = [
        'travel_date'  => 'date',
        'quoted_price' => 'decimal:2',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function getRouteDisplayAttribute(): string
    {
        if ($this->route_from && $this->route_to) {
            return strtoupper($this->route_from) . ' → ' . strtoupper($this->route_to);
        }
        return '-';
    }
}
