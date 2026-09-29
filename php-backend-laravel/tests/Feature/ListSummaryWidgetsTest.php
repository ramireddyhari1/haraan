<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Bookings\Widgets\BookingsExecutiveHeroWidget;
use App\Filament\Resources\LiveMatches\Widgets\MatchesExecutiveHeroWidget;
use App\Filament\Resources\Shifts\Widgets\ShiftSessionsExecutiveHeroWidget;
use App\Filament\Resources\VenueBlocks\Widgets\VenueBlocksExecutiveHeroWidget;
use App\Filament\Resources\VenueBookings\Widgets\VenueBookingsExecutiveHeroWidget;
use App\Filament\Resources\Venues\Widgets\VenuesExecutiveHeroWidget;
use App\Filament\Resources\Waitlist\Widgets\WaitlistExecutiveHeroWidget;
use App\Models\Booking;
use App\Models\LiveMatch;
use App\Models\ShiftSession;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueBlock;
use App\Models\VenueCourt;
use App\Models\WaitlistEntry;
use App\Services\BookingLedger;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The summary strips above the list pages report only what's in the database.
 * They used to fill empty tables with invented figures (a ₹32,60,400 GMV,
 * cashiers called Rajesh M., three sample live matches); these tests pin both
 * halves: real numbers come through, and an empty database shows nothing made up.
 */
class ListSummaryWidgetsTest extends TestCase
{
    use RefreshDatabase;

