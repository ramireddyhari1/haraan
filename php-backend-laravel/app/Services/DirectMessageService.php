<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Conversation;
use App\Models\DirectMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Player-to-player direct messages.
 *
 * The permission rule lives HERE and nowhere else, so "who may message whom" is one
 * decision in one place rather than a condition repeated at every call site.
 */
final class DirectMessageService
{
    /**
     * **Mutual follow.** You may message someone only if you each follow the other.
     *
     * Chosen over "anyone you follow" or Instagram's open-inbox-plus-Requests because
     * it is the only rule under which an unwanted message is impossible by
     * construction — and on an app where players are discoverable by district, that
     * matters more than reach. Loosening it later is additive (a Requests inbox);
     * tightening after launch would take something away from people already using it.
     */
    public function canMessage(User $me, User $them): bool
    {
        if ($me->id === $them->id || $them->is_guest) {
            return false;
        }

        return $me->isFollowing($them) && $them->isFollowing($me);
    }

    /**
     * The existing 1:1 with [$them], or a new one. Never creates a second thread for
     * a pair: the participant rows are looked up before anything is written.
     */
    public function conversationWith(User $me, User $them): ?Conversation
    {
        if (! $this->canMessage($me, $them)) {
            return null;
        }

        $existing = Conversation::query()
            ->whereHas('participants', fn ($q) => $q->where('users.id', $me->id))
            ->whereHas('participants', fn ($q) => $q->where('users.id', $them->id))
            ->withCount('participants')
            ->get()
            ->firstWhere('participants_count', 2);

        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($me, $them): Conversation {
            $conversation = Conversation::create([]);
            $conversation->participants()->attach([$me->id, $them->id]);

            return $conversation;
        });
    }

    /**
     * Append a message and move both sides' counters.
     *
     * The conversation's denormalised preview is updated in the same transaction as
     * the insert, so the list can never show a preview for a message that failed to
     * save — or miss one that succeeded.
     */
    public function send(Conversation $conversation, User $sender, string $body): DirectMessage
    {
        return DB::transaction(function () use ($conversation, $sender, $body): DirectMessage {
            $message = $conversation->messages()->create([
                'sender_id' => $sender->id,
                'body' => $body,
            ]);

            $conversation->forceFill([
                'last_message_at' => $message->created_at,
                'last_message_preview' => mb_substr($body, 0, 200),
                'last_sender_id' => $sender->id,
            ])->save();

            // Everyone except the sender gains an unread; the sender is caught up by
            // definition.
            DB::table('conversation_participants')
                ->where('conversation_id', $conversation->id)
                ->where('user_id', '!=', $sender->id)
                ->increment('unread_count');

            DB::table('conversation_participants')
                ->where('conversation_id', $conversation->id)
                ->where('user_id', $sender->id)
                ->update(['unread_count' => 0, 'last_read_at' => now()]);

            return $message;
        });
    }

    /** Mark everything in [$conversation] read for [$user]. */
    public function markRead(Conversation $conversation, User $user): void
    {
        DB::table('conversation_participants')
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->update(['unread_count' => 0, 'last_read_at' => now()]);
    }

    /** Total unread across every thread — the bottom-bar badge. */
    public function unreadTotal(User $user): int
    {
        return (int) DB::table('conversation_participants')
            ->where('user_id', $user->id)
            ->sum('unread_count');
    }
}
