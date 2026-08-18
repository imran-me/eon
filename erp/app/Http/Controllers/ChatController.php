<?php

namespace App\Http\Controllers;

use App\Events\MessageDeleted;
use App\Events\MessageRead;
use App\Events\MessageSent;
use App\Events\MessageUpdated;
use App\Models\Chat;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;
use App\Models\User;
use Aws\S3\S3Client;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Services\NotificationService;

class ChatController extends Controller
{
    private const CHAT_ALLOWED_TYPES = [
        'image/png',
        'image/jpeg',
        'application/pdf',
    ];
    private const CHAT_MAX_FILE_SIZE = 3145728; // 3 MB

    public function fetchMessages($role, $receiver_id)
    {
        $updatedCount = Chat::where('sender_id', $receiver_id)
            ->where('receiver_id', Auth::id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        if ($updatedCount > 0) {
            broadcast(new MessageRead((int) $receiver_id, (int) Auth::id()));
        }

        $messages = Chat::where(function ($q) use ($receiver_id) {
            $q->where('sender_id', Auth::id())->where('receiver_id', $receiver_id);
        })->orWhere(function ($q) use ($receiver_id) {
            $q->where('sender_id', $receiver_id)->where('receiver_id', Auth::id());
        })->orderBy('created_at', 'asc')->get();

        return response()->json($messages);
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
            return response()->json(['error' => 'Message or file is required.'], 422);
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

        return response()->json(['status' => 'success', 'data' => $chat]);
    }

    public function updateMessage(Request $request, $role, Chat $chat)
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

        return response()->json(['status' => 'success', 'data' => $chat]);
    }

    public function destroyMessage($role, Chat $chat)
    {
        abort_unless((int) $chat->sender_id === (int) Auth::id(), 403);

        $chatId = $chat->id;
        $receiverId = $chat->receiver_id;
        $senderId = $chat->sender_id;

        $chat->delete();

        broadcast(new MessageDeleted($chatId, $receiverId, $senderId))->toOthers();

        return response()->json(['status' => 'success']);
    }

    public function presignUpload(Request $request)
    {
        $request->validate([
            'filename'     => ['required', 'string'],
            'content_type' => ['required', 'string'],
            'size'         => ['required', 'integer', 'max:' . self::CHAT_MAX_FILE_SIZE],
        ]);

        if (!in_array($request->content_type, self::CHAT_ALLOWED_TYPES, true)) {
            return response()->json(['error' => 'Invalid file type.'], 422);
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
            'upload_url' => (string) $signedRequest->getUri(),
            'key' => $key,
            'public_url' => rtrim((string) $r2['public_url'] ?? null, '/') . '/' . $key,
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

        return response()->json($users);
    }
}
