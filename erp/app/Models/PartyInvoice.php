<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartyInvoice extends Model
{
    protected $fillable = [
        'user_id', 'invoice_number', 'service_description',
        'country_id', 'no_of_candidates', 'amount',
        'due_date', 'notes', 'created_by',
    ];

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
