<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\User;
use App\Services\DirectMessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        // Their app now holds every message in these threads — that is what the second
        // tick means. Stamped here, NOT on opening a thread (that is `read`).
        $this->dm->markDelivered($me);

        return response()->json([
            'unread_total' => $this->dm->unreadTotal($me),
            'results' => $conversations->map(fn (Conversation $c) => $this->card($c, $me))->values(),
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

        // ?since_id= returns only what has arrived since the client last looked.
        //
        // The app polls this thread every few seconds while it is open; without this it
        // re-downloaded up to 300 messages each time and rebuilt the whole list, which
        // costs data on every tick and fights the reader's scroll position. With it, a
        // quiet thread answers with an empty array.
        $since = $request->query('since_id');
        $since = is_numeric($since) ? (int) $since : null;

        $query = $conversation->messages()->orderBy('id');
        if ($since !== null && $since > 0) {
            $query->where('id', '>', $since);
        }
        $messages = $query->limit(300)->get();

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

        return response()->json([
            'is_group' => (bool) $conversation->is_group,
            // Tells an appending client this was a delta, not the whole thread.
            'since_id' => $since,
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
            'results' => $messages->map(function ($m) use ($me, $senders, $reactions, $quoted, $quotedSenders) {
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
            'body' => ['required', 'string', 'max:2000'],
                    // Quoting another message in this thread. Membership and same-thread
            // are enforced in the service, not here.
            'reply_to_id' => ['nullable', 'integer'],
        ]);

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
            trim($data['body']),
            isset($data['reply_to_id']) ? (int) $data['reply_to_id'] : null,
        );

        return response()->json([
            'id' => (int) $message->id,
            'body' => $message->body,
            'sender_id' => (int) $message->sender_id,
            'mine' => true,
            'sent_at' => $message->created_at?->toIso8601String(),
        ], 201);
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
    private function card(Conversation $c, User $me): array
    {
        $mine = $c->participants->firstWhere('id', $me->id);

        $base = [
            'id' => (int) $c->id,
            'is_group' => (bool) $c->is_group,
            'last_message' => $c->last_message_preview,
            'last_message_at' => $c->last_message_at?->toIso8601String(),
            'unread_count' => (int) ($mine?->pivot?->unread_count ?? 0),
        ];

        if ($c->is_group) {
            // A group's identity is its title; the row stacks a few member avatars in
            // place of the single counterpart photo a 1:1 shows.
            $others = $c->others($me);

            return array_merge($base, [
                'player_id' => null,
                'name' => $c->title,
                'username' => null,
                'avatar' => null,
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
