<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueSlot;
use App\Support\BusinessClock;
use App\Support\JwtService;
use App\Support\PlatformRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The walk-in sheet's UPI QR: the desk shows a Razorpay QR for the exact amount, the
 * booking stays unpaid until Razorpay reports the capture, and a timer that runs out,
 * a fresh QR, or cash taken instead can never collect the same rupees twice.
 */
final class DeskUpiQrTest extends TestCase
{
    use RefreshDatabase;

    private User $partner;

    private Venue $venue;

    private VenueSlot $slot;

    private string $token;

    /** Whether Razorpay reports the QR as paid, and for which booking it was minted. */
    private bool $qrPaid = false;

    private string $qrBooking = '';

    private bool $qrEnabled = true;

    /** @var list<string> */
    private array $closed = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.razorpay.key' => 'rzp_test_key', 'services.razorpay.secret' => 'rzp_test_secret']);

        Http::fake(function (HttpRequest $request) {
            $url = $request->url();

            if (str_ends_with($url, '/v1/payments/qr_codes') && $request->method() === 'POST') {
                if (! $this->qrEnabled) {
                    return Http::response(['error' => ['description' => 'QR codes not enabled']], 400);
                }
                $this->qrBooking = (string) ($request->data()['notes']['booking_id'] ?? '');

                return Http::response([
                    'id' => 'qr_Desk1', 'status' => 'active',
                    'image_content' => 'upi://pay?pa=rpy.qrdesk1@icici&am=500.00',
                    'image_url' => 'https://rzp.io/i/qrimg', 'close_by' => time() + 1200,
                ], 200);
            }

            if (str_ends_with($url, '/close')) {
                $this->closed[] = $url;

                return Http::response(['status' => 'closed'], 200);
            }

            if (str_contains($url, '/v1/payments/qr_codes/qr_Desk1/payments')) {
                return Http::response(['items' => $this->qrPaid
                    ? [['id' => 'pay_Qr77', 'status' => 'captured', 'amount' => 50000]]
                    : []], 200);
            }

            if (str_contains($url, '/v1/payments/qr_codes/qr_Desk1')) {
                return Http::response([
                    'id' => 'qr_Desk1', 'status' => 'active', 'payment_amount' => 50000,
                    'notes' => ['booking_id' => $this->qrBooking],
                ], 200);
            }

            if (str_ends_with($url, '/v1/payment_links') && $request->method() === 'POST') {
                return Http::response(['id' => 'plink_Fallback', 'short_url' => 'https://rzp.io/i/fallback'], 200);
            }

            return Http::response(['messages' => [['id' => 'wamid.x']]], 200);
        });

        $this->partner = User::factory()->create(['role' => 'partner', 'partner_type' => 'venue', 'status' => 'active']);
        $this->venue = Venue::create([
            'name' => 'Qr Arena', 'location' => 'Madhapur', 'price' => 500,
            'is_active' => true, 'is_bookable' => true, 'partner_id' => $this->partner->id,
        ]);
        $this->slot = VenueSlot::create(['venue_id' => $this->venue->id, 'time' => '6:00 PM', 'price' => 500, 'capacity' => 1]);

