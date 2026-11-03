<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationTracker extends Model
{
    public const TYPES = ['visa', 'file', 'ticket', 'contract_flight'];
    public const STATUSES = ['new', 'in_progress', 'waiting', 'ready', 'completed', 'rejected'];
    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    /** History rows that are a human message rather than a state change. */
    public const NOTE_EVENT = 'note';

    protected $fillable = [
        'company_id',
        'trackable_type',
        'trackable_id',
        'normalized_status',
        'assigned_to',
        'priority',
        'due_at',
        'next_action',
        'blocked_reason',
        'last_activity_at',
        'completed_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Extra people pulled in to look at this operation, alongside the single
     * `assigned_to` owner. The owner answers for it; collaborators are looped in.
     */
    public function collaborators()
    {
        return $this->belongsToMany(User::class, 'operation_tracker_assignees')
            ->withPivot(['note', 'assigned_by'])
            ->withTimestamps();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function histories()
    {
        return $this->hasMany(OperationTrackerHistory::class)->latest();
    }

    /** Just the conversation, newest first — drives the Notes column. */
    public function notes()
    {
        return $this->hasMany(OperationTrackerHistory::class)
            ->where('event_type', self::NOTE_EVENT)
            ->latest();
    }

    /**
     * Everyone who should hear about a note: the owner plus every collaborator.
     * Returns plain ids so callers can reject the actor without loading users.
     */
    public function audienceIds(): array
    {
        return collect([$this->assigned_to])
            ->merge($this->collaborators->pluck('id'))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
