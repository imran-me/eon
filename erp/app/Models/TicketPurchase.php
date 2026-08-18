<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketPurchase extends Model
{

    protected $table = 'ticket_purchases';

    protected $fillable = [
        'passport_holder_id',
        'vendor_id',
        'portal_id',
        'bank_id',
        'ticket_id',
        'ticket_type', // 'air', 'bus', 'train', 'other'
        'trip_type', // 'one-way', 'two-way'
        'ticket_no',
        'source', // 'direct_sale' when created via Ticketing (Direct Sale) form, null otherwise
        // 'from_location',
        // 'to_location',
        // 'travel_date',
        // 'airline_or_operator',
        // 'seat_number',
        // 'description',
        'purchase_date',
        'amount',
        'currency',
        'status', // 'draft', 'pending', 'confirm', 'cancelled'
        'attachment',
        'transaction_id',
        'created_by',
        'updated_by',
        'paid_amount',
        'due_amount',
        'payment_status',
        'company_id',
    ];
    
    public function passportHolder()
    {
        return $this->belongsTo(PassportHolder::class, 'passport_holder_id');
    }

    public function vendor()
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function portal()
    {
        return $this->belongsTo(Portal::class, 'portal_id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'invoice_id');
    }
    
    public function portalBalances()
    {
        return $this->hasMany(PortalBalance::class, 'invoice_id');
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class, 'bank_id');
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    // public function transaction()
    // {
    //     return $this->belongsTo(Transaction::class, 'transaction_id');
    // }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
    
    public function legs()
    {
        return $this->hasMany(TicketLeg::class, 'ticket_purchase_id');
    }

    public function schedules()
    {
        return $this->morphMany(PaymentSchedule::class, 'schedulable');
    }
}
