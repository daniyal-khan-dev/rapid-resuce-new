<?php

namespace App\Events;

use App\Models\ContactReply;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AdminReplyNotification implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $payload;

    public function __construct(ContactReply $reply, int $userId)
    {
        $message = $reply->contactMessage;
        $this->payload = [
            'reply_id'           => $reply->id,
            'contact_message_id' => $reply->contact_message_id,
            'subject'            => $message?->subject ?? 'Your message',
            'preview'            => \Str::limit($reply->message, 80),
            'time'               => $reply->created_at->format('d M Y, h:i A'),
            'user_id'            => $userId,
        ];
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('contact.user.' . $this->payload['user_id']),
        ];
    }

    public function broadcastAs(): string
    {
        return 'admin.reply';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
