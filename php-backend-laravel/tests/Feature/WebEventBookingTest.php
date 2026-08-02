<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The website's Book Tickets flow (/events/{id}/book) must create the same
 * bookings the app's /api/bookings does — both call BookingService::createOrder.
 */
class WebEventBookingTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::create([
            'name'     => 'Web Buyer',
            'email'    => 'buyer@example.test',
            'password' => bcrypt('secret'),
            'role'     => 'user',
            'status'   => 'active',
        ]);
    }

    /** The attendee-contact block the checkout form always requires. */
    private function contact(): array
    {
        return ['contact' => ['name' => 'Web Buyer', 'email' => 'buyer@example.test', 'phone' => '9876543210']];
    }

    private function event(array $overrides = []): Event
    {
        $partner = User::firstOrCreate(
            ['email' => 'host@example.test'],
            ['name' => 'Host', 'password' => bcrypt('secret'), 'role' => 'PARTNER', 'status' => 'active'],
        );

        return Event::create(array_merge([
            'partner_id'      => $partner->id,
            'title'           => 'Test Concert',
            'category'        => 'Music',
            'location'        => 'Test Arena',
            'venue'           => 'Test Arena, Hyderabad',
            'date'            => now()->addDays(7),
            'time'            => '19:00',
            'price'           => 249,
            'total_slots'     => 100,
            'available_slots' => 100,
            'images'          => [],
            'status'          => 'published',
        ], $overrides));
    }

    public function test_guest_is_sent_to_login_and_intended_url_survives(): void
    {
        $event = $this->event();

        $response = $this->get("/events/{$event->id}/book?qty[0]=2");

        $response->assertRedirect(route('site.login'));
        $this->assertStringContainsString(
            "/events/{$event->id}/book",
            (string) session('url.intended'),
        );
    }

    public function test_checkout_review_prices_the_flat_event_rate(): void
    {
        $event = $this->event();

        $this->actingAs($this->user())
            ->get("/events/{$event->id}/book?qty[0]=2")
            ->assertOk()
            ->assertSee('Test Concert')
            ->assertSee('Standard × 2')
            ->assertSee('498.00');
    }

    /** A free event skips payment: the booking confirms on the spot and lands on the pass. */
    public function test_confirm_creates_booking_and_shows_pass(): void
    {
        $event = $this->event(['price' => 0]);
        $user  = $this->user();

        $response = $this->actingAs($user)
            ->post("/events/{$event->id}/book", ['qty' => [0 => 2]] + $this->contact());

        $booking = Booking::query()->firstOrFail();
        $response->assertRedirect(route('site.booking.pass', ['id' => $booking->id]));

        $this->assertSame(2, $booking->quantity);
        $this->assertSame('CONFIRMED', $booking->status);
        $this->assertSame(0.0, (float) $booking->total_amount);
        $this->assertSame(98, $event->fresh()->available_slots);
        $this->assertNotEmpty($booking->ticket_code);

        $this->actingAs($user)
            ->get("/bookings/{$booking->id}/pass")
            ->assertOk()
            ->assertSee($booking->ticket_code)
            ->assertSee('haraan:ticket:'.$booking->ticket_code, false);
    }

    /**
     * A paid order reserves each tier at its own price and stops on the payment page
     * (status PENDING until Razorpay confirms) — seats are held, not yet ticketed.
     */
    public function test_tiered_order_books_each_tier_at_its_own_price(): void
    {
        $event = $this->event();
        $gold  = TicketType::create(['event_id' => $event->id, 'name' => 'Gold', 'kind' => 'paid', 'price' => 500, 'quota' => 10, 'sold' => 0, 'sort' => 1]);
        $silver = TicketType::create(['event_id' => $event->id, 'name' => 'Silver', 'kind' => 'paid', 'price' => 300, 'quota' => 10, 'sold' => 0, 'sort' => 2]);

        $this->actingAs($this->user())
            ->post("/events/{$event->id}/book", ['qty' => [$gold->id => 1, $silver->id => 2]] + $this->contact())
            ->assertOk();

        $this->assertSame(2, Booking::query()->count());
        $this->assertSame(['PENDING'], Booking::query()->pluck('status')->unique()->all());
        $this->assertSame(500.0, (float) Booking::query()->where('ticket_type_id', $gold->id)->value('total_amount'));
        $this->assertSame(600.0, (float) Booking::query()->where('ticket_type_id', $silver->id)->value('total_amount'));
        $this->assertSame(1, $gold->fresh()->sold);
        $this->assertSame(2, $silver->fresh()->sold);
        $this->assertSame(97, $event->fresh()->available_slots);
    }

    public function test_overbooking_bounces_back_with_error_and_books_nothing(): void
    {
        $event = $this->event(['available_slots' => 1]);

        $this->actingAs($this->user())
            ->from("/events/{$event->id}/book?qty[0]=3")
            ->post("/events/{$event->id}/book", ['qty' => [0 => 3]] + $this->contact())
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(0, Booking::query()->count());
        $this->assertSame(1, $event->fresh()->available_slots);
    }

    public function test_draft_event_cannot_be_booked(): void
    {
        $event = $this->event(['status' => 'draft']);

        $this->actingAs($this->user())
            ->post("/events/{$event->id}/book", ['qty' => [0 => 1]])
            ->assertNotFound();

        $this->assertSame(0, Booking::query()->count());
    }

    public function test_foreign_tier_ids_are_ignored(): void
    {
        $event = $this->event();
        $other = $this->event(['title' => 'Other Show']);
        $foreignTier = TicketType::create(['event_id' => $other->id, 'name' => 'VIP', 'kind' => 'paid', 'price' => 900, 'quota' => 5, 'sold' => 0, 'sort' => 1]);

        // Only the foreign tier is requested → the cart is effectively empty.
        $this->actingAs($this->user())
            ->post("/events/{$event->id}/book", ['qty' => [$foreignTier->id => 2]])
            ->assertRedirect("/events/{$event->id}");

        $this->assertSame(0, Booking::query()->count());
    }

    /**
     * Release phases must reach the buyer: a phase-2 tier is listed as locked (no
     * quantity input, so it can't be selected) and refuses a hand-typed order until
     * the earlier phase sells out.
     */
    public function test_unreleased_phase_tier_is_locked_on_the_site_and_at_checkout(): void
    {
        // Free tiers so the order confirms in one step — the phase gate is what's under test.
        $event = $this->event(['price' => 0, 'release_phases' => [['name' => 'Early Bird'], ['name' => 'Phase 2']]]);
        $early = TicketType::create(['event_id' => $event->id, 'name' => 'Early Bird Pass', 'kind' => 'standard', 'price' => 0, 'capacity' => 2, 'sold' => 0, 'sort' => 1, 'release_phase' => 0]);
        $late  = TicketType::create(['event_id' => $event->id, 'name' => 'Phase Two Pass', 'kind' => 'standard', 'price' => 0, 'capacity' => 5, 'sold' => 0, 'sort' => 2, 'release_phase' => 1]);

        $this->get("/events/{$event->id}")
            ->assertOk()
            ->assertSee('Phase Two Pass')
            ->assertSee('Opens when Early Bird sells out')
            ->assertSee('qty['.$early->id.']', false)
            ->assertDontSee('qty['.$late->id.']', false);

        $buyer = $this->user();

        $this->actingAs($buyer)
            ->post("/events/{$event->id}/book", ['qty' => [$late->id => 1]] + $this->contact())
            ->assertRedirect("/events/{$event->id}");
        $this->assertSame(0, Booking::query()->count());

        // Early Bird sells out → Phase 2 opens for real.
        $early->update(['sold' => 2]);

        $this->get("/events/{$event->id}")
            ->assertOk()
            ->assertDontSee('Opens when Early Bird sells out')
            ->assertSee('qty['.$late->id.']', false);

        $this->actingAs($buyer)
            ->post("/events/{$event->id}/book", ['qty' => [$late->id => 1]] + $this->contact())
            ->assertRedirect();
        $this->assertSame(1, Booking::query()->where('ticket_type_id', $late->id)->count());
    }

    /** A hidden tier is not buyable from the website, the same rule the API applies. */
    public function test_hidden_tier_never_reaches_web_checkout(): void
    {
        $event  = $this->event();
        $hidden = TicketType::create(['event_id' => $event->id, 'name' => 'Staff Comp', 'kind' => 'paid', 'price' => 1, 'capacity' => 5, 'sold' => 0, 'sort' => 1, 'visible' => false]);

        $this->actingAs($this->user())
            ->post("/events/{$event->id}/book", ['qty' => [$hidden->id => 1]] + $this->contact())
            ->assertRedirect("/events/{$event->id}");

        $this->assertSame(0, Booking::query()->count());
    }

    public function test_pass_page_is_owner_only(): void
    {
        $event = $this->event();
        $owner = $this->user();

        $this->actingAs($owner)->post("/events/{$event->id}/book", ['qty' => [0 => 1]] + $this->contact());
        $booking = Booking::query()->firstOrFail();

        $stranger = User::create([
            'name' => 'Someone Else', 'email' => 'other@example.test',
            'password' => bcrypt('secret'), 'role' => 'user', 'status' => 'active',
        ]);

        $this->actingAs($stranger)
            ->get("/bookings/{$booking->id}/pass")
            ->assertNotFound();
    }

    public function test_multi_slot_booking_requires_a_session_and_tracks_its_inventory(): void
    {
        $event = $this->event(['price' => 0]);
        $user  = $this->user();
        // Two sessions turn on slot selection; the second one is capped at 2 seats.
        $morning = \App\Models\EventSlot::create(['event_id' => $event->id, 'label' => 'Morning', 'starts_at' => now()->addDays(7)->setTime(10, 0), 'sort' => 0]);
        $evening = \App\Models\EventSlot::create(['event_id' => $event->id, 'label' => 'Evening', 'starts_at' => now()->addDays(7)->setTime(19, 0), 'capacity' => 2, 'sort' => 1]);

        // No session chosen on a multi-session event → bounced, nothing booked.
        $this->actingAs($user)
            ->from("/events/{$event->id}")
            ->post("/events/{$event->id}/book", ['qty' => [0 => 1]] + $this->contact())
            ->assertRedirect()
            ->assertSessionHas('error');
        $this->assertSame(0, Booking::query()->count());

        // Booking the capped evening session decrements its own sold count. A ₹0
        // event confirms immediately, so this redirects to the pass.
        $this->actingAs($user)
            ->post("/events/{$event->id}/book", ['qty' => [0 => 2], 'eventSlotId' => $evening->id] + $this->contact())
            ->assertRedirect();

        $this->assertSame(2, (int) $evening->fresh()->sold);
        $this->assertSame(0, (int) $morning->fresh()->sold);
        $this->assertSame($evening->id, (int) Booking::query()->value('event_slot_id'));

        // The evening session is now full → a further booking there is refused.
        $this->actingAs($user)
            ->from("/events/{$event->id}")
            ->post("/events/{$event->id}/book", ['qty' => [0 => 1], 'eventSlotId' => $evening->id] + $this->contact())
            ->assertRedirect()
            ->assertSessionHas('error');
        $this->assertSame(1, Booking::query()->count());
    }
}
