<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * "Your inbox changed — reload the list."
 *
 * The thread-level [ConversationUpdated] only reaches someone who has that conversation OPEN.
 * This one reaches each member's inbox, so the chat list re-orders and its unread badges move
 * the moment a message lands, instead of when the Chat tab is next opened.
 *
 * Same privacy shape as [ConversationUpdated]: Reverb here has no client auth, so the channel
 * is public and the payload carries nothing but a time. The channel NAME is the protection —
 * an HMAC of the user id under the app key ({@see \App\Services\DirectMessageService::inboxChannel}),
 * so nobody can listen to someone else's inbox by guessing their id, and even the person who
 * finds one learns only "something happened", never what or with whom.
 */
final class InboxUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;

    /** @param list<string> $channels inbox channel names, one per member */
    public function __construct(public array $channels) {}

    /** @return list<Channel> */
    public function broadcastOn(): array
    {
        return array_map(fn (string $name) => new Channel($name), $this->channels);
    }

    public function broadcastAs(): string
    {
        return 'inbox.updated';
    }

    /** @return array<string, string> */
    public function broadcastWith(): array
    {
        return ['at' => now()->toIso8601String()];
    }
}
