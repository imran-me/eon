<?php

namespace Modules\WoodArt\Modules\Installation\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Wood Art · Install — one site visit: delivery, fitting, snagging, handover.
 *
 * ─── ISOLATION (CLAUDE.md) ───────────────────────────────────────────────
 * Reads and writes exactly one table, `wa_installs`, which no other company
 * touches. It extends nothing of the host's and declares no relationship to
 * any shared model. A global scope pins every query and insert to
 * `company_id = 'woodart'`.
 *
 * ─── THE RULES THIS MODEL OWNS ───────────────────────────────────────────
 * All the reference's, defined once here so the schedule, the snag queue and
 * the team view can never disagree (decision I6):
 *
 *   OPEN            — anything not `Handover` is still live work.
 *   OVERDUE         — open AND past its visit date. A handed-over site is
 *                     never overdue however late it ran; an undated one never
 *                     is either.
 *   CLEAN HANDOVER  — handed over AND zero open snags. A site CAN be marked
 *                     Handover with snags still open, and the business wants
 *                     to SEE that rather than have it hidden.
 *
 * ─── THE SNAG COUNT: TWO SHAPES, ONE ANSWER (decision I1) ────────────────
 * A record may carry a plain numeric `snags` count, or an itemised
 * `snag_list` of {text, done}. `open_snags` prefers the LIST when it exists —
 * counting the un-done items, not the array length — and falls back to the
 * number. Reading only one shape would make this module disagree with whatever
 * itemised the list, for exactly the records someone has actually touched.
 *
 * Nothing in this Laravel build writes `snag_list` yet: the reference itemises
 * snags in the Projects drawer, and decision I5 is explicit that two editors
 * for one list is a bug factory. The dual read is here so imported reference
 * data is counted correctly, and so the column means one thing when an editor
 * does arrive.
 *
 * ─── WHERE WE DEVIATE FROM THE REFERENCE, AND WHY ────────────────────────
 * The reference injects a fixed demo clock (decision I7). This is live with
 * real site visits, so today() reads the real date — the same deviation, for
 * the same reason, as Workshop's Job model.
 *
 * @property int         $id
 * @property string      $ext_id
 * @property string|null $project     a project EXT id ('WAP-101'), not an FK
 * @property string      $site
 * @property string|null $team
 * @property string      $status
 * @property \Illuminate\Support\Carbon|null $date
 * @property int         $snags       the stored OPEN count
 * @property array|null  $snag_list
 * @property-read int    $open_snags  the authoritative count — read this, not $snags
 * @property-read bool   $is_open
 * @property-read bool   $is_overdue
 * @property-read bool   $is_clean_handover
 * @property-read int|null $days_left negative = days late, null = undated
 */
class Install extends Model
{
    use SoftDeletes;

    protected $table = 'wa_installs';

    public const COMPANY = 'woodart';

    /** Verbatim from the reference seam (api.js STATUSES). */
    public const STATUSES = ['Scheduled', 'In Progress', 'Snagging', 'Handover'];

    /** The colour each status carries on screen, matching the suite's palette. */
    public const STATUS_COLORS = [
        'Scheduled'   => '#f4b740',
        'In Progress' => '#2f6bff',
        'Snagging'    => '#f0506e',
        'Handover'    => '#23c17e',
    ];

    protected $fillable = [
        'ext_id', 'company_id', 'project', 'site', 'team',
        'status', 'date', 'snags', 'snag_list', 'created_on',
    ];

    protected $casts = [
        'date'       => 'date',
        'created_on' => 'date',
        'snags'      => 'integer',
        'snag_list'  => 'array',
    ];

    public function getRouteKeyName(): string
    {
        return 'ext_id';
    }

    protected static function booted(): void
    {
        static::addGlobalScope('woodart', fn (Builder $q) => $q->where('company_id', self::COMPANY));

        static::creating(function (self $i) {
            $i->company_id = $i->company_id ?: self::COMPANY;
        });

        // Decision I2: a supplied list always RECOMPUTES the stored count. The
        // handover queue is ordered by that figure, so a stale number must
        // never be able to outrank a real one. Enforced on the model rather
        // than in the controller so no future write path can skip it.
        static::saving(function (self $i) {
            if (is_array($i->snag_list) && $i->snag_list !== []) {
                $i->snags = self::countOpen($i->snag_list);
            }
        });
    }

    /** The one date the whole module measures "overdue" against. See the header. */
    public static function today(): string
    {
        return now()->toDateString();
    }

    /** Un-done items in an itemised list — NOT the array length. */
    public static function countOpen(array $list): int
    {
        return count(array_filter($list, fn ($s) => empty($s['done'])));
    }

    /* ── derived, never stored ────────────────────────────────────────────── */

    /** THE snag count. Read this everywhere; never read `snags` directly. */
    public function getOpenSnagsAttribute(): int
    {
        if (is_array($this->snag_list) && $this->snag_list !== []) {
            return self::countOpen($this->snag_list);
        }

        return max(0, (int) $this->snags);
    }

    public function getIsOpenAttribute(): bool
    {
        return $this->status !== 'Handover';
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->is_open
            && $this->date !== null
            && $this->date->toDateString() < self::today();
    }

    /** Handed over AND nothing outstanding — the only fully finished state. */
    public function getIsCleanHandoverAttribute(): bool
    {
        return ! $this->is_open && $this->open_snags === 0;
    }

    /** Days until the visit; negative means late. NULL when undated. */
    public function getDaysLeftAttribute(): ?int
    {
        if ($this->date === null) {
            return null;
        }

        return (int) round(
            $this->date->startOfDay()->diffInDays(now()->startOfDay(), false) * -1
        );
    }

    /* ── scopes ───────────────────────────────────────────────────────────── */

    public function scopeOpen(Builder $q): Builder
    {
        return $q->where('status', '!=', 'Handover');
    }

    public function scopeOverdue(Builder $q): Builder
    {
        return $q->open()->whereNotNull('date')->whereDate('date', '<', self::today());
    }
}
