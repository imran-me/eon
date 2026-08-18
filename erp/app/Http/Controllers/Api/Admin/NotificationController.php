<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Get all notifications for the authenticated user
     */
    public function index()
    {
        $notifications = auth()->user()
            ->notifications()
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'message' => 'Notifications retrieved successfully.',
            'data' => $notifications->map(function ($notification) {
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

    /**
     * Get notification icon based on type
     */
    private function getNotificationIcon($notification)
    {
        $type = $notification->type;
        
        if (str_contains($type, 'TaskAssigned')) return 'fa-user-plus';
        if (str_contains($type, 'TaskUpdated')) return 'fa-edit';
        if (str_contains($type, 'TaskCompleted')) return 'fa-check-circle';
        if (str_contains($type, 'CommentAdded')) return 'fa-comment';
        if (str_contains($type, 'TaskMoved')) return 'fa-arrows-alt';
        if (str_contains($type, 'office_todo_assigned')) return 'fa-clipboard-list';
        if (str_contains($type, 'office_todo_updated')) return 'fa-pen-to-square';
        if (str_contains($type, 'office_todo_status_changed')) return 'fa-circle-check';
        if (str_contains($type, 'notice_published')) return 'fa-bullhorn';
        if (str_contains($type, 'notice_updated')) return 'fa-bell';
        
        return 'fa-bell';
    }

    /**
     * Get notification icon background color based on type
     */
    private function getNotificationIconBg($notification)
    {
        $type = $notification->type;
        
        if (str_contains($type, 'TaskAssigned')) return 'bg-blue-500';
        if (str_contains($type, 'TaskUpdated')) return 'bg-yellow-500';
        if (str_contains($type, 'TaskCompleted')) return 'bg-green-500';
        if (str_contains($type, 'CommentAdded')) return 'bg-purple-500';
        if (str_contains($type, 'TaskMoved')) return 'bg-indigo-500';
        if (str_contains($type, 'office_todo_assigned')) return 'bg-blue-500';
        if (str_contains($type, 'office_todo_updated')) return 'bg-indigo-500';
        if (str_contains($type, 'office_todo_status_changed')) return 'bg-emerald-500';
        if (str_contains($type, 'notice_published')) return 'bg-orange-500';
        if (str_contains($type, 'notice_updated')) return 'bg-amber-500';
        
        return 'bg-gray-500';
    }
}
