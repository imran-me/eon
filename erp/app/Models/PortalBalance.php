<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortalBalance extends Model
{
    protected $table = 'portal_balances';

    protected $fillable = [
        'portal_id',
        'invoice_id',
        'date',
        'reference',
        'journal_entry_id',
        'source_account_id',
        'type',
        'old_balance',
        'debit',
        'credit',
        'current_balance',
        'payment_method',
        'remarks',
        'created_by',
    ];

    protected $casts = [
        'date'            => 'date',
        'old_balance'     => 'decimal:2',
        'debit'           => 'decimal:2',
        'credit'          => 'decimal:2',
        'current_balance' => 'decimal:2',
    ];

    public function portal()
    {
        return $this->belongsTo(Portal::class, 'portal_id');
    }

    public function sourceAccount()
    {
        return $this->belongsTo(Account::class, 'source_account_id');
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
