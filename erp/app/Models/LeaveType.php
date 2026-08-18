<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    use HasFactory;

    protected $table = 'leave_types';

    protected $fillable = [
        'name',
        'max_leaves_count',
        'requires_time',
        'exempts_early_out_deduction',
    ];

    protected $casts = [
        'requires_time' => 'boolean',
        'exempts_early_out_deduction' => 'boolean',
    ];
}
