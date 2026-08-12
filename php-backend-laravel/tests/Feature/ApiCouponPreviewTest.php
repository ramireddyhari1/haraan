<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Coupon;
use App\Models\Event;
use App\Models\User;
use App\Support\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * POST /api/bookings/validate-coupon — the quote behind the app checkout's Apply button.
 *
 * The number it returns is shown to the buyer as the discount they're getting, so it has
 * to be the number the booking actually applies. Both used to drift: a percentage coupon
 * quoted without a cart returned its raw `discount`, and the per-customer cap was never
 * checked here at all.
 */
class ApiCouponPreviewTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email = 'app-buyer@example.test'): User
    {
        return User::create([
            'name' => 'App Buyer', 'email' => $email,
            'password' => bcrypt('secret'), 'role' => 'user', 'status' => 'active',
        ]);
    }

    private function token(User $user): string
    {
        return JwtService::issue(['sub' => $user->id], (string) config('app.jwt_secret', env('JWT_SECRET', 'change_me')));
    }

    private function event(array $overrides = []): Event
    {
        $partner = User::firstOrCreate(
            ['email' => 'host@example.test'],
            ['name' => 'Host', 'password' => bcrypt('secret'), 'role' => 'PARTNER', 'status' => 'active'],
        );

        return Event::create(array_merge([
            'partner_id' => $partner->id, 'title' => 'Coupon Show', 'category' => 'Music',
            'location' => 'Arena', 'venue' => 'Arena, Hyderabad', 'date' => now()->addDays(5),
            'time' => '19:00', 'price' => 500, 'total_slots' => 50, 'available_slots' => 50,
            'images' => [], 'status' => 'published',
        ], $overrides));
    }

    private function preview(User $user, array $payload)
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token($user))
            ->postJson('/api/bookings/validate-coupon', $payload);
    }

    /** A percentage coupon must be priced against the cart, not quoted as its raw number. */
    public function test_percentage_coupon_is_priced_against_the_cart(): void
    {
        $event = $this->event();
        $user  = $this->user();

        Coupon::create(['event_id' => $event->id, 'code' => 'HALF', 'type' => 'percent', 'discount' => 50, 'active' => true]);

        $this->preview($user, ['code' => 'HALF', 'eventId' => $event->id, 'subtotal' => 1000])
            ->assertOk()
            ->assertJson(['valid' => true, 'code' => 'HALF', 'discount' => 500]);
    }

    /** A percentage coupon's cap survives the preview. */
    public function test_percentage_coupon_respects_its_cap(): void
    {
        $event = $this->event();
        $user  = $this->user();

        Coupon::create([
            'event_id' => $event->id, 'code' => 'CAPPED', 'type' => 'percent',
            'discount' => 50, 'max_discount' => 200, 'active' => true,
        ]);

        $this->preview($user, ['code' => 'CAPPED', 'eventId' => $event->id, 'subtotal' => 1000])
            ->assertOk()
            ->assertJson(['valid' => true, 'discount' => 200]);
    }

    /** A code the buyer has already spent must not preview a discount they can't have. */
    public function test_per_customer_limit_is_enforced_in_the_preview(): void
    {
        $event = $this->event();
        $user  = $this->user();

        Coupon::create([
            'event_id' => $event->id, 'code' => 'ONCE', 'type' => 'fixed',
            'discount' => 100, 'per_customer_limit' => 1, 'active' => true,
        ]);

        $this->preview($user, ['code' => 'ONCE', 'eventId' => $event->id, 'subtotal' => 1000])
            ->assertOk()
            ->assertJson(['valid' => true, 'discount' => 100]);

        // Their one allowed use, recorded the way a real order records it.
        Booking::create([
            'quantity' => 1, 'total_amount' => 1000, 'status' => 'CONFIRMED', 'coupon_code' => 'ONCE',
            'discount' => 100, 'user_id' => $user->id, 'event_id' => $event->id,
        ]);

        $this->preview($user, ['code' => 'ONCE', 'eventId' => $event->id, 'subtotal' => 1000])
            ->assertOk()
            ->assertJson(['valid' => false, 'message' => 'You’ve already used this code.']);

        // …and only for that buyer.
        $this->preview($this->user('someone-else@example.test'), ['code' => 'ONCE', 'eventId' => $event->id, 'subtotal' => 1000])
            ->assertOk()
            ->assertJson(['valid' => true, 'discount' => 100]);
    }

    public function test_minimum_order_and_wrong_event_are_rejected(): void
    {
        $event = $this->event();
        $other = $this->event(['title' => 'Other Show']);
        $user  = $this->user();

        Coupon::create(['event_id' => $event->id, 'code' => 'BIGCART', 'type' => 'fixed', 'discount' => 100, 'min_order' => 800, 'active' => true]);

        $this->preview($user, ['code' => 'BIGCART', 'eventId' => $event->id, 'subtotal' => 500])
            ->assertOk()
            ->assertJson(['valid' => false, 'message' => 'Applies on orders over ₹800.']);

        $this->preview($user, ['code' => 'BIGCART', 'eventId' => $other->id, 'subtotal' => 1000])
            ->assertOk()
            ->assertJson(['valid' => false, 'message' => 'This code isn’t valid for this event.']);

        $this->preview($user, ['code' => 'GHOST', 'eventId' => $event->id, 'subtotal' => 1000])
            ->assertOk()
            ->assertJson(['valid' => false, 'message' => 'This code isn’t valid.']);
    }

    /** The app's quote enforces the ticket minimum too, and reports it in the same words. */
    public function test_minimum_tickets_is_enforced_in_the_app_preview(): void
    {
        $event = $this->event(['price' => 699]);
        $user  = $this->user();

        Coupon::create([
            'event_id' => $event->id, 'code' => 'PAIR100', 'type' => 'fixed',
            'discount' => 100, 'min_tickets' => 2, 'active' => true,
        ]);

        $this->preview($user, ['code' => 'PAIR100', 'eventId' => $event->id, 'subtotal' => 699, 'tickets' => 1])
            ->assertOk()
            ->assertJson(['valid' => false, 'message' => 'Applies on 2 tickets or more.']);

        $this->preview($user, ['code' => 'PAIR100', 'eventId' => $event->id, 'subtotal' => 1398, 'tickets' => 2])
            ->assertOk()
            ->assertJson(['valid' => true, 'discount' => 100]);

        // An older app build sends no ticket count — quote it rather than refuse on a
        // number nobody supplied; createOrder still counts tickets when it charges.
        $this->preview($user, ['code' => 'PAIR100', 'eventId' => $event->id, 'subtotal' => 699])
            ->assertOk()
            ->assertJson(['valid' => true]);
    }

    public function test_preview_requires_a_token(): void
    {
        $this->postJson('/api/bookings/validate-coupon', ['code' => 'HALF'])->assertUnauthorized();
    }
}
