<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A standing sum of cash held by one person on the company's behalf.
 *
 * The float is not an expense and not a loan — it is company money that happens
 * to be in a pocket instead of the drawer. What is actually in that pocket is
 * never stored here; it is Dr − Cr on the float account for this custodian, read
 * back by PettyCashService::balanceOf(). A stored balance would be one more thing
 * that can disagree with the ledger, and the ledger would still be right.
 *
 * `float_limit` is the imprest amount — the figure the custodian is topped back up
 * TO, not the last amount handed over.
 */
class PettyCashFloat extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'custodian_id',
        'account_id',
        'float_limit',
        'note',
        'status',
        'created_by',
    ];

    protected $casts = [
        'float_limit' => 'decimal:2',
        'status'      => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /** The person holding the money. */
    public function custodian()
    {
        return $this->belongsTo(User::class, 'custodian_id');
    }

    /** The asset account this float sits in — 1165 unless overridden. */
    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Hand-overs and hand-backs. Spending is in expenses(), not here. */
    public function transactions()
    {
        return $this->hasMany(PettyCashTransaction::class);
    }

    /** What was actually bought with this float. */
    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }
}
