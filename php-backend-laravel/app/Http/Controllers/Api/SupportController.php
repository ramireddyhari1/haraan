<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Events\ContentUpdated;
use App\Http\Controllers\Controller;
use App\Models\SupportMessage;
use App\Models\SupportThread;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * In-app support chat for the mobile app. Each user has a single conversation
 * with the Haraan support team; admins/assigned workers reply from the Filament
 * control panel. The app opens {@see thread()} and polls it while the chat
 * screen is visible.
 */
final class SupportController extends Controller
{
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

        $thread = $this->threadFor($user);

        if ($thread->user_unread_count !== 0) {
            $thread->forceFill(['user_unread_count' => 0])->save();
        }

        return response()->json($this->present($thread));
    }

    /**
     * Post a message from the app. Creates the thread on first contact.
     * POST /api/support/messages   { body: string }
     */
    public function send(Request $request): JsonResponse
    {
        $user = $this->user($request);
        if (!$user instanceof User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $data = $request->validate([
            'body' => ['required', 'string', 'max:4000'],
        ]);

        $body = trim($data['body']);
        if ($body === '') {
            throw ValidationException::withMessages(['body' => 'Message cannot be empty.']);
        }

        $thread = $this->threadFor($user);

        SupportMessage::create([
            'thread_id'   => $thread->id,
            'sender_type' => 'user',
            'sender_id'   => $user->id,
            'body'        => $body,
        ]);

        // A new user message reopens a closed thread and flags it for the team.
        $thread->forceFill([
            'status'             => 'open',
            'last_message_at'    => now(),
            'admin_unread_count' => $thread->admin_unread_count + 1,
            'user_unread_count'  => 0,
        ])->save();

        // Nudge any listening admin dashboards to refresh (no payload).
        ContentUpdated::dispatch('support');

        return response()->json($this->present($thread->fresh()), 201);
    }

    // -------------------------------------------------------------------------

    private function user(Request $request): ?User
    {
        $user = $request->attributes->get('auth_user');

        return $user instanceof User ? $user : null;
    }

    /** Get the user's live (non-closed) thread, or create a fresh one. */
    private function threadFor(User $user): SupportThread
    {
        return SupportThread::query()
            ->where('user_id', $user->id)
            ->where('status', '!=', 'closed')
            ->latest('id')
            ->first()
            ?? SupportThread::create([
                'user_id'         => $user->id,
                'status'          => 'open',
                'last_message_at' => now(),
            ]);
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
            ],
            'messages' => $messages,
        ];
    }
}
