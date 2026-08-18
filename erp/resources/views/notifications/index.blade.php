@extends('layout.app')

@section('meta-information')
    <title>Notifications - {{ config('app.name') }}</title>
@endsection

@section('css')
<style>
    .notification-item {
        transition: all 0.2s ease;
    }
    .notification-item:hover {
        transform: translateX(5px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }
    .notification-unread {
        background-color: #EFF6FF;
        border-left: 4px solid #3B82F6;
    }
    .notification-read {
        background-color: #ffffff;
        border-left: 4px solid transparent;
    }
</style>
@endsection

@section('main-content')
@php
    $role = Str::slug(auth()->user()->getRoleNames()->first());
@endphp

<div class="h-full flex flex-col">
    <!-- Header -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">
                    <i class="fas fa-bell mr-2 text-blue-600"></i>Notifications
                </h1>
                <p class="text-sm text-gray-500 mt-1">{{ $notifications->total() }} total notifications</p>
            </div>
            
            <div class="flex gap-2">
                @if($notifications->count() > 0)
                <button id="bulkDeleteBtn" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition hidden">
                    <i class="fas fa-trash-alt mr-2"></i>Delete Selected
                </button>
                @endif
                @if($notifications->where('read_at', null)->count() > 0)
                <button id="markAllReadBtn" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                    <i class="fas fa-check-double mr-2"></i>Mark All as Read
                </button>
                @endif
            </div>
        </div>
    </div>

    <!-- Notifications List -->
    <div class="bg-white rounded-lg shadow-md flex-1 overflow-hidden flex flex-col">
        @if($notifications->count() > 0)
            <div class="flex-1 overflow-y-auto p-4">
                <div class="mb-3 flex items-center">
                    <label class="inline-flex items-center text-sm text-gray-700 cursor-pointer">
                        <input type="checkbox" id="selectAllNotifications" class="mr-2 rounded border-gray-300">
                        Select all on this page
                    </label>
                </div>
                <div class="space-y-3">
                    @foreach($notifications as $notification)
                    @php
                        $data = $notification->data;
                        $isRead = !is_null($notification->read_at);
                        $message = $data['message'] ?? 'New notification';
                        $actorName = $data['actor_name'] ?? 'Someone';
                        $taskId = $data['task_id'] ?? null;
                        $boardId = $data['board_id'] ?? null;
                        
                        // Determine icon and color
                        $type = $notification->type;
                        if (str_contains($type, 'TaskAssigned')) {
                            $icon = 'fa-user-plus';
                            $iconBg = 'bg-blue-500';
                        } elseif (str_contains($type, 'TaskUpdated')) {
                            $icon = 'fa-edit';
                            $iconBg = 'bg-yellow-500';
                        } elseif (str_contains($type, 'TaskCompleted')) {
                            $icon = 'fa-check-circle';
                            $iconBg = 'bg-green-500';
                        } elseif (str_contains($type, 'CommentAdded')) {
                            $icon = 'fa-comment';
                            $iconBg = 'bg-purple-500';
                        } elseif (str_contains($type, 'TaskMoved')) {
                            $icon = 'fa-arrows-alt';
                            $iconBg = 'bg-indigo-500';
                        } else {
                            $icon = 'fa-bell';
                            $iconBg = 'bg-gray-500';
                        }
                    @endphp
                    
                    <div class="notification-item {{ $isRead ? 'notification-read' : 'notification-unread' }} rounded-lg p-4 cursor-pointer flex items-start gap-4"
                         data-notification-id="{{ $notification->id }}"
                         data-board-id="{{ $boardId }}"
                         data-task-id="{{ $taskId }}">
                        <div class="pt-1">
                            <input type="checkbox"
                                   class="notification-select rounded border-gray-300"
                                   data-notification-id="{{ $notification->id }}"
                                   aria-label="Select notification {{ $notification->id }}">
                        </div>
                        
                        <!-- Icon -->
                        <div class="flex-shrink-0">
                            <div class="{{ $iconBg }} w-12 h-12 rounded-full flex items-center justify-center text-white">
                                <i class="fas {{ $icon }} text-lg"></i>
                            </div>
                        </div>
                        
                        <!-- Content -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex-1">
                                    <p class="text-sm text-gray-900 {{ !$isRead ? 'font-semibold' : '' }}">
                                        {{ $message }}
                                    </p>
                                    @if(isset($data['task_title']))
                                        <p class="text-xs text-gray-600 mt-1">
                                            <i class="fas fa-tasks mr-1"></i>{{ $data['task_title'] }}
                                        </p>
                                    @endif
                                </div>
                                
                                @if(!$isRead)
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        New
                                    </span>
                                @endif
                            </div>
                            
                            <div class="flex items-center justify-between mt-2">
                                <p class="text-xs text-gray-500">
                                    <i class="far fa-clock mr-1"></i>{{ $notification->created_at->diffForHumans() }}
                                </p>
                                
                                <div class="flex gap-2">
                                    @if(!$isRead)
                                        <button class="mark-read-btn text-xs text-blue-600 hover:text-blue-800"
                                                data-notification-id="{{ $notification->id }}">
                                            <i class="fas fa-check mr-1"></i>Mark as read
                                        </button>
                                    @endif
                                    <button class="delete-notification-btn text-xs text-red-600 hover:text-red-800"
                                            data-notification-id="{{ $notification->id }}">
                                        <i class="fas fa-trash mr-1"></i>Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            
            <!-- Pagination -->
            @if($notifications->hasPages())
            <div class="border-t border-gray-200 p-4">
                {{ $notifications->links() }}
            </div>
            @endif
        @else
            <!-- Empty State -->
            <div class="flex-1 flex items-center justify-center p-8">
                <div class="text-center">
                    <i class="fas fa-bell-slash fa-4x text-gray-400 mb-4"></i>
                    <h3 class="text-xl font-semibold text-gray-700 mb-2">No notifications yet</h3>
                    <p class="text-gray-500">You're all caught up! Check back later for new updates.</p>
                </div>
            </div>
        @endif
    </div>
</div>

@endsection

@section('import-script')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Built by Laravel, so these URLs survive the app living in a subdirectory.
    const base = @json(url($role));
    const selectedNotificationIds = new Set();
    const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
    const selectAllCheckbox = document.getElementById('selectAllNotifications');
    const notificationCheckboxes = Array.from(document.querySelectorAll('.notification-select'));

    function toggleBulkDeleteButton() {
        if (!bulkDeleteBtn) {
            return;
        }

        if (selectedNotificationIds.size > 0) {
            bulkDeleteBtn.classList.remove('hidden');
            bulkDeleteBtn.innerHTML = `<i class="fas fa-trash-alt mr-2"></i>Delete Selected (${selectedNotificationIds.size})`;
        } else {
            bulkDeleteBtn.classList.add('hidden');
            bulkDeleteBtn.innerHTML = '<i class="fas fa-trash-alt mr-2"></i>Delete Selected';
        }
    }

    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            notificationCheckboxes.forEach((checkbox) => {
                checkbox.checked = this.checked;
                const notificationId = Number(checkbox.dataset.notificationId);

                if (this.checked) {
                    selectedNotificationIds.add(notificationId);
                } else {
                    selectedNotificationIds.delete(notificationId);
                }
            });

            toggleBulkDeleteButton();
        });
    }

    notificationCheckboxes.forEach((checkbox) => {
        checkbox.addEventListener('click', function(e) {
            e.stopPropagation();
        });

        checkbox.addEventListener('change', function() {
            const notificationId = Number(this.dataset.notificationId);

            if (this.checked) {
                selectedNotificationIds.add(notificationId);
            } else {
                selectedNotificationIds.delete(notificationId);
            }

            if (selectAllCheckbox) {
                const allChecked = notificationCheckboxes.length > 0 && notificationCheckboxes.every((item) => item.checked);
                selectAllCheckbox.checked = allChecked;
            }

            toggleBulkDeleteButton();
        });
    });

    if (bulkDeleteBtn) {
        bulkDeleteBtn.addEventListener('click', function() {
            bulkDeleteNotifications(Array.from(selectedNotificationIds));
        });
    }
    
    // Mark single notification as read
    document.querySelectorAll('.mark-read-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const notificationId = this.dataset.notificationId;
            markAsRead(notificationId);
        });
    });
    
    // Mark all as read
    const markAllReadBtn = document.getElementById('markAllReadBtn');
    if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', function() {
            markAllAsRead();
        });
    }
    
    // Delete notification
    document.querySelectorAll('.delete-notification-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const notificationId = this.dataset.notificationId;
            deleteNotification(notificationId);
        });
    });
    
    // Click notification to navigate
    document.querySelectorAll('.notification-item').forEach(item => {
        item.addEventListener('click', function(e) {
            if (e.target.closest('button') || e.target.closest('input[type="checkbox"]')) {
                return;
            }

            const notificationId = this.dataset.notificationId;
            const boardId = this.dataset.boardId;
            const taskId = this.dataset.taskId;
            
            // Mark as read first
            if (!this.classList.contains('notification-read')) {
                markAsRead(notificationId, false);
            }
            
            // Navigate if there's a task link
            if (boardId && taskId) {
                window.location.href = `${base}/board/${boardId}?task=${taskId}`;
            }
        });
    });
    
    function markAsRead(notificationId, reload = true) {
        fetch(`${base}/notifications/${notificationId}/mark-as-read`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && reload) {
                window.location.reload();
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
    }
    
    function markAllAsRead() {
        Swal.fire({
            title: 'Mark all as read?',
            text: 'This will mark all unread notifications as read.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3B82F6',
            cancelButtonColor: '#6B7280',
            confirmButtonText: 'Yes, mark all'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch(`${base}/notifications/mark-all-read`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: 'All notifications marked as read',
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => {
                            window.location.reload();
                        });
                    }
                })
                .catch(error => {
                    Swal.fire('Error!', 'Failed to mark notifications as read', 'error');
                });
            }
        });
    }
    
    function deleteNotification(notificationId) {
        Swal.fire({
            title: 'Delete notification?',
            text: 'This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#EF4444',
            cancelButtonColor: '#6B7280',
            confirmButtonText: 'Yes, delete it'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch(`${base}/notifications/${notificationId}`, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Deleted!',
                            text: 'Notification has been deleted',
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => {
                            window.location.reload();
                        });
                    }
                })
                .catch(error => {
                    Swal.fire('Error!', 'Failed to delete notification', 'error');
                });
            }
        });
    }

    function bulkDeleteNotifications(notificationIds) {
        if (!Array.isArray(notificationIds) || notificationIds.length === 0) {
            return;
        }

        Swal.fire({
            title: 'Delete selected notifications?',
            text: `You are about to delete ${notificationIds.length} notifications. This action cannot be undone.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#EF4444',
            cancelButtonColor: '#6B7280',
            confirmButtonText: 'Yes, delete selected'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch(`${base}/notifications/bulk-delete`, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ notification_ids: notificationIds })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Deleted!',
                            text: `${data.deleted_count ?? notificationIds.length} notifications deleted`,
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => {
                            window.location.reload();
                        });
                    } else {
                        Swal.fire('Error!', data.message || 'Failed to delete selected notifications', 'error');
                    }
                })
                .catch(() => {
                    Swal.fire('Error!', 'Failed to delete selected notifications', 'error');
                });
            }
        });
    }
});
</script>
@endsection