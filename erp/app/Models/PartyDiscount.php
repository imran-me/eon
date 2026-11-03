<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartyDiscount extends Model
{
    protected $fillable = [
        'company_id', 'user_id', 'document_type', 'document_id', 'charge_transaction_id',
        'amount', 'discount_date', 'reason', 'request_key', 'batch_key', 'created_by',
        'journal_entry_id', 'reversal_journal_entry_id', 'reversed_by', 'reversed_at', 'reversal_reason',
    ];

    protected $casts = ['amount' => 'decimal:2', 'discount_date' => 'date', 'reversed_at' => 'datetime'];
}
