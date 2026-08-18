<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeePromotion extends Model
{
    use HasFactory;

    protected $table = 'employee_promotions';

    protected $fillable = [
        'user_id',    
        'previous_designation_id',    
        'new_designation_id',
        'previous_department_id',
        'new_department_id',
        'approved_at',    
        'effective_from',
        'previous_salary',    
        'new_salary',    
        'reason',    
        'status'
    ];

    protected $casts = [
        'approved_at'   => 'date',
        'effective_from' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
    public function previousDesignation()
    {
        return $this->belongsTo(Designation::class, 'previous_designation_id', 'id');
    }
    public function newDesignation()
    {
        return $this->belongsTo(Designation::class, 'new_designation_id', 'id');
    }
    public function previousDepartment()
    {
        return $this->belongsTo(Department::class, 'previous_department_id', 'id');
    }
    public function newDepartment()
    {
        return $this->belongsTo(Department::class, 'new_department_id', 'id');
    }
}
