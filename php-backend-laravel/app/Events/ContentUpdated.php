<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Real-time "this content changed, please refetch" signal. Carries only the
 * domain (e.g. config, home, i18n, venues) — never the payload — so clients
 * refetch the relevant endpoint and stay the source of truth. Broadcast on the
 * public "content" channel; the app/web listen and refresh in-place.
 */
final class ContentUpdated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public string $domain) {}

    public function broadcastOn(): Channel
    {
        return new Channel('content');
    }

    public function broadcastAs(): string
    {
        return 'content.updated';
    }

    /** @return array<string, string> */
    public function broadcastWith(): array
    {
        return [
            'domain' => $this->domain,
            'at' => now()->toIso8601String(),
        ];
    }
}
