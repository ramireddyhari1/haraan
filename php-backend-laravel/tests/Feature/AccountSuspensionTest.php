<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\AccountSuspendedException;
use App\Models\User;
use App\Support\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Suspension used to be decorative: it changed a badge colour in /control and nothing
 * else. The JWT middleware never read `status`, so a suspended member kept full API
 * access for the remaining life of their 7-day token and could mint a fresh one simply
 * by signing in again.
 *
 * These lock the enforcement in at each layer that can be reached independently.
 */
class AccountSuspensionTest extends TestCase
{
    use RefreshDatabase;

    private function secret(): string
    {
        return (string) config('app.jwt_secret', 'test-secret');
    }

    private function user(array $attributes = []): User
    {
        return User::factory()->create($attributes + [
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);
    }

    public function test_status_casing_does_not_decide_whether_an_account_is_blocked(): void
    {
        // The column carries mixed casing across the table's history, so the gate has to
        // be case-insensitive or half the suspended accounts stay live.
        foreach (['SUSPENDED', 'suspended', 'Suspended', 'deleted', 'BANNED'] as $status) {
            $this->assertFalse(
                (new User(['status' => $status]))->isAccountActive(),
                "'{$status}' should block access",
            );
        }

        // A null/empty status predates the column and has never meant "suspended".
        foreach (['ACTIVE', 'active', '', null] as $status) {
            $this->assertTrue(
                (new User(['status' => $status]))->isAccountActive(),
                var_export($status, true).' should allow access',
            );
        }
    }

    public function test_a_suspended_account_cannot_obtain_a_new_token(): void
    {
        $user = $this->user(['status' => 'SUSPENDED']);

        $this->expectException(AccountSuspendedException::class);
        JwtService::issueForUser($user, $this->secret());
    }

    public function test_a_token_minted_before_suspension_stops_working_immediately(): void
    {
        $user = $this->user();
        $token = JwtService::issueForUser($user, $this->secret());

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/players/me')
            ->assertSuccessful();

        $user->update(['status' => 'SUSPENDED']);

        // 401, not 403: suspending bumps token_version, so the revocation check rejects
        // this token before the status check is even reached. That is the intended order
        // — the token is genuinely revoked, and 401 is what tells the client to stop
        // using it. The status gate below is the backstop for the case this does not
        // cover: a row whose status was changed without the version moving.
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/players/me')
            ->assertStatus(401);
    }

    public function test_the_status_gate_rejects_a_token_that_is_still_at_the_right_version(): void
    {
        // Belt and braces for the case the revocation check cannot see: the status column
        // moved without token_version moving with it — a direct database write, a legacy
        // row suspended before revocation existed, or any future writer that forgets.
        // Without this check such a token sails straight through.
        $user = $this->user();
        $token = JwtService::issueForUser($user, $this->secret());

        \Illuminate\Support\Facades\DB::table('users')
            ->where('id', $user->id)
            ->update(['status' => 'SUSPENDED']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/players/me')
            ->assertStatus(403)
            ->assertJsonPath('error', 'account_suspended');
    }

    public function test_suspending_through_the_model_revokes_every_live_session(): void
    {
        $user = $this->user();
        $before = (int) $user->token_version;

        $user->update(['status' => 'SUSPENDED']);

        $this->assertSame($before + 1, (int) $user->fresh()->token_version);
    }

    public function test_resetting_a_password_revokes_every_live_session(): void
    {
        $user = $this->user();
        $before = (int) $user->token_version;

        $user->update(['password' => 'a-completely-new-password']);

        $this->assertSame($before + 1, (int) $user->fresh()->token_version);
    }

    public function test_an_ordinary_profile_edit_does_not_sign_anyone_out(): void
    {
        $user = $this->user();
        $before = (int) $user->token_version;

        $user->update(['district' => 'Chennai']);

        $this->assertSame($before, (int) $user->fresh()->token_version);
    }

    public function test_reactivating_does_not_sign_anyone_out(): void
    {
        $user = $this->user(['status' => 'SUSPENDED']);
        $before = (int) $user->fresh()->token_version;

        $user->update(['status' => 'ACTIVE']);

        $this->assertSame($before, (int) $user->fresh()->token_version);
    }

    public function test_a_suspended_admin_cannot_open_the_control_panel(): void
    {
        $admin = $this->user(['role' => 'ADMIN', 'status' => 'SUSPENDED']);
        $panel = \Filament\Facades\Filament::getPanel('control');

        $this->assertFalse($admin->canAccessPanel($panel));
        $this->assertTrue(
            $this->user(['role' => 'ADMIN'])->canAccessPanel($panel),
            'an active admin should still get in',
        );
    }
}
