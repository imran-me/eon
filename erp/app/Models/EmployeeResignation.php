<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeResignation extends Model
{
    use softDeletes;
    protected $fillable = [
        'employee_id',
        'resign_date',
        'last_working_day',
        'resign_type',
        'reason',
        'notice_period_days',
        'status',
        'approved_by',
        'approved_at',
        'exit_note',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function attachments()
    {
        return $this->hasMany(EmployeeResignationAttachment::class, 'employee_resignation_id');
    }
    protected $casts = [
        'resign_date' => 'date',
        'last_working_day' => 'date',
        'approved_at' => 'datetime',
    ];
}
