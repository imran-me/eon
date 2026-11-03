<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The super admin's verdict on a task: locked, flagged, rated.
 *
 * Written from Employee Task Oversight; read by the board and the drawer for
 * everyone, because a lock the employee cannot see is a lock that only produces
 * a confusing error. One row per task, created the first time somebody acts.
 *
 * A task with no row here is the normal case and means "no verdict" — never
 * assume this relation is loaded, and never assume it exists.
 */
class TaskOversight extends Model
{
    protected $table = 'task_oversight';

    protected $fillable = [
        'task_id',
        'is_restricted',
        'restriction_note',
        'restricted_by',
        'restricted_at',
        'red_flag',
        'red_flag_note',
        'red_flagged_by',
        'red_flagged_at',
        'rating',
        'rating_note',
        'rated_by',
        'rated_at',
    ];

    protected $casts = [
        'is_restricted'  => 'boolean',
        'red_flag'       => 'boolean',
        'rating'         => 'integer',
        'restricted_at'  => 'datetime',
        'red_flagged_at' => 'datetime',
        'rated_at'       => 'datetime',
    ];

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function restrictedBy()
    {
        return $this->belongsTo(User::class, 'restricted_by');
    }

    public function redFlaggedBy()
    {
        return $this->belongsTo(User::class, 'red_flagged_by');
    }

    public function ratedBy()
    {
        return $this->belongsTo(User::class, 'rated_by');
    }

    /**
     * True when there is nothing left worth keeping a row for. The write path
     * deletes the row instead of storing an all-off verdict, so "has a row"
     * stays a useful signal and the table does not fill with blanks.
     */
    public function isEmpty(): bool
    {
        return !$this->is_restricted
            && !$this->red_flag
            && $this->rating === null;
    }
}
