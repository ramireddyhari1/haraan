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

    public function test_confirm_creates_booking_and_shows_pass(): void
    {
        $event = $this->event();
        $user  = $this->user();

        $response = $this->actingAs($user)
            ->post("/events/{$event->id}/book", ['qty' => [0 => 2]]);

        $booking = Booking::query()->firstOrFail();
        $response->assertRedirect(route('site.booking.pass', ['id' => $booking->id]));

        $this->assertSame(2, $booking->quantity);
        $this->assertSame('CONFIRMED', $booking->status);
        $this->assertSame(498.0, (float) $booking->total_amount);
        $this->assertSame(98, $event->fresh()->available_slots);
        $this->assertNotEmpty($booking->ticket_code);

        $this->actingAs($user)
            ->get("/bookings/{$booking->id}/pass")
            ->assertOk()
            ->assertSee($booking->ticket_code)
            ->assertSee('haraan:ticket:'.$booking->ticket_code, false);
    }

    public function test_tiered_order_books_each_tier_at_its_own_price(): void
    {
        $event = $this->event();
        $gold  = TicketType::create(['event_id' => $event->id, 'name' => 'Gold', 'kind' => 'paid', 'price' => 500, 'quota' => 10, 'sold' => 0, 'sort' => 1]);
        $silver = TicketType::create(['event_id' => $event->id, 'name' => 'Silver', 'kind' => 'paid', 'price' => 300, 'quota' => 10, 'sold' => 0, 'sort' => 2]);

        $this->actingAs($this->user())
            ->post("/events/{$event->id}/book", ['qty' => [$gold->id => 1, $silver->id => 2]])
            ->assertRedirect();

        $this->assertSame(2, Booking::query()->count());
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
            ->post("/events/{$event->id}/book", ['qty' => [0 => 3]])
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

    public function test_pass_page_is_owner_only(): void
    {
        $event = $this->event();
        $owner = $this->user();

        $this->actingAs($owner)->post("/events/{$event->id}/book", ['qty' => [0 => 1]]);
        $booking = Booking::query()->firstOrFail();

        $stranger = User::create([
            'name' => 'Someone Else', 'email' => 'other@example.test',
            'password' => bcrypt('secret'), 'role' => 'user', 'status' => 'active',
        ]);

        $this->actingAs($stranger)
            ->get("/bookings/{$booking->id}/pass")
            ->assertNotFound();
    }
}
