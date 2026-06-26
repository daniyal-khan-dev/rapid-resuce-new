<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatResolved implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public int $messageId, public ?int $userId) {}

    public function broadcastOn(): array
    {
        $channels = [new Channel('contact.admin')];
        if ($this->userId) {
            $channels[] = new PrivateChannel('contact.user.' . $this->userId);
        }
        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'chat.resolved';
    }

    public function broadcastWith(): array
    {
        return ['contact_message_id' => $this->messageId];
    }
}
