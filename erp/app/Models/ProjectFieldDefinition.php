<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectFieldDefinition extends Model
{
    protected $fillable = [
        'project_department_id',
        'label',
        'type',
        'options',
        'required',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'required' => 'boolean',
        'status' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function projectDepartment()
    {
        return $this->belongsTo(ProjectDepartment::class);
    }

    public function values()
    {
        return $this->hasMany(ProjectFieldValue::class);
    }

    public function getOptionsArrayAttribute(): array
    {
        if (empty($this->options)) {
            return [];
        }
        return array_map('trim', explode(',', $this->options));
    }
}
