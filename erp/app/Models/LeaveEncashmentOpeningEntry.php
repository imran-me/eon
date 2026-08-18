<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A one-time manual "opening entry" per employee — days + amount they're
 * credited with toward their first leave-encashment payout, for time that
 * predates clean per-month leave_deduction tracking (e.g. before the
 * Feb 2026 cutover). Added into PayrollService's accrual calculation only
 * until that employee's first SalaryReconciliation exists — after that,
 * real monthly leave_deduction data carries the calculation and this entry
 * is no longer added, so it can never be double-counted.
 */
class LeaveEncashmentOpeningEntry extends Model
{
    protected $table = 'leave_encashment_opening_entries';

    protected $fillable = [
        'user_id',
        'days',
        'amount',
        'as_of_date',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'as_of_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
