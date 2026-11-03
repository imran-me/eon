<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringExpenseGeneration extends Model
{
    public $timestamps = false;

    protected $fillable = ['recurring_expense_id', 'period', 'expense_id', 'generated_at'];

    protected $casts = ['period' => 'date', 'generated_at' => 'datetime'];

    public function recurringExpense(): BelongsTo
    {
        return $this->belongsTo(RecurringExpense::class);
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }
}
