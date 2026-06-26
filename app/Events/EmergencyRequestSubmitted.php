<?php

namespace App\Events;

use App\Models\EmergencyRequest;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EmergencyRequestSubmitted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $payload;

    public function __construct(EmergencyRequest $req)
    {
        $typeLabel = $req->type === '1' ? 'Emergency' : 'Non-Emergency';

        $this->payload = [
            'emergency_id'       => $req->id,
            'request_id'         => $req->id,
            'rreb_id'            => $req->rreb_id,
            'mobile_no'          => $req->mobile_no,
            'pickup_address'     => $req->pickup_address,
            'pickup_lat'         => $req->pickup_lat ? (float) $req->pickup_lat : null,
            'pickup_lng'         => $req->pickup_lng ? (float) $req->pickup_lng : null,
            'hospital_name'      => $req->hospital_name,
            'type'               => $req->type,
            'type_label'         => $typeLabel,
            'notes'              => $req->notes,
            'created_at'         => $req->created_at->format('d M Y, h:i A'),
            'time'               => $req->created_at->format('d M Y, h:i A'),
            'date_short'         => $req->created_at->format('d M'),
            'badge_count'        => EmergencyRequest::whereNotIn('status', ['6', '7'])->count(),
        ];
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('contact.admin'),
            new Channel('emergency.pool'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'emergency.submitted';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
