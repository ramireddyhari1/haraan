<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueBlock;
use App\Models\VenueCourt;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

/**
 * One court-hour, one occupant.
 *
 * Bookings, maintenance, holidays, academy batches and tournament holds all
 * compete for the same physical court-hour. Before venue_blocks only bookings
 * competed, so a court under maintenance would happily sell.
 */
class CourtHourConflictTest extends TestCase
{
    use RefreshDatabase;

    private BookingService $service;
    private Venue $venue;
    private VenueCourt $turfA;
    private VenueCourt $turfB;
    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(BookingService::class);

        $this->venue = Venue::create([
            'name' => 'Sportz Arena',
            'location' => 'Gachibowli',
            'price' => 1400,
            'is_active' => true,
            'is_bookable' => true,
        ]);

        $this->turfA = VenueCourt::create([
            'venue_id' => $this->venue->id, 'name' => 'Turf A', 'price' => 1400, 'is_active' => true,
        ]);
        $this->turfB = VenueCourt::create([
            'venue_id' => $this->venue->id, 'name' => 'Turf B', 'price' => 1600, 'is_active' => true,
        ]);

        $this->customer = User::create([
            'name' => 'Rohit Iyer',
            'email' => 'rohit@haraan.test',
            'password' => Hash::make('secret123'),
            'role' => 'user',
            'status' => 'active',
        ]);
    }

    private function date(): string
    {
        return now()->addDay()->toDateString();
    }

    /** Place a booking directly, bypassing the service, to set up a conflict. */
    private function seedBooking(array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'quantity' => 1,
            'total_amount' => 1400,
            'status' => 'CONFIRMED',
            'booking_type' => 'venue',
            'user_id' => $this->customer->id,
            'venue_id' => $this->venue->id,
            'venue_court_id' => $this->turfA->id,
            'slot_date' => $this->date(),
            'start_time' => '19:00',
            'end_time' => '20:00',
            'channel' => 'offline',
        ], $overrides));
    }

    private function block(array $overrides = []): VenueBlock
    {
        return VenueBlock::create(array_merge([
            'venue_id' => $this->venue->id,
            'venue_court_id' => $this->turfA->id,
            'kind' => 'maintenance',
            'title' => 'Surface re-lay',
            'starts_on' => $this->date(),
            'ends_on' => $this->date(),
            'start_time' => '18:00',
            'end_time' => '21:00',
        ], $overrides));
    }

    /** Book Turf A for one hour from $startHour via the real service path. */
    private function book(int $startHour = 19, ?int $courtId = null): Booking
    {
        $slot = $this->venue->slots()->create([
            'day' => now()->addDay()->format('D'),
            'time' => sprintf('%02d:00', $startHour),
            'is_available' => true,
            'capacity' => 1,
        ]);

        return $this->service->createVenueBooking(
            $this->customer,
            $this->venue->id,
            $slot->id,
            $this->date(),
            $courtId ?? $this->turfA->id,
            1,
        );
    }

    // ---------------------------------------------------------------- bookings

    public function test_an_overlapping_booking_on_the_same_court_is_refused(): void
    {
        $this->seedBooking();

        $this->expectException(ConflictHttpException::class);
        $this->book(19);
    }

    public function test_the_same_hour_on_a_different_court_is_allowed(): void
    {
        $this->seedBooking();

        $booking = $this->book(19, $this->turfB->id);

        $this->assertSame($this->turfB->id, $booking->venue_court_id);
    }

    public function test_a_non_overlapping_hour_on_the_same_court_is_allowed(): void
    {
        $this->seedBooking();

        $booking = $this->book(21);

        $this->assertNotNull($booking->id);
    }

    /**
     * The rule that must never regress: occupancy is lifecycle, not money. A
     * booking with a ₹500 advance — or none at all — still owns its court.
     */
    public function test_an_unpaid_booking_still_holds_its_court(): void
    {
        $booking = $this->seedBooking();
        $this->assertSame('unpaid', $booking->payment_status);

        $this->expectException(ConflictHttpException::class);
        $this->book(19);
    }

    public function test_a_live_hold_blocks_but_an_expired_one_releases_the_slot(): void
    {
        $held = $this->seedBooking([
            'status' => 'PENDING',
            'reserved_until' => now()->addMinutes(10),
        ]);

        try {
            $this->book(19);
            $this->fail('A live hold should have blocked the slot.');
        } catch (ConflictHttpException) {
            // expected
        }

        $held->update(['reserved_until' => now()->subMinute()]);

        $this->assertNotNull($this->book(19)->id, 'An expired hold must release the court.');
    }

    public function test_a_cancelled_booking_releases_the_slot(): void
    {
        $this->seedBooking(['status' => 'CANCELLED']);

        $this->assertNotNull($this->book(19)->id);
    }

    // ------------------------------------------------------------------ blocks

    public function test_a_maintenance_window_on_the_court_refuses_a_booking(): void
    {
        $this->block();

        $this->expectException(ConflictHttpException::class);
        $this->book(19);
    }

    public function test_a_booking_outside_the_maintenance_window_is_allowed(): void
    {
        $this->block(['start_time' => '06:00', 'end_time' => '09:00']);

        $this->assertNotNull($this->book(19)->id);
    }

    public function test_a_whole_venue_block_covers_every_court(): void
    {
        $this->block(['venue_court_id' => null, 'kind' => 'holiday', 'title' => 'Independence Day']);

        $this->expectException(ConflictHttpException::class);
        $this->book(19, $this->turfB->id);
    }

    public function test_an_all_day_block_refuses_any_hour(): void
    {
        $this->block(['start_time' => null, 'end_time' => null]);

        $this->expectException(ConflictHttpException::class);
        $this->book(7);
    }

    /** An academy batch: same weekday, every week, for a term. */
    public function test_a_weekday_recurring_block_applies_only_on_that_weekday(): void
    {
        $target = now()->addDay();

        $this->block([
            'kind' => 'academy',
            'title' => 'U-14 batch',
            'starts_on' => $target->copy()->subMonth()->toDateString(),
            'ends_on' => $target->copy()->addMonth()->toDateString(),
            'weekday' => $target->dayOfWeek,
            'start_time' => '19:00',
            'end_time' => '20:00',
        ]);

        try {
            $this->book(19);
            $this->fail('The batch weekday should have blocked the slot.');
        } catch (ConflictHttpException) {
            // expected
        }

        // A different weekday inside the same date range stays bookable.
        VenueBlock::query()->update(['weekday' => $target->copy()->addDay()->dayOfWeek]);

        $this->assertNotNull($this->book(19)->id);
    }

    public function test_the_block_reason_reaches_the_caller(): void
    {
        $this->block(['title' => 'Surface re-lay']);

        try {
            $this->book(19);
            $this->fail('Expected a conflict.');
        } catch (ConflictHttpException $e) {
            $this->assertStringContainsString('Surface re-lay', $e->getMessage());
        }
    }
}
