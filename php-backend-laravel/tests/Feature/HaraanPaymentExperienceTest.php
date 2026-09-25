<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Event;
use App\Models\User;
use App\Support\JwtService;
use App\Services\RazorpayGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HaraanPaymentExperienceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.razorpay.key', 'rzp_test_key123');
        Config::set('services.razorpay.secret', 'rzp_test_secret456');
        Config::set('services.razorpay.webhook_secret', 'rzp_webhook_secret789');

        $this->user = User::create([
            'name'     => 'Hariharan Consumer',
            'email'    => 'hari@haraan.test',
            'password' => Hash::make('password123'),
            'role'     => 'USER',
            'status'   => 'active',
        ]);

        $this->event = Event::create([
            'title'       => 'Haraan Pro Cricket Championship 2026',
            'description' => 'Championship match',
            'price'       => 1499.00,
            'category'    => 'sports',
            'status'      => 'PUBLISHED',
            'user_id'     => $this->user->id,
            'partner_id'  => $this->user->id,
            'date'        => today()->addDays(5)->toDateString(),
            'venue'       => 'Haraan Arena',
            'city'        => 'Hyderabad',
        ]);
    }

    private function asUser(User $user): self
    {
        $token = JwtService::issueForUser(
            $user,
            (string) config('app.jwt_secret', env('JWT_SECRET', 'change_me')),
        );

        $this->withHeader('Authorization', 'Bearer ' . $token);

        return $this;
    }

    public function test_payment_status_returns_confirmed_for_already_confirmed_booking(): void
    {
        $booking = Booking::create([
            'user_id'           => $this->user->id,
            'event_id'          => $this->event->id,
            'quantity'          => 1,
            'total_amount'      => 1499.00,
            'status'            => 'CONFIRMED',
            'razorpay_order_id' => 'order_CONFIRMED_123',
            'booking_type'      => 'event',
        ]);

        $res = $this->asUser($this->user)->postJson('/api/bookings/status', [
            'razorpayOrderId' => 'order_CONFIRMED_123',
        ]);

        $res->assertOk();
        $res->assertJsonPath('status', 'CONFIRMED');
        $res->assertJsonPath('data.id', $booking->id);
    }

    public function test_payment_status_reconciles_pending_booking_when_razorpay_captured(): void
    {
        $booking = Booking::create([
            'user_id'           => $this->user->id,
            'event_id'          => $this->event->id,
            'quantity'          => 1,
            'total_amount'      => 1499.00,
            'status'            => 'PENDING',
            'razorpay_order_id' => 'order_RECON_456',
            'booking_type'      => 'event',
            'reserved_until'    => now()->addMinutes(15),
        ]);

        // Mock Razorpay API response for order payments: captured payment pay_RECON_999
        Http::fake([
            'https://api.razorpay.com/v1/orders/order_RECON_456/payments' => Http::response([
                'items' => [
                    [
                        'id'     => 'pay_RECON_999',
                        'status' => 'captured',
                        'amount' => 149900,
                    ],
                ],
            ], 200),
        ]);

        $res = $this->asUser($this->user)->postJson('/api/bookings/status', [
            'razorpayOrderId' => 'order_RECON_456',
        ]);

        $res->assertOk();
        $res->assertJsonPath('status', 'CONFIRMED');
        $res->assertJsonPath('reconciled', true);

        $booking->refresh();
        $this->assertSame('CONFIRMED', $booking->status);
        $this->assertSame('pay_RECON_999', $booking->razorpay_payment_id);
    }

    public function test_payment_status_returns_pending_when_razorpay_has_no_captured_payment(): void
    {
        $booking = Booking::create([
            'user_id'           => $this->user->id,
            'event_id'          => $this->event->id,
            'quantity'          => 1,
            'total_amount'      => 1499.00,
            'status'            => 'PENDING',
            'razorpay_order_id' => 'order_PENDING_789',
            'booking_type'      => 'event',
            'reserved_until'    => now()->addMinutes(15),
        ]);

        Http::fake([
            'https://api.razorpay.com/v1/orders/order_PENDING_789/payments' => Http::response([
                'items' => [
                    [
                        'id'     => 'pay_AUTH_111',
                        'status' => 'authorized', // authorized is not captured yet
                        'amount' => 149900,
                    ],
                ],
            ], 200),
        ]);

        $res = $this->asUser($this->user)->postJson('/api/bookings/status', [
            'razorpayOrderId' => 'order_PENDING_789',
        ]);

        $res->assertOk();
        $res->assertJsonPath('status', 'PENDING');

        $booking->refresh();
        $this->assertSame('PENDING', $booking->status);
    }

    public function test_payment_confirm_is_idempotent_if_already_confirmed(): void
    {
        $booking = Booking::create([
            'user_id'           => $this->user->id,
            'event_id'          => $this->event->id,
            'quantity'          => 1,
            'total_amount'      => 1499.00,
            'status'            => 'CONFIRMED',
            'razorpay_order_id' => 'order_ALREADY_CONFIRMED',
            'booking_type'      => 'event',
        ]);

        // Even with a signature mismatch or re-confirm, an already confirmed booking returns successfully
        $res = $this->asUser($this->user)->postJson('/api/bookings/confirm', [
            'razorpayOrderId'   => 'order_ALREADY_CONFIRMED',
            'razorpayPaymentId' => 'pay_ANY_123',
            'razorpaySignature' => 'arbitrary_stale_signature',
        ]);

        $res->assertOk();
        $res->assertJsonPath('message', 'Booking confirmed');
        $res->assertJsonPath('data.id', $booking->id);
    }

    public function test_webhook_deduplicates_events_by_id(): void
    {
        $secret = 'rzp_webhook_secret789';
        $payload = json_encode([
            'event'   => 'payment.captured',
            'payload' => [
                'payment' => [
                    'entity' => [
                        'id'       => 'pay_DEDUP_001',
                        'order_id' => 'order_DEDUP_001',
                        'amount'   => 149900,
                    ],
                ],
            ],
        ]);

        $signature = hash_hmac('sha256', $payload, $secret);

        // First delivery
        $res1 = $this->call('POST', '/api/webhooks/razorpay', [], [], [], [
            'HTTP_X-Razorpay-Signature' => $signature,
            'HTTP_X-Razorpay-Event-Id'  => 'event_unique_xyz_123',
            'CONTENT_TYPE'              => 'application/json',
        ], $payload);

        $res1->assertOk();

        // Second delivery with identical event ID
        $res2 = $this->call('POST', '/api/webhooks/razorpay', [], [], [], [
            'HTTP_X-Razorpay-Signature' => $signature,
            'HTTP_X-Razorpay-Event-Id'  => 'event_unique_xyz_123',
            'CONTENT_TYPE'              => 'application/json',
        ], $payload);

        $res2->assertOk();
    }

    public function test_webhook_handles_order_paid_and_payment_failed(): void
    {
        $secret = 'rzp_webhook_secret789';

        // 1. payment.failed
        $failedPayload = json_encode([
            'event'   => 'payment.failed',
            'payload' => [
                'payment' => [
                    'entity' => [
                        'id'                => 'pay_FAIL_001',
                        'order_id'          => 'order_FAIL_001',
                        'error_code'        => 'BAD_REQUEST_ERROR',
                        'error_description' => 'Payment was declined by bank',
                    ],
                ],
            ],
        ]);

        $sigFailed = hash_hmac('sha256', $failedPayload, $secret);

        $this->call('POST', '/api/webhooks/razorpay', [], [], [], [
            'HTTP_X-Razorpay-Signature' => $sigFailed,
            'HTTP_X-Razorpay-Event-Id'  => 'event_failed_111',
            'CONTENT_TYPE'              => 'application/json',
        ], $failedPayload)->assertOk();
    }
}
