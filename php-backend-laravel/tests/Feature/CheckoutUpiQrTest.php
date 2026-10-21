<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;
use App\Services\BookingService;
use App\Support\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Pay a held checkout order by Razorpay UPI QR — the app's way to show UPI on a phone
 * where Razorpay's checkout lists no UPI app.
 *
 * Pins: one QR per order (never two codes to pay twice), the QR's amount is the order's,
 * only a paid QR this server minted for THIS order confirms it, a refused QR leaves the
 * hold alone, and neither release nor the lapsed-hold sweep can drop a QR payment.
 */
class CheckoutUpiQrTest extends TestCase
{
    use RefreshDatabase;

    private bool $qrEnabled = true;

    private bool $qrPaid = false;

    private string $qrBooking = '';

    private int $qrAmount = 0;

    private int $qrCreates = 0;

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
                    return Http::response(['error' => ['description' => 'The requested URL was not found on the server.']], 400);
                }
                $this->qrCreates++;
                $this->qrBooking = (string) ($request->data()['notes']['booking_id'] ?? '');
                $this->qrAmount = (int) $request->data()['payment_amount'];

                return Http::response([
                    'id' => 'qr_Chk1', 'status' => 'active',
                    'image_content' => 'upi://pay?pa=rpy.qrchk1@icici&am=1000.00',
                    'image_url' => 'https://rzp.io/i/qrimg', 'close_by' => time() + 1200,
                ], 200);
            }

            if (str_ends_with($url, '/close')) {
                $this->closed[] = $url;

                return Http::response(['status' => 'closed'], 200);
            }

            if (str_contains($url, '/v1/payments/qr_codes/qr_Chk1/payments')) {
                return Http::response(['items' => $this->qrPaid
                    ? [['id' => 'pay_QrChk', 'status' => 'captured', 'amount' => $this->qrAmount]]
                    : []], 200);
            }

            if (str_contains($url, '/v1/payments/qr_codes/qr_Chk1')) {
                return Http::response([
                    'id' => 'qr_Chk1', 'status' => 'active', 'payment_amount' => $this->qrAmount,
                    'notes' => ['booking_id' => $this->qrBooking],
                ], 200);
            }

            // The Razorpay order itself: never paid in these tests.
            if (str_contains($url, '/v1/orders/')) {
                return Http::response(['items' => [], 'status' => 'attempted', 'amount_paid' => 0], 200);
            }

            return Http::response([], 200);
        });
    }

    private function buyer(string $email = 'qr-buyer@example.test'): User
    {
        return User::create([
            'name' => 'Qr Buyer', 'email' => $email,
            'password' => bcrypt('secret'), 'role' => 'user', 'status' => 'active',
        ]);
    }

    /** @return array{0: User, 1: Collection<int, Booking>} */
    private function heldOrder(string $orderId = 'order_Chk1'): array
    {
        $partner = User::firstOrCreate(
            ['email' => 'qr-host@example.test'],
            ['name' => 'Host', 'password' => bcrypt('secret'), 'role' => 'PARTNER', 'status' => 'active'],
        );
        $event = Event::create([
            'partner_id' => $partner->id, 'title' => 'Qr Show', 'category' => 'Music',
            'location' => 'Arena', 'venue' => 'Arena, Hyderabad', 'date' => now()->addDays(7),
            'time' => '19:00', 'price' => 500, 'total_slots' => 100, 'available_slots' => 100,
            'images' => [], 'status' => 'published',
        ]);
        $tier = TicketType::create([
            'event_id' => $event->id, 'name' => 'Gold', 'kind' => 'paid',
            'price' => 500, 'quota' => 10, 'sold' => 0, 'sort' => 1,
        ]);
        $buyer = $this->buyer();
        $order = app(BookingService::class)->createOrder(
            $buyer, $event->id, [['ticketTypeId' => $tier->id, 'quantity' => 2]], reserve: true,
        );
        app(BookingService::class)->attachOrderId($order, $orderId);

        return [$buyer, $order->fresh()];
    }

    private function as(User $user)
    {
        $secret = config('app.jwt_secret') ?: env('JWT_SECRET', 'change_me');

        return $this->withHeader('Authorization', 'Bearer '.JwtService::issueForUser($user, $secret));
    }

    public function test_a_held_order_gets_one_qr_for_its_own_total(): void
    {
        [$buyer, $order] = $this->heldOrder();

        $first = $this->as($buyer)->postJson('/api/bookings/upi-qr', ['razorpayOrderId' => 'order_Chk1'])
            ->assertOk()
            ->assertJsonPath('qrId', 'qr_Chk1')
            ->assertJsonPath('upi', 'upi://pay?pa=rpy.qrchk1@icici&am=1000.00')
            ->json();

        $this->assertSame((int) round(Booking::orderGrandTotal($order) * 100), $this->qrAmount);
        $this->assertSame((string) $order->first()->id, $this->qrBooking);
        $this->assertGreaterThan(0, $first['expiresIn']);

        // Asking again shows the same code — never a second one to pay twice.
        $this->as($buyer)->postJson('/api/bookings/upi-qr', ['razorpayOrderId' => 'order_Chk1'])
            ->assertOk()->assertJsonPath('qrId', 'qr_Chk1');
        $this->assertSame(1, $this->qrCreates);
    }

    public function test_paying_the_qr_confirms_the_hold(): void
    {
        [$buyer, $order] = $this->heldOrder();
        $this->as($buyer)->postJson('/api/bookings/upi-qr', ['razorpayOrderId' => 'order_Chk1'])->assertOk();

        $this->as($buyer)->postJson('/api/bookings/upi-qr/status', ['razorpayOrderId' => 'order_Chk1'])
            ->assertOk()->assertJsonPath('status', 'PENDING');
        $this->assertSame('PENDING', strtoupper((string) $order->first()->fresh()->status));

        $this->qrPaid = true;

        $this->as($buyer)->postJson('/api/bookings/upi-qr/status', ['razorpayOrderId' => 'order_Chk1'])
            ->assertOk()->assertJsonPath('status', 'CONFIRMED');

        $row = $order->first()->fresh();
        $this->assertSame('CONFIRMED', strtoupper((string) $row->status));
        $this->assertSame('pay_QrChk', $row->razorpay_payment_id);
    }

    public function test_a_paid_qr_minted_for_another_booking_does_not_confirm(): void
    {
        [$buyer, $order] = $this->heldOrder();
        $this->as($buyer)->postJson('/api/bookings/upi-qr', ['razorpayOrderId' => 'order_Chk1'])->assertOk();

        $this->qrBooking = '999999';
        $this->qrPaid = true;

        $this->as($buyer)->postJson('/api/bookings/upi-qr/status', ['razorpayOrderId' => 'order_Chk1'])
            ->assertOk()->assertJsonPath('status', 'PENDING');
        $this->assertSame('PENDING', strtoupper((string) $order->first()->fresh()->status));
    }

    public function test_a_refused_qr_says_so_and_leaves_the_hold(): void
    {
        $this->qrEnabled = false;
        [$buyer, $order] = $this->heldOrder();

        $this->as($buyer)->postJson('/api/bookings/upi-qr', ['razorpayOrderId' => 'order_Chk1'])
            ->assertStatus(503)
            ->assertJsonFragment(['error' => 'UPI QR isn’t available right now. Pay with Razorpay instead.']);

        $this->assertSame('PENDING', strtoupper((string) $order->first()->fresh()->status));
    }

    public function test_someone_elses_order_and_a_lapsed_hold_get_no_qr(): void
    {
        [, $order] = $this->heldOrder();

        $this->as($this->buyer('stranger@example.test'))
            ->postJson('/api/bookings/upi-qr', ['razorpayOrderId' => 'order_Chk1'])
            ->assertNotFound();

        Booking::query()->whereIn('id', $order->pluck('id'))->update(['reserved_until' => now()->subMinute()]);
        $buyer = User::where('email', 'qr-buyer@example.test')->first();

        $this->as($buyer)->postJson('/api/bookings/upi-qr', ['razorpayOrderId' => 'order_Chk1'])
            ->assertStatus(409);
        $this->assertSame(0, $this->qrCreates);
    }

    public function test_releasing_the_hold_closes_its_qr(): void
    {
        [$buyer, $order] = $this->heldOrder();
        $this->as($buyer)->postJson('/api/bookings/upi-qr', ['razorpayOrderId' => 'order_Chk1'])->assertOk();

        $this->as($buyer)->postJson('/api/bookings/release', ['razorpayOrderId' => 'order_Chk1'])->assertOk();

        $this->assertCount(1, array_filter($this->closed, fn ($u) => str_contains($u, 'qr_Chk1/close')));
        $this->assertNotSame('PENDING', strtoupper((string) $order->first()->fresh()->status));
    }

    public function test_the_sweep_confirms_a_lapsed_hold_paid_by_qr(): void
    {
        [$buyer, $order] = $this->heldOrder();
        $this->as($buyer)->postJson('/api/bookings/upi-qr', ['razorpayOrderId' => 'order_Chk1'])->assertOk();

        $this->qrPaid = true;
        Booking::query()->whereIn('id', $order->pluck('id'))->update(['reserved_until' => now()->subMinute()]);

        app(BookingService::class)->releaseAllExpired();

        $row = $order->first()->fresh();
        $this->assertSame('CONFIRMED', strtoupper((string) $row->status));
        $this->assertSame('pay_QrChk', $row->razorpay_payment_id);
        // Closed before it was checked, so nothing can land after the sweep looked.
        $this->assertNotEmpty($this->closed);
    }

    public function test_the_sweep_closes_an_unpaid_qr_and_frees_the_hold(): void
    {
        [$buyer, $order] = $this->heldOrder();
        $this->as($buyer)->postJson('/api/bookings/upi-qr', ['razorpayOrderId' => 'order_Chk1'])->assertOk();

        Booking::query()->whereIn('id', $order->pluck('id'))->update(['reserved_until' => now()->subMinute()]);

        app(BookingService::class)->releaseAllExpired();

        $this->assertSame('EXPIRED', strtoupper((string) $order->first()->fresh()->status));
        $this->assertNotEmpty($this->closed);
    }
}
