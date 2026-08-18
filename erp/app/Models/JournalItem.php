<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;

class JournalItem extends Model
{
    use SoftDeletes;

    protected $table = 'journal_items';

    protected $fillable = [
        'journal_entry_id',
        'account_id',
        'debit',
        'credit',
        'note',
        'party_type',
        'party_id',
    ];

    protected $casts = [
        'debit'  => 'decimal:2',
        'credit' => 'decimal:2',
    ];

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /**
     * Resolves to Customer or Supplier via the AppServiceProvider morph map.
     * Use for party_type = 'customer' | 'supplier'.
     */
    public function party()
    {
        return $this->morphTo('party', 'party_type', 'party_id');
    }

    /**
     * Resolves to User (agents and vendors are stored in the users table).
     * Use for party_type = 'agent' | 'vendor'.
     * Kept separate to avoid registering User::class in the morph map,
     * which would break Spatie Permission's reverse morph lookup.
     */
    public function partyUser()
    {
        return $this->belongsTo(User::class, 'party_id');
    }
}
