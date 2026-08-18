<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalaryReconciliation extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'salary_reconciliations';

    protected $fillable = [
        'user_id',
        'company_id',
        'service_year_number',
        'anniversary_date',
        'period_start',
        'period_end',
        'month_salary_amount',
        'leave_deduction_refund',
        'total_amount',
        'status',
        'notes',
    ];

    protected $casts = [
        'anniversary_date' => 'date',
        'period_start' => 'date',
        'period_end' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function schedules()
    {
        return $this->morphMany(PaymentSchedule::class, 'schedulable');
    }
}
