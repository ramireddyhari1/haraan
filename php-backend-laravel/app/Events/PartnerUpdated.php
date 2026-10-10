<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\PartnerUpdate;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * "Haraan just changed something on your account" — straight to the partner app's
 * open socket. A PRIVATE channel (private-partner.{owner id}), because unlike the
 * content/match nudges this one carries the update's own words (amounts, account
 * names); the app signs in to it through /api/partner/realtime/auth.
 *
 * ShouldBroadcastNow: prod has no queue worker for broadcasts.
 */
final class PartnerUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(public PartnerUpdate $update) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('partner.' . $this->update->partner_id);
    }

    public function broadcastAs(): string
    {
        return 'partner.updated';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return $this->update->toApi();
    }
}
