<?php

namespace App\Events;

use App\Models\EmergencyRequest;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EmergencyDispatched implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $payload;
    private int  $driverId;

    public function __construct(EmergencyRequest $req, int $notifId)
    {
        $this->driverId = (int) $req->driver_id;

        $this->payload = [
            'notif_id'        => $notifId,
            'request_id'      => $req->id,
            'rreb_id'         => $req->rreb_id,
            'type'            => $req->type,
            'type_label'      => $req->type === '1' ? 'Emergency' : 'Non-Emergency',
            'pickup_address'  => $req->pickup_address,
            'hospital_name'   => $req->hospital_name,
            'mobile_no'       => $req->mobile_no,
            'notes'           => $req->notes ?? '',
            'ambulance_no'    => $req->ambulance?->vehicle_number ?? '',
            'ambulance_type'  => $req->ambulance?->type ?? '',
            'time'            => $req->dispatched_at?->format('d M Y, h:i A') ?? now()->format('d M Y, h:i A'),
            'date_short'      => now()->format('d M'),
        ];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('driver.' . $this->driverId)];
    }

    public function broadcastAs(): string
    {
        return 'emergency.dispatched';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
