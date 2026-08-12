<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Player-to-player direct messages.
 *
 * The contract these tests defend is the PERMISSION rule, because it is the part that
 * cannot be fixed after the fact: messaging requires a MUTUAL follow. Under any looser
 * rule an unwanted message is possible, and on an app where players are discoverable
 * by district that is a real exposure.
 */
class DirectMessageTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    private function player(string $name): User
    {
        $this->seq++;

        return User::create([
            'name' => $name, 'email' => strtolower($name) . '@haraan.test',
            'password' => Hash::make('secret123'), 'role' => 'user', 'status' => 'active',
            'player_id' => 'HRN' . str_pad((string) $this->seq, 5, '0', STR_PAD_LEFT),
            'is_guest' => false,
            'state' => 'Andhra Pradesh', 'district' => 'YSR Kadapa',
            'primary_sport' => 'Cricket',
            'sport_attributes' => ['role' => 'All-rounder', 'batting' => 'Right', 'bowling' => 'Right-arm medium'],
        ]);
    }

    private function token(User $u): string
    {
        return \App\Support\JwtService::issue(
            ['sub' => $u->id],
            (string) config('app.jwt_secret', env('JWT_SECRET', 'change_me')),
        );
    }

    private function as(User $u): \Illuminate\Testing\TestCase|\Tests\TestCase
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token($u));
    }

    /** Make two players mutual followers. */
    private function befriend(User $a, User $b): void
    {
        $a->follow($b);
        $b->follow($a);
    }

    public function test_mutual_followers_can_open_a_conversation(): void
    {
        $a = $this->player('Ava');
        $b = $this->player('Ben');
        $this->befriend($a, $b);

        $this->as($a)->postJson('/api/dm/with/' . $b->player_id)
            ->assertCreated()
            ->assertJsonPath('name', 'Ben');
    }

    /** The rule. Following someone one-way must NOT open a channel to them. */
    public function test_a_one_way_follow_cannot_message(): void
    {
        $a = $this->player('Ava');
        $b = $this->player('Ben');
        $a->follow($b); // b does not follow back

        $this->as($a)->postJson('/api/dm/with/' . $b->player_id)
            ->assertStatus(403);
    }

    public function test_a_stranger_cannot_message(): void
    {
        $this->as($this->player('Ava'))
            ->postJson('/api/dm/with/' . $this->player('Ben')->player_id)
            ->assertStatus(403);
    }

    /** Opening the same 1:1 twice must not fork into two threads. */
    public function test_opening_twice_returns_the_same_conversation(): void
    {
        $a = $this->player('Ava');
        $b = $this->player('Ben');
        $this->befriend($a, $b);

        $first = $this->as($a)->postJson('/api/dm/with/' . $b->player_id)->json('id');
        $second = $this->as($b)->postJson('/api/dm/with/' . $a->player_id)->json('id');

        self::assertSame($first, $second);
        self::assertSame(1, Conversation::count());
    }

    public function test_a_sent_message_lands_and_raises_only_the_recipients_unread(): void
    {
        $a = $this->player('Ava');
        $b = $this->player('Ben');
        $this->befriend($a, $b);
        $id = $this->as($a)->postJson('/api/dm/with/' . $b->player_id)->json('id');

        $this->as($a)->postJson("/api/dm/$id/messages", ['body' => 'good game'])->assertCreated();

        // Sender sees zero unread; recipient sees one.
        self::assertSame(0, $this->as($a)->getJson('/api/dm')->json('unread_total'));
        self::assertSame(1, $this->as($b)->getJson('/api/dm')->json('unread_total'));
        self::assertSame('good game', $this->as($b)->getJson('/api/dm')->json('results.0.last_message'));
    }

    public function test_reading_a_thread_clears_its_unread(): void
    {
        $a = $this->player('Ava');
        $b = $this->player('Ben');
        $this->befriend($a, $b);
        $id = $this->as($a)->postJson('/api/dm/with/' . $b->player_id)->json('id');
        $this->as($a)->postJson("/api/dm/$id/messages", ['body' => 'hi']);

        $this->as($b)->getJson("/api/dm/$id/messages")->assertOk();

        self::assertSame(0, $this->as($b)->getJson('/api/dm')->json('unread_total'));
    }

    /**
     * A conversation id is guessable, so membership is the only thing protecting it.
     * An outsider must get 404 — not 403, which would confirm the thread exists.
     */
    public function test_an_outsider_cannot_read_someone_elses_thread(): void
    {
        $a = $this->player('Ava');
        $b = $this->player('Ben');
        $this->befriend($a, $b);
        $id = $this->as($a)->postJson('/api/dm/with/' . $b->player_id)->json('id');

        $this->as($this->player('Nosy'))->getJson("/api/dm/$id/messages")->assertStatus(404);
    }

    /**
     * Unfollowing must actually close the channel. Checking permission only at thread
     * creation would leave a conversation open forever once it existed.
     */
    public function test_unfollowing_stops_further_messages_in_an_existing_thread(): void
    {
        $a = $this->player('Ava');
        $b = $this->player('Ben');
        $this->befriend($a, $b);
        $id = $this->as($a)->postJson('/api/dm/with/' . $b->player_id)->json('id');
        $this->as($a)->postJson("/api/dm/$id/messages", ['body' => 'hi'])->assertCreated();

        $b->unfollow($a);

        $this->as($a)->postJson("/api/dm/$id/messages", ['body' => 'still there?'])
            ->assertStatus(403);
    }

    public function test_an_empty_message_is_rejected(): void
    {
        $a = $this->player('Ava');
        $b = $this->player('Ben');
        $this->befriend($a, $b);
        $id = $this->as($a)->postJson('/api/dm/with/' . $b->player_id)->json('id');

        $this->as($a)->postJson("/api/dm/$id/messages", ['body' => ''])->assertStatus(422);
    }
}
