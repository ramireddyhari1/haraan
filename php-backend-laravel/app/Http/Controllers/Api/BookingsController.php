<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Event;
use App\Models\User;
use App\Services\BookingService;
use App\Services\RazorpayGateway;
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

        // Legacy path for clients that don't understand payment — immediate confirmed booking.
        if (! $request->boolean('pay')) {
            $legacy = $this->bookings->createOrder(
                $authUser,
                (int) $request->validated('eventId'),
                $request->orderLines(),
                $request->validated('couponCode'),
                $request->contact(),
            )->load('ticketType');

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
            reserve: true,
        )->load('ticketType');

        $grand      = $this->grandTotal($bookings);
        $grandPaise = (int) round($grand * 100);

        // Free order (fully discounted / ₹0 tiers): no payment needed — confirm right away.
        if ($grandPaise <= 0) {
            $confirmed = $this->bookings->confirmReservation($bookings->pluck('id')->all(), null);

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
            // Leave the reservation PENDING (it will expire) rather than confirm on a bad
            // signature — never mark paid without a verified payment.
            return response()->json(['error' => 'Payment verification failed'], 400);
        }

        $confirmed = $this->bookings
            ->confirmReservedOrder($authUser, $data['razorpayOrderId'], $data['razorpayPaymentId'])
            ->load('ticketType');

        return response()->json([
            'message' => 'Booking confirmed',
            'data'    => $this->envelope($confirmed, 'CONFIRMED'),
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

    /** Grand total charged for an order: ticket subtotal + convenience fee − discount. */
    private function grandTotal(Collection $bookings): float
    {
        $subtotal = round((float) $bookings->sum('total_amount'), 2);
        $fee      = round((float) $bookings->sum('convenience_fee'), 2);
        $discount = round((float) $bookings->sum('discount'), 2);

        return round($subtotal + $fee - $discount, 2);
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
            'discount'       => (string) $discount,
            'totalAmount'    => (string) round($subtotal + $fee - $discount, 2),
            'status'         => $status,
            'ticketCode'     => $primary->ticket_code,
            'bookings'       => BookingResource::collection($bookings),
        ];
    }

    /**
     * Validate a coupon code for the checkout screen (preview only — the discount is
     * re-applied authoritatively on booking). Returns the flat ₹ amount it takes off.
     */
    public function validateCoupon(Request $request): JsonResponse
    {
        $code    = trim((string) $request->input('code'));
        $eventId = $request->filled('eventId') ? (int) $request->input('eventId') : null;
        $coupon  = \App\Models\Coupon::findByCode($code);

        if ($coupon === null || ! $coupon->isRedeemable()) {
            return response()->json([
                'valid'   => false,
                'message' => 'This code isn’t valid.',
            ]);
        }

        // A coupon scoped to another event must not preview a discount here.
        if (! $coupon->appliesToEvent($eventId)) {
            return response()->json([
                'valid'   => false,
                'message' => 'This code isn’t valid for this event.',
            ]);
        }

        return response()->json([
            'valid'    => true,
            'code'     => $coupon->code,
            'discount' => (float) $coupon->discount,
            'message'  => 'Coupon applied.',
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
            'date'     => ['required', 'date'],
            'duration' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        $booking = $this->bookings->createVenueBooking(
            $authUser,
            (int) $data['venueId'],
            isset($data['slotId']) ? (int) $data['slotId'] : null,
            (string) $data['date'],
            isset($data['courtId']) ? (int) $data['courtId'] : null,
            isset($data['duration']) ? (int) $data['duration'] : 1,
        );

        return response()->json([
            'message' => 'Venue booked',
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
}
