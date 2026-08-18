<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Board extends Model
{
    protected $fillable = ['workspace_id', 'project_id', 'name', 'description', 'created_by'];

    public function board_columns()
    {
        return $this->hasMany(BoardColumn::class)->orderBy('position', 'asc');
    }
    public function columns()
    {
        return $this->belongsToMany(Column::class, 'board_columns')
            ->withPivot('position')
            ->orderBy('board_columns.position', 'asc');
    }
    public function tasks()
    {
        return $this->hasMany(Task::class);
    }
    public function workspace()
    {
        return $this->belongsTo(Company::class);
    }
    public function project()
    {
        return $this->belongsTo(Project::class);
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
