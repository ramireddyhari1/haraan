<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The gap between "Razorpay took the money" and "we wrote CONFIRMED".
 *
 * A ticket reservation holds its seats as PENDING and only becomes a real booking when the
 * client comes back with a verified signature. Everything in that gap is someone else's
 * network: a UPI approval waiting on a human, a browser tab closed on the payment screen, an
 * app killed on the checkout sheet. Get it wrong and the customer is charged for a ticket that
 * exists nowhere — not in their account, not in the host's analytics, which count only paid
 * statuses.
 *
 * These tests pin the recovery: the sweep must ask Razorpay before writing a hold off, the
 * webhook must be able to confirm without the client, and confirming a hold that already
 * lapsed must take its seats back rather than sell them twice.
 */
class PaidTicketHoldRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_SECRET = 'whsec-test';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.razorpay.key' => 'rzp_test_key',
            'services.razorpay.secret' => 'rzp_test_secret',
            'services.razorpay.webhook_secret' => self::WEBHOOK_SECRET,
        ]);
    }

    private function buyer(): User
    {
        return User::create([
            'name' => 'Hold Buyer', 'email' => 'hold-buyer@example.test',
            'password' => bcrypt('secret'), 'role' => 'user', 'status' => 'active',
        ]);
    }

    private function event(): Event
    {
        $partner = User::firstOrCreate(
            ['email' => 'hold-host@example.test'],
            ['name' => 'Host', 'password' => bcrypt('secret'), 'role' => 'PARTNER', 'status' => 'active'],
        );

        return Event::create([
            'partner_id' => $partner->id,
            'title' => 'Hold Recovery Show',
            'category' => 'Music',
            'location' => 'Test Arena',
            'venue' => 'Test Arena, Hyderabad',
            'date' => now()->addDays(7),
            'time' => '19:00',
            'price' => 500,
            'total_slots' => 100,
            'available_slots' => 100,
            'images' => [],
            'status' => 'published',
        ]);
    }

    /**
     * A reservation exactly as checkout leaves it while the buyer is on the payment screen:
     * PENDING, tagged with the gateway order, and with its hold already lapsed.
     *
     * @return array{0: Event, 1: TicketType, 2: \Illuminate\Support\Collection<int, Booking>}
     */
    private function lapsedHold(int $qty = 2): array
    {
        $event = $this->event();
        $tier = TicketType::create([
            'event_id' => $event->id, 'name' => 'Gold', 'kind' => 'paid',
            'price' => 500, 'quota' => 10, 'sold' => 0, 'sort' => 1,
        ]);

        $order = app(BookingService::class)->createOrder(
            $this->buyer(),
            $event->id,
            [['ticketTypeId' => $tier->id, 'quantity' => $qty]],
            reserve: true,
        );

        app(BookingService::class)->attachOrderId($order, 'order_LAPSED');

        Booking::query()->whereIn('id', $order->pluck('id')->all())
            ->update(['reserved_until' => now()->subMinute()]);

        return [$event, $tier, $order];
    }

    /** Whether the faked Razorpay currently reports a captured payment. */
    private bool $gatewayPaid = false;

    private bool $gatewayFaked = false;

    /**
     * Razorpay's answer to "was this order paid?" — a captured payment, or nothing.
     *
     * The stub reads the flag at call time rather than being re-registered: Http::fake()
     * MERGES stubs, so a second registration for the same pattern never wins and a test that
     * needs the answer to change mid-run would silently keep the first one.
     */
    private function fakeGateway(bool $paid): void
    {
        $this->gatewayPaid = $paid;

        if ($this->gatewayFaked) {
            return;
        }

        $this->gatewayFaked = true;

        Http::fake(['api.razorpay.com/v1/orders/*/payments' => fn () => Http::response([
            'count' => $this->gatewayPaid ? 1 : 0,
            'items' => $this->gatewayPaid ? [['id' => 'pay_RECOVERED', 'status' => 'captured']] : [],
        ], 200)]);
    }

    /** @param array<string, mixed> $payload */
    private function webhook(array $payload)
    {
        $raw = json_encode($payload);

        return $this->call(
            'POST', '/api/webhooks/razorpay', [], [], [],
            [
                'HTTP_X-Razorpay-Signature' => hash_hmac('sha256', $raw, self::WEBHOOK_SECRET),
                'CONTENT_TYPE' => 'application/json',
            ],
            $raw,
        );
    }

    /**
     * The bug this whole file exists for: the buyer paid, their client never came back, and
     * the every-minute sweep found a lapsed hold. Expiring it would have lost the ticket.
     */
    public function test_a_lapsed_hold_that_was_actually_paid_is_confirmed_not_expired(): void
    {
        [$event, $tier, $order] = $this->lapsedHold();
        $this->fakeGateway(paid: true);

        $released = app(BookingService::class)->releaseAllExpired();

        $this->assertSame(0, $released);
        $this->assertSame(['CONFIRMED'], Booking::query()->pluck('status')->unique()->all());
        $this->assertSame('pay_RECOVERED', Booking::query()->value('razorpay_payment_id'));

        // Seats stay sold — the hold was never handed back.
        $this->assertSame(98, (int) $event->fresh()->available_slots);
        $this->assertSame(2, (int) $tier->fresh()->sold);
    }

    /** The ordinary case still has to work: nobody paid, so the seats go back. */
    public function test_a_genuinely_abandoned_hold_is_expired_and_its_seats_returned(): void
    {
        [$event, $tier] = $this->lapsedHold();
        $this->fakeGateway(paid: false);

        // One row: an order line is one booking of quantity 2, not two bookings.
        $released = app(BookingService::class)->releaseAllExpired();

        $this->assertSame(1, $released);
        $this->assertSame(['EXPIRED'], Booking::query()->pluck('status')->unique()->all());
        $this->assertSame(100, (int) $event->fresh()->available_slots);
        $this->assertSame(0, (int) $tier->fresh()->sold);
    }

    /**
     * An unreachable gateway means "don't know", and "don't know" must never be spent as
     * "not paid" — the hold stands and the next sweep asks again.
     */
    public function test_an_unreachable_gateway_leaves_the_hold_alone(): void
    {
        [$event] = $this->lapsedHold();
        Http::fake(['api.razorpay.com/*' => Http::response('', 500)]);

        $released = app(BookingService::class)->releaseAllExpired();

        $this->assertSame(0, $released);
        $this->assertSame(['PENDING'], Booking::query()->pluck('status')->unique()->all());
        $this->assertSame(98, (int) $event->fresh()->available_slots);
    }

    /**
     * A hold that never reached the gateway can't have been charged, so it needs no round-trip
     * to write off — and must not make one.
     */
    public function test_a_hold_with_no_gateway_order_is_expired_without_asking_razorpay(): void
    {
        [$event] = $this->lapsedHold();
        Booking::query()->update(['razorpay_order_id' => null]);
        Http::fake();

        $this->assertSame(1, app(BookingService::class)->releaseAllExpired());
        $this->assertSame(['EXPIRED'], Booking::query()->pluck('status')->unique()->all());
        $this->assertSame(100, (int) $event->fresh()->available_slots);
        Http::assertNothingSent();
    }

    /**
     * Confirming after the hold lapsed must take the inventory back. Without this the host's
     * "Bookings x / y" and sell-through read from the slot counts and show a paid ticket as an
     * unsold seat — revenue with nothing against it.
     */
    public function test_confirming_a_released_hold_takes_its_inventory_back(): void
    {
        [$event, $tier, $order] = $this->lapsedHold();
        $ids = $order->pluck('id')->all();

        // The hold lapses and is released the old way — seats handed back.
        $this->fakeGateway(paid: false);
        app(BookingService::class)->releaseAllExpired();
        $this->assertSame(100, (int) $event->fresh()->available_slots);

        // Then the buyer's confirm callback finally lands.
        app(BookingService::class)->confirmReservation($ids, 'pay_LATE');

        $this->assertSame(['CONFIRMED'], Booking::query()->pluck('status')->unique()->all());
        $this->assertSame(98, (int) $event->fresh()->available_slots);
        $this->assertSame(2, (int) $tier->fresh()->sold);
    }

    /** Confirming twice is a no-op, not a second bite out of the inventory. */
    public function test_confirming_twice_does_not_double_deduct(): void
    {
        [$event, $tier, $order] = $this->lapsedHold();
        $ids = $order->pluck('id')->all();

        app(BookingService::class)->confirmReservation($ids, 'pay_ONCE');
        app(BookingService::class)->confirmReservation($ids, 'pay_ONCE');

        $this->assertSame(98, (int) $event->fresh()->available_slots);
        $this->assertSame(2, (int) $tier->fresh()->sold);
    }

    /**
     * The server-side backstop. Razorpay tells us the payment captured; the booking confirms
     * with no client involved at all.
     */
    public function test_the_webhook_confirms_a_ticket_order_without_the_client(): void
    {
        [$event, $tier] = $this->lapsedHold();

        $this->webhook(['event' => 'payment.captured', 'payload' => ['payment' => ['entity' => [
            'id' => 'pay_WEBHOOK', 'order_id' => 'order_LAPSED',
        ]]]])->assertStatus(200);

        $this->assertSame(['CONFIRMED'], Booking::query()->pluck('status')->unique()->all());
        $this->assertSame('pay_WEBHOOK', Booking::query()->value('razorpay_payment_id'));
        $this->assertSame(98, (int) $event->fresh()->available_slots);
        $this->assertSame(2, (int) $tier->fresh()->sold);
    }

    /** Razorpay redelivers until it gets a 200; redelivery must change nothing. */
    public function test_a_redelivered_webhook_is_a_no_op(): void
    {
        [$event, $tier] = $this->lapsedHold();

        $payload = ['event' => 'payment.captured', 'payload' => ['payment' => ['entity' => [
            'id' => 'pay_WEBHOOK', 'order_id' => 'order_LAPSED',
        ]]]];

        $this->webhook($payload)->assertStatus(200);
        $this->webhook($payload)->assertStatus(200);

        $this->assertSame(1, Booking::query()->where('status', 'CONFIRMED')->count());
        $this->assertSame(98, (int) $event->fresh()->available_slots);
        $this->assertSame(2, (int) $tier->fresh()->sold);
    }

    /** A late capture event must not resurrect an order that was already cancelled. */
    public function test_the_webhook_will_not_revive_a_cancelled_order(): void
    {
        [$event, $tier] = $this->lapsedHold();
        Booking::query()->update(['status' => 'CANCELLED']);

        $this->webhook(['event' => 'payment.captured', 'payload' => ['payment' => ['entity' => [
            'id' => 'pay_LATE', 'order_id' => 'order_LAPSED',
        ]]]])->assertStatus(200);

        $this->assertSame(['CANCELLED'], Booking::query()->pluck('status')->unique()->all());
    }

    /** A paid row must never be expirable, whatever route reaches the release path. */
    public function test_a_booking_with_a_payment_against_it_is_never_expired(): void
    {
        [$event] = $this->lapsedHold();

        // A PENDING row that somehow already carries a payment id — the shape that turns a
        // customer's money into a cancelled ticket.
        Booking::query()->update(['razorpay_payment_id' => 'pay_ALREADY']);
        Http::fake(['api.razorpay.com/*' => Http::response(['count' => 0, 'items' => []], 200)]);

        app(BookingService::class)->releaseAllExpired();

        $this->assertSame(['PENDING'], Booking::query()->pluck('status')->unique()->all());
        $this->assertSame(98, (int) $event->fresh()->available_slots);
    }

    /**
     * The backlog case. A row an earlier build already wrote off as EXPIRED is out of the
     * sweep's reach forever — reconciliation is the only way those customers get their ticket
     * and the host's reports get the sale.
     */
    public function test_reconciliation_recovers_an_order_already_written_off_as_expired(): void
    {
        [$event, $tier] = $this->lapsedHold();

        // How the old behaviour left it: expired, seats handed back, money taken.
        $this->fakeGateway(paid: false);
        app(BookingService::class)->releaseAllExpired();
        $this->assertSame(['EXPIRED'], Booking::query()->pluck('status')->unique()->all());

        $this->fakeGateway(paid: true);

        // A dry run reports and changes nothing.
        $found = app(BookingService::class)->reconcileUnconfirmedPayments(30);
        $this->assertCount(1, $found);
        $this->assertSame('would confirm', $found[0]['action']);
        $this->assertSame('pay_RECOVERED', $found[0]['payment']);
        $this->assertSame(['EXPIRED'], Booking::query()->pluck('status')->unique()->all());

        // --apply confirms it and takes the inventory back.
        $applied = app(BookingService::class)->reconcileUnconfirmedPayments(30, apply: true);

        $this->assertSame('confirmed', $applied[0]['action']);
        $this->assertSame(['CONFIRMED'], Booking::query()->pluck('status')->unique()->all());
        $this->assertSame(98, (int) $event->fresh()->available_slots);
        $this->assertSame(2, (int) $tier->fresh()->sold);
    }

    /** Reconciliation must never invent a ticket: an unpaid order stays unpaid. */
    public function test_reconciliation_leaves_genuinely_unpaid_orders_alone(): void
    {
        [$event] = $this->lapsedHold();
        $this->fakeGateway(paid: false);

        $this->assertSame([], app(BookingService::class)->reconcileUnconfirmedPayments(30, apply: true));
        $this->assertSame(['PENDING'], Booking::query()->pluck('status')->unique()->all());
    }

    /** The hold has to outlast a real UPI approval, not a stopwatch. */
    public function test_the_reservation_hold_is_long_enough_to_pay_in(): void
    {
        $this->assertGreaterThanOrEqual(10, BookingService::RESERVATION_HOLD_MINUTES);
    }
}
