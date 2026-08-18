<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $table = 'payments';

    protected $fillable = [
        'user_id',    
        'employee_salary_id',    
        'payment_date',    
        'bank_id',
        'payment_method',    
        'amount',    
        'transaction_no',    
        'notes'
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
