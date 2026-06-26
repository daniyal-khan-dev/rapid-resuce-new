<?php

namespace App\Events;

use App\Models\EmergencyRequest;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PendingRideAvailable implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $payload;

    public function __construct(EmergencyRequest $req)
    {
        $this->payload = [
            'request_id'     => $req->id,
            'rreb_id'        => $req->rreb_id,
            'type'           => $req->type,
            'type_label'     => $req->type === '1' ? 'Emergency' : 'Non-Emergency',
            'pickup_address' => $req->pickup_address,
            'pickup_lat'     => $req->pickup_lat ? (float) $req->pickup_lat : null,
            'pickup_lng'     => $req->pickup_lng ? (float) $req->pickup_lng : null,
            'hospital_name'  => $req->hospital_name,
            'mobile_no'      => $req->mobile_no,
            'notes'          => $req->notes,
            'created_at'     => $req->created_at?->format('d M Y, h:i A'),
        ];
    }

    public function broadcastOn(): array
    {
        return [new Channel('emergency.pool')];
    }

    public function broadcastAs(): string
    {
        return 'pending.ride.available';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
