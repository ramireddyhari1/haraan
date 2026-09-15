<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Hrms\EmployeeProfile;
use App\Models\LiveMatch;
use App\Models\User;
use App\Models\Venue;
use App\Support\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The authorization boundaries the September 2026 audit found missing. Each test names
 * the attack it defends against; a failure here is a security regression, not a flake.
 */
final class ApiAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $attrs = []): User
    {
        static $n = 0;
        $n++;

        return User::create(array_merge([
            'name' => "User {$n}",
            'email' => "user{$n}@haraan.test",
            'password' => Hash::make('secret-password'),
            'role' => 'USER',
            'status' => 'ACTIVE',
            'is_guest' => false,
        ], $attrs));
    }

    private function auth(User $user): array
    {
        $secret = (string) (config('app.jwt_secret') ?: env('JWT_SECRET', 'change_me'));

        return ['Authorization' => 'Bearer ' . JwtService::issueForUser($user->fresh(), $secret)];
    }

    private function venue(User $owner, string $name = 'Arena'): Venue
    {
        return Venue::create([
            'name' => $name, 'location' => 'Madhapur', 'price' => 1000,
            'is_active' => true, 'is_bookable' => true, 'partner_id' => $owner->id,
        ]);
    }

    // ------------------------------------------------------------------ /api/users

    public function test_a_member_cannot_list_accounts(): void
    {
        $member = $this->user();

        $this->withHeaders($this->auth($member))->getJson('/api/users')->assertStatus(403);
        $this->withHeaders($this->auth($member))->getJson('/api/users/partners')->assertStatus(403);
    }

    public function test_a_member_cannot_promote_themselves_to_admin(): void
    {
        $member = $this->user();

        $this->withHeaders($this->auth($member))
            ->patchJson("/api/users/{$member->id}/role", ['role' => 'ADMIN'])
            ->assertStatus(403);

        $this->assertSame('USER', strtoupper((string) $member->fresh()->role));
    }

    public function test_a_member_cannot_take_over_another_account(): void
    {
        $member = $this->user();
        $victim = $this->user();

        $this->withHeaders($this->auth($member))
            ->putJson("/api/users/{$victim->id}", ['email' => 'attacker@evil.test', 'password' => 'attacker-password'])
            ->assertStatus(403);

        $victim->refresh();
        $this->assertNotSame('attacker@evil.test', $victim->email);
        $this->assertTrue(Hash::check('secret-password', $victim->password));
    }

    public function test_a_member_cannot_create_partner_accounts(): void
    {
        $member = $this->user();

        $this->withHeaders($this->auth($member))
            ->postJson('/api/users/partners', ['name' => 'X', 'email' => 'x@haraan.test'])
            ->assertStatus(403);
    }

    public function test_an_admin_can_manage_accounts_within_the_rules(): void
    {
        $admin = $this->user(['role' => 'ADMIN']);
        $member = $this->user();

        $this->withHeaders($this->auth($admin))->getJson('/api/users')->assertOk();

        $this->withHeaders($this->auth($admin))
            ->patchJson("/api/users/{$member->id}/role", ['role' => 'PARTNER'])
            ->assertOk();
        $this->assertSame('PARTNER', $member->fresh()->role);

        // Free-text roles are rejected.
        $this->withHeaders($this->auth($admin))
            ->patchJson("/api/users/{$member->id}/role", ['role' => 'GOD'])
            ->assertStatus(422);

        // No self-service role changes, even for an admin.
        $this->withHeaders($this->auth($admin))
            ->patchJson("/api/users/{$admin->id}/role", ['role' => 'USER'])
            ->assertStatus(403);
    }

    public function test_a_coadmin_cannot_mint_an_admin(): void
    {
        $coadmin = $this->user(['role' => 'COADMIN']);
        $member = $this->user();
        $admin = $this->user(['role' => 'ADMIN']);

        $this->withHeaders($this->auth($coadmin))
            ->patchJson("/api/users/{$member->id}/role", ['role' => 'ADMIN'])
            ->assertStatus(403);

        $this->withHeaders($this->auth($coadmin))
            ->putJson("/api/users/{$admin->id}", ['password' => 'new-password-123'])
            ->assertStatus(403);
    }

    public function test_an_admin_password_reset_revokes_existing_sessions(): void
    {
        $admin = $this->user(['role' => 'ADMIN']);
        $member = $this->user();
        $oldHeaders = $this->auth($member);

        $this->withHeaders($this->auth($admin))
            ->putJson("/api/users/{$member->id}", ['password' => 'brand-new-password'])
            ->assertOk();

        $this->withHeaders($oldHeaders)->getJson('/api/auth/me')->assertStatus(401);
    }

    public function test_creating_a_partner_never_resets_an_existing_members_password(): void
    {
        $admin = $this->user(['role' => 'ADMIN']);
        $member = $this->user(['email' => 'owner@haraan.test']);

        $this->withHeaders($this->auth($admin))
            ->postJson('/api/users/partners', ['name' => 'Owner', 'email' => 'owner@haraan.test'])
            ->assertOk();

        $this->assertTrue(Hash::check('secret-password', $member->fresh()->password));
        $this->assertFalse(Hash::check('partner123', $member->fresh()->password));
    }

    // ----------------------------------------------------- partner venue isolation

    public function test_a_partner_cannot_open_another_partners_whatsapp_desk(): void
    {
        $partnerA = $this->user(['role' => 'PARTNER', 'partner_type' => 'venue']);
        $partnerB = $this->user(['role' => 'PARTNER', 'partner_type' => 'venue']);
        $venueA = $this->venue($partnerA, 'A Arena');

        $this->withHeaders($this->auth($partnerB))
            ->getJson("/api/partner/venues/{$venueA->id}/whatsapp/dashboard")
            ->assertStatus(404);
        $this->withHeaders($this->auth($partnerB))
            ->getJson("/api/partner/venues/{$venueA->id}/whatsapp/conversations")
            ->assertStatus(404);

        $this->withHeaders($this->auth($partnerA))
            ->getJson("/api/partner/venues/{$venueA->id}/whatsapp/dashboard")
            ->assertOk();
    }

    public function test_a_partner_cannot_read_another_partners_operations(): void
    {
        $partnerA = $this->user(['role' => 'PARTNER', 'partner_type' => 'venue']);
        $partnerB = $this->user(['role' => 'PARTNER', 'partner_type' => 'venue']);
        $venueA = $this->venue($partnerA, 'A Arena');

        foreach (['overview', 'revenue', 'occupancy', 'staff', 'funnel', 'alerts', 'suggestions'] as $page) {
            $this->withHeaders($this->auth($partnerB))
                ->getJson("/api/partner/venues/{$venueA->id}/operations/{$page}")
                ->assertStatus(404);
        }

        $this->withHeaders($this->auth($partnerA))
            ->getJson("/api/partner/venues/{$venueA->id}/operations/overview")
            ->assertOk();
    }

    public function test_check_in_staff_cannot_change_pricing_or_read_revenue(): void
    {
        $owner = $this->user(['role' => 'PARTNER', 'partner_type' => 'venue']);
        $venue = $this->venue($owner);
        $gate = $this->user([
            'role' => 'PARTNER', 'partner_type' => 'venue',
            'parent_partner_id' => $owner->id, 'staff_permissions' => ['checkin'],
        ]);

        $this->withHeaders($this->auth($gate))
            ->postJson("/api/partner/venues/{$venue->id}/pricing/rules", [])
            ->assertStatus(403);
        $this->withHeaders($this->auth($gate))
            ->getJson("/api/partner/venues/{$venue->id}/operations/revenue")
            ->assertStatus(403);
        $this->withHeaders($this->auth($gate))
            ->getJson("/api/partner/venues/{$venue->id}/whatsapp/dashboard")
            ->assertStatus(403);
    }

    public function test_a_members_account_cannot_reach_partner_routes(): void
    {
        $owner = $this->user(['role' => 'PARTNER', 'partner_type' => 'venue']);
        $venue = $this->venue($owner);

        $this->withHeaders($this->auth($this->user()))
            ->getJson("/api/partner/venues/{$venue->id}/operations/overview")
            ->assertStatus(403);
    }

    // --------------------------------------------------------- workforce / payroll

    public function test_a_member_cannot_run_payroll_for_a_venue(): void
    {
        $owner = $this->user(['role' => 'PARTNER', 'partner_type' => 'venue']);
        $venue = $this->venue($owner);
        $member = $this->user();
        $month = now()->format('Y-m');

        $this->withHeaders($this->auth($member))
            ->postJson('/api/v1/workforce/payroll/generate-batch', ['venue_id' => $venue->id, 'month' => $month])
            ->assertStatus(403);
        $this->withHeaders($this->auth($member))
            ->postJson('/api/v1/workforce/payroll/lock-batch', ['venue_id' => $venue->id, 'month' => $month])
            ->assertStatus(403);
        $this->withHeaders($this->auth($member))
            ->getJson("/api/v1/workforce/payroll/compliance-export?venue_id={$venue->id}&month={$month}")
            ->assertStatus(403);
        $this->withHeaders($this->auth($member))
            ->getJson("/api/v1/workforce/labor/forecast?venue_id={$venue->id}")
            ->assertStatus(403);
    }

    public function test_another_partner_cannot_run_payroll_for_a_venue(): void
    {
        $owner = $this->user(['role' => 'PARTNER', 'partner_type' => 'venue']);
        $rival = $this->user(['role' => 'PARTNER', 'partner_type' => 'venue']);
        $venue = $this->venue($owner);

        $this->withHeaders($this->auth($rival))
            ->getJson("/api/v1/workforce/labor/forecast?venue_id={$venue->id}")
            ->assertStatus(403);
    }

    public function test_nobody_can_punch_in_as_someone_else(): void
    {
        $owner = $this->user(['role' => 'PARTNER', 'partner_type' => 'venue']);
        $venue = $this->venue($owner);
        $worker = $this->user(['role' => 'EMPLOYEE']);
        $employee = EmployeeProfile::create([
            'user_id' => $worker->id, 'employee_code' => 'EMP-SEC-1',
            'venue_id' => $venue->id, 'employment_status' => 'active',
        ]);
        $stranger = $this->user(['role' => 'EMPLOYEE']);

        $this->withHeaders($this->auth($stranger))
            ->postJson('/api/v1/workforce/punch', [
                'employee_code' => $employee->employee_code,
                'punch_type' => 'in', 'latitude' => 17.44, 'longitude' => 78.38,
            ])
            ->assertStatus(403);
    }

    // ------------------------------------------------------------ match visibility

    private function match(User $creator, bool $private): LiveMatch
    {
        return LiveMatch::create([
            'title' => 'Friendly', 'home' => 'HHH', 'away' => 'KKJ',
            'home_score' => 0, 'away_score' => 0, 'status' => 'Live',
            'sport' => 'football', 'user_id' => $creator->id,
            'is_private' => $private, 'join_code' => $private ? 'SECRET1' : null,
        ]);
    }

    public function test_a_private_matchs_event_log_is_hidden_from_strangers(): void
    {
        $creator = $this->user(['player_id' => 'HRNCREATOR']);
        $match = $this->match($creator, true);

        $this->getJson("/api/matches/{$match->id}/events")->assertStatus(404);
        $this->withHeaders($this->auth($this->user()))->getJson("/api/matches/{$match->id}/events")->assertStatus(404);
        $this->flushHeaders();
        $this->getJson("/api/matches/{$match->id}/iq")->assertStatus(404);
        $this->getJson("/api/matches/{$match->id}/ground")->assertStatus(404);

        $this->withHeaders($this->auth($creator))->getJson("/api/matches/{$match->id}/events")->assertOk();
        $this->flushHeaders();
        $this->getJson("/api/matches/{$match->id}/events?code=secret1")->assertOk();
        $this->getJson("/api/matches/{$match->id}/events?code=WRONG")->assertStatus(404);
    }

    public function test_a_public_matchs_event_log_is_open(): void
    {
        $match = $this->match($this->user(), false);

        $this->getJson("/api/matches/{$match->id}/events")->assertOk();
    }

    // ---------------------------------------------------------- order creation

    public function test_free_amount_order_creation_is_not_exposed_outside_local(): void
    {
        $this->postJson('/api/create-order', ['amount' => 100])->assertStatus(404);
        $this->postJson('/api/verify-payment', [])->assertStatus(404);
    }
}
