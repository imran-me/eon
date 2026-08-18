<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'project_category_id',
        'department_id',
        'customer_id',
        'company_id',
        'lead_id',
        'project_name',
        'color',
        'start_date',
        'end_date',
        'status',
        'budget',
        'description',
        'team_members',
        'type',
    ];

    protected $casts = [
        'team_members' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
        'budget' => 'decimal:2',
        'lead_id' => 'integer',
    ];

    // Relationships
    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function isFromCrm(): bool
    {
        return !is_null($this->lead_id);
    }

    public function projectCategory()
    {
        return $this->belongsTo(ProjectCategory::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function fieldValues()
    {
        return $this->hasMany(ProjectFieldValue::class)->with('definition');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function modules()
    {
        return $this->hasMany(Board::class)->orderBy('name', 'asc');
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    // Get team members as User models
    public function teamMembers()
    {
        if (empty($this->team_members)) {
            return collect();
        }
        return \App\Models\User::whereIn('id', $this->team_members)->get();
    }

    /**
     * Get color details with Tailwind classes
     */
    public function getColorDetailsAttribute()
    {
        $colors = [
            'gray' => [
                'bg' => 'bg-gray-100',
                'text' => 'text-gray-700',
                'gradient' => 'linear-gradient(135deg, #F3F4F6 0%, #9CA3AF 100%)'
            ],
            'blue' => [
                'bg' => 'bg-blue-100',
                'text' => 'text-blue-700',
                'gradient' => 'linear-gradient(135deg, #DBEAFE 0%, #60A5FA 100%)'
            ],
            'purple' => [
                'bg' => 'bg-purple-100',
                'text' => 'text-purple-700',
                'gradient' => 'linear-gradient(135deg, #EDE9FE 0%, #A78BFA 100%)'
            ],
            'green' => [
                'bg' => 'bg-green-100',
                'text' => 'text-green-700',
                'gradient' => 'linear-gradient(135deg, #023020 0%, #34D399 100%)'
            ],
            'yellow' => [
                'bg' => 'bg-yellow-100',
                'text' => 'text-yellow-700',
                'gradient' => 'linear-gradient(135deg, #FEF3C7 0%, #FBBF24 100%)'
            ],
            'red' => [
                'bg' => 'bg-red-100',
                'text' => 'text-red-700',
                'gradient' => 'linear-gradient(135deg, #FEE2E2 0%, #F87171 100%)'
            ],
            'indigo' => [
                'bg' => 'bg-indigo-100',
                'text' => 'text-indigo-700',
                'gradient' => 'linear-gradient(135deg, #E0E7FF 0%, #818CF8 100%)'
            ],
            'pink' => [
                'bg' => 'bg-pink-100',
                'text' => 'text-pink-700',
                'gradient' => 'linear-gradient(135deg, #FCE7F3 0%, #F472B6 100%)'
            ],
            'orange' => [
                'bg' => 'bg-orange-100',
                'text' => 'text-orange-700',
                'gradient' => 'linear-gradient(135deg, #FFEDD5 0%, #FB923C 100%)'
            ],
            'teal' => [
                'bg' => 'bg-teal-100',
                'text' => 'text-teal-700',
                'gradient' => 'linear-gradient(135deg, #CCFBF1 0%, #2DD4BF 100%)'
            ],
        ];

        return $colors[$this->color] ?? $colors['blue'];
    }
}
