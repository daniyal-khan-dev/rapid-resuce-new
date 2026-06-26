<?php

namespace App\Events;

use App\Models\EmergencyRequest;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EmergencyStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $payload;

    public function __construct(EmergencyRequest $req)
    {
        $statusLabels = [
            '1' => 'Pending',
            '2' => 'Dispatched',
            '3' => 'En Route',
            '4' => 'Arrived',
            '5' => 'Transporting',
            '6' => 'Completed',
            '7' => 'Cancelled',
            '8' => 'Awaiting Acceptance',
        ];

        $this->payload = [
            'request_id'   => $req->id,
            'rreb_id'      => $req->rreb_id,
            'status'       => $req->status,
            'status_label' => $statusLabels[$req->status] ?? ucfirst($req->status),
            'ambulance_id' => $req->ambulance_id,
            'ambulance_no' => $req->ambulance?->vehicle_number ?? null,
            'driver_id'    => $req->driver_id,
            'driver_name'  => $req->driver?->name ?? '',
        ];
    }

    public function broadcastOn(): array
    {
        return [new Channel('contact.admin')];
    }

    public function broadcastAs(): string
    {
        return 'emergency.status.updated';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
