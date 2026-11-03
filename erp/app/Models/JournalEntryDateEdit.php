<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalEntryDateEdit extends Model
{
    protected $table = 'journal_entry_date_edits';

    protected $fillable = [
        'journal_entry_id',
        'old_date',
        'new_date',
        'reason',
        'edited_by',
    ];

    protected $casts = [
        'old_date' => 'date',
        'new_date' => 'date',
    ];

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'edited_by');
    }
}
