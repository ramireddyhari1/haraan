<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Payouts\Schemas\PayoutForm;
use App\Models\Booking;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueCourt;
use App\Models\VenueSlot;
use App\Services\PartnerSettlement;
use App\Support\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Advance payment: Haraan sets a % per venue in /control; the customer chooses to pay only
 * that share of the court price online (plus fees/tax) and the rest at the venue, or pay in
 * full. The balance must reach the partner desk as balance due, and must not be paid out.
 */
class VenueAdvancePaymentTest extends TestCase
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
            'id' => 'order_ADV1', 'amount' => 24000, 'currency' => 'INR',
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
            'city' => 'Hyderabad', 'images' => ['venues/test.jpg'], 'status' => 'published',
            'advance_percent' => 20, 'commission_percent' => 10,
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

    private function book(array $overrides = []): TestResponse
    {
        $this->withHeader('Authorization', 'Bearer '.JwtService::issueForUser(
            $this->player,
            (string) config('app.jwt_secret', env('JWT_SECRET', 'change_me')),
        ));

        return $this->postJson('/api/bookings/venue', array_merge([
            'venueId' => $this->venue->id,
            'slotId' => $this->slot->id,
            'courtId' => $this->court->id,
            'date' => today()->toDateString(),
            'duration' => 1,
        ], $overrides));
    }

    private function confirm(): void
    {
        $sig = hash_hmac('sha256', 'order_ADV1|pay_ADV1', 'rzp_test_secret');

        $this->postJson('/api/bookings/confirm', [
            'razorpayOrderId' => 'order_ADV1',
            'razorpayPaymentId' => 'pay_ADV1',
            'razorpaySignature' => $sig,
        ])->assertOk();
    }

    public function test_venue_math(): void
    {
        $this->assertTrue($this->venue->offersAdvance());
        $this->assertEqualsWithDelta(960.0, $this->venue->payAtVenueFor(1200), 0.001);
        // The advance rounds UP to the rupee: 20% of 999 = 199.8 -> 200 online, 799 at venue.
        $this->assertEqualsWithDelta(799.0, $this->venue->payAtVenueFor(999), 0.001);

        $this->venue->advance_percent = null;
        $this->assertFalse($this->venue->offersAdvance());
        $this->assertEqualsWithDelta(0.0, $this->venue->payAtVenueFor(1200), 0.001);
    }

    public function test_paying_the_advance_charges_only_the_advance_and_leaves_the_rest_due(): void
    {
        $this->book(['payMode' => 'advance'])->assertCreated()
            ->assertJsonPath('payment.required', true);

        // 20% of ₹1,200 = ₹240 online.
        Http::assertSent(fn ($r) => str_contains($r->url(), '/v1/orders') && (int) $r->data()['amount'] === 24000);

        $booking = Booking::query()->latest('id')->firstOrFail();
        $this->assertEqualsWithDelta(1200.0, (float) $booking->total_amount, 0.01);
        $this->assertEqualsWithDelta(960.0, (float) $booking->pay_at_venue, 0.01);
        $this->assertEqualsWithDelta(240.0, $booking->onlineDue(), 0.01);

        $this->confirm();

        $booking->refresh();
        $this->assertSame('CONFIRMED', $booking->status);
        $this->assertEqualsWithDelta(240.0, (float) $booking->amount_paid, 0.01);
        // The partner desk sees the rest as balance due.
        $this->assertEqualsWithDelta(960.0, $booking->balanceDue(), 0.01);
        $this->assertSame('partial', strtolower((string) $booking->payment_status));

        // Payout: only the advance passed through Haraan, less 10% commission on ₹1,200.
        $this->assertEqualsWithDelta(120.0, PayoutForm::partnerShare($booking), 0.01);
        $this->assertEqualsWithDelta(120.0, app(PartnerSettlement::class)->collected($this->owner->id), 0.01);
    }

    public function test_paying_in_full_is_unchanged(): void
    {
        $this->book(['payMode' => 'full'])->assertCreated();

        Http::assertSent(fn ($r) => str_contains($r->url(), '/v1/orders') && (int) $r->data()['amount'] === 120000);

        $booking = Booking::query()->latest('id')->firstOrFail();
        $this->assertEqualsWithDelta(0.0, (float) $booking->pay_at_venue, 0.01);
        $this->assertEqualsWithDelta(1200.0, $booking->onlineDue(), 0.01);
    }

    public function test_old_apps_without_pay_mode_pay_in_full(): void
    {
        $this->book()->assertCreated();

        $booking = Booking::query()->latest('id')->firstOrFail();
        $this->assertEqualsWithDelta(0.0, (float) $booking->pay_at_venue, 0.01);
    }

    public function test_advance_is_ignored_when_the_venue_offers_none(): void
    {
        $this->venue->update(['advance_percent' => null]);

        $this->book(['payMode' => 'advance'])->assertCreated();

        $booking = Booking::query()->latest('id')->firstOrFail();
        $this->assertEqualsWithDelta(0.0, (float) $booking->pay_at_venue, 0.01);
    }

    public function test_venue_api_publishes_the_advance_percent(): void
    {
        $this->getJson('/api/venues/'.$this->venue->id)
            ->assertOk()
            ->assertJsonPath('data.advance_percent', 20);
    }
}
