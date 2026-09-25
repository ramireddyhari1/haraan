<?php

declare(strict_types=1);

namespace Tests\Feature\Membership;

use App\Models\Booking;
use App\Models\Event;
use App\Models\MemberPlanPrice;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueCourt;
use App\Models\VenueSlot;
use App\Services\BookingService;
use App\Services\Membership\MemberSubscriptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

/**
 * The booking perks and longer terms behind Pro and Hero: early ticket access, priority
 * venue booking, and 3- and 6-month prices — each read from the catalogue the migration
 * leaves, and each leaving a regular buyer's rules exactly as they were.
 */
class MemberBookingPerksTest extends TestCase
{
    use MembershipFixtures;
    use RefreshDatabase;

    private function grant(User $user, string $planCode): void
    {
        $admin = $this->member(['role' => 'ADMIN']);
        app(MemberSubscriptions::class)->grant($user, $this->plan($planCode), null, $admin, 'test grant');
    }

    private function event(array $overrides = []): Event
    {
        $host = User::firstOrCreate(
            ['email' => 'host@haraan.test'],
            ['name' => 'Host', 'password' => bcrypt('secret'), 'role' => 'PARTNER', 'status' => 'active'],
        );

        return Event::create(array_merge([
            'partner_id' => $host->id,
            'title' => 'Sunburn Arena',
            'category' => 'Music',
            'location' => 'Gachibowli',
            'venue' => 'Gachibowli Stadium, Hyderabad',
            'date' => now()->addDays(20),
            'time' => '19:00',
            'price' => 0,
            'total_slots' => 100,
            'available_slots' => 100,
            'images' => [],
            'status' => 'published',
        ], $overrides));
    }

    /** A free tier whose sale opens [hours] from now — free, so an order confirms in one step. */
    private function tierOpeningIn(Event $event, int $hours): TicketType
    {
        return TicketType::create([
            'event_id' => $event->id, 'name' => 'General Pass', 'kind' => 'standard', 'price' => 0,
            'capacity' => 50, 'sold' => 0, 'sort' => 1, 'sales_start' => now()->addHours($hours),
        ]);
    }

    private function orderTier(User $buyer, Event $event, TicketType $tier)
    {
        return $this->asMember($buyer)->postJson('/api/bookings', [
            'eventId' => $event->id,
            'items' => [['ticketTypeId' => $tier->id, 'quantity' => 1]],
            'contact' => ['name' => 'Asha Rao', 'email' => 'asha@haraan.test', 'phone' => '9876543210'],
        ]);
    }

    // ── Catalogue ─────────────────────────────────────────────────────────────

    public function test_migration_configures_both_perks_on_every_plan(): void
    {
        $values = fn (string $code, string $key) => $this->plan($code)->entitlements()->where('feature_key', $key)->first(['enabled', 'limit_value'])?->toArray();

        $this->assertSame(['enabled' => false, 'limit_value' => 0], $values('free', 'events.early_access'));
        $this->assertSame(['enabled' => true, 'limit_value' => 12], $values('pro', 'events.early_access'));
        $this->assertSame(['enabled' => true, 'limit_value' => 24], $values('hero', 'events.early_access'));
        $this->assertSame(['enabled' => false, 'limit_value' => 0], $values('free', 'venues.priority_booking_days'));
        $this->assertSame(['enabled' => true, 'limit_value' => 1], $values('pro', 'venues.priority_booking_days'));
        $this->assertSame(['enabled' => true, 'limit_value' => 3], $values('hero', 'venues.priority_booking_days'));
    }

    public function test_migration_prices_every_term_as_draft_prices(): void
    {
        $prices = fn (string $code) => MemberPlanPrice::query()->where('plan_id', $this->plan($code)->id)
            ->pluck('amount_paise', 'interval')->all();

        $this->assertEquals(['month' => 9900, 'quarter' => 24900, 'half_year' => 44900, 'year' => 79900], $prices('pro'));
        $this->assertEquals(['month' => 19900, 'quarter' => 49900, 'half_year' => 89900, 'year' => 159900], $prices('hero'));
        // Drafts: nothing is sold until /control links each one to a Razorpay plan.
        $this->assertSame(0, MemberPlanPrice::query()->where('is_active', true)->count());
    }

