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
        $venue = Venue::with(['slots', 'courts' => fn ($q) => $q->where('is_active', true), 'reviews' => fn ($q) => $q->where('is_active', true)])
            ->where('is_active', true)
            ->findOrFail($id);

        return response()->json(['data' => [
            ...$this->card($venue),
            // Full address + operating hours + policies + structured pricing. The app
            // parses all of these; omitting them here is why admin-entered values never
            // reached the venue page.
            'address' => $venue->address,
            'hours' => $venue->displayHours(),
            'hours_json' => $venue->hours_json ?? (object) [],
            'cancellation' => $venue->cancellationText(),
            'rules' => $venue->rules ?? [],
            'price_chart' => $venue->price_chart ?? [],
            'price_note' => $venue->price_note,
            'about' => $venue->about,
            'amenities' => $venue->amenities ?? [],
            // Courts are physical bookable units, each carrying the sports it can host and its
            // own hourly price (falls back to the venue price). The app filters by the chosen
            // sport, then locks the court across the picked time window.
            'courts' => $venue->courts->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'sports' => $c->sportsList() ?: $venue->sportsList(),
                'price' => $c->price ?? $venue->price,
                // Optional peak pricing (null price = none). Days are 3-letter names; the window
                // is "HH:MM". Clients apply the peak rate when the picked day/time matches.
                'peak_price' => $c->peak_price,
                'peak_days' => $c->peakDaysList(),
                'peak_start' => $c->peak_start,
                'peak_end' => $c->peak_end,
            ])->values(),
            'images' => $venue->images ?? [],
            'latitude' => $venue->latitude,
            'longitude' => $venue->longitude,
            'map_link' => $venue->map_link,
            'slots' => $venue->slots->map(fn ($s) => [
                'id' => $s->id,
                'day' => $s->day,
                'time' => $s->time,
                'available' => $s->is_available,
                'filling_fast' => $s->filling_fast,
                // Per-slot price + court capacity — the slot chips render both.
                'price' => $s->price,
                'capacity' => $s->capacity,
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
            'sports' => $v->sportsList(),
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
