<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Coupon;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueCourt;
use App\Models\VenueSlot;
use App\Support\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The turf-booking handshake, end to end: a player books a court in the app
 * (Pulse -> venue detail -> pay), and the partner standing at that turf has to
 * see it. Every assertion here is one half of that sentence.
 */
class VenueBookingUserToPartnerTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $player;
    private Venue $venue;
    private VenueCourt $court;
    private VenueSlot $slot;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-25 10:00:00'));

        config([
            'services.razorpay.key' => 'rzp_test_key',
            'services.razorpay.secret' => 'rzp_test_secret',
        ]);

        Http::fake(['api.razorpay.com/*' => Http::response([
            'id' => 'order_TEST123', 'amount' => 120000, 'currency' => 'INR',
        ], 200)]);

        $this->owner = User::create([
            'name' => 'Turf Owner', 'email' => 'turf@haraan.test',
            'password' => Hash::make('secret123'), 'role' => 'PARTNER',
            'partner_type' => 'venue', 'status' => 'active',
        ]);

        $this->player = User::create([
            'name' => 'Arjun Player', 'email' => 'player@haraan.test',
            'password' => Hash::make('secret123'), 'role' => 'USER', 'status' => 'active',
        ]);

        $this->venue = Venue::create([
            'name' => 'Kick Off Turf', 'location' => 'Gachibowli', 'price' => 1000,
            'is_active' => true, 'is_bookable' => true, 'partner_id' => $this->owner->id,
        ]);

        $this->court = VenueCourt::create([
            'venue_id' => $this->venue->id, 'name' => 'Turf A',
            'price' => 1200, 'is_active' => true,
        ]);

        $this->slot = VenueSlot::create([
            'venue_id' => $this->venue->id, 'day' => 'everyday', 'time' => '7:00 PM',
            'is_available' => true, 'sort_order' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function as(User $user): self
    {
        $this->withHeader('Authorization', 'Bearer '.JwtService::issueForUser(
            $user,
            (string) config('app.jwt_secret', env('JWT_SECRET', 'change_me')),
        ));

        return $this;
    }

    private function date(): string
    {
        return today()->toDateString();
    }

    /** Book as the player, the same call the app's venue sheet makes. */
    private function book(array $overrides = []): TestResponse
    {
        return $this->as($this->player)->postJson('/api/bookings/venue', array_merge([
            'venueId' => $this->venue->id,
            'slotId' => $this->slot->id,
            'courtId' => $this->court->id,
            'date' => $this->date(),
            'duration' => 1,
        ], $overrides));
    }

    // ------------------------------------------------------------- user app

    public function test_a_player_can_reserve_a_court_and_is_sent_to_checkout(): void
    {
        $res = $this->book()->assertCreated();

        $res->assertJsonPath('payment.required', true);
        $res->assertJsonPath('payment.orderId', 'order_TEST123');

        $booking = Booking::query()->latest('id')->first();
        $this->assertNotNull($booking);
        $this->assertSame('PENDING', $booking->status);
        $this->assertSame('venue', $booking->booking_type);
        $this->assertSame($this->player->id, $booking->user_id);
        $this->assertSame($this->court->id, (int) $booking->venue_court_id);
        $this->assertNotNull($booking->reserved_until);
        // Court price wins over the venue base price.
        $this->assertEqualsWithDelta(1200.0, (float) $booking->total_amount, 0.01);
    }

    public function test_a_verified_payment_confirms_the_booking(): void
    {
        $this->book()->assertCreated();

        $sig = hash_hmac('sha256', 'order_TEST123|pay_TEST1', 'rzp_test_secret');

        $this->as($this->player)->postJson('/api/bookings/confirm', [
            'razorpayOrderId' => 'order_TEST123',
            'razorpayPaymentId' => 'pay_TEST1',
            'razorpaySignature' => $sig,
        ])->assertOk();

        $booking = Booking::query()->latest('id')->first();
        $this->assertSame('CONFIRMED', $booking->status);
    }

    public function test_a_held_court_cannot_be_double_sold(): void
    {
        $this->book()->assertCreated();

        $other = User::create([
            'name' => 'Second Player', 'email' => 'p2@haraan.test',
            'password' => Hash::make('secret123'), 'role' => 'USER', 'status' => 'active',
        ]);

        $this->as($other)->postJson('/api/bookings/venue', [
            'venueId' => $this->venue->id,
            'slotId' => $this->slot->id,
            'courtId' => $this->court->id,
            'date' => $this->date(),
            'duration' => 1,
        ])->assertStatus(409);
    }

    /**
     * The order summary quotes a fee and a discount; the Razorpay order has to be
     * built from the same two numbers. The app now sends the code it discounted by —
     * before that it kept the code to itself and the server priced at full rate, so
     * the sheet said one total and checkout asked for another.
     */
    public function test_the_quoted_total_is_the_total_charged(): void
    {
        $this->venue->update(['convenience_fee_type' => 'percent', 'convenience_fee_value' => 5]);

        Coupon::create([
            'venue_id' => $this->venue->id, 'scope' => 'venue', 'code' => 'TURF200',
            'type' => 'fixed', 'discount' => 200, 'active' => true,
        ]);

        // What the summary shows: 1200 subtotal + 60 fee - 200 off = 1060.
        $quote = $this->as($this->player)->postJson('/api/bookings/validate-coupon', [
            'code' => 'TURF200', 'venueId' => $this->venue->id, 'subtotal' => 1200,
        ])->assertOk();

        $quote->assertJsonPath('valid', true);
        $this->assertEqualsWithDelta(60.0, (float) $quote->json('fee'), 0.01);
        $this->assertEqualsWithDelta(200.0, (float) $quote->json('discount'), 0.01);

        $this->book(['couponCode' => 'TURF200'])->assertCreated()
            ->assertJsonPath('payment.required', true);

        $booking = Booking::query()->latest('id')->first();
        $this->assertEqualsWithDelta(1060.0, (float) $booking->total_amount, 0.01);
        $this->assertSame('TURF200', $booking->coupon_code);
    }

    /** A venue that charges a fee has to say so before checkout, not after. */
    public function test_the_venue_api_publishes_the_booking_fee(): void
    {
        $this->venue->update(['convenience_fee_type' => 'percent', 'convenience_fee_value' => 5]);

        $data = $this->getJson("/api/venues/{$this->venue->id}")->assertOk()->json('data');

        $this->assertSame('percent', $data['convenience_fee_type']);
        $this->assertEqualsWithDelta(5.0, (float) $data['convenience_fee_value'], 0.01);
    }

    /**
     * The bookings already sitting in production: confirmed, paid, and carrying a
     * Razorpay payment id, but with nothing on the ledger — so the partner is still
     * being told to chase them. The backfill closes that.
     */
    public function test_the_backfill_settles_bookings_confirmed_before_the_ledger(): void
    {
        $this->book()->assertCreated();
        $booking = Booking::query()->latest('id')->first();

        // Exactly the shape the old confirm path left behind.
        $booking->forceFill([
            'status' => 'CONFIRMED',
            'reserved_until' => null,
            'razorpay_payment_id' => 'pay_OLD1',
            'amount_paid' => 0,
            'payment_status' => 'unpaid',
        ])->save();
        $booking->payments()->delete();

        $this->artisan('bookings:backfill-online-payments')->assertExitCode(0);
        $this->assertSame('unpaid', $booking->refresh()->payment_status, 'a dry run must change nothing');

        $this->artisan('bookings:backfill-online-payments --apply')->assertExitCode(0);

        $this->assertSame('paid', $booking->refresh()->payment_status);
        $this->assertEqualsWithDelta(1200.0, (float) $booking->amount_paid, 0.01);

        // Re-running finds nothing left to do rather than collecting twice.
        $this->artisan('bookings:backfill-online-payments --apply')->assertExitCode(0);
        $this->assertSame(1, $booking->payments()->count());
    }

    // ---------------------------------------------------------- partner app

    private function confirmedBooking(): Booking
    {
        $this->book()->assertCreated();
        $sig = hash_hmac('sha256', 'order_TEST123|pay_TEST1', 'rzp_test_secret');
        $this->as($this->player)->postJson('/api/bookings/confirm', [
            'razorpayOrderId' => 'order_TEST123',
            'razorpayPaymentId' => 'pay_TEST1',
            'razorpaySignature' => $sig,
        ])->assertOk();

        return Booking::query()->latest('id')->first();
    }

    public function test_the_partner_sees_the_app_booking_on_todays_sheet(): void
    {
        $this->confirmedBooking();

        $res = $this->as($this->owner)->getJson('/api/partner/today')->assertOk();

        $rows = $res->json('data.next');
        $this->assertNotEmpty($rows, 'today() returned no rows for an app booking: '.json_encode($res->json('data')));
        $this->assertSame('Arjun Player', $rows[0]['customer'] ?? null);
        $this->assertFalse($rows[0]['walk_in'] ?? true);
    }

    /**
     * The regression this whole file was written for: the player paid Razorpay in
     * the app, and the shift board still put them on the chase list for the full
     * amount, because online money never reached the ledger.
     */
    public function test_money_paid_in_the_app_reads_as_collected_at_the_desk(): void
    {
        $booking = $this->confirmedBooking();

        $this->assertSame('paid', $booking->payment_status);
        $this->assertEqualsWithDelta(1200.0, (float) $booking->amount_paid, 0.01);
        $this->assertSame(0.0, $booking->balanceDue());

        $data = $this->as($this->owner)->getJson('/api/partner/today')->assertOk()->json('data');

        $this->assertEqualsWithDelta(1200.0, (float) $data['money']['collected'], 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $data['money']['due'], 0.01);
        $this->assertSame(0, (int) $data['chase']['count']);
        $this->assertTrue($data['next'][0]['paid']);
    }

    /**
     * A discounted order paid in full is PAID, not partial.
     *
     * An event row keeps the fee and discount beside `total_amount` rather than inside
     * it, so settling against the subtotal made a ₹200-off order that cleared in full
     * read 'partial' — and put its customer on a chase list for a ₹200 nobody owed.
     */
    public function test_a_discounted_order_paid_in_full_reads_as_paid(): void
    {
        Coupon::create([
            'venue_id' => $this->venue->id, 'scope' => 'venue', 'code' => 'TURF200',
            'type' => 'fixed', 'discount' => 200, 'active' => true,
        ]);

        $this->book(['couponCode' => 'TURF200'])->assertCreated();

        $sig = hash_hmac('sha256', 'order_TEST123|pay_TEST1', 'rzp_test_secret');
        $this->as($this->player)->postJson('/api/bookings/confirm', [
            'razorpayOrderId' => 'order_TEST123',
            'razorpayPaymentId' => 'pay_TEST1',
            'razorpaySignature' => $sig,
        ])->assertOk();

        $booking = Booking::query()->latest('id')->first();

        $this->assertEqualsWithDelta(1000.0, (float) $booking->amount_paid, 0.01);
        $this->assertSame('paid', $booking->payment_status);
        $this->assertSame(0.0, $booking->balanceDue());
        $this->assertFalse($booking->hasBalanceDue());
    }

    /** One order, one ledger row — the webhook backstop must not bill it twice. */
    public function test_the_webhook_does_not_collect_the_same_rupees_twice(): void
    {
        $booking = $this->confirmedBooking();

        // Razorpay redelivers `payment.captured` for the order the client already
        // confirmed; the ledger row is keyed on the payment id.
        app(\App\Services\BookingService::class)
            ->confirmReservation([$booking->id], 'pay_TEST1');

        $this->assertSame(1, $booking->payments()->count());
        $this->assertEqualsWithDelta(1200.0, (float) $booking->refresh()->amount_paid, 0.01);
    }

    public function test_the_partner_sees_the_app_booking_in_the_sales_feed(): void
    {
        $this->confirmedBooking();

        $res = $this->as($this->owner)->getJson('/api/partner/bookings')->assertOk();

        $rows = $res->json('data');
        $this->assertNotEmpty($rows);
        $this->assertSame('Kick Off Turf', $rows[0]['venue'] ?? null);
    }

    public function test_the_day_grid_marks_the_cell_booked(): void
    {
        $this->confirmedBooking();

        $res = $this->as($this->owner)
            ->getJson("/api/partner/venues/{$this->venue->id}/day?date={$this->date()}")
            ->assertOk();

        $slots = $res->json('slots');
        $this->assertNotEmpty($slots, 'no slot rows on the day grid');
        $cell = $slots[0]['courts'][0] ?? null;
        $this->assertNotNull($cell, 'no court cell on the slot row');
        $this->assertTrue($cell['is_booked'], 'the grid does not show the app booking');
    }

    /**
     * A court-hour someone is paying for in the app is not for sale at the desk — the
     * conflict engine already says so. The grid has to say so too, or the desk taps an
     * Open cell and gets an error about a booking it was never shown.
     */
    public function test_the_day_grid_shows_a_live_hold_as_held(): void
    {
        $this->book()->assertCreated();   // PENDING, inside its hold window

        $cell = $this->as($this->owner)
            ->getJson("/api/partner/venues/{$this->venue->id}/day?date={$this->date()}")
            ->assertOk()
            ->json('slots.0.courts.0');

        $this->assertTrue($cell['is_held'], 'the grid draws a held court-hour as free');
        $this->assertFalse($cell['is_booked'], 'a hold is not a sale');
        $this->assertSame(0, (int) $cell['booked']);

        // And the desk is genuinely refused, which is what the amber cell is warning about.
        $this->as($this->owner)->postJson("/api/partner/venues/{$this->venue->id}/bookings", [
            'slotId' => $this->slot->id, 'courtId' => $this->court->id,
            'date' => $this->date(), 'duration' => 1, 'guestName' => 'Walk In',
        ])->assertStatus(409);
    }

    /** A lapsed hold is nobody's court — the cell goes back to Open. */
    public function test_an_expired_hold_stops_blocking_the_grid(): void
    {
        $this->book()->assertCreated();

        Carbon::setTestNow(now()->addMinutes(30));

        $cell = $this->as($this->owner)
            ->getJson("/api/partner/venues/{$this->venue->id}/day?date={$this->date()}")
            ->assertOk()
            ->json('slots.0.courts.0');

        $this->assertFalse($cell['is_held']);
        $this->assertFalse($cell['is_booked']);
    }

    public function test_a_desk_walk_in_blocks_the_same_court_in_the_app(): void
    {
        $this->as($this->owner)->postJson("/api/partner/venues/{$this->venue->id}/bookings", [
            'slotId' => $this->slot->id,
            'courtId' => $this->court->id,
            'date' => $this->date(),
            'duration' => 1,
            'guestName' => 'Walk In',
            'guestPhone' => '9000000000',
            'paymentMethod' => 'cash',
        ])->assertSuccessful();

        $this->book()->assertStatus(409);
    }

    public function test_a_partner_cancel_frees_the_court_for_the_app(): void
    {
        $booking = $this->confirmedBooking();

        $this->as($this->owner)->postJson("/api/partner/bookings/{$booking->id}/cancel")
            ->assertSuccessful();

        $this->book()->assertCreated();
    }
}
