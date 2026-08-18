<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Owner money in (investment) or out (drawings).
 *
 * Not a loan: nothing is expected back and there is no schedule. Money the
 * owner takes as a LOAN belongs in FinancingLoan's lent book instead, because
 * that is repayable and does have a schedule.
 *
 * Posts no journal entry — the matching deposit or withdrawal is recorded in
 * Manage Banks. See the migration for why both would double-count.
 */
class FinancingCapital extends Model
{
    use SoftDeletes;

    protected $table = 'financing_capital_movements';

    protected $fillable = [
        'company_id', 'created_by', 'kind', 'person_name',
        'party_type', 'party_id', 'reason', 'amount', 'date',
        'method', 'bank_id', 'gl_account_id', 'reference', 'notes',
    ];

    protected $casts = [
        'date'   => 'date',
        'amount' => 'decimal:2',
    ];

    /** The reasons the owner actually uses, offered as suggestions not a fixed list. */
    public const REASONS = [
        'investment' => ['Working capital', 'Cover negative balance', 'New project funding', 'Asset purchase'],
        'drawings'   => ['Profit withdrawal', 'Dividend', 'Personal use'],
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeInvestment($q)
    {
        return $q->where('kind', 'investment');
    }

    public function scopeDrawings($q)
    {
        return $q->where('kind', 'drawings');
    }

    /** Money in counts positive, money out negative — for a net figure. */
    public function getSignedAmountAttribute(): float
    {
        return $this->kind === 'investment'
            ? (float) $this->amount
            : -1 * (float) $this->amount;
    }
}