    public function test_longer_terms_bill_as_monthly_razorpay_plans_every_three_or_six_periods(): void
    {
        $this->assertSame(['period' => 'monthly', 'every' => 1], MemberPlanPrice::razorpayTerm('month'));
        $this->assertSame(['period' => 'monthly', 'every' => 3], MemberPlanPrice::razorpayTerm('quarter'));
        $this->assertSame(['period' => 'monthly', 'every' => 6], MemberPlanPrice::razorpayTerm('half_year'));
        $this->assertSame(['period' => 'yearly', 'every' => 1], MemberPlanPrice::razorpayTerm('year'));

        $quarter = $this->sellable('pro', MemberPlanPrice::INTERVAL_QUARTER);
        $this->assertSame('₹249/3 months', $quarter->label());
        $this->assertSame('₹449/6 months', $this->sellable('pro', MemberPlanPrice::INTERVAL_HALF_YEAR)->label());
    }

    public function test_catalogue_offers_the_longer_terms_once_they_are_on_sale(): void
    {
        $this->sellable('hero', MemberPlanPrice::INTERVAL_QUARTER);

        $hero = collect($this->getJson('/api/membership/plans')->assertOk()->json('data.plans'))->firstWhere('code', 'hero');

        $this->assertSame([['interval' => 'quarter', 'amount_paise' => 49900, 'label' => '₹499/3 months']],
            collect($hero['prices'])->map(fn (array $p) => collect($p)->only(['interval', 'amount_paise', 'label'])->all())->all());
    }

    // ── Early ticket access ───────────────────────────────────────────────────

    public function test_regular_buyer_waits_for_the_sale_to_open(): void
    {
        $event = $this->event();
        $tier = $this->tierOpeningIn($event, 10);

        $this->orderTier($this->member(), $event, $tier)->assertStatus(409);
        $this->assertSame(0, Booking::query()->count());
    }

    public function test_member_books_within_their_early_access_head_start(): void
    {
        $event = $this->event();
        $tier = $this->tierOpeningIn($event, 10);

        $pro = $this->member();
        $this->grant($pro, 'pro');

        $this->orderTier($pro, $event, $tier)->assertSuccessful();
        $this->assertSame(1, Booking::query()->where('user_id', $pro->id)->count());
    }

    public function test_head_start_is_bounded_by_the_plan(): void
    {
        $event = $this->event();
        $tier = $this->tierOpeningIn($event, 20);

        $pro = $this->member();
        $this->grant($pro, 'pro');   // 12 hours: not enough
        $hero = $this->member();
        $this->grant($hero, 'hero'); // 24 hours: enough

        $this->orderTier($pro, $event, $tier)->assertStatus(409);
        $this->orderTier($hero, $event, $tier)->assertSuccessful();
    }

    public function test_event_payload_marks_tiers_opened_only_by_membership(): void
    {
        $event = $this->event();
        $this->tierOpeningIn($event, 10);

        $tier = fn ($response) => $response->assertOk()->json('data.ticketTypes.0');

        $guest = $tier($this->getJson("/api/events/{$event->id}"));
        $this->assertFalse($guest['onSale']);
        $this->assertFalse($guest['earlyAccess']);

        $hero = $this->member();
        $this->grant($hero, 'hero');
        $member = $tier($this->asMember($hero)->getJson("/api/events/{$event->id}"));
        $this->assertTrue($member['onSale']);
        $this->assertTrue($member['earlyAccess']);
    }

    public function test_website_ticket_sheet_and_checkout_honour_early_access(): void
    {
        $event = $this->event();
        $tier = $this->tierOpeningIn($event, 10);
        $contact = ['contact' => ['name' => 'Asha Rao', 'email' => 'asha@haraan.test', 'phone' => '9876543210']];

        $this->get("/events/{$event->id}")->assertOk()->assertDontSee('qty['.$tier->id.']', false);

        $regular = $this->member();
        $this->actingAs($regular)->post("/events/{$event->id}/book", ['qty' => [$tier->id => 1]] + $contact);
        $this->assertSame(0, Booking::query()->count());

        $hero = $this->member();
        $this->grant($hero, 'hero');
        $this->actingAs($hero)->get("/events/{$event->id}")->assertOk()->assertSee('qty['.$tier->id.']', false);
        $this->actingAs($hero)->post("/events/{$event->id}/book", ['qty' => [$tier->id => 1]] + $contact)->assertRedirect();
        $this->assertSame(1, Booking::query()->where('user_id', $hero->id)->count());
    }

