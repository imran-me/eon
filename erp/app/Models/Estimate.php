<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Estimate extends Model
{
    protected $fillable = [
        'deal_id',
        'estimate_no',
        'estimate_date',
        'valid_until',
        'status',
        'total_amount',
        'description',
    ];

    protected $casts = [
        'estimate_date' => 'date',
        'valid_until' => 'date',
    ];

    public function deal()
    {
        return $this->belongsTo(Deal::class);
    }
}
