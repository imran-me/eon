<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectFieldValue extends Model
{
    protected $fillable = [
        'project_id',
        'project_field_definition_id',
        'value',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function definition()
    {
        return $this->belongsTo(ProjectFieldDefinition::class, 'project_field_definition_id');
    }
}
