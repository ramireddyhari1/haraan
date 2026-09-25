<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Event;
use App\Models\User;
use App\Models\Venue;
use App\Services\BookingNotifier;
use App\Services\BookingService;
use App\Services\RazorpayGateway;
use App\Support\Operations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Handles booking listing, creation, and cancellation.
 */
final class BookingsController extends Controller
{
    public function __construct(
        private readonly BookingService $bookings,
        private readonly RazorpayGateway $razorpay,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');

        if (!$authUser instanceof User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // ticketType is eager-loaded so the entry pass can show the tier the user bought.
        $query = Booking::query()->with(['event', 'venue', 'ticketType'])->orderByDesc('created_at');

        if ($authUser->role !== 'ADMIN') {
            $query->where('user_id', $authUser->id);
        }

        if ($request->filled('status') && $request->query('status') !== 'All') {
            $query->where('status', (string) $request->query('status'));
        }

        $limit = (int) $request->query('limit', '20');

        return BookingResource::collection($query->paginate($limit))
            ->response();
    }

    public function show(string $id): JsonResponse
    {
        $booking = Booking::query()->find($id);

        if ($booking === null) {
            return response()->json(['error' => 'Booking not found'], 404);
        }

        return response()->json(['data' => new BookingResource($booking)]);
    }

    /**
     * POST /api/bookings — create a booking.
     *
     * Payment is OPT-IN (`pay: true`) so already-installed app builds keep the legacy
     * behaviour: without the flag the order is confirmed immediately, exactly as before. New
     * clients (web + payment-aware app builds) send `pay: true` to get the reserve→pay path:
     * a PENDING reservation that holds inventory until {@see confirm()} verifies the signature.
     */
    public function store(StoreBookingRequest $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');

        if (!$authUser instanceof User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Legacy path for app builds that predate payment (no `pay` flag). It used to confirm
        // ANY order on the spot — paid tiers included, with no money taken. Now the order is
        // priced first: a free order still confirms in one step; a paid one is released and
        // the client is told to update, so a ticket is never issued without payment.
        if (! $request->boolean('pay')) {
            $held = $this->bookings->createOrder(
                $authUser,
                (int) $request->validated('eventId'),
                $request->orderLines(),
                $request->validated('couponCode'),
                $request->contact(),
                eventSlotId: $request->eventSlotId(),
                reserve: true,
            );

            if (Booking::orderGrandTotal($held) > 0) {
                $this->bookings->releaseReservation($held->pluck('id')->all());

                return response()->json([
                    'error' => 'update_required',
                    'message' => 'Update the Haraan app to pay for tickets.',
                ], 426);
            }

            $legacy = $this->bookings->confirmReservation($held->pluck('id')->all(), null)->load('ticketType');

            BookingNotifier::dispatch($legacy->first());

            return response()->json([
                'message' => 'Booking confirmed',
                'data'    => $this->envelope($legacy, (string) $legacy->first()->status),
            ], 201);
        }

        $bookings = $this->bookings->createOrder(
            $authUser,
            (int) $request->validated('eventId'),
            $request->orderLines(),
            $request->validated('couponCode'),
            $request->contact(),
            eventSlotId: $request->eventSlotId(),
            reserve: true,
        )->load('ticketType');

        $grand      = $this->grandTotal($bookings);
        $grandPaise = (int) round($grand * 100);

        // Free order (fully discounted / ₹0 tiers): no payment needed — confirm right away.
        if ($grandPaise <= 0) {
            $confirmed = $this->bookings->confirmReservation($bookings->pluck('id')->all(), null);

            BookingNotifier::dispatch($confirmed->first());

            return response()->json([
                'message' => 'Booking confirmed',
                'data'    => $this->envelope($confirmed->load('ticketType'), 'CONFIRMED'),
            ], 201);
        }

        // Paid order: create the Razorpay order, tag the reservation with its id, and hand the
        // client the checkout parameters. If the gateway is down we release the hold so the
        // seats aren't stuck PENDING for 15 minutes on an error the buyer can't retry past.
        try {
            $order = $this->razorpay->createOrder(
                $grandPaise,
                'evt_' . (int) $request->validated('eventId') . '_' . $bookings->first()->id,
            );
        } catch (RuntimeException $e) {
            $this->bookings->releaseReservation($bookings->pluck('id')->all());

            $status = $e->getCode() >= 400 ? (int) $e->getCode() : 500;

            return response()->json(['error' => $e->getMessage()], $status);
        }

        $this->bookings->attachOrderId($bookings, (string) $order['id']);

        return response()->json([
            'message'  => 'Payment required',
            'payment'  => [
                'required'   => true,
                'key'        => $this->razorpay->publicKey(),
                'orderId'    => $order['id'],
                'amount'     => $order['amount'],
                'currency'   => $order['currency'],
            ],
            'data'     => $this->envelope($bookings, 'PENDING'),
        ], 201);
    }

    /**
     * POST /api/bookings/confirm — finalise a reserved order after checkout.
     * Body: { razorpayOrderId, razorpayPaymentId, razorpaySignature }.
     * Verifies the signature server-side, then flips the reservation to CONFIRMED.
     * Idempotent: returns existing confirmed booking if already confirmed.
     */
    public function confirm(Request $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');

        if (!$authUser instanceof User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $data = $request->validate([
            'razorpayOrderId'   => ['required', 'string'],
            'razorpayPaymentId' => ['required', 'string'],
            'razorpaySignature' => ['required', 'string'],
        ]);

        if (! $this->razorpay->verifySignature($data['razorpayOrderId'], $data['razorpayPaymentId'], $data['razorpaySignature'])) {
            // If already confirmed (e.g. webhook won the race or retry), return confirmed data gracefully
            $existing = Booking::query()
                ->with(['event', 'venue', 'ticketType'])
                ->where('razorpay_order_id', $data['razorpayOrderId'])
                ->where('user_id', $authUser->id)
                ->where('status', 'CONFIRMED')
                ->get();

            if ($existing->isNotEmpty()) {
                return response()->json([
                    'message' => 'Booking confirmed',
                    'data'    => $this->envelope($existing, 'CONFIRMED'),
                ]);
            }

            // Leave the reservation PENDING (it will expire) rather than confirm on a bad
            // signature — never mark paid without a verified payment.
            return response()->json(['error' => 'Payment verification failed'], 400);
        }

        $confirmed = $this->bookings
            ->confirmReservedOrder($authUser, $data['razorpayOrderId'], $data['razorpayPaymentId'])
            ->load('ticketType');

        BookingNotifier::dispatch($confirmed->first());

        return response()->json([
            'message' => 'Booking confirmed',
            'data'    => $this->envelope($confirmed, 'CONFIRMED'),
        ]);
    }

    /**
     * POST /api/bookings/status — reconcile payment and retrieve status for a reserved order.
     * Body: { razorpayOrderId: string }.
     *
     * Handles network drops and client timeouts:
     * 1. If already CONFIRMED (by webhook or prior confirm), returns confirmed details immediately.
     * 2. If PENDING or EXPIRED, queries Razorpay directly to check if a captured payment exists.
     *    If captured: authoritatively confirms the reservation and credits the ledger.
     *    If not captured: returns PENDING status.
     */
    public function status(Request $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');

        if (! $authUser instanceof User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $data = $request->validate([
            'razorpayOrderId' => ['required', 'string'],
        ]);

        $orderId = trim($data['razorpayOrderId']);

        /** @var Collection<int, Booking> $bookings */
        $bookings = Booking::query()
            ->with(['event', 'venue', 'ticketType'])
            ->where('razorpay_order_id', $orderId)
            ->where('user_id', $authUser->id)
            ->get();

        if ($bookings->isEmpty()) {
            return response()->json(['error' => 'Reservation not found'], 404);
        }

        $first = $bookings->first();
        $currentStatus = strtoupper((string) $first->status);

        if ($currentStatus === 'CONFIRMED') {
            return response()->json([
                'status'  => 'CONFIRMED',
                'message' => 'Booking is confirmed',
                'data'    => $this->envelope($bookings, 'CONFIRMED'),
            ]);
        }

        if (in_array($currentStatus, ['CANCELLED', 'REFUNDED'], true)) {
            return response()->json([
                'status'  => $currentStatus,
                'message' => 'Booking is ' . strtolower($currentStatus),
                'data'    => $this->envelope($bookings, $currentStatus),
            ]);
        }

        // Reconcile with Razorpay authoritative REST API
        try {
            $capturedPaymentId = $this->razorpay->capturedPaymentFor($orderId);
        } catch (\Throwable) {
            $capturedPaymentId = null;
        }

        if ($capturedPaymentId !== null && $capturedPaymentId !== '') {
            $confirmed = $this->bookings
                ->confirmReservedOrder($authUser, $orderId, $capturedPaymentId)
                ->load(['event', 'venue', 'ticketType']);

            BookingNotifier::dispatch($confirmed->first());

            return response()->json([
                'status'     => 'CONFIRMED',
                'reconciled' => true,
                'message'    => 'Payment verified and booking confirmed',
                'data'       => $this->envelope($confirmed, 'CONFIRMED'),
            ]);
        }

        return response()->json([
            'status'  => 'PENDING',
            'message' => 'Payment awaiting confirmation from bank',
            'data'    => $this->envelope($bookings, 'PENDING'),
        ]);
    }

    /**
     * POST /api/bookings/release — hand back a reservation the buyer abandoned (modal dismissed
     * or payment failed), freeing its seats immediately instead of waiting for the hold to lapse.
     */
    public function release(Request $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');

        if (!$authUser instanceof User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $data = $request->validate(['razorpayOrderId' => ['required', 'string']]);

        $this->bookings->releaseReservedOrder($authUser, $data['razorpayOrderId']);

        return response()->json(['message' => 'Reservation released']);
    }

    /** Grand total charged for an order — the one definition, on Booking. */
    private function grandTotal(Collection $bookings): float
    {
        return Booking::orderGrandTotal($bookings);
    }

    /**
     * Aggregate response envelope. Top-level fields keep the legacy single-booking shape (so
     * older clients keep working); `bookings` carries the full per-tier breakdown.
     *
     * @param  Collection<int, Booking>  $bookings
     */
    private function envelope(Collection $bookings, string $status): array
    {
        $primary  = $bookings->first();
        $subtotal = round((float) $bookings->sum('total_amount'), 2);
        $fee      = round((float) $bookings->sum('convenience_fee'), 2);
        $discount = round((float) $bookings->sum('discount'), 2);

        return [
            'id'             => $primary->id,
            'quantity'       => (int) $bookings->sum('quantity'),
            'subtotal'       => (string) $subtotal,
            'convenienceFee' => (string) $fee,
            'platformFee' => (string) round((float) $bookings->sum('platform_fee'), 2),
            'gatewayFee' => (string) round((float) $bookings->sum('gateway_fee'), 2),
            'taxAmount' => (string) round((float) $bookings->sum('tax_amount'), 2),
            'discount'       => (string) $discount,
            'totalAmount' => (string) Booking::orderGrandTotal($bookings),
            'status'         => $status,
            'ticketCode'     => $primary->ticket_code,
            'bookings'       => BookingResource::collection($bookings),
        ];
    }

    /**
     * Quote a coupon for the app's checkout screen (preview only — the discount is
     * re-applied authoritatively on booking). Returns the ₹ amount it takes off.
     *
     * Runs {@see BookingService::resolveCoupon()}, the same resolver the booking itself
     * uses, so the amount shown on the order summary is the amount charged. It used to
     * decide this here with its own subset of the rules, which drifted two ways:
     * a percentage coupon quoted with no cart returned its raw `discount` (a 20%-off
     * code previewed as "₹20 off"), and the per-customer cap wasn't checked at all, so
     * an already-used code previewed a discount the booking then refused.
     *
     * `subtotal` is the ticket subtotal on screen. The convenience fee is added here
     * rather than trusted from the client, since it only affects the clamp that keeps
     * an order from going below ₹0.
     */
    public function validateCoupon(Request $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');

        if (! $authUser instanceof User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $code     = trim((string) $request->input('code'));
        $eventId  = $request->filled('eventId') ? (int) $request->input('eventId') : 0;
        $subtotal = max(0.0, (float) $request->input('subtotal', 0));
        // Null, not 0, when the client didn't say — a minimum-tickets coupon must not be
        // refused on a count nobody sent.
        $tickets  = $request->filled('tickets') ? max(0, (int) $request->input('tickets')) : null;

        // A venueId routes to the venue resolver instead of the event one. Same endpoint on
        // purpose: the client contract ("does this code work, and what would it save me?")
        // is identical, and a second near-identical route is how the two drift apart.
        $venueId = $request->filled('venueId') ? (int) $request->input('venueId') : 0;

        if ($venueId > 0) {
            $venue    = Venue::query()->find($venueId);
            $venueFee = $venue?->convenienceFeeFor($subtotal) ?? 0.0;
            $resolved = $this->bookings->resolveVenueCoupon($authUser, $venueId, $code, $subtotal, $venueFee);

            return response()->json(
                $resolved['coupon'] === null
                    ? ['valid' => false, 'message' => $resolved['message']]
                    : [
                        'valid'    => true,
                        'code'     => $resolved['coupon']->code,
                        'type'     => $resolved['coupon']->type,
                        'discount' => $resolved['discount'],
                        // The fee is quoted back so the summary's arithmetic is the
                        // server's, not a second copy computed in the app.
                        'fee'      => $venueFee,
                        'tax'      => Venue::taxFor($subtotal, (float) $resolved['discount']),
                        'message'  => $resolved['message'],
                    ]
            );
        }

        $event = $eventId > 0 ? Event::query()->find($eventId) : null;
        $fee = $event !== null ? $event->orderCharges($subtotal)['charges_before_discount'] : 0.0;

        $resolved = $this->bookings->resolveCoupon($authUser, $eventId, $code, $subtotal, $fee, $tickets);

        if ($resolved['coupon'] === null) {
            return response()->json([
                'valid'   => false,
                'message' => $resolved['message'],
            ]);
        }

        return response()->json([
            'valid'    => true,
            'code'     => $resolved['coupon']->code,
            'type'     => $resolved['coupon']->type,
            'discount' => $resolved['discount'],
            'message'  => $resolved['message'],
        ]);
    }

    /**
     * POST /api/bookings/quote — the bill for a cart, exactly as checkout will charge it:
     * every fee line (host fees, platform and gateway fees, tax), the coupon discount, and the
     * total. Display only — nothing is held, no coupon use is counted. Same body as a booking.
     *
     * The app's order summary shows these lines instead of recomputing fees on the device, so
     * a fee or tax change in /control reaches the summary without an app release.
     */
    public function quote(StoreBookingRequest $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');

        if (! $authUser instanceof User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $event = Event::query()->with('ticketTypes')->findOrFail((int) $request->validated('eventId'));
        $tiers = $event->ticketTypes->keyBy('id');
        $subtotal = 0.0;
        $tickets = 0;

        foreach ($request->orderLines() as $line) {
            $qty = max(0, (int) $line['quantity']);
            $tier = $line['ticketTypeId'] !== null ? $tiers->get($line['ticketTypeId']) : null;
            $unit = $tier !== null ? $tier->effectivePrice() : (float) $event->price;
            $subtotal += $unit * $qty;
            $tickets += $qty;
        }

        $subtotal = round($subtotal, 2);
        $code = trim((string) $request->validated('couponCode'));
        $coupon = ['applied' => false, 'code' => null, 'message' => null];
        $discount = 0.0;

        if ($code !== '') {
            $resolved = $this->bookings->resolveCoupon(
                $authUser, $event->id, $code, $subtotal, $event->orderCharges($subtotal)['charges_before_discount'], $tickets,
            );
            $discount = $resolved['discount'];
            $coupon = [
                'applied' => $resolved['coupon'] !== null,
                'code' => $resolved['coupon']?->code,
                'message' => $resolved['message'],
            ];
        }

        $charges = $event->orderCharges($subtotal, $discount);

        return response()->json([
            'subtotal' => $charges['subtotal'],
            'lines' => $charges['lines'],
            'discount' => $charges['discount'],
            'total' => $charges['total'],
            'coupon' => $coupon,
            'paymentsEnabled' => ! Operations::paymentsDisabled(),
            'bookingsPaused' => Operations::bookingsPaused(Operations::EVENTS),
        ]);
    }

    /** POST /api/bookings/venue — reserve a venue slot for a date. */
    public function storeVenue(Request $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');

        if (!$authUser instanceof User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $data = $request->validate([
            'venueId'  => ['required', 'integer'],
            'slotId'   => ['nullable', 'integer'],
            'courtId'  => ['nullable', 'integer'],
            'date'       => ['required', 'date'],
            'duration'   => ['nullable', 'integer', 'min:1', 'max:12'],
            'couponCode' => ['nullable', 'string', 'max:40'],
        ]);

        // Reserve first, price second: the court is held while we talk to Razorpay, so two
        // people tapping the same 7 PM slot can't both reach checkout. Mirrors the event
        // order flow in store() — same reserve → pay → confirm handshake, same endpoints.
        $booking = $this->bookings->createVenueBooking(
            $authUser,
            (int) $data['venueId'],
            isset($data['slotId']) ? (int) $data['slotId'] : null,
            (string) $data['date'],
            isset($data['courtId']) ? (int) $data['courtId'] : null,
            isset($data['duration']) ? (int) $data['duration'] : 1,
            reserve: true,
            // Priced server-side: the discount is resolved against the coupon's own rules,
            // never taken from a client-supplied total.
            couponCode: $data['couponCode'] ?? null,
        );

        // amountCharged(), not total_amount: Pulse tax sits beside the venue's share.
        $amountPaise = (int) round($booking->amountCharged() * 100);

        // Free slot (₹0 rate): nothing to charge, so confirm on the spot rather than
        // sending the user to a checkout for zero rupees.
        if ($amountPaise <= 0) {
            $confirmed = $this->bookings->confirmReservation([$booking->id], null)->first() ?? $booking;

            BookingNotifier::dispatch($confirmed);

            return response()->json([
                'message' => 'Venue booked',
                'data'    => new BookingResource($confirmed),
            ], 201);
        }

        try {
            $order = $this->razorpay->createOrder(
                $amountPaise,
                'vnu_' . (int) $data['venueId'] . '_' . $booking->id,
            );
        } catch (RuntimeException $e) {
            // Gateway down: drop the hold immediately. Leaving it would keep the court
            // blocked for 15 minutes over an error the user cannot retry past.
            $this->bookings->releaseReservation([$booking->id]);

            $status = $e->getCode() >= 400 ? (int) $e->getCode() : 500;

            return response()->json(['error' => $e->getMessage()], $status);
        }

        $this->bookings->attachOrderId(collect([$booking]), (string) $order['id']);

        // NB: the booking notification fires on CONFIRM, not here — a PENDING hold is not
        // a booking, and mailing "you're booked" before payment is how double-messaging starts.
        return response()->json([
            'message' => 'Payment required',
            'payment' => [
                'required' => true,
                'key'      => $this->razorpay->publicKey(),
                'orderId'  => $order['id'],
                'amount'   => $order['amount'],
                'currency' => $order['currency'],
            ],
            'data'    => new BookingResource($booking),
        ], 201);
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');

        if (!$authUser instanceof User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $booking = $this->bookings->cancel($authUser, $id);

        return response()->json([
            'message' => 'Booking cancelled',
            'data'    => new BookingResource($booking),
        ]);
    }

    /**
     * POST /api/bookings/{id}/reschedule — reschedule a confirmed venue booking.
     */
    public function reschedule(Request $request, string $id): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');

        if (! $authUser instanceof User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $data = $request->validate([
            'date'    => ['required', 'date'],
            'slotId'  => ['nullable', 'integer'],
            'courtId' => ['nullable', 'integer'],
        ]);

        $booking = $this->bookings->rescheduleVenueBooking(
            $authUser,
            $id,
            (string) $data['date'],
            isset($data['slotId']) ? (int) $data['slotId'] : null,
            isset($data['courtId']) ? (int) $data['courtId'] : null,
        );

        return response()->json([
            'message' => 'Booking rescheduled successfully',
            'data'    => new BookingResource($booking),
        ]);
    }
}
