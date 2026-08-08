<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\User;
use App\Services\DirectMessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        $messages = $conversation->messages()->orderBy('id')->limit(300)->get();

        return response()->json([
            'results' => $messages->map(fn ($m) => [
                'id' => (int) $m->id,
                'body' => $m->body,
                'sender_id' => (int) $m->sender_id,
                'mine' => (int) $m->sender_id === (int) $me->id,
                'sent_at' => $m->created_at?->toIso8601String(),
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
        ]);

        // Re-check on every send, not just at thread creation: an unfollow must stop
        // the conversation, otherwise a thread opened while mutual stays open forever.
        $other = $conversation->counterpart($me);
        if ($other !== null && ! $this->dm->canMessage($me, $other)) {
            return response()->json([
                'error' => 'You can no longer message this player.',
            ], 403);
        }

        $message = $this->dm->send($conversation, $me, trim($data['body']));

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
        $other = $c->counterpart($me);
        $mine = $c->participants->firstWhere('id', $me->id);

        return [
            'id' => (int) $c->id,
            'player_id' => $other?->player_id,
            'name' => $other?->name,
            'username' => $other?->username,
            'avatar' => $other?->avatar,
            'last_message' => $c->last_message_preview,
            'last_message_at' => $c->last_message_at?->toIso8601String(),
            'unread_count' => (int) ($mine?->pivot?->unread_count ?? 0),
        ];
    }
}
