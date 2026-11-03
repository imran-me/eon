<?php

namespace App\Services;

use App\Models\Chat;
use App\Models\DeviceToken;
use App\Models\EmployeeSalary;
use App\Models\EmployeePayment;
use App\Models\Leave;
use App\Models\ManualAttendanceRequest;
use App\Models\Notice;
use App\Models\Notification;
use App\Models\OfficeTodo;
use App\Models\Payslip;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class NotificationService
{
    /** In-app bell plus device push for one committed employee payment. */
    public static function notifyEmployeePayment(EmployeePayment $payment): void
    {
        $employee = $payment->employee;
        if (! $employee) {
            return;
        }

        $message = 'Salary payment of ৳'.number_format((float) $payment->amount, 2)
            .' received on '.$payment->payment_date->format('d M Y')
            .' at '.Carbon::parse((string) $payment->transaction_time)->format('h:i A')
            .'. Reference: '.$payment->reference.'.';

        $created = self::storeNotifications([$employee->id], 'employee_payment', [
            'employee_payment_id' => $payment->id,
            'amount' => (float) $payment->amount,
            'reference' => $payment->reference,
            'payment_date' => $payment->payment_date->format('Y-m-d'),
            'transaction_time' => substr((string) $payment->transaction_time, 0, 5),
            'message' => $message,
        ]);

        foreach ($created as $userId => $notification) {
            self::sendPushToUser($userId, 'Salary Payment Received', $message, [
                'id' => (string) $notification->id,
                'type' => 'employee_payment',
                'title' => 'Salary Payment Received',
                'message' => $message,
                'entity_type' => 'employee_payment',
                'entity_id' => (string) $payment->id,
                'is_read' => '0',
                'created_at' => optional($notification->created_at)->toDateTimeString(),
            ], 'chat_notification.aiff');
        }
    }

    public static function notifyManualAttendanceSubmitted(ManualAttendanceRequest $request): void
    {
        $request->loadMissing('user:id,name');
        $employeeName = $request->user?->name ?? 'An employee';
        $action = $request->action === 'check_out' ? 'check-out' : 'check-in';
        $date = $request->requested_date?->format('d M Y');
        $time = Carbon::parse($request->requested_time)->format('h:i A');
        $receivedAt = $request->created_at?->format('d M Y, h:i A');
        $message = "{$employeeName} requested manual {$action} for {$date} at {$time}. Server received: {$receivedAt}.";

        $superAdminIds = Schema::hasTable('manual_attendance_notification_recipients')
            ? $request->user->manualAttendanceNotificationRecipients()
                ->where('users.status', 'active')
                ->pluck('users.id')
                ->all()
            : [];

        if (empty($superAdminIds)) {
            $superAdminIds = User::query()
                ->where('status', 'active')
                ->where(function ($query) {
                    $query->where('is_super_admin', true)
                        ->orWhereHas('roles', fn ($role) => $role->where('name', 'super admin'));
                })
                ->pluck('id')
                ->all();
        }

        $created = self::storeNotifications($superAdminIds, 'manual_attendance_submitted', [
            'manual_attendance_request_id' => $request->id,
            'employee_id' => $request->user_id,
            'employee_name' => $employeeName,
            'action' => $request->action,
            'message' => $message,
            'url' => '/super-admin/dashboard#manual-attendance-requests',
        ]);

        foreach ($created as $userId => $notification) {
            self::sendPushToUser($userId, 'Manual Attendance Approval', $message, [
                'id' => (string) $notification->id,
                'type' => 'manual_attendance_submitted',
                'entity_type' => 'manual_attendance_request',
                'entity_id' => (string) $request->id,
                'title' => 'Manual Attendance Approval',
                'message' => $message,
            ], 'chat_notification.aiff');
        }
    }

    public static function notifyManualAttendanceDecision(ManualAttendanceRequest $request): void
    {
        $action = $request->action === 'check_out' ? 'check-out' : 'check-in';
        $status = ucfirst($request->status);
        $message = "Your manual {$action} request has been {$request->status} by Super Admin.";
        $created = self::storeNotifications([$request->user_id], 'manual_attendance_decision', [
            'manual_attendance_request_id' => $request->id,
            'status' => $request->status,
            'review_note' => $request->review_note,
            'message' => $message,
        ]);

        foreach ($created as $userId => $notification) {
            self::sendPushToUser($userId, "Manual Attendance {$status}", $message, [
                'id' => (string) $notification->id,
                'type' => 'manual_attendance_decision',
                'entity_type' => 'manual_attendance_request',
                'entity_id' => (string) $request->id,
                'status' => $request->status,
                'title' => "Manual Attendance {$status}",
                'message' => $message,
            ], 'chat_notification.aiff');
        }
    }

    public static function notifyPayslipGenerated(EmployeeSalary $empSalary, ?Payslip $payslip = null): void
    {
        $monthLabel = Carbon::createFromDate((int) $empSalary->year, (int) $empSalary->month, 1)->format('F Y');
        $message = "Your payslip for {$monthLabel} has been generated. Net payable: ৳".number_format($empSalary->net_salary, 2);

        $created = self::storeNotifications([$empSalary->user_id], 'payslip_generated', [
            'employee_salary_id' => $empSalary->id,
            'payslip_id' => $payslip?->id,
            'month' => $empSalary->month,
            'year' => $empSalary->year,
            'net_salary' => $empSalary->net_salary,
            'message' => $message,
        ]);

        foreach ($created as $userId => $notif) {
            self::sendPushToUser($userId, 'Payslip Generated', $message, [
                'id' => (string) $notif->id,
                'type' => 'payslip_generated',
                'title' => 'Payslip Generated',
                'message' => $message,
                'entity_type' => 'employee_salary',
                'entity_id' => (string) $empSalary->id,
                'is_read' => '0',
                'created_at' => optional($notif->created_at)->toDateTimeString(),
            ], 'chat_notification.aiff');
        }
    }

    public static function notifySalaryPaid(EmployeeSalary $empSalary, float $paidAmount, bool $isPartial, float $remainingDue): void
    {
        $monthLabel = Carbon::createFromDate((int) $empSalary->year, (int) $empSalary->month, 1)->format('F Y');
        $message = $isPartial
            ? 'A partial payment of ৳'.number_format($paidAmount, 2)." has been made against your {$monthLabel} salary. Remaining due: ৳".number_format($remainingDue, 2)
            : "Your {$monthLabel} salary of ৳".number_format($paidAmount, 2).' has been paid.';

        $created = self::storeNotifications([$empSalary->user_id], 'salary_paid', [
            'employee_salary_id' => $empSalary->id,
            'month' => $empSalary->month,
            'year' => $empSalary->year,
            'paid_amount' => $paidAmount,
            'is_partial' => $isPartial,
            'remaining_due' => $remainingDue,
            'message' => $message,
        ]);

        foreach ($created as $userId => $notif) {
            $title = $isPartial ? 'Partial Salary Payment' : 'Salary Paid';
            self::sendPushToUser($userId, $title, $message, [
                'id' => (string) $notif->id,
                'type' => 'salary_paid',
                'title' => $title,
                'message' => $message,
                'entity_type' => 'employee_salary',
                'entity_id' => (string) $empSalary->id,
                'is_read' => '0',
                'created_at' => optional($notif->created_at)->toDateTimeString(),
            ], 'chat_notification.aiff');
        }
    }

    public static function notifyChatMessage(Chat $chat): void
    {
        $sender = $chat->sender;
        $receiverId = (int) $chat->receiver_id;

        $messageText = trim((string) $chat->message);
        if ($messageText === '' && $chat->file_path) {
            $messageText = 'Sent an attachment';
        }

        Notification::create([
            'user_id' => $receiverId,
            'type' => 'chat_message',
            'data' => [
                'chat_id' => $chat->id,
                'sender_id' => $chat->sender_id,
                'receiver_id' => $chat->receiver_id,
                'message' => $sender?->name
                    ? ($sender->name.': '.$messageText)
                    : $messageText,
            ],
        ]);

        self::sendPushToUser(
            $receiverId,
            $sender?->name ?? 'New message',
            $messageText,
            [
                'type' => 'chat.message',
                'chat_id' => (string) $chat->id,
                'sender_id' => (string) $chat->sender_id,
                'receiver_id' => (string) $chat->receiver_id,
            ],
            'chat_notification.aiff'
        );
    }

    /**
     * Operations Status Tracker — somebody was pulled in to look at a record.
     *
     * In-app bell + live toast only (the NotificationCreated event fires from
     * Notification::booted). Deliberately no FCM push: this is desk work, and
     * the tracker already digests everything on one screen.
     *
     * $url is passed in rather than built here because only the caller knows
     * the role segment the current panel is mounted under.
     */
    public static function notifyOperationAssigned(array $userIds, string $reference, string $url, ?string $note = null): array
    {
        $actorName = optional(Auth::user())->name ?? 'Someone';
        $message = $actorName.' asked you to look into '.$reference
            .($note ? ' — '.Str::limit($note, 140) : '');

        return self::storeNotifications(self::withoutActor($userIds), 'operation_assigned', [
            'reference' => $reference,
            'actor_name' => $actorName,
            'note' => $note,
            'url' => $url,
            'message' => $message,
        ]);
    }

    /** Operations Status Tracker — a note was posted; everyone on the record hears it. */
    public static function notifyOperationNote(array $userIds, string $reference, string $url, string $body): array
    {
        $actorName = optional(Auth::user())->name ?? 'Someone';
        $message = $actorName.' noted on '.$reference.': '.Str::limit($body, 160);

        return self::storeNotifications(self::withoutActor($userIds), 'operation_note', [
            'reference' => $reference,
            'actor_name' => $actorName,
            'note' => $body,
            'url' => $url,
            'message' => $message,
        ]);
    }

    /** Nobody needs a notification about their own action. */
    private static function withoutActor(array $userIds): array
    {
        $actorId = Auth::id();

        return collect($userIds)
            ->filter()
            ->map(fn ($userId) => (int) $userId)
            ->unique()
            ->when($actorId, fn ($collection) => $collection->reject(fn ($userId) => $userId === (int) $actorId))
            ->values()
            ->all();
    }

    private static function sendPushToUser(int $userId, string $title, string $body, array $data = [], ?string $sound = null): void
    {
        $projectId = config('services.fcm.project_id');
        $accessToken = self::getFcmAccessToken();
        if (! $projectId || ! $accessToken) {
            return;
        }

        $tokens = DeviceToken::query()
            ->where('user_id', $userId)
            ->pluck('token')
            ->filter()
            ->values()
            ->all();

        if (empty($tokens)) {
            return;
        }

        $notification = array_filter([
            'title' => $title,
            'body' => $body,
        ]);

        $androidNotification = [
            'channel_id' => 'chat_channel',
        ];

        if ($sound) {
            $androidNotification['sound'] = pathinfo($sound, PATHINFO_FILENAME) ?: $sound;
        }

        $aps = [];

        if ($sound) {
            $aps['sound'] = $sound;
        }

        // json_encode(array) emits `[]` for an empty array, but APNs requires
        // `aps` to be a JSON dictionary even when it has no sound to carry.
        // Casting to object guarantees `{}` instead.
        $apnsPayload = [
            'aps' => (object) $aps,
        ];

        foreach ($tokens as $token) {
            $payload = [
                'message' => [
                    'token' => $token,
                    'notification' => $notification,
                    'data' => $data,
                    'android' => [
                        'priority' => 'high',
                        'notification' => $androidNotification,
                    ],
                    'apns' => [
                        'headers' => [
                            'apns-priority' => '10',
                        ],
                        'payload' => $apnsPayload,
                    ],
                ],
            ];

            $response = Http::withToken($accessToken)
                ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", $payload);

            Log::info('FCM response', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

        }
    }

    /**
     * Store notifications for a list of users and return created Notification models keyed by user id.
     *
     * @return Notification[] keyed by user id
     */
    private static function storeNotifications(array $userIds, string $type, array $data): array
    {
        $created = [];

        collect($userIds)
            ->filter()
            ->map(fn ($userId) => (int) $userId)
            ->unique()
            ->values()
            ->each(function (int $userId) use ($type, $data, &$created): void {
                $created[$userId] = Notification::create([
                    'user_id' => $userId,
                    'type' => $type,
                    'data' => $data,
                ]);
            });

        return $created;
    }

    private static function getFcmAccessToken(): ?string
    {
        $serviceAccount = config('services.fcm.service_account_json');
        if (! $serviceAccount) {
            return null;
        }

        $json = null;
        if (is_string($serviceAccount)) {
            $candidatePaths = [
                storage_path('app/'.ltrim($serviceAccount, '\\\/')),
            ];

            foreach (array_unique($candidatePaths) as $candidatePath) {
                if ($candidatePath && file_exists($candidatePath)) {
                    $json = json_decode((string) file_get_contents($candidatePath), true);
                    break;
                }
            }

            if (! is_array($json)) {
                $json = json_decode($serviceAccount, true);
            }
        }

        if (! is_array($json)) {
            return null;
        }

        $scopes = ['https://www.googleapis.com/auth/firebase.messaging'];
        $credentials = new ServiceAccountCredentials($scopes, $json);
        $token = $credentials->fetchAuthToken();

        return $token['access_token'] ?? null;
    }

    public static function notifyTaskAssigned($task, $assignedUserIds)
    {
        $actorId = Auth::id();
        $actorName = optional(Auth::user())->name ?? 'Someone';

        $usersToNotify = collect($assignedUserIds)
            ->filter()
            ->map(fn ($userId) => (int) $userId)
            ->unique()
            ->when($actorId, fn ($collection) => $collection->reject(fn ($userId) => $userId === (int) $actorId))
            ->values()
            ->all();

        $created = self::storeNotifications($usersToNotify, 'task_assigned', [
            'task_id' => $task->id,
            'task_title' => $task->title,
            'actor_name' => $actorName,
            'board_id' => $task->board_id,
            'message' => $actorName.' assigned you to task: '.$task->title,
        ]);

        foreach ($created as $userId => $notif) {
            $payload = [
                'id' => (string) $notif->id,
                'type' => 'task_assigned',
                'title' => 'Task Assigned',
                'message' => $notif->data['message'] ?? ($actorName.' assigned you to task: '.$task->title),
                'entity_type' => 'task',
                'entity_id' => (string) $task->id,
                'is_read' => '0',
                'created_at' => optional($notif->created_at)->toDateTimeString(),
            ];

            // Use same notification sound as chat messages
            self::sendPushToUser($userId, $payload['title'], $payload['message'], $payload, 'chat_notification.aiff');
        }
    }

    /**
     * Notify assigned users when someone comments on a task
     */
    public static function notifyCommentAdded(Task $task, $comment)
    {
        $actorName = Auth::user()->name;
        $actorId = Auth::id();

        // Get all assigned users
        $usersToNotify = $task->users->pluck('id')->toArray();

        // Also notify task creator if not already in the list
        if ($task->created_by && ! in_array($task->created_by, $usersToNotify)) {
            $usersToNotify[] = $task->created_by;
        }

        // Remove the current user
        $usersToNotify = array_diff($usersToNotify, [$actorId]);

        $message = $actorName.' commented on: '.$task->title;

        $created = self::storeNotifications($usersToNotify, 'comment_added', [
            'task_id' => $task->id,
            'task_title' => $task->title,
            'board_id' => $task->board_id,
            'actor_id' => $actorId,
            'actor_name' => $actorName,
            'comment' => substr($comment, 0, 100),
            'message' => $message,
        ]);

        foreach ($created as $userId => $notif) {
            $payload = [
                'id' => (string) $notif->id,
                'type' => 'comment_added',
                'title' => 'New Comment',
                'message' => $notif->data['message'] ?? $message,
                'entity_type' => 'task',
                'entity_id' => (string) $task->id,
                'is_read' => '0',
                'created_at' => optional($notif->created_at)->toDateTimeString(),
            ];

            self::sendPushToUser($userId, $payload['title'], $payload['message'], $payload, 'chat_notification.aiff');
        }
    }

    /**
     * Notify when priority changes (only on increase)
     */
    public static function notifyPriorityChanged($task, $oldPriority, $newPriority)
    {
        $priorityLevels = ['low' => 1, 'medium' => 2, 'high' => 3];

        $oldLevel = $priorityLevels[$oldPriority] ?? 0;
        $newLevel = $priorityLevels[$newPriority] ?? 0;

        // Only notify if priority INCREASED
        if ($newLevel <= $oldLevel) {
            return; // Skip notification for priority decrease or no change
        }

        $actorName = Auth::user()->name;
        $actorId = Auth::id();

        $usersToNotify = self::getTaskParticipants($task, $actorId);
        $priorityText = $newPriority ? ucfirst($newPriority) : 'None';

        self::storeNotifications($usersToNotify, 'priority_changed', [
            'task_id' => $task->id,
            'task_title' => $task->title,
            'board_id' => $task->board_id,
            'actor_id' => $actorId,
            'actor_name' => $actorName,
            'new_priority' => $priorityText,
            'old_priority' => $oldPriority ? ucfirst($oldPriority) : 'None',
            'message' => $actorName.' increased priority to '.$priorityText.' on: '.$task->title,
        ]);
    }

    /**
     * Notify when attachment is uploaded
     */
    public static function notifyAttachmentUploaded($task, $fileName)
    {
        $actorName = Auth::user()->name;
        $actorId = Auth::id();

        $usersToNotify = self::getTaskParticipants($task, $actorId);

        self::storeNotifications($usersToNotify, 'attachment_uploaded', [
            'task_id' => $task->id,
            'task_title' => $task->title,
            'board_id' => $task->board_id,
            'actor_id' => $actorId,
            'actor_name' => $actorName,
            'file_name' => $fileName,
            'message' => $actorName.' uploaded an attachment to: '.$task->title,
        ]);
    }

    /**
     * Notify when a new task is created with assignees
     */
    public static function notifyTaskCreated($task, $assignedUserIds)
    {
        if (empty($assignedUserIds)) {
            return; // No one to notify
        }

        $actorName = Auth::user()->name;
        $actorId = Auth::id();

        // Remove the creator from notifications
        $usersToNotify = array_diff($assignedUserIds, [$actorId]);

        $message = $actorName.' created a new task and assigned you: '.$task->title;

        $created = self::storeNotifications($usersToNotify, 'task_created', [
            'task_id' => $task->id,
            'task_title' => $task->title,
            'board_id' => $task->board_id,
            'actor_id' => $actorId,
            'actor_name' => $actorName,
            'message' => $message,
        ]);

        foreach ($created as $userId => $notif) {
            $payload = [
                'id' => (string) $notif->id,
                'type' => 'task_created',
                'title' => 'New Task Assigned',
                'message' => $notif->data['message'] ?? $message,
                'entity_type' => 'task',
                'entity_id' => (string) $task->id,
                'is_read' => '0',
                'created_at' => optional($notif->created_at)->toDateTimeString(),
            ];

            self::sendPushToUser($userId, $payload['title'], $payload['message'], $payload, 'chat_notification.aiff');
        }
    }

    /**
     * Notify when due date changes (only if becoming sooner or removed)
     */
    public static function notifyDueDateChanged($task, $oldDate, $newDate)
    {
        // Don't notify if due date is pushed back to a later date
        if ($newDate && $oldDate && $newDate > $oldDate) {
            return; // Skip - less urgent now
        }

        $actorName = Auth::user()->name;
        $actorId = Auth::id();

        $usersToNotify = self::getTaskParticipants($task, $actorId);

        if (empty($usersToNotify)) {
            return;
        }

        $messageAction = $newDate
            ? 'changed the due date to '.Carbon::parse($newDate)->format('M d, Y, g:i A')
            : 'removed the due date';

        $created = self::storeNotifications($usersToNotify, 'due_date_changed', [
            'task_id' => $task->id,
            'task_title' => $task->title,
            'board_id' => $task->board_id,
            'actor_id' => $actorId,
            'actor_name' => $actorName,
            'new_due_date' => $newDate,
            'old_due_date' => $oldDate,
            'message' => $actorName.' '.$messageAction.' on: '.$task->title,
        ]);

        foreach ($created as $userId => $notif) {
            $payload = [
                'id' => (string) $notif->id,
                'type' => 'due_date_changed',
                'title' => 'Due Date Changed',
                'message' => $notif->data['message'] ?? ($actorName.' '.$messageAction.' on: '.$task->title),
                'entity_type' => 'task',
                'entity_id' => (string) $task->id,
                'is_read' => '0',
                'created_at' => optional($notif->created_at)->toDateTimeString(),
            ];

            self::sendPushToUser($userId, $payload['title'], $payload['message'], $payload, 'chat_notification.aiff');
        }
    }

    /**
     * Notify when task state/column changes
     */
    public static function notifyStateChanged($task, $oldColumnName, $newColumnName)
    {
        $actorName = Auth::user()->name;
        $actorId = Auth::id();

        $usersToNotify = self::getTaskParticipants($task, $actorId);

        if (empty($usersToNotify)) {
            return;
        }

        $created = self::storeNotifications($usersToNotify, 'state_changed', [
            'task_id' => $task->id,
            'task_title' => $task->title,
            'board_id' => $task->board_id,
            'actor_id' => $actorId,
            'actor_name' => $actorName,
            'new_state' => $newColumnName,
            'old_state' => $oldColumnName,
            'message' => $actorName.' moved "'.$task->title.'" from '.$oldColumnName.' to '.$newColumnName,
        ]);

        foreach ($created as $userId => $notif) {
            $payload = [
                'id' => (string) $notif->id,
                'type' => 'state_changed',
                'title' => 'Task Updated',
                'message' => $notif->data['message'] ?? ($actorName.' moved "'.$task->title.'" from '.$oldColumnName.' to '.$newColumnName),
                'entity_type' => 'task',
                'entity_id' => (string) $task->id,
                'is_read' => '0',
                'created_at' => optional($notif->created_at)->toDateTimeString(),
            ];

            self::sendPushToUser($userId, $payload['title'], $payload['message'], $payload, 'chat_notification.aiff');
        }
    }

    /**
     * Notify project team members when project status changes.
     */
    public static function notifyProjectStatusChanged(Project $project, string $oldStatus, string $newStatus): void
    {
        $actorId = Auth::id();
        $actorName = optional(Auth::user())->name ?? 'Someone';

        $teamMembers = collect($project->team_members ?? [])
            ->filter()
            ->map(fn ($userId) => (int) $userId)
            ->unique()
            ->when($actorId, fn ($collection) => $collection->reject(fn ($userId) => $userId === (int) $actorId))
            ->values()
            ->all();

        if (empty($teamMembers)) {
            return;
        }

        $message = sprintf(
            '%s changed project status from %s to %s: %s',
            $actorName,
            str_replace('_', ' ', $oldStatus),
            str_replace('_', ' ', $newStatus),
            $project->project_name
        );

        $created = self::storeNotifications($teamMembers, 'project_status_changed', [
            'project_id' => $project->id,
            'project_name' => $project->project_name,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'actor_name' => $actorName,
            'message' => $message,
        ]);

        foreach ($created as $userId => $notif) {
            $payload = [
                'id' => (string) $notif->id,
                'type' => 'project_status_changed',
                'title' => 'Project Status Changed',
                'message' => $notif->data['message'] ?? $message,
                'entity_type' => 'project',
                'entity_id' => (string) $project->id,
                'is_read' => '0',
                'created_at' => optional($notif->created_at)->toDateTimeString(),
            ];

            self::sendPushToUser($userId, $payload['title'], $payload['message'], $payload, 'chat_notification.aiff');
        }
    }

    public static function notifyOfficeTodoAssigned(OfficeTodo $todo, array $assignedUserIds): void
    {
        $actorId = Auth::id();
        $actorName = optional(Auth::user())->name ?? 'Someone';

        $usersToNotify = collect($assignedUserIds)
            ->filter()
            ->map(fn ($userId) => (int) $userId)
            ->unique()
            ->when($actorId, fn ($collection) => $collection->reject(fn ($userId) => $userId === (int) $actorId))
            ->values()
            ->all();

        if (empty($usersToNotify)) {
            return;
        }

        $message = $actorName.' assigned you an office todo: '.$todo->title;

        $created = self::storeNotifications($usersToNotify, 'office_todo_assigned', [
            'todo_id' => $todo->id,
            'todo_title' => $todo->title,
            'company_id' => $todo->company_id,
            'department_id' => $todo->department_id,
            'actor_id' => $actorId,
            'actor_name' => $actorName,
            'message' => $message,
        ]);

        foreach ($created as $userId => $notif) {
            $payload = [
                'id' => (string) $notif->id,
                'type' => 'office_todo_assigned',
                'title' => 'Office Todo Assigned',
                'message' => $notif->data['message'] ?? $message,
                'entity_type' => 'office_todo',
                'entity_id' => (string) $todo->id,
                'is_read' => '0',
                'created_at' => optional($notif->created_at)->toDateTimeString(),
            ];

            // Use chat sound for consistency
            self::sendPushToUser($userId, $payload['title'], $payload['message'], $payload, 'chat_notification.aiff');
        }
    }

    public static function notifyOfficeTodoUpdated(OfficeTodo $todo, array $recipientUserIds): void
    {
        $actorId = Auth::id();
        $actorName = optional(Auth::user())->name ?? 'Someone';

        $usersToNotify = collect($recipientUserIds)
            ->filter()
            ->map(fn ($userId) => (int) $userId)
            ->unique()
            ->when($actorId, fn ($collection) => $collection->reject(fn ($userId) => $userId === (int) $actorId))
            ->values()
            ->all();

        if (empty($usersToNotify)) {
            return;
        }

        $message = $actorName.' updated office todo: '.$todo->title;

        $created = self::storeNotifications($usersToNotify, 'office_todo_updated', [
            'todo_id' => $todo->id,
            'todo_title' => $todo->title,
            'company_id' => $todo->company_id,
            'department_id' => $todo->department_id,
            'actor_id' => $actorId,
            'actor_name' => $actorName,
            'message' => $message,
        ]);

        foreach ($created as $userId => $notif) {
            $payload = [
                'id' => (string) $notif->id,
                'type' => 'office_todo_updated',
                'title' => 'Office Todo Updated',
                'message' => $notif->data['message'] ?? $message,
                'entity_type' => 'office_todo',
                'entity_id' => (string) $todo->id,
                'is_read' => '0',
                'created_at' => optional($notif->created_at)->toDateTimeString(),
            ];

            self::sendPushToUser($userId, $payload['title'], $payload['message'], $payload, 'chat_notification.aiff');
        }
    }

    public static function notifyOfficeTodoStatusChanged(OfficeTodo $todo, array $recipientUserIds, string $oldStatus, string $newStatus): void
    {
        $actorId = Auth::id();
        $actorName = optional(Auth::user())->name ?? 'Someone';

        $usersToNotify = collect($recipientUserIds)
            ->filter()
            ->map(fn ($userId) => (int) $userId)
            ->unique()
            ->when($actorId, fn ($collection) => $collection->reject(fn ($userId) => $userId === (int) $actorId))
            ->values()
            ->all();

        if (empty($usersToNotify)) {
            return;
        }

        $message = sprintf(
            '%s changed office todo status from %s to %s: %s',
            $actorName,
            str_replace('_', ' ', $oldStatus),
            str_replace('_', ' ', $newStatus),
            $todo->title
        );

        $created = self::storeNotifications($usersToNotify, 'office_todo_status_changed', [
            'todo_id' => $todo->id,
            'todo_title' => $todo->title,
            'company_id' => $todo->company_id,
            'department_id' => $todo->department_id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'actor_id' => $actorId,
            'actor_name' => $actorName,
            'message' => $message,
        ]);

        foreach ($created as $userId => $notif) {
            $payload = [
                'id' => (string) $notif->id,
                'type' => 'office_todo_status_changed',
                'title' => 'Office Todo Status Changed',
                'message' => $notif->data['message'] ?? $message,
                'entity_type' => 'office_todo',
                'entity_id' => (string) $todo->id,
                'is_read' => '0',
                'created_at' => optional($notif->created_at)->toDateTimeString(),
            ];

            self::sendPushToUser($userId, $payload['title'], $payload['message'], $payload, 'chat_notification.aiff');
        }
    }

    public static function notifyNoticePublished(Notice $notice, ?string $action = 'published'): void
    {
        $actorId = Auth::id();
        $actorName = optional(Auth::user())->name ?? 'Someone';

        $companyId = $notice->company_id ?? Auth::user()?->company_id;
        $departmentId = $notice->department_id;

        $recipientsQuery = User::query()
            ->where('status', 'active')
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->when($departmentId, fn ($query) => $query->whereHas('profile', fn ($profileQuery) => $profileQuery->where('department_id', $departmentId)));

        $recipientUserIds = $recipientsQuery
            ->when($actorId, fn ($query) => $query->where('id', '!=', $actorId))
            ->pluck('id')
            ->all();

        if (empty($recipientUserIds)) {
            return;
        }

        $message = sprintf(
            '%s %s notice: %s',
            $actorName,
            $action === 'updated' ? 'updated' : 'published',
            $notice->title
        );

        $created = self::storeNotifications($recipientUserIds, $action === 'updated' ? 'notice_updated' : 'notice_published', [
            'notice_id' => $notice->id,
            'notice_title' => $notice->title,
            'company_id' => $notice->company_id,
            'department_id' => $notice->department_id,
            'publish_date' => filled($notice->publish_date) ? Carbon::parse($notice->publish_date)->toDateTimeString() : null,
            'expiry_date' => filled($notice->expiry_date) ? Carbon::parse($notice->expiry_date)->toDateTimeString() : null,
            'actor_id' => $actorId,
            'actor_name' => $actorName,
            'message' => $message,
        ]);

        foreach ($created as $userId => $notif) {
            $payload = [
                'id' => (string) $notif->id,
                'type' => $action === 'updated' ? 'notice_updated' : 'notice_published',
                'title' => $action === 'updated' ? 'Notice Updated' : 'Notice Published',
                'message' => $notif->data['message'] ?? $message,
                'entity_type' => 'notice',
                'entity_id' => (string) $notice->id,
                'is_read' => '0',
                'created_at' => optional($notif->created_at)->toDateTimeString(),
            ];

            self::sendPushToUser($userId, $payload['title'], $payload['message'], $payload, 'chat_notification.aiff');
        }
    }

    /**
     * Notify super admin / operation users when an employee applies for leave.
     */
    public static function notifyLeaveApplied(Leave $leave): void
    {
        $actorId = Auth::id();
        $applicantName = optional($leave->user)->name ?? 'An employee';

        $recipientUserIds = User::query()
            ->where('status', 'active')
            ->role(['operation', 'super admin'])
            ->when($actorId, fn ($query) => $query->where('id', '!=', $actorId))
            ->pluck('id')
            ->all();

        if (empty($recipientUserIds)) {
            return;
        }

        $leaveTypeName = optional($leave->leave_type)->name ?? 'Leave';
        $message = sprintf(
            '%s applied for %s (%s to %s)',
            $applicantName,
            $leaveTypeName,
            Carbon::parse($leave->start_date)->format('M d, Y'),
            Carbon::parse($leave->end_date)->format('M d, Y')
        );

        $created = self::storeNotifications($recipientUserIds, 'leave_applied', [
            'leave_id' => $leave->id,
            'user_id' => $leave->user_id,
            'applicant_name' => $applicantName,
            'leave_type' => $leaveTypeName,
            'start_date' => $leave->start_date,
            'end_date' => $leave->end_date,
            'message' => $message,
        ]);

        foreach ($created as $userId => $notif) {
            $payload = [
                'id' => (string) $notif->id,
                'type' => 'leave_applied',
                'title' => 'New Leave Application',
                'message' => $notif->data['message'] ?? $message,
                'entity_type' => 'leave',
                'entity_id' => (string) $leave->id,
                'is_read' => '0',
                'created_at' => optional($notif->created_at)->toDateTimeString(),
            ];

            self::sendPushToUser($userId, $payload['title'], $payload['message'], $payload, 'chat_notification.aiff');
        }
    }

    /** In-app bell plus device push when an employee's leave is decided. */
    public static function notifyLeaveDecision(Leave $leave): void
    {
        $leave->loadMissing(['user:id,name', 'leave_type:id,name']);

        if (! $leave->user_id) {
            return;
        }

        $status = strtolower((string) $leave->status);
        $statusLabel = ucfirst($status);
        $leaveType = $leave->leave_type?->name ?? 'Leave';
        $dateRange = Carbon::parse($leave->start_date)->isSameDay(Carbon::parse($leave->end_date))
            ? Carbon::parse($leave->start_date)->format('d M Y')
            : Carbon::parse($leave->start_date)->format('d M Y').' to '.Carbon::parse($leave->end_date)->format('d M Y');
        $message = "Your {$leaveType} request for {$dateRange} has been {$status}.";

        $created = self::storeNotifications([$leave->user_id], 'leave_decision', [
            'leave_id' => $leave->id,
            'status' => $status,
            'leave_type' => $leaveType,
            'start_date' => $leave->start_date,
            'end_date' => $leave->end_date,
            'message' => $message,
        ]);

        foreach ($created as $userId => $notification) {
            self::sendPushToUser($userId, "Leave {$statusLabel}", $message, [
                'id' => (string) $notification->id,
                'type' => 'leave_decision',
                'title' => "Leave {$statusLabel}",
                'message' => $message,
                'entity_type' => 'leave',
                'entity_id' => (string) $leave->id,
                'status' => $status,
                'is_read' => '0',
                'created_at' => optional($notification->created_at)->toDateTimeString(),
            ], 'chat_notification.aiff');
        }
    }

    /**
     * Helper: Get all task participants (assignees + creator) excluding actor
     */
    private static function getTaskParticipants($task, $excludeUserId)
    {
        $users = $task->users->pluck('id')->toArray();

        if ($task->created_by && ! in_array($task->created_by, $users)) {
            $users[] = $task->created_by;
        }

        return array_diff($users, [$excludeUserId]);
    }
}
