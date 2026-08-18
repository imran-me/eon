<?php

namespace App\Models\Log;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class CustomerLog extends Model
{
    protected $fillable = ['customer_id', 'changed_by', 'action', 'before', 'after'];

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
    ];

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
