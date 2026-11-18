<?php

declare(strict_types=1);

namespace Tests\Feature\Membership;

use App\Filament\Clusters\Finance\Pages\AppIconsPage;
use App\Models\AdminAction;
use App\Models\AppIconPlanRule;
use App\Models\AppIconSlot;
use App\Models\MemberPlanEntitlement;
use App\Models\User;
use App\Services\Membership\AppIconCatalogue;
use App\Support\Membership\MemberFeature;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The app-icon library: what each plan may pick and gets automatically, driven entirely
 * from /control → Finance → App icons.
 */
class AppIconsTest extends TestCase
{
    use MembershipFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureRazorpay();
        FakeRazorpaySubscriptions::install();
    }

    private function rule(string $slot, string $plan, bool $pick, bool $auto = false): void
    {
        AppIconPlanRule::query()->updateOrCreate(
            ['slot_key' => $slot, 'plan_id' => $this->plan($plan)->id],
            ['can_pick' => $pick || $auto, 'is_auto' => $auto],
        );
    }

    private function keys(array $payload): array
    {
        return array_column($payload['icons'], 'key');
    }

    public function test_seeded_library_matches_the_old_behaviour(): void
    {
        $this->assertSame(12, AppIconSlot::query()->count());

        $guest = $this->getJson('/api/membership/app-icons')->assertOk()->json('data');
        // Only icons some plan can pick are offered; the nine new ones belong to nobody yet.
        $this->assertSame(['default', 'pro', 'hero'], $this->keys($guest));
        $this->assertNull($guest['auto']);
        $this->assertSame(AppIconCatalogue::MIN_HOURS_DEFAULT, $guest['min_hours_between_auto']);

        $pro = collect($guest['icons'])->firstWhere('key', 'pro');
        $this->assertFalse($pro['unlocked']);
        $this->assertSame('pro', $pro['unlocked_by']['code'], 'the cheapest plan that unlocks it');
        $this->assertTrue(collect($guest['icons'])->firstWhere('key', 'default')['unlocked']);

        $hero = $this->member();
        $this->paidSubscription($hero, 'hero');
        $mine = $this->asMember($hero)->getJson('/api/membership/app-icons')->assertOk()->json('data');
        $this->assertSame([true, true, true], array_column($mine['icons'], 'unlocked'));
    }

    public function test_admin_can_give_any_icon_to_any_plan_and_auto_apply_it(): void
    {
        $this->rule('onyx', 'pro', pick: true, auto: true);

        $pro = $this->member();
        $this->paidSubscription($pro, 'pro');
        $data = $this->asMember($pro)->getJson('/api/membership/app-icons')->json('data');

        $this->assertContains('onyx', $this->keys($data));
        $this->assertSame('onyx', $data['auto']);

        $free = $this->member(['email' => 'free@haraan.test', 'phone' => '+919000000999']);
        $theirs = $this->asMember($free)->getJson('/api/membership/app-icons')->json('data');
        $this->assertNull($theirs['auto']);
        $this->assertFalse(collect($theirs['icons'])->firstWhere('key', 'onyx')['unlocked']);
    }

    public function test_a_live_seasonal_icon_beats_the_usual_auto_icon_then_ends(): void
    {
        $this->rule('hero', 'hero', pick: true, auto: true);
        $this->rule('festive', 'hero', pick: true, auto: true);
        AppIconSlot::query()->where('key', 'festive')->update([
            'starts_at' => Carbon::parse('2026-11-01 00:00'), 'ends_at' => Carbon::parse('2026-11-06 00:00'),
        ]);
        $hero = $this->member();
        $this->paidSubscription($hero, 'hero');
        $catalogue = app(AppIconCatalogue::class);

        $this->assertSame('festive', $catalogue->for($hero, Carbon::parse('2026-11-03 12:00'))['auto']);

        $after = $catalogue->for($hero, Carbon::parse('2026-11-07 12:00'));
        $this->assertSame('hero', $after['auto']);
        $this->assertNotContains('festive', $this->keys($after), 'out of its window it isn’t offered');
    }

    public function test_switched_off_icon_is_not_offered_but_default_always_is(): void
    {
        AppIconSlot::query()->whereIn('key', ['pro', 'default'])->update(['is_active' => false]);

        $data = app(AppIconCatalogue::class)->for(null);
        $this->assertNotContains('pro', $this->keys($data));
        $this->assertContains('default', $this->keys($data));
    }

    public function test_control_page_saves_audits_and_keeps_old_apps_in_step(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('control'));
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin@haraan.test', 'password' => bcrypt('secret123'),
            'role' => 'ADMIN', 'status' => 'active',
        ]);
        $this->actingAs($admin);
        $pro = (string) $this->plan('pro')->id;
        $hero = (string) $this->plan('hero')->id;

        Livewire::test(AppIconsPage::class)
            ->set('data.min_hours', 24)
            ->set('data.slots.pro.pick', [$hero])          // Pro icon now Hero-only
            ->set('data.slots.turf.pick', [$pro])
            ->set('data.slots.turf.auto', [$pro])
            ->set('data.slots.turf.name', 'Turf green')
            ->callAction('save')
            ->assertHasNoFormErrors();

        $this->assertSame(24, AppIconCatalogue::minHoursBetweenAuto());
        $this->assertTrue((bool) AppIconPlanRule::query()->where(['slot_key' => 'turf', 'plan_id' => $pro])->value('is_auto'));
        $this->assertSame('Turf green', AppIconSlot::query()->where('key', 'turf')->value('name'));
        $this->assertFalse((bool) MemberPlanEntitlement::query()
            ->where(['plan_id' => $pro, 'feature_key' => MemberFeature::APP_ICON_PRO])->value('enabled'),
            'older apps read app.icon_pro, which follows the library');
        $this->assertTrue(AdminAction::query()->where('action', 'app_icons.updated')->exists());

        $member = $this->member();
        $this->paidSubscription($member, 'pro');
        $data = $this->asMember($member)->getJson('/api/membership/app-icons')->json('data');
        $this->assertSame('turf', $data['auto']);
        $this->assertFalse(collect($data['icons'])->firstWhere('key', 'pro')['unlocked']);
    }

    public function test_changing_the_free_plans_auto_icon_asks_first(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('control'));
        $this->actingAs(User::create([
            'name' => 'Admin', 'email' => 'admin@haraan.test', 'password' => bcrypt('secret123'),
            'role' => 'ADMIN', 'status' => 'active',
        ]));
        $free = (string) $this->plan('free')->id;

        Livewire::test(AppIconsPage::class)
            ->set('data.slots.sunrise.auto', [$free])
            ->mountAction('save')
            ->assertActionMounted('save');   // the confirmation modal is open, nothing saved yet

        $this->assertFalse((bool) AppIconPlanRule::query()->where(['slot_key' => 'sunrise', 'plan_id' => $free])->value('is_auto'));
    }

    public function test_only_admins_reach_the_page(): void
    {
        $this->actingAs(User::create([
            'name' => 'Fin', 'email' => 'fin@haraan.test', 'password' => bcrypt('secret123'),
            'role' => 'FINANCE', 'status' => 'active',
        ]));
        $this->assertFalse(AppIconsPage::canAccess());
    }
}
