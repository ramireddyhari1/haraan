<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Profile completion, measured in the player's own sport.
 *
 * The bug this defends against: the app computed completion against CRICKET's fields
 * (batting style, bowling style), so a footballer was asked for a batting style they
 * have no way to set and could never reach 100% however complete their profile was.
 * The demo footballer on prod sat at 37% permanently.
 */
class ProfileCompletionTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    /**
     * @param  array<string, string>  $attrs
     */
    private function player(string $sport, array $attrs, ?string $avatar = 'a.jpg'): User
    {
        $this->seq++;

        return User::create([
            'name' => 'P' . $this->seq, 'email' => 'p' . $this->seq . '@haraan.test',
            'password' => Hash::make('secret123'), 'role' => 'user', 'status' => 'active',
            'player_id' => 'HRN' . str_pad((string) $this->seq, 5, '0', STR_PAD_LEFT),
            'is_guest' => false,
            'state' => 'Andhra Pradesh', 'district' => 'YSR Kadapa',
            'avatar' => $avatar,
            'primary_sport' => $sport,
            'sport_attributes' => $attrs,
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
    private function completion(User $user): array
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token($user))
            ->getJson('/api/players/me')
            ->assertOk()
            ->json('profile_completion');
    }

    public function test_a_fully_set_up_footballer_reaches_one_hundred_percent(): void
    {
        $c = $this->completion($this->player('Football', ['position' => 'Midfielder', 'foot' => 'Right']));

        self::assertSame(100, $c['pct']);
        self::assertSame([], $c['missing']);
    }

    /** The actual reported bug: never asked for a cricket field. */
    public function test_a_footballer_is_never_asked_for_batting_or_bowling(): void
    {
        $c = $this->completion($this->player('Football', ['position' => 'Midfielder']));

        self::assertContains('foot', $c['missing']);
        self::assertNotContains('batting', $c['missing']);
        self::assertNotContains('bowling', $c['missing']);
    }

    public function test_a_cricketer_is_still_asked_for_batting_and_bowling(): void
    {
        $c = $this->completion($this->player('Cricket', ['role' => 'All-rounder']));

        self::assertContains('batting', $c['missing']);
        self::assertContains('bowling', $c['missing']);
        self::assertNotContains('role', $c['missing']);
    }

    public function test_badminton_asks_for_its_own_attributes(): void
    {
        $c = $this->completion($this->player('Badminton', ['hand' => 'Right']));

        self::assertContains('format', $c['missing']);
        self::assertNotContains('hand', $c['missing']);
        self::assertNotContains('batting', $c['missing']);
    }

    public function test_a_missing_avatar_counts_but_does_not_dominate(): void
    {
        $c = $this->completion($this->player('Football', ['position' => 'Midfielder', 'foot' => 'Right'], avatar: null));

        self::assertSame(['avatar'], $c['missing']);
        // state + district + avatar + position + foot = 5 checks, 4 filled.
        self::assertSame(80, $c['pct']);
    }
}
