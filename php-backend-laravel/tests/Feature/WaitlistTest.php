<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Waitlist\WaitlistEntryResource;
use App\Models\Booking;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueCourt;
use App\Models\WaitlistEntry;
use App\Services\WaitlistService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Turning a cancellation into revenue.
 *
 * The behaviour that matters: cancelling a booking — through ANY path, which is
 * why it hangs off an observer — offers that court-hour to whoever was waiting.
 */
class WaitlistTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Venue $venue;
    private VenueCourt $turfA;
    private VenueCourt $turfB;
    private WaitlistService $waitlist;

    protected function setUp(): void
    {
        parent::setUp();

        $this->waitlist = app(WaitlistService::class);

        $this->owner = User::create([
            'name' => 'Rahul Krishnan', 'email' => 'owner@haraan.test',
            'password' => Hash::make('secret123'), 'role' => 'PARTNER',
            'partner_type' => 'venue', 'status' => 'active',
        ]);

        $this->venue = Venue::create([
            'name' => 'Sportz Arena', 'location' => 'Gachibowli', 'price' => 1400,
            'is_active' => true, 'is_bookable' => true, 'partner_id' => $this->owner->id,
        ]);

        $this->turfA = VenueCourt::create(['venue_id' => $this->venue->id, 'name' => 'Turf A', 'is_active' => true]);
        $this->turfB = VenueCourt::create(['venue_id' => $this->venue->id, 'name' => 'Turf B', 'is_active' => true]);
    }

    private function booking(array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'quantity' => 1, 'total_amount' => 4400, 'status' => 'CONFIRMED',
            'booking_type' => 'venue', 'user_id' => $this->owner->id,
            'venue_id' => $this->venue->id, 'venue_court_id' => $this->turfA->id,
            'slot_date' => today()->addDay()->toDateString(),
            'start_time' => '19:00', 'end_time' => '21:00', 'channel' => 'offline',
        ], $overrides));
    }

    private function waiter(array $overrides = []): WaitlistEntry
    {
        return WaitlistEntry::create(array_merge([
            'venue_id' => $this->venue->id,
            'venue_court_id' => null,
            'wanted_on' => today()->addDay()->toDateString(),
            'start_time' => '19:00',
            'end_time' => '20:00',
            'guest_name' => 'Kiran Varma',
            'guest_phone' => '+91 90000 55412',
        ], $overrides));
    }

    // ------------------------------------------------------------- matching

    public function test_cancelling_a_booking_offers_the_slot_automatically(): void
    {
        $waiter = $this->waiter();
        $booking = $this->booking();

        $booking->update(['status' => 'CANCELLED']);

        $waiter->refresh();

        $this->assertSame(WaitlistEntry::STATUS_OFFERED, $waiter->status);
        $this->assertSame($booking->id, $waiter->freed_by_booking_id);
        $this->assertTrue($waiter->isOfferLive());
        $this->assertTrue($waiter->offer_expires_at->isFuture());
    }

    /** Mixed-case status is endemic here — the observer must catch both. */
    public function test_lowercase_cancellation_also_fires(): void
    {
        $waiter = $this->waiter();

        $this->booking()->update(['status' => 'cancelled']);

        $this->assertSame(WaitlistEntry::STATUS_OFFERED, $waiter->refresh()->status);
    }

    public function test_editing_an_already_cancelled_booking_does_not_re_offer(): void
    {
        $booking = $this->booking(['status' => 'CANCELLED']);
        $waiter = $this->waiter();

        $booking->update(['note' => 'customer called']);

        $this->assertSame(WaitlistEntry::STATUS_WAITING, $waiter->refresh()->status);
    }

    public function test_an_entry_wanting_any_time_matches_any_freed_slot_that_day(): void
    {
        $waiter = $this->waiter(['start_time' => null, 'end_time' => null]);

        $this->booking(['start_time' => '06:00', 'end_time' => '07:00'])->update(['status' => 'CANCELLED']);

        $this->assertSame(WaitlistEntry::STATUS_OFFERED, $waiter->refresh()->status);
    }

    public function test_a_non_overlapping_window_is_not_offered(): void
    {
        $waiter = $this->waiter(['start_time' => '06:00', 'end_time' => '07:00']);

        $this->booking(['start_time' => '19:00', 'end_time' => '21:00'])->update(['status' => 'CANCELLED']);

        $this->assertSame(WaitlistEntry::STATUS_WAITING, $waiter->refresh()->status);
    }

    public function test_someone_holding_out_for_a_specific_court_is_not_offered_another(): void
    {
        $anyCourt = $this->waiter();
        $turfBOnly = $this->waiter(['venue_court_id' => $this->turfB->id, 'guest_name' => 'Meera']);

        $this->booking(['venue_court_id' => $this->turfA->id])->update(['status' => 'CANCELLED']);

        $this->assertSame(WaitlistEntry::STATUS_OFFERED, $anyCourt->refresh()->status);
        $this->assertSame(WaitlistEntry::STATUS_WAITING, $turfBOnly->refresh()->status);
    }

    public function test_a_different_date_is_not_offered(): void
    {
        $waiter = $this->waiter(['wanted_on' => today()->addDays(5)->toDateString()]);

        $this->booking()->update(['status' => 'CANCELLED']);

        $this->assertSame(WaitlistEntry::STATUS_WAITING, $waiter->refresh()->status);
    }

    public function test_another_venues_waitlist_is_untouched(): void
    {
        $otherVenue = Venue::create([
            'name' => 'Rival Turf', 'location' => 'Kondapur',
            'is_active' => true, 'is_bookable' => true,
        ]);
        $theirs = $this->waiter(['venue_id' => $otherVenue->id]);

        $this->booking()->update(['status' => 'CANCELLED']);

        $this->assertSame(WaitlistEntry::STATUS_WAITING, $theirs->refresh()->status);
    }

    public function test_event_bookings_never_touch_the_venue_waitlist(): void
    {
        $waiter = $this->waiter();

        Booking::create([
            'quantity' => 1, 'total_amount' => 1200, 'status' => 'CONFIRMED',
            'booking_type' => 'event', 'user_id' => $this->owner->id,
        ])->update(['status' => 'CANCELLED']);

        $this->assertSame(WaitlistEntry::STATUS_WAITING, $waiter->refresh()->status);
    }

    // -------------------------------------------------------------- offering

    /** Offer several at once — one at a time means a 90-minute wait per non-responder. */
    public function test_the_freed_slot_is_offered_to_several_people_oldest_first(): void
    {
        $waiters = collect(range(1, 5))->map(fn (int $i) => tap(
            $this->waiter(['guest_name' => "Caller {$i}"]),
            fn (WaitlistEntry $e) => $e->forceFill(['created_at' => now()->subMinutes(60 - $i)])->save(),
        ));

        $this->booking()->update(['status' => 'CANCELLED']);

        $offered = WaitlistEntry::where('status', WaitlistEntry::STATUS_OFFERED)->pluck('guest_name');

        $this->assertCount(WaitlistService::OFFERS_PER_SLOT, $offered);
        $this->assertContains('Caller 1', $offered->all(), 'The earliest request is offered first.');
        $this->assertNotContains('Caller 5', $offered->all());
    }

    /**
     * An un-expiring offer is worse than no waitlist: the slot looks sold and earns
     * nothing. Lapsed offers put the person back in the queue rather than dropping
     * them — they still want it, they just missed this one.
     */
    public function test_a_lapsed_offer_returns_the_person_to_the_queue(): void
    {
        $waiter = $this->waiter();
        $this->booking()->update(['status' => 'CANCELLED']);

        $waiter->refresh()->forceFill(['offer_expires_at' => now()->subMinute()])->save();

        $this->assertSame(1, $this->waitlist->releaseLapsedOffers());

        $waiter->refresh();
        $this->assertSame(WaitlistEntry::STATUS_WAITING, $waiter->status);
        $this->assertNull($waiter->offer_expires_at);
        $this->assertNull($waiter->freed_by_booking_id);
    }

    public function test_a_live_offer_is_not_released(): void
    {
        $this->waiter();
        $this->booking()->update(['status' => 'CANCELLED']);

        $this->assertSame(0, $this->waitlist->releaseLapsedOffers());
    }

    // ------------------------------------------------------------- outcomes

    public function test_converting_records_what_the_waitlist_recovered(): void
    {
        $waiter = $this->waiter();
        $this->booking()->update(['status' => 'CANCELLED']);

        $replacement = $this->booking(['total_amount' => 4400, 'guest_name' => 'Kiran Varma']);
        $this->waitlist->convert($waiter->refresh(), $replacement);

        $this->assertSame(WaitlistEntry::STATUS_CONVERTED, $waiter->refresh()->status);
        $this->assertSame(4400.0, $this->waitlist->recovered($this->venue->id));
    }

    public function test_a_cancelled_entry_is_no_longer_offered_anything(): void
    {
        $waiter = $this->waiter();
        $this->waitlist->cancel($waiter);

        $this->booking()->update(['status' => 'CANCELLED']);

        $this->assertSame(WaitlistEntry::STATUS_CANCELLED, $waiter->refresh()->status);
    }

    public function test_entries_are_scoped_to_the_partners_own_venues(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('partner'));
        $this->actingAs($this->owner);

        $mine = $this->waiter();

        $otherVenue = Venue::create([
            'name' => 'Rival Turf', 'location' => 'Kondapur',
            'is_active' => true, 'is_bookable' => true,
        ]);
        $theirs = $this->waiter(['venue_id' => $otherVenue->id]);

        $ids = WaitlistEntryResource::getEloquentQuery()->pluck('id')->all();

        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($theirs->id, $ids);
    }
}
