<?php

namespace App\Models;

use App\Events\NotificationCreated as NotificationCreatedEvent;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    /**
     * Types the web UI surfaces — the bell dropdown, the notifications page and
     * the live bulletin all read from this list so they never disagree.
     */
    public const DISPLAY_TYPES = [
        'task_assigned',
        'task_created',
        'state_changed',
        'priority_changed',
        'due_date_changed',
        'comment_added',
        'attachment_uploaded',
        'project_status_changed',
        'office_todo_assigned',
        'office_todo_updated',
        'office_todo_status_changed',
        'notice_published',
        'notice_updated',
        'payslip_generated',
    ];

    protected $fillable = [
        'user_id',
        'type',
        'data',
        'read_at',
        'bulletin_dismissed_at'
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
        'bulletin_dismissed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    protected static function booted(): void
    {
        static::created(function (Notification $notification) {
            event(new NotificationCreatedEvent(
                $notification->user_id,
                $notification->data['message'] ?? '',
                $notification->type,
            ));
        });
    }

    /**
     * Get the user that owns the notification
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mark the notification as read
     */
    public function markAsRead()
    {
        if (is_null($this->read_at)) {
            $this->forceFill(['read_at' => now()])->save();
        }
    }

    /**
     * Mark the notification as unread
     */
    public function markAsUnread()
    {
        if (!is_null($this->read_at)) {
            $this->forceFill(['read_at' => null])->save();
        }
    }

    /**
     * Scope to get only unread notifications
     */
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    /**
     * Scope to get only read notifications
     */
    public function scopeRead($query)
    {
        return $query->whereNotNull('read_at');
    }

    /**
     * Drop the notification from the live bulletin without touching read_at, so
     * it stays waiting in the bell until the user actually reads it there.
     */
    public function dismissFromBulletin(): void
    {
        if (is_null($this->bulletin_dismissed_at)) {
            $this->forceFill(['bulletin_dismissed_at' => now()])->save();
        }
    }

    /**
     * Scope to the notifications the live bulletin still has to show: unread,
     * and not yet cleared from the ticker itself.
     */
    public function scopeOnBulletin($query)
    {
        return $query->whereNull('read_at')->whereNull('bulletin_dismissed_at');
    }

    /**
     * Get icon class based on notification type
     */
    public function getIconAttribute()
    {
        $icons = [
            'task_assigned' => 'fas fa-tasks text-blue-600',
            'task_updated' => 'fas fa-edit text-purple-600',
            'task_deleted' => 'fas fa-trash text-red-600',
            'comment_added' => 'fas fa-comment text-green-600',
            'assignee_added' => 'fas fa-user-plus text-blue-600',
            'assignee_removed' => 'fas fa-user-minus text-orange-600',
            'priority_changed' => 'fas fa-exclamation-circle text-yellow-600',
            'due_date_changed' => 'fas fa-calendar text-purple-600',
            'attachment_uploaded' => 'fas fa-paperclip text-teal-600',
            'label_added' => 'fas fa-tag text-indigo-600',
            'office_todo_assigned' => 'fas fa-clipboard-list text-blue-600',
            'office_todo_updated' => 'fas fa-pen-to-square text-indigo-600',
            'office_todo_status_changed' => 'fas fa-circle-check text-emerald-600',
            'notice_published' => 'fas fa-bullhorn text-orange-600',
            'notice_updated' => 'fas fa-bell text-amber-600',
            'leave_applied' => 'fas fa-calendar-check text-blue-600',
        ];

        return $icons[$this->type] ?? 'fas fa-bell text-gray-600';
    }

    /**
     * Get icon background color class based on notification type
     */
    public function getIconBgAttribute()
    {
        $backgrounds = [
            'task_assigned' => 'bg-blue-100',
            'task_updated' => 'bg-purple-100',
            'task_deleted' => 'bg-red-100',
            'comment_added' => 'bg-green-100',
            'assignee_added' => 'bg-blue-100',
            'assignee_removed' => 'bg-orange-100',
            'priority_changed' => 'bg-yellow-100',
            'due_date_changed' => 'bg-purple-100',
            'attachment_uploaded' => 'bg-teal-100',
            'label_added' => 'bg-indigo-100',
            'office_todo_assigned' => 'bg-blue-100',
            'office_todo_updated' => 'bg-indigo-100',
            'office_todo_status_changed' => 'bg-emerald-100',
            'notice_published' => 'bg-orange-100',
            'notice_updated' => 'bg-amber-100',
            'leave_applied' => 'bg-blue-100',
        ];

        return $backgrounds[$this->type] ?? 'bg-gray-100';
    }
}
