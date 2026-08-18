<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

// use Illuminate\Database\Eloquent\SoftDeletes;

class WorkspaceUser extends Model
{
    // use SoftDeletes;
    
    protected $table = 'workspace_users';
    
    protected $fillable = [
        'workspace_id',
        'project_id',
        'user_id',
        'role',
    ];

    public function workspace()
    {
        return $this->belongsTo(Company::class, 'workspace_id');
    }

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeGroupedByProject($query)
    {
        return $query->select('project_id', DB::raw('MIN(created_at) as created_at'))
            ->groupBy('project_id');
    }

}
