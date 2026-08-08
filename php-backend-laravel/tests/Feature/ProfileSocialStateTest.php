<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Follow state on the player profile.
 *
 * The follow system was built and wired into player SEARCH, but the profile — the one
 * screen where following belongs — carried none of it. These tests defend the part
 * that is easy to get silently wrong: `GET /api/players/{id}` is a PUBLIC route, so
 * without optional auth `auth_user` is never populated and `is_following` reads false
 * for everybody, meaning the button opens as "Follow" even for someone you already
 * follow.
 */
class ProfileSocialStateTest extends TestCase
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

    private function token(User $user): string
    {
        return \App\Support\JwtService::issue(
            ['sub' => $user->id],
            (string) config('app.jwt_secret', env('JWT_SECRET', 'change_me')),
        );
    }

    /** @return array<string, mixed> */
    private function socialAs(?User $viewer, User $target): array
    {
        $req = $viewer === null
            ? $this
            : $this->withHeader('Authorization', 'Bearer ' . $this->token($viewer));

        return $req->getJson('/api/players/' . $target->player_id)
            ->assertOk()
            ->json('social');
    }

    public function test_counts_reflect_the_real_follow_graph(): void
    {
        $star = $this->player('Star');
        $this->player('FanA')->follow($star);
        $this->player('FanB')->follow($star);
        $star->follow($this->player('Idol'));

        $social = $this->socialAs(null, $star);

        self::assertSame(2, $social['followers_count']);
        self::assertSame(1, $social['following_count']);
    }

    /**
     * The regression this route's missing middleware would cause: a signed-in viewer
     * who already follows the player must see is_following = true.
     */
    public function test_a_signed_in_follower_sees_is_following_true(): void
    {
        $star = $this->player('Star');
        $fan = $this->player('Fan');
        $fan->follow($star);

        $social = $this->socialAs($fan, $star);

        self::assertTrue($social['is_following'], 'optional auth must populate the viewer');
        self::assertTrue($social['can_follow']);
        self::assertFalse($social['is_self']);
    }

    public function test_a_non_follower_can_follow_but_is_not_following(): void
    {
        $social = $this->socialAs($this->player('Stranger'), $this->player('Star'));

        self::assertFalse($social['is_following']);
        self::assertTrue($social['can_follow']);
    }

    /** You cannot follow yourself — that slot becomes Share instead. */
    public function test_your_own_profile_is_flagged_self_and_never_followable(): void
    {
        $me = $this->player('Me');

        $social = $this->socialAs($me, $me);

        self::assertTrue($social['is_self']);
        self::assertFalse($social['can_follow']);
        self::assertFalse($social['is_following']);
    }

    /** A shared profile link must still open for someone with no account. */
    public function test_a_guest_still_gets_the_profile_but_cannot_follow(): void
    {
        $social = $this->socialAs(null, $this->player('Star'));

        self::assertFalse($social['can_follow']);
        self::assertFalse($social['is_following']);
        self::assertFalse($social['is_self']);
    }

    /** `me` is always self, whichever endpoint it came through. */
    public function test_the_me_endpoint_reports_self(): void
    {
        $me = $this->player('Me');

        $social = $this->withHeader('Authorization', 'Bearer ' . $this->token($me))
            ->getJson('/api/players/me')
            ->assertOk()
            ->json('social');

        self::assertTrue($social['is_self']);
        self::assertFalse($social['can_follow']);
    }
}
