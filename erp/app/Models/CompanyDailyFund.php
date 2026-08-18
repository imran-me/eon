<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One company's cash spending ceiling, for the window it was in force.
 *
 * A ceiling, not a pot — see the 2026_08_13 migration. Nothing here posts to the
 * ledger, so this model has no journal entry, no account and no balance. What was
 * actually spent against it is read from `expenses`.
 *
 * Rows for a company never overlap; DailyFundService::save() is the only writer
 * and is what keeps that true. Write through the service, not through this model.
 */
class CompanyDailyFund extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'amount',
        'effective_from',
        'effective_to',
        'note',
        'created_by',
    ];

    protected $casts = [
        'amount'         => 'decimal:2',
        'effective_from' => 'date',
        'effective_to'   => 'date',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The rows in force on a date — at most one per company, given the
     * no-overlap invariant.
     */
    public function scopeInForceOn(Builder $query, string $date): Builder
    {
        return $query->whereDate('effective_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date));
    }
}
