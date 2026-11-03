<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeSalary extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'employee_salaries';

    protected $fillable = [
        'user_id',
        'salary_template_id',
        'loan_id',
        'advance_salary_id',
        'month',
        'year',
        'bonus_label',
        'bonus_amount',
        'gross_salary',
        'loan_deduction',
        'advance_salary_deduction',
        'absent_deduction',
        'leave_deduction',
        'late_deduction',
        'early_leave_deduction',
        'salary_adjustment',
        'over_time',
        'over_time_days',
        'overtime_salary',
        'total_deductions',
        'net_salary',
        'salary_generation_date',
        'scheduled_date',
        'payment_method',
        'bank_id',
        'status',
        'notes',
        'summary_snapshot',
    ];

    protected $casts = [
        'salary_generation_date' => 'date',
        'scheduled_date' => 'date',
        // Frozen attendance/task figures for the month, written once by
        // PayrollService::issuePayslip(). Null on every salary row created
        // before that snapshot existed - renderers must treat it as optional.
        'summary_snapshot' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function salary_template()
    {
        return $this->belongsTo(SalaryTemplate::class);
    }

    public function schedules()
    {
        return $this->morphMany(PaymentSchedule::class, 'schedulable');
    }

    public function leaveEncashments()
    {
        return $this->hasMany(SalaryReconciliation::class);
    }

    public function paymentAllocations()
    {
        return $this->hasMany(EmployeePaymentAllocation::class);
    }

    public function allocatedPaid(): float
    {
        return EmployeePaymentAllocation::paidForSalary($this->id);
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }
}
