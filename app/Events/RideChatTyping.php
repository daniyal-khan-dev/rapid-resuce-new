<?php

namespace App\Events;

use App\Models\EmergencyRequest;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RideChatTyping implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $requestId,
        public string $senderType,
        public string $senderName,
        public ?int $driverId,
        public ?int $userId
    ) {}

    public function broadcastOn(): array
    {
        $channels = [new Channel('contact.admin')];

        if ($this->driverId) {
            $channels[] = new PrivateChannel('driver.' . $this->driverId);
        }

        if ($this->userId) {
            $channels[] = new PrivateChannel('contact.user.' . $this->userId);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'ride.chat.typing';
    }

    public function broadcastWith(): array
    {
        return [
            'request_id'  => $this->requestId,
            'sender_type' => $this->senderType,
            'sender_name' => $this->senderName,
        ];
    }
}
