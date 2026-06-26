<?php

namespace App\Events;

use App\Models\ContactReply;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserReplySubmitted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $payload;

    public function __construct(ContactReply $reply)
    {
        $msg = $reply->contactMessage;
        $this->payload = [
            'reply_id'           => $reply->id,
            'contact_message_id' => $reply->contact_message_id,
            'user_name'          => $msg?->name ?? 'User',
            'subject'            => $msg?->subject ?? 'Message',
            'message'            => $reply->message,
            'preview'            => \Str::limit($reply->message, 80),
            'time'               => $reply->created_at->format('d M Y, h:i A'),
            'date_short'         => $reply->created_at->format('d M'),
        ];
    }

    public function broadcastOn(): array
    {
        return [new Channel('contact.admin')];
    }

    public function broadcastAs(): string
    {
        return 'user.reply';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
