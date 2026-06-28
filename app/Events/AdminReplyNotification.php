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
        $msg = $reply->contactMessage;
        $this->payload = [
            'contact_message_id' => $reply->contact_message_id,
            'reply_id'           => $reply->id,
            'subject'            => $msg?->subject ?? '',
            'preview'            => \Str::limit($reply->message, 80),
            'message'            => $reply->message,
            'time'               => $reply->created_at->format('d M Y, h:i A'),
            'date_short'         => $reply->created_at->format('d M'),
            'user_id'            => $userId,
        ];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('contact.user.' . $this->payload['user_id'])];
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
