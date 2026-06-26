<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RideChatNotificationRead implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $recipientType,
        public ?int   $recipientId,
        public int    $requestId,
        public string $rrebId,
        public int    $count = 0,
    ) {}

    public function broadcastOn(): array
    {
        if ($this->recipientType === 'admin') {
            return [new Channel('contact.admin')];
        }
        if ($this->recipientType === 'driver') {
            return [new PrivateChannel('driver.' . $this->recipientId)];
        }
        return [new PrivateChannel('contact.user.' . $this->recipientId)];
    }

    public function broadcastAs(): string
    {
        return 'ride.chat.notif.read';
    }

    public function broadcastWith(): array
    {
        return [
            'recipient_type' => $this->recipientType,
            'recipient_id'   => $this->recipientId,
            'request_id'     => $this->requestId,
            'rreb_id'        => $this->rrebId,
            'count'          => $this->count,
        ];
    }
}
