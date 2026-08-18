<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequestRecovery extends Model
{
    protected $table = 'request_recoveries';

    protected $fillable = [
        'request_id',
        'payslip_id',
        'installment_no',
        'deducted_amount',
        'remaining_balance',
        'deducted_at',
        'note',
    ];

    protected $casts = [
        'deducted_at' => 'date',
    ];

    public function request()
    {
        return $this->belongsTo(EmployeeRequest::class, 'request_id');
    }

    public function payslip()
    {
        return $this->belongsTo(\App\Models\Payslip::class ?? \App\Models\EmployeeSalary::class, 'payslip_id');
    }
}
