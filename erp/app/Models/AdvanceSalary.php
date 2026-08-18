<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AdvanceSalary extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'advance_salaries';

    protected $fillable = [
        'user_id',
        'amount',
        'month',
        'schedule_date',
        'reason',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function schedules()
    {
        return $this->morphMany(\App\Models\PaymentSchedule::class, 'schedulable');
    }

    /**
     * The salaries this advance was recovered from.
     *
     * An advance has no movement table of its own the way a loan does; its
     * recovery lives on the payslip that withheld it. These rows ARE the
     * repayment history, and every derived figure below reads them rather than a
     * stored running total, so a corrected payslip cannot leave the balance
     * claiming something that never happened.
     */
    public function recoveries()
    {
        return $this->hasMany(EmployeeSalary::class, 'advance_salary_id')
            ->where('advance_salary_deduction', '>', 0)
            ->orderBy('year')
            ->orderByRaw('CAST(month AS UNSIGNED)');
    }

    /** Has the money actually gone out to the employee yet? */
    public function getIsReleasedAttribute(): bool
    {
        return strtolower((string) $this->payment_status) === 'paid';
    }

    /** How much has been taken back out of salary. */
    public function getRecoveredAmountAttribute(): float
    {
        $rows = $this->relationLoaded('recoveries')
            ? $this->recoveries
            : $this->recoveries()->get();

        return round((float) $rows->sum('advance_salary_deduction'), 2);
    }

    /**
     * What the employee still owes back.
     *
     * Nothing is outstanding until the advance has been released: an approved
     * request that has not been paid out is money we owe THEM, not the other way
     * round, and counting it here would put the liability on the wrong side.
     */
    public function getOutstandingAttribute(): float
    {
        if (! $this->is_released) {
            return 0.0;
        }

        return max(0, round((float) $this->amount - $this->recovered_amount, 2));
    }

    /** Approved but not yet handed over — queued to go out. */
    public function getAwaitingReleaseAttribute(): float
    {
        return $this->is_released ? 0.0 : (float) $this->amount;
    }

    /** Released and fully recovered — nothing left to do. */
    public function getIsSettledAttribute(): bool
    {
        return $this->is_released && $this->outstanding <= 0.01;
    }

    /** How much of a released advance has come back, 0–100. */
    public function getProgressPctAttribute(): int
    {
        if (! $this->is_released || (float) $this->amount <= 0) {
            return 0;
        }

        return (int) min(100, round($this->recovered_amount / (float) $this->amount * 100));
    }

    /** Where this advance stands, in one word. */
    public function getStateAttribute(): string
    {
        if (! $this->is_released) {
            return 'awaiting';
        }

        return $this->is_settled ? 'recovered' : 'outstanding';
    }

    /** When it was last recovered from, or null while nothing has been. */
    public function getLastRecoveredOnAttribute(): ?string
    {
        $last = ($this->relationLoaded('recoveries') ? $this->recoveries : $this->recoveries()->get())->last();

        return $last?->salary_generation_date
            ? \Illuminate\Support\Carbon::parse($last->salary_generation_date)->toDateString()
            : null;
    }
}
