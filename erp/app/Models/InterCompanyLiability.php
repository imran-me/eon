<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One company's debt to another, raised when an expense is approved against a
 * bank account belonging to a different company.
 *
 * Tracking only — this model writes no journal and moves no balance. The rule
 * and what it deliberately does not fix are on the
 * create_inter_company_liability_tables migration.
 */
class InterCompanyLiability extends Model
{
    use SoftDeletes;

    protected $table = 'inter_company_liabilities';

    protected $fillable = [
        'debtor_company_id',
        'creditor_company_id',
        'expense_id',
        'bank_id',
        'amount',
        'settled_amount',
        'incurred_on',
        'title',
        'reference',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'incurred_on'    => 'date',
        'amount'         => 'decimal:2',
        'settled_amount' => 'decimal:2',
    ];

    public const ACTIVE      = 'active';
    public const SETTLED     = 'settled';
    public const WRITTEN_OFF = 'written_off';
    public const CANCELLED   = 'cancelled';

    /* ── Relationships ──────────────────────────────────────────────────── */

    public function debtor(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'debtor_company_id');
    }

    public function creditor(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'creditor_company_id');
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(InterCompanyLiabilityTransaction::class)
            ->orderByDesc('date')->orderByDesc('id');
    }

    /* ── Derived figures ────────────────────────────────────────────────── */

    /**
     * What is still owed. Floored at zero so an over-settlement — which the
     * service refuses, but data can arrive by other routes — reads as nothing
     * outstanding rather than as a negative debt.
     */
    public function getOutstandingAttribute(): float
    {
        return max(0, round((float) $this->amount - (float) $this->settled_amount, 2));
    }

    public function getIsSettledAttribute(): bool
    {
        return in_array($this->status, [self::SETTLED, self::WRITTEN_OFF, self::CANCELLED], true);
    }

    /* ── Scopes ─────────────────────────────────────────────────────────── */

    /** The Active Liability table: still owed, in whole or in part. */
    public function scopeActive($query)
    {
        return $query->where('status', self::ACTIVE);
    }

    /**
     * The Register: everything that has come to rest — settled, written off or
     * cancelled. Deliberately not "settled only": a written-off debt is part of
     * the history of what happened between two companies, and hiding it would
     * make the register disagree with the transaction list below it.
     */
    public function scopeClosed($query)
    {
        return $query->whereIn('status', [self::SETTLED, self::WRITTEN_OFF, self::CANCELLED]);
    }

    /** Either side of the pair — what a company-locked user may see. */
    public function scopeForCompany($query, $companyId)
    {
        return $query->where(function ($q) use ($companyId) {
            $q->where('debtor_company_id', $companyId)
              ->orWhere('creditor_company_id', $companyId);
        });
    }
}
