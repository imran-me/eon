<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    protected $table = 'vouchers';

    protected $fillable = [
        'voucher_no',
        'date',
        'type',
        'narration',
        'total_amount',
        'created_by',
    ];

    public function details()
    {
        return $this->hasMany(VoucherDetail::class);
    }
}
