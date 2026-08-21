<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * "Something arrived in this conversation — go and read it."
 *
 * Deliberately carries NO message body, only the conversation id. The channel is public
 * (Reverb here has no client auth wired), so anything in this payload is readable by anyone
 * who guesses the channel name — and a private message is exactly the thing that must not
 * leak that way. The client is told only that its thread moved; it then fetches through the
 * authenticated `?since_id=` endpoint, which checks membership before returning a word.
 *
 * That indirection is also what keeps the thread correct: the server stays the single source
 * of what was said, so a dropped or reordered frame can cost latency but never content.
 *
 * ShouldBroadcastNow, like [MatchUpdated]: prod runs no queue worker for broadcasts, and a
 * chat that pushes "in a minute or so" is not a chat.
 */
final class ConversationUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public int|string $conversationId,
        /** Who sent it, so a client can ignore the echo of its own message. */
        public int|string|null $senderId = null,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('conversation.' . $this->conversationId);
    }

    public function broadcastAs(): string
    {
        return 'conversation.updated';
    }

    /** @return array<string, string|null> */
    public function broadcastWith(): array
    {
        return [
            'id' => (string) $this->conversationId,
            'sender_id' => $this->senderId !== null ? (string) $this->senderId : null,
            'at' => now()->toIso8601String(),
        ];
    }
}
