<?php

namespace Modules\WoodArt\Modules\Materials\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Wood Art · Material — one item in the store.
 *
 * ─── ISOLATION (CLAUDE.md) ───────────────────────────────────────────────
 * Reads and writes exactly one table, `wa_materials`, which no other company
 * touches. A global scope pins every query and insert to
 * `company_id = 'woodart'`.
 *
 * @property int         $id
 * @property string      $ext_id
 * @property string      $name
 * @property string      $category
 * @property string      $unit
 * @property float       $stock       signed — a negative means more was issued than held
 * @property float       $reorder     the level at which it needs buying
 * @property int         $unit_cost   integer taka
 * @property string|null $supplier
 * @property-read int    $value       stock × unit_cost
 * @property-read bool   $is_low
 * @property-read bool   $is_dead
 * @property-read float  $short       how far below the reorder level
 * @property-read int    $refill_cost what closing that gap would cost
 */
class Material extends Model
{
    use SoftDeletes;

    protected $table = 'wa_materials';

    public const COMPANY = 'woodart';

    /** Verbatim from the reference (materials view.js CATEGORIES). */
    public const CATEGORIES = ['Board', 'Laminate', 'Hardware', 'Adhesive', 'Finish', 'Fabric', 'Civil'];

    /** Verbatim from the reference (materials view.js UNITS). */
    public const UNITS = ['pcs', 'sheet', 'kg', 'litre', 'sft', 'bag', 'cft'];

    protected $fillable = [
        'ext_id', 'company_id', 'name', 'category', 'unit', 'stock',
        'reorder', 'unit_cost', 'supplier', 'created_on',
    ];

    protected $casts = [
        // Fractional on purpose — Wood Art buys and issues 12.5 kg of adhesive
        // and 2.5 litres of lacquer. 'float', not 'decimal:3': a decimal cast
        // returns a STRING, which breaks the truthiness tests and puts "0.000"
        // in the edit form's number box.
        'stock'      => 'float',
        'reorder'    => 'float',
        'unit_cost'  => 'integer',
        'created_on' => 'date',
    ];

    public function getRouteKeyName(): string
    {
        return 'ext_id';
    }

    protected static function booted(): void
    {
        static::addGlobalScope('woodart', fn (Builder $q) => $q->where('company_id', self::COMPANY));
        static::creating(function (self $m) {
            $m->company_id = $m->company_id ?: self::COMPANY;
        });
    }

    /* ── derived, never stored ────────────────────────────────────────────── */

    /** What is on the shelf is worth. */
    public function getValueAttribute(): int
    {
        // Rounded, not truncated: the `: int` return type would silently drop
        // the remainder of a fractional quantity (12.5 × 545 = 6812.5 -> 6812)
        // with only an E_DEPRECATED that production log levels hide.
        return (int) round($this->stock * $this->unit_cost);
    }

    /**
     * At or below the reorder level — the reference's own test, which is `<=`
     * rather than `<`: sitting exactly ON the line already needs buying.
     * An item with no reorder level set is never low, because zero would
     * otherwise flag every item that has run out as needing a decision nobody
     * has made yet.
     */
    public function getIsLowAttribute(): bool
    {
        return $this->reorder > 0 && $this->stock <= $this->reorder;
    }

    /** Held nothing at all — counted separately from "low". */
    public function getIsDeadAttribute(): bool
    {
        return $this->stock <= 0;
    }

    /** Units needed to get back up to the reorder level. */
    public function getShortAttribute(): float
    {
        // A shortfall is a QUANTITY, not a count: reorder 3 − stock 1.4 is 1.6,
        // and returning 1 would under-order every time.
        return max(0.0, (float) $this->reorder - (float) $this->stock);
    }

    /** What closing that gap costs at today's unit cost. */
    public function getRefillCostAttribute(): int
    {
        return (int) round($this->short * $this->unit_cost);
    }

    /* ── scopes ───────────────────────────────────────────────────────────── */

    public function scopeLow(Builder $q): Builder
    {
        return $q->where('reorder', '>', 0)->whereColumn('stock', '<=', 'reorder');
    }

    /* ── display ──────────────────────────────────────────────────────────── */

    /**
     * The house format for a stock quantity: up to three decimals, trailing
     * zeros trimmed, so 12.500 reads "12.5" and 12.000 reads "12" — identical
     * to what number_format() printed while quantities were whole.
     *
     * Every stock quantity in the module goes through here, so precision lives
     * in ONE place. A bare number_format($n) is 0 decimal places and ROUNDS:
     * it renders 12.5 kg as a completely plausible "13 kg" with no visual tell,
     * which is the failure this method exists to prevent.
     *
     * Safe on thousands separators: number_format(1200, 3) is "1,200.000", and
     * the rtrim of '0' stops at the '.', giving "1,200" rather than "1,2".
     */
    public static function qty(int|float|string|null $n): string
    {
        return rtrim(rtrim(number_format((float) $n, 3), '0'), '.');
    }
}
