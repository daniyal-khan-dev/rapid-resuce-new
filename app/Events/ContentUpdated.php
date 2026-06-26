<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ContentUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $payload;

    private static array $moduleUrls = [
        'driver' => '/admin/drivers',
    ];

    public function __construct(string $module, string $action, array $data, string $actor = '')
    {
        $moduleUrl = self::$moduleUrls[$module] ?? '/admin/dashboard';

        $this->payload = [
            'module'     => $module,
            'action'     => $action,
            'data'       => $data,
            'actor'      => $actor,
            'module_url' => $moduleUrl,
            'time'       => now()->format('d M Y, h:i A'),
            'date_short' => now()->format('d M'),
            'nonce'      => bin2hex(random_bytes(8)),
        ];
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('content.updates'),
            new Channel('contact.admin'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'content.updated';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
