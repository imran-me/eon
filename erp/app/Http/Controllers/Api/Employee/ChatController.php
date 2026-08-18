<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Events\MessageDeleted;
use App\Events\MessageRead;
use App\Events\MessageSent;
use App\Events\MessageUpdated;
use App\Models\Chat;
use Illuminate\Http\Request;
use App\Models\User;
use Aws\S3\S3Client;
use App\Services\NotificationService;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    private const CHAT_ALLOWED_TYPES = [
        'image/png',
        'image/jpeg',
        'application/pdf',
    ];
    private const CHAT_MAX_FILE_SIZE = 3145728; // 3 MB

    public function conversations(Request $request)
    {
        $currentUserId = (int) Auth::id();

        $chatEmployeesQuery = User::query()
            ->where('id', '!=', $currentUserId)
            ->where('status', 'active')
            ->role(['employee', 'super admin']);

        $chatLastMessageSubQuery = Chat::query()
            ->selectRaw(
                'CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END as other_user_id, MAX(created_at) as last_message_at',
                [$currentUserId]
            )
            ->where(function ($q) use ($currentUserId) {
                $q->where('sender_id', $currentUserId)
                    ->orWhere('receiver_id', $currentUserId);
            })
            ->groupBy('other_user_id');

        $chatUnreadBySender = Chat::query()
            ->where('receiver_id', $currentUserId)
            ->whereNull('read_at')
            ->select('sender_id', DB::raw('COUNT(*) as unread_count'))
            ->groupBy('sender_id')
            ->pluck('unread_count', 'sender_id');

        $employees = $chatEmployeesQuery
            ->joinSub($chatLastMessageSubQuery, 'last_chat', function ($join) {
                $join->on('users.id', '=', 'last_chat.other_user_id');
            })
            ->select('users.id', 'users.name', 'users.image', 'users.last_seen_at', 'last_chat.last_message_at')
            ->orderByRaw('CASE WHEN last_chat.last_message_at IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('last_chat.last_message_at')
            ->orderBy('users.name')
            ->get()
            ->map(function ($employee) use ($chatUnreadBySender) {
                return [
                    'id' => (int) $employee->id,
                    'name' => $employee->name,
                    'image' => $employee->image,
                    'is_online' => (bool) $employee->is_online,
                    'last_seen_at' => optional($employee->last_seen_at)?->toISOString(),
                    'last_message_at' => optional($employee->last_message_at)?->toISOString(),
                    'unread_count' => (int) ($chatUnreadBySender[$employee->id] ?? 0),
                ];
            })
            ->values();

        return response()->json([
            'status' => 'success',
            'message' => 'Conversation list fetched successfully.',
            'data' => $employees,
        ]);
    }

    public function employees(Request $request)
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'online' => ['nullable', 'boolean'],
        ]);

        $currentUserId = (int) Auth::id();
        $search = trim((string) $request->query('search', ''));
        $online = $request->query('online');

        $query = User::query()
            ->where('id', '!=', $currentUserId)
            ->where('status', 'active')
            ->role(['employee', 'super admin'])
            ->select('id', 'name', 'image', 'last_seen_at')
            ->orderBy('name');

        if ($search !== '') {
            $query->where('name', 'like', '%' . $search . '%');
        }

        $employees = $query->get();

        if (!is_null($online)) {
            $mustBeOnline = filter_var($online, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if (!is_null($mustBeOnline)) {
                $employees = $employees->filter(function ($employee) use ($mustBeOnline) {
                    return (bool) $employee->is_online === $mustBeOnline;
                })->values();
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Employee list fetched successfully.',
            'data' => $employees->map(function ($employee) {
                return [
                    'id' => (int) $employee->id,
                    'name' => $employee->name,
                    'image' => $employee->image,
                    'is_online' => (bool) $employee->is_online,
                    'last_seen_at' => optional($employee->last_seen_at)?->toISOString(),
                ];
            })->values(),
        ]);
    }

    public function fetchMessages(Request $request, string $receiver_id)
    {
        $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'before_id' => ['nullable', 'integer', 'min:1'],
            'cursor' => ['nullable', 'string'],
        ]);

        $perPage = (int) $request->query('per_page', 30);
        $beforeId = $request->query('before_id');
        $cursor = $request->query('cursor');

        $updatedCount = Chat::where('sender_id', $receiver_id)
            ->where('receiver_id', Auth::id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        if ($updatedCount > 0) {
            broadcast(new MessageRead((int) $receiver_id, (int) Auth::id()));
        }

        $baseQuery = Chat::query()
            ->where(function ($q) use ($receiver_id) {
                $q->where('sender_id', Auth::id())->where('receiver_id', $receiver_id);
            })
            ->orWhere(function ($q) use ($receiver_id) {
                $q->where('sender_id', $receiver_id)->where('receiver_id', Auth::id());
            })
            ->orderByDesc('id');

        if (!empty($beforeId)) {
            $baseQuery->where('id', '<', (int) $beforeId);
        }

        $paginator = $baseQuery->cursorPaginate($perPage, ['*'], 'cursor', $cursor);

        // Keep chat payload oldest -> newest for easier UI append.
        $messages = array_values(array_reverse($paginator->items()));

        $nextBeforeId = null;
        if (!empty($messages) && $paginator->hasMorePages()) {
            $nextBeforeId = (int) $messages[0]->id;
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Messages fetched successfully.',
            'data' => $messages,
            'pagination' => [
                'per_page' => $perPage,
                'has_more' => $paginator->hasMorePages(),
                'next_before_id' => $nextBeforeId,
                'next_cursor' => optional($paginator->nextCursor())->encode(),
                'prev_cursor' => optional($paginator->previousCursor())->encode(),
            ],
        ]);
    }

    public function markAsRead(Request $request)
    {
        $request->validate([
            'sender_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $senderId = (int) $request->sender_id;
        $readerId = (int) Auth::id();

        $updatedCount = Chat::query()
            ->where('sender_id', $senderId)
            ->where('receiver_id', $readerId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        if ($updatedCount > 0) {
            broadcast(new MessageRead($senderId, $readerId));
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Messages marked as read.',
            'data' => [
                'sender_id' => $senderId,
                'reader_id' => $readerId,
                'updated_count' => $updatedCount,
            ],
        ]);
    }

    public function sendMessage(Request $request)
    {
        $request->validate([
            'receiver_id' => ['required', 'integer', 'exists:users,id'],
            'message'     => ['nullable', 'string', 'max:5000'],
            'file'        => ['nullable', 'file', 'mimes:png,jpg,jpeg,pdf', 'max:3072'],
            'file_url'    => ['nullable', 'url'],
            'file_type'   => ['nullable', 'string'],
        ]);

        if (!$request->filled('message') && !$request->hasFile('file') && !$request->filled('file_url')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Message or file is required.'
            ], 422);
        }

        $filePath = null;
        $fileType = null;

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $filePath = $file->store('chat-files', 'public');
            $fileType = $file->getMimeType();
        } elseif ($request->filled('file_url')) {
            $filePath = $request->file_url;
            $fileType = $request->file_type;
        }

        $chat = Chat::create([
            'sender_id'   => Auth::id(),
            'receiver_id' => $request->receiver_id,
            'message'     => $request->message ?? '',
            'file_path'   => $filePath,
            'file_type'   => $fileType,
        ]);

        broadcast(new MessageSent($chat))->toOthers();
        NotificationService::notifyChatMessage($chat);

        return response()->json([
            'status' => 'success',
            'message' => 'Message sent successfully.',
            'data' => $chat
        ]);
    }

    public function updateMessage(Request $request, Chat $chat)
    {
        abort_unless((int) $chat->sender_id === (int) Auth::id(), 403);

        $request->validate([
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $chat->update([
            'message'   => $request->message,
            'edited_at' => now(),
        ]);

        broadcast(new MessageUpdated($chat))->toOthers();

        return response()->json([
            'status' => 'success',
            'message' => 'Message updated successfully.',
            'data' => $chat,
        ]);
    }

    public function destroyMessage(Chat $chat)
    {
        abort_unless((int) $chat->sender_id === (int) Auth::id(), 403);

        $chatId = $chat->id;
        $receiverId = $chat->receiver_id;
        $senderId = $chat->sender_id;

        $chat->delete();

        broadcast(new MessageDeleted($chatId, $receiverId, $senderId))->toOthers();

        return response()->json([
            'status' => 'success',
            'message' => 'Message deleted successfully.',
        ]);
    }

    public function presignUpload(Request $request)
    {        
        $request->validate([
            'filename'     => ['required', 'string'],
            'content_type' => ['required', 'string'],
            'size'         => ['required', 'integer', 'max:' . self::CHAT_MAX_FILE_SIZE],
        ]);

        if (!in_array($request->content_type, self::CHAT_ALLOWED_TYPES, true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid file type.'
            ], 422);
        }

        $ext = pathinfo($request->filename, PATHINFO_EXTENSION);
        $key = 'chat-files/' . Str::uuid() . ($ext ? '.' . $ext : '');

        $r2 = config('filesystems.disks.r2');
        $client = new S3Client([
            'version' => 'latest',
            'region' => $r2['region'] ?? 'auto',
            'endpoint' => $r2['endpoint'] ?? null,
            'credentials' => [
                'key' => $r2['key'] ?? null,
                'secret' => $r2['secret'] ?? null,
            ],
            'use_path_style_endpoint' => true,
        ]);

        $command = $client->getCommand('PutObject', [
            'Bucket' => $r2['bucket'] ?? null,
            'Key' => $key,
            'ContentType' => $request->content_type,
        ]);

        $signedRequest = $client->createPresignedRequest($command, '+10 minutes');

        return response()->json([
            'status' => 'success',
            'message' => 'Presigned URL generated successfully.',
            'data' => [
                'upload_url' => (string) $signedRequest->getUri(),
                'key' => $key,
                'public_url' => rtrim((string) $r2['public_url'] ?? null, '/') . '/' . $key,
            ],
        ]);
    }

    public function onlineStatus(Request $request)
    {
        $ids = array_values(array_filter(explode(',', $request->query('ids', ''))));
        if (empty($ids)) {
            return response()->json([]);
        }
        $users = User::whereIn('id', $ids)
            ->select('id', 'last_seen_at')
            ->get()
            ->mapWithKeys(fn($u) => [$u->id => $u->is_online]);

        return response()->json([
            'status' => 'success',
            'message' => 'Online status fetched successfully.',
            'data' => $users,
        ]);
    }
}
