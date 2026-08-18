<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageRead implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $sender_id;
    public int $reader_id;

    public function __construct(int $senderId, int $readerId)
    {
        $this->sender_id = $senderId;
        $this->reader_id = $readerId;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat.' . $this->sender_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.read';
    }
}
