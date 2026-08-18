<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A department the expense desk budgets and reports against.
 *
 * Deliberately NOT App\Models\Department, which is HR's and exists to place
 * people. This one belongs to exactly one company, which is what lets the expense
 * form fill the company in the moment a department is picked. See the migration
 * for the full reasoning.
 */
class ExpenseDepartment extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'expense_departments';

    protected $fillable = [
        'user_id',
        'company_id',
        'name',
        'description',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class, 'expense_department_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }
}
