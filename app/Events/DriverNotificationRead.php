<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DriverNotificationRead implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $payload;
    private int  $driverId;

    public function __construct(int $driverId, int $notifId, ?int $emergencyRequestId = null)
    {
        $this->driverId = $driverId;
        $this->payload  = [
            'notif_id'             => $notifId,
            'emergency_request_id' => $emergencyRequestId,
        ];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('driver.' . $this->driverId)];
    }

    public function broadcastAs(): string
    {
        return 'notification.read';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
