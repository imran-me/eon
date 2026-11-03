<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A stamp saying "this user has looked at this task".
 *
 * Written when the task drawer opens; read by the board to decide whether an
 * admin's action on a task is still unseen and the corner ribbon should show.
 */
class TaskSeen extends Model
{
    protected $table = 'task_seen';

    // seen_at is the only time this row cares about.
    public $timestamps = false;

    protected $fillable = ['task_id', 'user_id', 'seen_at'];

    protected $casts = ['seen_at' => 'datetime'];
}
