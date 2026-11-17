<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\User;
use App\Services\DirectMessageService;
use App\Support\ChatShareCards;
use App\Support\PlatformRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Player-to-player direct messages. Distinct from SupportController, which is the
 * player↔admin desk and models a different thing entirely.
 */
final class DirectMessageController extends Controller
{
    public function __construct(private readonly DirectMessageService $dm)
    {
    }

    /** GET /api/dm — every thread this player is in, newest activity first. */
    public function index(Request $request): JsonResponse
    {
        $me = $request->attributes->get('auth_user');
        if (! $me instanceof User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $conversations = Conversation::query()
            ->whereHas('participants', fn ($q) => $q->where('users.id', $me->id))
            ->with('participants')
            ->orderByDesc('last_message_at')
            ->limit(100)
            ->get();

        // This viewer's own row per thread (pin, archive, mute, "deleted up to"), in one read.
        $rows = DB::table('conversation_participants')
            ->where('user_id', $me->id)
            ->whereIn('conversation_id', $conversations->pluck('id'))
            ->get()
            ->keyBy('conversation_id');
        $latestIds = \App\Models\DirectMessage::query()
            ->whereIn('conversation_id', $conversations->pluck('id'))
            ->groupBy('conversation_id')
            ->selectRaw('conversation_id, max(id) as max_id')
            ->pluck('max_id', 'conversation_id');

        $conversations = $conversations
            // A chat this viewer deleted stays gone until someone writes again.
            ->filter(function (Conversation $c) use ($rows, $latestIds): bool {
                $row = $rows->get($c->id);

                return $row === null || $row->cleared_upto_id === null
                    || (int) ($latestIds[$c->id] ?? 0) > (int) $row->cleared_upto_id;
            })
            // Pinned first (most recently pinned on top), then by latest activity.
            ->sort(function (Conversation $a, Conversation $b) use ($rows): int {
                $pa = $rows->get($a->id)?->pinned_at;
                $pb = $rows->get($b->id)?->pinned_at;
                if (($pa === null) !== ($pb === null)) {
                    return $pa === null ? 1 : -1;
                }
                if ($pa !== null && $pa !== $pb) {
                    return strcmp((string) $pb, (string) $pa);
                }

                return ($b->last_message_at?->getTimestamp() ?? 0) <=> ($a->last_message_at?->getTimestamp() ?? 0);
            })
            ->values();

        // Their app now holds every message in these threads — that is what the second
        // tick means. Stamped here, NOT on opening a thread (that is `read`).
        $this->dm->markDelivered($me);

        return response()->json([
            'unread_total' => $this->dm->unreadTotal($me),
            // Where this player's chat list listens for "your inbox moved". Null when the
            // live inbox is off in /control — the app then simply doesn't subscribe.
            'inbox_channel' => DirectMessageService::inboxChannel($me),
            // Whether the + (share a match / venue / player) button should show.
            'share_cards' => PlatformRules::bool('chat.share_cards'),
            // Whether the photo button shows in a conversation.
            'photos' => PlatformRules::bool('chat.photos'),
            'max_pins' => PlatformRules::int('chat.max_pins'),
            'results' => $conversations->map(fn (Conversation $c) => $this->card($c, $me, $rows->get($c->id)))->values(),
        ]);
    }

    /**
     * POST /api/dm/with/{player} — open (or start) the 1:1 with a player.
     * 403 when the mutual-follow rule is not met, so the client can say why.
     */
    public function with(Request $request, string $playerId): JsonResponse
    {
        $me = $request->attributes->get('auth_user');
        if (! $me instanceof User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $them = User::query()->where('player_id', $playerId)->where('is_guest', false)->first();
        if (! $them instanceof User) {
            return response()->json(['error' => 'No player with that ID'], 404);
        }

        $conversation = $this->dm->conversationWith($me, $them);
        if ($conversation === null) {
            return response()->json([
                'error' => 'You can only message players you follow who also follow you.',
            ], 403);
        }

        return response()->json($this->card($conversation->load('participants'), $me), 201);
    }

    /** GET /api/dm/{id}/messages — the thread, oldest first so it reads top-down. */
    public function messages(Request $request, int $id): JsonResponse
    {
        $me = $request->attributes->get('auth_user');
        $conversation = $this->memberConversation($me, $id);
        if (! $conversation instanceof Conversation) {
            return $conversation ?? response()->json(['error' => 'Not found'], 404);
        }

        // Opening the thread IS reading it.
        $this->dm->markRead($conversation, $me);
        // "Delete chat" hid everything up to here, for this viewer only.
        $clearedUpto = (int) ($this->dm->participantRow($conversation, $me)?->cleared_upto_id ?? 0);

        // ?since_id= returns only what has arrived since the client last looked.
        //
        // The app polls this thread every few seconds while it is open; without this it
        // re-downloaded up to 300 messages each time and rebuilt the whole list, which
        // costs data on every tick and fights the reader's scroll position. With it, a
        // quiet thread answers with an empty array.
        $since = $request->query('since_id');
        $since = is_numeric($since) ? (int) $since : null;
        // ?before_id= pages BACKWARDS: the messages older than the oldest one the client holds,
        // for when the reader scrolls to the top.
        $before = $request->query('before_id');
        $before = is_numeric($before) ? (int) $before : null;

        if ($since !== null && $since > 0) {
            $messages = $conversation->messages()->where('id', '>', max($since, $clearedUpto))->orderBy('id')->limit(300)->get();
            $hasMore = null;
        } else {
            // The NEWEST page, read newest-first and flipped back to reading order.
            //
            // This used to be `orderBy('id')->limit(300)` — the OLDEST 300 — so a conversation
            // past 300 messages opened on its first week and never showed anything newer. A
            // build that asks for no `limit` still gets 300, just the latest 300.
            $limit = $request->has('limit')
                ? max(1, min((int) $request->query('limit'), PlatformRules::int('chat.page_size')))
                : 300;
            $query = $conversation->messages()->where('id', '>', $clearedUpto)->orderByDesc('id');
            if ($before !== null && $before > 0) {
                $query->where('id', '<', $before);
            }
            $page = $query->limit($limit + 1)->get();
            $hasMore = $page->count() > $limit;
            $messages = $page->take($limit)->reverse()->values();
        }

        // Who sent each line — needed so a GROUP thread can label incoming bubbles.
        // Resolved from a lookup (not the participant list) because a sender may have
        // since left the group while their messages remain.
        $senderIds = $messages->pluck('sender_id')->unique()->all();
        $senders = User::query()->whereIn('id', $senderIds)->get()->keyBy('id');

        // Reactions for this page in one query, grouped by message. Aggregated here rather
        // than stored as counters: forty hearts are forty rows, and a count that is computed
        // can never drift away from the rows it describes.
        $reactions = DB::table('message_reactions')
            ->whereIn('direct_message_id', $messages->pluck('id')->all())
            ->get()
            ->groupBy('direct_message_id');

        // The messages being quoted. Fetched by id rather than eager-loaded so a quote of a
        // message OLDER than this page still resolves — a reply to something from last month
        // must still show what it was replying to.
        $quotedIds = $messages->pluck('reply_to_id')->filter()->unique()->all();
        $quoted = $quotedIds === []
            ? collect()
            : \App\Models\DirectMessage::query()->whereIn('id', $quotedIds)->get()->keyBy('id');
        $quotedSenders = $quoted->isEmpty()
            ? collect()
            : User::query()->whereIn('id', $quoted->pluck('sender_id')->unique()->all())->get()->keyBy('id');

        // Shared cards for this page, one build per distinct thing — a match shared five times
        // in a busy group is read once.
        $cards = [];
        if (PlatformRules::bool('chat.share_cards')) {
            foreach ($messages as $m) {
                if ($m->share_type !== null && $m->deleted_at === null) {
                    $key = $m->share_type.':'.$m->share_ref;
                    $cards[$key] ??= ChatShareCards::resolve((string) $m->share_type, (string) $m->share_ref, $me);
                }
            }
        }

        // When the OTHER side last had these messages delivered and read. Sent back as two
        // timestamps rather than a status per message: the client compares them against each
        // message's own time, which is the same answer in a fraction of the payload.
        //
        // 1:1 only. A group has many recipients, and one tick that means "everyone" while
        // another means "somebody" is worse than no tick at all — so groups get null and the
        // app shows nothing.
        $counterpart = null;
        if (! $conversation->is_group) {
            $counterpart = DB::table('conversation_participants')
                ->where('conversation_id', $conversation->id)
                ->where('user_id', '!=', $me->id)
                ->first();
        }

        // "online" / "last seen" for a 1:1, from the heartbeat every signed-in request stamps
        // (throttled to one write per 5 minutes — so "online" means seen in the last 6).
        $lastSeen = null;
        if (! $conversation->is_group && PlatformRules::bool('chat.show_last_seen')) {
            $lastSeen = $conversation->counterpart($me)?->last_seen_at;
        }

        return response()->json([
            'is_group' => (bool) $conversation->is_group,
            'their_last_seen_at' => $lastSeen?->toIso8601String(),
            'their_online' => $lastSeen !== null && $lastSeen->gt(now()->subMinutes(6)),
            // How long after sending a message its author may still edit it (0 = never).
            'edit_window_minutes' => PlatformRules::int('chat.edit_window_minutes'),
            // Who is reading, so the client can ignore the echo of its own typing signal
            // (which arrives on the same public thread channel as everyone else's).
            'viewer_id' => (int) $me->id,
            // Whether this conversation shows and sends "typing…" — a /control switch.
            'typing_indicator' => PlatformRules::bool('chat.typing_indicator'),
            // Tells an appending client this was a delta, not the whole thread.
            'since_id' => $since,
            'before_id' => $before,
            // Whether anything older is left to load. Null on a since_id delta, which says
            // nothing about history.
            'has_more' => $hasMore,
            // ISO-8601 with an offset, NOT the raw column.
            //
            // A DB::table() read hands back "2026-08-20 10:14:05" — a UTC instant with
            // nothing saying so. The app parsed that as LOCAL time, which in IST made every
            // receipt 5.5 hours earlier than the message it belonged to, so a delivered
            // message kept showing a single tick. Timestamps crossing a wire need offsets.
            'their_delivered_at' => $counterpart?->last_delivered_at
                ? \Illuminate\Support\Carbon::parse($counterpart->last_delivered_at)->toIso8601String()
                : null,
            'their_read_at' => $counterpart?->last_read_at
                ? \Illuminate\Support\Carbon::parse($counterpart->last_read_at)->toIso8601String()
                : null,
            'title' => $conversation->title,
            'results' => $messages->map(function ($m) use ($me, $senders, $reactions, $quoted, $quotedSenders, $cards) {
                $sender = $senders->get($m->sender_id);

                return [
                    'id' => (int) $m->id,
                    'body' => $m->body,
                    'sender_id' => (int) $m->sender_id,
                    'sender_name' => $sender?->name,
                    'sender_avatar' => $sender?->avatar,
                    'mine' => (int) $m->sender_id === (int) $me->id,
                    'sent_at' => $m->created_at?->toIso8601String(),
                    // An unsent message keeps its place in the thread but carries no words.
                    'deleted' => $m->deleted_at !== null,
                    'forwarded' => (bool) $m->is_forwarded,
                    // Corrected by its author after sending — the bubble says so.
                    'edited' => $m->edited_at !== null && $m->deleted_at === null,
                    // 'system' for "Ravi added Kiran"; null for anything a person wrote.
                    'kind' => $m->kind,
                    // A photo, absolute, with its size so the bubble can hold the shape
                    // before the image arrives. Null once unsent.
                    'image' => $m->deleted_at === null && $m->image_path !== null
                        ? \App\Support\MediaUrl::resolve(Storage::disk('public')->url($m->image_path))
                        : null,
                    'image_width' => $m->image_width !== null ? (int) $m->image_width : null,
                    'image_height' => $m->image_height !== null ? (int) $m->image_height : null,
                    // A shared match / venue / player, built for THIS reader at read time.
                    // Null for plain text, for an unsent message, and while sharing is off.
                    'share' => $m->deleted_at === null && $m->share_type !== null
                        ? $cards[$m->share_type.':'.$m->share_ref] ?? null
                        : null,
                    // What this message is replying to, flattened for the bubble that draws
                    // it. Null when it replies to nothing, or when the quoted message is gone.
                    'reply_to' => $m->reply_to_id !== null
                        ? (function () use ($m, $quoted, $quotedSenders, $me): ?array {
                            $q = $quoted->get($m->reply_to_id);
                            if ($q === null) {
                                return null;
                            }

                            return [
                                'id' => (int) $q->id,
                                'body' => $q->deleted_at !== null ? '' : (string) $q->body,
                                'deleted' => $q->deleted_at !== null,
                                'sender_name' => $quotedSenders->get($q->sender_id)?->name,
                                'mine' => (int) $q->sender_id === (int) $me->id,
                            ];
                        })()
                        : null,
                    'reactions' => collect($reactions->get($m->id, collect()))
                        ->groupBy('emoji')
                        ->map(fn ($rows, $emoji) => [
                            'emoji' => (string) $emoji,
                            'count' => $rows->count(),
                            // So the client can show yours as selected without a second call.
                            'mine' => $rows->contains(fn ($r) => (int) $r->user_id === (int) $me->id),
                        ])
                        ->values(),
                ];
            })->values(),
        ]);
    }

    /**
     * DELETE /api/dm/{id}/messages/{message} — unsend your own message.
     *
     * Only the sender can, and only once: the service refuses anything already gone. There
     * is no time limit, deliberately — a limit would need a rule nobody has decided, and
     * "you may unsend what you wrote" is the promise the long-press menu makes.
     */
    public function unsend(Request $request, int $id, int $message): JsonResponse
    {
        $me = $request->attributes->get('auth_user');
        $conversation = $this->memberConversation($me, $id);
        if (! $conversation instanceof Conversation) {
            return $conversation ?? response()->json(['error' => 'Not found'], 404);
        }

        if (! $this->dm->unsend($conversation, $me, $message)) {
            return response()->json(['error' => 'That message cannot be unsent.'], 422);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * POST /api/dm/{id}/messages/{message}/reaction — react, or take it back.
     *
     * Sending the emoji you already chose clears it, so the client needs no separate delete.
     */
    public function react(Request $request, int $id, int $message): JsonResponse
    {
        $me = $request->attributes->get('auth_user');
        $conversation = $this->memberConversation($me, $id);
        if (! $conversation instanceof Conversation) {
            return $conversation ?? response()->json(['error' => 'Not found'], 404);
        }

        $data = $request->validate([
            // Emoji only, and one of them: this is a reaction row, not a comment field.
            'emoji' => ['nullable', 'string', 'max:16'],
        ]);

        if (! $this->dm->react($conversation, $me, $message, (string) ($data['emoji'] ?? ''))) {
            return response()->json(['error' => 'That message cannot be reacted to.'], 422);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * POST /api/dm/messages/{message}/forward — copy a message into another of your threads.
     *
     * The body is `{ to: <conversation id> }`. Both ends are checked in the service: you must
     * be in the thread you are copying FROM and the one you are copying TO.
     */
    public function forward(Request $request, int $message): JsonResponse
    {
        $me = $request->attributes->get('auth_user');
        if (! $me instanceof User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $data = $request->validate([
            'to' => ['required', 'integer'],
        ]);

        $sent = $this->dm->forward($me, $message, (int) $data['to']);
        if ($sent === null) {
            return response()->json(['error' => 'That message cannot be forwarded there.'], 422);
        }

        return response()->json(['ok' => true, 'id' => (int) $sent->id], 201);
    }

    /**
     * POST /api/dm/group — create a group with a title and a set of member player IDs.
     * 403 when any member is not a mutual follow (the same rule as 1:1, per person).
     */
    public function group(Request $request): JsonResponse
    {
        $me = $request->attributes->get('auth_user');
        if (! $me instanceof User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:80'],
            'members' => ['required', 'array', 'min:1', 'max:50'],
            'members.*' => ['string'],
        ]);

        $members = User::query()
            ->whereIn('player_id', $data['members'])
            ->where('is_guest', false)
            ->get()
            ->all();

        if ($members === []) {
            return response()->json(['error' => 'None of those players could be found.'], 422);
        }

        if (count($members) + 1 > PlatformRules::int('chat.group_max_members')) {
            return response()->json([
                'error' => 'A group can have up to '.PlatformRules::int('chat.group_max_members').' people.',
            ], 422);
        }

        $conversation = $this->dm->createGroup($me, $data['title'], $members);
        if ($conversation === null) {
            return response()->json([
                'error' => 'You can only add players who follow you back.',
            ], 403);
        }

        return response()->json($this->card($conversation->load('participants'), $me), 201);
    }

    /** POST /api/dm/{id}/leave — leave a group. No-op-shaped 422 for a 1:1. */
    public function leave(Request $request, int $id): JsonResponse
    {
        $me = $request->attributes->get('auth_user');
        if (! $me instanceof User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $conversation = $this->memberConversation($me, $id);
        if (! $conversation instanceof Conversation) {
            return $conversation ?? response()->json(['error' => 'Not found'], 404);
        }

        if (! $conversation->is_group) {
            return response()->json(['error' => 'This is not a group.'], 422);
        }

        $this->dm->leaveGroup($conversation, $me);

        return response()->json(['left' => true]);
    }

    /**
     * GET /api/dm/eligible — players this user may start a chat or group with: the
     * mutual follows. This is the honest contents of a member picker.
     */
    public function eligible(Request $request): JsonResponse
    {
        $me = $request->attributes->get('auth_user');
        if (! $me instanceof User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $followingIds = $me->following()->pluck('users.id');
        $mutuals = $me->followers()
            ->whereIn('users.id', $followingIds)
            ->where('is_guest', false)
            ->orderBy('name')
            ->get();

        return response()->json([
            'results' => $mutuals->map(fn (User $u) => [
                'player_id' => $u->player_id,
                'name' => $u->name,
                'username' => $u->username,
                'avatar' => $u->avatar,
            ])->values(),
        ]);
    }

    /** POST /api/dm/{id}/messages */
    public function send(Request $request, int $id): JsonResponse
    {
        $me = $request->attributes->get('auth_user');
        $conversation = $this->memberConversation($me, $id);
        if (! $conversation instanceof Conversation) {
            return $conversation ?? response()->json(['error' => 'Not found'], 404);
        }

        $data = $request->validate([
            // Words are optional only when the message carries a card.
            'body' => ['required_without:share', 'nullable', 'string', 'max:2000'],
            // Quoting another message in this thread. Membership and same-thread
            // are enforced in the service, not here.
            'reply_to_id' => ['nullable', 'integer'],
            'share' => ['nullable', 'array'],
            'share.type' => ['required_with:share', 'string', 'in:'.implode(',', ChatShareCards::TYPES)],
            'share.id' => ['required_with:share', 'string', 'max:64'],
        ]);

        $shareType = $data['share']['type'] ?? null;
        $shareRef = isset($data['share']['id']) ? trim((string) $data['share']['id']) : null;
        if ($shareType !== null) {
            if (! PlatformRules::bool('chat.share_cards')) {
                return response()->json(['error' => 'Sharing into chat is switched off right now.'], 403);
            }
            // You can share only what you can see — so nobody can push a private match's
            // card into a thread by guessing its id.
            if (! ChatShareCards::canShare($shareType, (string) $shareRef, $me)) {
                return response()->json(['error' => 'That cannot be shared.'], 422);
            }
        }
        $body = trim((string) ($data['body'] ?? ''));
        if ($body === '' && $shareType === null) {
            return response()->json(['error' => 'Write a message first.'], 422);
        }

        // Re-check on every send, not just at thread creation: an unfollow must stop a
        // 1:1, otherwise a thread opened while mutual stays open forever. A GROUP is
        // governed by membership instead — being in it (verified above) is the
        // permission, so unfollowing one member doesn't mute the room.
        if (! $conversation->is_group) {
            $other = $conversation->counterpart($me);
            if ($other !== null && ! $this->dm->canMessage($me, $other)) {
                return response()->json([
                    'error' => 'You can no longer message this player.',
                ], 403);
            }
        }

        $message = $this->dm->sendAndBroadcast(
            $conversation,
            $me,
            $body,
            isset($data['reply_to_id']) ? (int) $data['reply_to_id'] : null,
            false,
            $shareType,
            $shareRef,
        );

        return response()->json([
            'id' => (int) $message->id,
            'body' => $message->body,
            'sender_id' => (int) $message->sender_id,
            'mine' => true,
            'sent_at' => $message->created_at?->toIso8601String(),
            'share' => $shareType !== null ? ChatShareCards::resolve($shareType, (string) $shareRef, $me) : null,
        ], 201);
    }

    /** POST /api/dm/{id}/pin — `{ pinned: bool }`. 422 past the /control pin cap. */
    public function pin(Request $request, int $id): JsonResponse
    {
        $me = $request->attributes->get('auth_user');
        $conversation = $this->memberConversation($me, $id);
        if (! $conversation instanceof Conversation) {
            return $conversation ?? response()->json(['error' => 'Not found'], 404);
        }
        $data = $request->validate(['pinned' => ['required', 'boolean']]);
        if (! $this->dm->setPinned($conversation, $me, (bool) $data['pinned'])) {
            return response()->json([
                'error' => 'You can pin up to '.PlatformRules::int('chat.max_pins').' chats. Unpin one first.',
            ], 422);
        }

        return response()->json(['pinned' => (bool) $data['pinned']]);
    }

    /** POST /api/dm/{id}/archive — `{ archived: bool }`. A new message brings it back. */
    public function archive(Request $request, int $id): JsonResponse
    {
        $me = $request->attributes->get('auth_user');
        $conversation = $this->memberConversation($me, $id);
        if (! $conversation instanceof Conversation) {
            return $conversation ?? response()->json(['error' => 'Not found'], 404);
        }
        $data = $request->validate(['archived' => ['required', 'boolean']]);
        $this->dm->setArchived($conversation, $me, (bool) $data['archived']);

        return response()->json(['archived' => (bool) $data['archived']]);
    }

    /** POST /api/dm/{id}/clear — "Delete chat", for this viewer only. */
    public function clear(Request $request, int $id): JsonResponse
    {
        $me = $request->attributes->get('auth_user');
        $conversation = $this->memberConversation($me, $id);
        if (! $conversation instanceof Conversation) {
            return $conversation ?? response()->json(['error' => 'Not found'], 404);
        }
        $this->dm->clearFor($conversation, $me);

        return response()->json(['cleared' => true]);
    }

    /** POST /api/dm/{id}/messages/{message}/edit — `{ body }`. Sender only, inside the window. */
    public function edit(Request $request, int $id, int $message): JsonResponse
    {
        $me = $request->attributes->get('auth_user');
        $conversation = $this->memberConversation($me, $id);
        if (! $conversation instanceof Conversation) {
            return $conversation ?? response()->json(['error' => 'Not found'], 404);
        }
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);

        if (! $this->dm->edit($conversation, $me, $message, (string) $data['body'])) {
            $window = PlatformRules::int('chat.edit_window_minutes');

            return response()->json([
                'error' => $window > 0
                    ? "Messages can only be edited for {$window} minutes after sending."
                    : 'Editing messages is switched off.',
            ], 422);
        }

        return response()->json(['ok' => true, 'body' => trim((string) $data['body'])]);
    }

    /**
     * GET /api/dm/{id}/search?q= — this thread's messages containing q, newest first. Only
     * what this viewer can see: not unsent, not before their "Delete chat", not system lines.
     */
    public function search(Request $request, int $id): JsonResponse
    {
        $me = $request->attributes->get('auth_user');
        $conversation = $this->memberConversation($me, $id);
        if (! $conversation instanceof Conversation) {
            return $conversation ?? response()->json(['error' => 'Not found'], 404);
        }
        $q = trim(mb_substr((string) $request->query('q', ''), 0, 60));
        if (mb_strlen($q) < 2) {
            return response()->json(['results' => []]);
        }
        $clearedUpto = (int) ($this->dm->participantRow($conversation, $me)?->cleared_upto_id ?? 0);
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q).'%';

        $hits = $conversation->messages()
            ->where('id', '>', $clearedUpto)
            ->whereNull('deleted_at')
            ->whereNull('kind')
            ->where('body', 'like', $like)
            ->orderByDesc('id')
            ->limit(30)
            ->get();
        $senders = User::query()->whereIn('id', $hits->pluck('sender_id')->unique())->get()->keyBy('id');

        return response()->json([
            'results' => $hits->map(fn ($m) => [
                'id' => (int) $m->id,
                'body' => (string) $m->body,
                'sent_at' => $m->created_at?->toIso8601String(),
                'mine' => (int) $m->sender_id === (int) $me->id,
                'sender_name' => $senders->get($m->sender_id)?->name,
            ])->values(),
        ]);
    }

    /**
     * POST /api/dm/{id}/photo — a photo, with an optional caption and reply.
     *
     * Multipart (`image`, `body?`, `reply_to_id?`). The app shrinks photos before sending, so
     * the /control size cap is a guard, not the usual case. Stored under an unguessable name:
     * the file is served from the public disk, so the name is what keeps it private.
     */
    public function photo(Request $request, int $id): JsonResponse
    {
        $me = $request->attributes->get('auth_user');
        $conversation = $this->memberConversation($me, $id);
        if (! $conversation instanceof Conversation) {
            return $conversation ?? response()->json(['error' => 'Not found'], 404);
        }
        if (! PlatformRules::bool('chat.photos')) {
            return response()->json(['error' => 'Photos in chat are switched off right now.'], 403);
        }

        $maxKb = PlatformRules::int('chat.photo_max_mb') * 1024;
        $data = $request->validate([
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.$maxKb],
            'body' => ['nullable', 'string', 'max:2000'],
            'reply_to_id' => ['nullable', 'integer'],
        ]);

        if (! $conversation->is_group) {
            $other = $conversation->counterpart($me);
            if ($other !== null && ! $this->dm->canMessage($me, $other)) {
                return response()->json(['error' => 'You can no longer message this player.'], 403);
            }
        }

        $file = $request->file('image');
        $size = @getimagesize($file->getRealPath()) ?: [null, null];
        $path = $file->storeAs(
            'chat-photos/'.$conversation->id,
            Str::random(40).'.'.strtolower($file->guessExtension() ?: 'jpg'),
            'public',
        );
        if ($path === false) {
            return response()->json(['error' => 'Could not save the photo.'], 500);
        }

        $message = $this->dm->sendAndBroadcast(
            $conversation,
            $me,
            trim((string) ($data['body'] ?? '')),
            isset($data['reply_to_id']) ? (int) $data['reply_to_id'] : null,
            false,
            null,
            null,
            [
                'path' => $path,
                'width' => $size[0] ? min((int) $size[0], 65535) : null,
                'height' => $size[1] ? min((int) $size[1], 65535) : null,
            ],
        );

        return response()->json([
            'id' => (int) $message->id,
            'body' => $message->body,
            'sender_id' => (int) $message->sender_id,
            'mine' => true,
            'sent_at' => $message->created_at?->toIso8601String(),
            'image' => \App\Support\MediaUrl::resolve(Storage::disk('public')->url($path)),
            'image_width' => $message->image_width !== null ? (int) $message->image_width : null,
            'image_height' => $message->image_height !== null ? (int) $message->image_height : null,
        ], 201);
    }

    /**
     * POST /api/dm/{id}/mute — `{ hours: 8 | 168 | -1 }` mutes (-1 = until turned back on),
     * `{ hours: 0 }` unmutes. Per viewer: the other side is never told.
     */
    public function mute(Request $request, int $id): JsonResponse
    {
        $me = $request->attributes->get('auth_user');
        $conversation = $this->memberConversation($me, $id);
        if (! $conversation instanceof Conversation) {
            return $conversation ?? response()->json(['error' => 'Not found'], 404);
        }

        $data = $request->validate(['hours' => ['required', 'integer', 'min:-1', 'max:8760']]);
        $hours = (int) $data['hours'];
        $until = match (true) {
            $hours === 0 => null,
            $hours < 0 => now()->addYears(50),
            default => now()->addHours($hours),
        };
        $this->dm->setMute($conversation, $me, $until);

        return response()->json([
            'muted' => $until !== null,
            'muted_until' => $until !== null && $hours > 0 ? $until->toIso8601String() : null,
        ]);
    }

    /**
     * GET /api/dm/{id}/info — what the thread's info screen shows: the members (and who
     * administers a group), the viewer's mute, and what the viewer may change.
     */
    public function info(Request $request, int $id): JsonResponse
    {
        $me = $request->attributes->get('auth_user');
        $conversation = $this->memberConversation($me, $id);
        if (! $conversation instanceof Conversation) {
            return $conversation ?? response()->json(['error' => 'Not found'], 404);
        }

        $adminId = $conversation->is_group ? $this->dm->adminId($conversation) : null;
        $mutedUntil = $this->dm->mutedUntil($conversation, $me);
        // Admin first, then you, then everyone else by name.
        $members = $conversation->participants
            ->sortBy(fn (User $u) => sprintf(
                '%d%d%s',
                (int) $u->id === $adminId ? 0 : 1,
                (int) $u->id === (int) $me->id ? 0 : 1,
                mb_strtolower((string) $u->name),
            ))
            ->values();

        return response()->json([
            'id' => (int) $conversation->id,
            'is_group' => (bool) $conversation->is_group,
            'title' => $conversation->is_group ? $conversation->title : $conversation->counterpart($me)?->name,
            'avatar' => $conversation->is_group && $conversation->avatar !== null
                ? \App\Support\MediaUrl::resolve(Storage::disk('public')->url($conversation->avatar))
                : null,
            'muted' => $mutedUntil !== null,
            // Null for "until I turn it back on" (stored decades out) — the app just says "Muted".
            'muted_until' => $mutedUntil !== null && $mutedUntil->lessThan(now()->addYear())
                ? $mutedUntil->toIso8601String()
                : null,
            'is_admin' => $adminId !== null && $adminId === (int) $me->id,
            'max_members' => PlatformRules::int('chat.group_max_members'),
            'photos' => PlatformRules::bool('chat.photos'),
            'members' => $members->map(fn (User $u) => [
                'player_id' => $u->player_id,
                'name' => $u->name,
                'username' => $u->username,
                'avatar' => $u->avatar,
                'is_admin' => (int) $u->id === $adminId,
                'is_me' => (int) $u->id === (int) $me->id,
            ])->values(),
        ]);
    }

    /** POST /api/dm/{id}/rename — `{ title }`. Any member of a group may rename it. */
    public function rename(Request $request, int $id): JsonResponse
    {
        $me = $request->attributes->get('auth_user');
        $conversation = $this->memberConversation($me, $id);
        if (! $conversation instanceof Conversation) {
            return $conversation ?? response()->json(['error' => 'Not found'], 404);
        }
        if (! $conversation->is_group) {
            return response()->json(['error' => 'This is not a group.'], 422);
        }

        $data = $request->validate(['title' => ['required', 'string', 'max:80']]);
        if (trim($data['title']) === '') {
            return response()->json(['error' => 'Give the group a name.'], 422);
        }
        $this->dm->renameGroup($conversation, $me, $data['title']);

        return response()->json(['title' => $conversation->fresh()?->title]);
    }

    /**
     * POST /api/dm/{id}/members — `{ members: [player ids] }`. Any member may add people who
     * mutually follow THEM; 403 when one doesn't, 422 past the /control size cap.
     */
    public function addMembers(Request $request, int $id): JsonResponse
    {
        $me = $request->attributes->get('auth_user');
        $conversation = $this->memberConversation($me, $id);
        if (! $conversation instanceof Conversation) {
            return $conversation ?? response()->json(['error' => 'Not found'], 404);
        }
        if (! $conversation->is_group) {
            return response()->json(['error' => 'This is not a group.'], 422);
        }

        $data = $request->validate([
            'members' => ['required', 'array', 'min:1', 'max:50'],
            'members.*' => ['string'],
        ]);
        $members = User::query()->whereIn('player_id', $data['members'])->where('is_guest', false)->get()->all();
        if ($members === []) {
            return response()->json(['error' => 'None of those players could be found.'], 422);
        }

        $currentCount = $conversation->participants->count();
        $added = $this->dm->addMembers($conversation, $me, $members);
        if ($added === null) {
            $cap = PlatformRules::int('chat.group_max_members');

            return $currentCount + count($members) > $cap
                ? response()->json(['error' => "A group can have up to {$cap} people."], 422)
                : response()->json(['error' => 'You can only add players who follow you back.'], 403);
        }

        return response()->json(['added' => count($added)]);
    }

    /** DELETE /api/dm/{id}/members/{playerId} — remove someone. Group admin only. */
    public function removeMember(Request $request, int $id, string $playerId): JsonResponse
    {
        $me = $request->attributes->get('auth_user');
        $conversation = $this->memberConversation($me, $id);
        if (! $conversation instanceof Conversation) {
            return $conversation ?? response()->json(['error' => 'Not found'], 404);
        }
        if (! $conversation->is_group) {
            return response()->json(['error' => 'This is not a group.'], 422);
        }
        if (! $this->dm->isAdmin($conversation, $me)) {
            return response()->json(['error' => 'Only the group admin can remove people.'], 403);
        }

        $member = $conversation->participants->firstWhere('player_id', $playerId);
        if (! $member instanceof User) {
            return response()->json(['error' => 'They are not in this group.'], 404);
        }
        if ((int) $member->id === (int) $me->id) {
            return response()->json(['error' => 'Use Leave group to leave.'], 422);
        }

        $this->dm->removeMember($conversation, $me, $member);

        return response()->json(['removed' => true]);
    }

    /** POST /api/dm/{id}/avatar — multipart `image`. Any member may change a group's photo. */
    public function avatar(Request $request, int $id): JsonResponse
    {
        $me = $request->attributes->get('auth_user');
        $conversation = $this->memberConversation($me, $id);
        if (! $conversation instanceof Conversation) {
            return $conversation ?? response()->json(['error' => 'Not found'], 404);
        }
        if (! $conversation->is_group) {
            return response()->json(['error' => 'This is not a group.'], 422);
        }

        $request->validate([
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.(PlatformRules::int('chat.photo_max_mb') * 1024)],
        ]);
        $file = $request->file('image');
        $path = $file->storeAs('chat-groups', Str::random(40).'.'.strtolower($file->guessExtension() ?: 'jpg'), 'public');
        if ($path === false) {
            return response()->json(['error' => 'Could not save the photo.'], 500);
        }
        $this->dm->setGroupPhoto($conversation, $me, $path);

        return response()->json(['avatar' => \App\Support\MediaUrl::resolve(Storage::disk('public')->url($path))]);
    }

    /**
     * POST /api/dm/{id}/typing — "I'm typing in this thread." No body: nothing typed is sent.
     * 202 whether it was broadcast or throttled; the client just keeps typing.
     */
    public function typing(Request $request, int $id): JsonResponse
    {
        $me = $request->attributes->get('auth_user');
        $conversation = $this->memberConversation($me, $id);
        if (! $conversation instanceof Conversation) {
            return $conversation ?? response()->json(['error' => 'Not found'], 404);
        }

        return response()->json(['sent' => $this->dm->typing($conversation, $me)], 202);
    }

    /**
     * GET /api/dm/share/search?type=match|venue|player&q= — what the share picker lists.
     * Every row is a finished card, so the picker shows exactly what the chat will.
     */
    public function shareSearch(Request $request): JsonResponse
    {
        $me = $request->attributes->get('auth_user');
        if (! $me instanceof User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        if (! PlatformRules::bool('chat.share_cards')) {
            return response()->json(['results' => []]);
        }

        $type = (string) $request->query('type', '');
        if (! in_array($type, ChatShareCards::TYPES, true)) {
            return response()->json(['error' => 'Unknown type'], 422);
        }

        return response()->json([
            'results' => ChatShareCards::search($type, mb_substr((string) $request->query('q', ''), 0, 60), $me),
        ]);
    }

    /**
     * GET /api/dm/share/card?type=&id= — one card, fresh. A LIVE match card on screen asks
     * for this while it is visible, so the score in the chat moves with the match.
     */
    public function shareCard(Request $request): JsonResponse
    {
        $me = $request->attributes->get('auth_user');
        if (! $me instanceof User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $type = (string) $request->query('type', '');
        $card = PlatformRules::bool('chat.share_cards')
            ? ChatShareCards::resolve($type, mb_substr((string) $request->query('id', ''), 0, 64), $me)
            : null;

        return $card === null
            ? response()->json(['error' => 'Not found'], 404)
            : response()->json($card);
    }

    /**
     * The conversation, if this user is in it. Returns a JsonResponse to bounce with,
     * or null when it simply does not exist.
     */
    private function memberConversation(mixed $me, int $id): Conversation|JsonResponse|null
    {
        if (! $me instanceof User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $conversation = Conversation::query()->with('participants')->find($id);
        if ($conversation === null) {
            return null;
        }

        // Membership is the authorisation. A conversation id is guessable, so this
        // check is the only thing standing between a stranger and someone's messages.
        if (! $conversation->participants->contains('id', $me->id)) {
            return response()->json(['error' => 'Not found'], 404);
        }

        return $conversation;
    }

    /** @return array<string, mixed> */
    private function card(Conversation $c, User $me, ?object $row = null): array
    {
        $mine = $c->participants->firstWhere('id', $me->id);
        $row ??= $this->dm->participantRow($c, $me);
        $mutedUntil = $row?->muted_until !== null ? \Illuminate\Support\Carbon::parse($row->muted_until) : null;

        $base = [
            'id' => (int) $c->id,
            'is_group' => (bool) $c->is_group,
            'last_message' => $c->last_message_preview,
            'last_message_at' => $c->last_message_at?->toIso8601String(),
            'unread_count' => (int) ($mine?->pivot?->unread_count ?? 0),
            // Muted by THIS viewer right now — the row shows a bell-off and the phone stays quiet.
            'muted' => $mutedUntil !== null && $mutedUntil->isFuture(),
            // This viewer's own list housekeeping; nobody else is told.
            'pinned' => $row?->pinned_at !== null,
            'archived' => $row?->archived_at !== null,
        ];

        if ($c->is_group) {
            // A group's identity is its title; the row stacks a few member avatars in
            // place of the single counterpart photo a 1:1 shows.
            $others = $c->others($me);

            return array_merge($base, [
                'player_id' => null,
                'name' => $c->title,
                'username' => null,
                'avatar' => $c->avatar !== null
                    ? \App\Support\MediaUrl::resolve(Storage::disk('public')->url($c->avatar))
                    : null,
                'member_count' => $c->participants->count(),
                'member_avatars' => $others->take(3)->map(fn (User $u) => $u->avatar)->values(),
                'member_names' => $others->take(3)->map(fn (User $u) => $u->name)->values(),
            ]);
        }

        $other = $c->counterpart($me);

        return array_merge($base, [
            'player_id' => $other?->player_id,
            'name' => $other?->name,
            'username' => $other?->username,
            'avatar' => $other?->avatar,
        ]);
    }
}
