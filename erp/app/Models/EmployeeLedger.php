<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeLedger extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'employee_ledger';

    protected $fillable = [
        'user_id',
        'type',
        'source_type',
        'source_id',
        'entry_date',
        'transaction_time',
        'reference',
        'old_balance',
        'debit',
        'credit',
        'balance',
        'note',
        'created_by',
    ];

    protected $casts = [
        'entry_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function source()
    {
        return $this->morphTo();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Return the user-selected transaction time when the source record has one.
     * Historical salary rows intentionally remain date-only.
     */
    public function getDisplayTimeAttribute(): ?string
    {
        $transactionTime = $this->transaction_time ?: $this->source?->getAttribute('transaction_time');
        if (! $transactionTime && $this->source instanceof AdvanceSalary) {
            $transactionTime = $this->source->releaseJournal?->entry_time;
        }
        if (! $transactionTime && $this->source instanceof Loan) {
            $transactionTime = JournalEntry::where('source', 'loan')
                ->where('source_id', $this->source->id)
                ->value('entry_time');
        }
        if (! $transactionTime && $this->source instanceof EmployeeLedger) {
            $transactionTime = JournalEntry::where('source', 'employee_ledger')
                ->where('source_id', $this->source->id)
                ->value('entry_time');
        }

        if (! $transactionTime) {
            return null;
        }

        return Carbon::parse($transactionTime)->format('h:i A');
    }
}