    private const WIDGETS = [
        BookingsExecutiveHeroWidget::class,
        VenueBookingsExecutiveHeroWidget::class,
        VenuesExecutiveHeroWidget::class,
        MatchesExecutiveHeroWidget::class,
        ShiftSessionsExecutiveHeroWidget::class,
        VenueBlocksExecutiveHeroWidget::class,
        WaitlistExecutiveHeroWidget::class,
    ];

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@haraan.test',
            'password' => bcrypt('secret123'),
            'role' => 'ADMIN',
            'status' => 'active',
        ]);
        $this->actingAs($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('control'));
    }

    private function venue(string $name = 'Sportz Arena', string $city = 'Hyderabad'): Venue
    {
        $venue = Venue::create([
            'name' => $name, 'location' => 'Gachibowli', 'city' => $city, 'price' => 1400,
            'status' => 'published', 'is_active' => true, 'is_bookable' => true,
        ]);
        VenueCourt::create(['venue_id' => $venue->id, 'name' => 'Turf A', 'price' => 1400, 'is_active' => true]);

        return $venue;
    }

    private function slot(Venue $venue, string $status = 'CONFIRMED', int $amount = 1400, ?string $date = null): Booking
    {
        return Booking::create([
            'quantity' => 1, 'total_amount' => $amount, 'status' => $status,
            'booking_type' => 'venue', 'user_id' => $this->admin->id, 'venue_id' => $venue->id,
            'slot_date' => $date ?? now()->toDateString(), 'start_time' => '19:00', 'end_time' => '20:00',
        ]);
    }

    public function test_an_empty_database_shows_no_invented_figures(): void
    {
        $invented = ['3,260,400', '32,60,400', 'Rajesh M.', 'Strikers FC', '₹48,000', 'TURFPRO20',
            '96% AI', '14,280', '₹36,400', '94.2%', 'Court 4', 'Vikram S.', '₹28,450', 'Surge'];

        foreach (self::WIDGETS as $widget) {
            $page = Livewire::test($widget)->assertOk();
            foreach ($invented as $made_up) {
                $page->assertDontSee($made_up, escape: false);
            }
        }

        Livewire::test(MatchesExecutiveHeroWidget::class)->assertSee('Nothing is live right now.');
        Livewire::test(ShiftSessionsExecutiveHeroWidget::class)->assertSee('No drawer is open right now.');
        Livewire::test(WaitlistExecutiveHeroWidget::class)->assertSee('Nobody is waiting for a slot.');
    }

    public function test_venue_bookings_report_ledger_money_and_the_week_ahead(): void
    {
        $venue = $this->venue();
        $advance = $this->slot($venue, amount: 4400);
        app(BookingLedger::class)->collect($advance, 500, 'upi');
        $this->slot($venue, 'CANCELLED');
        $this->slot($venue, date: now()->addDay()->toDateString());

        $s = (new VenueBookingsExecutiveHeroWidget)->getSummary();

        $this->assertSame('3', $s['stats'][0]['value']);
        $this->assertSame('₹500', $s['stats'][1]['value'], 'Collected is the advance, not the invoice.');
        $this->assertSame('33.3%', $s['stats'][2]['value']);
        $this->assertSame('UPI', $s['split']['parts'][0]['name']);
        $this->assertSame('1 slot', $s['list']['rows'][1]['trailing'], 'Tomorrow has one live booking.');
    }

    public function test_venues_count_live_venues_and_the_busiest_week(): void
    {
        $busy = $this->venue('Sportz Arena');
        $this->venue('Quiet Courts', 'Chennai');
        $this->slot($busy);
        $this->slot($busy, date: now()->addDays(2)->toDateString());

        $s = (new VenuesExecutiveHeroWidget)->getSummary();

        $this->assertSame('2', $s['stats'][0]['value']);
        $this->assertSame('2', $s['stats'][2]['value']);
        $this->assertSame('Sportz Arena', $s['list']['rows'][0]['primary']);
        $this->assertSame('2 slots booked', $s['list']['rows'][0]['trailing']);
    }

    public function test_matches_list_the_ones_actually_live(): void
    {
        LiveMatch::create(['home' => 'RCB', 'away' => 'CSK', 'status' => 'Live', 'sport' => 'cricket', 'score_text' => '74/3']);
        LiveMatch::create(['home' => 'A', 'away' => 'B', 'status' => 'Scheduled', 'sport' => 'football']);

        $s = (new MatchesExecutiveHeroWidget)->getSummary();

        $this->assertSame('1', $s['stats'][0]['value']);
        $this->assertSame('1', $s['stats'][1]['value']);
        $this->assertCount(1, $s['list']['rows']);
        $this->assertSame('74/3', $s['list']['rows'][0]['trailing']);
    }

    public function test_shifts_show_the_drawer_and_who_is_on_duty(): void
    {
        $venue = $this->venue();
        $desk = User::create(['name' => 'Priya Desk', 'email' => 'desk@haraan.test', 'password' => bcrypt('x'), 'role' => 'USER']);
        $shift = ShiftSession::create(['venue_id' => $venue->id, 'user_id' => $desk->id, 'opened_by' => $desk->id,
            'opened_at' => now()->subHour(), 'opening_float' => 1000]);

        $payment = app(BookingLedger::class)->collect($this->slot($venue), 800, 'cash');
        $payment->forceFill(['shift_session_id' => $shift->id])->save();

        $s = (new ShiftSessionsExecutiveHeroWidget)->getSummary();

        $this->assertSame('1', $s['stats'][0]['value']);
        $this->assertSame('₹1,800', $s['stats'][1]['value'], 'Float plus cash taken.');
        $this->assertSame('Priya Desk', $s['list']['rows'][0]['primary']);
    }

    public function test_blocks_count_what_is_in_force_today(): void
    {
        $venue = $this->venue();
        VenueBlock::create(['venue_id' => $venue->id, 'kind' => 'maintenance', 'title' => 'Re-turfing',
            'starts_on' => now()->toDateString(), 'ends_on' => now()->addDay()->toDateString()]);
        VenueBlock::create(['venue_id' => $venue->id, 'kind' => 'tournament', 'title' => 'Cup final',
            'starts_on' => now()->addDays(3)->toDateString(), 'ends_on' => now()->addDays(3)->toDateString()]);

        $s = (new VenueBlocksExecutiveHeroWidget)->getSummary();

        $this->assertSame('1', $s['stats'][0]['value']);
        $this->assertSame('1', $s['stats'][1]['value']);
        $this->assertSame('Re-turfing', $s['list']['rows'][0]['primary']);
    }

    public function test_waitlist_shows_the_real_queue(): void
    {
        $venue = $this->venue();
        WaitlistEntry::create(['venue_id' => $venue->id, 'wanted_on' => now()->addDay()->toDateString(),
            'start_time' => '19:00', 'end_time' => '20:00', 'guest_name' => 'Arjun']);

        $s = (new WaitlistExecutiveHeroWidget)->getSummary();

        $this->assertSame('1', $s['stats'][0]['value']);
        $this->assertSame('₹0', $s['stats'][3]['value']);
        $this->assertSame('Arjun', $s['list']['rows'][0]['primary']);
    }

    public function test_the_strips_stay_out_of_the_partner_console(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('partner'));

        foreach (self::WIDGETS as $widget) {
            $this->assertFalse($widget::canView(), class_basename($widget) . ' must not show to partners.');
        }
    }
}
