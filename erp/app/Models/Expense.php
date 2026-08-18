<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'expenses';

    /**
     * Approval states.
     *
     * Kept apart from `status`, which is the record's own active flag. Only
     * APPROVED means the money is in the accounts — `status` never decides that
     * any more, because it used to and a dropdown could delete a posting.
     */
    public const PENDING  = 'pending';
    public const APPROVED = 'approved';

    protected $fillable = [
        'user_id',
        'company_id',
        'expense_department_id',
        'account_id',
        'expense_category_id',
        'expense_sub_category_id',
        'other_note',
        'title',
        'description',
        'amount',
        'payment_mode',
        'bank_id',
        // Set when the money came out of a custodian's pocket rather than the
        // drawer or a bank. It decides which account the expense is credited
        // against — see ExpenseController::settlementAccountId().
        'petty_cash_float_id',
        // Set when the money was the filer's OWN and the company owes it back.
        // The only settlement source that credits a liability instead of moving
        // company cash. Outranks every other source when present.
        'reimburse_to_user_id',
        // How much of this expense became a debt, filled when it posts. The whole
        // amount for an own-pocket claim; for a float that could not cover the
        // spend, only the part the custodian made up themselves.
        'reimbursed_amount',
        'attachment',
        'reference',
        'expense_date',
        'status',
        'approval_status',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    /** True once the posting exists in the ledger. */
    public function getIsApprovedAttribute(): bool
    {
        return $this->approval_status === self::APPROVED;
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    /** The custodian's float this was paid out of, when it was not the drawer. */
    public function pettyCashFloat()
    {
        return $this->belongsTo(PettyCashFloat::class, 'petty_cash_float_id');
    }

    /** The person owed for this one, when they paid out of their own pocket. */
    public function reimburseTo()
    {
        return $this->belongsTo(User::class, 'reimburse_to_user_id');
    }

    /** True when this expense is a claim against the company rather than a spend of its cash. */
    public function getIsReimbursableAttribute(): bool
    {
        return !empty($this->reimburse_to_user_id);
    }

    /** The expense desk's own department — not HR's. See ExpenseDepartment. */
    public function expenseDepartment()
    {
        return $this->belongsTo(ExpenseDepartment::class, 'expense_department_id');
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * The classification, as one readable line: Company › Department › Category
     * › Sub-category, with whatever "Others" text was typed in place of the level
     * that had none.
     */
    public function getClassificationPathAttribute(): string
    {
        return collect([
            $this->company?->short_name ?: $this->company?->name,
            $this->expenseDepartment?->name,
            $this->expense_category?->name,
            $this->expense_sub_category?->name,
            $this->other_note ? '“' . $this->other_note . '”' : null,
        ])->filter()->implode(' › ');
    }

    public function expense_category()
    {
        return $this->belongsTo(ExpenseCategory::class);
    }

    public function expense_sub_category()
    {
        return $this->belongsTo(ExpenseSubCategory::class);
    }

    public function items()
    {
        return $this->hasMany(ExpenseItem::class);
    }
}
