<?php

namespace Modules\WoodArt\Modules\Projects\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Wood Art · Project — a single interior fit-out job.
 *
 * ─── ISOLATION (CLAUDE.md) ───────────────────────────────────────────────
 * This model reads and writes exactly one table, `wa_projects`, which no other
 * company's code touches. It extends nothing of the host's, shares no base
 * model, and is never resolved outside Wood Art's own controllers.
 *
 * Every query is additionally narrowed to `company_id = 'woodart'` by a global
 * scope (booted() below), so even a bare Project::all() cannot return another
 * concern's rows if this table ever becomes multi-tenant. That string is the
 * reference build's own company key (its CID), NOT the ERP's numeric
 * companies.id of 6 — the table was created by the reference's migration and
 * its existing rows carry 'woodart'. Do not "correct" it to 6; that would
 * orphan every row already stored.
 *
 * @property int                             $id
 * @property string                          $ext_id
 * @property string                          $company_id
 * @property string                          $name
 * @property string|null                     $client
 * @property string                          $type
 * @property int                             $area      square feet
 * @property int                             $value     contract value
 * @property int                             $cost      committed cost
 * @property string                          $stage
 * @property string|null                     $phase
 * @property int                             $progress  0-100
 * @property string|null                     $designer
 * @property \Illuminate\Support\Carbon|null $start
 * @property \Illuminate\Support\Carbon|null $deadline
 * @property bool                            $billed
 * @property \Illuminate\Support\Carbon|null $created_on
 * @property-read int                        $margin
 * @property-read int|null                   $margin_pct
 * @property-read int|null                   $days_left
 * @property-read bool                       $is_live
 * @property-read bool                       $is_at_risk
 */
class Project extends Model
{
    use SoftDeletes;

    /** The reference's table, created by this module's own migration. */
    protected $table = 'wa_projects';

    /** The tenant key every row carries. See the class note above. */
    public const COMPANY = 'woodart';

    /**
     * The five stages, verbatim from the reference (view.js:37). An earlier
     * pass here added a sixth, 'Approval', which the reference does not have —
     * removed while the only stored value is 'Production', so no row is
     * stranded on a stage the board cannot show.
     *
     * These five are load-bearing in the reference: they drive the stage board,
     * the deadline-risk test, and the gate that decides when a job may be
     * billed. ROOT-MAP.md §2.2 proposes a finer-grained sequence but insists it
     * be added as a SEPARATE `phase` column rather than by redefining these —
     * "redefining stage first breaks the billing gate, which is the one thing
     * in this company that moves money." The column exists; nothing reads it yet.
     */
    public const STAGES = ['Design', 'Production', 'Installation', 'Handover', 'Completed'];

    /**
     * Project kinds — verbatim from the reference (view.js:48). An earlier pass
     * here invented a different set (Commercial/Hospitality); corrected to the
     * reference's four while the only stored row is 'Residential', so no
     * existing project is orphaned by the change.
     */
    public const TYPES = ['Residential', 'Office', 'Retail', 'Restaurant'];

    /** Stage accents — reference view.js:41-44. Used by the stage board. */
    public const STAGE_COLORS = [
        'Design'       => '#7b5cff',
        'Production'   => '#2f6bff',
        'Installation' => '#f4b740',
        'Handover'     => '#1A43BF',
        'Completed'    => '#23c17e',
    ];

    /** Type accents — reference view.js:45-47. */
    public const TYPE_COLORS = [
        'Residential' => '#6f9c1c',
        'Office'      => '#2f6bff',
        'Retail'      => '#e0356e',
        'Restaurant'  => '#e2721b',
    ];

    /** A job is "live" until it reaches one of these. */
    public const CLOSED_STAGES = ['Handover', 'Completed'];

    protected $fillable = [
        'ext_id', 'company_id', 'name', 'client', 'type', 'area', 'value', 'cost',
        'stage', 'phase', 'progress', 'designer', 'start', 'deadline', 'billed',
        'created_on',
    ];

