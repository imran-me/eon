<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Leave extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'leaves';

    protected $fillable = [
        'user_id',
        'leave_type_id',
        'company_id',
        'start_date',
        'end_date',
        'leave_time',
        'reason',
        'status'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
    public function leave_type()
    {
        return $this->belongsTo(LeaveType::class);
    }

    /**
     * Find an approved leave for this user/date whose leave type waives the
     * automatic early-checkout deduction (e.g. an approved "Early Leave" request).
     */
    public static function findApprovedEarlyLeaveForDate(int $userId, string $dateString): ?self
    {
        return static::query()
            ->join('leave_types', 'leave_types.id', '=', 'leaves.leave_type_id')
            ->where('leaves.user_id', $userId)
            ->where('leaves.status', 'approved')
            ->where('leave_types.exempts_early_out_deduction', true)
            ->whereDate('leaves.start_date', '<=', $dateString)
            ->whereDate('leaves.end_date', '>=', $dateString)
            ->select('leaves.*')
            ->first();
    }
}
