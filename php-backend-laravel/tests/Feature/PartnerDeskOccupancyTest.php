<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueCourt;
use App\Models\VenueSlot;
use App\Support\BusinessClock;
use App\Support\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Online, web and WhatsApp bookings store start/end times and no slot id — and at a
 * single-court venue, no court either. The desk grid, Home and the conflict check all
 * matched on those ids alone, so a paid 7 AM booking sat on the grid as "Open", Home
 * read "3 of 2 slots booked", Next up showed "—", and the desk could sell 7 AM twice.
 */
final class PartnerDeskOccupancyTest extends TestCase
{
    use RefreshDatabase;

    private User $partner;

    private Venue $venue;

    private VenueCourt $court;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->partner = User::factory()->create(['role' => 'partner', 'partner_type' => 'venue', 'status' => 'active']);
        $this->venue = Venue::create([
            'name' => 'Occupancy Turf', 'location' => 'Madhapur', 'price' => 500,
            'is_active' => true, 'is_bookable' => true, 'partner_id' => $this->partner->id,
        ]);
        $this->court = VenueCourt::create(['venue_id' => $this->venue->id, 'name' => 'VADI', 'price' => 500, 'is_active' => true]);

        $secret = config('app.jwt_secret') ?: env('JWT_SECRET', 'change_me');
        $this->token = JwtService::issueForUser($this->partner, $secret);
    }

    private function slot(string $time): VenueSlot
    {
        return VenueSlot::create(['venue_id' => $this->venue->id, 'time' => $time, 'price' => 500, 'capacity' => 1]);
    }

    /** An app checkout as prod stores it: times, no slot id, no court. */
    private function onlineBooking(string $date, string $start, string $end, string $status = 'CONFIRMED'): Booking
    {
        $player = User::factory()->create();

        return Booking::forceCreate([
            'booking_type' => 'venue', 'venue_id' => $this->venue->id, 'user_id' => $player->id,
            'channel' => 'online', 'status' => $status, 'payment_status' => 'paid',
            'slot_date' => $date, 'start_time' => $start, 'end_time' => $end,
            'quantity' => 1, 'total_amount' => 500, 'amount_paid' => 500,
            'reserved_until' => $status === 'PENDING' ? now()->addMinutes(10) : null,
        ]);
    }

    private function partnerGet(string $path)
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->token)->getJson('/api/partner'.$path);
    }

    private function walkIn(VenueSlot $slot, string $date, ?int $courtId)
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson("/api/partner/venues/{$this->venue->id}/bookings", array_filter([
                'slotId' => $slot->id, 'courtId' => $courtId, 'date' => $date,
                'guestName' => 'Desk', 'guestPhone' => '9000000001', 'paymentMethod' => 'cash',
            ], fn ($v) => $v !== null));
    }

    public function test_the_desk_cannot_sell_an_hour_the_app_already_sold(): void
    {
        $date = BusinessClock::todayDate()->addDay()->toDateString();
        $seven = $this->slot('7:00 AM');
        $this->onlineBooking($date, '07:00', '08:00');

        $this->walkIn($seven, $date, $this->court->id)->assertStatus(409);
        $this->assertSame(1, Booking::query()->where('venue_id', $this->venue->id)->count());
    }

    public function test_a_venue_without_courts_is_checked_by_hours_too(): void
    {
        $this->court->delete();
        $date = BusinessClock::todayDate()->addDay()->toDateString();
        $seven = $this->slot('7:00 AM');
        $this->onlineBooking($date, '07:00', '08:00');

        $this->walkIn($seven, $date, null)->assertStatus(409);
    }

    public function test_a_free_hour_next_to_it_still_sells(): void
    {
        $date = BusinessClock::todayDate()->addDay()->toDateString();
        $this->slot('7:00 AM');
        $eight = $this->slot('8:00 AM');
        $this->onlineBooking($date, '07:00', '08:00');

        $this->walkIn($eight, $date, $this->court->id)->assertCreated();
    }

    public function test_the_desk_grid_shows_an_app_booking_as_booked(): void
    {
        $date = BusinessClock::todayDate()->addDay()->toDateString();
        $six = $this->slot('6:00 AM');
        $seven = $this->slot('7:00 AM');
        $this->onlineBooking($date, '07:00', '08:00');

        $slots = collect($this->partnerGet("/venues/{$this->venue->id}/day?date={$date}")->assertOk()->json('slots'))->keyBy('slot_id');

        $this->assertTrue($slots[$seven->id]['courts'][0]['is_booked']);
        $this->assertSame(1, $slots[$seven->id]['booked']);
        $this->assertFalse($slots[$six->id]['courts'][0]['is_booked']);
    }

    public function test_home_counts_court_hours_not_rows_and_leaves_holds_out(): void
    {
        $today = BusinessClock::today();
        $this->slot('10:00 PM');
        $this->slot('11:00 PM');
        $this->onlineBooking($today, '22:00', '23:00');
        // A second paid row on the same hour, and one at an hour with no slot at all:
        // neither can push "booked" past what the day holds.
        $this->onlineBooking($today, '22:00', '23:00');
        $this->onlineBooking($today, '15:00', '16:00');
        // A player still on the payment screen: not a sale.
        $this->onlineBooking($today, '23:00', '23:59', 'PENDING');

        $data = $this->partnerGet('/today')->assertOk()->json('data');

        $this->assertSame(2, $data['capacity']['total']);
        $this->assertSame(1, $data['capacity']['booked']);
        $this->assertEquals(1500, $data['money']['expected']);
        $this->assertContains('10:00 PM', collect($data['next'])->pluck('time')->all());
        $this->assertNotContains('', collect($data['next'])->pluck('time')->all());
    }
}
