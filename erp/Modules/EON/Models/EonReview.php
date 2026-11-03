<?php

namespace Modules\EON\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * A written evaluation of one person for one month.
 *
 * EVERY read of this model goes through installed() first. Deploys land before
 * migrations run — that is not a hypothetical here, it is the sequence that
 * produced the 2026-08-10 outage — so a page that assumes the table exists will
 * be wrong on the server for the window between upload and migrate. When the
 * table is missing, EON renders in full and the review panel says it is not
 * installed yet.
 */
class EonReview extends Model
{
    protected $table = 'eon_reviews';

    protected $fillable = [
        'user_id',
        'period',
        'overall_rating',
        'verdict',
        'summary',
        'actions',
        'machine_score',
        'machine_band',
        'machine_confidence',
        'snapshot_id',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'overall_rating' => 'integer',
        'machine_score'  => 'float',
        'reviewed_at'    => 'datetime',
    ];

    /** The four verdicts, and what each one commits the reviewer to. */
    public const VERDICTS = [
        'commend'      => 'Commend — above what the role asks',
        'satisfactory' => 'Satisfactory — meeting expectations',
        'improve'      => 'Needs improvement — agreed actions below',
        'escalate'     => 'Escalate — formal follow-up required',
    ];

    private static ?bool $installed = null;

    public static function installed(): bool
    {
        if (self::$installed === null) {
            try {
                self::$installed = Schema::hasTable('eon_reviews');
            } catch (Throwable $e) {
                self::$installed = false;
            }
        }

        return self::$installed;
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function subject()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function verdictLabel(): string
    {
        return self::VERDICTS[$this->verdict] ?? '—';
    }

    /** How far the human landed from the machine, in points. Null if either is absent. */
    public function disagreement(): ?float
    {
        if ($this->overall_rating === null || $this->machine_score === null) {
            return null;
        }

        // The 1-5 rating on the same 0-100 scale the engine uses.
        return round(((($this->overall_rating - 1) / 4) * 100) - $this->machine_score, 1);
    }
}
