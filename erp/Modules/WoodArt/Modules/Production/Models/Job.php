<?php

namespace Modules\WoodArt\Modules\Production\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Wood Art · Job — one fabrication job on the workshop floor.
 *
 * ─── ISOLATION (CLAUDE.md) ───────────────────────────────────────────────
 * Reads and writes exactly one table, `wa_production`, which no other company
 * touches. It extends nothing of the host's and declares no relationship to
 * any shared model. A global scope pins every query and insert to
 * `company_id = 'woodart'`, so even a bare Job::all() cannot return another
 * company's row.
 *
 * ─── THE TWO RULES THIS MODEL OWNS ───────────────────────────────────────
 * Both are the reference's, defined once here so the register, the board and
 * the load screen can never disagree about them (reference decision W2):
 *
 *   OPEN    — anything not `Done` is still work.
 *   OVERDUE — open AND past its due date. A finished job is NEVER overdue,
 *             however late it ran; it is done, and the register should stop
 *             shouting about it. An undated job is never overdue either.
 *
 * ─── WHERE WE DEVIATE FROM THE REFERENCE, AND WHY ────────────────────────
 * The reference injects a fixed demo clock (`$today = '2026-07-05'`) so its
 * seeded screenshots stay reproducible — its own decision W1 says "one line
 * changes when the app goes live". This IS live, with the owner's real jobs,
 * so `today()` reads the real date. It stays a single method rather than
 * inline `now()` calls precisely so the whole module still agrees on one date
 * within a request.
 *
 * @property int         $id
 * @property string      $ext_id
 * @property string      $job          what is being made
 * @property string|null $project      a project EXT id ('WAP-101'), not an FK
 * @property string      $station
 * @property string|null $assigned_to  a person's NAME, not an employee id
 * @property string      $status
 * @property \Illuminate\Support\Carbon|null $due
 * @property-read bool   $is_open
 * @property-read bool   $is_overdue
 * @property-read int|null $days_left  negative = days late, null = undated
 */
class Job extends Model
{
    use SoftDeletes;

    protected $table = 'wa_production';

    public const COMPANY = 'woodart';

    /** The shop floor's vocabulary — verbatim from the reference seam (api.js STATIONS). */
    public const STATIONS = ['CNC', 'Cutting', 'Edge Banding', 'Assembly', 'Finishing'];

    /** Verbatim from the reference seam (api.js STATUSES). Also the board's four columns. */
    public const STATUSES = ['Queued', 'Running', 'Blocked', 'Done'];

    /** The dot colour each board column paints (reference view.js STATUS_COLOR). */
    public const STATUS_COLORS = [
        'Queued'  => '#f4b740',
        'Running' => '#2f6bff',
        'Blocked' => '#f0506e',
        'Done'    => '#23c17e',
    ];

    protected $fillable = [
        'ext_id', 'company_id', 'job', 'project', 'station',
        'assigned_to', 'status', 'due', 'created_on',
    ];

    protected $casts = [
        'due'        => 'date',
        'created_on' => 'date',
    ];

    public function getRouteKeyName(): string
    {
        return 'ext_id';
    }

    protected static function booted(): void
    {
        static::addGlobalScope('woodart', fn (Builder $q) => $q->where('company_id', self::COMPANY));
        static::creating(function (self $j) {
            $j->company_id = $j->company_id ?: self::COMPANY;
        });
    }

    /** The one date the whole module measures "overdue" against. See the header. */
    public static function today(): string
    {
        return now()->toDateString();
    }

    /* ── derived, never stored ────────────────────────────────────────────── */

    public function getIsOpenAttribute(): bool
    {
        return $this->status !== 'Done';
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->is_open
            && $this->due !== null
            && $this->due->toDateString() < self::today();
    }

    /** Days until due; negative means late. NULL when there is no due date —
     *  which is different from 0, and the screens must not conflate them. */
    public function getDaysLeftAttribute(): ?int
    {
        if ($this->due === null) {
            return null;
        }

        return (int) round(
            $this->due->startOfDay()->diffInDays(now()->startOfDay(), false) * -1
        );
    }

    /* ── scopes ───────────────────────────────────────────────────────────── */

    public function scopeOpen(Builder $q): Builder
    {
        return $q->where('status', '!=', 'Done');
    }

    public function scopeOverdue(Builder $q): Builder
    {
        return $q->open()->whereNotNull('due')->whereDate('due', '<', self::today());
    }
}
