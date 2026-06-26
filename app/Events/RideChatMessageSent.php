<?php

namespace App\Events;

use App\Models\EmergencyRequest;
use App\Models\RideChatMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RideChatMessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public RideChatMessage  $chatMsg,
        public EmergencyRequest $request,
        public ?int             $driverId,
        public ?int             $userId,
    ) {}

    public function broadcastOn(): array
    {
        $channels = [new Channel('contact.admin')];

        if ($this->driverId) {
            $channels[] = new PrivateChannel('driver.' . $this->driverId);
        }

        if ($this->userId) {
            $channels[] = new PrivateChannel('contact.user.' . $this->userId);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'ride.chat.message';
    }

    public function broadcastWith(): array
    {
        return [
            'message_id'     => $this->chatMsg->id,
            'request_id'     => $this->request->id,
            'rreb_id'        => $this->request->rreb_id,
            'status'         => $this->request->status,
            'pickup_address' => $this->request->pickup_address,
            'driver_name'    => $this->request->driver?->name ?? '—',
            'chat_status'    => \App\Models\RideChatMessage::chatStatus($this->request->status),
            'sender_type'    => $this->chatMsg->sender_type,
            'sender_id'      => $this->chatMsg->sender_id,
            'sender_name'    => $this->chatMsg->sender_name,
            'message'        => $this->chatMsg->message,
            'time'           => $this->chatMsg->created_at->format('d M Y, h:i A'),
            'date_short'     => $this->chatMsg->created_at->format('d M'),
            'driver_id'      => $this->driverId,
            'user_id'        => $this->userId,
        ];
    }
}
