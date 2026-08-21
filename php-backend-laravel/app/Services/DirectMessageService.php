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

        // A block closes the conversation from either side. Blocking already tears down
        // the mutual follow, so this is belt-and-braces — but it is the check that holds
        // if a follow row ever survives, and messaging is the one place where being
        // wrong is not a cosmetic bug.
        if (User::blockExistsBetween($me, $them)) {
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
     * Create a group with [$creator] and [$members].
     *
     * Every member must mutually follow the creator — the same rule that governs 1:1,
     * applied per person, so nobody can be pulled into a group by someone they haven't
     * connected with. Returns null if the roster is empty or any member fails the rule,
     * so the caller answers with a reason rather than a half-built group.
     *
     * @param  array<int, User>  $members
     */
    public function createGroup(User $creator, string $title, array $members): ?Conversation
    {
        $title = trim($title);
        if ($title === '' || $members === []) {
            return null;
        }

        // De-dupe and drop the creator if they were included by mistake.
        $unique = [];
        foreach ($members as $m) {
            if ($m->id !== $creator->id) {
                $unique[$m->id] = $m;
            }
        }
        if ($unique === []) {
            return null;
        }

        foreach ($unique as $member) {
            if (! $this->canMessage($creator, $member)) {
                return null;
            }
        }

        return DB::transaction(function () use ($creator, $title, $unique): Conversation {
            $conversation = Conversation::create([
                'is_group' => true,
                'title' => mb_substr($title, 0, 80),
                'created_by' => $creator->id,
            ]);
            $conversation->participants()->attach(
                array_merge([$creator->id], array_keys($unique))
            );

            return $conversation;
        });
    }

    /**
     * Remove [$user] from a group. Only meaningful for groups — a 1:1 is left by simply
     * not opening it. When the last member leaves, the empty conversation (and its
     * messages, via cascade) is deleted so it can't linger as an unreachable shell.
     */
    public function leaveGroup(Conversation $conversation, User $user): void
    {
        DB::transaction(function () use ($conversation, $user): void {
            $conversation->participants()->detach($user->id);

            if ($conversation->participants()->count() === 0) {
                $conversation->delete();
            }
        });
    }

    /**
     * Append a message and move both sides' counters.
     *
     * The conversation's denormalised preview is updated in the same transaction as
     * the insert, so the list can never show a preview for a message that failed to
     * save — or miss one that succeeded.
     */
    public function send(
        Conversation $conversation,
        User $sender,
        string $body,
        ?int $replyToId = null,
        bool $forwarded = false,
    ): DirectMessage {
        return DB::transaction(function () use ($conversation, $sender, $body, $replyToId, $forwarded): DirectMessage {
            // A reply may only quote a message from THIS conversation. Quoting across threads
            // would put words from a conversation the reader isn't in onto their screen.
            $replyTo = null;
            if ($replyToId !== null) {
                $replyTo = $conversation->messages()->whereKey($replyToId)->first()?->id;
            }

            $message = $conversation->messages()->create([
                'sender_id' => $sender->id,
                'body' => $body,
                'reply_to_id' => $replyTo,
                'is_forwarded' => $forwarded,
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

    /**
     * Send, then tell everyone watching the thread that it moved.
     *
     * The broadcast happens AFTER the transaction commits — a listener that refetches the
     * instant it hears must find the message already there, and a push from inside the
     * transaction can arrive before the row is visible to other connections.
     */
    public function sendAndBroadcast(
        Conversation $conversation,
        User $sender,
        string $body,
        ?int $replyToId = null,
        bool $forwarded = false,
    ): DirectMessage {
        $message = $this->send($conversation, $sender, $body, $replyToId, $forwarded);

        \App\Events\ConversationUpdated::dispatch($conversation->id, $sender->id);

        return $message;
    }

    /**
     * Mark every thread as DELIVERED to [$user] up to now.
     *
     * Called when their app fetches the thread list: the messages are on their device, but
     * they have not opened the conversation. That is exactly the gap between one tick and
     * two, and without a separate stamp the second tick would be a lie dressed as a fact.
     */
    public function markDelivered(User $user): void
    {
        DB::table('conversation_participants')
            ->where('user_id', $user->id)
            ->update(['last_delivered_at' => now()]);
    }

    /**
     * Unsend: clear the body, keep the row.
     *
     * The row survives so the thread keeps its order and every other client can be told
     * the message is gone — a vanished row leaves a hole nobody can explain. The body is
     * actually cleared, so "unsent" means unreadable, not merely hidden by a client that
     * might be an old build.
     *
     * Returns false when the message is not this user's, or is already gone.
     */
    public function unsend(Conversation $conversation, User $user, int $messageId): bool
    {
        $message = $conversation->messages()
            ->where('id', $messageId)
            ->whereNull('deleted_at')
            ->first();

        if ($message === null || (int) $message->sender_id !== (int) $user->id) {
            return false;
        }

        $message->forceFill(['body' => '', 'deleted_at' => now()])->save();

        // The preview on the thread list quotes the last message; if that was this one, it
        // has to stop quoting it.
        if ((int) ($conversation->last_sender_id ?? 0) === (int) $user->id) {
            $latest = $conversation->messages()->whereNull('deleted_at')->orderByDesc('id')->first();
            $conversation->forceFill([
                'last_message_preview' => $latest?->body ?? 'Message deleted',
                'last_message_at' => $latest?->created_at ?? $conversation->last_message_at,
                'last_sender_id' => $latest?->sender_id ?? $conversation->last_sender_id,
            ])->save();
        }

        \App\Events\ConversationUpdated::dispatch($conversation->id, $user->id);

        return true;
    }

    /**
     * React to a message, or take your reaction back.
     *
     * One per person per message: sending a different emoji replaces yours, and sending the
     * one you already chose clears it — which is what tapping the same face twice means to
     * anyone who has used a messenger. An empty emoji clears it too, so the client has an
     * explicit way to say "remove" without guessing.
     *
     * Returns false when the message is not in this conversation, or has been unsent.
     */
    public function react(Conversation $conversation, User $user, int $messageId, string $emoji): bool
    {
        $message = $conversation->messages()
            ->where('id', $messageId)
            ->whereNull('deleted_at')
            ->first();

        if ($message === null) {
            return false;
        }

        $emoji = trim($emoji);
        $existing = DB::table('message_reactions')
            ->where('direct_message_id', $message->id)
            ->where('user_id', $user->id)
            ->first();

        if ($emoji === '' || ($existing !== null && $existing->emoji === $emoji)) {
            DB::table('message_reactions')
                ->where('direct_message_id', $message->id)
                ->where('user_id', $user->id)
                ->delete();
        } else {
            DB::table('message_reactions')->updateOrInsert(
                ['direct_message_id' => $message->id, 'user_id' => $user->id],
                ['emoji' => $emoji, 'updated_at' => now(), 'created_at' => now()],
            );
        }

        // Everyone in the thread should see it land, not on their next poll.
        \App\Events\ConversationUpdated::dispatch($conversation->id, $user->id);

        return true;
    }

    /**
     * Forward one message into another conversation the sender is also in.
     *
     * The forward is a NEW message from whoever forwarded it, carrying only the words — not a
     * pointer back to where it came from. Following such a pointer would expose a thread the
     * reader may have no business seeing; "this isn't mine" is the honest half of a forwarded
     * label, and it's the half that ships.
     *
     * Returns the new message, or null when the source is gone or the target isn't theirs.
     */
    public function forward(User $sender, int $messageId, int $toConversationId): ?DirectMessage
    {
        $source = DirectMessage::query()->whereKey($messageId)->whereNull('deleted_at')->first();
        if ($source === null) {
            return null;
        }

        // They must be in BOTH: the one they are copying from, and the one they are copying to.
        $inSource = DB::table('conversation_participants')
            ->where('conversation_id', $source->conversation_id)
            ->where('user_id', $sender->id)
            ->exists();
        $target = Conversation::query()->whereKey($toConversationId)->first();
        $inTarget = $target !== null && DB::table('conversation_participants')
            ->where('conversation_id', $target->id)
            ->where('user_id', $sender->id)
            ->exists();

        if (! $inSource || ! $inTarget) {
            return null;
        }

        return $this->sendAndBroadcast($target, $sender, (string) $source->body, null, true);
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
