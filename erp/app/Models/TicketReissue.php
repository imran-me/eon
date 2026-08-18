<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TicketReissue extends Model
{
    use SoftDeletes;

    protected $table = 'ticket_reissues';

    protected $fillable = [
        'company_id', 'original_sale_id', 'original_purchase_id', 'agent_id',
        'new_ticket_id', 'new_vendor_id', 'new_portal_id', 'bank_id',
        'original_invoice', 'new_ticket_no', 'new_trip_type',
        'reissue_date', 'new_travel_date', 'new_purchase_date',
        'org_cost', 'penalty', 'fare_diff', 'new_cost', 'service_charge', 'new_sale_price',
        'paid_amount', 'due_amount', 'payment_status', 'pay_method',
        'status', 'currency', 'attachment', 'created_by', 'updated_by',
    ];

    public function originalSale()
    {
        return $this->belongsTo(TicketSale::class, 'original_sale_id');
    }

    public function originalPurchase()
    {
        return $this->belongsTo(TicketPurchase::class, 'original_purchase_id');
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function newTicket()
    {
        return $this->belongsTo(Ticket::class, 'new_ticket_id');
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class, 'bank_id');
    }
}
