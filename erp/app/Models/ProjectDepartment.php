<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectDepartment extends Model
{
    protected $table = 'require_types';

    protected $fillable = [
        'company_id',
        'name',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function fieldDefinitions()
    {
        return $this->hasMany(ProjectFieldDefinition::class)->orderBy('sort_order');
    }

    public function projects()
    {
        return $this->hasMany(Project::class);
    }
}
