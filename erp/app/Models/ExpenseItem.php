<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseItem extends Model
{
    protected $fillable = ['expense_id', 'account_id', 'expense_category_id', 'expense_sub_category_id', 'description', 'quantity', 'unit_price', 'amount'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function subCategory(): BelongsTo
    {
        return $this->belongsTo(ExpenseSubCategory::class, 'expense_sub_category_id');
    }

    protected $casts = [
        'quantity' => 'float',
        'unit_price' => 'float',
        'amount' => 'float',
    ];

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
