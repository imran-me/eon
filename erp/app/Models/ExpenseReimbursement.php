<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One payment back to a member of staff for money they spent themselves.
 *
 * What is still owed is NOT stored here. It is read from the ledger — see
 * EmployeeReimbursementService::owedTo() — so a payment and a claim can never
 * drift out of step with the accounts they both post to.
 */
class ExpenseReimbursement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'company_id',
        'amount',
        'bank_id',
        'paid_on',
        'note',
        'journal_entry_id',
        'created_by',
    ];

    protected $casts = [
        'amount'  => 'decimal:2',
        'paid_on' => 'date',
    ];

    /** The person who was paid. */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /** Null means it was paid in cash out of the pot. */
    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Where the money came from, in the words the expense list uses. */
    public function getPaidFromAttribute(): string
    {
        return $this->bank?->name ?? 'Cash';
    }
}
