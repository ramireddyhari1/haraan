<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportCategory;
use App\Models\SupportMessage;
use App\Models\SupportThread;
use App\Models\User;
use App\Services\SupportChat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * In-app support chat for the mobile app. Each user has a single conversation
 * with the Haraan support team; admins/assigned workers reply from the Filament
 * control panel. The app opens {@see thread()} and polls it while the chat
 * screen is visible.
 *
 * The conversation rules themselves live in {@see SupportChat}, shared with the
 * website's session-authenticated chat.
 */
final class SupportController extends Controller
{
    public function __construct(private readonly SupportChat $chat)
    {
    }

    /**
     * The current user's conversation + messages. Opening the thread marks the
     * admin's replies as read (clears the user's unread badge).
     * GET /api/support/thread
     */
    public function thread(Request $request): JsonResponse
    {
        $user = $this->user($request);
        if (!$user instanceof User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return response()->json($this->present($this->chat->openForUser($user)));
    }

    /**
     * The issue topics shown as a picker before the chat starts. Admin-managed,
     * so the app must never hardcode this list.
     * GET /api/support/categories
     */
    public function categories(): JsonResponse
    {
        $categories = SupportCategory::query()->active()->get()
            ->map(fn (SupportCategory $c): array => [
                'id'       => $c->id,
                'label'    => $c->label,
                'icon_key' => $c->icon_key,
                'subtitle' => $c->subtitle,
            ])->all();

        return response()->json(['categories' => $categories]);
    }

    /**
     * Post a message from the app. Creates the thread on first contact.
     * POST /api/support/messages   { body: string, category_id?: int }
     *
     * @see SupportChat::postUserMessage() for the thread rules.
     */
    public function send(Request $request): JsonResponse
    {
        $user = $this->user($request);
        if (!$user instanceof User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $data = $request->validate([
            'body'        => ['required', 'string', 'max:4000'],
            'category_id' => ['nullable', 'integer', 'exists:support_categories,id'],
        ]);

        $body = trim($data['body']);
        if ($body === '') {
            throw ValidationException::withMessages(['body' => 'Message cannot be empty.']);
        }

        $thread = $this->chat->postUserMessage($user, $body, isset($data['category_id']) ? (int) $data['category_id'] : null);

        return response()->json($this->present($thread), 201);
    }

    // -------------------------------------------------------------------------

    private function user(Request $request): ?User
    {
        $user = $request->attributes->get('auth_user');

        return $user instanceof User ? $user : null;
    }

    /** @return array<string, mixed> */
    private function present(SupportThread $thread): array
    {
        $messages = $thread->messages()->get()->map(fn (SupportMessage $m): array => [
            'id'         => $m->id,
            'body'       => $m->body,
            'from'       => $m->sender_type, // 'user' | 'admin'
            'created_at' => $m->created_at?->toIso8601String(),
        ])->all();

        return [
            'thread' => [
                'id'          => $thread->id,
                'status'      => $thread->status,
                'unread'      => $thread->user_unread_count,
                'assigned_to' => $thread->assignee?->name,
                'category'    => $thread->category?->label,
            ],
            'messages' => $messages,
        ];
    }
}
