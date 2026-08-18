<?php

namespace Modules\WoodArt\Modules\Procurement\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Wood Art · Vendor — a supplier of board, laminate, hardware, finishes or
 * fabric.
 *
 * Deliberately NOT the same thing as a contractor: a vendor sells material
 * against a purchase order, a contractor sells labour against a phase. The
 * reference keeps them in separate tables for exactly that reason — sharing
 * one would make the spend screens quietly report labour as material buying.
 *
 * ─── ISOLATION (CLAUDE.md) ───────────────────────────────────────────────
 * One table, `wa_vendors`, scoped to `company_id = 'woodart'` by a global
 * scope on every query and insert.
 *
 * @property string      $ext_id
 * @property string      $name
 * @property string      $category
 * @property string|null $terms
 */
class Vendor extends Model
{
    use SoftDeletes;

    protected $table = 'wa_vendors';

    public const COMPANY = 'woodart';

    /** Verbatim from the reference (procurement view.js CATEGORIES). */
    public const CATEGORIES = ['Board', 'Laminate', 'Hardware', 'Adhesive', 'Finish', 'Fabric', 'General'];

    /** Payment terms the reference offers. */
    public const TERMS = ['Advance', 'Net 15', 'Net 30', 'Net 45'];

    protected $fillable = [
        'ext_id', 'company_id', 'name', 'category', 'contact', 'phone',
        'email', 'area', 'terms', 'since', 'created_on',
    ];

    protected $casts = [
        'since'      => 'date',
        'created_on' => 'date',
    ];

    public function getRouteKeyName(): string
    {
        return 'ext_id';
    }

    protected static function booted(): void
    {
        static::addGlobalScope('woodart', fn (Builder $q) => $q->where('company_id', self::COMPANY));
        static::creating(function (self $v) {
            $v->company_id = $v->company_id ?: self::COMPANY;
        });
    }
}
