<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One movement on an inter-company liability: the debt being raised, or a part
 * of it being settled.
 *
 * The balance could be read from `settled_amount` alone. These rows exist so
 * the desk can answer *when* each part was paid and against which transfer —
 * a running total says what is owed but never how it got there.
 */
class InterCompanyLiabilityTransaction extends Model
{
    use SoftDeletes;

    protected $table = 'inter_company_liability_transactions';

    protected $fillable = [
        'inter_company_liability_id',
        'type',
        'date',
        'amount',
        'bank_transfer_id',
        'memo',
        'created_by',
    ];

    protected $casts = [
        'date'   => 'date',
        'amount' => 'decimal:2',
    ];

    public const RAISE       = 'raise';
    public const SETTLEMENT  = 'settlement';
    public const WRITE_OFF   = 'write_off';
    public const ADJUSTMENT  = 'adjustment';

    public function liability(): BelongsTo
    {
        return $this->belongsTo(InterCompanyLiability::class, 'inter_company_liability_id');
    }

    public function bankTransfer(): BelongsTo
    {
        return $this->belongsTo(BankTransfer::class, 'bank_transfer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Everything except the raise reduces what is owed. */
    public function getReducesBalanceAttribute(): bool
    {
        return $this->type !== self::RAISE;
    }
}
