<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Real-time "this match changed, please refetch" signal — the match counterpart of
 * [ContentUpdated]. Carries only the match id, never the scoreline: the client stays
 * the source of truth and re-pulls the detail endpoint, so a dropped or reordered
 * frame can never leave a scoreboard disagreeing with the server.
 *
 * ShouldBroadcastNow (not queued): a live score must push the instant the scorer taps,
 * and prod has no dedicated queue worker for broadcasts — so it goes out inline.
 * Broadcast on the public per-match channel "match.{id}"; anyone watching listens.
 */
final class MatchUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public int|string $matchId) {}

    public function broadcastOn(): Channel
    {
        return new Channel('match.' . $this->matchId);
    }

    public function broadcastAs(): string
    {
        return 'match.updated';
    }

    /** @return array<string, string> */
    public function broadcastWith(): array
    {
        return [
            'id' => (string) $this->matchId,
            'at' => now()->toIso8601String(),
        ];
    }
}
