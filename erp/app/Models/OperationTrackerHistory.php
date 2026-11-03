<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationTrackerHistory extends Model
{
    protected $fillable = [
        'operation_tracker_id',
        'event_type',
        'from_status',
        'to_status',
        'note',
        'changed_by',
    ];

    public function tracker()
    {
        return $this->belongsTo(OperationTracker::class, 'operation_tracker_id');
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
