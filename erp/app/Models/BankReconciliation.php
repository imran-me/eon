<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankReconciliation extends Model
{
    use SoftDeletes;

    protected $table = 'bank_reconciliations';

    protected $fillable = [
        'company_id',
        'bank_id',
        'date',
        'actual_closing_balance',
        'note',
        'created_by',
    ];

    protected $casts = [
        'date'                   => 'date',
        'actual_closing_balance' => 'decimal:2',
    ];

    public function bank()
    {
        return $this->belongsTo(Bank::class, 'bank_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
