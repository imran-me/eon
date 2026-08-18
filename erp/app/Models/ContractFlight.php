<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContractFlight extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'flight_number',
        'flight_category_id',
        'flight_category_type_id',
        'handling_type',
        'ticket_class',
        'ticket_id',
        'airline_flight_no',
        'departure_at',
        'arrival_at',
        'total_seats',
        'seats_sold',
        'cost_price',
        'due_amount',
        'cost_paid_amount',
        'purchase_date',
        'payable_date',
        'revenue',
        'agent_id',
        'vendor_id',
        'portal_id',
        'cost_bank_id',
        'boarding_officer_id',
        'immigration_officer_id',
        'ticket_cost_per_pax',
        'manpower_cost_per_pax',
        'boarding_cost_per_pax',
        'immigration_cost_per_pax',
        'sale_price_per_pax',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'departure_at' => 'datetime',
        'arrival_at' => 'datetime',
        'total_seats'  => 'integer',
        'seats_sold'   => 'integer',
        'cost_price'   => 'float',
        'due_amount'   => 'float',
        'cost_paid_amount' => 'float',
        'purchase_date' => 'date',
        'payable_date' => 'date',
        'revenue'      => 'float',
        'ticket_cost_per_pax' => 'float',
        'manpower_cost_per_pax' => 'float',
        'boarding_cost_per_pax' => 'float',
        'immigration_cost_per_pax' => 'float',
        'sale_price_per_pax' => 'float',
    ];

    protected $appends = [
        'airline',
        'route',
        'profit',
        'seats_available',
    ];

    public function flightCategory()
    {
        return $this->belongsTo(FlightCategory::class);
    }

    public function categoryType()
    {
        return $this->belongsTo(FlightCategoryType::class, 'flight_category_type_id');
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function vendor()
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function portal()
    {
        return $this->belongsTo(Portal::class);
    }

    public function costBank()
    {
        return $this->belongsTo(Bank::class, 'cost_bank_id');
    }

    public function paymentSchedules()
    {
        return $this->morphMany(PaymentSchedule::class, 'schedulable');
    }

    public function boardingOfficer()
    {
        return $this->belongsTo(FlightOfficer::class, 'boarding_officer_id');
    }
    public function immigrationOfficer()
    {
        return $this->belongsTo(FlightOfficer::class, 'immigration_officer_id');
    }
    public function passengers()
    {
        return $this->hasMany(ContractFlightPassenger::class);
    }

    public function bookings()
    {
        return $this->hasMany(ContractFlightBooking::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable')->latest();
    }

    /**
     * The airline is derived from the linked ticket route (no duplicate storage).
     */
    public function getAirlineAttribute()
    {
        return $this->ticket?->airline;
    }

    /**
     * The route label is derived from the linked ticket's airports, e.g. "DAC → JED".
     */
    public function getRouteAttribute()
    {
        if (!$this->ticket) {
            return null;
        }

        $from = $this->ticket->from_airport?->code;
        $to   = $this->ticket->to_airport?->code;

        return ($from && $to) ? "{$from} → {$to}" : ($this->ticket->title ?? null);
    }

    public function getProfitAttribute(): float
    {
        return $this->revenue - $this->cost_price;
    }

    public function getSeatsAvailableAttribute(): int
    {
        return max(0, $this->total_seats - $this->seats_sold);
    }

    public static function nextFlightNumber(): string
    {
        $last = static::withTrashed()->latest('id')->first();
        $next = $last ? ($last->id + 1) : 1;
        return 'CF-' . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }
}
