<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\ContentUpdated;
use App\Models\SupportMessage;
use App\Models\SupportThread;
use App\Models\User;

/**
 * The rules of a user's support conversation, shared by every client.
 *
 * The app talks to {@see \App\Http\Controllers\Api\SupportController} over JWT and
 * the website to {@see \App\Http\Controllers\Web\SupportChatController} over a
 * session — but a thread must behave identically either way (same reopen rule,
 * same unread accounting, same topic-locking), so those rules live here rather
 * than in each controller.
 */
final class SupportChat
{
    /** The user's live (non-closed) thread, or a fresh one. */
    public function threadFor(User $user): SupportThread
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

    /**
     * Open the thread as the user: their unread badge clears, because they are
     * now looking at the admin's replies.
     */
    public function openForUser(User $user): SupportThread
    {
        $thread = $this->threadFor($user);

        if ($thread->user_unread_count !== 0) {
            $thread->forceFill(['user_unread_count' => 0])->save();
        }

        return $thread;
    }

    /**
     * Post a message from the user, and flag the thread for the team.
     *
     * Creates the thread on first contact. A closed thread is never reopened —
     * {@see threadFor} skips it and the user gets a fresh one — so a resolved
     * conversation stays resolved in the team's queue.
     */
    public function postUserMessage(User $user, string $body, ?int $categoryId = null): SupportThread
    {
        $thread = $this->threadFor($user);

        // The picker only labels a thread that doesn't have a topic yet. Once an
        // admin has (re)classified it, the user's next message can't overwrite
        // that — users guess wrong often, admins correct them.
        if ($thread->category_id === null && $categoryId !== null) {
            $thread->forceFill(['category_id' => $categoryId])->save();
        }

        SupportMessage::create([
            'thread_id'   => $thread->id,
            'sender_type' => 'user',
            'sender_id'   => $user->id,
            'body'        => $body,
        ]);

        $thread->forceFill([
            'status'             => 'open',
            'last_message_at'    => now(),
            'admin_unread_count' => $thread->admin_unread_count + 1,
            'user_unread_count'  => 0,
        ])->save();

        // Nudge any listening admin dashboards to refresh (no payload).
        ContentUpdated::dispatch('support');

        return $thread->fresh();
    }

    /** Unread admin replies waiting for this user, for the header badge. */
    public function unreadFor(User $user): int
    {
        return (int) SupportThread::query()
            ->where('user_id', $user->id)
            ->where('status', '!=', 'closed')
            ->sum('user_unread_count');
    }
}
