<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartyInvoiceItem extends Model
{
    protected $fillable = ['service_type', 'description', 'passenger_name', 'quantity', 'unit_price', 'amount', 'revenue_account_code'];

    protected $casts = ['quantity' => 'decimal:2', 'unit_price' => 'decimal:2', 'amount' => 'decimal:2'];

    public function invoice()
    {
        return $this->belongsTo(PartyInvoice::class, 'party_invoice_id');
    }
}
