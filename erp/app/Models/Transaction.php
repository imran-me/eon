<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $table = 'transactions';

    protected $fillable = [
        'user_id',
        'type',
        'user_type',
        'account_id',
        'payment_date',
        'reference_no',
        'payment_method',
        'invoice_id',
        'old_balance',
        'debit',
        'credit',
        'balance',
        'remarks'
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function account()
    {
        return $this->belongsTo(Bank::class, 'account_id', 'id');
    }
}
