<?php

namespace App\Events;

use App\Models\EmergencyRequest;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EmergencyRequestDeleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $payload;

    public function __construct(int $requestId)
    {
        $this->payload = [
            'request_id'  => $requestId,
            'time'        => now()->format('d M Y, h:i A'),
            'badge_count' => EmergencyRequest::whereNotIn('status', ['6', '7'])->count(),
        ];
    }

    public function broadcastOn(): array
    {
        return [new Channel('contact.admin')];
    }

    public function broadcastAs(): string
    {
        return 'emergency.request.deleted';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
