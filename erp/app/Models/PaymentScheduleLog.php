<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentScheduleLog extends Model
{
    protected $table = 'payment_schedule_logs';

    protected $fillable = [
        'payment_schedule_id',
        'action',
        'old_date',
        'new_date',
        'reason',
        'done_by',
    ];

    protected $casts = [
        'old_date' => 'date',
        'new_date' => 'date',
    ];

    public function schedule()
    {
        return $this->belongsTo(PaymentSchedule::class, 'payment_schedule_id');
    }

    public function doneBy()
    {
        return $this->belongsTo(User::class, 'done_by');
    }
}
