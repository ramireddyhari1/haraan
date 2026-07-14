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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Handles booking listing, creation, and cancellation.
 */
final class BookingsController extends Controller
{
    public function __construct(
        private readonly BookingService $bookings,
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

    public function store(StoreBookingRequest $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');

        if (!$authUser instanceof User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $bookings = $this->bookings->createOrder(
            $authUser,
            (int) $request->validated('eventId'),
            $request->orderLines(),
            $request->validated('couponCode'),
        )->load('ticketType');

        $primary  = $bookings->first();
        $subtotal = round((float) $bookings->sum('total_amount'), 2);
        $fee      = round((float) $bookings->sum('convenience_fee'), 2);
        $discount = round((float) $bookings->sum('discount'), 2);
        $grand    = round($subtotal + $fee - $discount, 2);

        // Aggregate envelope: the top-level fields keep the legacy single-booking
        // shape (so older clients keep working) — `totalAmount` is the grand total
        // charged. `bookings` carries the full per-tier breakdown (one pass each).
        return response()->json([
            'message' => 'Booking confirmed',
            'data'    => [
                'id'             => $primary->id,
                'quantity'       => (int) $bookings->sum('quantity'),
                'subtotal'       => (string) $subtotal,
                'convenienceFee' => (string) $fee,
                'discount'       => (string) $discount,
                'totalAmount'    => (string) $grand,
                'status'         => $primary->status,
                'ticketCode'     => $primary->ticket_code,
                'bookings'       => BookingResource::collection($bookings),
            ],
        ], 201);
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
