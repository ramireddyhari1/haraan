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
        ?array $audio = null,
    ): DirectMessage {
        return DB::transaction(function () use ($conversation, $sender, $body, $replyToId, $forwarded, $shareType, $shareRef, $image, $kind, $audio): DirectMessage {
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
                'audio_path' => $audio['path'] ?? null,
                'audio_duration_ms' => $audio['duration_ms'] ?? null,
            ]);

            // A card with no words still needs a line in the thread list — "Shared a match:
            // Lions vs Tigers" — built for the sender, who has just been allowed to share it.
            $preview = $body;
            if ($audio !== null) {
                $preview = '🎤 Voice message ('.self::clock((int) ($audio['duration_ms'] ?? 0)).')';
            } elseif ($kind === 'poll') {
                $preview = '📊 '.$body;
            } elseif ($image !== null) {
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
            if ($kind !== 'system') {
                DB::table('conversation_participants')
                    ->where('conversation_id', $conversation->id)
                    ->where('user_id', '!=', $sender->id)
                    ->increment('unread_count');
            }

            DB::table('conversation_participants')
                ->where('conversation_id', $conversation->id)
                ->where('user_id', $sender->id)
                ->update(['unread_count' => 0, 'last_read_at' => now()]);

            // A new message brings an archived thread back to the inbox — unless the reader
            // muted it, which is exactly the "keep this out of my way" they asked for.
            if ($kind !== 'system') {
                DB::table('conversation_participants')
                    ->where('conversation_id', $conversation->id)
                    ->where('user_id', '!=', $sender->id)
                    ->whereNotNull('archived_at')
                    ->where(fn ($q) => $q->whereNull('muted_until')->orWhere('muted_until', '<', now()))
                    ->update(['archived_at' => null]);
            }

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
        ?array $audio = null,
    ): DirectMessage {
        $message = $this->send($conversation, $sender, $body, $replyToId, $forwarded, $shareType, $shareRef, $image, $kind, $audio);

        \App\Events\ConversationUpdated::dispatch($conversation->id, $sender->id);
        $this->broadcastInbox($conversation);

        // The phone in a pocket. After the response, so the sender's tick never waits on FCM;
        // the job itself decides who is muted and whether push is on at all.
        if ($kind !== 'system') {
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

    // ── This person's list: pin, archive, delete chat ───────────────────────────────

    /** This viewer's own row in a conversation (pin, archive, mute, clear line). */
    public function participantRow(Conversation $conversation, User $user): ?object
    {
        return DB::table('conversation_participants')
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->first();
    }

    /** Pin or unpin. False when pinning would pass the /control cap. */
    public function setPinned(Conversation $conversation, User $user, bool $pinned): bool
    {
        if ($pinned) {
            $already = DB::table('conversation_participants')
                ->where('user_id', $user->id)
                ->whereNotNull('pinned_at')
                ->where('conversation_id', '!=', $conversation->id)
                ->count();
            if ($already >= \App\Support\PlatformRules::int('chat.max_pins')) {
                return false;
            }
        }

        DB::table('conversation_participants')
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->update(['pinned_at' => $pinned ? now() : null]);

        return true;
    }

    public function setArchived(Conversation $conversation, User $user, bool $archived): void
    {
        DB::table('conversation_participants')
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            // An archived thread isn't pinned — the two would fight over where it sits.
            ->update($archived ? ['archived_at' => now(), 'pinned_at' => null] : ['archived_at' => null]);
    }

    /**
     * "Delete chat" for [$user] only: everything up to now disappears from their view and the
     * thread leaves their list until someone writes again. The other side keeps theirs.
     */
    public function clearFor(Conversation $conversation, User $user): void
    {
        $latest = (int) ($conversation->messages()->max('id') ?? 0);
        DB::table('conversation_participants')
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->update([
                'cleared_upto_id' => $latest,
                'unread_count' => 0,
                'last_read_at' => now(),
                'pinned_at' => null,
                'archived_at' => null,
            ]);
    }

    // ── Editing ─────────────────────────────────────────────────────────────────────

    /**
     * Correct your own message within the /control edit window. Only words can change: a
     * photo's caption may, a forwarded line or a system line may not (they aren't yours to
     * reword). Returns false when any rule says no.
     */
    public function edit(Conversation $conversation, User $user, int $messageId, string $body): bool
    {
        $window = \App\Support\PlatformRules::int('chat.edit_window_minutes');
        $body = trim($body);
        if ($window <= 0 || $body === '') {
            return false;
        }

        $message = $conversation->messages()->whereKey($messageId)->whereNull('deleted_at')->first();
        if (
            $message === null
            || (int) $message->sender_id !== (int) $user->id
            || $message->kind !== null
            || $message->audio_path !== null
            || $message->is_forwarded
            || ($message->share_type !== null && trim((string) $message->body) === '')
            || $message->created_at === null
            || $message->created_at->lt(now()->subMinutes($window))
        ) {
            return false;
        }
        if ($body === (string) $message->body) {
            return true;
        }

        $message->forceFill(['body' => mb_substr($body, 0, 2000), 'edited_at' => now()])->save();

        // The list quotes the last message; if this was it, quote the corrected words.
        $latest = $conversation->messages()->whereNull('deleted_at')->orderByDesc('id')->first();
        if ($latest !== null && (int) $latest->id === (int) $message->id) {
            $conversation->forceFill([
                'last_message_preview' => mb_substr(\App\Jobs\SendChatPush::preview($message), 0, 200),
            ])->save();
        }

        \App\Events\ConversationUpdated::dispatch($conversation->id, $user->id);
        $this->broadcastInbox($conversation);

        return true;
    }

    // ── Message requests ────────────────────────────────────────────────────────────

    public function isMutual(User $a, User $b): bool
    {
        return $a->isFollowing($b) && $b->isFollowing($a);
    }

    /**
     * May [$me] send [$them] a message REQUEST (they don't follow back)? Off unless /control
     * allows it, never to or from a guest, never across a block, never to a private profile,
     * and never again after they said no. /control can also require that the sender at least
     * follows them.
     */
    public function requestAllowed(User $me, User $them): bool
    {
        if (! \App\Support\PlatformRules::bool('chat.requests')) {
            return false;
        }
        if ($me->id === $them->id || $them->is_guest || $me->is_guest || User::blockExistsBetween($me, $them)) {
            return false;
        }
        // A private profile has said "people I don't know shouldn't reach me".
        if ($them->privacy_public_profile !== null && ! (bool) $them->privacy_public_profile) {
            return false;
        }
        if (\App\Support\PlatformRules::bool('chat.requests_need_follow') && ! $me->isFollowing($them)) {
            return false;
        }

        return ! DB::table('chat_request_declines')
            ->where('requester_id', $me->id)
            ->where('recipient_id', $them->id)
            ->exists();
    }

    /** The 1:1 between two people if one exists, whatever its request state. */
    public function existingOneToOne(User $a, User $b): ?Conversation
    {
        return Conversation::query()
            ->where('is_group', false)
            ->whereHas('participants', fn ($q) => $q->where('users.id', $a->id))
            ->whereHas('participants', fn ($q) => $q->where('users.id', $b->id))
            ->withCount('participants')
            ->get()
            ->firstWhere('participants_count', 2);
    }

    /**
     * Open a chat with [$them]: the ordinary 1:1 for mutual follows, an existing thread if
     * there is one, otherwise a new message REQUEST (their row 'pending'). Null = not allowed.
     */
    public function openOrRequest(User $me, User $them): ?Conversation
    {
        if (User::blockExistsBetween($me, $them)) {
            return null;
        }
        if ($this->canMessage($me, $them)) {
            return $this->conversationWith($me, $them);
        }
        $existing = $this->existingOneToOne($me, $them);
        if ($existing !== null) {
            return $existing;
        }
        if (! $this->requestAllowed($me, $them)) {
            return null;
        }

        return DB::transaction(function () use ($me, $them): Conversation {
            $conversation = Conversation::create([]);
            $conversation->participants()->attach([$me->id]);
            $conversation->participants()->attach([$them->id => ['request_status' => 'pending']]);

            return $conversation;
        });
    }

    /**
     * May [$me] post into [$conversation] right now? Groups: membership is the permission.
     * A 1:1: mutual follow, or an accepted request, or — while their side is still 'pending' —
     * a few opening messages (the /control limit). The recipient of a pending request must
     * accept before they can reply.
     */
    public function canSendIn(Conversation $conversation, User $me): bool
    {
        if ($conversation->is_group) {
            return true;
        }
        $conversation->loadMissing('participants');
        $them = $conversation->counterpart($me);
        if ($them === null || User::blockExistsBetween($me, $them)) {
            return false;
        }
        $rows = DB::table('conversation_participants')->where('conversation_id', $conversation->id)->get()->keyBy('user_id');
        $mine = $rows->get($me->id)?->request_status;
        $theirs = $rows->get($them->id)?->request_status;

        if ($mine === 'pending') {
            return false;
        }
        if ($mine === 'accepted' || $theirs === 'accepted') {
            return true;
        }
        if ($theirs === 'pending') {
            return $this->requestMessagesLeft($conversation, $me) > 0;
        }

        return $this->canMessage($me, $them);
    }

    /** How many more messages the requester may send before the request is answered. */
    public function requestMessagesLeft(Conversation $conversation, User $requester): int
    {
        $sent = $conversation->messages()->where('sender_id', $requester->id)->whereNull('kind')->count()
            + $conversation->messages()->where('sender_id', $requester->id)->where('kind', 'poll')->count();

        return max(0, \App\Support\PlatformRules::int('chat.request_max_messages') - $sent);
    }

    /** This viewer's request state in a 1:1: 'incoming' (they must answer), 'outgoing' (waiting), or null. */
    public function requestState(Conversation $conversation, User $me): ?string
    {
        if ($conversation->is_group) {
            return null;
        }
        $rows = DB::table('conversation_participants')->where('conversation_id', $conversation->id)->get();
        foreach ($rows as $row) {
            if ($row->request_status === 'pending') {
                return (int) $row->user_id === (int) $me->id ? 'incoming' : 'outgoing';
            }
        }

        return null;
    }

    public function acceptRequest(Conversation $conversation, User $me): bool
    {
        $done = DB::table('conversation_participants')
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $me->id)
            ->where('request_status', 'pending')
            ->update(['request_status' => 'accepted', 'unread_count' => 0, 'last_read_at' => now()]);
        if ($done > 0) {
            \App\Events\ConversationUpdated::dispatch($conversation->id, $me->id);
            $this->broadcastInbox($conversation);
        }

        return $done > 0;
    }

    /** Say no: the thread is deleted for both, and this person can't ask again. */
    public function declineRequest(Conversation $conversation, User $me): bool
    {
        $mine = $this->participantRow($conversation, $me);
        if ($mine === null || $mine->request_status !== 'pending') {
            return false;
        }
        $requester = $conversation->counterpart($me);
        DB::transaction(function () use ($conversation, $me, $requester): void {
            if ($requester !== null) {
                DB::table('chat_request_declines')->insertOrIgnore([
                    'requester_id' => $requester->id,
                    'recipient_id' => $me->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            foreach ($conversation->messages()->whereNotNull('image_path')->pluck('image_path') as $f) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($f);
            }
            foreach ($conversation->messages()->whereNotNull('audio_path')->pluck('audio_path') as $f) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($f);
            }
            $conversation->delete();
        });

        return true;
    }

    // ── Polls ───────────────────────────────────────────────────────────────────────

    /** @param array<int, string> $options */
    public function createPoll(Conversation $conversation, User $sender, string $question, array $options, bool $multiple): DirectMessage
    {
        $message = $this->sendAndBroadcast($conversation, $sender, mb_substr(trim($question), 0, 200), kind: 'poll');
        $pollId = DB::table('chat_polls')->insertGetId([
            'direct_message_id' => $message->id,
            'question' => mb_substr(trim($question), 0, 200),
            'allow_multiple' => $multiple,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        foreach (array_values($options) as $i => $label) {
            DB::table('chat_poll_options')->insert([
                'chat_poll_id' => $pollId,
                'label' => mb_substr(trim((string) $label), 0, 80),
                'position' => $i,
            ]);
        }
        // The thread was told before the options existed; tell it again now they do.
        \App\Events\ConversationUpdated::dispatch($conversation->id, $sender->id);

        return $message;
    }

    /**
     * Replace this person's vote with [$optionIds] (empty = take it back). A single-choice
     * poll takes the first id only. False when the poll isn't in this thread or was unsent.
     *
     * @param array<int, int> $optionIds
     */
    public function vote(Conversation $conversation, User $user, int $pollId, array $optionIds): bool
    {
        $poll = DB::table('chat_polls')
            ->join('direct_messages', 'direct_messages.id', '=', 'chat_polls.direct_message_id')
            ->where('chat_polls.id', $pollId)
            ->where('direct_messages.conversation_id', $conversation->id)
            ->whereNull('direct_messages.deleted_at')
            ->select('chat_polls.*')
            ->first();
        if ($poll === null) {
            return false;
        }
        $valid = DB::table('chat_poll_options')->where('chat_poll_id', $pollId)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $chosen = array_values(array_unique(array_filter(array_map('intval', $optionIds), fn ($id) => in_array($id, $valid, true))));
        if (! $poll->allow_multiple) {
            $chosen = array_slice($chosen, 0, 1);
        }

        DB::transaction(function () use ($pollId, $user, $chosen): void {
            DB::table('chat_poll_votes')->where('chat_poll_id', $pollId)->where('user_id', $user->id)->delete();
            foreach ($chosen as $optionId) {
                DB::table('chat_poll_votes')->insert([
                    'chat_poll_id' => $pollId,
                    'chat_poll_option_id' => $optionId,
                    'user_id' => $user->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
        \App\Events\ConversationUpdated::dispatch($conversation->id, $user->id);

        return true;
    }

    /**
     * The polls on these messages, built for [$viewer]: options with counts, whether each is
     * the viewer's, and (in a group) who voted for what — a team poll is only useful if you
     * can see who's in.
     *
     * @param array<int, int> $messageIds
     * @return array<int, array<string, mixed>> keyed by message id
     */
    public function pollsFor(array $messageIds, User $viewer, bool $withNames): array
    {
        if ($messageIds === []) {
            return [];
        }
        $polls = DB::table('chat_polls')->whereIn('direct_message_id', $messageIds)->get();
        if ($polls->isEmpty()) {
            return [];
        }
        $options = DB::table('chat_poll_options')->whereIn('chat_poll_id', $polls->pluck('id'))->orderBy('position')->get()->groupBy('chat_poll_id');
        $votes = DB::table('chat_poll_votes')->whereIn('chat_poll_id', $polls->pluck('id'))->get();
        $names = $withNames
            ? User::query()->whereIn('id', $votes->pluck('user_id')->unique())->pluck('name', 'id')
            : collect();

        $out = [];
        foreach ($polls as $poll) {
            $pollVotes = $votes->where('chat_poll_id', $poll->id);
            $out[(int) $poll->direct_message_id] = [
                'id' => (int) $poll->id,
                'question' => (string) $poll->question,
                'multiple' => (bool) $poll->allow_multiple,
                'voters' => $pollVotes->pluck('user_id')->unique()->count(),
                'options' => collect($options->get($poll->id, []))->map(function ($o) use ($pollVotes, $viewer, $names) {
                    $forThis = $pollVotes->where('chat_poll_option_id', $o->id);

                    return [
                        'id' => (int) $o->id,
                        'label' => (string) $o->label,
                        'votes' => $forThis->count(),
                        'mine' => $forThis->contains(fn ($v) => (int) $v->user_id === (int) $viewer->id),
                        'voter_names' => $names->isEmpty() ? [] : $forThis->map(fn ($v) => (string) ($names[$v->user_id] ?? 'Player'))->values()->all(),
                    ];
                })->values()->all(),
            ];
        }

        return $out;
    }

    // ── Team groups ─────────────────────────────────────────────────────────────────

    /**
     * The team chat for a match the viewer created: the existing one, or a new group of the
     * creator plus everyone they ACCEPTED into the match (those players asked to play, so
     * being added to the team chat is no surprise). Squad entries that resolve to real
     * accounts join only if they mutually follow the creator. Returns null when the match
     * isn't theirs or there's nobody to talk to.
     *
     * @return array{0: Conversation, 1: bool}|null [conversation, created now]
     */
    public function teamGroup(User $creator, int $matchId): ?array
    {
        $match = DB::table('live_matches')->where('id', $matchId)->first();
        if ($match === null || (int) $match->user_id !== (int) $creator->id) {
            return null;
        }

        $existing = Conversation::query()->where('match_id', $matchId)->where('is_group', true)->first();
        if ($existing !== null) {
            if (! DB::table('conversation_participants')->where('conversation_id', $existing->id)->where('user_id', $creator->id)->exists()) {
                $existing->participants()->attach([$creator->id]);
            }

            return [$existing, false];
        }

        $joined = DB::table('match_join_requests')
            ->where('match_id', $matchId)
            ->where('status', 'accepted')
            ->pluck('requester_id')
            ->map(fn ($id) => (int) $id);

        $squadIds = collect(array_merge(
            (array) (json_decode((string) ($match->home_squad ?? '[]'), true) ?: []),
            (array) (json_decode((string) ($match->away_squad ?? '[]'), true) ?: []),
        ))->pluck('id')->filter()->map(fn ($id) => (string) $id)->unique()->values();
        $fromSquad = $squadIds->isEmpty()
            ? collect()
            : User::query()->whereIn('player_id', $squadIds)->where('is_guest', false)->get()
                ->filter(fn (User $u) => $this->canMessage($creator, $u))
                ->pluck('id');

        $memberIds = $joined->merge($fromSquad)->unique()
            ->reject(fn ($id) => (int) $id === (int) $creator->id)
            ->values();
        $members = User::query()->whereIn('id', $memberIds)->where('is_guest', false)->get()
            ->reject(fn (User $u) => User::blockExistsBetween($creator, $u))
            ->take(\App\Support\PlatformRules::int('chat.group_max_members') - 1);
        if ($members->isEmpty()) {
            return null;
        }

        $title = trim(((string) ($match->home_full ?: $match->home ?? 'Team')).' vs '.((string) ($match->away_full ?: $match->away ?? 'Team')));
        $conversation = DB::transaction(function () use ($creator, $members, $title, $matchId): Conversation {
            $c = Conversation::create([
                'is_group' => true,
                'title' => mb_substr($title !== 'vs' ? $title : 'Match chat', 0, 80),
                'created_by' => $creator->id,
                'match_id' => $matchId,
            ]);
            $c->participants()->attach(array_merge([$creator->id], $members->pluck('id')->all()));

            return $c;
        });
        $this->systemLine($conversation, $creator, ($creator->name ?: 'The host').' made a team chat for this match');

        return [$conversation, true];
    }

    /** "0:07", "1:42". */
    public static function clock(int $ms): string
    {
        $s = (int) round($ms / 1000);

        return intdiv($s, 60).':'.str_pad((string) ($s % 60), 2, '0', STR_PAD_LEFT);
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

        if (DB::table('conversation_participants')->where('conversation_id', $conversation->id)->where('request_status', 'pending')->exists()) {
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
            ->where(fn ($q) => $q->whereNull('request_status')->orWhere('request_status', '!=', 'pending'))
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
        $audio = $message->audio_path;
        $message->forceFill(['body' => '', 'deleted_at' => now(), 'image_path' => null, 'audio_path' => null])->save();
        if ($image !== null && ! DirectMessage::query()->where('image_path', $image)->exists()) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($image);
        }
        if ($audio !== null && ! DirectMessage::query()->where('audio_path', $audio)->exists()) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($audio);
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

        if (! $inSource || ! $inTarget || $source->kind === 'poll' || ! $this->canSendIn($target, $sender)) {
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
            null,
            $source->audio_path !== null
                ? ['path' => $source->audio_path, 'duration_ms' => $source->audio_duration_ms]
                : null,
        );
    }

    /** Mark everything in [$conversation] read for [$user]. */
    public function markRead(Conversation $conversation, User $user): void
    {
        DB::table('conversation_participants')
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            // Reading a message REQUEST is private: the requester sees no "read" until it's accepted.
            ->where(fn ($q) => $q->whereNull('request_status')->orWhere('request_status', '!=', 'pending'))
            ->update(['unread_count' => 0, 'last_read_at' => now()]);
    }

    /** Total unread across every thread — the bottom-bar badge. */
    public function unreadTotal(User $user): int
    {
        return (int) DB::table('conversation_participants')
            ->where('user_id', $user->id)
            ->where(fn ($q) => $q->whereNull('request_status')->orWhere('request_status', '!=', 'pending'))
            ->sum('unread_count');
    }
}
