<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One movement on a staff loan — a disbursement out, or a repayment back in.
 *
 * Outstanding is derived from these rows (disbursed − repaid) rather than kept
 * as a running total, so a corrected or deleted row can never leave the balance
 * lying about what actually happened.
 */
class LoanTransaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'loan_id',
        'user_id',
        'type',
        'method',
        'amount',
        'date',
        'account_id',
        'bank_id',
        'employee_salary_id',
        'journal_entry_id',
        'note',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
    ];

    /** Human label for the "repaid via" column. */
    public function getMethodLabelAttribute(): string
    {
        return $this->method === 'salary' ? 'Salary deduction' : 'Cash / bank';
    }

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    public function employeeSalary()
    {
        return $this->belongsTo(EmployeeSalary::class);
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function scopeRepayments($query)
    {
        return $query->where('type', 'repay');
    }

    public function scopeDisbursements($query)
    {
        return $query->where('type', 'disburse');
    }
}
