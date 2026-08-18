<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Company;

class ProjectCategory extends Model
{
    protected $table = 'project_categories';

    protected $fillable = [
        'company_id',
        'name',
        'is_active',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
