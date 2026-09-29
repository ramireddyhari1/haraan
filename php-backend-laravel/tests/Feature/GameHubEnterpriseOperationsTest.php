<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Clusters\GameHub\Pages\GameHubIntegrations;
use App\Filament\Clusters\GameHub\Pages\GameHubMembers;
use App\Filament\Clusters\GameHub\Pages\GameHubNotifications;
use App\Filament\Clusters\GameHub\Pages\GameHubOverview;
use App\Filament\Clusters\GameHub\Pages\GameHubPricingRules;
use App\Filament\Clusters\GameHub\Pages\GameHubStaff;
use App\Filament\Clusters\GameHub\Pages\GameHubSupport;
use App\Filament\Clusters\GameHub\Pages\GameHubTournaments;
use App\Filament\Clusters\GameHub\Pages\Reports;
use App\Models\Booking;
use App\Models\PricingRule;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueCourt;
use App\Services\BookingLedger;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The GameHub pages report only what's in the database, and in the partner
 * console only the partner's own venues. They used to be filled with invented
 * figures (a "98 health score", "36 IoT controllers online", cashiers and
 * referees with made-up names and phone numbers, fake "credit points" and
 * "sync" buttons that did nothing but show a success toast).
 */
class GameHubEnterpriseOperationsTest extends TestCase
{
    use RefreshDatabase;

    private const PAGES = [
        GameHubOverview::class, GameHubMembers::class, GameHubStaff::class, GameHubPricingRules::class,
        GameHubTournaments::class, GameHubNotifications::class, GameHubSupport::class,
        GameHubIntegrations::class, Reports::class,
    ];

    private function admin(): User
    {
        return User::create([
            'name' => 'Super Admin', 'email' => 'admin@haraan.test', 'password' => bcrypt('secret123'),
            'role' => 'ADMIN', 'status' => 'active',
        ]);
    }

    private function partner(string $email): User
    {
        return User::create([
            'name' => 'Venue Owner', 'email' => $email, 'password' => bcrypt('secret123'),
            'role' => 'PARTNER', 'partner_type' => 'venue', 'status' => 'active',
        ]);
    }

    private function venue(?User $owner, string $name): Venue
    {
        $venue = Venue::create([
            'name' => $name, 'location' => 'Gachibowli', 'city' => 'Hyderabad', 'price' => 1400,
            'status' => 'published', 'is_active' => true, 'is_bookable' => true, 'partner_id' => $owner?->id,
        ]);
        VenueCourt::create(['venue_id' => $venue->id, 'name' => 'Turf A', 'price' => 1400, 'is_active' => true]);

        return $venue;
    }

    private function booking(Venue $venue, User $by, int $amount, ?string $phone = null): Booking
    {
        return Booking::create([
            'quantity' => 1, 'total_amount' => $amount, 'status' => 'CONFIRMED', 'booking_type' => 'venue',
            'user_id' => $by->id, 'venue_id' => $venue->id, 'guest_phone' => $phone,
            'slot_date' => now()->toDateString(), 'start_time' => '23:00', 'end_time' => '23:59',
        ]);
    }

    public function test_every_page_renders_without_invented_figures(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('control'));

        $invented = ['Rohan Varma', '+91 98840', 'Vikram Singh', 'Rajesh Sharma', 'TIC-8821', 'Hyderabad Premier Turf League',
            '36 / 36', '142,500', '99.98%', '99.8%', 'AI Yield', '1.25x', '₹48,750', 'OpenCV', 'Optimal Operations',
            '1,845,200', '18,45,200', '82.4%', '74.2%', 'creditPoints', 'syncAll', 'scheduleReport'];

        foreach (self::PAGES as $page) {
            $test = Livewire::test($page)->assertOk();
            foreach ($invented as $made_up) {
                $test->assertDontSee($made_up, escape: false);
            }
        }
    }

    public function test_overview_counts_real_money_and_the_sheet(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('control'));

        $venue = $this->venue(null, 'Sportz Arena');
        app(BookingLedger::class)->collect($this->booking($venue, $admin, 4400), 500, 'upi');

        $today = (new GameHubOverview)->getPanels()[0];

        $this->assertSame('₹500', $today['stats'][0]['value'], 'Collected is the advance, not the invoice.');
        $this->assertSame('1', $today['stats'][1]['value']);
    }

    public function test_partner_console_sees_only_its_own_venues(): void
    {
        $mine = $this->partner('mine@haraan.test');
        $theirs = $this->partner('theirs@haraan.test');
        $myVenue = $this->venue($mine, 'My Turf');
        $theirVenue = $this->venue($theirs, 'Their Turf');

        $this->booking($myVenue, $mine, 1000, '9000000001');
        $this->booking($theirVenue, $theirs, 9000, '9000000002');
        $this->booking($theirVenue, $theirs, 9000, '9000000003');
        PricingRule::create(['venue_id' => $theirVenue->id, 'name' => 'Their surge', 'rule_type' => 'time_of_day',
            'start_time' => '18:00', 'end_time' => '23:00', 'pricing_mode' => 'percentage', 'amount' => 20, 'priority' => 1, 'is_active' => true]);

        $this->actingAs($mine);
        Filament::setCurrentPanel(Filament::getPanel('partner'));

        $players = (new GameHubMembers)->getPanels()[0];
        $this->assertSame('1', $players['stats'][0]['value']);
        $this->assertSame('₹1,000', $players['list']['rows'][0]['trailing']);

        $pricing = (new GameHubPricingRules)->getPanels()[0];
        $this->assertSame('0', $pricing['stats'][0]['value'], 'Another partner\'s rule must not show.');

        $month = (new GameHubOverview)->getPanels()[1];
        $this->assertSame('₹1,000', $month['stats'][0]['value']);

        $this->assertFalse(GameHubTournaments::canAccess(), 'Player tournaments are not venue data.');
    }

    public function test_reports_export_is_not_empty_for_admins(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('control'));

        $this->booking($this->venue(null, 'Sportz Arena'), $admin, 1400);

        $this->assertSame(1, Livewire::test(Reports::class)->instance()->rowCount());
    }
}
