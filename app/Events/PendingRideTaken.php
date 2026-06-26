<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PendingRideTaken implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $payload;

    public function __construct(int $requestId)
    {
        $this->payload = ['request_id' => $requestId];
    }

    public function broadcastOn(): array
    {
        return [new Channel('emergency.pool')];
    }

    public function broadcastAs(): string
    {
        return 'pending.ride.taken';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
