<?php

namespace Modules\WoodArt\Modules\Design\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Wood Art · Drawing — one design deliverable.
 *
 * ─── ISOLATION (CLAUDE.md) ───────────────────────────────────────────────
 * Reads and writes exactly one table, `wa_drawings`, pinned by a global scope
 * to `company_id = 'woodart'`. `project` and `designer` are STRINGS — an
 * ext_id and a name — never foreign keys.
 *
 * ─── THE RULES THIS MODEL OWNS ───────────────────────────────────────────
 *   OPEN     — anything not Approved is still design work in flight.
 *   WAITING  — status Issued: sitting with the CLIENT. This is the only state
 *              where the delay is somebody else's; every other open state is
 *              on us, and the approvals queue exists to keep that visible.
 *
 * `rev` is a single letter from A upward, and the revision COUNT is derived
 * from it (A = none) rather than stored, so the letter and the count can never
 * drift apart.
 *
 * @property string      $ext_id
 * @property string      $title
 * @property string      $kind
 * @property string|null $project    a project EXT id, not an FK
 * @property string|null $designer   a NAME, not an employee id
 * @property string      $rev        one letter, A upward
 * @property string      $status
 * @property-read bool   $is_open
 * @property-read bool   $is_waiting
 * @property-read int|null $waiting_days  days with the client, null if not issued
 * @property-read int    $rev_count
 * @property-read string $next_rev
 */
class Drawing extends Model
{
    use SoftDeletes;

    protected $table = 'wa_drawings';

    public const COMPANY = 'woodart';

    /** Verbatim from the reference seam (api.js KINDS). */
    public const KINDS = ['Plan', 'Elevation', 'Section', 'Detail', '3D Model', 'Render'];

    /** Verbatim from the reference seam (api.js STATUSES), in lifecycle order. */
    public const STATUSES = ['Draft', 'Issued', 'Commented', 'Approved'];

    protected $fillable = [
        'ext_id', 'company_id', 'title', 'kind', 'project', 'designer',
        'rev', 'status', 'issued', 'approved', 'created_on',
    ];

    protected $casts = [
        'issued'     => 'date',
        'approved'   => 'date',
        'created_on' => 'date',
    ];

    public function getRouteKeyName(): string
    {
        return 'ext_id';
    }

    protected static function booted(): void
    {
        static::addGlobalScope('woodart', fn (Builder $q) => $q->where('company_id', self::COMPANY));
        static::creating(function (self $d) {
            $d->company_id = $d->company_id ?: self::COMPANY;
        });
    }

    public static function today(): string
    {
        return now()->toDateString();
    }

    /* ── derived, never stored ────────────────────────────────────────────── */

    public function getIsOpenAttribute(): bool
    {
        return $this->status !== 'Approved';
    }

    /** With the client, waiting on their answer. */
    public function getIsWaitingAttribute(): bool
    {
        return $this->status === 'Issued';
    }

    /** How long it has sat with the client. NULL when it is not with them. */
    public function getWaitingDaysAttribute(): ?int
    {
        if (! $this->is_waiting || $this->issued === null) {
            return null;
        }

        return max(0, (int) round(
            $this->issued->startOfDay()->diffInDays(now()->startOfDay(), false)
        ));
    }

    /** Times revised — A is the first draft, so A means none. */
    public function getRevCountAttribute(): int
    {
        $n = \ord(strtoupper(substr((string) ($this->rev ?: 'A'), 0, 1))) - 65;

        return max(0, $n);
    }

    /** The letter after the current one, capped at Z. */
    public function getNextRevAttribute(): string
    {
        $c = \ord(strtoupper(substr((string) ($this->rev ?: 'A'), 0, 1)));

        return \chr(min(90, $c + 1));
    }

    /* ── scopes ───────────────────────────────────────────────────────────── */

    public function scopeOpen(Builder $q): Builder
    {
        return $q->where('status', '!=', 'Approved');
    }

    public function scopeWaiting(Builder $q): Builder
    {
        return $q->where('status', 'Issued');
    }
}
