<?php

namespace App\Events;

use App\Models\Driver\Driver;
use App\Models\EmergencyRequest;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DriverLocationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $payload;

    public function __construct(Driver $driver)
    {
        $activeReq = EmergencyRequest::where('driver_id', $driver->id)
            ->whereIn('status', ['2', '3', '4', '5', '8'])
            ->latest('dispatched_at')
            ->first();

        $this->payload = [
            'driver_id'   => $driver->id,
            'driver_name' => $driver->name,
            'lat'         => (float) $driver->lat,
            'lng'         => (float) $driver->lng,
            'request_id'  => $activeReq?->id,
            'time'        => now()->format('d M Y, h:i A'),
            'ts'          => now()->timestamp,
            /* Active-ride details for admin live monitoring */
            'req_status'   => $activeReq?->status,
            'pickup_lat'   => $activeReq ? (float) $activeReq->pickup_lat   : null,
            'pickup_lng'   => $activeReq ? (float) $activeReq->pickup_lng   : null,
            'hospital_lat' => $activeReq ? (float) $activeReq->hospital_lat : null,
            'hospital_lng' => $activeReq ? (float) $activeReq->hospital_lng : null,
        ];
    }

    public function broadcastOn(): array
    {
        return [new Channel('contact.admin')];
    }

    public function broadcastAs(): string
    {
        return 'driver.location.updated';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
