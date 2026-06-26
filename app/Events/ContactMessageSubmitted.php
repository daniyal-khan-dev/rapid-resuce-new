<?php

namespace App\Events;

use App\Models\User\ContactMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ContactMessageSubmitted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $payload;

    public function __construct(ContactMessage $message)
    {
        $this->payload = [
            'message_id' => $message->id,
            'user_id'    => $message->user_id,
            'name'       => $message->name,
            'email'      => $message->email,
            'subject'    => $message->subject,
            'message'    => $message->message,
            'time'       => $message->created_at->format('d M Y, h:i A'),
            'date_short' => $message->created_at->format('d M'),
        ];
    }

    public function broadcastOn(): array
    {
        $channels = [
            new Channel('contact.admin'),
        ];

        if ($this->payload['user_id']) {
            $channels[] = new PrivateChannel('contact.user.' . $this->payload['user_id']);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'contact.submitted';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
