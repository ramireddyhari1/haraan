<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueCourt;
use App\Models\VenueSlot;
use App\Support\PlatformRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The website court checkout (/gamehub/{id}/book) must price a booking exactly the way the
 * app does, and the ledger must record exactly what Razorpay charged.
 *
 * It used to add a hardcoded 18% "GST" and ₹50 platform fee, then write the whole order's
 * total onto the first row while the other rows kept their slot rates — so confirming a
 * two-slot ₹2,410 order credited the venue ₹3,410.
 */
class WebVenueCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test_secret';

    private User $buyer;
    private Venue $venue;
    private VenueCourt $court;

    /** @var list<int> */
    private array $ordered = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.razorpay.key' => 'rzp_test_key', 'services.razorpay.secret' => self::SECRET]);

        Http::fake(function (HttpRequest $request) {
            $amount = (int) $request->data()['amount'];
            $this->ordered[] = $amount;

            return Http::response(['id' => 'order_web_' . count($this->ordered), 'amount' => $amount, 'currency' => 'INR']);
        });

        $owner = User::create([
            'name' => 'Rahul Krishnan', 'email' => 'owner@haraan.test', 'password' => Hash::make('secret123'),
            'role' => 'PARTNER', 'partner_type' => 'venue', 'status' => 'active',
        ]);

        $this->buyer = User::create([
            'name' => 'Asha Menon', 'email' => 'asha@haraan.test', 'password' => Hash::make('secret123'),
            'status' => 'active',
        ]);

        $this->venue = Venue::create([
            'name' => 'Sportz Arena', 'location' => 'Gachibowli', 'city' => 'Hyderabad', 'price' => 1000,
            'images' => ['venues/arena.jpg'], 'status' => 'published', 'published_at' => now(),
            'is_active' => true, 'is_bookable' => true, 'partner_id' => $owner->id,
        ]);

        $this->court = VenueCourt::create([
            'venue_id' => $this->venue->id, 'name' => 'Court 1', 'price' => 1000, 'is_active' => true,
        ]);

        foreach (['6:00 AM', '7:00 AM', '8:00 AM'] as $time) {
            VenueSlot::create(['venue_id' => $this->venue->id, 'day' => 'Daily', 'time' => $time, 'price' => 1000, 'is_available' => true]);
        }
    }

    /** @param  list<string>  $times */
    private function reserve(array $times, array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->buyer)->postJson('/gamehub/' . $this->venue->id . '/book', array_merge([
            'date' => today()->addDay()->toDateString(),
            'court_id' => $this->court->id,
            'slots' => array_map(fn (string $t) => ['time' => $t, 'price' => 1], $times),
        ], $extra));
    }

    private function confirm(string $orderId): void
    {
        $paymentId = 'pay_' . $orderId;

        $this->actingAs($this->buyer)->postJson('/gamehub/' . $this->venue->id . '/confirm', [
            'razorpay_order_id' => $orderId,
            'razorpay_payment_id' => $paymentId,
            'razorpay_signature' => hash_hmac('sha256', $orderId . '|' . $paymentId, self::SECRET),
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_multi_slot_order_charges_no_invented_tax_and_the_ledger_matches_razorpay(): void
    {
        $this->venue->update(['convenience_fee_type' => 'percent', 'convenience_fee_value' => 10]);

        $res = $this->reserve(['6:00 AM - 7:00 AM', '7:00 AM - 8:00 AM'])->assertOk();

        // ₹2,000 of court time + the venue's own 10% fee. No 18% GST, no ₹50.
        $this->assertSame([220000], $this->ordered);
        $res->assertJsonPath('breakdown.subtotal', 2000)
            ->assertJsonPath('breakdown.convenienceFee', 200)
            ->assertJsonPath('total', 2200);

        $rows = Booking::query()->where('razorpay_order_id', 'order_web_1')->get();
        $this->assertCount(2, $rows);
        foreach ($rows as $row) {
            $this->assertEqualsWithDelta(1100.0, (float) $row->total_amount, 0.001);
            $this->assertEqualsWithDelta(100.0, (float) $row->convenience_fee, 0.001);
            $this->assertEqualsWithDelta(0.0, (float) $row->tax_amount, 0.001);
            $this->assertEqualsWithDelta(0.0, (float) $row->platform_fee, 0.001);
        }
        $this->assertEqualsWithDelta(2200.0, $rows->sum(fn (Booking $b) => $b->amountCharged()), 0.001);

        $this->confirm('order_web_1');

        // The venue is credited what the buyer paid — not a rupee more.
        $this->assertEqualsWithDelta(2200.0, (float) BookingPayment::query()->whereIn('booking_id', $rows->pluck('id'))->sum('amount'), 0.001);
        foreach ($rows->fresh() as $row) {
            $this->assertSame('CONFIRMED', $row->status);
            $this->assertSame('paid', $row->payment_status);
        }
    }

    public function test_a_flat_fee_is_charged_once_per_order_and_shares_add_up_to_the_paisa(): void
    {
        $this->venue->update(['convenience_fee_type' => 'flat', 'convenience_fee_value' => 25]);
        PlatformRules::save(['fees.venue_commission_percent' => 5]);

        $this->reserve(['6:00 AM - 7:00 AM', '7:00 AM - 8:00 AM', '8:00 AM - 9:00 AM'])->assertOk();

        $this->assertSame([302500], $this->ordered);

        $rows = Booking::query()->where('razorpay_order_id', 'order_web_1')->orderBy('id')->get();
        $this->assertEqualsWithDelta(25.0, (float) $rows->sum('convenience_fee'), 0.001);
        $this->assertEqualsWithDelta(3025.0, $rows->sum(fn (Booking $b) => $b->amountCharged()), 0.001);
        // Pulse commission on each slot's value, same rule as the app checkout.
        $this->assertEqualsWithDelta(150.0, (float) $rows->sum('host_deduction'), 0.001);

        $this->confirm('order_web_1');

        $this->assertEqualsWithDelta(3025.0, (float) BookingPayment::query()->whereIn('booking_id', $rows->pluck('id'))->sum('amount'), 0.001);
    }

    public function test_pulse_tax_from_control_is_charged_on_the_web_and_kept_out_of_the_venue_share(): void
    {
        PlatformRules::save(['fees.venue_tax_type' => 'percent', 'fees.venue_tax_value' => 18, 'fees.venue_tax_label' => 'GST']);
        $this->venue->update(['convenience_fee_type' => 'percent', 'convenience_fee_value' => 10]);

        // 2000 court + 200 fee + 18% of 2000 (360) = 2560.
        $res = $this->reserve(['6:00 AM - 7:00 AM', '7:00 AM - 8:00 AM'])->assertOk()
            ->assertJsonPath('breakdown.tax', 360)
            ->assertJsonPath('breakdown.taxLabel', 'GST')
            ->assertJsonPath('total', 2560);
        $this->assertSame([256000], $this->ordered);

        $rows = Booking::query()->where('razorpay_order_id', 'order_web_1')->get();
        $this->assertEqualsWithDelta(2200.0, (float) $rows->sum('total_amount'), 0.001);
        $this->assertEqualsWithDelta(360.0, (float) $rows->sum('tax_amount'), 0.001);

        $this->confirm('order_web_1');

        $this->assertEqualsWithDelta(2560.0, (float) BookingPayment::query()->whereIn('booking_id', $rows->pluck('id'))->sum('amount'), 0.001);
        foreach ($rows->fresh() as $row) {
            $this->assertSame('paid', $row->payment_status);
        }
    }

    public function test_the_price_sent_by_the_browser_is_ignored(): void
    {
        // reserve() sends price => 1 for every slot; the court rate is what gets charged.
        $this->reserve(['6:00 AM - 7:00 AM'])->assertOk()->assertJsonPath('total', 1000);

        $this->assertSame([100000], $this->ordered);
    }

    public function test_paused_payments_refuse_a_paid_web_booking_before_holding_the_court(): void
    {
        PlatformRules::save(['ops.payments_disabled' => true]);

        $this->reserve(['6:00 AM - 7:00 AM'])->assertStatus(409);

        $this->assertSame([], $this->ordered);
        $this->assertSame(0, Booking::query()->count());
    }

    /** @param  list<string>  $times */
    private function quote(array $times, array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->buyer)->postJson('/gamehub/' . $this->venue->id . '/book/quote', array_merge([
            'date' => today()->addDay()->toDateString(),
            'court_id' => $this->court->id,
            'slots' => $times,
        ], $extra));
    }

    public function test_the_review_quote_matches_what_checkout_charges_and_holds_nothing(): void
    {
        $this->venue->update(['convenience_fee_type' => 'percent', 'convenience_fee_value' => 10]);

        $this->quote(['6:00 AM - 7:00 AM', '7:00 AM - 8:00 AM'])
            ->assertOk()
            ->assertJsonPath('subtotal', 2000)
            ->assertJsonPath('fee', 200)
            ->assertJsonPath('discount', 0)
            ->assertJsonPath('total', 2200)
            ->assertJsonPath('lines.0.rate', 1000)
            ->assertJsonPath('coupon.applied', false);

        // A quote is not a reservation: no rows, no Razorpay order.
        $this->assertSame(0, Booking::query()->count());
        $this->assertSame([], $this->ordered);

        // And Proceed charges exactly the quoted total.
        $this->reserve(['6:00 AM - 7:00 AM', '7:00 AM - 8:00 AM'])->assertOk();
        $this->assertSame([220000], $this->ordered);
    }

    public function test_the_review_quote_applies_a_venue_coupon_and_names_a_bad_one(): void
    {
        \App\Models\Coupon::create([
            'scope' => 'venue', 'venue_id' => $this->venue->id, 'code' => 'COURT20',
            'type' => 'percent', 'discount' => 20, 'active' => true,
        ]);

        $this->quote(['6:00 AM - 7:00 AM'], ['couponCode' => 'court20'])
            ->assertOk()
            ->assertJsonPath('coupon.applied', true)
            ->assertJsonPath('coupon.code', 'COURT20')
            ->assertJsonPath('discount', 200)
            ->assertJsonPath('total', 800);

        $this->quote(['6:00 AM - 7:00 AM'], ['couponCode' => 'NOPE'])
            ->assertOk()
            ->assertJsonPath('coupon.applied', false)
            ->assertJsonPath('total', 1000)
            ->assertJsonPath('coupon.message', 'This code isn’t valid.');
    }

    public function test_the_review_quote_needs_a_signed_in_buyer(): void
    {
        $this->postJson('/gamehub/' . $this->venue->id . '/book/quote', [
            'date' => today()->addDay()->toDateString(), 'slots' => ['6:00 AM - 7:00 AM'],
        ])->assertStatus(401);
    }

    public function test_passed_slots_and_dates_are_judged_on_the_venues_clock_not_utc(): void
    {
        // 10:00 AM IST is 04:30 UTC: on the server's clock the 6 AM slot was still ahead.
        \Illuminate\Support\Carbon::setTestNow('2026-09-28 04:30:00');
        $this->reserve(['6:00 AM - 7:00 AM'], ['date' => '2026-09-28'])->assertStatus(422);

        // 01:30 AM IST on the 28th is still the 27th in UTC - the 27th is gone at the venue.
        \Illuminate\Support\Carbon::setTestNow('2026-09-27 20:00:00');
        $this->reserve(['8:00 AM - 9:00 AM'], ['date' => '2026-09-27'])->assertStatus(422);

        $this->assertSame([], $this->ordered);
        $this->assertSame(0, Booking::query()->count());

        // And the venue's own morning is still sellable at 01:30 AM IST.
        $this->reserve(['6:00 AM - 7:00 AM'], ['date' => '2026-09-28'])->assertOk();
        \Illuminate\Support\Carbon::setTestNow();
    }

    public function test_the_venue_page_opens_on_the_venues_today(): void
    {
        // 01:30 AM IST: the strip must start on the 28th, not UTC's 27th.
        \Illuminate\Support\Carbon::setTestNow('2026-09-27 20:00:00');
        $this->get('/gamehub/' . $this->venue->id)
            ->assertOk()
            ->assertSee('Monday, 28 Sep')
            ->assertSee("let selectedDate = '2026-09-28'", false);
        \Illuminate\Support\Carbon::setTestNow();
    }

    public function test_a_time_the_venue_never_set_up_is_refused(): void
    {
        // The website used to draw its own 6 AM–11 PM grid, and checkout sold whatever it sent.
        $this->reserve(['9:00 PM - 10:00 PM'])->assertStatus(422);

        $this->assertSame([], $this->ordered);
        $this->assertSame(0, Booking::query()->count());
    }

    public function test_a_range_longer_than_one_slot_is_refused(): void
    {
        // One slot's start time stretched to three hours would be priced as one hour.
        $this->reserve(['6:00 AM - 9:00 AM'])->assertStatus(422);

        $this->assertSame([], $this->ordered);
    }

    public function test_a_slot_the_venue_switched_off_is_refused(): void
    {
        VenueSlot::query()->where('venue_id', $this->venue->id)->where('time', '7:00 AM')->update(['is_available' => false]);

        $this->reserve(['7:00 AM - 8:00 AM'])->assertStatus(422);
        $this->assertSame([], $this->ordered);
    }

    public function test_a_weekday_row_adds_to_the_everyday_rows_for_that_day(): void
    {
        $date = today()->addDay();
        VenueSlot::create(['venue_id' => $this->venue->id, 'day' => $date->format('l'), 'time' => '5:00 PM', 'price' => 1000, 'is_available' => true]);

        // The every-day hours keep selling on a day that also has its own rows.
        $this->reserve(['6:00 AM - 7:00 AM'])->assertOk();
        $this->reserve(['5:00 PM - 6:00 PM'])->assertOk();
    }

    public function test_a_weekday_row_at_the_same_time_replaces_the_everyday_row(): void
    {
        $date = today()->addDay();
        VenueSlot::query()->where('venue_id', $this->venue->id)->where('time', '6:00 AM')->update(['is_available' => true]);
        VenueSlot::create(['venue_id' => $this->venue->id, 'day' => $date->format('l'), 'time' => '6:00 AM', 'price' => 1000, 'is_available' => false]);

        // That day's own 6 AM row is closed, and the every-day 6 AM doesn't slip back in.
        $this->reserve(['6:00 AM - 7:00 AM'])->assertStatus(422);
    }

    public function test_the_venue_page_offers_only_the_admins_slots(): void
    {
        $page = $this->get('/gamehub/' . $this->venue->id)->assertOk();

        $page->assertSee('06:00 AM - 07:00 AM')
            ->assertSee('08:00 AM - 09:00 AM')
            ->assertDontSee('10:00 PM - 11:00 PM')
            ->assertDontSee('isSlotBooked', false);
    }

    public function test_the_venue_page_shows_the_real_fee_and_no_invented_gst(): void
    {
        $this->venue->update(['convenience_fee_type' => 'flat', 'convenience_fee_value' => 30]);

        $this->get('/gamehub/' . $this->venue->id)
            ->assertOk()
            ->assertSee('Convenience fee')
            ->assertSee("const venueFeeType = \"flat\"", false)
            ->assertDontSee('GST (18%)')
            ->assertDontSee('PLATFORM FEE');
    }
}
