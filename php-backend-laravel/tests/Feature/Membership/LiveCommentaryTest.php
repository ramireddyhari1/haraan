<?php

declare(strict_types=1);

namespace Tests\Feature\Membership;

use App\Models\LiveMatch;
use App\Models\MatchCommentarySession;
use App\Models\User;
use App\Services\MatchCommentary;
use App\Services\Membership\MemberEntitlements;
use App\Support\PlatformRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Live commentary: Hero scorers go on air, everyone listens free, and every way a broadcast
 * can end — the commentator, the match, the clock, a dead phone, reports, /control — ends it.
 */
class LiveCommentaryTest extends TestCase
{
    use MembershipFixtures;
    use RefreshDatabase;

    private int $participants = 1;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureRazorpay();
        config([
            'services.livekit.url' => 'wss://live.haraan.test',
            'services.livekit.key' => 'lk_key',
            'services.livekit.secret' => 'lk_secret_lk_secret_lk_secret_32',
        ]);
        Http::fake(function (HttpRequest $request) {
            return str_ends_with($request->url(), '/ListParticipants')
                ? Http::response(['participants' => array_fill(0, $this->participants, ['identity' => 'x'])])
                : Http::response([]);
        });
    }

    public function test_a_free_scorer_is_told_commentary_is_part_of_hero(): void
    {
        $scorer = $this->player();
        $match = $this->match($scorer);

        $this->asMember($scorer)->postJson("/api/matches/{$match->id}/commentary")
            ->assertStatus(403)
            ->assertJsonPath('code', 'upgrade_required')
            ->assertJsonPath('feature', 'matches.live_commentary')
            ->assertJsonPath('upgrade_plan', 'hero');

        $this->assertSame(0, MatchCommentarySession::count());
        Http::assertNothingSent();
    }

    public function test_pro_is_not_enough_but_hero_goes_on_air_with_a_publish_token(): void
    {
        $scorer = $this->player();
        $match = $this->match($scorer);

        $this->paidSubscription($scorer, 'pro');
        $this->asMember($scorer)->postJson("/api/matches/{$match->id}/commentary")->assertStatus(403);

        $this->paidSubscription($scorer, 'hero');
        MemberEntitlements::flush();

        $res = $this->asMember($scorer)->postJson("/api/matches/{$match->id}/commentary")
            ->assertCreated()
            ->assertJsonPath('onAir', true)
            ->assertJsonPath('url', 'wss://live.haraan.test')
            ->assertJsonPath('heartbeatSeconds', MatchCommentary::HEARTBEAT_SECONDS);

        $claims = $this->claims((string) $res->json('token'));
        $this->assertSame('lk_key', $claims['iss']);
        $this->assertTrue($claims['video']['canPublish']);
        $this->assertFalse($claims['video']['canSubscribe']);
        $this->assertSame($res->json('room'), $claims['video']['room']);
        $this->assertTrue($this->signatureValid((string) $res->json('token')));

        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/CreateRoom')
            && $r['max_participants'] === PlatformRules::int('commentary.max_listeners') + 1);
    }

    public function test_only_the_scorer_can_go_on_air(): void
    {
        $owner = $this->player();
        $match = $this->match($owner);
        $other = $this->player();
        $this->paidSubscription($other, 'hero');

        $this->asMember($other)->postJson("/api/matches/{$match->id}/commentary")
            ->assertStatus(403)->assertJsonPath('code', 'not_scorer');
    }

    public function test_anyone_can_listen_free_with_a_listen_only_token_and_the_detail_shows_the_speaker(): void
    {
        [$scorer, $match] = $this->onAir();

        $this->getJson("/api/live-matches/{$match->id}")
            ->assertOk()
            ->assertJsonPath('liveCommentary.onAir', true)
            ->assertJsonPath('liveCommentary.by', $scorer->name);

        // A guest — no account, no plan.
        $res = $this->postJson("/api/live-matches/{$match->id}/commentary/listen", ['viewer' => 'install-abc'])
            ->assertOk()
            ->assertJsonPath('onAir', true);
        $claims = $this->claims((string) $res->json('token'));
        $this->assertFalse($claims['video']['canPublish']);
        $this->assertTrue($claims['video']['canSubscribe']);

        // A free member too.
        $this->asMember($this->member())->postJson("/api/live-matches/{$match->id}/commentary/listen")->assertOk();
    }

    public function test_listening_with_nobody_on_air_says_so(): void
    {
        $match = $this->match($this->player());

        $this->getJson("/api/live-matches/{$match->id}")->assertJsonPath('liveCommentary.onAir', false);
        $this->postJson("/api/live-matches/{$match->id}/commentary/listen")
            ->assertNotFound()->assertJsonPath('code', 'off_air');
    }

    public function test_heartbeat_counts_listeners_and_a_full_room_turns_listeners_away(): void
    {
        [$scorer, $match, $session] = $this->onAir();
        PlatformRules::save(['commentary.max_listeners' => 5]);

        $this->participants = 6; // commentator + 5
        $this->asMember($scorer)->postJson("/api/matches/{$match->id}/commentary/{$session}/heartbeat", ['muted' => false])
            ->assertOk()->assertJsonPath('onAir', true)->assertJsonPath('listeners', 5);

        $this->assertSame(5, MatchCommentarySession::find($session)->peak_listeners);
        $this->postJson("/api/live-matches/{$match->id}/commentary/listen")
            ->assertStatus(409)->assertJsonPath('code', 'full');
    }

    public function test_the_commentator_stops_and_the_room_is_closed(): void
    {
        [$scorer, $match, $session] = $this->onAir();

        $this->asMember($scorer)->deleteJson("/api/matches/{$match->id}/commentary/{$session}", ['reason' => 'silence'])
            ->assertOk()->assertJsonPath('onAir', false)->assertJsonPath('reason', 'silence');

        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/DeleteRoom'));
        $this->getJson("/api/live-matches/{$match->id}")->assertJsonPath('liveCommentary.onAir', false);
    }

    public function test_a_phone_that_stops_beating_falls_off_air(): void
    {
        [, $match, $session] = $this->onAir();

        $this->travel(MatchCommentary::STALE_AFTER_SECONDS + 5)->seconds();

        $this->getJson("/api/live-matches/{$match->id}")->assertJsonPath('liveCommentary.onAir', false);
        $this->assertSame('stale', MatchCommentarySession::find($session)->ended_reason);
    }

    public function test_the_time_limit_ends_the_broadcast_on_the_next_heartbeat(): void
    {
        [$scorer, $match, $session] = $this->onAir();
        PlatformRules::save(['commentary.max_minutes' => 5]);

        $this->travel(6)->minutes();
        // keep it from going stale so the time limit is what ends it
        MatchCommentarySession::whereKey($session)->update(['last_heartbeat_at' => now()]);

        $this->asMember($scorer)->postJson("/api/matches/{$match->id}/commentary/{$session}/heartbeat")
            ->assertOk()->assertJsonPath('onAir', false)->assertJsonPath('reason', 'time_limit');
    }

    public function test_finishing_the_match_ends_commentary(): void
    {
        [, $match, $session] = $this->onAir();

        app(\App\Services\MatchCompletion::class)->finish($match->fresh(), 'completed');

        $this->assertSame('match_finished', MatchCommentarySession::find($session)->ended_reason);
    }

    public function test_enough_listener_reports_take_it_off_air_and_each_listener_counts_once(): void
    {
        [, $match, $session] = $this->onAir();
        PlatformRules::save(['commentary.reports_to_end' => 2]);

        $guest = ['reason' => 'abusive', 'viewer' => 'install-1'];
        $this->postJson("/api/live-matches/commentary/{$session}/report", $guest)->assertOk()->assertJsonPath('offAir', false);
        $this->postJson("/api/live-matches/commentary/{$session}/report", $guest)->assertOk()->assertJsonPath('offAir', false);

        $this->postJson("/api/live-matches/commentary/{$session}/report", ['reason' => 'spam', 'viewer' => 'install-2'])
            ->assertOk()->assertJsonPath('offAir', true);
        $this->assertSame('reports', MatchCommentarySession::find($session)->ended_reason);
    }

    public function test_control_switch_off_ends_live_broadcasts_and_blocks_new_ones(): void
    {
        [$scorer, $match, $session] = $this->onAir();
        PlatformRules::save(['commentary.enabled' => false]);

        $this->asMember($scorer)->postJson("/api/matches/{$match->id}/commentary/{$session}/heartbeat")
            ->assertJsonPath('onAir', false)->assertJsonPath('reason', 'switched_off');
        $this->asMember($scorer)->postJson("/api/matches/{$match->id}/commentary")
            ->assertStatus(503)->assertJsonPath('code', 'commentary_off');
    }

    public function test_admin_take_off_air_is_seen_by_the_commentators_phone(): void
    {
        [$scorer, $match, $session] = $this->onAir();
        $admin = $this->member();

        app(MatchCommentary::class)->end(MatchCommentarySession::find($session), 'admin', $admin);

        $this->asMember($scorer)->postJson("/api/matches/{$match->id}/commentary/{$session}/heartbeat")
            ->assertJsonPath('onAir', false)
            ->assertJsonPath('reason', 'admin')
            ->assertJsonPath('message', 'Haraan took your commentary off air.');
        $this->assertSame($admin->id, MatchCommentarySession::find($session)->ended_by);
    }

    public function test_going_on_air_again_closes_the_earlier_broadcast(): void
    {
        [$scorer, $match, $first] = $this->onAir();

        $second = $this->asMember($scorer)->postJson("/api/matches/{$match->id}/commentary")->assertCreated()->json('session');

        $this->assertNotSame($first, $second);
        $this->assertSame('restarted', MatchCommentarySession::find($first)->ended_reason);
        $this->assertSame(1, MatchCommentarySession::open()->count());
    }

    public function test_private_matches_follow_the_rule(): void
    {
        $scorer = $this->player();
        $this->paidSubscription($scorer, 'hero');
        $match = $this->match($scorer);
        $match->forceFill(['is_private' => true])->save();

        PlatformRules::save(['commentary.private_matches' => false]);
        $this->asMember($scorer)->postJson("/api/matches/{$match->id}/commentary")
            ->assertStatus(422)->assertJsonPath('code', 'private_match');
    }

    public function test_without_an_audio_server_nobody_goes_on_air(): void
    {
        config(['services.livekit.key' => null]);
        $scorer = $this->player();
        $this->paidSubscription($scorer, 'hero');
        $match = $this->match($scorer);

        $this->asMember($scorer)->postJson("/api/matches/{$match->id}/commentary")
            ->assertStatus(503)->assertJsonPath('code', 'unavailable');
    }

    public function test_the_website_match_page_shows_the_speaker_only_while_on_air(): void
    {
        $match = $this->match($this->player());
        $page = "/gamehub/actionboard/match/{$match->id}";

        $this->get($page)->assertOk()->assertDontSee('data-commentary-speaker data-match', false);
        $this->getJson("/api/live-matches/{$match->id}/pulse")->assertOk()->assertJsonPath('commentary', false);

        [, $live] = $this->onAir();
        $this->get("/gamehub/actionboard/match/{$live->id}")
            ->assertOk()
            ->assertSee('data-commentary-speaker data-match', false)
            ->assertSee('livekit-client@2.22.4', false);
        $this->getJson("/api/live-matches/{$live->id}/pulse")->assertOk()->assertJsonPath('commentary', true);
    }

    public function test_other_sports_scorers_go_on_air_and_their_web_page_gets_the_speaker(): void
    {
        $scorer = $this->player();
        $this->paidSubscription($scorer, 'hero');
        $match = LiveMatch::create(['sport' => 'football', 'status' => 'live', 'home' => 'KFC', 'away' => 'MUN', 'title' => 'KFC vs MUN']);
        $match->forceFill(['user_id' => $scorer->id])->save();

        $page = "/gamehub/actionboard/match/{$match->id}";
        $this->get($page)->assertOk()->assertDontSee('data-commentary-speaker data-match', false);

        $this->asMember($scorer)->postJson("/api/matches/{$match->id}/commentary")->assertCreated();
        $this->flushHeaders();

        $this->get($page)
            ->assertOk()
            ->assertSee('data-commentary-speaker data-match', false)
            ->assertSee('livekit-client@2.22.4', false);
        $this->postJson("/api/live-matches/{$match->id}/commentary/listen")->assertOk()->assertJsonPath('onAir', true);
    }

    /** @return array{0: User, 1: LiveMatch, 2: int} */
    private function onAir(): array
    {
        $scorer = $this->player();
        $this->paidSubscription($scorer, 'hero');
        $match = $this->match($scorer);
        $session = (int) $this->asMember($scorer)->postJson("/api/matches/{$match->id}/commentary")
            ->assertCreated()->json('session');
        // Later requests are listeners, not the scorer, unless a test signs in again.
        $this->flushHeaders();

        return [$scorer, $match, $session];
    }

    private function match(User $owner): LiveMatch
    {
        $match = LiveMatch::create(['sport' => 'cricket', 'status' => 'live', 'home' => 'KDK', 'away' => 'NLW', 'title' => 'KDK vs NLW']);
        $match->forceFill(['user_id' => $owner->id])->save();

        return $match;
    }

    /** @return array<string, mixed> */
    private function claims(string $jwt): array
    {
        $part = explode('.', $jwt)[1] ?? '';

        return (array) json_decode((string) base64_decode(strtr($part, '-_', '+/')), true);
    }

    private function signatureValid(string $jwt): bool
    {
        [$h, $b, $s] = explode('.', $jwt);
        $expected = rtrim(strtr(base64_encode(hash_hmac('sha256', "$h.$b", 'lk_secret_lk_secret_lk_secret_32', true)), '+/', '-_'), '=');

        return hash_equals($expected, $s);
    }
}
