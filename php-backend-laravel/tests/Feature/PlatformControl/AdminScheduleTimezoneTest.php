<?php

declare(strict_types=1);

namespace Tests\Feature\PlatformControl;

use App\Filament\Resources\Ads\Pages\CreateAd;
use App\Models\Ad;
use App\Models\User;
use App\Support\BusinessClock;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admin-entered schedules are typed in India time and stored as true UTC, so the schedule
 * queries (which compare with UTC now()) switch at the moment the admin meant. Before, "6 PM"
 * was saved as 6 PM UTC and an ad went live at 11:30 PM IST.
 */
class AdminScheduleTimezoneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@h.test', 'password' => bcrypt('x'), 'role' => 'ADMIN', 'status' => 'active']);
        // Ads are Shield-gated per permission.
        foreach (['ViewAny:Ad', 'Create:Ad'] as $name) {
            \Spatie\Permission\Models\Permission::findOrCreate($name, 'web');
        }
        $admin->givePermissionTo(['ViewAny:Ad', 'Create:Ad']);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('control'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function atIst(string $local): void
    {
        Carbon::setTestNow(Carbon::parse($local, BusinessClock::zone())->utc());
    }

    public function test_an_ad_set_for_6pm_ist_starts_serving_at_6pm_ist(): void
    {
        $this->atIst('2026-10-20 12:00:00');

        Livewire::test(CreateAd::class)
            ->assertOk()
            ->fillForm([
                'title' => 'Diwali offer',
                'cta_text' => 'Book now',
                'placement' => array_key_first(Ad::PLACEMENTS),
                'is_active' => true,
                'sort_order' => 0,
                'starts_at' => '2026-10-20 18:00',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $ad = Ad::firstOrFail();
        // 6 PM IST is 12:30 UTC.
        $this->assertSame('2026-10-20 12:30:00', $ad->starts_at->utc()->format('Y-m-d H:i:s'));

        $this->atIst('2026-10-20 17:59:00');
        $this->assertFalse($ad->fresh()->isServing());
        $this->assertSame(0, Ad::serving()->count());

        $this->atIst('2026-10-20 18:00:00');
        $this->assertTrue($ad->fresh()->isServing());
        $this->assertSame(1, Ad::serving()->count());
    }

    public function test_the_ads_list_shows_the_time_that_was_typed(): void
    {
        $this->atIst('2026-10-20 12:00:00');
        Ad::create([
            'title' => 'Shown', 'cta_text' => 'Go', 'placement' => array_key_first(Ad::PLACEMENTS),
            'is_active' => true, 'sort_order' => 0,
            'starts_at' => Carbon::parse('2026-10-20 18:00', BusinessClock::zone())->utc(),
        ]);

        // The column is toggled off by default; show it, then read the rendered time.
        Livewire::test(\App\Filament\Resources\Ads\Pages\ListAds::class)
            ->assertOk()
            ->toggleAllTableColumns()
            ->assertSee('18:00')
            ->assertDontSee('12:30');
    }

    public function test_a_ticket_tier_sales_window_opens_at_the_ist_time(): void
    {
        $tier = new \App\Models\TicketType(['sales_start' => Carbon::parse('2026-10-25 10:00', BusinessClock::zone())->utc()]);

        $this->atIst('2026-10-25 09:59:00');
        $this->assertFalse($tier->isOnSale());

        $this->atIst('2026-10-25 10:00:00');
        $this->assertTrue($tier->isOnSale());
    }
}
