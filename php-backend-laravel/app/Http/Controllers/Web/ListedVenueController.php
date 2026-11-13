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
use Illuminate\Support\Str;

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

        $hoursLine = $venue->publicHours();
        $openNow = self::openNow($venue);

        // Facts only — no rating (there are no Haraan reviews), no price.
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
            'openingHoursSpecification' => $venue->hours_json ? ($venue->openingHoursSchema() ?: null) : null,
            'geo' => $venue->latitude && $venue->longitude ? [
                '@type' => 'GeoCoordinates',
                'latitude' => (float) $venue->latitude,
                'longitude' => (float) $venue->longitude,
            ] : null,
        ]);

        $activity = \App\Support\VenueActivity::forVenue($venue);
        $kind = self::kind($venue);
        $faqs = self::faqs($venue, $kind, $area, $city, $hoursLine, $activity);
        if ($faqs) {
            $schema = [$schema, [
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => array_map(static fn (array $f) => [
                    '@type' => 'Question',
                    'name' => $f['question'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['answer']],
                ], $faqs),
            ]];
        }

        // "Crazy Turf, Krishnalanka – Football Turf in Vijayawada": the venue's name and
        // area (what people search for it by), then what it is and where.
        $title = $venue->name . ($area !== '' ? ', ' . $area : '')
            . ($city !== '' ? ' – ' . $kind . ' in ' . $city : '');
        $description = Str::limit(trim(
            $venue->name . ' is a ' . mb_strtolower($kind) . ($where !== '' ? ' in ' . $where : '')
            . ($sportLine !== '' && count($sports) > 1 ? ' for ' . mb_strtolower($sportLine) : '') . '.'
            . ($hoursLine !== '' ? ' Timings: ' . $hoursLine . '.' : '')
            . ' Photos, phone number & directions'
            . ($activity && $activity['matches'] > 0 ? ', and ' . $activity['matches'] . ' ' . ($activity['matches'] === 1 ? 'match' : 'matches') . ' scored here' : '')
            . '. Ask to book on Haraan.'
        ), 158, '…');

        return view('site.venue-listed', [
            'title' => $title,
            'activity' => $activity,
            'faqs' => $faqs,
            'venue' => $venue,
            'sports' => $venue->sports ? $sports : [],
            'city' => $city,
            'citySlug' => $city !== '' ? SeoUrl::city($city) : null,
            'where' => $where,
            'photos' => PlatformRules::bool('venue_discovery.google_photos')
                ? \App\Support\PlacePhotos::forPlace((string) $venue->place_id)
                : [],
            'hoursLine' => $hoursLine,
            'openNow' => $openNow,
            'requests' => $requests,
            'requestsOpen' => PlatformRules::bool('venue_discovery.requests_enabled'),
            'times' => self::TIMES,
            'prefill' => ContactPrefill::for($request->user()),
            'bookable' => $city === '' ? collect() : Venue::published()
                ->whereRaw('lower(city) = ?', [mb_strtolower($city)])
                ->orderByDesc('is_featured')->orderBy('sort_order')->limit(3)->get(),
            'cityLinks' => $city === '' ? [] : self::cityLinks($venue, $city),
            'nearby' => $city === '' ? collect() : self::nearby($venue, $city),
            'seo' => [
                'title' => $title,
                'description' => $description,
                'canonical' => $canonical,
                'robots' => $indexable ? 'index,follow' : 'noindex,follow',
                'breadcrumbs' => $crumbs,
                'jsonld' => $schema,
            ],
        ]);
    }

    /**
     * "Open now · closes 11 PM" / "Closed now · opens 6 AM" from the weekly hours, on the
     * venue's clock (IST). Null when the venue has no hours.
     */
    public static function openNow(Venue $venue): ?array
    {
        if (! is_array($venue->hours_json) || $venue->hours_json === []) {
            return null;
        }

        $now = \App\Support\BusinessClock::now();
        $label = static function (string $hm): string {
            [$h, $m] = array_map('intval', explode(':', $hm));

            return \Illuminate\Support\Carbon::createFromTime($h % 24, $m)->format($m === 0 ? 'g A' : 'g:i A');
        };
        $minutes = static function (string $hm): int {
            [$h, $m] = array_map('intval', explode(':', $hm));

            return $h * 60 + $m;
        };

        $today = $venue->hoursForWeekday($now->format('D'));
        $nowM = $now->hour * 60 + $now->minute;
        if ($today !== null) {
            $o = $minutes($today['open']);
            $c = $minutes($today['close']);
            if ($o === $c) {
                return ['open' => true, 'text' => 'Open 24 hours'];
            }
            $closesAfterMidnight = $c < $o;
            if ($nowM >= $o && ($closesAfterMidnight || $nowM < $c)) {
                return ['open' => true, 'text' => 'Open now · closes ' . $label($today['close'])];
            }
            if ($nowM < $o) {
                return ['open' => false, 'text' => 'Closed now · opens ' . $label($today['open'])];
            }
        }

        for ($i = 1; $i <= 7; $i++) {
            $day = $now->copy()->addDays($i);
            $h = $venue->hoursForWeekday($day->format('D'));
            if ($h !== null) {
                return ['open' => false, 'text' => 'Closed now · opens ' . ($i === 1 ? 'tomorrow' : $day->format('l')) . ' ' . $label($h['open'])];
            }
        }

        return null;
    }

    /** What a venue is called, singular: "Football Turf", "Badminton Court", "Cricket & Football Turf". */
    public static function kind(Venue $venue): string
    {
        if (preg_match('/\bstadium\b/i', (string) $venue->name)) {
            return 'Stadium';
        }
        $nouns = [
            'Cricket' => ['Cricket', 'Turf'], 'Football' => ['Football', 'Turf'],
            'Badminton' => ['Badminton', 'Court'], 'Pickleball' => ['Pickleball', 'Court'],
            'Tennis' => ['Tennis', 'Court'], 'Basketball' => ['Basketball', 'Court'],
            'Volleyball' => ['Volleyball', 'Court'],
        ];
        $known = array_values(array_filter(
            array_map(fn (string $s) => $nouns[$s] ?? null, $venue->sports ? array_values(array_unique($venue->sportsList())) : []),
        ));
        if (count($known) === 1) {
            return $known[0][0] . ' ' . $known[0][1];
        }
        if (count($known) === 2 && $known[0][1] === $known[1][1]) {
            return $known[0][0] . ' & ' . $known[1][0] . ' ' . $known[0][1];
        }

        return 'Sports Venue';
    }

    /**
     * Questions people search about a venue, answered from what is known — a question
     * whose fact is missing is left out. Shown on the page and as FAQPage markup.
     *
     * @return list<array{question:string,answer:string}>
     */
    private static function faqs(Venue $venue, string $kind, string $area, string $city, string $hoursLine, ?array $activity): array
    {
        $name = $venue->name;
        $faqs = [];
        if ($hoursLine !== '') {
            $faqs[] = ['question' => 'What are the timings of ' . $name . '?', 'answer' => $name . ' is open ' . $hoursLine . '. Timings come from the venue’s listing — call before you go.'];
        }
        if (trim((string) $venue->address) !== '') {
            $faqs[] = ['question' => 'Where is ' . $name . ($city !== '' ? ' in ' . $city : '') . '?', 'answer' => $name . ' is at ' . trim((string) $venue->address) . '. Use “Directions” on this page to open it in Google Maps.'];
        }
        if ($venue->sports) {
            $faqs[] = ['question' => 'Which sports can I play at ' . $name . '?', 'answer' => $name . ' is a ' . mb_strtolower($kind) . ($area !== '' ? ' in ' . $area : '') . ' for ' . implode(', ', $venue->sportsList()) . '.'];
        }
        if ($venue->contact_phone) {
            $faqs[] = ['question' => 'How do I book ' . $name . '?', 'answer' => 'Call the venue on ' . $venue->contact_phone . ', or tap “Ask to book” on this page — Haraan passes your request to the owner and messages you when online booking opens.'];
        }
        if ($activity && $activity['faq']) {
            $faqs[] = $activity['faq'];
        }

        return $faqs;
    }

    /**
     * Other venues by real distance from this one ("1.2 km"), so the block answers "what
     * else is near here"; alphabetical in the city when this venue has no pin.
     */
    private static function nearby(Venue $venue, string $city): Collection
    {
        if ($venue->latitude !== null && $venue->longitude !== null) {
            $rows = \App\Support\CityVenueGuide::nearest(
                \App\Support\CityVenueGuide::venues($city), (float) $venue->latitude, (float) $venue->longitude, 15.0, (int) $venue->id,
            );
            if ($rows !== []) {
                return collect(array_slice($rows, 0, 8))->map(fn (array $r) => (object) [
                    'name' => $r['name'],
                    'area' => $r['area'],
                    'sports' => $r['sports'],
                    'url' => $r['url'],
                    'km' => \App\Support\CityVenueGuide::distanceLabel($r['km']),
                ]);
            }
        }

        return self::forCity($city, null, 9)->where('id', '!=', $venue->id)->take(8)->values();
    }

    /**
     * "Badminton courts in Vijayawada", "All sports venues in Vijayawada" — the city and
     * sport pages this venue belongs to, so search engines (and players) can walk up.
     *
     * @return list<array{label:string,url:string}>
     */
    private static function cityLinks(Venue $venue, string $city): array
    {
        $citySlug = SeoUrl::city($city);
        $links = [];
        foreach (\App\Support\CityVenueGuide::SPORTS as $slug => [$label, $sport]) {
            if ($slug !== 'box-cricket' && in_array($sport, $venue->sports ? $venue->sportsList() : [], true)) {
                $links[] = ['label' => $label . ' in ' . $city, 'url' => url('/venues/' . $citySlug . '/' . $slug)];
                if ($slug === 'cricket') {
                    $links[] = ['label' => 'Box cricket in ' . $city, 'url' => url('/venues/' . $citySlug . '/box-cricket')];
                }
            }
        }
        $links[] = ['label' => 'All sports venues in ' . $city, 'url' => url('/venues/' . $citySlug)];
        if (($state = \App\Support\CityVenueGuide::stateSlug()) !== null && \App\Support\CityVenueGuide::inState($city)) {
            $links[] = ['label' => 'Sports venues across ' . DiscoverySettings::state(), 'url' => url('/venues/' . $state)];
        }

        return $links;
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
