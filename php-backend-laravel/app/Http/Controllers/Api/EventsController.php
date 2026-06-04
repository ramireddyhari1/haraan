<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Event\StoreEventRequest;
use App\Http\Requests\Event\UpdateEventRequest;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CRUD operations for events.
 */
final class EventsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Event::query()->orderByDesc('created_at');

        if ($request->filled('status') && $request->query('status') !== 'All') {
            $query->where('status', (string) $request->query('status'));
        }

        if ($request->filled('search')) {
            $term = (string) $request->query('search');
            $query->where(function ($q) use ($term): void {
                $q->where('title', 'like', "%{$term}%")
                  ->orWhere('location', 'like', "%{$term}%")
                  ->orWhere('venue', 'like', "%{$term}%");
            });
        }

        $limit = (int) $request->query('limit', '20');

        return EventResource::collection($query->paginate($limit))
            ->response();
    }

    public function show(string $id): JsonResponse
    {
        $event = Event::query()->find($id);

        if ($event === null) {
            return response()->json(['error' => 'Event not found'], 404);
        }

        return response()->json(['data' => new EventResource($event)]);
    }

    public function store(StoreEventRequest $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');

        if (!$authUser instanceof User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validated = $request->validated();

        $event = Event::query()->create([
            'title'           => $validated['title'],
            'description'     => $validated['description'] ?? '',
            'category'        => $validated['category'] ?? 'GENERAL',
            'booking_format'  => $validated['bookingFormat'] ?? 'HYBRID',
            'visibility'      => $validated['visibility'] ?? 'PUBLIC',
            'access_code'     => $validated['accessCode'] ?? null,
            'location'        => $validated['location'] ?? '',
            'venue'           => $validated['venue'] ?? '',
            'date'            => $validated['date'] ?? null,
            'time'            => $validated['time'] ?? '',
            'price'           => (float) ($validated['price'] ?? 0),
            'total_slots'     => (int) ($validated['totalSlots'] ?? 0),
            'available_slots' => (int) ($validated['availableSlots'] ?? $validated['totalSlots'] ?? 0),
            'images'          => $validated['images'] ?? [],
            'status'          => $validated['status'] ?? 'DRAFT',
            'partner_id'      => $authUser->id,
            'seat_rows'       => $validated['seatRows'] ?? null,
            'seats_per_row'   => $validated['seatsPerRow'] ?? null,
            'seat_selection'  => (bool) ($validated['seatSelection'] ?? true),
        ]);

        return response()->json([
            'message' => 'Event created',
            'data'    => new EventResource($event),
        ], 201);
    }

    public function update(UpdateEventRequest $request, string $id): JsonResponse
    {
        $event = Event::query()->find($id);

        if ($event === null) {
            return response()->json(['error' => 'Event not found'], 404);
        }

        $validated = $request->validated();

        $event->fill(array_filter([
            'title'           => $validated['title'] ?? null,
            'description'     => $validated['description'] ?? null,
            'category'        => $validated['category'] ?? null,
            'location'        => $validated['location'] ?? null,
            'venue'           => $validated['venue'] ?? null,
            'date'            => $validated['date'] ?? null,
            'time'            => $validated['time'] ?? null,
            'price'           => isset($validated['price']) ? (float) $validated['price'] : null,
            'status'          => $validated['status'] ?? null,
            'images'          => $validated['images'] ?? null,
            'total_slots'     => isset($validated['totalSlots']) ? (int) $validated['totalSlots'] : null,
            'available_slots' => isset($validated['availableSlots']) ? (int) $validated['availableSlots'] : null,
        ], static fn ($v) => $v !== null));

        $event->save();

        return response()->json([
            'message' => 'Event updated',
            'data'    => new EventResource($event),
        ]);
    }

    public function categories(): JsonResponse
    {
        return response()->json([
            'data' => [
                'SPORTS', 'MUSIC', 'COMEDY', 'WORKSHOP', 'ADVENTURE',
                'FOOD', 'NIGHTLIFE', 'FESTIVAL', 'THEATER', 'EXHIBITION',
            ],
        ]);
    }
}
