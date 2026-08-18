<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One movement on a financing arrangement — the money that actually changed
 * hands, as opposed to the schedule, which is only what was expected.
 *
 * Every balance shown on the desk is summed from these rows.
 *
 * A movement on a BORROWING also reaches the ledger, written by
 * App\Services\FinancingPostingService. Two records of that link exist and both
 * are wanted: the entry carries source/source_id back to this row — the ERP's
 * own convention, and what the idempotency and reversal checks read — while
 * `journal_entry_id` holds the same answer on this side, so a register printing
 * a hundred rows can show which ones posted without a lookup per row.
 *
 * The column is only ever written from the entry the service returned, never
 * assembled by hand, so the two cannot drift apart.
 */
class FinancingTransaction extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'financing_loan_id', 'financing_schedule_id', 'type', 'date',
        'amount', 'principal_part', 'interest_part', 'fee_part', 'tds_amount',
        'method', 'paid_by', 'bank_id', 'journal_entry_id', 'memo', 'created_by',
    ];

    protected $casts = [
        'date'           => 'date',
        'amount'         => 'decimal:2',
        'principal_part' => 'decimal:2',
        'interest_part'  => 'decimal:2',
        'fee_part'       => 'decimal:2',
        'tds_amount'     => 'decimal:2',
    ];

    /**
     * Did this movement take money out of a company account?
     *
     * The question only has a real answer on a loan in someone's own name, where
     * `paid_by` was asked. Everywhere else the company is the payer by
     * definition, so an unanswered column reads as company money.
     *
     * Null on a personal loan means nobody said — and that is NOT the same as
     * "the company paid". It returns false there so an unanswered form can never
     * be read as a withdrawal that happened.
     */
    public function getIsCompanyMoneyAttribute(): bool
    {
        if ($this->loan?->taken_for !== 'personal') {
            return true;
        }

        return $this->paid_by === 'company';
    }

    /** What the payment settled, once the lender's charge is taken off the top. */
    public function getAppliedAmountAttribute(): float
    {
        return round((float) $this->amount - (float) $this->fee_part, 2);
    }

    public function loan()
    {
        return $this->belongsTo(FinancingLoan::class, 'financing_loan_id');
    }

    public function schedule()
    {
        return $this->belongsTo(FinancingSchedule::class, 'financing_schedule_id');
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    /** The entry this movement produced, when it produced one. */
    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
