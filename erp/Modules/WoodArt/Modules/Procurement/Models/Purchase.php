<?php

namespace Modules\WoodArt\Modules\Procurement\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Wood Art · Purchase Order — one order raised on a vendor.
 *
 * ─── ISOLATION (CLAUDE.md) ───────────────────────────────────────────────
 * One table, `wa_purchases`, scoped to `company_id = 'woodart'`.
 *
 * ─── WHAT `status` MEANS ─────────────────────────────────────────────────
 * Ordered → Partial → Received. The reference treats anything not fully
 * Received as still owed, which is what "Awaiting Delivery" counts.
 *
 * ─── WHAT THIS MODEL DOES NOT DO ─────────────────────────────────────────
 * Receiving goods does NOT touch stock here. In the reference a goods receipt
 * posts a stock movement AND a payable in one transaction — neither of which
 * exists yet (no `wa_movements`, and Wood Art's books are an open question).
 * Marking an order Received therefore records only that it arrived; it does
 * not add to `wa_materials.stock`. The screen says so rather than pretending,
 * because a stock figure that silently disagrees with its ledger is precisely
 * what the reference warns against.
 *
 * @property string      $ext_id
 * @property string      $supplier   vendor NAME, not an id
 * @property string|null $project    project ext_id, or null for general stock
 * @property int         $items
 * @property int         $amount     integer taka
 * @property string      $status
 * @property-read bool   $is_open
 */
class Purchase extends Model
{
    use SoftDeletes;

    protected $table = 'wa_purchases';

    public const COMPANY = 'woodart';

    /** Verbatim from the reference (procurement view.js STATUSES). */
    public const STATUSES = ['Ordered', 'Partial', 'Received'];

    protected $fillable = [
        'ext_id', 'company_id', 'supplier', 'project', 'items', 'amount',
        'status', 'date', 'created_on',
    ];

    protected $casts = [
        'items'      => 'integer',
        'amount'     => 'integer',
        'date'       => 'date',
        'created_on' => 'date',
    ];

    public function getRouteKeyName(): string
    {
        return 'ext_id';
    }

    protected static function booted(): void
    {
        static::addGlobalScope('woodart', fn (Builder $q) => $q->where('company_id', self::COMPANY));
        static::creating(function (self $p) {
            $p->company_id = $p->company_id ?: self::COMPANY;
        });
    }

    /** Not yet fully delivered — the reference's "awaiting delivery" test. */
    public function getIsOpenAttribute(): bool
    {
        return $this->status !== 'Received';
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->where('status', '!=', 'Received');
    }
}
