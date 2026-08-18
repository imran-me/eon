<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TicketRefund extends Model
{
    use SoftDeletes;

    protected $table = 'ticket_refunds';

    protected $fillable = [
        'company_id', 'ticket_sale_id', 'agent_id', 'vendor_id', 'portal_id', 'bank_id',
        'original_invoice', 'refund_ref_no', 'refund_date',
        'org_cost', 'airline_refund', 'penalty', 'service_charge', 'org_sale', 'net_refund',
        'paid_amount', 'due_amount', 'payment_status', 'pay_method',
        'status', 'currency', 'attachment', 'created_by', 'updated_by',
    ];

    public function sale()
    {
        return $this->belongsTo(TicketSale::class, 'ticket_sale_id');
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class, 'bank_id');
    }
}
