<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'parent_id',
        'company_id',
        'project_id',
        'board_id',
        'column_id',
        'pre_complete_column_id',
        'title',
        'description',
        'priority',
        'start_date',
        'due_date',
        'completed_at',
        'position',
        'created_by',
        'assigned_to',
        'timer_accum_ms',
        'timer_started_at',
        'timer_first_started_at',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'due_date' => 'datetime',
        'completed_at' => 'datetime',
        'timer_started_at' => 'datetime',
        'timer_first_started_at' => 'datetime',
        'timer_accum_ms' => 'integer',
    ];

    /**
     * A timer left running overnight would otherwise bank fourteen hours of
     * "work". Cap one unbroken run at eight hours, the same ceiling the
     * reference build uses.
     */
    public const TIMER_MAX_RUN_MS = 8 * 3600 * 1000;

    public function isTimerRunning(): bool
    {
        return !is_null($this->timer_started_at);
    }

    /**
     * Banked time plus the run in progress, capped. Read-only — call
     * reconcileTimer() when the capped value needs to be written back.
     */
    public function elapsedMs(): int
    {
        $elapsed = (int) $this->timer_accum_ms;

        if ($this->isTimerRunning()) {
            /* Signed, deliberately. diffInMilliseconds($x, true) is the ABSOLUTE
             * difference, so a timer_started_at in the FUTURE came back as a
             * large positive run and the card claimed hours it had not worked —
             * possible since the Start chip can re-base the clock, and the user
             * can type any date into it. Subtracting the timestamps keeps the
             * sign, and max(0) then does the job its name implies.
             *
             * The int cast stays: Carbon 3 returns a float and the JSON payload
             * would otherwise ship "1.4210854715202e-14"-style noise. */
            $run = (int) max(0, now()->getTimestampMs() - $this->timer_started_at->getTimestampMs());
            $elapsed += min($run, self::TIMER_MAX_RUN_MS);
        }

        return $elapsed;
    }

    /**
     * Force-pause a run that has exceeded the cap, banking exactly the cap.
     * Called on read so a forgotten tab self-corrects without a scheduled job.
     */
    public function reconcileTimer(): void
    {
        if (!$this->isTimerRunning()) {
            return;
        }

        if (now()->diffInMilliseconds($this->timer_started_at, true) <= self::TIMER_MAX_RUN_MS) {
            return;
        }

        $this->forceFill([
            'timer_accum_ms'   => (int) $this->timer_accum_ms + self::TIMER_MAX_RUN_MS,
            'timer_started_at' => null,
        ])->save();
    }

    /**
     * "1 Hour 35 min" / "35 min" / "12 sec" — the long form the reference prints.
     */
    /**
     * The same span, down to the second — "55 min 12 sec", or "55m 12s" short.
     *
     * humanDuration() rounds to the minute, which is right for a total nobody
     * is watching but wrong for a clock that is running: at minute resolution
     * the number sits still for sixty seconds at a time and reads as broken.
     *
     * The short form is what the card's chip uses. It is not merely compact —
     * it is NARROWER than the rounded form at every scale that matters
     * ("2h 15m 30s" against "2 Hours 15 min"), so the footer gains detail
     * without gaining width.
     */
    /**
     * What the card's chip writes: seconds up to an hour, minutes past it.
     *
     * "1h 16m 26s" is four characters more than the footer can spare, and the
     * seconds stop being the interesting part once the reading is in hours —
     * nobody watches a card tick at that scale. Under an hour they are exactly
     * what makes the chip legible as a live clock, so they stay.
     *
     * The expanded panel keeps them at every scale (humanDurationPrecise): that
     * row has the width, and it is where somebody is actually timing the work.
     */
    public static function humanDurationCard(int $ms): string
    {
        $seconds = intdiv(max(0, $ms), 1000);
        $hours   = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        if ($hours > 0) {
            return "{$hours}h {$minutes}m";
        }

        return $minutes > 0 ? "{$minutes}m " . ($seconds % 60) . 's' : ($seconds % 60) . 's';
    }

    public static function humanDurationPrecise(int $ms, bool $short = false): string
    {
        $seconds = intdiv(max(0, $ms), 1000);
        $hours   = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $secs    = $seconds % 60;

        if ($short) {
            if ($hours > 0)   { return "{$hours}h {$minutes}m {$secs}s"; }
            if ($minutes > 0) { return "{$minutes}m {$secs}s"; }

            return "{$secs}s";
        }

        if ($hours > 0) {
            return $hours . ' Hour' . ($hours > 1 ? 's' : '') . " {$minutes} min {$secs} sec";
        }

        return $minutes > 0 ? "{$minutes} min {$secs} sec" : "{$secs} sec";
    }

    public static function humanDuration(int $ms): string
    {
        $seconds = intdiv(max(0, $ms), 1000);
        $hours   = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        if ($hours > 0) {
            return $hours . ' Hour' . ($hours > 1 ? 's' : '') . ' ' . $minutes . ' min';
        }

        return $minutes > 0 ? $minutes . ' min' : $seconds . ' sec';
    }

    /**
     * "17:09:33 24/08/26" — HHMMSS DDMMYY, the owner's format for the task
     * module. Rendered server side so it lands in the app timezone
     * (Asia/Dhaka) like every other date here, rather than whatever the
     * viewer's browser happens to be set to.
     *
     * Seconds matter: without them an eight-second item printed the same value
     * at both ends of its range chip and read as broken. The JS twin is
     * formatDateDisplay() in resources/js/task/task-utils.js — change both.
     */
    public static function stampLabel($value): ?string
    {
        return $value ? $value->format('H:i:s d/m/y') : null;
    }

    public function column()
    {
        return $this->belongsTo(Column::class);
    }
    public function board()
    {
        return $this->belongsTo(Board::class);
    }
    // public function assignedUser()
    // {
    //     return $this->belongsTo(User::class, 'assigned_to');
    // }
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function users()
    {
        return $this->belongsToMany(User::class);
    }
    public function labels()
    {
        return $this->belongsToMany(Label::class)->withTimestamps();
    }
    public function links()
    {
        return $this->hasMany(TaskLink::class)->orderBy('created_at', 'desc');
    }
    public function activityLogs()
    {
        return $this->hasMany(TaskActivityLog::class)->orderBy('created_at', 'desc');
    }
    public function comments()
    {
        return $this->hasMany(TaskComment::class)->orderBy('created_at', 'asc');
    }
    public function attachments()
    {
        return $this->hasMany(TaskAttachment::class)->orderBy('created_at', 'desc');
    }

    public function parent()
    {
        return $this->belongsTo(Task::class, 'parent_id');
    }

    public function subtasks()
    {
        return $this->hasMany(Task::class, 'parent_id')->orderBy('created_at');
    }

    /**
     * The super admin's verdict on this task — lock, red flag, rating.
     *
     * Usually absent: a row exists only once somebody has acted, so every
     * reader has to cope with null. Written from Employee Task Oversight.
     */
    public function oversight()
    {
        return $this->hasOne(TaskOversight::class);
    }

    /**
     * Locked by a super admin, so nobody else may edit or delete it.
     *
     * Reads the loaded relation when it is there and falls back to a query
     * when it is not, because the guards in TaskController::update/destroy run
     * on a route-model-bound task that has nothing eager-loaded.
     */
    public function isRestricted(): bool
    {
        return (bool) ($this->relationLoaded('oversight')
            ? ($this->oversight?->is_restricted)
            : $this->oversight()->value('is_restricted'));
    }
}
