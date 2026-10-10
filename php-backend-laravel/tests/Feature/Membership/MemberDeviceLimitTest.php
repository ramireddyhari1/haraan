<?php

declare(strict_types=1);

namespace Tests\Feature\Membership;

use App\Http\Middleware\EnforceMemberDevice;
use App\Models\AppSetting;
use App\Models\MemberDevice;
use App\Models\MemberEntitlementOverride;
use App\Models\User;
use App\Services\Membership\MemberDevices;
use App\Services\Membership\MemberEntitlements;
use App\Support\JwtService;
use App\Support\Membership\MemberFeature;
use App\Support\Membership\MembershipSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Device limits: Free 1, Pro 1, Hero 3 (account.devices), enforced on the app's JWT and the
 * website's session. Every sign-in here goes through the real login endpoint with the headers
 * the member app sends.
 */
class MemberDeviceLimitTest extends TestCase
{
    use MembershipFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureRazorpay();
    }

    private function signIn(User $user, string $installId, string $name = 'Test Phone'): string
    {
        $response = $this->withHeaders([
            MemberDevices::CLIENT_HEADER => MemberDevices::CLIENT_ANDROID,
            'X-Device-Id' => $installId,
            'X-Device-Name' => $name,
            'X-App-Version' => '9.9.9',
        ])->postJson('/api/auth/password', ['email' => $user->email, 'password' => 'secret'])->assertOk();

        $this->flushHeaders();
        MemberEntitlements::flush();

        return (string) $response->json('token');
    }

    private function as(string $token): static
    {
        $this->flushHeaders();
        MemberEntitlements::flush();

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }

    private function me(string $token): TestResponse
    {
        // /api/account/privacy is an ordinary member route — not on the chooser's allow-list.
        return $this->as($token)->getJson('/api/account/privacy');
    }

    public function test_free_member_gets_one_device_and_the_second_waits_at_the_chooser(): void
    {
        $user = $this->member();

        $phoneA = $this->signIn($user, 'install-a', 'Realme 9');
        $this->me($phoneA)->assertOk();

        $phoneB = $this->signIn($user, 'install-b', 'Pixel 8');
        $this->me($phoneB)->assertStatus(403)->assertJsonPath('error', 'device_limit_reached');

        // The phone already in use is never locked out by a new sign-in.
        $this->me($phoneA)->assertOk();

        $state = $this->as($phoneB)->getJson('/api/account/devices')->assertOk();
        $state->assertJsonPath('blocked', true)->assertJsonPath('limit', 1)->assertJsonPath('used', 1);
        // Pro allows no more devices than Free, so the offer skips straight to Hero.
        $state->assertJsonPath('upgrade.code', 'hero');
        $this->assertCount(2, $state->json('devices'));
        $this->assertTrue($state->json('devices.0.this_device'));
    }

    public function test_signing_out_another_device_from_the_chooser_lets_the_new_one_in(): void
    {
        $user = $this->member();
        $phoneA = $this->signIn($user, 'install-a');
        $phoneB = $this->signIn($user, 'install-b');

        $devices = $this->as($phoneB)->getJson('/api/account/devices')->json('devices');
        $other = collect($devices)->firstWhere('this_device', false)['id'];

        $this->as($phoneB)->deleteJson("/api/account/devices/{$other}")
            ->assertOk()->assertJsonPath('blocked', false);

        $this->me($phoneB)->assertOk();
        $this->me($phoneA)->assertStatus(401)->assertJsonPath('error', 'device_signed_out');
    }

    public function test_hero_allows_three_devices_and_holds_the_fourth(): void
    {
        $user = $this->member();
        $this->paidSubscription($user, 'hero');

        $tokens = [];
        foreach (['a', 'b', 'c', 'd'] as $i) {
            $tokens[$i] = $this->signIn($user, "install-{$i}");
        }

        $this->me($tokens['a'])->assertOk();
        $this->me($tokens['b'])->assertOk();
        $this->me($tokens['c'])->assertOk();
        $this->me($tokens['d'])->assertStatus(403);
    }

    public function test_pro_allows_one_device_and_offers_hero(): void
    {
        $user = $this->member();
        $this->paidSubscription($user, 'pro');

        $this->signIn($user, 'install-a');
        $second = $this->signIn($user, 'install-b');

        $this->as($second)->getJson('/api/account/devices')
            ->assertJsonPath('blocked', true)
            ->assertJsonPath('plan.code', 'pro')
            ->assertJsonPath('upgrade.code', 'hero')
            ->assertJsonPath('upgrade.limit', 3);
    }

    public function test_signing_in_again_on_the_same_phone_keeps_its_slot(): void
    {
        $user = $this->member();
        $first = $this->signIn($user, 'install-a');
        $again = $this->signIn($user, 'install-a');

        $this->me($first)->assertOk();
        $this->me($again)->assertOk();
        $this->assertSame(1, MemberDevice::query()->where('user_id', $user->id)->count());
    }

    public function test_downgrade_keeps_the_most_recently_used_device(): void
    {
        $user = $this->member();
        $subscription = $this->paidSubscription($user, 'hero');

        $old = $this->signIn($user, 'install-old');
        $recent = $this->signIn($user, 'install-recent');
        MemberDevice::query()->where('install_id', 'install-old')->update(['last_active_at' => now()->subDays(2)]);

        $subscription->forceFill(['status' => 'cancelled'])->save();
        MemberEntitlements::flush();

        $this->me($recent)->assertOk();
        $this->me($old)->assertStatus(403)->assertJsonPath('error', 'device_limit_reached');
    }

    public function test_upgrading_lets_a_waiting_device_in_without_signing_anything_out(): void
    {
        $user = $this->member();
        $this->signIn($user, 'install-a');
        $waiting = $this->signIn($user, 'install-b');
        $this->me($waiting)->assertStatus(403);

        $this->paidSubscription($user, 'hero');

        $this->me($waiting)->assertOk();
    }

    public function test_admin_override_and_the_off_switch_are_honoured(): void
    {
        $user = $this->member();
        $this->signIn($user, 'install-a');
        $waiting = $this->signIn($user, 'install-b');
        $this->me($waiting)->assertStatus(403);

        AppSetting::set(MembershipSettings::storageKey(MembershipSettings::DEVICE_LIMITS_KEY), '0', MembershipSettings::GROUP);
        $this->me($waiting)->assertOk();

        AppSetting::set(MembershipSettings::storageKey(MembershipSettings::DEVICE_LIMITS_KEY), '1', MembershipSettings::GROUP);
        $third = $this->signIn($user, 'install-c');
        $this->me($third)->assertStatus(403);

        MemberEntitlementOverride::query()->create([
            'user_id' => $user->id, 'feature_key' => MemberFeature::ACCOUNT_DEVICES,
            'enabled' => true, 'limit_value' => 5, 'reason' => 'test',
        ]);
        $this->me($third)->assertOk();
    }

    public function test_logout_signs_out_only_this_device(): void
    {
        $user = $this->member();
        $this->paidSubscription($user, 'hero');
        $phoneA = $this->signIn($user, 'install-a');
        $phoneB = $this->signIn($user, 'install-b');

        $this->as($phoneA)->postJson('/api/auth/logout')->assertOk()->assertJsonPath('scope', 'device');

        $this->me($phoneA)->assertStatus(401);
        $this->me($phoneB)->assertOk();
    }

    public function test_an_expired_phone_frees_its_slot(): void
    {
        $user = $this->member();
        $this->signIn($user, 'install-a');
        MemberDevice::query()->where('install_id', 'install-a')->update(['expires_at' => now()->subMinute()]);

        $phoneB = $this->signIn($user, 'install-b');
        $this->me($phoneB)->assertOk();
    }

    public function test_tokens_without_a_device_are_untouched_and_can_enroll(): void
    {
        $user = $this->member();
        $legacy = JwtService::issueForUser($user, (string) config('app.jwt_secret'));
        $this->assertArrayNotHasKey('sid', JwtService::decode($legacy, (string) config('app.jwt_secret')));
        $this->me($legacy)->assertOk();

        $enrolled = $this->as($legacy)->withHeaders([
            MemberDevices::CLIENT_HEADER => MemberDevices::CLIENT_ANDROID,
            'X-Device-Id' => 'install-legacy',
        ])->postJson('/api/account/devices/enroll')->assertOk();

        $token = (string) $enrolled->json('token');
        $this->assertArrayHasKey('sid', JwtService::decode($token, (string) config('app.jwt_secret')));
        $this->me($token)->assertOk();
    }

    public function test_partner_app_sign_ins_never_take_a_device(): void
    {
        $user = $this->member();
        $this->postJson('/api/auth/password', ['email' => $user->email, 'password' => 'secret'])->assertOk();

        $this->assertSame(0, MemberDevice::query()->where('user_id', $user->id)->count());
    }

    public function test_a_waiting_device_browses_public_feeds_as_a_guest(): void
    {
        $user = $this->member();
        $this->signIn($user, 'install-a');
        $waiting = $this->signIn($user, 'install-b');

        // Optional-auth routes never refuse; the held device just isn't resolved as the member.
        $this->as($waiting)->getJson('/api/membership/plans')->assertOk();
    }

    public function test_website_browser_counts_as_a_device_and_is_held_past_the_limit(): void
    {
        $user = $this->member();
        $phone = $this->signIn($user, 'install-a');

        $this->flushHeaders();
        $this->actingAs($user)->get('/profile')->assertRedirect(route('site.account.devices'));
        $this->actingAs($user)->get('/account/devices')->assertOk()->assertSee('Signed-in devices');

        $browser = MemberDevice::query()->where('user_id', $user->id)->where('surface', 'web')->firstOrFail();
        $this->assertSame(MemberDevice::STATUS_PENDING, $browser->status);

        $phoneId = MemberDevice::query()->where('install_id', 'install-a')->value('public_id');
        $this->actingAs($user)->withSession([EnforceMemberDevice::SESSION_KEY => $browser->public_id])
            ->post(route('site.account.devices.remove', ['id' => $phoneId]))
            ->assertRedirect(route('site.profile'));

        $this->me($phone)->assertStatus(401);
        $this->actingAs($user)->withSession([EnforceMemberDevice::SESSION_KEY => $browser->public_id])
            ->get('/profile')->assertOk();
    }

    public function test_a_browser_signed_out_from_the_app_is_logged_out(): void
    {
        $user = $this->member();
        $this->paidSubscription($user, 'hero');

        $this->actingAs($user)->get('/profile')->assertOk();
        $browser = MemberDevice::query()->where('user_id', $user->id)->where('surface', 'web')->firstOrFail();

        $phone = $this->signIn($user, 'install-a');
        $this->as($phone)->deleteJson("/api/account/devices/{$browser->public_id}")->assertOk();

        $this->flushHeaders();
        $this->actingAs($user)->withSession([EnforceMemberDevice::SESSION_KEY => $browser->public_id])
            ->get('/profile')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_staff_consoles_never_enroll_a_device(): void
    {
        $user = $this->member();
        $this->actingAs($user)->get('/control/login');

        $this->assertSame(0, MemberDevice::query()->where('user_id', $user->id)->count());
    }

    public function test_idle_window_comes_from_settings(): void
    {
        $user = $this->member();
        $this->actingAs($user)->get('/profile')->assertOk();
        MemberDevice::query()->where('user_id', $user->id)->update(['last_active_at' => Carbon::now()->subDays(10)]);

        AppSetting::set(MembershipSettings::storageKey('device_idle_days'), '7', MembershipSettings::GROUP);

        $phone = $this->signIn($user, 'install-a');
        $this->me($phone)->assertOk();
    }
}
