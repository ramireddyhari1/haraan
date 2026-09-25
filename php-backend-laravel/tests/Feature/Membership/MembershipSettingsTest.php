<?php

declare(strict_types=1);

namespace Tests\Feature\Membership;

use App\Filament\Clusters\Finance\Pages\MembershipSettingsPage;
use App\Models\AdminAction;
use App\Models\AppSetting;
use App\Models\FeatureFlag;
use App\Models\User;
use App\Models\Venue;
use App\Services\Membership\MemberBookingPerks;
use App\Services\Membership\MemberEntitlements;
use App\Services\Membership\MemberSubscriptions;
use App\Support\Membership\MembershipSettings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Finance → Membership settings: every tunable number and line of copy is an admin's to
 * change, and a change takes effect on the next request — no deploy, no env edit.
 */
class MembershipSettingsTest extends TestCase
{
    use MembershipFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('control'));
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin', 'email' => 'admin@haraan.test', 'password' => bcrypt('secret123'),
            'role' => 'ADMIN', 'status' => 'active',
        ]);
    }

    private function venue(): Venue
    {
        $venue = Venue::create([
            'name' => 'Sportz Arena', 'location' => 'Gachibowli', 'price' => 1400,
            'is_active' => true, 'is_bookable' => true, 'partner_id' => $this->member(['role' => 'PARTNER'])->id,
            'city' => 'Hyderabad', 'images' => ['venues/test.jpg'], 'status' => 'published',
        ]);

        // Customer endpoints only serve a live venue (Venue::scopePublished): a court and a slot.
        $venue->courts()->create(['name' => 'Turf A', 'price' => 1400, 'is_active' => true]);
        $venue->slots()->create(['day' => 'Every day', 'time' => '7:00 PM', 'is_available' => true]);

        return $venue;
    }

    public function test_unset_settings_fall_back_to_config_and_are_clamped(): void
    {
        $this->assertSame(60, MembershipSettings::int('venue_booking_window_days'));
        $this->assertSame(72, MembershipSettings::int('early_access_max_hours'));
        $this->assertSame('Plans can’t be bought in the app.', MembershipSettings::text('app_store_note'));

        AppSetting::set(MembershipSettings::storageKey('venue_booking_window_days'), '9999', MembershipSettings::GROUP);
        $this->assertSame(365, MembershipSettings::int('venue_booking_window_days'));
    }

    public function test_only_admins_reach_the_page(): void
    {
        $this->actingAs($this->admin());
        $this->assertTrue(MembershipSettingsPage::canAccess());

        $this->actingAs($this->member());
        $this->assertFalse(MembershipSettingsPage::canAccess());
    }

    public function test_saving_the_page_changes_behaviour_immediately_and_is_audited(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(MembershipSettingsPage::class)
            ->set('data.venue_booking_window_days', 14)
            ->set('data.priority_booking_max_days', 2)
            ->set('data.web_headline', 'Go Pro')
            ->set('data.app_store_note', 'Join on haraan.app')
            ->set('data.in_app_checkout', true)
            ->call('save')
            ->assertHasNoErrors();

        // Default booking window: a venue without its own window now opens 14 days ahead.
        $venue = $this->venue();
        $this->assertSame(14, $venue->bookingWindowDays());
        $this->getJson("/api/venues/{$venue->id}/availability?date=".now()->addDays(15)->toDateString())
            ->assertStatus(422);

        // Perk ceiling: Hero's 3 priority days are capped at the admin's 2.
        $hero = $this->member();
        app(MemberSubscriptions::class)->grant($hero, $this->plan('hero'), null, $this->member(['role' => 'ADMIN']), 'test');
        $this->assertSame(2, app(MemberBookingPerks::class)->priorityBookingDays($hero));

        // Copy and the in-app checkout switch reach the website and the app.
        $this->get('/membership')->assertOk()->assertSee('Go Pro');
        $this->getJson('/api/membership/plans')
            ->assertOk()
            ->assertJsonPath('data.checkout.in_app', true)
            ->assertJsonPath('data.checkout.note', 'Join on haraan.app');

        $this->assertTrue(FeatureFlag::query()->where('key', MembershipSettings::IN_APP_CHECKOUT_FLAG)->value('enabled'));
        $this->assertTrue(AdminAction::query()->where('action', 'membership_settings.updated')->exists());
    }

    public function test_app_does_not_sell_plans_until_an_admin_allows_it(): void
    {
        $this->getJson('/api/membership/plans')
            ->assertOk()
            ->assertJsonPath('data.checkout.in_app', false)
            ->assertJsonPath('data.checkout.web_url', route('site.membership'));
    }

    public function test_perk_values_per_plan_are_edited_like_any_entitlement(): void
    {
        // The per-plan numbers live on member_plan_entitlements, which Member plans edits.
        $pro = $this->member();
        app(MemberSubscriptions::class)->grant($pro, $this->plan('pro'), null, $this->member(['role' => 'ADMIN']), 'test');
        $this->assertSame(12, app(MemberBookingPerks::class)->earlyAccessHours($pro));

        $this->plan('pro')->entitlements()->where('feature_key', 'events.early_access')->update(['limit_value' => 36]);
        MemberEntitlements::flush($pro->id);

        $this->assertSame(36, app(MemberBookingPerks::class)->earlyAccessHours($pro));
    }
}
