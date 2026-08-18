<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One hand-over of cash, in either direction.
 *
 *     issue   the drawer to the custodian's pocket
 *     return  the pocket back to the drawer
 *
 * Spending is deliberately absent: what the money was spent ON belongs to the
 * expense, with the whole classification picker behind it, so it lives in
 * `expenses` and reaches the ledger from there. Recording it in both places would
 * give two answers to one question and eventually two different ones.
 */
class PettyCashTransaction extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPE_ISSUE  = 'issue';
    public const TYPE_RETURN = 'return';

    protected $fillable = [
        'petty_cash_float_id',
        'type',
        'amount',
        'date',
        'bank_id',
        'journal_entry_id',
        'attachment',
        'note',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'date'   => 'date',
    ];

    public function float()
    {
        return $this->belongsTo(PettyCashFloat::class, 'petty_cash_float_id');
    }

    /** Where the cash came from, or went back to. Null means office cash. */
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
}
