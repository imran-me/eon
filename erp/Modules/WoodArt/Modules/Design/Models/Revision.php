<?php

namespace Modules\WoodArt\Modules\Design\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Wood Art · Revision — one entry in a drawing's audit trail.
 *
 * ─── ISOLATION (CLAUDE.md) ───────────────────────────────────────────────
 * Reads and writes exactly one table, `wa_revisions`, pinned by a global scope
 * to `company_id = 'woodart'`. `drawing` holds a drawing ext_id STRING, never
 * a foreign key, so removing a drawing cannot cascade its evidence away.
 *
 * ─── THIS IS EVIDENCE, NOT A CHECKLIST ───────────────────────────────────
 * A revision records who did what to a deliverable and when: drafted, issued
 * to the client, commented on, revised, approved. It is written once and read
 * many times. Nothing in this build edits or deletes an individual revision —
 * appending a correcting entry is how a mistake is handled, the same way a
 * ledger is corrected by a reversal rather than an erasure.
 *
 * @property string      $ext_id
 * @property string      $drawing   a drawing EXT id, not an FK
 * @property string      $rev
 * @property string      $action
 * @property string|null $by        a NAME, not an employee id
 */
class Revision extends Model
{
    use SoftDeletes;

    protected $table = 'wa_revisions';

    public const COMPANY = 'woodart';

    /** Verbatim from the reference seam (api.js ACTIONS). */
    public const ACTIONS = ['Drafted', 'Issued', 'Commented', 'Revised', 'Approved'];

    /**
     * What each action means for the drawing it is logged against. Kept here
     * so the controller cannot invent a different mapping: logging "Issued"
     * always means the same thing to the deliverable's status.
     */
    public const RESULTING_STATUS = [
        'Drafted'   => 'Draft',
        'Issued'    => 'Issued',
        'Commented' => 'Commented',
        'Revised'   => 'Draft',
        'Approved'  => 'Approved',
    ];

    protected $fillable = [
        'ext_id', 'company_id', 'drawing', 'rev', 'action', 'by', 'note', 'date',
    ];

    protected $casts = [
        'date' => 'date',
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

    public function scopeFor(Builder $q, string $drawingExtId): Builder
    {
        return $q->where('drawing', $drawingExtId);
    }
}
