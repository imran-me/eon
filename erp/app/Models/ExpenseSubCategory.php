<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExpenseSubCategory extends Model
{
    use HasFactory;

    protected $table = 'expense_sub_categories';

    protected $fillable = [
        'user_id',
        'company_id',
        'expense_category_id',
        'account_id',
        'name',
        'description',
        'status'
    ];

    /**
     * The chart-of-accounts line this sub-category posts to.
     *
     * Finer than the category's, and preferred over it — a phone bill and a
     * broadband bill are both Communication but are not the same ledger line.
     * See ExpenseClassificationService::accountFor().
     */
    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function expense_category()
    {
        return $this->belongsTo(ExpenseCategory::class);
    }
}
