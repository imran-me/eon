<?php

namespace Modules\WoodArt\Modules\Scope\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Wood Art · Requirement — what one phase of one space needs.
 *
 * ─── ISOLATION (CLAUDE.md) ───────────────────────────────────────────────
 * Reads and writes exactly one table, `wa_requirements`, pinned by a global
 * scope to `company_id = 'woodart'`. Every reference it holds — project,
 * space, phase, material — is an ext_id STRING, never a foreign key.
 *
 * ─── THIS IS NOT A LEDGER ────────────────────────────────────────────────
 * A requirement states what a phase needs and what it was costed at. It is a
 * PLAN, not a transaction: it does not mean money moved, stock left the store
 * or a purchase order exists. Spend lives on Procurement's purchase orders and
 * billing lives on Projects, and this table must never become a second source
 * of truth for either. `status` (Planned → Ordered → Issued) records how far
 * along the plan is, nothing more.
 *
 * @property string      $ext_id
 * @property string      $project
 * @property string      $space
 * @property string      $phase
 * @property string      $kind          material|contract
 * @property string      $item
 * @property string|null $material_id   a material EXT id, or NULL for bought work
 * @property float       $qty
 * @property int         $unit_cost     integer taka, per unit
 * @property int         $unit_sale
 * @property-read int    $line_cost
 * @property-read int    $line_sale
 * @property-read int    $margin
 */
class Requirement extends Model
{
    use SoftDeletes;

    protected $table = 'wa_requirements';

    public const COMPANY = 'woodart';

    /** Something bought for the store, or work put out to contract. */
    public const KINDS = ['material', 'contract'];

    /** How far along the plan is — NOT an accounting state. */
    public const STATUSES = ['Planned', 'Ordered', 'Issued'];

    protected $fillable = [
        'ext_id', 'company_id', 'project', 'space', 'phase', 'kind', 'code',
        'item', 'material_id', 'qty', 'unit', 'unit_cost', 'unit_sale',
        'status', 'note',
    ];

    protected $casts = [
        'qty'       => 'float',
        'unit_cost' => 'integer',
        'unit_sale' => 'integer',
    ];

    public function getRouteKeyName(): string
    {
        return 'ext_id';
    }

    protected static function booted(): void
    {
        static::addGlobalScope('woodart', fn (Builder $q) => $q->where('company_id', self::COMPANY));
        static::creating(function (self $r) {
            $r->company_id = $r->company_id ?: self::COMPANY;
        });
    }

    /* ── derived, never stored ────────────────────────────────────────────── */

    public function getLineCostAttribute(): int
    {
        return (int) round($this->qty * $this->unit_cost);
    }

    public function getLineSaleAttribute(): int
    {
        return (int) round($this->qty * $this->unit_sale);
    }

    public function getMarginAttribute(): int
    {
        return $this->line_sale - $this->line_cost;
    }

    /* ── scopes ───────────────────────────────────────────────────────────── */

    public function scopeMaterials(Builder $q): Builder
    {
        return $q->where('kind', 'material');
    }
}
