<?php

namespace App\Events;

use App\Models\Admin\ContentNotification;
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
        'ambulance'  => '/admin/ambulances',
        'service'    => '/admin/services',
        'testimonial'=> '/admin/testimonials',
        'faq'        => '/admin/faqs',
        'branch'     => '/admin/branch',
    ];

    private static array $moduleLabels = [
        'ambulance'  => 'Ambulance',
        'service'    => 'Service',
        'testimonial'=> 'Testimonial',
        'faq'        => 'FAQ',
        'branch'     => 'Branch',
    ];

    public function __construct(string $module, string $action, array $data, string $actor = '')
    {
        $moduleLabel = self::$moduleLabels[$module] ?? ucfirst($module);
        $moduleUrl   = self::$moduleUrls[$module]   ?? '/admin/dashboard';

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

        try {
            ContentNotification::create([
                'module'     => $module,
                'action'     => $action,
                'subject'    => $moduleLabel . ' ' . $action,
                'preview'    => 'By ' . ($actor ?: 'admin'),
                'module_url' => $moduleUrl,
                'actor'      => $actor,
                'is_read'    => false,
                'data'       => $data,
            ]);
        } catch (\Throwable $ignored) {}
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
