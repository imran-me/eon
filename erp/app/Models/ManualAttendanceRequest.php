<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ManualAttendanceRequest extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'requested_date',
        'requested_time',
        'selfie',
        'status',
        'approved_date',
        'approved_time',
        'attendance_log_id',
        'reviewed_by',
        'reviewed_at',
        'review_note',
    ];

    protected $casts = [
        'requested_date' => 'date',
        'approved_date' => 'date',
        'reviewed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function attendanceLog()
    {
        return $this->belongsTo(AttendanceLog::class);
    }
}
