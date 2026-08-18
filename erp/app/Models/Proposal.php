<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Proposal extends Model
{
    protected $table = 'proposals';
    
    protected $fillable = [
        'deal_id',
        'proposal_no',
        'proposal_date',
        'valid_until',
        'status',
        'terms',
        'description',
    ];

    public function deal()
    {
        return $this->belongsTo(Deal::class);
    }
}
