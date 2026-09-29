<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Clusters\GameHub\Pages\VenueBookings;
use App\Models\Booking;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueCourt;
use App\Models\VenueSlot;
use App\Support\BusinessClock;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The web Day bookings page — the partner app's day screen on the web. It reads the
 * same grid as the app (VenueDayGrid), seats walk-ins on a court and can take the
 * money there and then, and never lets one partner act on another's booking.
 */
final class DayBookingsPageTest extends TestCase
{
    use RefreshDatabase;

    private User $partner;

    private Venue $venue;

    private VenueCourt $court;

    private VenueSlot $slot;

    private string $tomorrow;

    protected function setUp(): void
    {
        parent::setUp();

        $this->partner = User::factory()->create(['role' => 'PARTNER', 'partner_type' => 'venue', 'status' => 'active']);
        $this->venue = Venue::create([
            'name' => 'Desk Turf', 'location' => 'Madhapur', 'price' => 500,
            'is_active' => true, 'is_bookable' => true, 'partner_id' => $this->partner->id,
        ]);
        $this->court = VenueCourt::create(['venue_id' => $this->venue->id, 'name' => 'Turf A', 'price' => 600, 'is_active' => true]);
        $this->slot = VenueSlot::create(['venue_id' => $this->venue->id, 'time' => '7:00 PM', 'price' => 600, 'capacity' => 1]);
        $this->tomorrow = BusinessClock::todayDate()->addDay()->toDateString();

        $this->actingAs($this->partner);
        Filament::setCurrentPanel(Filament::getPanel('partner'));
    }

    public function test_the_day_renders_open_courts_at_their_real_price(): void
    {
        Livewire::test(VenueBookings::class)
            ->call('selectDate', $this->tomorrow)
            ->assertOk()
            ->assertSee('Turf A')
            ->assertSee('7:00 PM')
            ->assertSee('₹600');
    }

    public function test_a_walk_in_paid_in_cash_lands_on_the_court_and_in_the_ledger(): void
    {
        $page = Livewire::test(VenueBookings::class)
            ->call('selectDate', $this->tomorrow)
            ->call('openSeat', $this->slot->id, $this->court->id)
            ->set('guestName', 'Arjun')
            ->set('guestPhone', '98765 43210')
            ->set('payNow', 'cash')
            ->call('seat')
            ->assertHasNoErrors();

        $booking = Booking::where('guest_name', 'Arjun')->firstOrFail();
        $this->assertSame($this->court->id, (int) $booking->venue_court_id);
        $this->assertSame('9876543210', $booking->guest_phone);
        $this->assertEqualsWithDelta((float) $booking->total_amount, (float) $booking->refresh()->amount_paid, 0.01);

        $stats = $page->instance()->stats();
        $this->assertSame(1, $stats['booked']);
        $this->assertSame(0.0, (float) $stats['due']);
    }

    public function test_collect_later_shows_as_owed_until_collected(): void
    {
        $page = Livewire::test(VenueBookings::class)
            ->call('selectDate', $this->tomorrow)
            ->call('openSeat', $this->slot->id, $this->court->id)
            ->set('guestName', 'Meera')
            ->set('payNow', 'later')
            ->call('seat');

        $booking = Booking::where('guest_name', 'Meera')->firstOrFail();
        $this->assertGreaterThan(0, $page->instance()->stats()['due']);

        $page->call('collect', $booking->id, 'upi');

        $this->assertEqualsWithDelta((float) $booking->total_amount, (float) $booking->refresh()->amount_paid, 0.01);
    }

    public function test_another_partners_booking_cannot_be_touched(): void
    {
        $other = User::factory()->create(['role' => 'PARTNER', 'partner_type' => 'venue', 'status' => 'active']);
        $theirVenue = Venue::create([
            'name' => 'Their Turf', 'location' => 'Kondapur', 'price' => 500,
            'is_active' => true, 'is_bookable' => true, 'partner_id' => $other->id,
        ]);
        $theirs = Booking::forceCreate([
            'booking_type' => 'venue', 'venue_id' => $theirVenue->id, 'user_id' => $other->id,
            'status' => 'CONFIRMED', 'slot_date' => $this->tomorrow, 'start_time' => '19:00', 'end_time' => '20:00',
            'quantity' => 1, 'total_amount' => 900, 'amount_paid' => 0, 'payment_status' => 'unpaid',
        ]);

        Livewire::test(VenueBookings::class)
            ->call('checkIn', $theirs->id)
            ->call('collect', $theirs->id, 'cash')
            ->call('cancelBooking', $theirs->id);

        $theirs->refresh();
        $this->assertNull($theirs->checked_in_at);
        $this->assertSame(0.0, (float) $theirs->amount_paid);
        $this->assertSame('CONFIRMED', $theirs->status);
    }
}
