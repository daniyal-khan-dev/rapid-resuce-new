<?php

namespace App\Events;

use App\Models\EmergencyRequest;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RequestStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $payload;
    private ?int $userId;
    private ?int $driverId;

    public function __construct(
        EmergencyRequest $req,
        string $driverName,
        ?float $driverLat = null,
        ?float $driverLng = null
    ) {
        $statusLabels = [
            '1' => 'Pending',    '2' => 'Dispatched',   '3' => 'En Route',
            '4' => 'Arrived',    '5' => 'Transporting',  '6' => 'Completed',
            '7' => 'Cancelled',
        ];

        $this->userId   = $req->user_id   ?? null;
        $this->driverId = $req->driver_id ?? null;

        $this->payload = [
            'request_id'     => $req->id,
            'rreb_id'        => $req->rreb_id,
            'status'         => $req->status,
            'status_label'   => $statusLabels[$req->status] ?? ucfirst($req->status),
            'driver_id'      => $req->driver_id,
            'driver_name'    => $driverName,
            'driver_lat'     => $driverLat,
            'driver_lng'     => $driverLng,
            'user_id'        => $this->userId,
            'time'           => now()->format('d M Y, h:i A'),
            'date_short'     => now()->format('d M'),
            'type'           => $req->type,
            'pickup_address' => $req->pickup_address,
            'hospital_name'  => $req->hospital_name,
            'mobile_no'      => $req->mobile_no,
            'ambulance_no'   => $req->ambulance?->vehicle_number ?? null,
            'completed_at'   => $req->completed_at?->format('d M Y, h:i A'),
            'dispatched_at'  => $req->dispatched_at?->format('d M Y, h:i A'),
            'created_at'     => $req->created_at->format('d M Y, h:i A'),
            'notes'          => $req->notes,
            'badge_count'    => EmergencyRequest::whereNotIn('status', ['6', '7'])->count(),
            'pickup_lat'     => $req->pickup_lat   ? (float) $req->pickup_lat   : null,
            'pickup_lng'     => $req->pickup_lng   ? (float) $req->pickup_lng   : null,
            'hospital_lat'   => $req->hospital_lat ? (float) $req->hospital_lat : null,
            'hospital_lng'   => $req->hospital_lng ? (float) $req->hospital_lng : null,
        ];
    }

    public function broadcastOn(): array
    {
        $channels = [new Channel('contact.admin')];

        if ($this->userId) {
            $channels[] = new PrivateChannel('contact.user.' . $this->userId);
        }

        if ($this->driverId) {
            $channels[] = new PrivateChannel('driver.' . $this->driverId);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'request.status.updated';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
