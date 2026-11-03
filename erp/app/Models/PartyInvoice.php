<?php

namespace App\Models;

use App\Traits\HasPartyDiscounts;
use Illuminate\Database\Eloquent\Model;

class PartyInvoice extends Model
{
    use HasPartyDiscounts;

    protected $fillable = [
        'company_id', 'user_id', 'invoice_number', 'invoice_date', 'invoice_time', 'service_description',
        'country_id', 'no_of_candidates', 'amount',
        'subtotal', 'discount_amount', 'tax_amount', 'paid_amount', 'due_amount',
        'payment_status', 'journal_entry_id', 'due_date', 'notes', 'created_by',
    ];

    protected $casts = ['invoice_date' => 'date', 'due_date' => 'date'];

    public function items()
    {
        return $this->hasMany(PartyInvoiceItem::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
