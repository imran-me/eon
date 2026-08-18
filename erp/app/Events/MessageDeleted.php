<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageDeleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $chatId;
    public int $receiverId;
    public int $senderId;

    public function __construct(int $chatId, int $receiverId, int $senderId)
    {
        $this->chatId = $chatId;
        $this->receiverId = $receiverId;
        $this->senderId = $senderId;
    }

    public function broadcastOn(): array
    {
        // Broadcast on a private channel unique to the receiver
        return [
            new PrivateChannel('chat.' . $this->receiverId)
        ];
    }

    public function broadcastAs()
    {
        return 'message.deleted';
    }

    public function broadcastWith(): array
    {
        return ['chat_id' => $this->chatId, 'sender_id' => $this->senderId];
    }
}
