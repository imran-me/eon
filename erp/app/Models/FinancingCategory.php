<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A loan category, or a sub-category — the same thing one level down.
 *
 * Self-parenting: parent_id null is a top-level category, set is a
 * sub-category. `direction` scopes it to a book ('borrowed' / 'lent') or leaves
 * it null to apply to both.
 */
class FinancingCategory extends Model
{
    use SoftDeletes;

    protected $fillable = ['parent_id', 'name', 'direction', 'status', 'sort_order'];

    protected $casts = [
        'status'     => 'boolean',
        'sort_order' => 'integer',
    ];

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    /** Loans filed directly under this category. */
    public function loans()
    {
        return $this->hasMany(FinancingLoan::class, 'category_id');
    }

    public function scopeTopLevel($q)
    {
        return $q->whereNull('parent_id');
    }

    public function scopeActive($q)
    {
        return $q->where('status', true);
    }

    /**
     * Categories usable on a given book: those scoped to it, plus those scoped
     * to neither.
     */
    public function scopeForBook($q, ?string $direction)
    {
        return $q->where(function ($w) use ($direction) {
            $w->whereNull('direction');
            if ($direction) {
                $w->orWhere('direction', $direction);
            }
        });
    }

    public function getFullNameAttribute(): string
    {
        return $this->parent ? $this->parent->name . ' › ' . $this->name : $this->name;
    }
}
