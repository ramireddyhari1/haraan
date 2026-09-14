<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\VenueAvailabilityUpdated;
use App\Models\Booking;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueBlock;
use App\Models\VenueBlockedDate;
use App\Models\VenueCourt;
use App\Models\VenueSlot;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

/**
 * The venue page's "Playing today" chips read /api/venues/{id}/availability. The contract
 * that matters: the endpoint must say "booked" exactly when checkout would refuse the hour,
 * and "open" exactly when it would take it — anything else is the chip lying to a player.
 */
class VenueSlotAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Venue $venue;
    private VenueCourt $courtA;
    private VenueSlot $slot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::create([
            'name' => 'Rahul Krishnan',
            'email' => 'owner@haraan.test',
            'password' => Hash::make('secret123'),
            'role' => 'PARTNER',
            'partner_type' => 'venue',
            'status' => 'active',
        ]);

        $this->venue = Venue::create([
            'name' => 'Sportz Arena', 'location' => 'Gachibowli', 'price' => 1400,
            'is_active' => true, 'is_bookable' => true, 'partner_id' => $this->owner->id,
        ]);

        $this->courtA = VenueCourt::create([
            'venue_id' => $this->venue->id, 'name' => 'Turf A', 'price' => 1400, 'is_active' => true,
        ]);

        $this->slot = VenueSlot::create([
            'venue_id' => $this->venue->id, 'day' => 'Every day', 'time' => '7:00 PM',
            'is_available' => true, 'capacity' => 1,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function book(array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'quantity' => 1,
            'total_amount' => 1400,
            'status' => 'CONFIRMED',
            'booking_type' => 'venue',
            'user_id' => $this->owner->id,
            'venue_id' => $this->venue->id,
            'venue_court_id' => $this->courtA->id,
            'venue_slot_id' => $this->slot->id,
            'slot_date' => today()->toDateString(),
            'start_time' => '19:00',
            'end_time' => '20:00',
            'channel' => 'offline',
            'guest_name' => 'Kiran Varma',
            'guest_phone' => '+91 90000 55412',
        ], $overrides));
    }

    /** @return array{id:int, state:string, courts_free:int, courts_total:int} */
    private function slotState(?string $date = null): array
    {
        $response = $this->getJson('/api/venues/' . $this->venue->id . '/availability'
            . ($date ? '?date=' . $date : ''));
        $response->assertOk();

        return collect($response->json('data.slots'))->firstWhere('id', $this->slot->id);
    }

    public function test_an_untouched_slot_is_open_with_every_court_free(): void
    {
        VenueCourt::create(['venue_id' => $this->venue->id, 'name' => 'Turf B', 'price' => 1400, 'is_active' => true]);

        $this->assertSame(
            ['id' => $this->slot->id, 'state' => 'open', 'courts_free' => 2, 'courts_total' => 2],
            $this->slotState(),
        );
    }

    public function test_the_only_court_booked_makes_the_slot_booked(): void
    {
        $this->book();

        $state = $this->slotState();
        $this->assertSame('booked', $state['state']);
        $this->assertSame(0, $state['courts_free']);
    }

    public function test_one_of_two_courts_booked_leaves_the_slot_open_with_one_left(): void
    {
        VenueCourt::create(['venue_id' => $this->venue->id, 'name' => 'Turf B', 'price' => 1400, 'is_active' => true]);
        $this->book();

        $state = $this->slotState();
        $this->assertSame('open', $state['state']);
        $this->assertSame(1, $state['courts_free']);
        $this->assertSame(2, $state['courts_total']);
    }

    public function test_an_overlapping_longer_booking_takes_the_hour(): void
    {
        // Booked 6–8 PM: the 7 PM start overlaps it even though the booking began earlier.
        $this->book(['start_time' => '18:00', 'end_time' => '20:00', 'venue_slot_id' => null]);

        $this->assertSame('booked', $this->slotState()['state']);
    }

    public function test_cancelled_bookings_and_expired_holds_release_the_hour(): void
    {
        $this->book(['status' => 'CANCELLED']);
        $this->book(['status' => 'PENDING', 'reserved_until' => now()->subMinute()]);

        $this->assertSame('open', $this->slotState()['state']);
    }

    public function test_a_live_checkout_hold_shows_as_booked(): void
    {
        $this->book(['status' => 'PENDING', 'reserved_until' => now()->addMinutes(10)]);

        $this->assertSame('booked', $this->slotState()['state']);
    }

    public function test_a_booking_on_another_day_does_not_count(): void
    {
        $this->book(['slot_date' => today()->addDay()->toDateString()]);

        $this->assertSame('open', $this->slotState()['state']);
        $this->assertSame('booked', $this->slotState(today()->addDay()->toDateString())['state']);
    }

    public function test_booking_the_parent_court_takes_its_sub_court(): void
    {
        // Turf A is the full pitch; Half 1 is part of it. With A booked, Half 1 can't be sold.
        $this->courtA->update(['is_active' => false]);
        $full = VenueCourt::create(['venue_id' => $this->venue->id, 'name' => 'Full pitch', 'price' => 2400, 'is_active' => true, 'is_composite' => true]);
        VenueCourt::create(['venue_id' => $this->venue->id, 'name' => 'Half 1', 'price' => 1400, 'is_active' => true, 'parent_court_id' => $full->id]);

        $this->book(['venue_court_id' => $full->id]);

        $this->assertSame('booked', $this->slotState()['state']);
    }

    public function test_court_blocks_template_switch_and_closed_dates_read_as_closed(): void
    {
        VenueBlock::create([
            'venue_id' => $this->venue->id, 'venue_court_id' => $this->courtA->id, 'kind' => 'maintenance',
            'starts_on' => today()->toDateString(), 'ends_on' => today()->toDateString(),
            'start_time' => '18:00', 'end_time' => '21:00', 'created_by' => $this->owner->id,
        ]);
        $this->assertSame('closed', $this->slotState()['state']);

        $tomorrow = today()->addDay()->toDateString();
        VenueBlockedDate::create(['venue_id' => $this->venue->id, 'date' => $tomorrow, 'reason' => 'Holiday']);
        $this->assertSame('closed', $this->slotState($tomorrow)['state']);

        $this->slot->update(['is_available' => false]);
        $this->assertSame('closed', $this->slotState(today()->addDays(2)->toDateString())['state']);
    }

    public function test_a_venue_without_courts_books_by_slot(): void
    {
        $this->courtA->delete();
        $this->book(['venue_court_id' => null]);

        $state = $this->slotState();
        $this->assertSame('booked', $state['state']);
        $this->assertSame(1, $state['courts_total']);
    }

    public function test_open_means_checkout_accepts_and_booked_means_it_refuses(): void
    {
        $date = today()->addDay()->toDateString();
        $service = app(BookingService::class);

        $this->assertSame('open', $this->slotState($date)['state']);
        $service->createVenueBooking($this->owner, $this->venue->id, $this->slot->id, $date, $this->courtA->id, 1);

        $this->assertSame('booked', $this->slotState($date)['state']);
        $this->expectException(ConflictHttpException::class);
        $service->createVenueBooking($this->owner, $this->venue->id, $this->slot->id, $date, $this->courtA->id, 1);
    }

    public function test_bookings_and_blocks_nudge_the_venue_channel_but_event_tickets_do_not(): void
    {
        Event::fake([VenueAvailabilityUpdated::class]);

        $this->book();
        Event::assertDispatched(VenueAvailabilityUpdated::class, fn ($e) => $e->venueId === $this->venue->id
            && $e->broadcastOn()->name === 'venue.' . $this->venue->id);

        Event::fake([VenueAvailabilityUpdated::class]);
        Booking::create([
            'quantity' => 1, 'total_amount' => 500, 'status' => 'CONFIRMED',
            'booking_type' => 'event', 'user_id' => $this->owner->id,
        ]);
        Event::assertNotDispatched(VenueAvailabilityUpdated::class);
    }

    public function test_the_date_is_validated_and_bounded(): void
    {
        $base = '/api/venues/' . $this->venue->id . '/availability';

        $this->getJson($base . '?date=tomorrow')->assertStatus(422);
        $this->getJson($base . '?date=' . today()->addDays(90)->toDateString())->assertStatus(422);
        $this->getJson('/api/venues/999999/availability')->assertNotFound();
    }
}
