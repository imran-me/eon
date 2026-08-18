<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeResignationAttachment extends Model
{
    protected $fillable = [
        'employee_resignation_id',
        'file_path',
        'file_name',
    ];

    public function resignation()
    {
        return $this->belongsTo(EmployeeResignation::class, 'employee_resignation_id');
    }
}
