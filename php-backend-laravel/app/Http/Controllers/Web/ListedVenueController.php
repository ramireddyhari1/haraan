<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Venue;
use App\Models\VenueInterest;
use App\Services\VenueDiscovery\DiscoverySettings;
use App\Support\ContactPrefill;
use App\Support\PlatformRules;
use App\Support\SeoUrl;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;

/**
 * The website page of a "listed" venue — one Haraan knows about whose owner has not
 * joined. It states only what is known (name, sports, area, address, phone, directions),
 * says plainly that it cannot be booked here, and gives a player one thing to do: ask to
 * book. Those requests are what the team takes to the owner.
 *
 * Reached through /gamehub/{id}, the same URL a bookable venue has, so the address
 * doesn't change the day the owner joins ({@see PublicWebController::gamehubDetail()}).
 */
class ListedVenueController extends Controller
{
    /** When players usually play — a choice, so the answers can be counted. */
    public const TIMES = ['Early morning', 'Morning', 'Afternoon', 'Evening', 'Night'];

    public function render(Request $request, Venue $venue): View
    {
        $sports = $venue->sportsList();
        $city = trim((string) $venue->city);
        $area = trim((string) $venue->location);
        $area = $area !== '' && strcasecmp($area, $city) !== 0 ? $area : '';
        $where = trim($area . ($area !== '' && $city !== '' ? ', ' : '') . $city);

        $sportLine = $venue->sports ? implode(', ', $sports) : '';
        $requests = VenueInterest::query()->where('venue_id', $venue->id)->distinct()->count('phone');

        $canonical = SeoUrl::venue($venue);
        $crumbs = array_values(array_filter([
            ['name' => 'Haraan', 'url' => url('/')],
            ['name' => 'Pulse Venues', 'url' => route('site.gamehub')],
            $city !== '' ? ['name' => $city, 'url' => url('/venues/' . SeoUrl::city($city))] : null,
            ['name' => $venue->name],
        ]));

        $indexable = PlatformRules::bool('venue_discovery.index_pages') && $venue->isIndexableListing();

        // Facts only — no rating (there are no Haraan reviews), no price, no photo.
        $schema = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'SportsActivityLocation',
            'name' => $venue->name,
            'url' => $canonical,
            'telephone' => $venue->contact_phone ?: null,
            'address' => array_filter([
                '@type' => 'PostalAddress',
                'streetAddress' => $venue->address ?: null,
                'addressLocality' => $city ?: null,
                'addressCountry' => 'IN',
            ]),
            'geo' => $venue->latitude && $venue->longitude ? [
                '@type' => 'GeoCoordinates',
                'latitude' => (float) $venue->latitude,
                'longitude' => (float) $venue->longitude,
            ] : null,
        ]);

        return view('site.venue-listed', [
            'title' => $venue->name . ($where !== '' ? ', ' . $where : ''),
            'venue' => $venue,
            'sports' => $venue->sports ? $sports : [],
            'city' => $city,
            'citySlug' => $city !== '' ? SeoUrl::city($city) : null,
            'where' => $where,
            'photos' => PlatformRules::bool('venue_discovery.google_photos')
                ? \App\Support\PlacePhotos::forPlace((string) $venue->place_id)
                : [],
            'requests' => $requests,
            'requestsOpen' => PlatformRules::bool('venue_discovery.requests_enabled'),
            'times' => self::TIMES,
            'prefill' => ContactPrefill::for($request->user()),
            'bookable' => $city === '' ? collect() : Venue::published()
                ->whereRaw('lower(city) = ?', [mb_strtolower($city)])
                ->orderByDesc('is_featured')->orderBy('sort_order')->limit(3)->get(),
            'nearby' => $city === '' ? collect() : self::forCity($city, null, 9)->where('id', '!=', $venue->id)->take(8)->values(),
            'seo' => [
                'title' => $venue->name . ($where !== '' ? ' in ' . $where : '') . ($sportLine !== '' ? ' — ' . $sportLine : '') . ' | Haraan',
                'description' => trim($venue->name . ($where !== '' ? ' in ' . $where : '') . '.'
                    . ($sportLine !== '' ? ' Sports: ' . $sportLine . '.' : '')
                    . ' Address and directions. This venue does not take bookings on Haraan yet — ask to book and we’ll tell the owner.'),
                'canonical' => $canonical,
                'robots' => $indexable ? 'index,follow' : 'noindex,follow',
                'breadcrumbs' => $crumbs,
                'jsonld' => $schema,
            ],
        ]);
    }

    /** A player asks to book a listed venue. One row per phone per venue; asking again updates it. */
    public function interest(Request $request, int $id): JsonResponse
    {
        abort_unless(DiscoverySettings::publicPages() && PlatformRules::bool('venue_discovery.requests_enabled'), 404);
        $venue = Venue::listed()->findOrFail($id);

        $data = Validator::make($request->all(), [
            'name' => ['required', 'string', 'min:2', 'max:60'],
            'phone' => ['required', 'string', 'max:20'],
            'sport' => ['nullable', 'string', 'max:40'],
            'preferred_time' => ['nullable', 'string', 'in:' . implode(',', self::TIMES)],
        ])->validate();

        $phone = preg_replace('/\D+/', '', $data['phone']) ?? '';
        if (strlen($phone) === 12 && str_starts_with($phone, '91')) {
            $phone = substr($phone, 2);
        }
        if (! preg_match('/^[6-9]\d{9}$/', $phone)) {
            return response()->json(['message' => 'Enter a 10-digit mobile number.', 'errors' => ['phone' => ['Enter a 10-digit mobile number.']]], 422);
        }

        $sport = in_array($data['sport'] ?? null, $venue->sportsList(), true) ? $data['sport'] : null;

        VenueInterest::query()->updateOrCreate(
            ['venue_id' => $venue->id, 'phone' => $phone],
            [
                'user_id' => $request->user()?->id,
                'kind' => VenueInterest::KIND_BOOK,
                'name' => trim($data['name']),
                'sport' => $sport,
                'preferred_time' => $data['preferred_time'] ?? null,
            ]
        );

        return response()->json(['ok' => true]);
    }

    /**
     * Listed venues for a city page, as plain rows (name, area, sports, link).
     *
     * @return Collection<int, object>
     */
    public static function forCity(string $city, ?string $sport, int $limit): Collection
    {
        if (! DiscoverySettings::publicPages()) {
            return collect();
        }

        $photos = PlatformRules::bool('venue_discovery.google_photos');
        $query = Venue::listed()->whereRaw('lower(city) = ?', [mb_strtolower($city)]);
        if ($sport !== null) {
            $query->where(function ($q) use ($sport): void {
                $q->whereRaw('lower(category) = ?', [$sport])->orWhere('sports', 'like', '%' . ucfirst($sport) . '%');
            });
        }

        return $query->orderBy('name')->limit($limit)->get()->map(fn (Venue $v): object => (object) [
            'id' => $v->id,
            'name' => $v->name,
            'area' => strcasecmp((string) $v->location, $city) === 0 ? '' : (string) $v->location,
            'sports' => $v->sports ? $v->sportsList() : [],
            'url' => SeoUrl::venue($v),
            // Loaded lazily by the browser; a venue with no Google photo just 404s and the
            // row hides its thumbnail, so listing 60 venues costs the page nothing.
            'photo' => $photos && $v->place_id ? route('site.gamehub.placephoto', ['id' => $v->id, 'index' => 0]) : null,
        ]);
    }
}
