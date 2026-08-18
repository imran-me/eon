<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One instalment row of a financing arrangement's repayment schedule.
 *
 * Generated up front from the loan's principal, rate, method and tenure, then
 * settled as money arrives. No journal is written — see FinancingLoan.
 */
class FinancingSchedule extends Model
{
    protected $fillable = [
        'financing_loan_id', 'instalment_no', 'due_date',
        'principal_component', 'interest_component', 'total_amount',
        'paid_amount', 'paid_date', 'status',
    ];

    protected $casts = [
        'due_date'            => 'date',
        'paid_date'           => 'date',
        'principal_component' => 'decimal:2',
        'interest_component'  => 'decimal:2',
        'total_amount'        => 'decimal:2',
        'paid_amount'         => 'decimal:2',
        'instalment_no'       => 'integer',
    ];

    public function loan()
    {
        return $this->belongsTo(FinancingLoan::class, 'financing_loan_id');
    }

    public function transactions()
    {
        return $this->hasMany(FinancingTransaction::class, 'financing_schedule_id');
    }

    public function getBalanceAttribute(): float
    {
        return max(0, round((float) $this->total_amount - (float) $this->paid_amount, 2));
    }

    /**
     * Past its due date and not yet settled. Read from the date rather than
     * stored as a status, so a row becomes overdue on its own without a nightly
     * job having had to run.
     */
    public function getIsOverdueAttribute(): bool
    {
        return in_array($this->status, ['due', 'partial'], true)
            && $this->due_date
            && $this->due_date->isPast();
    }
}
