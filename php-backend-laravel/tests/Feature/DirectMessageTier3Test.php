<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Support\PlatformRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * ActionBoard chat, tier 3: message requests, polls, voice notes, team chats for a match.
 */
class DirectMessageTier3Test extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    private function player(string $name): User
    {
        $this->seq++;

        return User::create([
            'name' => $name, 'email' => strtolower($name).'@haraan.test',
            'password' => Hash::make('secret123'), 'role' => 'user', 'status' => 'active',
            'player_id' => 'HRN7'.str_pad((string) $this->seq, 4, '0', STR_PAD_LEFT),
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

    private function mutual(User $a, User $b): int
    {
        $a->follow($b);
        $b->follow($a);

        return (int) $this->as($a)->postJson('/api/dm/with/'.$b->player_id)->assertCreated()->json('id');
    }

    // ── Requests ──────────────────────────────────────────────────────────────────────

    public function test_a_one_way_follower_can_send_a_request_that_waits(): void
    {
        $ava = $this->player('Ava');
        $ben = $this->player('Ben');
        $ava->follow($ben); // Ben doesn't follow back

        $id = (int) $this->as($ava)->postJson('/api/dm/with/'.$ben->player_id)->assertCreated()
            ->assertJsonPath('request', 'outgoing')->json('id');

        PlatformRules::save(['chat.request_max_messages' => 2]);
        $this->as($ava)->postJson("/api/dm/{$id}/messages", ['body' => 'Hi, need a keeper Sunday?'])->assertCreated();
        $this->as($ava)->postJson("/api/dm/{$id}/messages", ['body' => 'I can play'])->assertCreated();
        $this->as($ava)->postJson("/api/dm/{$id}/messages", ['body' => 'hello??'])->assertStatus(403);

        // Ben sees it in his requests, not counted in his badge, and can't reply yet.
        $this->as($ben)->getJson('/api/dm')
            ->assertJsonPath('requests_count', 1)
            ->assertJsonPath('unread_total', 0)
            ->assertJsonPath('results.0.request', 'incoming');
        $this->as($ben)->getJson("/api/dm/{$id}/messages")
            ->assertJsonPath('request', 'incoming')
            ->assertJsonPath('can_send', false);
        // Reading a request tells Ava nothing.
        $this->as($ava)->getJson("/api/dm/{$id}/messages")->assertJsonPath('their_read_at', null);
        $this->as($ben)->postJson("/api/dm/{$id}/messages", ['body' => 'who?'])->assertStatus(403);

        $this->as($ben)->postJson("/api/dm/{$id}/request/accept")->assertOk();
        $this->as($ben)->postJson("/api/dm/{$id}/messages", ['body' => 'Sure, 6am'])->assertCreated();
        $this->as($ava)->postJson("/api/dm/{$id}/messages", ['body' => 'great'])->assertCreated();
    }

    public function test_declining_deletes_it_and_blocks_asking_again(): void
    {
        $ava = $this->player('Ava');
        $ben = $this->player('Ben');
        $ava->follow($ben);
        $id = (int) $this->as($ava)->postJson('/api/dm/with/'.$ben->player_id)->json('id');
        $this->as($ava)->postJson("/api/dm/{$id}/messages", ['body' => 'hey'])->assertCreated();

        $this->as($ben)->postJson("/api/dm/{$id}/request/decline")->assertOk();
        $this->assertDatabaseMissing('conversations', ['id' => $id]);
        $this->as($ava)->postJson('/api/dm/with/'.$ben->player_id)->assertStatus(403);
    }

    public function test_requests_respect_the_switches_privacy_and_follow_rule(): void
    {
        $ava = $this->player('Ava');
        $ben = $this->player('Ben');

        // Not even following them.
        $this->as($ava)->postJson('/api/dm/with/'.$ben->player_id)->assertStatus(403);

        $ava->follow($ben);
        $ben->forceFill(['privacy_public_profile' => false])->save();
        $this->as($ava)->postJson('/api/dm/with/'.$ben->player_id)->assertStatus(403);

        $ben->forceFill(['privacy_public_profile' => true])->save();
        PlatformRules::save(['chat.requests' => false]);
        $this->as($ava)->postJson('/api/dm/with/'.$ben->player_id)->assertStatus(403);
    }

    // ── Polls ─────────────────────────────────────────────────────────────────────────

    public function test_a_group_poll_counts_votes_and_names_voters(): void
    {
        $ava = $this->player('Ava');
        $ben = $this->player('Ben');
        $cal = $this->player('Cal');
        foreach ([$ben, $cal] as $u) {
            $ava->follow($u);
            $u->follow($ava);
        }
        $id = (int) $this->as($ava)->postJson('/api/dm/group', [
            'title' => 'Sunday XI', 'members' => [$ben->player_id, $cal->player_id],
        ])->json('id');

        $res = $this->as($ava)->postJson("/api/dm/{$id}/poll", [
            'question' => 'Who is in Saturday 6am?', 'options' => ['In', 'Out', 'Maybe'],
        ])->assertCreated();
        $pollId = $res->json('poll.id');
        $in = $res->json('poll.options.0.id');
        $out = $res->json('poll.options.1.id');

        $this->as($ben)->postJson("/api/dm/{$id}/polls/{$pollId}/vote", ['options' => [$in]])->assertOk();
        $this->as($cal)->postJson("/api/dm/{$id}/polls/{$pollId}/vote", ['options' => [$out]])->assertOk();
        // Single choice: changing your mind replaces the vote.
        $this->as($cal)->postJson("/api/dm/{$id}/polls/{$pollId}/vote", ['options' => [$in, $out]])
            ->assertJsonPath('poll.options.0.votes', 2)
            ->assertJsonPath('poll.options.1.votes', 0);

        $page = $this->as($ava)->getJson("/api/dm/{$id}/messages")->json('results');
        $poll = collect($page)->firstWhere('poll', '!=', null)['poll'];
        $this->assertSame(2, $poll['voters']);
        $this->assertEqualsCanonicalizing(['Ben', 'Cal'], $poll['options'][0]['voter_names']);
        $this->as($ava)->getJson('/api/dm')->assertJsonPath('results.0.last_message', '📊 Who is in Saturday 6am?');

        // Another thread's member can't vote here.
        $this->as($this->player('Dev'))->postJson("/api/dm/{$id}/polls/{$pollId}/vote", ['options' => [$in]])->assertNotFound();
    }

    public function test_a_poll_needs_two_real_answers_and_the_switch(): void
    {
        $ava = $this->player('Ava');
        $id = $this->mutual($ava, $this->player('Ben'));
        $this->as($ava)->postJson("/api/dm/{$id}/poll", ['question' => 'Q', 'options' => ['Yes', 'Yes']])->assertStatus(422);
        PlatformRules::save(['chat.polls' => false]);
        $this->as($ava)->postJson("/api/dm/{$id}/poll", ['question' => 'Q', 'options' => ['A', 'B']])->assertStatus(403);
    }

    // ── Voice ─────────────────────────────────────────────────────────────────────────

    public function test_a_voice_note_is_stored_and_capped(): void
    {
        Storage::fake('public');
        $ava = $this->player('Ava');
        $ben = $this->player('Ben');
        $id = $this->mutual($ava, $ben);

        $res = $this->as($ava)->post("/api/dm/{$id}/voice", [
            'audio' => UploadedFile::fake()->create('note.m4a', 40, 'audio/mp4'),
            'duration_ms' => 12_400,
        ], ['Accept' => 'application/json'])->assertCreated();
        $this->assertSame(12_400, $res->json('audio_duration_ms'));

        $this->as($ben)->getJson("/api/dm/{$id}/messages")->assertJsonPath('results.0.audio_duration_ms', 12_400);
        $this->as($ben)->getJson('/api/dm')->assertJsonPath('results.0.last_message', '🎤 Voice message (0:12)');

        $this->as($ava)->post("/api/dm/{$id}/voice", [
            'audio' => UploadedFile::fake()->create('long.m4a', 40, 'audio/mp4'),
            'duration_ms' => 999_000,
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $path = DB::table('direct_messages')->where('id', $res->json('id'))->value('audio_path');
        $this->as($ava)->deleteJson("/api/dm/{$id}/messages/".$res->json('id'))->assertOk();
        Storage::disk('public')->assertMissing($path);
    }

    // ── Team chats ────────────────────────────────────────────────────────────────────

    public function test_the_host_gets_one_team_chat_with_accepted_players(): void
    {
        $host = $this->player('Host');
        $ben = $this->player('Ben');
        $cal = $this->player('Cal');
        $matchId = DB::table('live_matches')->insertGetId([
            'user_id' => $host->id, 'home' => 'Lions', 'away' => 'Tigers', 'status' => 'Scheduled',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ([[$ben, 'accepted'], [$cal, 'pending']] as [$u, $status]) {
            DB::table('match_join_requests')->insert([
                'match_id' => $matchId, 'requester_id' => $u->id, 'status' => $status,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // Not your match.
        $this->as($ben)->postJson("/api/dm/match/{$matchId}/team")->assertStatus(422);

        $id = $this->as($host)->postJson("/api/dm/match/{$matchId}/team")->assertCreated()
            ->assertJsonPath('name', 'Lions vs Tigers')
            ->assertJsonPath('member_count', 2)
            ->assertJsonPath('match_id', (string) $matchId)
            ->json('id');
        // Again: the same chat, not a second one.
        $this->as($host)->postJson("/api/dm/match/{$matchId}/team")->assertOk()->assertJsonPath('id', $id);
        // Ben (accepted, no follow) can talk in it; Cal (pending) isn't in it.
        $this->as($ben)->postJson("/api/dm/{$id}/messages", ['body' => 'I will bring the ball'])->assertCreated();
        $this->as($cal)->getJson("/api/dm/{$id}/messages")->assertNotFound();
    }
}
