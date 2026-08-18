<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContractFlightBooking extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'booking_number',
        'company_id',
        'contract_flight_id',
        'client_id',
        'seats',
        'unit_price',
        'total_amount',
        'paid_amount',
        'due_amount',
        'receivable_date',
        'payment_status',
        'payment_method',
        'bank_id',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'seats' => 'integer',
        'unit_price' => 'float',
        'total_amount' => 'float',
        'paid_amount' => 'float',
        'due_amount' => 'float',
        'receivable_date' => 'date',
    ];

    public function contractFlight()
    {
        return $this->belongsTo(ContractFlight::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function items()
    {
        return $this->hasMany(ContractFlightBookingItem::class);
    }

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class, 'bank_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable')->latest();
    }

    public function paymentSchedules()
    {
        return $this->morphMany(PaymentSchedule::class, 'schedulable');
    }

    public static function nextBookingNumber(): string
    {
        $last = static::withTrashed()->latest('id')->first();
        $next = $last ? ($last->id + 1) : 1;
        return 'FB-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
