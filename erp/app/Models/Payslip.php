<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payslip extends Model
{
    use HasFactory;

    protected $table = 'payslips';

    protected $fillable = [
        'user_id',    
        'employee_salary_id',    
        'payslip_number',    
        'issue_date',    
        'pdf_path'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function employee_salary()
    {
        return $this->belongsTo(EmployeeSalary::class);
    }
}
