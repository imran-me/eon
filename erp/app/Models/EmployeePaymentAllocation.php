<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EmployeePaymentAllocation extends Model
{
    protected $fillable = ['employee_payment_id', 'employee_salary_id', 'amount'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Payment allocations are immutable.'));
        static::deleting(fn () => throw new \LogicException('Payment allocations are immutable.'));
    }

    public function payment()
    {
        return $this->belongsTo(EmployeePayment::class, 'employee_payment_id');
    }

    public function salary()
    {
        return $this->belongsTo(EmployeeSalary::class, 'employee_salary_id');
    }

    public static function postedTotalsSubquery()
    {
        return DB::table('employee_payment_allocations as epa')
            ->join('employee_payments as ep', 'ep.id', '=', 'epa.employee_payment_id')
            ->where('ep.status', 'posted')
            ->groupBy('epa.employee_salary_id')
            ->selectRaw('epa.employee_salary_id as schedulable_id, SUM(epa.amount) as paid_total');
    }

    public static function paidForSalary(int $salaryId): float
    {
        return round((float) static::query()
            ->where('employee_salary_id', $salaryId)
            ->whereHas('payment', fn ($q) => $q->where('status', 'posted'))
            ->sum('amount'), 2);
    }
}
