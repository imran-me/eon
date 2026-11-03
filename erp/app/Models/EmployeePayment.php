<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeePayment extends Model
{
    protected $fillable = [
        'user_id', 'company_id', 'bank_id', 'payment_date', 'transaction_time', 'payment_method',
        'recipient_bank', 'recipient_account',
        'reference', 'amount', 'bank_charge_amount', 'bank_charge_account_id', 'note', 'status', 'created_by', 'reversed_by',
        'reversed_at', 'reversal_reason',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'reversed_at' => 'datetime',
        'bank_charge_amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $payment) {
            $allowed = ['status', 'reversed_by', 'reversed_at', 'reversal_reason', 'updated_at'];
            if (array_diff(array_keys($payment->getDirty()), $allowed)) {
                throw new \LogicException('Posted employee payments are immutable; reverse the payment instead.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Employee payments cannot be deleted; reverse the payment instead.'));
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    public function allocations()
    {
        return $this->hasMany(EmployeePaymentAllocation::class);
    }
}
