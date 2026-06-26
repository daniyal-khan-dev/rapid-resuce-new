<?php

namespace App\Events;

use App\Models\EmergencyRequest;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class DispatchRequestSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $payload;
    private int  $driverId;

    public function __construct(EmergencyRequest $req)
    {
        $this->driverId = (int) $req->driver_id;

        $this->payload = [
            'dispatch_token' => (string) Str::uuid(),   // unique per dispatch, used for client-side dedup
            'request_id'     => $req->id,
            'rreb_id'        => $req->rreb_id,
            // Authoritative badge count at the moment of dispatch — used by the
            // frontend to set the Requests nav badge directly, avoiding the
            // localStorage race condition that occurs when multiple tabs both
            // increment from the old stored value.
            'pending_count'  => (int) EmergencyRequest::where('driver_id', $req->driver_id)
                ->whereIn('status', ['2', '3', '4', '5', '8'])
                ->count(),
            'type'           => $req->type,
            'type_label'     => $req->type === '1' ? 'Emergency' : 'Non-Emergency',
            'pickup_address' => $req->pickup_address,
            'pickup_lat'     => $req->pickup_lat     ? (float) $req->pickup_lat     : null,
            'pickup_lng'     => $req->pickup_lng     ? (float) $req->pickup_lng     : null,
            'hospital_name'  => $req->hospital_name,
            'hospital_lat'   => $req->hospital_lat   ? (float) $req->hospital_lat   : null,
            'hospital_lng'   => $req->hospital_lng   ? (float) $req->hospital_lng   : null,
            'mobile_no'      => $req->mobile_no,
            'notes'          => $req->notes ?? '',
            'ambulance_no'   => $req->ambulance?->vehicle_number ?? '',
            'ambulance_type' => $req->ambulance?->type ?? '',
            'time'           => now()->format('d M Y, h:i A'),
            'date_short'     => now()->format('d M'),
        ];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('driver.' . $this->driverId)];
    }

    public function broadcastAs(): string
    {
        return 'dispatch.request.sent';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
