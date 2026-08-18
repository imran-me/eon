<?php

namespace Modules\WoodArt\Modules\Scope\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Wood Art · Space — one room of a project.
 *
 * ─── ISOLATION (CLAUDE.md) ───────────────────────────────────────────────
 * Reads and writes exactly one table, `wa_spaces`. A global scope pins every
 * query and insert to `company_id = 'woodart'`. No relationship to any shared
 * model; `project` is an ext_id STRING, never a foreign key.
 *
 * @property string      $ext_id
 * @property string      $project
 * @property string      $name
 * @property string      $kind
 * @property int         $area    square feet; 0 means "not measured"
 * @property int         $sort
 */
class Space extends Model
{
    use SoftDeletes;

    protected $table = 'wa_spaces';

    public const COMPANY = 'woodart';

    /** The room types in the live data. Free text is still accepted. */
    public const KINDS = ['Living', 'Dining', 'Kitchen', 'Bedroom', 'Bath', 'Common', 'Balcony'];

    protected $fillable = [
        'ext_id', 'company_id', 'project', 'name', 'kind',
        'area', 'sort', 'note', 'created_on',
    ];

    protected $casts = [
        'area'       => 'integer',
        'sort'       => 'integer',
        'created_on' => 'date',
    ];

    public function getRouteKeyName(): string
    {
        return 'ext_id';
    }

    protected static function booted(): void
    {
        static::addGlobalScope('woodart', fn (Builder $q) => $q->where('company_id', self::COMPANY));
        static::creating(function (self $s) {
            $s->company_id = $s->company_id ?: self::COMPANY;
        });
    }
}
