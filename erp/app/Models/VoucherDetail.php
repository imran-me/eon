<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoucherDetail extends Model
{
    protected $table = 'voucher_details';

    protected $fillable = [
        'voucher_id',
        'account_id',
        'debit',
        'credit',
        'description',
    ];

    public function chartOfAccount()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }
}
