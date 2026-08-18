<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequireAssignment extends Model
{
    use HasFactory;

    protected $table = 'require_assignments';

    protected $fillable = [
        'request_id',
        'assigned_to',
        'assigned_by',
        'due_date',
        'reminder_at',
        'fulfilled_at',
        'escalated',
        'fulfillment_note',
    ];

    protected $casts = [
        'due_date'     => 'date',
        'reminder_at'  => 'datetime',
        'fulfilled_at' => 'datetime',
        'escalated'    => 'boolean',
    ];

    public function request()
    {
        return $this->belongsTo(EmployeeRequest::class, 'request_id');
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function isOverdue(): bool
    {
        return !$this->fulfilled_at && $this->due_date && $this->due_date->isPast();
    }
}
