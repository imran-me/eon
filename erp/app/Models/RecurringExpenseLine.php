<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringExpenseLine extends Model
{
    protected $fillable = [
        'recurring_expense_id', 'expense_category_id', 'expense_sub_category_id',
        'account_id', 'description', 'amount', 'sort_order',
    ];

    protected $casts = ['amount' => 'decimal:2'];

    public function recurringExpense(): BelongsTo
    {
        return $this->belongsTo(RecurringExpense::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
