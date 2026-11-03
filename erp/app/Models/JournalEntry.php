<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class JournalEntry extends Model
{
    use SoftDeletes;

    protected $table = 'journal_entries';

    protected $fillable = [
        'company_id',
        'created_by',
        'date',
        'entry_time',
        'reference',
        'source',
        'source_id',
        'description',
        // Who a bank deposit/withdrawal was for — see the 2026_09_14 migration.
        'beneficiary',
        'beneficiary_party_id',
        'beneficiary_method',
        'beneficiary_bank',
        'beneficiary_ref',
        'reversed_journal_entry_id',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    /**
     * A reversal belongs beside the entry it cancels on every statement.
     * Statements order same-day rows by COALESCE(date + entry_time, created_at),
     * so a reversal saved with no entry_time sorts on the click time and jumps
     * to the top of the day, far from the original. Whichever screen posts the
     * reversal, it inherits the original's clock time here — one rule instead
     * of one per controller. It overrides any caller-supplied reversal time.
     * A lookup failure never blocks posting and leaves that value untouched.
     */
    protected static function booted(): void
    {
        static::creating(function (JournalEntry $entry) {
            if (! $entry->reversed_journal_entry_id) {
                return;
            }
            try {
                $original = static::query()
                    ->whereKey($entry->reversed_journal_entry_id)
                    ->first(['date', 'entry_time', 'created_at']);
                if ($original) {
                    $entry->date = $original->date;
                    $entry->entry_time = $original->entry_time
                        ?: optional($original->created_at)->format('H:i:s');
                }
            } catch (\Throwable $e) {
                // ordering nicety only — never fail the ledger write
            }
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(JournalItem::class, 'journal_entry_id');
    }

    public function journalItems()
    {
        return $this->hasMany(JournalItem::class, 'journal_entry_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /**
     * The original entry this one reverses (set only on reversal entries).
     */
    public function reversalOf()
    {
        return $this->belongsTo(JournalEntry::class, 'reversed_journal_entry_id');
    }

    /**
     * The reversal entry that undid this one, if any.
     */
    public function reversedBy()
    {
        return $this->hasOne(JournalEntry::class, 'reversed_journal_entry_id');
    }

    /**
     * History of date corrections made to this entry — see updateJournalDate().
     */
    public function dateEdits()
    {
        return $this->hasMany(JournalEntryDateEdit::class, 'journal_entry_id');
    }

    // ── Computed ───────────────────────────────────────────

    public function getTotalDebitAttribute(): float
    {
        return (float) $this->items->sum('debit');
    }

    public function getTotalCreditAttribute(): float
    {
        return (float) $this->items->sum('credit');
    }

    public function getIsBalancedAttribute(): bool
    {
        return abs($this->total_debit - $this->total_credit) < 0.001;
    }

    public function expensable()
    {
        return $this->morphTo(__FUNCTION__, 'expense', 'source_id');
    }
}
