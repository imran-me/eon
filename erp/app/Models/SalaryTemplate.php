<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalaryTemplate extends Model
{
    use HasFactory;

    protected $table = 'salary_templates';

    protected $fillable = [
        'name',
        'company_id',
        'basic_salary',
        'house_rent',
        'medical_allowance',
        'conveyance_allowance',
        'other_allowance',
        'bonus',
        'total_salary'
    ];
}
