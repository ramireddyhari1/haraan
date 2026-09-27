<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Venue;
use App\Services\VenueBookingWindow;
use App\Services\VenueSlotAvailability;
use App\Support\MediaUrl;
use App\Support\PlatformRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Support\BusinessClock;
use Illuminate\Support\Carbon;

final class VenuesController extends Controller
{
    /** GET /api/venues — list active venues for the GameHub browse screen. */
    public function index(): JsonResponse
    {
        $venues = Venue::published()
            ->with('partner.hostProfile')
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Venue $v) => $this->card($v));

        return response()->json(['data' => $venues]);
    }

    /** GET /api/venues/{id} — full detail incl. slots, reviews, amenities. */
    public function show(int $id): JsonResponse
    {
        $venue = Venue::published()
            ->with(['slots', 'courts' => fn ($q) => $q->where('is_active', true), 'reviews' => fn ($q) => $q->where('is_active', true), 'partner.hostProfile'])
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
            // The booking fee, quoted before checkout rather than after it. The app used
            // to learn this venue charged one only if the customer happened to apply a
            // coupon (the only response that carried a fee), so a fee venue showed a
            // total on the order summary that was not the total Razorpay then charged.
            // Same two fields the model prices from — none | flat | percent.
            'convenience_fee_type' => $venue->convenience_fee_type ?? 'none',
            'convenience_fee_value' => (float) ($venue->convenience_fee_value ?? 0),
            // Pulse tax (/control → Platform rules → Fees), same shape: none | flat | percent,
            // on (subtotal − discount). Platform-wide, so every venue quotes the same rule.
            'tax_type' => PlatformRules::string('fees.venue_tax_type'),
            'tax_value' => PlatformRules::float('fees.venue_tax_value'),
            'tax_label' => Venue::taxLabel(),
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
            'images' => MediaUrl::resolveMany($venue->images),
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
                // Which sports this time runs for; empty = all of them. A player
                // picking a sport shouldn't be offered a time that doesn't run it.
                'sports' => $s->sportsList(),
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

    /**
     * GET /api/venues/{id}/availability?date=YYYY-MM-DD — per-slot bookability for one day.
     *
     * Computed against real bookings, live payment holds and court blocks (see
     * VenueSlotAvailability), unlike the detail payload's `slots[].available`, which is only
     * the venue's template switch. Dates before yesterday or past the caller's booking window
     * (the venue's window plus any priority days on their plan) are refused, so the endpoint
     * can't sweep a venue's history and never offers a day checkout would refuse.
     */
    public function availability(
        Request $request,
        int $id,
        VenueSlotAvailability $availability,
        VenueBookingWindow $window,
    ): JsonResponse {
        $venue = Venue::published()->findOrFail($id);

        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'duration' => ['nullable', 'integer', 'min:1', 'max:12'],
            'court_id' => ['nullable', 'integer'],
        ]);
        $date = isset($validated['date'])
            ? Carbon::createFromFormat('Y-m-d', $validated['date'])->startOfDay()
            : BusinessClock::todayDate();
        $duration = isset($validated['duration']) ? (int) $validated['duration'] : 1;
        $courtId = isset($validated['court_id']) ? (int) $validated['court_id'] : null;

        $viewer = $request->attributes->get('auth_user');
        $viewer = $viewer instanceof User ? $viewer : null;

        if ($date->lt(BusinessClock::todayDate()->subDay())) {
            return response()->json(['message' => 'Date out of range'], 422);
        }

        if (! $window->allows($venue, $viewer, $date)) {
            return response()->json([
                'message' => $window->refusal($venue, $viewer),
                'code' => 'outside_booking_window',
                'booking_window' => $window->describe($venue, $viewer),
            ], 422);
        }

        return response()->json(['data' => [
            'date' => $date->toDateString(),
            'slots' => $availability->forDate($venue, $date, $duration, $courtId),
            'booking_window' => $window->describe($venue, $viewer),
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
            // Coordinates travel with the card so the client can compute real GPS
            // distance and radius-filter the list (the static `distance` string above
            // is admin copy, identical for every viewer).
            'latitude' => $v->latitude,
            'longitude' => $v->longitude,
            'price' => $v->price,
            'rating' => ($v->ratings_count > 0 && $v->rating) ? $v->rating : null,
            'ratings_count' => $v->ratings_count,
            'reviews_count' => $v->reviews_count,
            'tagline' => $v->tagline,
            'image' => MediaUrl::resolve(is_array($v->images) ? ($v->images[0] ?? null) : null),
            // Every photo, so list cards can swipe through the gallery without opening the
            // venue. Additive: `image` above stays for older app builds.
            'images' => MediaUrl::resolveMany(is_array($v->images) ? $v->images : null),
            'is_bookable' => $v->is_bookable,
            'is_featured' => $v->is_featured,
            // The owner's public page, when they have a live one (host-profile twin).
            'host' => $this->hostPayload($v),
        ];
    }

    /**
     * The venue owner's public profile, or null when they don't have a live one.
     *
     * @return array<string, mixed>|null
     */
    private function hostPayload(Venue $v): ?array
    {
        $profile = $v->partner?->hostProfile;

        if ($profile === null || ! $profile->isLive()) {
            return null;
        }

        return [
            'name' => $profile->display_name,
            'slug' => $profile->slug,
            'logo' => $profile->logoUrl(),
            'verified' => $profile->isVerified(),
            'url' => url('/host/'.$profile->slug),
        ];
    }
}
