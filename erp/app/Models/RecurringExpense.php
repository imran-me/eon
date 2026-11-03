<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RecurringExpense extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'expense_department_id', 'name', 'description', 'generation_day',
        'start_month', 'end_month', 'payment_mode', 'bank_id', 'is_active', 'created_by',
    ];

    protected $casts = [
        'start_month' => 'date',
        'end_month' => 'date',
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function expenseDepartment(): BelongsTo
    {
        return $this->belongsTo(ExpenseDepartment::class);
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(RecurringExpenseLine::class)->orderBy('sort_order');
    }

    public function generations(): HasMany
    {
        return $this->hasMany(RecurringExpenseGeneration::class);
    }
}