        $secret = config('app.jwt_secret') ?: env('JWT_SECRET', 'change_me');
        $this->token = JwtService::issueForUser($this->partner, $secret);
    }

    private function api(string $path, array $body = [])
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->token)->postJson('/api/partner'.$path, $body);
    }

    private function walkIn(string $method = 'upi_qr')
    {
        return $this->api("/venues/{$this->venue->id}/bookings", [
            'slotId' => $this->slot->id,
            'date' => BusinessClock::todayDate()->addDay()->toDateString(),
            'guestName' => 'Ravi', 'guestPhone' => '9876543210',
            'paymentMethod' => $method,
        ]);
    }

    public function test_upi_qr_returns_a_scannable_qr_and_leaves_the_booking_unpaid(): void
    {
        $res = $this->walkIn()->assertCreated();

        $res->assertJsonPath('payment.kind', 'upi_qr')
            ->assertJsonPath('payment.id', 'qr_Desk1')
            ->assertJsonPath('payment.qr', 'upi://pay?pa=rpy.qrdesk1@icici&am=500.00')
            ->assertJsonPath('payment.expires_in', 300)
            ->assertJsonPath('booking.payment_status', 'unpaid');

        $this->assertSame(0, BookingPayment::query()->count());
    }

    public function test_a_paid_qr_settles_once_however_often_the_desk_polls(): void
    {
        $id = $this->walkIn()->json('booking.id');
        $this->qrPaid = true;

        $this->api("/bookings/{$id}/payment-status", ['qrId' => 'qr_Desk1'])->assertOk()->assertJsonPath('paid', true);
        $this->api("/bookings/{$id}/payment-status", ['qrId' => 'qr_Desk1'])->assertOk()->assertJsonPath('paid', true);

        $this->assertSame(1, BookingPayment::query()->where('booking_id', $id)->count());
        $this->assertSame('paid', strtolower((string) Booking::find($id)->payment_status));
    }

    public function test_a_qr_minted_for_another_booking_cannot_settle_this_one(): void
    {
        $first = $this->walkIn()->json('booking.id');
        $this->qrPaid = true;
        $this->slot = VenueSlot::create(['venue_id' => $this->venue->id, 'time' => '7:00 PM', 'price' => 500, 'capacity' => 1]);
        $second = $this->walkIn('link')->assertCreated()->json('booking.id');

        $this->api("/bookings/{$second}/payment-status", ['qrId' => 'qr_Desk1'])->assertStatus(422);

        $this->assertSame(0, BookingPayment::query()->where('booking_id', $second)->count());
        $this->assertNotSame($first, $second);
    }

    public function test_when_the_timer_runs_out_the_qr_is_closed(): void
    {
        $id = $this->walkIn()->json('booking.id');

        $this->api("/bookings/{$id}/payment-status", ['qrId' => 'qr_Desk1', 'close' => true])
            ->assertOk()->assertJsonPath('paid', false);

        $this->assertCount(1, $this->closed);
    }

    public function test_cash_instead_closes_the_qr_and_records_the_balance_once(): void
    {
        $id = $this->walkIn()->json('booking.id');

        $this->api("/bookings/{$id}/collect", ['method' => 'cash', 'closeKind' => 'upi_qr', 'closeId' => 'qr_Desk1'])
            ->assertOk()->assertJsonPath('via', 'cash')->assertJsonPath('booking.payment_status', 'paid');

        $this->assertCount(1, $this->closed);
        $this->assertSame(['cash'], BookingPayment::query()->where('booking_id', $id)->pluck('method')->all());
    }

    public function test_cash_after_the_customer_already_scanned_records_nothing_extra(): void
    {
        $id = $this->walkIn()->json('booking.id');
        $this->qrPaid = true;

        $this->api("/bookings/{$id}/collect", ['method' => 'cash', 'closeKind' => 'upi_qr', 'closeId' => 'qr_Desk1'])
            ->assertOk()->assertJsonPath('via', 'online');

        $this->assertSame(['online'], BookingPayment::query()->where('booking_id', $id)->pluck('method')->all());
    }

    public function test_a_fresh_qr_closes_the_old_one_first(): void
    {
        $id = $this->walkIn()->json('booking.id');

        $this->api("/bookings/{$id}/payment-request", ['kind' => 'upi_qr', 'replaceKind' => 'upi_qr', 'replaceId' => 'qr_Desk1'])
            ->assertOk()->assertJsonPath('paid', false)->assertJsonPath('payment.kind', 'upi_qr');

        $this->assertCount(1, $this->closed);
    }

    public function test_without_razorpay_qr_the_desk_opens_razorpays_page_never_a_link_qr(): void
    {
        $this->qrEnabled = false;

        $this->walkIn()->assertCreated()
            ->assertJsonPath('payment.kind', 'link')
            ->assertJsonPath('payment.present', 'page')
            ->assertJsonPath('payment.url', 'https://rzp.io/i/fallback')
            ->assertJsonPath('payment.qr', null)
            ->assertJsonPath('booking.payment_status', 'unpaid');

        // Paid on the desk's screen: no SMS of the same link on top.
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/v1/payment_links')
            && $r->data()['notify']['sms'] === false);
    }

    public function test_a_link_the_desk_chooses_is_texted_to_the_customer(): void
    {
        $id = $this->walkIn()->json('booking.id');

        $this->api("/bookings/{$id}/payment-request", ['kind' => 'link'])
            ->assertOk()->assertJsonPath('payment.present', 'share')->assertJsonPath('payment.url', 'https://rzp.io/i/fallback');

        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/v1/payment_links')
            && $r->data()['notify']['sms'] === true);
    }

    public function test_another_partner_cannot_touch_the_booking(): void
    {
        $id = $this->walkIn()->json('booking.id');
        $other = User::factory()->create(['role' => 'partner', 'partner_type' => 'venue', 'status' => 'active']);
        $secret = config('app.jwt_secret') ?: env('JWT_SECRET', 'change_me');

        $this->withHeader('Authorization', 'Bearer '.JwtService::issueForUser($other, $secret))
            ->postJson("/api/partner/bookings/{$id}/collect", ['method' => 'cash'])
            ->assertNotFound();
    }

    // ── Offline or online, nothing in between ──────────────────────────────────────

    public function test_there_is_no_pay_later_walk_in(): void
    {
        $this->walkIn('later')->assertStatus(422);
        $this->api("/venues/{$this->venue->id}/bookings", [
            'slotId' => $this->slot->id,
            'date' => BusinessClock::todayDate()->addDay()->toDateString(),
            'guestName' => 'Ravi',
        ])->assertStatus(422);

        $this->assertSame(0, Booking::query()->count());
    }

    public function test_an_online_walk_in_is_a_hold_until_it_is_paid(): void
    {
        $id = $this->walkIn()->assertCreated()->json('booking.id');
        $b = Booking::find($id);

        $this->assertSame('PENDING', strtoupper((string) $b->status));
        $this->assertNotNull($b->reserved_until);
        $this->assertTrue($b->reserved_until->isFuture());

        // While the customer pays, nobody else can take the court.
        $this->walkIn('cash')->assertStatus(409);

        $this->qrPaid = true;
        $this->api("/bookings/{$id}/payment-status", ['qrId' => 'qr_Desk1'])->assertOk()->assertJsonPath('paid', true);

        $b->refresh();
        $this->assertSame('CONFIRMED', strtoupper((string) $b->status));
        $this->assertNull($b->reserved_until);
        $this->assertSame('paid', strtolower((string) $b->payment_status));
        $this->assertSame(1, BookingPayment::query()->where('booking_id', $id)->count());
    }

    public function test_an_unpaid_hold_frees_the_court_when_it_lapses(): void
    {
        $id = $this->walkIn()->assertCreated()->json('booking.id');

        $this->travel(PlatformRules::int('bookings.desk_qr_minutes') + 3)->minutes();

        // Nothing was paid, so the court is for sale again — to the next walk-in.
        $next = $this->walkIn('cash')->assertCreated()->json('booking.id');
        $this->assertNotSame($id, $next);
        $this->assertSame('CONFIRMED', strtoupper((string) Booking::find($next)->status));
    }

    public function test_cash_at_the_counter_confirms_the_hold(): void
    {
        $id = $this->walkIn()->json('booking.id');

        $this->api("/bookings/{$id}/collect", ['method' => 'cash', 'closeKind' => 'upi_qr', 'closeId' => 'qr_Desk1'])
            ->assertOk()->assertJsonPath('paid', true)->assertJsonPath('via', 'cash');

        $b = Booking::find($id);
        $this->assertSame('CONFIRMED', strtoupper((string) $b->status));
        $this->assertSame('paid', strtolower((string) $b->payment_status));
    }

    public function test_a_hold_waiting_on_a_qr_is_not_money_owed(): void
    {
        $this->walkIn()->assertCreated();

        $this->withHeader('Authorization', 'Bearer '.$this->token)->getJson('/api/partner/today')
            ->assertOk()
            ->assertJsonPath('data.chase.count', 0);
    }
}
