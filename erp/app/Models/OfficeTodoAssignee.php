<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfficeTodoAssignee extends Model
{
    protected $table = 'office_todo_assignees';

    protected $fillable = [
        'office_todo_id',
        'user_id',
        'status',
        'completed_at',
        'note',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public function todo()
    {
        return $this->belongsTo(OfficeTodo::class, 'office_todo_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
