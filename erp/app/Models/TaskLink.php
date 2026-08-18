<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskLink extends Model
{
    protected $fillable = [
        'task_id',
        'url',
        'display_title',
        'created_by'
    ];

    public function task()
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
