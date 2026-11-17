<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Support\PlatformRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * ActionBoard chat, tier 2: pin / archive / delete chat (per person), editing a sent message,
 * search inside a thread, and "online / last seen".
 */
class DirectMessageTier2Test extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    private function player(string $name): User
    {
        $this->seq++;

        return User::create([
            'name' => $name, 'email' => strtolower($name).'@haraan.test',
            'password' => Hash::make('secret123'), 'role' => 'user', 'status' => 'active',
            'player_id' => 'HRN8'.str_pad((string) $this->seq, 4, '0', STR_PAD_LEFT),
            'is_guest' => false,
            'state' => 'Andhra Pradesh', 'district' => 'YSR Kadapa',
            'primary_sport' => 'Cricket',
        ]);
    }

    private function as(User $u): static
    {
        return $this->withHeader('Authorization', 'Bearer '.\App\Support\JwtService::issue(
            ['sub' => $u->id],
            (string) config('app.jwt_secret', env('JWT_SECRET', 'change_me')),
        ));
    }

    private function dm(User $a, User $b): int
    {
        $a->follow($b);
        $b->follow($a);

        return (int) $this->as($a)->postJson('/api/dm/with/'.$b->player_id)->assertCreated()->json('id');
    }

    private function say(User $u, int $id, string $body): int
    {
        return (int) $this->as($u)->postJson("/api/dm/{$id}/messages", ['body' => $body])->assertCreated()->json('id');
    }

    public function test_pinned_chats_come_first_and_the_cap_holds(): void
    {
        $me = $this->player('Ava');
        $one = $this->dm($me, $this->player('Ben'));
        $two = $this->dm($me, $this->player('Cal'));
        $this->say($me, $one, 'older');
        $this->travel(1)->minutes();
        $this->say($me, $two, 'newer');

        $this->as($me)->getJson('/api/dm')->assertJsonPath('results.0.id', $two);
        $this->as($me)->postJson("/api/dm/{$one}/pin", ['pinned' => true])->assertOk();
        $this->as($me)->getJson('/api/dm')
            ->assertJsonPath('results.0.id', $one)
            ->assertJsonPath('results.0.pinned', true);

        PlatformRules::save(['chat.max_pins' => 1]);
        $this->as($me)->postJson("/api/dm/{$two}/pin", ['pinned' => true])->assertStatus(422);
    }

    public function test_archive_hides_nothing_server_side_and_a_new_message_brings_it_back(): void
    {
        $me = $this->player('Ava');
        $ben = $this->player('Ben');
        $id = $this->dm($me, $ben);

        $this->as($me)->postJson("/api/dm/{$id}/archive", ['archived' => true])->assertOk();
        $this->as($me)->getJson('/api/dm')->assertJsonPath('results.0.archived', true);

        $this->say($ben, $id, 'you there?');
        $this->as($me)->getJson('/api/dm')->assertJsonPath('results.0.archived', false);

        // Muted + archived stays archived.
        $this->as($me)->postJson("/api/dm/{$id}/archive", ['archived' => true])->assertOk();
        $this->as($me)->postJson("/api/dm/{$id}/mute", ['hours' => -1])->assertOk();
        $this->say($ben, $id, 'hello?');
        $this->as($me)->getJson('/api/dm')->assertJsonPath('results.0.archived', true);
    }

    public function test_delete_chat_is_for_me_only_and_returns_on_a_new_message(): void
    {
        $me = $this->player('Ava');
        $ben = $this->player('Ben');
        $id = $this->dm($me, $ben);
        $this->say($ben, $id, 'secret one');

        $this->as($me)->postJson("/api/dm/{$id}/clear")->assertOk();
        $this->as($me)->getJson('/api/dm')->assertJsonCount(0, 'results');
        // Ben still has his copy.
        $this->as($ben)->getJson("/api/dm/{$id}/messages")->assertJsonCount(1, 'results');

        $this->say($ben, $id, 'fresh start');
        $this->as($me)->getJson('/api/dm')->assertJsonCount(1, 'results');
        $this->as($me)->getJson("/api/dm/{$id}/messages")
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.body', 'fresh start');
        $this->as($me)->getJson("/api/dm/{$id}/search?q=secret")->assertJsonCount(0, 'results');
    }

    public function test_editing_inside_the_window_marks_it_edited(): void
    {
        $me = $this->player('Ava');
        $ben = $this->player('Ben');
        $id = $this->dm($me, $ben);
        $msg = $this->say($me, $id, 'nets at 6');

        // Not someone else's.
        $this->as($ben)->postJson("/api/dm/{$id}/messages/{$msg}/edit", ['body' => 'hacked'])->assertStatus(422);

        $this->as($me)->postJson("/api/dm/{$id}/messages/{$msg}/edit", ['body' => 'nets at 6.30'])->assertOk();
        $this->as($ben)->getJson("/api/dm/{$id}/messages")
            ->assertJsonPath('results.0.body', 'nets at 6.30')
            ->assertJsonPath('results.0.edited', true);
        $this->as($ben)->getJson('/api/dm')->assertJsonPath('results.0.last_message', 'nets at 6.30');

        $this->travel(16)->minutes();
        $this->as($me)->postJson("/api/dm/{$id}/messages/{$msg}/edit", ['body' => 'too late'])->assertStatus(422);

        PlatformRules::save(['chat.edit_window_minutes' => 0]);
        $fresh = $this->say($me, $id, 'new');
        $this->as($me)->postJson("/api/dm/{$id}/messages/{$fresh}/edit", ['body' => 'newer'])->assertStatus(422);
    }

    public function test_search_finds_words_but_not_unsent_or_strangers(): void
    {
        $me = $this->player('Ava');
        $ben = $this->player('Ben');
        $id = $this->dm($me, $ben);
        $this->say($ben, $id, 'Bring the STUMPS please');
        $gone = $this->say($me, $id, 'stumps are in my car');
        $this->as($me)->deleteJson("/api/dm/{$id}/messages/{$gone}")->assertOk();
        $this->say($me, $id, 'ok');

        $this->as($me)->getJson("/api/dm/{$id}/search?q=stumps")
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.sender_name', 'Ben');
        $this->as($this->player('Cal'))->getJson("/api/dm/{$id}/search?q=stumps")->assertNotFound();
    }

    public function test_last_seen_is_shown_and_can_be_switched_off(): void
    {
        $me = $this->player('Ava');
        $ben = $this->player('Ben');
        $id = $this->dm($me, $ben);
        DB::table('users')->where('id', $ben->id)->update(['last_seen_at' => now()->subMinutes(2)]);

        $this->as($me)->getJson("/api/dm/{$id}/messages")->assertJsonPath('their_online', true);

        DB::table('users')->where('id', $ben->id)->update(['last_seen_at' => now()->subHours(3)]);
        $res = $this->as($me)->getJson("/api/dm/{$id}/messages")->assertJsonPath('their_online', false);
        $this->assertNotNull($res->json('their_last_seen_at'));

        PlatformRules::save(['chat.show_last_seen' => false]);
        $this->as($me)->getJson("/api/dm/{$id}/messages")->assertJsonPath('their_last_seen_at', null);
    }
}
