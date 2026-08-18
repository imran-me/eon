<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OfficeTodo extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'department_id',
        'created_by',
        'title',
        'description',
        'start_date',
        'due_date',
        'priority',
        'status',
        'attachment',
        'is_self',
    ];

    protected $casts = [
        'is_self'    => 'boolean',
        'start_date' => 'date',
        'due_date'   => 'date',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignees()
    {
        return $this->belongsToMany(User::class, 'office_todo_assignees', 'office_todo_id', 'user_id')
            ->withPivot(['status', 'completed_at', 'note'])
            ->withTimestamps();
    }

    public function assigneeRecords()
    {
        return $this->hasMany(OfficeTodoAssignee::class);
    }

    public function checklists()
    {
        return $this->hasMany(OfficeTodoChecklist::class)->orderBy('sort_order');
    }
}
