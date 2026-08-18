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
        'title',
        'description',
        'priority',
        'start_date',
        'due_date',
        'completed_at',
        'position',
        'created_by',
        'assigned_to',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'due_date' => 'datetime',
        'completed_at' => 'datetime',
    ];

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
}
