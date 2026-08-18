<?php

namespace Modules\WoodArt\Modules\Scope\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Wood Art · Phase — one stage a space runs through.
 *
 * ─── ISOLATION (CLAUDE.md) ───────────────────────────────────────────────
 * Reads and writes exactly one table, `wa_phases`, pinned by a global scope to
 * `company_id = 'woodart'`. `project`, `space` and `owner_id` are all STRINGS
 * — ext_ids and an employee code — never foreign keys, so this model declares
 * no relationship to any shared table and nothing here can cascade.
 *
 * ─── THE STATUS VOCABULARY ───────────────────────────────────────────────
 * Three states, from the live data: Not started → Active → Complete. They are
 * the Phase Board's three columns, and the board moves a card by writing this
 * column and nothing else. A phase reaching Complete does NOT touch its
 * project's progress: progress belongs to Projects, and a second writer would
 * give the ERP two answers.
 *
 * @property string      $ext_id
 * @property string      $project
 * @property string      $space     a space EXT id ('SPC-001')
 * @property string      $name
 * @property string|null $code      the trade
 * @property string      $status
 * @property string|null $owner_id  an employee CODE, not a users.id
 * @property-read bool   $is_open
 * @property-read bool   $is_overdue
 */
class Phase extends Model
{
    use SoftDeletes;

    protected $table = 'wa_phases';

    public const COMPANY = 'woodart';

    /** Verbatim from the live data, in workflow order. The board's columns. */
    public const STATUSES = ['Not started', 'Active', 'Complete'];

    /** The dot colour each board column paints. */
    public const STATUS_COLORS = [
        'Not started' => '#f4b740',
        'Active'      => '#2f6bff',
        'Complete'    => '#23c17e',
    ];

    protected $fillable = [
        'ext_id', 'company_id', 'project', 'space', 'name', 'code',
        'sort', 'status', 'owner_id', 'start', 'finish', 'note',
    ];

    protected $casts = [
        'sort'   => 'integer',
        'start'  => 'date',
        'finish' => 'date',
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

    public static function today(): string
    {
        return now()->toDateString();
    }

    /* ── derived, never stored ────────────────────────────────────────────── */

    public function getIsOpenAttribute(): bool
    {
        return $this->status !== 'Complete';
    }

    /**
     * Past its finish date and not finished. A completed phase is never
     * overdue however late it ran, and an undated one never is either — the
     * same rule Workshop and Site & Install use.
     */
    public function getIsOverdueAttribute(): bool
    {
        return $this->is_open
            && $this->finish !== null
            && $this->finish->toDateString() < self::today();
    }

    /* ── scopes ───────────────────────────────────────────────────────────── */

    public function scopeOpen(Builder $q): Builder
    {
        return $q->where('status', '!=', 'Complete');
    }
}