    protected $casts = [
        'area'       => 'integer',
        'value'      => 'integer',
        'cost'       => 'integer',
        'progress'   => 'integer',
        'billed'     => 'boolean',
        'start'      => 'date',
        'deadline'   => 'date',
        'created_on' => 'date',
    ];

    /**
     * Route-model binding resolves on `ext_id` ("WAP-101"), not the numeric id.
     *
     * That mirrors the reference build, where `ext_id` is the real key and the
     * auto-increment id is never joined on. It also means a project URL is
     * readable and shareable, and — because the global scope below is applied
     * to binding queries too — a Wood Art code can never resolve to another
     * concern's row.
     */
    public function getRouteKeyName(): string
    {
        return 'ext_id';
    }

    /**
     * Confine every query — and every insert — to Wood Art's own rows. A new
     * Project therefore cannot be written under another company key by
     * accident, only deliberately.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('woodart', fn (Builder $q) => $q->where('company_id', self::COMPANY));
        static::creating(function (self $p) {
            $p->company_id = $p->company_id ?: self::COMPANY;
        });
    }

    /* ── derived values the screens ask for ───────────────────────────────── */

    /** Contract value minus committed cost. */
    public function getMarginAttribute(): int
    {
        return (int) $this->value - (int) $this->cost;
    }

    /** Margin as a whole percentage of contract value; null when unquoted. */
    public function getMarginPctAttribute(): ?int
    {
        return $this->value > 0 ? (int) round($this->margin / $this->value * 100) : null;
    }

    /**
     * Whole days until the deadline — positive in the future, negative once
     * overdue. Null when no deadline is set, which callers must treat as
     * "unknown", not as zero.
     */
    public function getDaysLeftAttribute(): ?int
    {
        if (! $this->deadline) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->deadline->startOfDay(), false);
    }

    public function getIsLiveAttribute(): bool
    {
        return ! \in_array($this->stage, self::CLOSED_STAGES, true);
    }

    /**
     * The reference's "Deadline Risk" test (view.js:651): unfinished, due
     * inside 30 days, and not already handed over.
     */
    public function getIsAtRiskAttribute(): bool
    {
        $d = $this->days_left;

        return $this->progress < 100 && $this->is_live && $d !== null && $d < 30;
    }

    /* ── formatting ───────────────────────────────────────────────────────── */

    /**
     * The reference's compact money, ported exactly (platform/core/ui.js:92-106
     * — ui.money(n, {compact:true})). Bangladeshi numbering, so 70 lakh reads
     * "৳ 70L" and 1.25 crore "৳ 1.25Cr", NOT "7M"/"12.5M". Trailing zeros are
     * trimmed the same way (.00 and .0 dropped), and the sign leads.
     *
     * Note this returns "৳ 0" for zero, not a dash — matching the reference,
     * which only shows a dash for null/NaN.
     */
    public static function money(int|float|null $n): string
    {
        if ($n === null || \is_nan((float) $n)) {
            return '—';
        }

        $abs  = abs($n);
        $sign = $n < 0 ? '-' : '';

        $trim = static fn (string $s): string => rtrim(rtrim($s, '0'), '.');

        if ($abs >= 1e7) {
            return '৳ ' . $sign . $trim(number_format($abs / 1e7, 2, '.', '')) . 'Cr';
        }
        if ($abs >= 1e5) {
            return '৳ ' . $sign . $trim(number_format($abs / 1e5, 2, '.', '')) . 'L';
        }
        if ($abs >= 1e3) {
            return '৳ ' . $sign . $trim(number_format($abs / 1e3, 1, '.', '')) . 'K';
        }

        return '৳ ' . $sign . (int) $abs;
    }

    /* ── scopes ───────────────────────────────────────────────────────────── */

    public function scopeLive(Builder $q): Builder
    {
        return $q->whereNotIn('stage', self::CLOSED_STAGES);
    }
}
