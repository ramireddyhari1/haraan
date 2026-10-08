<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\LiveMatch;
use App\Models\MatchJoinRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The web ActionBoard carries the app's tabs: Scheduled (Mine / Open near me) on the
 * board, MVP + Insights on a cricket match, Stats / Box score / Line-ups on the other
 * sports — and its join + follow actions go through the app's own API controllers.
 */
final class WebActionBoardParityTest extends TestCase
{
    use RefreshDatabase;

    private function player(string $name, string $pid): User
    {
        return User::create([
            'name' => $name,
            'email' => strtolower($pid).'@haraan.test',
            'password' => Hash::make('secret-password'),
            'role' => 'USER',
            'status' => 'ACTIVE',
            'is_guest' => false,
            'player_id' => $pid,
            'state' => 'Andhra Pradesh',
            'district' => 'YSR Kadapa',
        ]);
    }

    private function match(User $owner, array $attrs = []): LiveMatch
    {
        return LiveMatch::create(array_merge([
            'title' => 'Friendly', 'home' => 'Kadapa Kings', 'away' => 'Nellore XI',
            'home_score' => 0, 'away_score' => 0, 'status' => 'Scheduled',
            'sport' => 'cricket', 'user_id' => $owner->id, 'is_private' => false,
            'home_squad' => [['id' => 'HRNAAA001', 'name' => 'Arjun']],
            'away_squad' => [['id' => null, 'name' => 'Ravi']],
        ], $attrs));
    }

    public function test_scheduled_tab_lists_my_matches_and_open_matches_near_me(): void
    {
        $me = $this->player('Me', 'HRNME0001');
        $other = $this->player('Other', 'HRNOT0001');
        $this->match($me, ['home' => 'My Side FC', 'away' => 'Their Side FC']);
        $this->match($other, ['home' => 'Open Hosts', 'away' => 'Need Players', 'open_to_join' => true, 'slots_needed' => 2]);

        $this->actingAs($me)->get('/gamehub/actionboard')
            ->assertOk()
            ->assertSee('Scheduled')
            ->assertSee('Your scheduled matches')
            ->assertSee('My Side FC')
            ->assertSee('Open Hosts')
            ->assertSee('2 spots left')
            ->assertSee('Request to join');
    }

    public function test_guests_get_a_sign_in_prompt_on_the_mine_lane(): void
    {
        $this->get('/gamehub/actionboard')->assertOk()->assertSee("Sign in to see the matches you've scheduled.", false);
    }

    public function test_request_and_withdraw_join_through_the_session(): void
    {
        $owner = $this->player('Owner', 'HRNOW0001');
        $me = $this->player('Me', 'HRNME0002');
        $m = $this->match($owner, ['open_to_join' => true, 'slots_needed' => 1]);

        $this->actingAs($me)->postJson("/gamehub/actionboard/match/{$m->id}/join")->assertSuccessful();
        $this->assertSame(MatchJoinRequest::PENDING, MatchJoinRequest::where('match_id', $m->id)->value('status'));

        $this->actingAs($me)->deleteJson("/gamehub/actionboard/match/{$m->id}/join")->assertOk();
        $this->assertSame(MatchJoinRequest::CANCELLED, MatchJoinRequest::where('match_id', $m->id)->value('status'));
    }

    public function test_cannot_join_your_own_match_and_guests_cannot_join(): void
    {
        $owner = $this->player('Owner', 'HRNOW0002');
        $m = $this->match($owner, ['open_to_join' => true, 'slots_needed' => 1]);

        $this->actingAs($owner)->postJson("/gamehub/actionboard/match/{$m->id}/join")->assertStatus(422);
        auth()->logout();
        $this->postJson("/gamehub/actionboard/match/{$m->id}/join")->assertUnauthorized();
    }

    public function test_follow_and_unfollow_a_player_from_the_web(): void
    {
        $me = $this->player('Me', 'HRNME0003');
        $star = $this->player('Star', 'HRNST0001');

        $this->actingAs($me)->postJson('/gamehub/players/HRNST0001/follow')->assertOk();
        $this->assertTrue(DB::table('player_follows')->where('follower_id', $me->id)->where('followee_id', $star->id)->exists());

        $this->actingAs($me)->deleteJson('/gamehub/players/HRNST0001/follow')->assertOk();
        $this->assertFalse(DB::table('player_follows')->where('follower_id', $me->id)->where('followee_id', $star->id)->exists());
    }

    public function test_cricket_match_has_mvp_and_insights_tabs(): void
    {
        $owner = $this->player('Owner', 'HRNOW0003');
        $m = $this->match($owner, ['status' => 'Live']);

        $this->get("/gamehub/actionboard/match/{$m->id}/mvp")->assertOk()->assertSee('No impact yet')->assertSee('Insights');
        $this->get("/gamehub/actionboard/match/{$m->id}/insights")->assertOk()->assertSee('MVP');
    }

    public function test_team_sports_carry_their_app_tabs(): void
    {
        $owner = $this->player('Owner', 'HRNOW0004');
        $football = $this->match($owner, ['sport' => 'football', 'status' => 'Live']);
        $basketball = $this->match($owner, ['sport' => 'basketball', 'status' => 'Live']);

        $this->get("/gamehub/actionboard/match/{$football->id}?tab=stats")->assertOk()->assertSee('No match stats yet')->assertSee('Line-ups');
        $this->get("/gamehub/actionboard/match/{$football->id}?tab=lineups")->assertOk()->assertSee('Arjun')->assertSee('Ravi');
        $this->get("/gamehub/actionboard/match/{$basketball->id}?tab=box")->assertOk()->assertSee('Box score');
        // An old Players link on basketball lands on its Box score.
        $this->get("/gamehub/actionboard/match/{$basketball->id}?tab=players")->assertOk()->assertSee('appears here', false);
    }
}
