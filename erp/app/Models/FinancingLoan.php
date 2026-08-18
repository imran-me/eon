<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One financing arrangement — money the group lent out, or money it borrowed.
 *
 * Deliberately NOT connected to the general ledger: see the migration
 * (2026_08_16_000001_create_financing_tables) for the owner's rule and why
 * double-booking a customer's instalment plan would count the same taka twice.
 *
 * Employee loans are not stored here. They live in App\Models\Loan under
 * Payroll and are shown on this desk read-only.
 */
class FinancingLoan extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'department_id', 'created_by', 'direction', 'taken_for', 'personal_of', 'kind',
        'counterparty_name', 'party_type', 'party_id', 'account_no',
        'category_id', 'sub_category_id',
        'linked_type', 'linked_id', 'linked_label',
        'principal', 'down_payment', 'processing_fee', 'disbursement_bank_id',
        'interest_rate', 'interest_method', 'loan_shape',
        'tenure_months', 'instalment_amount', 'start_date',
        'purpose', 'security', 'notes', 'status',
        'gl_account_id', 'gl_interest_account_id',
    ];

    protected $casts = [
        'start_date'        => 'date',
        'principal'         => 'decimal:2',
        'down_payment'      => 'decimal:2',
        'processing_fee'    => 'decimal:2',
        'interest_rate'     => 'decimal:3',
        'instalment_amount' => 'decimal:2',
        'tenure_months'     => 'integer',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /** The account the money landed in — the cash leg of the entry for a receipt. */
    public function disbursementBank()
    {
        return $this->belongsTo(Bank::class, 'disbursement_bank_id');
    }

    /**
     * The account the PRINCIPAL should be posted against in Manage Banks — the
     * liability being paid down, or the receivable being collected. A hint for
     * whoever records the money; nothing here posts it.
     */
    public function glAccount()
    {
        return $this->belongsTo(Account::class, 'gl_account_id');
    }

    /** The account the INTEREST portion belongs to: expense, or income. */
    public function glInterestAccount()
    {
        return $this->belongsTo(Account::class, 'gl_interest_account_id');
    }

    /**
     * What the arrangement is worth in total — what was paid up front plus what
     * was financed. Principal alone understates a client deal where half was
     * settled at the counter.
     */
    public function getDealValueAttribute(): float
    {
        return round((float) $this->principal + (float) $this->down_payment, 2);
    }

    /**
     * What actually reached the account — the sanctioned amount less any fee the
     * lender deducted at source.
     *
     * Kept separate from `principal` on purpose. A 1,000,000 loan that credits
     * 985,000 after a 15,000 arrangement fee still owes 1,000,000, so principal
     * is what the liability is worth and this is only what the bank statement
     * will show on the day. Never use this figure as the debt.
     */
    public function getNetDisbursedAttribute(): float
    {
        return round((float) $this->principal - (float) $this->processing_fee, 2);
    }

    /**
     * A loan in a person's own name, not the group's.
     *
     * The single question everything else follows from: no liability belongs on
     * any company's balance sheet, no interest may be claimed against it, and an
     * instalment the company happens to pay is money taken out for personal use
     * rather than a cost of doing business.
     */
    public function getIsPersonalAttribute(): bool
    {
        return $this->direction === 'borrowed' && $this->taken_for === 'personal';
    }

    /**
     * A running account rather than a fixed loan.
     *
     * The boss takes 2,000 today and 10,000 next week and settles when he
     * chooses. There is no agreed principal, no tenure and no instalment, so
     * `principal` stays 0 here and the balance is derived from the movements
     * instead. Anything that reads `principal` as "the debt" must test this
     * first, or a running account reads as fully settled from the day it opens.
     */
    public function getIsRunningAttribute(): bool
    {
        return $this->loan_shape === "running";
    }

    /**
     * Everything taken on a running account, before anything came back.
     *
     * Only `disburse` rows count. A repayment is the other direction and a
     * write-off settles rather than advances, so neither may swell the figure
     * that stands for "how much has gone out".
     */
    public function getDrawnAmountAttribute(): float
    {
        return (float) $this->transactions
            ->where("type", "disburse")
            ->sum(fn (FinancingTransaction $t) => (float) $t->amount);
    }

    /** What the arrangement is worth as a debt: the agreed sum, or what was taken. */
    public function getExposureAttribute(): float
    {
        return $this->is_running
            ? $this->drawn_amount
            : (float) $this->principal;
    }

    /** Whose debt this is, in words. Only meaningful on the borrowed book. */
    public function getTakenForLabelAttribute(): string
    {
        if ($this->direction !== 'borrowed') {
            return '';
        }

        return match ($this->taken_for) {
            'personal' => 'Personal',
            'company'  => $this->department?->name ? 'Company · ' . $this->department->name : 'Company',
            default    => '',
        };
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function schedules()
    {
        return $this->hasMany(FinancingSchedule::class)->orderBy('instalment_no');
    }

    public function transactions()
    {
        return $this->hasMany(FinancingTransaction::class)->orderBy('date');
    }

    /* ── Scopes ─────────────────────────────────────────────────────────── */

    public function scopeLent($q)
    {
        return $q->where('direction', 'lent');
    }

    public function scopeBorrowed($q)
    {
        return $q->where('direction', 'borrowed');
    }

    /* ── Derived figures ────────────────────────────────────────────────── */

    /**
     * What has actually been settled, read from the movement log rather than a
     * stored running total — the same discipline the staff-loan desk uses, so a
     * deleted or corrected transaction can never leave a stale balance behind.
     *
     * A disbursement is money going the other way and is excluded; a write-off
     * counts as settled because nothing more will be collected on it.
     *
     * Summed on the PRINCIPAL each payment repaid, not on what left the account.
     *
     * An instalment is two things travelling together: a debt coming down and a
     * cost being incurred. Only the first reduces what is owed. Counting the
     * whole instalment made the desk disagree with its own ledger from the very
     * first payment — a 10,00,000 loan showed 9,67,733 outstanding while 2510
     * correctly stood at 9,76,066, the gap being exactly the interest paid. Left
     * alone it compounds: across 36 months the interest is about 1,61,000, so
     * the desk would have reported the loan fully settled roughly five months
     * early, while the bank was still owed a sixth of it.
     *
     * A lender's closure fee is excluded for the same reason it always was — it
     * settles nothing — and it is already outside principal_part.
     */
    public function getSettledAmountAttribute(): float
    {
        return (float) $this->transactions
            ->whereIn('type', ['receipt', 'repayment', 'write_off'])
            ->sum(function (FinancingTransaction $t) {
                // A write-off closes the whole balance it covers: nothing further
                // will be collected, and it carries no principal/interest split.
                if ($t->type === 'write_off') {
                    return $t->applied_amount;
                }

                // A payment recorded before the split existed, or against a loan
                // with no interest, carries neither part. Falling back to what it
                // applied keeps those rows counting rather than silently
                // reporting the loan as never repaid at all.
                if ((float) $t->principal_part <= 0 && (float) $t->interest_part <= 0) {
                    return $t->applied_amount;
                }

                return (float) $t->principal_part;
            });
    }

    /**
     * Still owed. Measured against `exposure`, not `principal`, so a running
     * account answers with what is actually outstanding today instead of
     * reporting zero because it never had a principal to begin with.
     */
    public function getOutstandingAttribute(): float
    {
        return max(0, round($this->exposure - $this->settled_amount, 2));
    }

    public function getProgressPctAttribute(): float
    {
        $exposure = $this->exposure;

        return $exposure > 0
            ? min(100, round($this->settled_amount / $exposure * 100, 1))
            : 0.0;
    }

    /** The next instalment still owed, or null once the schedule is clear. */
    public function getNextDueAttribute()
    {
        return $this->schedules
            ->whereIn('status', ['due', 'partial'])
            ->sortBy('due_date')
            ->first();
    }

    /** Instalments whose due date has passed and which are not settled. */
    public function getOverdueCountAttribute(): int
    {
        return $this->schedules
            ->whereIn('status', ['due', 'partial'])
            ->filter(fn ($s) => $s->due_date && $s->due_date->isPast())
            ->count();
    }

    /* ── Credit risk ────────────────────────────────────────────────────
       Ageing and classification on the Bangladesh Bank scale.

       Thresholds follow BRPD Circular No. 15 (27 Nov 2024, effective 1 April
       2025), which moved non-performing to three months overdue from six:

         nothing overdue      Standard        1%
         1 day – 3 months     SMA             5%
         3 – 6 months         Sub-standard   20%
         6 – 12 months        Doubtful       50%
         over 12 months       Bad / Loss    100%

       Two honest limits. The circular's STD-0 / STD-1 / STD-2 sub-stages are
       forward-looking IFRS 9 staging that cannot be derived from arrears alone,
       so they collapse to one Standard band here. And Epal is not a bank: these
       rules bind a bank's own book, so this is a management view of credit risk
       — a consistent, recognised yardstick — not a regulatory return.

       It reads properly only on money owed TO us. On a borrowing, being overdue
       is our own late payment, and it is the lender who classifies us — so the
       screens show the ageing there but not the provision. */

    /** Days since the OLDEST unsettled instalment fell due. 0 if nothing is. */
    public function getDaysPastDueAttribute(): int
    {
        $oldest = $this->schedules
            ->whereIn('status', ['due', 'partial'])
            ->filter(fn ($s) => $s->due_date && $s->due_date->isPast())
            ->sortBy('due_date')
            ->first();

        // Oldest, not newest: arrears age from the first instalment missed, so
        // a borrower who keeps paying recent instalments while an old one sits
        // unpaid cannot age out of the bucket they belong in.
        return $oldest ? (int) $oldest->due_date->diffInDays(now()) : 0;
    }

    public function getClassificationAttribute(): string
    {
        $dpd = $this->days_past_due;

        return match (true) {
            $dpd <= 0   => 'standard',
            $dpd < 90   => 'sma',
            $dpd < 180  => 'substandard',
            $dpd < 365  => 'doubtful',
            default     => 'bad',
        };
    }

    public function getClassificationLabelAttribute(): string
    {
        return match ($this->classification) {
            'sma'         => 'SMA',
            'substandard' => 'Sub-standard',
            'doubtful'    => 'Doubtful',
            'bad'         => 'Bad / Loss',
            default       => 'Standard',
        };
    }

    /** Provision rate for the band, as a percentage. */
    public function getProvisionRateAttribute(): float
    {
        return match ($this->classification) {
            'sma'         => 5.0,
            'substandard' => 20.0,
            'doubtful'    => 50.0,
            'bad'         => 100.0,
            default       => 1.0,
        };
    }

    /**
     * What ought to be set aside against this receivable at the band's rate.
     * Provisioned on what is still outstanding, not the original principal —
     * money already collected is not at risk.
     */
    public function getProvisionAmountAttribute(): float
    {
        return round($this->outstanding * $this->provision_rate / 100, 2);
    }
}
