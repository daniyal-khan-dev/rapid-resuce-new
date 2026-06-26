<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AdminTyping implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public int $messageId, public int $userId) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('contact.user.' . $this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'admin.typing';
    }

    public function broadcastWith(): array
    {
        return ['contact_message_id' => $this->messageId];
    }
}
