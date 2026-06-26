<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewUserRegistered implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $payload;

    public function __construct(
        int    $userId,
        string $username,
        string $firstName,
        string $lastName,
        string $email,
        string $createdAt
    ) {
        $this->payload = [
            'id'         => $userId,
            'username'   => $username,
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'name'       => trim($firstName . ' ' . $lastName) ?: $username,
            'email'      => $email,
            'created_at' => $createdAt,
        ];
    }

    public function broadcastOn(): array
    {
        return [new Channel('contact.admin')];
    }

    public function broadcastAs(): string
    {
        return 'user.registered';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
