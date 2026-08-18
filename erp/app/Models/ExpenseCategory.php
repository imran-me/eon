<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExpenseCategory extends Model
{
    use HasFactory;

    protected $table = 'expense_categories';

    protected $fillable = [
        'user_id',
        'company_id',
        'account_id',
        'name',
        'description',
        'status'
    ];

    /** The chart-of-accounts expense account this category posts to. */
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

    /** Sub-categories filed under this category. */
    public function subCategories()
    {
        return $this->hasMany(ExpenseSubCategory::class, 'expense_category_id');
    }
}
