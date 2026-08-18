<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JournalEntry extends Model
{
    use SoftDeletes;

    protected $table = 'journal_entries';

    protected $fillable = [
        'company_id',
        'created_by',
        'date',
        'reference',
        'source',
        'source_id',
        'description',
        'reversed_journal_entry_id',
    ];

    protected $casts = [
        'date' => 'date',
    ];
    

    public function items()
    {
        return $this->hasMany(JournalItem::class, 'journal_entry_id');
    }

    public function journalItems()
    {
        return $this->hasMany(JournalItem::class, 'journal_entry_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function company()
    {
        return $this->belongsTo(\App\Models\Company::class, 'company_id');
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
