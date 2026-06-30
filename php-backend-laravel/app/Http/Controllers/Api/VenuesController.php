<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Venue;
use Illuminate\Http\JsonResponse;

final class VenuesController extends Controller
{
    /** GET /api/venues — list active venues for the GameHub browse screen. */
    public function index(): JsonResponse
    {
        $venues = Venue::query()
            ->where('is_active', true)
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Venue $v) => $this->card($v));

        return response()->json(['data' => $venues]);
    }

    /** GET /api/venues/{id} — full detail incl. slots, reviews, amenities. */
    public function show(int $id): JsonResponse
    {
        $venue = Venue::with(['slots', 'reviews' => fn ($q) => $q->where('is_active', true)])
            ->where('is_active', true)
            ->findOrFail($id);

        return response()->json(['data' => [
            ...$this->card($venue),
            'about' => $venue->about,
            'amenities' => $venue->amenities ?? [],
            'images' => $venue->images ?? [],
            'latitude' => $venue->latitude,
            'longitude' => $venue->longitude,
            'slots' => $venue->slots->map(fn ($s) => [
                'id' => $s->id,
                'day' => $s->day,
                'time' => $s->time,
                'available' => $s->is_available,
                'filling_fast' => $s->filling_fast,
            ]),
            'reviews' => $venue->reviews->map(fn ($r) => [
                'name' => $r->name,
                'rating' => $r->rating,
                'text' => $r->text,
                'avatar' => $r->avatar,
                'ago' => $r->ago,
            ]),
        ]]);
    }

    /** Compact card shape shared by list + detail. */
    private function card(Venue $v): array
    {
        return [
            'id' => $v->id,
            'name' => $v->name,
            'category' => $v->category,
            'location' => $v->location,
            'distance' => $v->distance,
            'price' => $v->price,
            'rating' => $v->rating,
            'ratings_count' => $v->ratings_count,
            'reviews_count' => $v->reviews_count,
            'tagline' => $v->tagline,
            'image' => $v->images[0] ?? null,
            'is_bookable' => $v->is_bookable,
            'is_featured' => $v->is_featured,
        ];
    }
}
