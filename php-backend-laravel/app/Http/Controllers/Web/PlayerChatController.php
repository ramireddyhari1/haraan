<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\DirectMessage;
use App\Models\User;
use App\Services\DirectMessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Player-to-player chat on the website — the twin of the app's Chat tab.
 *
 * The web's Chat button used to open the SUPPORT desk (you talking to Haraan), which is
 * a different conversation with a different other end. This is the app's one: your DMs
 * with other players, off the same `conversations` / `direct_messages` tables the JWT API
 * serves, so a thread reads the same wherever you open it.
 *
 * Everything routed through DirectMessageService, never straight into the tables — the
 * unread bookkeeping, the mutual-follow gate and the broadcast all live there, and a
 * second implementation of any of them is a second thing to get wrong.
 */
final class PlayerChatController extends Controller
{
    public function __construct(private readonly DirectMessageService $dm)
    {
    }

    /** GET /chat — your threads, most recent first. */
    public function index(): View
    {
        $me = auth()->user();

        $conversations = Conversation::query()
            ->whereHas('participants', fn ($q) => $q->where('users.id', $me->id))
            ->with('participants')
            ->orderByDesc('last_message_at')
            ->limit(100)
            ->get();

        // Opening the LIST means the messages have reached you — that is what the second
        // tick means to the other side. Reading them is a separate act (opening a thread).
        $this->dm->markDelivered($me);

        return view('site.chat', [
            'title' => 'Chat',
            'threads' => $conversations->map(fn (Conversation $c) => $this->card($c, $me))->values(),
            'unreadTotal' => $this->dm->unreadTotal($me),
        ]);
    }

    /** GET /chat/{id} — one thread, oldest message first so it reads top-down. */
    public function show(int $id): View|RedirectResponse
    {
        $me = auth()->user();
        $conversation = $this->memberConversation($me, $id);
        if ($conversation === null) {
            return redirect()->route('site.chat');
        }

        // How many were waiting when the reader arrived. Captured BEFORE markRead, and
        // used to draw the "N new messages" rule — after the mark it is always zero.
        $unreadOnOpen = (int) ($conversation->participants->firstWhere('id', $me->id)?->pivot?->unread_count ?? 0);
        $this->dm->markRead($conversation, $me);

        $messages = $conversation->messages()->orderBy('id')->limit(300)->get();
        $senders = User::query()->whereIn('id', $messages->pluck('sender_id')->unique()->all())->get()->keyBy('id');

        return view('site.chat-thread', [
            'title' => $this->card($conversation, $me)['name'] ?: 'Chat',
            'thread' => $this->card($conversation, $me),
            'messages' => $messages->map(fn (DirectMessage $m) => $this->line($m, $me, $senders))->values(),
            'unreadOnOpen' => $unreadOnOpen,
        ]);
    }

    /** POST /chat/{id}/messages — send. Answers with the stored line, not the input. */
    public function send(Request $request, int $id): JsonResponse
    {
        $me = $request->user();
        $conversation = $this->memberConversation($me, $id);
        if ($conversation === null) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        $body = trim($data['body']);
        if ($body === '') {
            return response()->json(['error' => 'Empty message'], 422);
        }

        // Same gate the app hits: you may only message someone you follow who follows you
        // back. Checked here too, because a conversation row can outlive the follow.
        $other = $conversation->is_group ? null : $conversation->counterpart($me);
        if ($other instanceof User && ! $this->dm->canMessage($me, $other)) {
            return response()->json([
                'error' => 'You can only message players you follow who also follow you.',
            ], 403);
        }

        $message = $this->dm->sendAndBroadcast($conversation, $me, $body);
        $senders = collect([$me->id => $me]);

        return response()->json($this->line($message, $me, $senders), 201);
    }

    /**
     * GET /chat/{id}/poll?since_id= — only what has arrived since the caller last looked.
     *
     * The open thread polls this. Without `since_id` it would re-download the whole page
     * every few seconds and fight the reader's scroll; with it, a quiet thread answers
     * with an empty array.
     */
    public function poll(Request $request, int $id): JsonResponse
    {
        $me = $request->user();
        $conversation = $this->memberConversation($me, $id);
        if ($conversation === null) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $since = $request->query('since_id');
        $since = is_numeric($since) ? (int) $since : 0;

        $query = $conversation->messages()->orderBy('id');
        if ($since > 0) {
            $query->where('id', '>', $since);
        }
        $messages = $query->limit(300)->get();

        // Anything the poll returns is now on screen, so the thread is read up to here.
        if ($messages->isNotEmpty()) {
            $this->dm->markRead($conversation, $me);
        }

        $senders = User::query()->whereIn('id', $messages->pluck('sender_id')->unique()->all())->get()->keyBy('id');

        return response()->json([
            'results' => $messages->map(fn (DirectMessage $m) => $this->line($m, $me, $senders))->values(),
        ]);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    /** The conversation, but only if the viewer is actually in it. */
    private function memberConversation(User $me, int $id): ?Conversation
    {
        $conversation = Conversation::query()->with('participants')->find($id);
        if ($conversation === null) {
            return null;
        }

        return $conversation->participants->contains('id', $me->id) ? $conversation : null;
    }

    /** One row in the thread list: who it is with, what was last said, what is unread. */
    private function card(Conversation $c, User $me): array
    {
        $mine = $c->participants->firstWhere('id', $me->id);
        $base = [
            'id' => (int) $c->id,
            'isGroup' => (bool) $c->is_group,
            'lastMessage' => (string) ($c->last_message_preview ?? ''),
            'lastAt' => $c->last_message_at,
            'unread' => (int) ($mine?->pivot?->unread_count ?? 0),
        ];

        if ($c->is_group) {
            $others = $c->others($me);

            return array_merge($base, [
                'playerId' => '',
                'name' => (string) ($c->title ?? 'Group'),
                'username' => '',
                'avatar' => '',
                'memberCount' => $c->participants->count(),
            ]);
        }

        $other = $c->counterpart($me);

        return array_merge($base, [
            'playerId' => (string) ($other?->player_id ?? ''),
            'name' => (string) ($other?->name ?? 'Player'),
            'username' => (string) ($other?->username ?? ''),
            'avatar' => $this->mediaUrl($other?->avatar),
            'memberCount' => 2,
        ]);
    }

    /** One message, flattened for the bubble that draws it. */
    private function line(DirectMessage $m, User $me, $senders): array
    {
        $sender = $senders[$m->sender_id] ?? null;
        $unsent = $m->deleted_at !== null;

        return [
            'id' => (int) $m->id,
            'mine' => (int) $m->sender_id === (int) $me->id,
            // An unsent message keeps its row so the thread keeps its order — but it must
            // never carry the body, or an old client could still render it.
            'body' => $unsent ? '' : (string) $m->body,
            'unsent' => $unsent,
            'senderName' => (string) ($sender?->name ?? 'Player'),
            // ISO, always. A bare "2026-08-20 10:14:05" is a UTC instant with nothing
            // saying so, and the browser reads it as local — which shifted every
            // timestamp in the app by the offset until it was fixed there.
            'sentAt' => $m->created_at?->toIso8601String(),
        ];
    }

    private function mediaUrl(?string $path): string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return '';
        }
        if (preg_match('~^https?://~i', $path) || str_starts_with($path, '/')) {
            return $path;
        }

        return asset('storage/' . ltrim($path, '/'));
    }
}
