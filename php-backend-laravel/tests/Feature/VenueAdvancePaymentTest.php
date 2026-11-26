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

    public function test_partner_sees_the_advance_online_and_collects_the_balance_in_cash(): void
    {
        $this->book(['payMode' => 'advance'])->assertCreated();
        $this->confirm();
        $booking = Booking::query()->latest('id')->firstOrFail();

        $this->withHeader('Authorization', 'Bearer '.JwtService::issueForUser(
            $this->owner,
            (string) config('app.jwt_secret', env('JWT_SECRET', 'change_me')),
        ));

        $row = collect($this->getJson('/api/partner/bookings?date='.today()->toDateString())
            ->assertOk()->json('data'))->firstWhere('id', $booking->id);
        $this->assertEqualsWithDelta(1200.0, $row['amount'], 0.01);
        $this->assertEqualsWithDelta(240.0, $row['amount_paid'], 0.01);
        $this->assertEqualsWithDelta(960.0, $row['pay_at_venue'], 0.01);
        $this->assertEqualsWithDelta(240.0, $row['paid_split']['online'], 0.01);

        $this->getJson('/api/partner/today')->assertOk()
            ->assertJsonPath('data.money.online', 240)
            ->assertJsonPath('data.money.at_venue_due', 960)
            ->assertJsonPath('data.money.due', 960);

        // The player turns up and pays the rest in cash.
        $this->postJson("/api/partner/bookings/{$booking->id}/collect", ['method' => 'cash'])
            ->assertOk()->assertJsonPath('paid', true);

        $booking->refresh();
        $this->assertSame('paid', strtolower((string) $booking->payment_status));
        $this->assertEqualsWithDelta(0.0, $booking->balanceDue(), 0.01);

        // Two ways, not one: ₹240 online and ₹960 cash — never ₹1,200 "cash".
        $row = collect($this->getJson('/api/partner/bookings?date='.today()->toDateString())
            ->assertOk()->json('data'))->firstWhere('id', $booking->id);
        $this->assertSame('cash', $row['payment_method']);
        $this->assertEqualsWithDelta(240.0, $row['paid_split']['online'], 0.01);
        $this->assertEqualsWithDelta(960.0, $row['paid_split']['cash'], 0.01);
        // And as two payments in the feed, each with its own way and time.
        $this->assertSame(['online', 'cash'], array_column($row['payments'], 'method'));
        $this->assertEqualsWithDelta(240.0, $row['payments'][0]['amount'], 0.01);
        $this->assertEqualsWithDelta(960.0, $row['payments'][1]['amount'], 0.01);
        $this->assertNotNull($row['payments'][1]['at']);

        $this->getJson('/api/partner/today')->assertOk()
            ->assertJsonPath('data.money.online', 240)
            ->assertJsonPath('data.money.cash', 960)
            ->assertJsonPath('data.money.due', 0);

        // "Through Haraan" is what Razorpay took — the ₹240 advance — not the ₹1,200 booking.
        $this->getJson('/api/partner/insights')->assertOk()
            ->assertJsonPath('data.haraan.all_time.count', 1)
            ->assertJsonPath('data.haraan.all_time.amount', 240)
            ->assertJsonPath('data.haraan.all_time.at_venue', 960)
            ->assertJsonPath('data.haraan.all_time.booked', 1200);

        // A walk-in paid ₹500 cash at the desk never reached Haraan: it changes nothing owed.
        $walkIn = Booking::forceCreate([
            'booking_type' => 'venue', 'venue_id' => $this->venue->id, 'user_id' => $this->owner->id,
            'channel' => 'offline', 'status' => 'CONFIRMED', 'payment_status' => 'unpaid',
            'slot_date' => today()->toDateString(), 'start_time' => '06:00', 'end_time' => '07:00',
            'quantity' => 1, 'total_amount' => 500, 'amount_paid' => 0, 'guest_name' => 'Cash Walkin',
        ]);
        app(\App\Services\BookingLedger::class)->collect($walkIn, 500, 'cash', $this->owner);
        $this->assertEqualsWithDelta(0.0, $walkIn->refresh()->partnerPayable(), 0.01);

        // Settlement reads like Razorpay: ₹240 paid online − ₹120 commission = ₹120 owed.
        $this->getJson('/api/partner/payouts')->assertOk()
            ->assertJsonPath('balance.breakdown.paid_online', 240)
            ->assertJsonPath('balance.breakdown.commission', 120)
            ->assertJsonPath('balance.breakdown.net', 120)
            ->assertJsonPath('balance.collected', 120);

        // Cash at the counter is never paid out: the payout is still the advance's share.
        $this->assertEqualsWithDelta(120.0, app(PartnerSettlement::class)->collected($this->owner->id), 0.01);
    }

    public function test_partner_sees_slot_money_only_never_the_fees_or_tax(): void
    {
        \App\Support\PlatformRules::save(['fees.venue_tax_type' => 'percent', 'fees.venue_tax_value' => 18]);
        $this->venue->update(['convenience_fee_type' => 'flat', 'convenience_fee_value' => 30]);

        // Razorpay takes ₹240 advance + ₹30 fee + ₹216 tax (18% of ₹1,200) = ₹486.
        $this->book(['payMode' => 'advance'])->assertCreated();
        Http::assertSent(fn ($r) => str_contains($r->url(), '/v1/orders') && (int) $r->data()['amount'] === 48600);
        $this->confirm();
        $booking = Booking::query()->latest('id')->firstOrFail();
        $this->assertEqualsWithDelta(1200.0, $booking->slotValue(), 0.01);

        $this->withHeader('Authorization', 'Bearer '.JwtService::issueForUser(
            $this->owner,
            (string) config('app.jwt_secret', env('JWT_SECRET', 'change_me')),
        ));

        // The partner sees the ₹1,200 slot and the ₹240 slot advance — never the ₹30 fee or tax.
        $row = collect($this->getJson('/api/partner/bookings?date='.today()->toDateString())
            ->assertOk()->json('data'))->firstWhere('id', $booking->id);
        $this->assertEqualsWithDelta(1200.0, $row['amount'], 0.01);
        $this->assertEqualsWithDelta(240.0, $row['amount_paid'], 0.01);
        $this->assertEqualsWithDelta(240.0, $row['paid_split']['online'], 0.01);
        $this->assertEqualsWithDelta(240.0, $row['payments'][0]['amount'], 0.01);
        $this->assertEqualsWithDelta(960.0, $row['amount'] - $row['amount_paid'], 0.01);

        $grid = $this->getJson("/api/partner/venues/{$this->venue->id}/day?date=".today()->toDateString())->assertOk();
        $this->assertStringContainsString('"amount":1200', $grid->getContent());

        $this->getJson('/api/partner/today')->assertOk()
            ->assertJsonPath('data.money.expected', 1200)
            ->assertJsonPath('data.money.online', 240)
            ->assertJsonPath('data.money.due', 960);

        // Settlement: slot money online ₹240 − 10% commission on ₹1,200 (₹120) = ₹120.
        $this->getJson('/api/partner/payouts')->assertOk()
            ->assertJsonPath('balance.breakdown.paid_online', 240)
            ->assertJsonPath('balance.breakdown.tax', 0)
            ->assertJsonPath('balance.breakdown.net', 120);
        $this->getJson('/api/partner/insights')->assertOk()
            ->assertJsonPath('data.haraan.all_time.amount', 240);

        // The balance in cash settles it: ₹960.
        $this->postJson("/api/partner/bookings/{$booking->id}/collect", ['method' => 'cash'])->assertOk();
        $booking->refresh();
        $this->assertSame('paid', strtolower((string) $booking->payment_status));
        $this->assertEqualsWithDelta(960.0, (float) $booking->payments()->where('method', 'cash')->sum('amount'), 0.01);
        $this->assertEqualsWithDelta(1200.0, $booking->partnerPaid(), 0.01);
    }

    /**
     * Classic Sports Arena, 25 Nov: an online advance booking never paid online, then settled
     * in full by UPI at the desk. The desk took Haraan's fee, the UPI pile read ₹25 more than
     * "collected", and payouts owed the ₹150 advance Haraan never received.
     */
    public function test_an_online_hold_never_paid_online_and_settled_at_the_desk(): void
    {
        $this->venue->update(['convenience_fee_type' => 'flat', 'convenience_fee_value' => 30]);
        $this->book(['payMode' => 'advance'])->assertCreated();
        $booking = Booking::query()->latest('id')->firstOrFail();
        $this->assertEqualsWithDelta(30.0, (float) $booking->convenience_fee, 0.01);
        // No confirm: the player never paid the Razorpay order.

        $this->withHeader('Authorization', 'Bearer '.JwtService::issueForUser(
            $this->owner,
            (string) config('app.jwt_secret', env('JWT_SECRET', 'change_me')),
        ));

        $this->postJson("/api/partner/bookings/{$booking->id}/collect", ['method' => 'upi'])
            ->assertOk()->assertJsonPath('paid', true);

        // The desk takes the ₹1,200 slot — never Haraan's ₹30 online fee.
        $booking->refresh();
        $this->assertSame('paid', strtolower((string) $booking->payment_status));
        $this->assertEqualsWithDelta(1200.0, (float) $booking->payments()->sum('amount'), 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $booking->convenience_fee, 0.01);
        $this->assertEqualsWithDelta(1200.0, $booking->slotValue(), 0.01);

        $this->getJson('/api/partner/today')->assertOk()
            ->assertJsonPath('data.money.collected', 1200)
            ->assertJsonPath('data.money.upi', 1200)
            ->assertJsonPath('data.money.online', 0);

        // Nothing came through Razorpay, so Haraan owes nothing for it.
        $this->assertEqualsWithDelta(0.0, $booking->partnerPayable(), 0.01);
        $this->assertEqualsWithDelta(0.0, app(PartnerSettlement::class)->collected($this->owner->id), 0.01);
        $this->assertEqualsWithDelta(0.0, PayoutForm::partnerShare($booking), 0.01);
    }

    /** Rows already settled the old way (fee taken at the desk) read consistently too. */
    public function test_a_desk_payment_that_included_the_online_fee_still_adds_up(): void
    {
        $b = Booking::forceCreate([
            'booking_type' => 'venue', 'venue_id' => $this->venue->id, 'user_id' => $this->player->id,
            'channel' => 'online', 'status' => 'CONFIRMED', 'payment_status' => 'unpaid',
            'slot_date' => today()->toDateString(), 'start_time' => '19:00', 'end_time' => '20:00',
            'quantity' => 1, 'total_amount' => 525, 'convenience_fee' => 25, 'pay_at_venue' => 350,
            'amount_paid' => 0,
        ]);
        app(\App\Services\BookingLedger::class)->collect($b, 525, 'upi', $this->owner);
        $b->refresh();

        $this->assertEqualsWithDelta(500.0, $b->partnerPaid(), 0.01);
        $this->assertEqualsWithDelta(0.0, $b->partnerPayable(), 0.01);
        $this->assertEqualsWithDelta(0.0, app(PartnerSettlement::class)->collected($this->owner->id), 0.01);

        $this->withHeader('Authorization', 'Bearer '.JwtService::issueForUser(
            $this->owner,
            (string) config('app.jwt_secret', env('JWT_SECRET', 'change_me')),
        ));
        $this->getJson('/api/partner/today')->assertOk()
            ->assertJsonPath('data.money.collected', 500)
            ->assertJsonPath('data.money.upi', 500);
    }

    public function test_changing_the_commission_reprices_bookings_not_yet_paid_out(): void
    {
        // Booked at 10%: ₹120 commission on the ₹1,200 court.
        $this->book(['payMode' => 'full'])->assertCreated();
        $this->confirm();
        $settledEarlier = Booking::query()->latest('id')->firstOrFail();
        $this->assertEqualsWithDelta(120.0, (float) $settledEarlier->host_deduction, 0.01);

        // Haraan settles with the partner: that booking is inside the batch now.
        \App\Models\PayoutBatch::create([
            'partner_id' => $this->owner->id, 'gross_amount' => 1200, 'amount' => 1080,
            'commission_amount' => 120, 'status' => 'paid',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-08-25 11:00:00'));
        Http::fake(['api.razorpay.com/*' => Http::response(['id' => 'order_ADV1', 'amount' => 24000, 'currency' => 'INR'], 200)]);
        $this->book(['payMode' => 'advance', 'date' => today()->addDay()->toDateString()])->assertCreated();
        $this->confirm();
        $unsettled = Booking::query()->latest('id')->firstOrFail();
        $this->assertEqualsWithDelta(120.0, (float) $unsettled->host_deduction, 0.01);

        // Admin sets the venue to 0% in /control.
        $this->venue->refresh()->update(['commission_percent' => 0]);

        $this->assertEqualsWithDelta(0.0, (float) $unsettled->refresh()->host_deduction, 0.01);
        // Already paid out at 10% — left exactly as it was.
        $this->assertEqualsWithDelta(120.0, (float) $settledEarlier->refresh()->host_deduction, 0.01);

        // And back to 5%: ₹60 on the unsettled ₹1,200 court.
        $this->venue->refresh()->update(['commission_percent' => 5]);
        $this->assertEqualsWithDelta(60.0, (float) $unsettled->refresh()->host_deduction, 0.01);
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
