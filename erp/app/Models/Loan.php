<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Loan extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'loans';

    protected $fillable = [
        'user_id',
        'bank_id',
        'amount',    
        'remaining_amount',
        'opening_paid_amount',
        'monthly_deduction',    
        'status',    
        'start_date',    
        'end_date'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function bank()
    {
        return $this->belongsTo(Bank::class, 'bank_id');
    }

    // Deliberately uncast: start_date / end_date are read as plain Y-m-d strings
    // by the edit modal's date inputs and by the Api\Admin\LoanController's JSON,
    // both of which a Carbon cast would silently reshape.

    public function transactions()
    {
        return $this->hasMany(LoanTransaction::class)->orderBy('date')->orderBy('id');
    }

    public function repayments()
    {
        return $this->hasMany(LoanTransaction::class)->where('type', 'repay');
    }

    public function disbursement()
    {
        return $this->hasOne(LoanTransaction::class)->where('type', 'disburse');
    }

    /**
     * Total repaid against this loan.
     *
     * opening_paid_amount covers whatever was already repaid before this system
     * started recording individual movements; everything after it comes from the
     * rows. Adding the two is what stops an older loan's balance jumping backwards
     * the first time a repayment is recorded against it.
     */
    public function getPaidAmountAttribute(): float
    {
        $recorded = $this->relationLoaded('transactions')
            ? $this->transactions->where('type', 'repay')->sum('amount')
            : $this->repayments()->sum('amount');

        return round((float) $this->opening_paid_amount + (float) $recorded, 2);
    }

    /** What the employee still owes. */
    public function getOutstandingAttribute(): float
    {
        return max(0, round((float) $this->amount - $this->paid_amount, 2));
    }

    /** Repaid split by how the money came back, for the "Repaid via" column. */
    public function repaidByMethod(): array
    {
        $rows = $this->relationLoaded('transactions')
            ? $this->transactions->where('type', 'repay')
            : $this->repayments()->get();

        return [
            'salary' => (float) $rows->where('method', 'salary')->sum('amount'),
            'cash'   => (float) $rows->whereIn('method', ['cash', 'bank'])->sum('amount'),
        ];
    }

    /**
     * Settled — nothing left to collect.
     *
     * Read off the money rather than off `status`, because status is a cache that
     * an older hand-edited loan may never have had written to it.
     */
    public function getIsClearedAttribute(): bool
    {
        return $this->outstanding <= 0.01;
    }

    /** The repayment rows only, oldest first — the loan's own payment history. */
    public function getPaymentRowsAttribute()
    {
        return ($this->relationLoaded('transactions')
            ? $this->transactions->where('type', 'repay')
            : $this->repayments()->orderBy('date')->orderBy('id')->get())
            ->sortBy([['date', 'asc'], ['id', 'asc']])
            ->values();
    }

    /** When money last came back, or null while nothing has. */
    public function getLastPaidOnAttribute(): ?string
    {
        $last = $this->payment_rows->last();

        return $last ? \Illuminate\Support\Carbon::parse($last->date)->toDateString() : null;
    }

    /**
     * The date the loan was cleared — the payment that took it to zero.
     *
     * Falls back to end_date for a loan cleared before this system recorded
     * individual payments (its whole repayment sits in opening_paid_amount).
     */
    public function getClearedOnAttribute(): ?string
    {
        if (! $this->is_cleared) {
            return null;
        }

        return $this->last_paid_on ?: ($this->end_date ?: null);
    }

    /** How much of the loan has come back, 0–100. */
    public function getProgressPctAttribute(): int
    {
        if ((float) $this->amount <= 0) {
            return 0;
        }

        return (int) min(100, round($this->paid_amount / (float) $this->amount * 100));
    }

    /** Instalments still to run at the current EMI, or 0 when there is no plan. */
    public function getInstalmentsLeftAttribute(): int
    {
        $emi = (float) $this->monthly_deduction;

        return ($emi > 0 && $this->outstanding > 0) ? (int) ceil($this->outstanding / $emi) : 0;
    }

    /**
     * Length of the EMI plan in months, from the dates the loan was booked with.
     *
     * Null means the loan was disbursed without a schedule — it comes back only
     * when someone records a repayment by hand.
     */
    public function getEmiMonthsAttribute(): ?int
    {
        if (! $this->start_date || ! $this->end_date || (float) $this->monthly_deduction <= 0) {
            return null;
        }

        $months = \Illuminate\Support\Carbon::parse($this->start_date)
            ->diffInMonths(\Illuminate\Support\Carbon::parse($this->end_date));

        return $months > 0 ? (int) $months : null;
    }
}
