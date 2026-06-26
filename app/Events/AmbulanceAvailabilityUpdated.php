<?php

namespace App\Events;

use App\Models\Admin\Ambulance;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AmbulanceAvailabilityUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $payload;

    public function __construct(Ambulance $ambulance)
    {
        $statusLabels = [
            '1' => 'Available',
            '2' => 'On Job',
            '3' => 'Maintenance',
            '4' => 'Out of Service',
        ];

        $typeLabels = [
            '1' => 'Type I',
            '2' => 'Type II',
            '3' => 'Type III',
            '4' => 'Mobile ICU',
            '5' => 'Bariatric',
        ];

        $this->payload = [
            'ambulance_id'   => $ambulance->id,
            'vehicle_number' => $ambulance->vehicle_number,
            'type'           => $ambulance->type,
            'label'          => $ambulance->vehicle_number . ' — ' . $ambulance->type,
            'status'         => (string) $ambulance->status,
            'status_label'   => $statusLabels[$ambulance->status] ?? 'Unknown',
            'time'           => now()->format('d M Y, h:i A'),
        ];
    }

    public function broadcastOn(): array
    {
        return [new Channel('contact.admin')];
    }

    public function broadcastAs(): string
    {
        return 'ambulance.availability.updated';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
