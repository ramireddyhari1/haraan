<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\VenueBlocks\VenueBlockResource;
use App\Filament\Resources\VenueBookings\Pages\ListVenueBookings;
use App\Filament\Resources\VenueBookings\VenueBookingResource;
use App\Models\Booking;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueBlock;
use App\Models\VenueCourt;
use App\Services\BookingLedger;
use App\Services\BookingService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

/**
 * The two surfaces that turn phase 1's data model into something a venue owner
 * can actually operate: the balance-due chase list, and the block editor that
 * finally feeds the court-hour conflict engine.
 */
class VenueBookingsAndBlocksTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Venue $venue;
    private VenueCourt $court;

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

        $this->court = VenueCourt::create([
            'venue_id' => $this->venue->id, 'name' => 'Turf A', 'price' => 1400, 'is_active' => true,
        ]);

        Filament::setCurrentPanel(Filament::getPanel('partner'));
        $this->actingAs($this->owner);
    }

    private function booking(float $total = 4400, array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'quantity' => 1,
            'total_amount' => $total,
            'status' => 'CONFIRMED',
            'booking_type' => 'venue',
            'user_id' => $this->owner->id,
            'venue_id' => $this->venue->id,
            'venue_court_id' => $this->court->id,
            'slot_date' => today()->toDateString(),
            'start_time' => '19:00',
            'end_time' => '21:00',
            'channel' => 'offline',
            'guest_name' => 'Kiran Varma',
            'guest_phone' => '+91 90000 55412',
        ], $overrides));
    }

    // ------------------------------------------------------- the chase list

    public function test_the_outstanding_query_finds_only_real_chase_targets(): void
    {
        $due = $this->booking();                                    // unpaid
        $partial = $this->booking();
        app(BookingLedger::class)->collect($partial, 500, 'upi');   // partial

        $settled = $this->booking();
        app(BookingLedger::class)->collect($settled, 4400, 'cash'); // paid

        $refunded = $this->booking();
        app(BookingLedger::class)->collect($refunded, 4400, 'online');
        app(BookingLedger::class)->refund($refunded, 4400, 'online');

        $cancelled = $this->booking(4400, ['status' => 'CANCELLED']);

        $ids = VenueBookingResource::outstanding()->pluck('id')->all();

        $this->assertContains($due->id, $ids);
        $this->assertContains($partial->id, $ids);
        $this->assertNotContains($settled->id, $ids, 'A settled booking is not owed.');
        $this->assertNotContains($refunded->id, $ids, 'A refunded booking is not a chase target.');
        $this->assertNotContains($cancelled->id, $ids, 'A cancelled booking is not owed.');
    }

    public function test_collecting_the_balance_writes_through_the_ledger(): void
    {
        $booking = $this->booking();
        app(BookingLedger::class)->collect($booking, 500, 'upi');

        Livewire::test(ListVenueBookings::class)
            ->callTableAction('collect', $booking, [
                'amount' => 3900,
                'method' => 'cash',
                'reference' => 'receipt-22',
            ])
            ->assertHasNoTableActionErrors();

        $booking->refresh();

        $this->assertSame('paid', $booking->payment_status);
        $this->assertSame(0.0, $booking->balanceDue());
        $this->assertCount(2, $booking->payments);
        // The staff member who took it is recorded — this is the shift close-out trail.
        $this->assertSame($this->owner->id, $booking->payments()->latest('id')->first()->collected_by);
    }

    public function test_a_part_payment_leaves_the_booking_on_the_chase_list(): void
    {
        $booking = $this->booking();

        Livewire::test(ListVenueBookings::class)
            ->callTableAction('collect', $booking, ['amount' => 1000, 'method' => 'cash'])
            ->assertHasNoTableActionErrors();

        $booking->refresh();

        $this->assertSame('partial', $booking->payment_status);
        $this->assertSame(3400.0, $booking->balanceDue());
        $this->assertContains($booking->id, VenueBookingResource::outstanding()->pluck('id')->all());
    }

    public function test_a_refund_is_recorded_as_a_negative_row(): void
    {
        $booking = $this->booking();
        app(BookingLedger::class)->collect($booking, 4400, 'online');

        Livewire::test(ListVenueBookings::class)
            ->callTableAction('refund', $booking, ['amount' => 2200, 'method' => 'upi'])
            ->assertHasNoTableActionErrors();

        $booking->refresh();

        $this->assertSame('part_refunded', $booking->payment_status);
        $this->assertSame(2200.0, (float) $booking->amount_paid);
    }

    public function test_the_list_only_shows_this_partners_venue_bookings(): void
    {
        $mine = $this->booking();

        $otherOwner = User::create([
            'name' => 'Other', 'email' => 'other@haraan.test', 'password' => Hash::make('x'),
            'role' => 'PARTNER', 'partner_type' => 'venue', 'status' => 'active',
        ]);
        $otherVenue = Venue::create([
            'name' => 'Rival Turf', 'location' => 'Kondapur', 'is_active' => true,
            'is_bookable' => true, 'partner_id' => $otherOwner->id,
        ]);
        $theirs = $this->booking(1400, ['venue_id' => $otherVenue->id, 'venue_court_id' => null]);

        $ids = VenueBookingResource::getEloquentQuery()->pluck('id')->all();

        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($theirs->id, $ids, 'A partner must never see another venue\'s bookings.');
    }

    public function test_bookings_cannot_be_created_or_edited_from_this_surface(): void
    {
        // Creating here would bypass the court-hour conflict engine.
        $this->assertFalse(VenueBookingResource::canCreate());
        $this->assertFalse(VenueBookingResource::canEdit($this->booking()));
    }

    // ------------------------------------------------------------- blocking

    public function test_a_block_created_here_immediately_stops_the_booking_engine(): void
    {
        $date = today()->addDay();

        // Bookable before the block exists.
        $slot = $this->venue->slots()->create([
            'day' => $date->format('D'), 'time' => '19:00', 'is_available' => true, 'capacity' => 1,
        ]);

        VenueBlock::create([
            'venue_id' => $this->venue->id,
            'venue_court_id' => $this->court->id,
            'kind' => 'maintenance',
            'title' => 'Surface re-lay',
            'starts_on' => $date->toDateString(),
            'ends_on' => $date->toDateString(),
            'start_time' => '18:00',
            'end_time' => '21:00',
            'created_by' => $this->owner->id,
        ]);

        $this->expectException(ConflictHttpException::class);

        app(BookingService::class)->createVenueBooking(
            $this->owner, $this->venue->id, $slot->id, $date->toDateString(), $this->court->id, 1,
        );
    }

    public function test_blocks_are_scoped_to_the_partners_own_venues(): void
    {
        $mine = VenueBlock::create([
            'venue_id' => $this->venue->id, 'kind' => 'holiday',
            'starts_on' => today(), 'ends_on' => today(),
        ]);

        $otherOwner = User::create([
            'name' => 'Other', 'email' => 'other2@haraan.test', 'password' => Hash::make('x'),
            'role' => 'PARTNER', 'partner_type' => 'venue', 'status' => 'active',
        ]);
        $otherVenue = Venue::create([
            'name' => 'Rival Turf', 'location' => 'Kondapur', 'is_active' => true,
            'is_bookable' => true, 'partner_id' => $otherOwner->id,
        ]);
        $theirs = VenueBlock::create([
            'venue_id' => $otherVenue->id, 'kind' => 'holiday',
            'starts_on' => today(), 'ends_on' => today(),
        ]);

        $ids = VenueBlockResource::getEloquentQuery()->pluck('id')->all();

        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($theirs->id, $ids);
    }

    /** Taking inventory off sale needs the same capability as repricing it. */
    public function test_a_desk_person_without_pricing_cannot_block_time(): void
    {
        $desk = User::create([
            'name' => 'Front Desk',
            'email' => 'desk@haraan.test',
            'password' => Hash::make('secret123'),
            'role' => 'PARTNER',
            'partner_type' => 'venue',
            'status' => 'active',
            'parent_partner_id' => $this->owner->id,
            'staff_permissions' => ['bookings', 'checkin'],
        ]);

        $this->actingAs($desk);

        $this->assertTrue(VenueBlockResource::canAccess(), 'They can still see what is blocked.');
        $this->assertFalse(VenueBlockResource::canCreate(), 'But not take a court off sale.');
    }
}
