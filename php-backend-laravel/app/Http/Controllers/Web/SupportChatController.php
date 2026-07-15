<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\SupportCategory;
use App\Services\SupportChat;
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
        $user   = auth()->user();
        $thread = $this->chat->openForUser($user);

        return view('site.support', [
            'title'      => 'Support',
            'thread'     => $thread,
            'messages'   => $thread->messages()->get(),
            // Only offered while the thread has no topic yet — same rule as the app.
            'categories' => $thread->category_id === null
                ? SupportCategory::query()->active()->get()
                : collect(),
        ]);
    }

    /** POST /support/messages — send a message as the user. */
    public function send(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'body'        => ['required', 'string', 'max:4000'],
            'category_id' => ['nullable', 'integer', 'exists:support_categories,id'],
        ]);

        $body = trim($data['body']);
        if ($body === '') {
            return back()->withErrors(['body' => 'Message cannot be empty.']);
        }

        $this->chat->postUserMessage(
            auth()->user(),
            $body,
            isset($data['category_id']) ? (int) $data['category_id'] : null,
        );

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
            ->when($after > 0, fn ($q) => $q->where('id', '>', $after))
            ->get()
            ->map(fn ($m): array => [
                'id'   => $m->id,
                'body' => $m->body,
                'from' => $m->sender_type,
                'at'   => $m->created_at?->format('g:i A'),
            ])->all();

        return response()->json(['messages' => $messages]);
    }
}
