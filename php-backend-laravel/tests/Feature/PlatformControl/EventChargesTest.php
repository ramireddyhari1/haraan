<?php

declare(strict_types=1);

namespace Tests\Feature\PlatformControl;

use App\Models\Booking;
use App\Models\Coupon;
use App\Models\Event;
use App\Models\User;
use App\Services\PartnerSettlement;
use App\Support\JwtService;
use App\Support\PlatformRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The platform fee, gateway fee and tax an admin sets in /control used to be stored and never
 * charged. These pin that they are now charged — on the booking, on the Razorpay order and on
 * the quote the app shows — and that host-paid fees come off the partner's payout.
 */
class EventChargesTest extends TestCase
{
    use RefreshDatabase;

    private User $host;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.razorpay.key' => 'rzp_test_key', 'services.razorpay.secret' => 'rzp_test_secret']);
        Http::fake([
            'api.razorpay.com/v1/orders' => fn (HttpRequest $r) => Http::response([
                'id' => 'order_test_'.$r['amount'], 'amount' => $r['amount'], 'currency' => 'INR',
            ]),
        ]);

        $this->host = User::create([
            'name' => 'Host', 'email' => 'host@haraan.test', 'password' => bcrypt('secret'),
            'role' => 'PARTNER', 'status' => 'active',
        ]);
    }

    private function buyer(): User
    {
        return User::create([
            'name' => 'Asha', 'email' => 'asha'.random_int(1, 99999).'@haraan.test',
            'password' => bcrypt('secret'), 'role' => 'user', 'status' => 'active',
        ]);
    }

    private function event(array $overrides = []): Event
    {
        return Event::create(array_merge([
            'partner_id' => $this->host->id, 'title' => 'Sunburn', 'category' => 'Music',
            'location' => 'Arena', 'venue' => 'Arena, Hyderabad', 'date' => now()->addDays(5),
            'time' => '19:00', 'price' => 1000, 'total_slots' => 50, 'available_slots' => 50,
            'images' => [], 'status' => 'published',
        ], $overrides));
    }

    private function api(User $user)
    {
        return $this->withHeader('Authorization', 'Bearer '.JwtService::issue(['sub' => $user->id], (string) config('app.jwt_secret', env('JWT_SECRET', 'change_me'))));
    }

    public function test_customer_paid_platform_fee_and_tax_are_charged_and_sent_to_razorpay(): void
    {
        $event = $this->event([
            'platform_fee_type' => 'percent', 'platform_fee_value' => 5, 'platform_fee_payer' => 'customer',
            'tax_type' => 'percent', 'tax_value' => 18,
        ]);

        $res = $this->api($this->buyer())->postJson('/api/bookings', [
            'eventId' => $event->id, 'quantity' => 2, 'pay' => true,
        ])->assertCreated();

        // 2 × ₹1000 = 2000; platform 5% = 100; GST 18% of 2000 = 360 → 2460.
        $res->assertJsonPath('data.platformFee', '100')
            ->assertJsonPath('data.taxAmount', '360')
            ->assertJsonPath('data.totalAmount', '2460')
            ->assertJsonPath('payment.amount', 246000);

        $row = Booking::query()->where('event_id', $event->id)->first();
        $this->assertSame(100.0, $row->platform_fee);
        $this->assertSame(360.0, $row->tax_amount);
        $this->assertSame(0.0, $row->host_deduction);
        $this->assertSame(2460.0, Booking::orderGrandTotal([$row]));
    }

    public function test_tax_is_on_the_subtotal_after_discount(): void
    {
        $event = $this->event(['tax_type' => 'percent', 'tax_value' => 10, 'platform_fee_type' => 'none', 'gateway_fee_type' => 'none']);

        $charges = $event->orderCharges(1000.0, 200.0);

        $this->assertSame(80.0, $charges['tax']);       // 10% of (1000 − 200)
        $this->assertSame(880.0, $charges['total']);    // 1000 − 200 + 80
    }

    public function test_host_paid_platform_fee_is_not_charged_and_comes_off_the_payout(): void
    {
        $event = $this->event([
            'platform_fee_type' => 'percent', 'platform_fee_value' => 10, 'platform_fee_payer' => 'host',
            'tax_type' => 'none', 'gateway_fee_type' => 'none',
        ]);

        $this->api($this->buyer())->postJson('/api/bookings', ['eventId' => $event->id, 'quantity' => 1, 'pay' => true])
            ->assertCreated()
            ->assertJsonPath('data.totalAmount', '1000')
            ->assertJsonPath('payment.amount', 100000);

        $row = Booking::query()->where('event_id', $event->id)->first();
        $this->assertSame(100.0, $row->host_deduction);

        $row->forceFill(['status' => 'CONFIRMED'])->save();
        $this->assertSame(900.0, app(PartnerSettlement::class)->collected($this->host->id));
    }

    public function test_platform_default_follows_the_rules_at_order_time(): void
    {
        PlatformRules::save([
            'fees.event_platform_fee_type' => 'flat', 'fees.event_platform_fee_value' => 25,
            'fees.event_tax_type' => 'percent', 'fees.event_tax_value' => 18, 'fees.event_tax_label' => 'GST',
        ]);

        // A new event inherits by default (column default 'inherit').
        $event = $this->event();
        $this->assertSame('inherit', $event->fresh()->platform_fee_type);

        $charges = $event->fresh()->orderCharges(1000.0);
        $this->assertSame(25.0, $charges['platform_fee']);
        $this->assertSame(180.0, $charges['tax']);
        $this->assertSame(1205.0, $charges['total']);
        $this->assertSame(['Platform fee', 'GST'], array_column($charges['lines'], 'label'));
    }

    public function test_quote_endpoint_returns_the_server_bill_including_coupon(): void
    {
        $event = $this->event(['platform_fee_type' => 'flat', 'platform_fee_value' => 20, 'tax_type' => 'percent', 'tax_value' => 10]);
        Coupon::create(['event_id' => $event->id, 'code' => 'OFF100', 'type' => 'flat', 'discount' => 100, 'active' => true]);

        $this->api($this->buyer())->postJson('/api/bookings/quote', [
            'eventId' => $event->id, 'quantity' => 1, 'couponCode' => 'OFF100',
        ])
            ->assertOk()
            ->assertJsonPath('subtotal', 1000)
            ->assertJsonPath('discount', 100)
            ->assertJsonPath('coupon.applied', true)
            // 1000 + 20 − 100 + 10% × 900 = 1010
            ->assertJsonPath('total', 1010);
    }

    public function test_legacy_client_without_pay_flag_cannot_get_a_paid_ticket_for_free(): void
    {
        $event = $this->event();

        $this->api($this->buyer())->postJson('/api/bookings', ['eventId' => $event->id, 'quantity' => 2])
            ->assertStatus(426)
            ->assertJsonPath('error', 'update_required');

        // Nothing confirmed, seats handed back.
        $this->assertFalse(Booking::query()->where('event_id', $event->id)->whereRaw('lower(status) = ?', ['confirmed'])->exists());
        $this->assertSame(50, $event->fresh()->available_slots);
    }

    public function test_legacy_client_still_books_a_free_event(): void
    {
        $event = $this->event(['price' => 0]);

        $this->api($this->buyer())->postJson('/api/bookings', ['eventId' => $event->id, 'quantity' => 1])
            ->assertCreated()
            ->assertJsonPath('data.status', 'CONFIRMED');
    }

    public function test_platform_ticket_cap_is_enforced(): void
    {
        PlatformRules::save(['bookings.max_tickets_per_order' => 3]);
        $event = $this->event();

        $this->api($this->buyer())->postJson('/api/bookings', ['eventId' => $event->id, 'quantity' => 4, 'pay' => true])
            ->assertStatus(409);
    }
}
