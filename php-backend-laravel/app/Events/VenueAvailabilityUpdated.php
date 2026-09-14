<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * "This venue's bookable hours just changed, please refetch" — the venue counterpart of
 * [MatchUpdated]. Carries only the venue id: who booked what is nobody's business on a
 * public channel, and the client re-pulls /api/venues/{id}/availability as the source of truth.
 *
 * ShouldBroadcastNow because prod runs no broadcast queue worker (see MatchUpdated), and
 * ShouldDispatchAfterCommit because bookings are written inside a transaction — a nudge that
 * lands before the commit would make the client refetch the old, still-open state.
 */
final class VenueAvailabilityUpdated implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public int $venueId) {}

    public function broadcastOn(): Channel
    {
        return new Channel('venue.' . $this->venueId);
    }

    public function broadcastAs(): string
    {
        return 'venue.availability';
    }

    /** @return array<string, int|string> */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->venueId,
            'at' => now()->toIso8601String(),
        ];
    }
}
