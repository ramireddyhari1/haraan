<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * "Someone in this conversation is typing."
 *
 * Goes to the open thread (`conversation.{id}`) and to each OTHER member's keyed inbox channel,
 * so both the "typing…" bubble and the chat-list row can show it. Carries who (a user id) and
 * nothing they typed — the channels are public, and a typing signal must never become a way to
 * read a message before it is sent. Clients drop it on their own after a few seconds, so a
 * dropped "stopped typing" can never leave the dots running.
 */
final class ConversationTyping implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;

    /** @param list<string> $inboxChannels the other members' inbox channels */
    public function __construct(
        public int $conversationId,
        public int $senderId,
        public array $inboxChannels = [],
    ) {}

    /** @return list<Channel> */
    public function broadcastOn(): array
    {
        return array_merge(
            [new Channel('conversation.'.$this->conversationId)],
            array_map(fn (string $name) => new Channel($name), $this->inboxChannels),
        );
    }

    public function broadcastAs(): string
    {
        return 'conversation.typing';
    }

    /** @return array<string, string> */
    public function broadcastWith(): array
    {
        return [
            'id' => (string) $this->conversationId,
            'sender_id' => (string) $this->senderId,
        ];
    }
}
