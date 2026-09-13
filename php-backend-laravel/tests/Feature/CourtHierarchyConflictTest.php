<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueCourt;
use App\Support\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class CourtHierarchyConflictTest extends TestCase
{
    use RefreshDatabase;

    private User $partner;
    private Venue $venue;
    private VenueCourt $parentCourt;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->partner = User::create([
            'name' => 'Partner Admin',
            'email' => 'partner_hierarchy@test.com',
            'password' => Hash::make('password'),
            'role' => 'PARTNER',
            'partner_type' => 'venue',
            'status' => 'active',
        ]);

        $this->venue = Venue::create([
            'name' => 'Mega Turf City',
            'location' => 'Kondapur',
            'price' => 2000,
            'is_active' => true,
            'is_bookable' => true,
            'partner_id' => $this->partner->id,
        ]);

        $this->parentCourt = VenueCourt::create([
            'venue_id' => $this->venue->id,
            'name' => 'Full Arena (11v11)',
            'price' => 2400,
            'is_active' => true,
        ]);

        $secret = config('app.jwt_secret') ?: env('JWT_SECRET', 'change_me');
        $this->token = JwtService::issueForUser($this->partner, $secret);
    }

    public function test_can_split_parent_court_into_sub_courts(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/partner/venues/{$this->venue->id}/pricing/hierarchy/split", [
                'court_id'   => $this->parentCourt->id,
                'split_type' => 'half',
                'partitions' => [
                    ['name' => 'Half Pitch A (5v5)', 'label' => 'North Half', 'price' => 1400],
                    ['name' => 'Half Pitch B (5v5)', 'label' => 'South Half', 'price' => 1400],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('parent.is_composite', true)
            ->assertJsonPath('parent.split_type', 'half');

        $this->assertDatabaseCount('venue_courts', 3); // 1 parent + 2 children
        $this->assertDatabaseHas('venue_courts', [
            'parent_court_id' => $this->parentCourt->id,
            'name'            => 'Half Pitch A (5v5)',
            'price'           => 1400,
        ]);
        $this->assertDatabaseHas('venue_courts', [
            'parent_court_id' => $this->parentCourt->id,
            'name'            => 'Half Pitch B (5v5)',
            'price'           => 1400,
        ]);
    }

    public function test_parent_court_booking_locks_all_child_courts(): void
    {
        // Split court
        $childA = VenueCourt::create([
            'venue_id'        => $this->venue->id,
            'parent_court_id' => $this->parentCourt->id,
            'name'            => 'Child A',
            'price'           => 1400,
            'is_active'       => true,
        ]);
        $childB = VenueCourt::create([
            'venue_id'        => $this->venue->id,
            'parent_court_id' => $this->parentCourt->id,
            'name'            => 'Child B',
            'price'           => 1400,
            'is_active'       => true,
        ]);
        $this->parentCourt->update(['is_composite' => true, 'split_type' => 'half']);

        // Create booking on Full Arena (Parent) for today 18:00 - 19:00
        Booking::create([
            'user_id'        => $this->partner->id,
            'venue_id'       => $this->venue->id,
            'venue_court_id' => $this->parentCourt->id,
            'booking_type'   => 'venue',
            'date'           => '2026-09-10',
            'slot_date'      => '2026-09-10',
            'start_time'     => '18:00',
            'end_time'       => '19:00',
            'amount_paid'    => 2400,
            'total_amount'   => 2400,
            'quantity'       => 1,
            'status'         => 'CONFIRMED',
        ]);

        // Attempting to book Child A during the same time window must be rejected
        $bookingService = app(\App\Services\BookingService::class);

        $slot18 = \App\Models\VenueSlot::create([
            'venue_id'     => $this->venue->id,
            'day'          => 'Thursday',
            'time'         => '18:00',
            'price'        => 1400,
            'capacity'     => 10,
            'is_available' => true,
        ]);

        $this->expectException(ConflictHttpException::class);
        $this->expectExceptionMessage('Court conflict: Connected composite or sub-court is already booked for this time');

        $bookingService->createOfflineVenueBooking(
            partner: $this->partner,
            venueId: $this->venue->id,
            slotId: $slot18->id,
            date: '2026-09-10',
            guestName: 'Customer X',
            guestPhone: '9876543210',
            courtId: $childA->id,
            duration: 1,
        );
    }

    public function test_child_court_booking_locks_parent_court(): void
    {
        // Split court
        $childA = VenueCourt::create([
            'venue_id'        => $this->venue->id,
            'parent_court_id' => $this->parentCourt->id,
            'name'            => 'Child A',
            'price'           => 1400,
            'is_active'       => true,
        ]);
        VenueCourt::create([
            'venue_id'        => $this->venue->id,
            'parent_court_id' => $this->parentCourt->id,
            'name'            => 'Child B',
            'price'           => 1400,
            'is_active'       => true,
        ]);
        $this->parentCourt->update(['is_composite' => true, 'split_type' => 'half']);

        // Create booking on Child A for today 20:00 - 21:00
        Booking::create([
            'user_id'        => $this->partner->id,
            'venue_id'       => $this->venue->id,
            'venue_court_id' => $childA->id,
            'booking_type'   => 'venue',
            'date'           => '2026-09-10',
            'slot_date'      => '2026-09-10',
            'start_time'     => '20:00',
            'end_time'       => '21:00',
            'amount_paid'    => 1400,
            'total_amount'   => 1400,
            'quantity'       => 1,
            'status'         => 'CONFIRMED',
        ]);

        $bookingService = app(\App\Services\BookingService::class);

        $slot20 = \App\Models\VenueSlot::create([
            'venue_id'     => $this->venue->id,
            'day'          => 'Thursday',
            'time'         => '20:00',
            'price'        => 2400,
            'capacity'     => 20,
            'is_available' => true,
        ]);

        $this->expectException(ConflictHttpException::class);
        $this->expectExceptionMessage('Court conflict: Connected composite or sub-court is already booked for this time');

        // Attempting to book the Full Arena (Parent) must fail because Child A is occupied
        $bookingService->createOfflineVenueBooking(
            partner: $this->partner,
            venueId: $this->venue->id,
            slotId: $slot20->id,
            date: '2026-09-10',
            guestName: 'Customer Y',
            guestPhone: '9876543211',
            courtId: $this->parentCourt->id,
            duration: 1,
        );
    }

    public function test_can_merge_courts_back_to_parent(): void
    {
        $childA = VenueCourt::create([
            'venue_id'        => $this->venue->id,
            'parent_court_id' => $this->parentCourt->id,
            'name'            => 'Child A',
            'price'           => 1400,
            'is_active'       => true,
        ]);
        $this->parentCourt->update(['is_composite' => true, 'split_type' => 'half']);

        $res = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/partner/venues/{$this->venue->id}/pricing/hierarchy/merge", [
                'court_id' => $this->parentCourt->id,
            ]);

        $res->assertStatus(200);

        $this->assertDatabaseMissing('venue_courts', ['id' => $childA->id]);
        $this->assertDatabaseHas('venue_courts', [
            'id'           => $this->parentCourt->id,
            'is_composite' => false,
            'split_type'   => 'none',
        ]);
    }
}
