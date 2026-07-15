<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SupportCategory;
use App\Models\SupportMessage;
use App\Models\SupportThread;
use App\Models\User;
use App\Services\SupportChat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The app (JWT) and the website (session) write to one conversation through
 * {@see SupportChat}. These cover the rules that must not drift between them.
 */
class SupportChatTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::create([
            'name'     => 'Test Player',
            'email'    => 'player@example.test',
            'password' => bcrypt('secret'),
            'role'     => 'user',
            'status'   => 'active',
        ]);
    }

    public function test_a_users_messages_reuse_one_open_thread(): void
    {
        $user = $this->user();
        $chat = app(SupportChat::class);

        $chat->postUserMessage($user, 'First');
        $chat->postUserMessage($user, 'Second');

        $this->assertSame(1, SupportThread::where('user_id', $user->id)->count());
        $this->assertSame(2, SupportMessage::count());
    }

    public function test_a_message_after_a_close_starts_a_fresh_thread(): void
    {
        $user = $this->user();
        SupportThread::create([
            'user_id'         => $user->id,
            'status'          => 'closed',
            'last_message_at' => now(),
        ]);

        $thread = app(SupportChat::class)->postUserMessage($user, 'Actually, one more thing');

        $this->assertSame('open', $thread->status);
        $this->assertSame(1, $thread->admin_unread_count);
        // A closed thread is left closed; the user gets a fresh one.
        $this->assertSame(2, SupportThread::where('user_id', $user->id)->count());
    }

    public function test_opening_the_thread_clears_only_the_users_unread_badge(): void
    {
        $user   = $this->user();
        $chat   = app(SupportChat::class);
        $thread = $chat->threadFor($user);
        $thread->forceFill(['user_unread_count' => 3, 'admin_unread_count' => 2])->save();

        $opened = $chat->openForUser($user);

        $this->assertSame(0, $opened->user_unread_count);
        $this->assertSame(2, $opened->admin_unread_count, 'the team\'s queue must survive the user reading');
    }

    public function test_the_user_cannot_overwrite_a_topic_an_admin_has_set(): void
    {
        $user  = $this->user();
        $chat  = app(SupportChat::class);
        $admin = SupportCategory::create(['label' => 'Payments', 'is_active' => true]);
        $wrong = SupportCategory::create(['label' => 'Scoring', 'is_active' => true]);

        $thread = $chat->threadFor($user);
        $thread->forceFill(['category_id' => $admin->id])->save();

        $chat->postUserMessage($user, 'hello', $wrong->id);

        $this->assertSame($admin->id, $thread->fresh()->category_id);
    }

    public function test_the_first_message_labels_an_unclassified_thread(): void
    {
        $user     = $this->user();
        $category = SupportCategory::create(['label' => 'Tickets', 'is_active' => true]);

        $thread = app(SupportChat::class)->postUserMessage($user, 'hello', $category->id);

        $this->assertSame($category->id, $thread->category_id);
    }

    public function test_the_web_chat_page_shows_the_users_thread(): void
    {
        $user = $this->user();
        app(SupportChat::class)->postUserMessage($user, 'My booking never arrived');

        $this->actingAs($user)
            ->get('/support')
            ->assertOk()
            ->assertSee('My booking never arrived');
    }

    public function test_the_web_chat_posts_a_message(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->post('/support/messages', ['body' => 'Sent from the website'])
            ->assertRedirect();

        $this->assertDatabaseHas('support_messages', [
            'body'        => 'Sent from the website',
            'sender_type' => 'user',
        ]);
    }

    public function test_the_web_chat_rejects_an_empty_message(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->post('/support/messages', ['body' => '   '])
            ->assertSessionHasErrors('body');

        $this->assertSame(0, SupportMessage::count());
    }

    public function test_polling_returns_only_messages_after_the_cursor(): void
    {
        $user   = $this->user();
        $chat   = app(SupportChat::class);
        $thread = $chat->postUserMessage($user, 'first');
        $first  = $thread->messages()->first();

        SupportMessage::create([
            'thread_id'   => $thread->id,
            'sender_type' => 'admin',
            'body'        => 'a reply',
        ]);

        $this->actingAs($user)
            ->getJson('/support/poll?after=' . $first->id)
            ->assertOk()
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.body', 'a reply')
            ->assertJsonPath('messages.0.from', 'admin');
    }

    public function test_the_inbox_lanes_require_a_signed_in_user(): void
    {
        $this->get('/support')->assertRedirect();
        $this->get('/notifications')->assertRedirect();
    }
}
