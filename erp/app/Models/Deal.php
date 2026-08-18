<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Deal extends Model
{
    protected $table = 'deals';
    
    protected $fillable = [
        'lead_id',
        'deal_agent',
        'deal_watcher',
        'product_id',
        'deal_name',
        'stage',
        'amount',
        'closing_date',
        'notes',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function dealAgent()
    {
        return $this->belongsTo(User::class, 'deal_agent');
    }

    public function dealWatcher()
    {
        return $this->belongsTo(User::class, 'deal_watcher');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
