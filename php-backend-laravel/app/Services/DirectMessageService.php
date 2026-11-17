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
        $remaining = DB::transaction(function () use ($conversation, $user): int {
            $conversation->participants()->detach($user->id);

            $left = $conversation->participants()->count();
            if ($left === 0) {
                $conversation->delete();
            }

            return $left;
        });

        // Said in the thread, so nobody wonders why someone stopped answering. Written as the
        // leaver (they are the actor) even though they can no longer read it.
        if ($remaining > 0) {
            $this->systemLine($conversation, $user, ($user->name ?: 'A player').' left');
        }
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
        ?string $shareType = null,
        ?string $shareRef = null,
        ?array $image = null,
        ?string $kind = null,
    ): DirectMessage {
        return DB::transaction(function () use ($conversation, $sender, $body, $replyToId, $forwarded, $shareType, $shareRef, $image, $kind): DirectMessage {
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
                'share_type' => $shareType,
                'share_ref' => $shareRef,
                'image_path' => $image['path'] ?? null,
                'image_width' => $image['width'] ?? null,
                'image_height' => $image['height'] ?? null,
                'kind' => $kind,
            ]);

            // A card with no words still needs a line in the thread list — "Shared a match:
            // Lions vs Tigers" — built for the sender, who has just been allowed to share it.
            $preview = $body;
            if ($image !== null) {
                $preview = $body !== '' ? '📷 '.$body : '📷 Photo';
            } elseif ($preview === '' && $shareType !== null && $shareRef !== null) {
                $preview = \App\Support\ChatShareCards::preview(
                    $shareType,
                    \App\Support\ChatShareCards::resolve($shareType, $shareRef, $sender),
                );
            }

            $conversation->forceFill([
                'last_message_at' => $message->created_at,
                'last_message_preview' => mb_substr($preview, 0, 200),
                'last_sender_id' => $sender->id,
            ])->save();

            // Everyone except the sender gains an unread; the sender is caught up by
            // definition. A system line ("Ravi added Kiran") is news, not a message — it
            // shows in the thread but doesn't light the badge.
            if ($kind === null) {
                DB::table('conversation_participants')
                    ->where('conversation_id', $conversation->id)
                    ->where('user_id', '!=', $sender->id)
                    ->increment('unread_count');
            }

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
        ?string $shareType = null,
        ?string $shareRef = null,
        ?array $image = null,
        ?string $kind = null,
    ): DirectMessage {
        $message = $this->send($conversation, $sender, $body, $replyToId, $forwarded, $shareType, $shareRef, $image, $kind);

        \App\Events\ConversationUpdated::dispatch($conversation->id, $sender->id);
        $this->broadcastInbox($conversation);

        // The phone in a pocket. After the response, so the sender's tick never waits on FCM;
        // the job itself decides who is muted and whether push is on at all.
        if ($kind === null) {
            \App\Jobs\SendChatPush::dispatchAfterResponse((int) $message->id);
        }

        return $message;
    }

    /** A line the app draws centred and grey: "Ravi added Kiran". Never pushed, never unread. */
    public function systemLine(Conversation $conversation, User $actor, string $text): void
    {
        try {
            $this->sendAndBroadcast($conversation, $actor, mb_substr($text, 0, 200), kind: 'system');
        } catch (\Throwable $e) {
            report($e);
        }
    }

    // ── Mute ──────────────────────────────────────────────────────────────────────────

    /**
     * Mute [$conversation] for [$user] until [$until] — null unmutes. "Until I turn it back
     * on" is stored as a far-future date so one column answers "is this muted right now".
     */
    public function setMute(Conversation $conversation, User $user, ?\DateTimeInterface $until): void
    {
        DB::table('conversation_participants')
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->update(['muted_until' => $until]);
    }

    /** When [$user]'s mute on this thread runs out, or null when it isn't muted now. */
    public function mutedUntil(Conversation $conversation, User $user): ?\Illuminate\Support\Carbon
    {
        $raw = DB::table('conversation_participants')
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->value('muted_until');
        if ($raw === null) {
            return null;
        }
        $until = \Illuminate\Support\Carbon::parse($raw);

        return $until->isFuture() ? $until : null;
    }

    // ── Groups ────────────────────────────────────────────────────────────────────────

    /**
     * The group's admin: whoever created it while they are still in it, otherwise the member
     * who has been there longest. Derived rather than stored as a role, so a group can never
     * be left with nobody able to manage it.
     */
    public function adminId(Conversation $conversation): ?int
    {
        $members = DB::table('conversation_participants')
            ->where('conversation_id', $conversation->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id);

        if ($conversation->created_by !== null && $members->contains((int) $conversation->created_by)) {
            return (int) $conversation->created_by;
        }

        return $members->first();
    }

    public function isAdmin(Conversation $conversation, User $user): bool
    {
        return $this->adminId($conversation) === (int) $user->id;
    }

    /**
     * Add players to a group. Each one must mutually follow the person adding them — the
     * same rule that makes an unwanted chat impossible by construction. Returns the users
     * actually added (already-members are skipped), or null when any one fails the rule or
     * the group would pass the /control size cap.
     *
     * @param  array<int, User>  $members
     * @return array<int, User>|null
     */
    public function addMembers(Conversation $conversation, User $actor, array $members): ?array
    {
        $existing = DB::table('conversation_participants')
            ->where('conversation_id', $conversation->id)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $fresh = [];
        foreach ($members as $m) {
            if (! in_array((int) $m->id, $existing, true) && (int) $m->id !== (int) $actor->id) {
                $fresh[(int) $m->id] = $m;
            }
        }
        if ($fresh === []) {
            return [];
        }

        if (count($existing) + count($fresh) > \App\Support\PlatformRules::int('chat.group_max_members')) {
            return null;
        }

        foreach ($fresh as $member) {
            if (! $this->canMessage($actor, $member)) {
                return null;
            }
        }

        $conversation->participants()->attach(array_keys($fresh));

        $names = collect($fresh)->map(fn (User $u) => $u->name ?: 'a player')->values()->all();
        $this->systemLine($conversation, $actor, ($actor->name ?: 'Someone').' added '.self::joinNames($names));

        return array_values($fresh);
    }

    /** Remove one member. Admin only — checked by the caller, so the reason can be said. */
    public function removeMember(Conversation $conversation, User $actor, User $member): void
    {
        $conversation->participants()->detach($member->id);
        $this->systemLine($conversation, $actor, ($actor->name ?: 'The admin').' removed '.($member->name ?: 'a player'));
    }

    public function renameGroup(Conversation $conversation, User $actor, string $title): void
    {
        $title = mb_substr(trim($title), 0, 80);
        if ($title === '' || $title === $conversation->title) {
            return;
        }
        $conversation->forceFill(['title' => $title])->save();
        $this->systemLine($conversation, $actor, ($actor->name ?: 'Someone').' renamed the group to “'.$title.'”');
    }

    public function setGroupPhoto(Conversation $conversation, User $actor, string $path): void
    {
        $old = $conversation->avatar;
        $conversation->forceFill(['avatar' => $path])->save();
        if ($old !== null && ! str_starts_with($old, 'http')) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($old);
        }
        $this->systemLine($conversation, $actor, ($actor->name ?: 'Someone').' changed the group photo');
    }

    /** "Kiran", "Kiran and Asha", "Kiran, Asha and 2 others". */
    private static function joinNames(array $names): string
    {
        $n = count($names);

        return match (true) {
            $n === 0 => 'nobody',
            $n === 1 => $names[0],
            $n === 2 => $names[0].' and '.$names[1],
            $n === 3 => $names[0].', '.$names[1].' and '.$names[2],
            default => $names[0].', '.$names[1].' and '.($n - 2).' others',
        };
    }

    /**
     * The public channel this user's chat list listens on — or null when the live inbox is
     * switched off in /control, which tells the app not to subscribe at all.
     *
     * An HMAC of the id under the app key, not the id itself: the channel is public (Reverb
     * has no client auth here), so a guessable name would let anyone watch when someone else
     * gets messages. Truncated to 32 hex chars, which is still far beyond guessing.
     */
    public static function inboxChannel(User $user): ?string
    {
        if (! \App\Support\PlatformRules::bool('chat.live_inbox')) {
            return null;
        }

        return 'inbox.'.substr(hash_hmac('sha256', 'dm-inbox:'.$user->id, (string) config('app.key')), 0, 32);
    }

    /**
     * Nudge every member's inbox. Sent to the sender too: their other phone's list should
     * move as well, and their own list re-sorts the thread to the top.
     */
    public function broadcastInbox(Conversation $conversation): void
    {
        if (! \App\Support\PlatformRules::bool('chat.live_inbox')) {
            return;
        }

        $channels = User::query()
            ->whereIn('id', DB::table('conversation_participants')
                ->where('conversation_id', $conversation->id)
                ->pluck('user_id'))
            ->get()
            ->map(fn (User $u) => (string) self::inboxChannel($u))
            ->filter()
            ->values()
            ->all();

        if ($channels !== []) {
            // A socket that is down must never fail the send that triggered it.
            try {
                \App\Events\InboxUpdated::dispatch($channels);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    /**
     * Tell the other members that [$user] is typing here.
     *
     * Throttled server-side to one signal per person per thread every 3 seconds, whatever the
     * client sends — a phone that pings on every keystroke must not turn into a broadcast
     * storm. Returns false when the signal was dropped (switched off, or throttled).
     */
    public function typing(Conversation $conversation, User $user): bool
    {
        if (! \App\Support\PlatformRules::bool('chat.typing_indicator')) {
            return false;
        }

        $key = "dm-typing:{$conversation->id}:{$user->id}";
        if (! \Illuminate\Support\Facades\Cache::add($key, 1, now()->addSeconds(3))) {
            return false;
        }

        // The other members' inboxes, so their chat list can say "typing…" too. Never the
        // typist's own: their list has nothing to show for it.
        $channels = User::query()
            ->whereIn('id', DB::table('conversation_participants')
                ->where('conversation_id', $conversation->id)
                ->where('user_id', '!=', $user->id)
                ->pluck('user_id'))
            ->get()
            ->map(fn (User $u) => (string) self::inboxChannel($u))
            ->filter()
            ->values()
            ->all();

        try {
            \App\Events\ConversationTyping::dispatch((int) $conversation->id, (int) $user->id, $channels);
        } catch (\Throwable $e) {
            report($e);
        }

        return true;
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

        if ($message === null || (int) $message->sender_id !== (int) $user->id || $message->kind !== null) {
            return false;
        }

        // An unsent photo is deleted from storage too — "unsent" means gone, not merely hidden.
        // Unless a forward still points at the same file.
        $image = $message->image_path;
        $message->forceFill(['body' => '', 'deleted_at' => now(), 'image_path' => null])->save();
        if ($image !== null && ! DirectMessage::query()->where('image_path', $image)->exists()) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($image);
        }

        // The preview on the thread list quotes the last message; if that was this one, it
        // has to stop quoting it.
        if ((int) ($conversation->last_sender_id ?? 0) === (int) $user->id) {
            $latest = $conversation->messages()->whereNull('deleted_at')->orderByDesc('id')->first();
            $conversation->forceFill([
                'last_message_preview' => $latest !== null ? \App\Jobs\SendChatPush::preview($latest) : 'Message deleted',
                'last_message_at' => $latest?->created_at ?? $conversation->last_message_at,
                'last_sender_id' => $latest?->sender_id ?? $conversation->last_sender_id,
            ])->save();
        }

        \App\Events\ConversationUpdated::dispatch($conversation->id, $user->id);
        $this->broadcastInbox($conversation);

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
        $source = DirectMessage::query()->whereKey($messageId)->whereNull('deleted_at')->whereNull('kind')->first();
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

        return $this->sendAndBroadcast(
            $target,
            $sender,
            (string) $source->body,
            null,
            true,
            $source->share_type,
            $source->share_ref,
            // A forwarded photo points at the same stored file — copying bytes per forward
            // would multiply storage for nothing. Unsend only deletes it when nothing else uses it.
            $source->image_path !== null
                ? ['path' => $source->image_path, 'width' => $source->image_width, 'height' => $source->image_height]
                : null,
        );
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
