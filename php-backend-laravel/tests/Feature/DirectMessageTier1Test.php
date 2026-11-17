<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\DeviceToken;
use App\Models\User;
use App\Support\PlatformRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * ActionBoard chat, tier 1: phone push, per-thread mute, photos, and group management.
 *
 * Push is tested against the REAL FcmClient with faked HTTP (the same setup the ticket
 * delivery tests use), so "who gets a notification" is what is asserted — not a mock.
 */
class DirectMessageTier1Test extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    private function player(string $name): User
    {
        $this->seq++;

        return User::create([
            'name' => $name, 'email' => strtolower($name).'@haraan.test',
            'password' => Hash::make('secret123'), 'role' => 'user', 'status' => 'active',
            'player_id' => 'HRN9'.str_pad((string) $this->seq, 4, '0', STR_PAD_LEFT),
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

    private function befriend(User $a, User $b): void
    {
        $a->follow($b);
        $b->follow($a);
    }

    private function dm(User $a, User $b): int
    {
        $this->befriend($a, $b);

        return (int) $this->as($a)->postJson('/api/dm/with/'.$b->player_id)->assertCreated()->json('id');
    }

    private function device(User $u, string $token): void
    {
        DeviceToken::create(['user_id' => $u->id, 'token' => $token, 'platform' => 'android']);
    }

    private function fakeFcm(): void
    {
        $path = storage_path('framework/testing/fcm-sa.json');
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, json_encode([
            'type' => 'service_account',
            'project_id' => 'haraan-test',
            'client_email' => 'test@haraan-test.iam.gserviceaccount.com',
            'private_key' => 'unused-the-access-token-is-pre-cached',
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ]));
        config(['services.fcm.credentials' => $path]);
        \Illuminate\Support\Facades\Cache::put('fcm.access_token', 'ya29.test', 3300);
        Http::fake([
            'fcm.googleapis.com/*' => Http::response(['name' => 'projects/haraan-test/messages/1'], 200),
            '*' => Http::response('', 200),
        ]);
    }

    /** @return array<int, array<string, string>> the data payload of every FCM send */
    private function pushes(): array
    {
        return collect(Http::recorded())
            ->filter(fn ($pair) => str_contains($pair[0]->url(), 'fcm.googleapis.com'))
            ->map(fn ($pair) => $pair[0]->data()['message'])
            ->values()
            ->all();
    }

    // ── Push ──────────────────────────────────────────────────────────────────────────

    public function test_a_message_is_pushed_to_the_other_side_only(): void
    {
        $this->fakeFcm();
        $a = $this->player('Ava');
        $b = $this->player('Ben');
        $id = $this->dm($a, $b);
        $this->device($a, 'tok-ava');
        $this->device($b, 'tok-ben');

        $this->as($a)->postJson("/api/dm/{$id}/messages", ['body' => 'Nets at 6?'])->assertCreated();
        // The push runs after the response; the test kernel terminates with each request.

        $sent = $this->pushes();
        $this->assertCount(1, $sent);
        $this->assertSame('tok-ben', $sent[0]['token']);
        $this->assertSame('Ava', $sent[0]['data']['title']);
        $this->assertSame('Nets at 6?', $sent[0]['data']['body']);
        $this->assertSame('dm', $sent[0]['data']['type']);
        $this->assertSame((string) $id, $sent[0]['data']['conversation_id']);
        $this->assertSame('haraan://chat/'.$id, $sent[0]['data']['deep_link']);
    }

    public function test_a_muted_thread_stays_quiet_and_unmuting_brings_it_back(): void
    {
        $this->fakeFcm();
        $a = $this->player('Ava');
        $b = $this->player('Ben');
        $id = $this->dm($a, $b);
        $this->device($b, 'tok-ben');

        $this->as($b)->postJson("/api/dm/{$id}/mute", ['hours' => 8])->assertOk()->assertJsonPath('muted', true);
        $this->as($b)->getJson('/api/dm')->assertJsonPath('results.0.muted', true);

        $this->as($a)->postJson("/api/dm/{$id}/messages", ['body' => 'hello'])->assertCreated();
        $this->assertCount(0, $this->pushes());

        $this->as($b)->postJson("/api/dm/{$id}/mute", ['hours' => 0])->assertOk()->assertJsonPath('muted', false);
        $again = (int) $this->as($a)->postJson("/api/dm/{$id}/messages", ['body' => 'again'])->assertCreated()->json('id');

        // The test kernel re-runs every earlier after-response callback on each request (a
        // real request is its own process), so assert on WHICH message was pushed, not a count.
        $ids = array_map(fn ($p) => $p['data']['message_id'], $this->pushes());
        $this->assertContains((string) $again, $ids);
    }

    public function test_the_control_switches_turn_push_off_and_hide_the_text(): void
    {
        $this->fakeFcm();
        $a = $this->player('Ava');
        $b = $this->player('Ben');
        $id = $this->dm($a, $b);
        $this->device($b, 'tok-ben');

        PlatformRules::save(['chat.push_preview' => false]);
        $this->as($a)->postJson("/api/dm/{$id}/messages", ['body' => 'secret plan'])->assertCreated();
        $this->assertSame('Sent you a message', $this->pushes()[0]['data']['body']);

        PlatformRules::save(['chat.push' => false]);
        $this->as($a)->postJson("/api/dm/{$id}/messages", ['body' => 'another'])->assertCreated();
        $this->assertCount(1, $this->pushes());
    }

    // ── Photos ────────────────────────────────────────────────────────────────────────

    public function test_a_photo_is_stored_shown_previewed_and_unsent(): void
    {
        Storage::fake('public');
        $a = $this->player('Ava');
        $b = $this->player('Ben');
        $id = $this->dm($a, $b);

        $res = $this->as($a)->post("/api/dm/{$id}/photo", [
            'image' => UploadedFile::fake()->image('team.jpg', 1200, 900),
            'body' => 'The squad',
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->assertSame(1200, $res->json('image_width'));
        $this->assertSame(900, $res->json('image_height'));
        $path = DB::table('direct_messages')->where('id', $res->json('id'))->value('image_path');
        Storage::disk('public')->assertExists($path);
        $this->assertStringStartsWith('chat-photos/'.$id.'/', $path);

        $this->as($b)->getJson("/api/dm/{$id}/messages")
            ->assertJsonPath('results.0.body', 'The squad')
            ->assertJsonPath('results.0.image_width', 1200);
        $this->assertNotNull($this->as($b)->getJson("/api/dm/{$id}/messages")->json('results.0.image'));
        $this->as($b)->getJson('/api/dm')->assertJsonPath('results.0.last_message', '📷 The squad');

        $this->as($a)->deleteJson("/api/dm/{$id}/messages/".$res->json('id'))->assertOk();
        Storage::disk('public')->assertMissing($path);
        $this->as($b)->getJson("/api/dm/{$id}/messages")->assertJsonPath('results.0.image', null);
    }

    public function test_photos_obey_the_control_switch_and_the_follow_rule(): void
    {
        Storage::fake('public');
        $a = $this->player('Ava');
        $b = $this->player('Ben');
        $id = $this->dm($a, $b);

        PlatformRules::save(['chat.photos' => false]);
        $this->as($a)->post("/api/dm/{$id}/photo", ['image' => UploadedFile::fake()->image('x.jpg')], ['Accept' => 'application/json'])
            ->assertStatus(403);

        PlatformRules::save(['chat.photos' => true]);
        $b->unfollow($a);
        $this->as($a)->post("/api/dm/{$id}/photo", ['image' => UploadedFile::fake()->image('x.jpg')], ['Accept' => 'application/json'])
            ->assertStatus(403);

        // A stranger can't post into a thread they're not in.
        $this->as($this->player('Cal'))->post("/api/dm/{$id}/photo", ['image' => UploadedFile::fake()->image('x.jpg')], ['Accept' => 'application/json'])
            ->assertNotFound();
    }

    // ── Groups ────────────────────────────────────────────────────────────────────────

    private function group(User $owner, array $members): int
    {
        foreach ($members as $m) {
            $this->befriend($owner, $m);
        }

        return (int) $this->as($owner)->postJson('/api/dm/group', [
            'title' => 'Sunday XI',
            'members' => array_map(fn (User $u) => $u->player_id, $members),
        ])->assertCreated()->json('id');
    }

    public function test_info_lists_members_with_the_admin_first(): void
    {
        $a = $this->player('Ava');
        $b = $this->player('Ben');
        $c = $this->player('Cal');
        $id = $this->group($a, [$b, $c]);

        $this->as($b)->getJson("/api/dm/{$id}/info")
            ->assertOk()
            ->assertJsonPath('is_admin', false)
            ->assertJsonPath('members.0.name', 'Ava')
            ->assertJsonPath('members.0.is_admin', true)
            ->assertJsonPath('members.1.is_me', true)
            ->assertJsonCount(3, 'members');
        $this->as($a)->getJson("/api/dm/{$id}/info")->assertJsonPath('is_admin', true);
    }

    public function test_adding_needs_a_mutual_follow_and_leaves_a_system_line(): void
    {
        $a = $this->player('Ava');
        $b = $this->player('Ben');
        $c = $this->player('Cal');
        $d = $this->player('Dev');
        $id = $this->group($a, [$b]);

        // Ben doesn't know Cal.
        $this->as($b)->postJson("/api/dm/{$id}/members", ['members' => [$c->player_id]])->assertStatus(403);

        $this->befriend($b, $c);
        $this->as($b)->postJson("/api/dm/{$id}/members", ['members' => [$c->player_id]])
            ->assertOk()->assertJsonPath('added', 1);

        $line = DB::table('direct_messages')->where('conversation_id', $id)->orderByDesc('id')->first();
        $this->assertSame('system', $line->kind);
        $this->assertSame('Ben added Cal', $line->body);
        // A system line is news, not something to answer: no badge.
        $this->assertSame(0, (int) DB::table('conversation_participants')
            ->where('conversation_id', $id)->where('user_id', $c->id)->value('unread_count'));

        // The /control cap.
        PlatformRules::save(['chat.group_max_members' => 3]);
        $this->befriend($a, $d);
        $this->as($a)->postJson("/api/dm/{$id}/members", ['members' => [$d->player_id]])->assertStatus(422);
    }

    public function test_only_the_admin_removes_and_admin_passes_on_when_they_leave(): void
    {
        $a = $this->player('Ava');
        $b = $this->player('Ben');
        $c = $this->player('Cal');
        $id = $this->group($a, [$b, $c]);

        $this->as($b)->deleteJson("/api/dm/{$id}/members/{$c->player_id}")->assertStatus(403);
        $this->as($a)->deleteJson("/api/dm/{$id}/members/{$c->player_id}")->assertOk();
        $this->as($c)->getJson("/api/dm/{$id}/messages")->assertNotFound();

        $this->as($a)->postJson("/api/dm/{$id}/leave")->assertOk();
        // The group is not left without anyone able to manage it.
        $this->as($b)->getJson("/api/dm/{$id}/info")->assertJsonPath('is_admin', true);
    }

    public function test_rename_and_group_photo(): void
    {
        Storage::fake('public');
        $a = $this->player('Ava');
        $b = $this->player('Ben');
        $id = $this->group($a, [$b]);

        $this->as($b)->postJson("/api/dm/{$id}/rename", ['title' => 'Kadapa Kings'])
            ->assertOk()->assertJsonPath('title', 'Kadapa Kings');
        $this->assertSame('Kadapa Kings', Conversation::find($id)->title);

        $this->as($b)->post("/api/dm/{$id}/avatar", ['image' => UploadedFile::fake()->image('g.png', 400, 400)], ['Accept' => 'application/json'])
            ->assertOk();
        $this->assertNotNull($this->as($a)->getJson('/api/dm')->json('results.0.avatar'));

        // Not for a 1:1.
        $c = $this->player('Cal');
        $one = $this->dm($a, $c);
        $this->as($a)->postJson("/api/dm/{$one}/rename", ['title' => 'x'])->assertStatus(422);
    }
}
