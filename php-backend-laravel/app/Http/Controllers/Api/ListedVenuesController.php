<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\ListedVenueController;
use App\Models\Venue;
use App\Services\VenueDiscovery\DiscoverySettings;
use App\Support\PlacePhotos;
use App\Support\PlatformRules;
use App\Support\SeoUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Listed venues for the app — venues Haraan shows whose owners haven't joined, so nothing
 * can be booked. Kept off GET /api/venues on purpose: every app already in the field treats
 * an item there as bookable, so these get their own endpoint that only new builds read.
 *
 *   GET  /api/venues/listed            the list (Pulse "More places to play")
 *   GET  /api/venues/listed/{id}       one venue with its photos, hours and contact
 *   POST /api/venues/listed/{id}/interest   "Ask to book" — same rules as the website form
 */
final class ListedVenuesController extends Controller
{
    public function index(): JsonResponse
    {
        if (! DiscoverySettings::publicPages()) {
            return response()->json(['data' => []]);
        }

        $photos = PlatformRules::bool('venue_discovery.google_photos');

        $rows = Venue::listed()->orderBy('city')->orderBy('name')->get()
            ->map(fn (Venue $v): array => $this->card($v, $photos))
            ->values()
            ->all();

        return response()->json(['data' => $rows]);
    }

    public function show(int $id): JsonResponse
    {
        abort_unless(DiscoverySettings::publicPages(), 404);
        $venue = Venue::listed()->findOrFail($id);
        $usePhotos = PlatformRules::bool('venue_discovery.google_photos');

        $photos = [];
        if ($usePhotos) {
            foreach (PlacePhotos::forPlace((string) $venue->place_id) as $p) {
                $photos[] = [
                    'url' => route('site.gamehub.placephoto', ['id' => $venue->id, 'index' => $p['index']]),
                    'credit' => $p['credit'],
                ];
            }
        }

        $hours = $venue->publicHours();

        return response()->json(['data' => $this->card($venue, $usePhotos) + [
            'address' => (string) $venue->address,
            'phone' => (string) $venue->contact_phone,
            'map_link' => (string) $venue->map_link,
            'hours' => $hours === '' ? [] : explode(' · ', $hours),
            'about' => (string) $venue->about,
            'photos' => $photos,
            'requests_open' => PlatformRules::bool('venue_discovery.requests_enabled'),
            'preferred_times' => ListedVenueController::TIMES,
        ]]);
    }

    /** Same validation, phone rule and one-row-per-phone as the website's form. */
    public function interest(Request $request, int $id): JsonResponse
    {
        return app(ListedVenueController::class)->interest($request, $id);
    }

    /** @return array<string, mixed> */
    private function card(Venue $v, bool $photos): array
    {
        $open = ListedVenueController::openNow($v);
        $city = (string) $v->city;
        $area = strcasecmp((string) $v->location, $city) === 0 ? '' : (string) $v->location;

        return [
            'id' => (string) $v->id,
            'name' => (string) $v->name,
            'area' => $area,
            'city' => $city,
            'sports' => $v->sports ? $v->sportsList() : [],
            'latitude' => $v->latitude !== null ? (float) $v->latitude : null,
            'longitude' => $v->longitude !== null ? (float) $v->longitude : null,
            // The venue's Google listing photo through our proxy (404s when it has none).
            'image' => $photos && $v->place_id ? route('site.gamehub.placephoto', ['id' => $v->id, 'index' => 0]) : null,
            'open_state' => $open['text'] ?? null,
            'open_now' => (bool) ($open['open'] ?? false),
            'url' => SeoUrl::venue($v),
            'is_bookable' => false,
        ];
    }
}
