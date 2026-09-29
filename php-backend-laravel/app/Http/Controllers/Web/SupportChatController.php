<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\SupportCategory;
use App\Models\SupportMessage;
use App\Models\SupportThread;
use App\Services\SupportChat;
use App\Support\SupportPageCopy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The website's half of the support chat — the same conversation the app shows,
 * reached from the header's chat icon. Session-authenticated (the API twin is
 * JWT), but both go through {@see SupportChat} so a thread behaves the same
 * whichever side the user writes from, and admins keep replying in one place
 * (the Filament "Support" resource).
 */
final class SupportChatController extends Controller
{
    public function __construct(private readonly SupportChat $chat)
    {
    }

    /** GET /support — the thread, with the admin's replies marked read. */
    public function show(): View
    {
        $user     = auth()->user();
        $thread   = $this->chat->openForUser($user);
        $messages = $thread->messages()->with('sender:id,name')->get();

        return view('site.support', [
            'title'      => 'Support',
            'thread'     => $thread,
            'messages'   => $messages,
            'payload'    => $messages->map(fn (SupportMessage $m): array => $this->present($m))->all(),
            'seen'       => $this->seen($thread),
            'copy'       => SupportPageCopy::all(),
            // Only offered while the thread has no topic yet — same rule as the app.
            'categories' => $thread->category_id === null
                ? SupportCategory::query()->active()->get()
                : collect(),
        ]);
    }

    /**
     * POST /support/messages — send a message as the user. The page sends with
     * fetch and gets the stored message back as JSON; without JS it's a plain
     * form post and a redirect.
     */
    public function send(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'body'        => ['required', 'string', 'max:4000'],
            'category_id' => ['nullable', 'integer', 'exists:support_categories,id'],
        ]);

        $body = trim($data['body']);
        if ($body === '') {
            return $request->expectsJson()
                ? response()->json(['message' => 'Message cannot be empty.'], 422)
                : back()->withErrors(['body' => 'Message cannot be empty.']);
        }

        $thread = $this->chat->postUserMessage(
            auth()->user(),
            $body,
            isset($data['category_id']) ? (int) $data['category_id'] : null,
        );

        if ($request->expectsJson()) {
            $message = $thread->messages()->where('sender_type', 'user')->latest('id')->first();

            return response()->json(['message' => $message ? $this->present($message) : null]);
        }

        return redirect()->route('site.support')->withFragment('latest');
    }

    /**
     * GET /support/poll — new messages as JSON, so an open chat picks up an
     * admin's reply without a reload. Mirrors the app, which polls the same
     * conversation every 4s; the web has no socket client yet.
     */
    public function poll(Request $request): JsonResponse
    {
        $after  = (int) $request->query('after', '0');
        $thread = $this->chat->openForUser(auth()->user());

        $messages = $thread->messages()
            ->with('sender:id,name')
            ->when($after > 0, fn ($q) => $q->where('id', '>', $after))
            ->get()
            ->map(fn (SupportMessage $m): array => $this->present($m))
            ->all();

        return response()->json(['messages' => $messages, 'seen' => $this->seen($thread)]);
    }

    /** One message, as the page's renderer reads it. */
    private function present(SupportMessage $m): array
    {
        $isTeam = $m->sender_type === 'admin';

        return [
            'id'   => $m->id,
            'body' => $m->body,
            'from' => $isTeam ? 'admin' : 'user',
            // First name only: enough to know a person answered, no more.
            'name' => $isTeam ? (strtok((string) $m->sender?->name, ' ') ?: null) : null,
            'at'   => $m->created_at?->format('g:i A'),
            'ts'   => $m->created_at?->toIso8601String(),
        ];
    }

    /**
     * True once the team has opened the thread since the user last wrote —
     * opening it in /control is what clears admin_unread_count.
     */
    private function seen(SupportThread $thread): bool
    {
        return (int) $thread->admin_unread_count === 0;
    }
}
