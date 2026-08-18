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
        'notes'
    ];

    protected $casts = [
        'salary_generation_date' => 'date',
        'scheduled_date' => 'date',
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

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }
}
