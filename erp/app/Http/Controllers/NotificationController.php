<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Get all notifications for the authenticated user
     */
    public function index(Request $request, $role)
    {
        $notifications = auth()->user()
            ->notifications()
            ->whereIn('type', Notification::DISPLAY_TYPES)
            ->latest()
            ->paginate(20);

        // If it's an AJAX request, return JSON
        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'notifications' => $notifications->map(function ($notification) {
                    return [
                        'id' => $notification->id,
                        'type' => $notification->type,
                        'data' => $notification->data,
                        'message' => $notification->data['message'] ?? '',
                        'task_id' => $notification->data['task_id'] ?? null,
                        'task_title' => $notification->data['task_title'] ?? null,
                        'board_id' => $notification->data['board_id'] ?? null,
                        'actor_name' => $notification->data['actor_name'] ?? 'Someone',
                        'is_read' => !is_null($notification->read_at),
                        'icon' => $this->getNotificationIcon($notification),
                        'icon_bg' => $this->getNotificationIconBg($notification),
                        'created_at' => $notification->created_at->diffForHumans(),
                    ];
                }),
                'total' => $notifications->total(),
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage()
            ]);
        }

        // Otherwise return view for web
        $title = 'Notifications';
        return view('notifications.index', compact('notifications', 'title'));
    }

    /**
     * Get notification icon based on type
     */
    private function getNotificationIcon($notification)
    {
        $type = $notification->type;

        if (str_contains($type, 'task_assigned')) return 'fa-user-plus';
        if (str_contains($type, 'task_created')) return 'fa-plus-circle';
        if (str_contains($type, 'state_changed')) return 'fa-arrows-alt';
        if (str_contains($type, 'due_date_changed')) return 'fa-calendar';
        if (str_contains($type, 'comment_added')) return 'fa-comment';
        if (str_contains($type, 'priority_changed')) return 'fa-exclamation-circle';
        if (str_contains($type, 'attachment_uploaded')) return 'fa-paperclip';
        if (str_contains($type, 'project_status_changed')) return 'fa-project-diagram';
        if (str_contains($type, 'office_todo_assigned')) return 'fa-clipboard-list';
        if (str_contains($type, 'office_todo_updated')) return 'fa-pen-to-square';
        if (str_contains($type, 'office_todo_status_changed')) return 'fa-circle-check';
        if (str_contains($type, 'notice_published')) return 'fa-bullhorn';
        if (str_contains($type, 'notice_updated')) return 'fa-bell';
        if (str_contains($type, 'payslip_generated')) return 'fa-file-invoice-dollar';

        return 'fa-bell';
    }

    /**
     * Get notification icon background color based on type
     */
    private function getNotificationIconBg($notification)
    {
        $type = $notification->type;

        if (str_contains($type, 'task_assigned')) return 'bg-blue-500';
        if (str_contains($type, 'task_created')) return 'bg-cyan-500';
        if (str_contains($type, 'state_changed')) return 'bg-indigo-500';
        if (str_contains($type, 'due_date_changed')) return 'bg-purple-500';
        if (str_contains($type, 'comment_added')) return 'bg-pink-500';
        if (str_contains($type, 'priority_changed')) return 'bg-yellow-500';
        if (str_contains($type, 'attachment_uploaded')) return 'bg-teal-500';
        if (str_contains($type, 'project_status_changed')) return 'bg-green-500';
        if (str_contains($type, 'office_todo_assigned')) return 'bg-blue-500';
        if (str_contains($type, 'office_todo_updated')) return 'bg-indigo-500';
        if (str_contains($type, 'office_todo_status_changed')) return 'bg-emerald-500';
        if (str_contains($type, 'notice_published')) return 'bg-orange-500';
        if (str_contains($type, 'notice_updated')) return 'bg-amber-500';
        if (str_contains($type, 'payslip_generated')) return 'bg-teal-600';

        return 'bg-gray-500';
    }

    /**
     * Get recent notifications for dropdown
     */
    public function recent($role)
    {
        try {
            $notifications = auth()->user()
                ->notifications()
                ->whereIn('type', Notification::DISPLAY_TYPES)
                ->latest()
                ->limit(10)
                ->get();
                
                return response()->json([
                    'success' => true,
                    'notifications' => $notifications->map(function ($notification) {
                        return [
                            'id' => $notification->id,
                            'type' => $notification->type,
                            'message' => $notification->data['message'] ?? '',
                            'task_id' => $notification->data['task_id'] ?? null,
                            'board_id' => $notification->data['board_id'] ?? null,
                            'is_read' => !is_null($notification->read_at),
                            'icon' => $this->getNotificationIcon($notification),
                            'icon_bg' => $this->getNotificationIconBg($notification),
                            'created_at' => $notification->created_at->diffForHumans(),
                            'created_at_raw' => $notification->created_at->toIso8601String(),
                        ];
                })
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch recent notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get unread notification count
     */
    public function unreadCount($role)
    {
        $count = auth()->user()
            ->notifications()
            ->whereIn('type', Notification::DISPLAY_TYPES)
            ->unread()
            ->count();

        return response()->json([
            'success' => true,
            'count' => $count
        ]);
    }

    /**
     * Mark a single notification as read
     */
    public function markAsRead($role, $id)
    {
        $notification = auth()->user()
            ->notifications()
            ->findOrFail($id);

        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read'
        ]);
    }

    /**
     * Clear a notification from the live bulletin. It deliberately leaves read_at
     * alone: the row goes from the ticker, but the bell still shows it unread.
     */
    public function dismissFromBulletin($role, $id)
    {
        $notification = auth()->user()
            ->notifications()
            ->findOrFail($id);

        $notification->dismissFromBulletin();

        return response()->json([
            'success' => true,
            'message' => 'Notification dismissed from the bulletin'
        ]);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead($role)
    {
        auth()->user()
            ->notifications()
            ->unread()
            ->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read'
        ]);
    }

    /**
     * Delete a notification
     */
    public function destroy($role, $id)
    {
        $notification = auth()->user()
            ->notifications()
            ->findOrFail($id);

        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted'
        ]);
    }

    /**
     * Delete multiple notifications
     */
    public function bulkDestroy(Request $request, $role)
    {
        $validated = $request->validate([
            'notification_ids' => 'required|array|min:1',
            'notification_ids.*' => 'required|integer',
        ]);

        $notificationIds = collect($validated['notification_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $deletedCount = auth()->user()
            ->notifications()
            ->whereIn('id', $notificationIds)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notifications deleted',
            'deleted_count' => $deletedCount,
        ]);
    }
}
