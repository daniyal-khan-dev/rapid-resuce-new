<?php

namespace App\Events;

use App\Models\Driver\Driver;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DriverAvailabilityUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $payload;

    public function __construct(Driver $driver)
    {
        $labels = ['1' => 'Online', '2' => 'Offline', '3' => 'Busy'];

        $this->payload = [
            'driver_id'    => $driver->id,
            'driver_name'  => $driver->name,
            'phone'        => $driver->phone,
            'status'       => $driver->status,
            'status_label' => $labels[$driver->status] ?? 'Unknown',
            'time'         => now()->format('d M Y, h:i A'),
            'lat'          => $driver->lat,
            'lng'          => $driver->lng,
        ];
    }

    public function broadcastOn(): array
    {
        return [new Channel('contact.admin')];
    }

    public function broadcastAs(): string
    {
        return 'driver.availability.updated';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