    // ── Priority venue booking ────────────────────────────────────────────────

    /** @return array{0: Venue, 1: VenueCourt, 2: VenueSlot} */
    private function venue(?int $windowDays): array
    {
        $owner = $this->member(['role' => 'PARTNER', 'partner_type' => 'venue']);
        $venue = Venue::create([
            'name' => 'Sportz Arena', 'location' => 'Gachibowli', 'price' => 1400,
            'is_active' => true, 'is_bookable' => true, 'partner_id' => $owner->id,
            'city' => 'Hyderabad', 'images' => ['venues/test.jpg'], 'status' => 'published',
            'booking_window_days' => $windowDays,
        ]);
        $court = VenueCourt::create(['venue_id' => $venue->id, 'name' => 'Turf A', 'price' => 1400, 'is_active' => true]);
        $slot = VenueSlot::create(['venue_id' => $venue->id, 'day' => 'Every day', 'time' => '7:00 PM', 'is_available' => true, 'capacity' => 1]);

        return [$venue, $court, $slot];
    }

    public function test_venue_without_its_own_window_keeps_the_sixty_day_default(): void
    {
        [$venue] = $this->venue(null);

        $this->getJson("/api/venues/{$venue->id}/availability?date=".now()->addDays(60)->toDateString())
            ->assertOk()
            ->assertJsonPath('data.booking_window.days', 60)
            ->assertJsonPath('data.booking_window.priority_days', 0);
        $this->getJson("/api/venues/{$venue->id}/availability?date=".now()->addDays(61)->toDateString())
            ->assertStatus(422)
            ->assertJsonPath('code', 'outside_booking_window');
    }

    public function test_member_sees_and_books_their_priority_days_past_the_window(): void
    {
        [$venue, $court, $slot] = $this->venue(7);
        $day = now()->addDays(9)->toDateString();

        $regular = $this->member();
        $this->asMember($regular)->getJson("/api/venues/{$venue->id}/availability?date={$day}")
            ->assertStatus(422)
            ->assertJsonPath('booking_window.last_date', now()->addDays(7)->toDateString());
        $this->asMember($regular)->postJson('/api/bookings/venue', [
            'venueId' => $venue->id, 'slotId' => $slot->id, 'courtId' => $court->id, 'date' => $day,
        ])->assertStatus(409);

        $hero = $this->member();
        $this->grant($hero, 'hero');
        $this->asMember($hero)->getJson("/api/venues/{$venue->id}/availability?date={$day}")
            ->assertOk()
            ->assertJsonPath('data.booking_window.priority_days', 3)
            ->assertJsonPath('data.booking_window.last_date', now()->addDays(10)->toDateString());
        $this->asMember($hero)->postJson('/api/bookings/venue', [
            'venueId' => $venue->id, 'slotId' => $slot->id, 'courtId' => $court->id, 'date' => $day,
        ])->assertSuccessful();
        $this->assertSame(1, Booking::query()->where('user_id', $hero->id)->where('venue_id', $venue->id)->count());
    }

    public function test_partner_desk_is_never_held_to_the_customer_window(): void
    {
        [$venue, $court, $slot] = $this->venue(7);
        $owner = User::find($venue->partner_id);

        $booking = app(BookingService::class)->createOfflineVenueBooking(
            $owner, $venue->id, $slot->id, now()->addDays(40)->toDateString(), 'Kiran Varma', '9876543210', $court->id,
        );

        $this->assertSame('offline', $booking->channel);
    }

    public function test_regular_customer_booking_inside_the_window_is_unchanged(): void
    {
        [$venue, $court, $slot] = $this->venue(7);

        $this->asMember($this->member())->postJson('/api/bookings/venue', [
            'venueId' => $venue->id, 'slotId' => $slot->id, 'courtId' => $court->id, 'date' => now()->addDays(7)->toDateString(),
        ])->assertSuccessful();

        $this->expectException(ConflictHttpException::class);
        app(BookingService::class)->createVenueBooking($this->member(), $venue->id, $slot->id, now()->addDays(8)->toDateString(), $court->id);
    }
}
