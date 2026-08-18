<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account extends Model
{
    use SoftDeletes;

    protected $table = 'accounts';

    protected $fillable = [
        'code',
        'name',
        'type',
        'parent_id',
        'company_id',
        'opening_balance',
        'status',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'status'          => 'boolean',
    ];

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    public function parent()
    {
        return $this->belongsTo(Account::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Account::class, 'parent_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    // ──────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    /**
     * Check if this account is an ancestor of the given account id.
     * Used to prevent circular parent references.
     */
    public function isAncestorOf(int $targetId): bool
    {
        $children = $this->children()->pluck('id')->toArray();
        if (in_array($targetId, $children)) {
            return true;
        }
        foreach ($this->children as $child) {
            if ($child->isAncestorOf($targetId)) {
                return true;
            }
        }
        return false;
    }

    // In Account model
    public function isSystemAccount(): bool
    {
        return in_array($this->code, array_values(config('accounts')));
    }

    public function items()
    {
        return $this->hasMany(\App\Models\JournalItem::class, 'account_id');
    }
}
