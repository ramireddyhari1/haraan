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

        $query = Booking::query()->orderByDesc('created_at');

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

        $booking = $this->bookings->create($authUser, $request->validated());

        return response()->json([
            'message' => 'Booking created',
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
