<?php

declare(strict_types=1);

namespace App\Services\VenueDiscovery;

use App\Models\Venue;
use App\Models\VenueCandidate;
use App\Support\PlacePhotos;
use App\Support\PlatformRules;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Finds sports venues in a city on Google and files them as {@see VenueCandidate}s for
 * review, then marks the ones Playo also lists.
 *
 * Google is the source of the facts kept (name, address, pin, phone, Maps link) — never
 * its rating, reviews or photos. Text Search answers with its best guess for anything,
 * so every result is filtered: it must be in the chosen state, still operating, and look
 * like somewhere a sport is played rather than a shop that sells bats.
 */
final class VenueDiscovery
{
    /** How far from a city's own venues a place can be and still be filed under that city. */
    private const CITY_REACH_M = 22000.0;

    /** What Google calls a city when it isn't the name people search with. */
    private const CITY_SPELLINGS = [
        'rajahmundry' => ['rajamahendravaram', 'rajamahendravaram rural'],
        'anantapur' => ['anantapuramu', 'anantapur rural'],
        'kadapa' => ['cuddapah', 'ysr kadapa'],
        'visakhapatnam' => ['vizag'],
        'nellore' => ['sri potti sriramulu nellore'],
    ];

    private const ENDPOINT = 'https://places.googleapis.com/v1/places:searchText';

    private const FIELDS = 'places.id,places.displayName,places.formattedAddress,places.location,places.types,'
        . 'places.primaryType,places.businessStatus,places.googleMapsUri,places.nationalPhoneNumber,'
        . 'places.websiteUri,places.addressComponents,nextPageToken';

    /** Google place types that are a sports venue on their own. */
    private const SPORT_TYPES = [
        'sports_complex', 'stadium', 'athletic_field', 'sports_club', 'sports_activity_location', 'arena',
        'sports_coaching', 'tennis_court',
    ];

    /** Primary types that are never a place to book a court, whatever the name says. */
    private const WRONG_TYPES = [
        'sporting_goods_store', 'store', 'clothing_store', 'shoe_store', 'shopping_mall', 'gym', 'fitness_center',
        'swimming_pool', 'hotel', 'lodging', 'restaurant', 'school', 'primary_school', 'secondary_school',
        'university', 'hospital', 'doctor', 'physiotherapist', 'wholesaler', 'manufacturer', 'corporate_office',
        'golf_course', 'bowling_alley', 'amusement_center', 'video_arcade', 'yoga_studio', 'event_venue',
        'wedding_venue', 'banquet_hall', 'supermarket', 'market', 'service', 'association_or_organization',
        'go_karting_venue', 'indoor_playground', 'park', 'consultant', 'general_contractor',
    ];

    private const NAME_HINT = '/\b(turf|box\s*cricket|cricket|futsal|football|soccer|badminton|shuttle|pickle\s*ball|tennis|'
        . 'basket\s*ball|volley\s*ball|sports?\s*(arena|club|complex|zone|hub|park|city)|arena|stadium|indoor)\b/i';

    private const NAME_WRONG = '/\b(shop|store|stores|showroom|traders|enterprises|emporium|snooker|billiards|gym|fitness|'
        . 'swimming|skating|chess|carrom|karate|taekwondo|dance|yoga|table\s*tennis|bats?\s*manufactur|repair|'
        . 'tailors?|jersey|trophies|infra|infrastructure|solutions|association|authority|federation|rifle|shooting|pvt|ltd|'
        . 'nets?\s*(supplier|dealer|manufactur))\b/i';

    /** Sport name => words in a venue's name that mean it. */
    private const SPORT_WORDS = [
        'Cricket' => '/\b(cricket|box\s*cricket)\b/i',
        'Football' => '/\b(football|futsal|soccer)\b/i',
        'Badminton' => '/\b(badminton|shuttle)\b/i',
        'Pickleball' => '/\bpickle\s*ball\b/i',
        'Tennis' => '/(?<!table\s)(?<!table)\btennis\b/i',
        'Basketball' => '/\bbasket\s*ball\b/i',
        'Volleyball' => '/\bvolley\s*ball\b/i',
    ];

    /**
     * Search one city and file what's found.
     *
     * @return array{searches:int, seen:int, added:int, updated:int, duplicates:int, skipped:int}
     */
    public function harvest(string $city): array
    {
        if (! PlacePhotos::configured()) {
            throw new RuntimeException('No Google Maps server key is set (GOOGLE_MAPS_SERVER_KEY).');
        }

        $city = trim($city);
        $state = DiscoverySettings::state();
        $terms = DiscoverySettings::terms();
        if ($city === '' || $terms === []) {
            throw new RuntimeException('Pick a city and keep at least one search line in the discovery settings.');
        }

        $stats = ['searches' => 0, 'seen' => 0, 'added' => 0, 'updated' => 0, 'duplicates' => 0, 'skipped' => 0];

        /** @var array<string, array{place: array<string,mixed>, sports: array<string,bool>, phrases: array<string,bool>}> $found */
        $found = [];
        $tokens = array_fill(0, count($terms), null);

        for ($page = 1; $page <= DiscoverySettings::pages(); $page++) {
            $open = $page === 1
                ? array_keys($terms)
                : array_keys(array_filter($tokens, static fn ($t): bool => is_string($t) && $t !== ''));
            if ($open === []) {
                break;
            }

            // One round trip per page for every search line, rather than a line at a time.
            $responses = Http::pool(function (Pool $pool) use ($open, $terms, $tokens, $city, $state): array {
                $calls = [];
                foreach ($open as $i) {
                    $body = [
                        'textQuery' => $terms[$i]['phrase'] . ' in ' . $city . ($state !== '' ? ', ' . $state : ''),
                        'regionCode' => 'IN',
                        'languageCode' => 'en',
                        'pageSize' => 20,
                    ];
                    if (is_string($tokens[$i])) {
                        $body['pageToken'] = $tokens[$i];
                    }
                    $calls[] = $pool->as((string) $i)->timeout(20)->withHeaders([
                        'X-Goog-Api-Key' => PlacePhotos::key(),
                        'X-Goog-FieldMask' => self::FIELDS,
                    ])->post(self::ENDPOINT, $body);
                }

                return $calls;
            });

            foreach ($open as $i) {
                $stats['searches']++;
                $res = $responses[(string) $i] ?? null;
                $tokens[$i] = null;

                if (! $res instanceof Response || ! $res->successful()) {
                    $status = $res instanceof Response ? $res->status() : 0;
                    $message = $res instanceof Response ? (string) $res->json('error.message') : 'no response';
                    Log::warning('Venue discovery search failed', ['city' => $city, 'phrase' => $terms[$i]['phrase'], 'status' => $status, 'message' => $message]);
                    // A key or billing problem fails every line the same way — say so once, plainly.
                    if (in_array($status, [400, 401, 403], true) && $found === []) {
                        throw new RuntimeException('Google refused the search: ' . ($message ?: 'HTTP ' . $status));
                    }

                    continue;
                }

                $tokens[$i] = $res->json('nextPageToken');
                foreach ((array) $res->json('places') as $place) {
                    $id = $place['id'] ?? null;
                    if (! is_string($id) || $id === '') {
                        continue;
                    }
                    $found[$id] ??= ['place' => $place, 'sports' => [], 'phrases' => []];
                    $found[$id]['phrases'][$terms[$i]['phrase']] = true;
                    if ($terms[$i]['sport'] !== null) {
                        $found[$id]['sports'][$terms[$i]['sport']] = true;
                    }
                }
            }
        }

        $existing = Venue::query()
            ->get(['id', 'name', 'place_id', 'latitude', 'longitude'])
            ->all();

        $stats['seen'] = count($found);
        $kept = [];
        foreach ($found as $placeId => $hit) {
            $facts = $this->facts($hit['place'], $city, $state);
            if ($facts === null) {
                $stats['skipped']++;

                continue;
            }
            $kept[$placeId] = $facts;
        }
        $kept = $this->foldSuburbsIntoCity($kept, $city);

        foreach ($kept as $placeId => $facts) {
            $hit = $found[$placeId];
            // "Basketball Court", "Tennis court 3": a pin with no name of its own is a public
            // court, not a venue with an owner to sign up — and no page worth having.
            // A business that calls itself "Turf Play Arena" has a phone; a bare pin does not.
            if ($facts['phone'] === null && $facts['website'] === null
                && NameMatcher::distinctive($facts['name'], array_merge(NameMatcher::tokens($city), NameMatcher::tokens((string) $facts['area']))) === []) {
                $stats['skipped']++;

                continue;
            }

            $facts['sports'] = $this->sports($facts['name'], array_keys($hit['sports']));
            $candidate = VenueCandidate::query()->where('place_id', $placeId)->first();

            if ($candidate !== null) {
                $candidate->fill($facts + [
                    'found_by' => array_values(array_unique(array_merge((array) $candidate->found_by, array_keys($hit['phrases'])))),
                ])->save();
                $stats['updated']++;

                continue;
            }

            $twin = $this->alreadyAVenue($placeId, $facts, $existing);
            VenueCandidate::query()->create($facts + [
                'place_id' => $placeId,
                'found_by' => array_keys($hit['phrases']),
                'status' => $twin ? VenueCandidate::STATUS_DUPLICATE : VenueCandidate::STATUS_NEW,
                'venue_id' => $twin?->id,
            ]);
            $twin ? $stats['duplicates']++ : $stats['added']++;
        }

        $this->tidyCities();

        return $stats;
    }

    /**
     * Mark which of a city's candidates Playo also lists.
     *
     * @return array{checked:int, matched:int, playo:int}
     */
    public function crossCheck(string $city): array
    {
        $city = trim($city);
        $theirs = PlayoDirectory::venuesIn($city);
        $stats = ['checked' => 0, 'matched' => 0, 'playo' => count($theirs)];
        if ($theirs === []) {
            // Nothing to compare with — leave rows "unchecked" rather than call them unmatched.
            return $stats;
        }

        $cityWords = array_merge(NameMatcher::tokens($city), PlayoDirectory::slugsFor($city));

        VenueCandidate::query()
            ->whereRaw('lower(city) = ?', [mb_strtolower($city)])
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($theirs, $cityWords, &$stats): void {
                foreach ($rows as $row) {
                    $mine = ['name' => $row->name, 'lat' => $row->latitude, 'lng' => $row->longitude];
                    $match = null;
                    foreach ($theirs as $their) {
                        $ignore = array_merge($cityWords, NameMatcher::tokens((string) ($their['area'] ?? '')), NameMatcher::tokens((string) $row->area));
                        if (NameMatcher::same($mine, $their, $ignore)) {
                            $match = $their;
                            break;
                        }
                    }

                    $row->forceFill([
                        'crosscheck_status' => $match ? VenueCandidate::CHECK_MATCHED : VenueCandidate::CHECK_UNMATCHED,
                        'crosscheck_source' => $match ? 'playo' : null,
                        'crosscheck_name' => $match['name'] ?? null,
                        'crosscheck_url' => $match['url'] ?? null,
                        'crosschecked_at' => now(),
                    ])->save();

                    $stats['checked']++;
                    $stats['matched'] += $match ? 1 : 0;
                }
            });

        return $stats;
    }

    /**
     * File every candidate under one of the configured cities where it belongs there.
     *
     * Google names a city its own way ("Rajamahendravaram" for Rajahmundry, "kadapa") and
     * names the village for a venue on a city's edge. A search for one city also turns up
     * venues on the edge of the next. So after each search: other spellings are renamed,
     * and a candidate in a village within reach of a configured city's own venues moves
     * to the nearest such city, keeping the village as its area. Rows in a configured city
     * never move. Uses only what is already stored — no searches.
     *
     * @return int rows changed
     */
    public function tidyCities(): int
    {
        $changed = 0;
        $cities = DiscoverySettings::cities();
        $known = array_map('mb_strtolower', $cities);

        foreach ($cities as $city) {
            $spellings = array_unique(array_merge([mb_strtolower($city)], PlayoDirectory::slugsFor($city), self::CITY_SPELLINGS[mb_strtolower($city)] ?? []));
            $changed += VenueCandidate::query()
                ->whereIn(\Illuminate\Support\Facades\DB::raw('lower(city)'), $spellings)
                ->where('city', '!=', $city)
                ->update(['city' => $city]);
        }

        $centres = [];
        foreach ($cities as $city) {
            $own = VenueCandidate::query()->where('city', $city)->whereNotNull('latitude')->get(['latitude', 'longitude']);
            if ($own->count() < 3) {
                continue;
            }
            $centres[$city] = [
                'lat' => (float) $own->pluck('latitude')->sort()->values()[intdiv($own->count(), 2)],
                'lng' => (float) $own->pluck('longitude')->sort()->values()[intdiv($own->count(), 2)],
            ];
        }

        $strays = VenueCandidate::query()->whereNotNull('latitude')
            ->whereNotIn(\Illuminate\Support\Facades\DB::raw('lower(city)'), $known)->get();
        foreach ($strays as $row) {
            $best = null;
            foreach ($centres as $city => $centre) {
                $metres = NameMatcher::metres($centre, ['lat' => $row->latitude, 'lng' => $row->longitude]);
                if ($metres !== null && $metres <= self::CITY_REACH_M && ($best === null || $metres < $best[1])) {
                    $best = [$city, $metres];
                }
            }
            if ($best !== null) {
                $row->forceFill(['area' => $row->area ?: $row->city, 'city' => $best[0]])->save();
                $changed++;
            }
        }

        return $changed;
    }

    /**
     * Fill a listed venue's opening hours from its Google listing, when it has none of its
     * own. Hours are a fact players need before they go ("is it open at 6 am?"), and the
     * venue form's Hours step reads the same map. Nothing else is taken — no rating, no
     * reviews. Never overwrites hours someone set in /control.
     *
     * @return bool whether hours were written
     */
    public function fillHours(Venue $venue): bool
    {
        if (! $venue->isListed() || ! empty($venue->hours_json) || trim((string) $venue->place_id) === '' || ! PlacePhotos::configured()) {
            return false;
        }

        try {
            $res = Http::timeout(15)->withHeaders([
                'X-Goog-Api-Key' => PlacePhotos::key(),
                'X-Goog-FieldMask' => 'regularOpeningHours',
            ])->get('https://places.googleapis.com/v1/places/' . $venue->place_id);
        } catch (Throwable $e) {
            Log::warning('Venue hours lookup errored: ' . $e->getMessage());

            return false;
        }
        if (! $res->successful()) {
            Log::warning('Venue hours lookup failed', ['venue' => $venue->id, 'status' => $res->status()]);

            return false;
        }

        $week = self::weekFromPeriods((array) $res->json('regularOpeningHours.periods'));
        if ($week === []) {
            return false;
        }

        $venue->forceFill(['hours_json' => $week])->saveQuietly();

        return true;
    }

    /**
     * Google's opening periods → the venue's `hours_json` map (Mon…Sun => open/close "HH:MM",
     * closed days left out). A day with a lunch break becomes first open → last close, which
     * is what the one-line hours on the page can say. A period with no close is open around
     * the clock, stored as open == close (the model reads that as "Open 24 hours").
     *
     * @param  list<array<string, mixed>>  $periods
     * @return array<string, array{open:string, close:string}>
     */
    public static function weekFromPeriods(array $periods): array
    {
        $names = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        $hm = static fn (array $p): string => sprintf('%02d:%02d', (int) ($p['hour'] ?? 0), (int) ($p['minute'] ?? 0));

        if (count($periods) === 1 && ! isset($periods[0]['close'])) {
            return array_fill_keys(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'], ['open' => '00:00', 'close' => '00:00']);
        }

        $days = [];
        foreach ($periods as $p) {
            $open = $p['open'] ?? null;
            if (! is_array($open) || ! isset($open['day'])) {
                continue;
            }
            $key = $names[((int) $open['day']) % 7];
            $start = $hm($open);
            // Closing past midnight ends "at" the next day's time — still this day's close.
            $end = isset($p['close']) && is_array($p['close']) ? $hm($p['close']) : '00:00';
            if (isset($p['close']['day']) && (int) $p['close']['day'] !== (int) $open['day'] && $end !== '00:00') {
                $end = '23:59';
            }
            if (! isset($days[$key])) {
                $days[$key] = ['open' => $start, 'close' => $end];

                continue;
            }
            $days[$key]['open'] = min($days[$key]['open'], $start);
            $days[$key]['close'] = $days[$key]['close'] === '00:00' || $end === '00:00' ? '00:00' : max($days[$key]['close'], $end);
        }

        // Back in Mon…Sun order.
        $ordered = [];
        foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $k) {
            if (isset($days[$k])) {
                $ordered[$k] = $days[$k];
            }
        }

        return $ordered;
    }

    public static function crossCheckEnabled(): bool
    {
        return PlatformRules::bool('venue_discovery.crosscheck_playo');
    }

    /**
     * The facts kept from one Google result, or null when it isn't a venue for this state.
     *
     * @param  array<string, mixed>  $place
     * @return ?array<string, mixed>
     */
    private function facts(array $place, string $searchedCity, string $state): ?array
    {
        // Names arrive with emoji and decorations ("D-Don Box Cricket 🏏") — keep the words.
        $name = (string) ($place['displayName']['text'] ?? '');
        $name = trim((string) preg_replace('/\s+/u', ' ', (string) preg_replace('/[^\p{L}\p{M}\p{N}\p{P}\s&+]/u', '', $name)), " 	-–|,");
        $status = (string) ($place['businessStatus'] ?? 'OPERATIONAL');
        if ($name === '' || $status !== 'OPERATIONAL') {
            return null;
        }

        $types = array_values(array_filter((array) ($place['types'] ?? []), 'is_string'));
        $primary = (string) ($place['primaryType'] ?? '');

        if (in_array($primary, self::WRONG_TYPES, true) || preg_match(self::NAME_WRONG, $name)) {
            return null;
        }
        $looksRight = array_intersect($types, self::SPORT_TYPES) !== [] || preg_match(self::NAME_HINT, $name);
        if (! $looksRight) {
            return null;
        }

        $part = static function (string ...$wanted) use ($place): ?string {
            foreach ($wanted as $type) {
                foreach ((array) ($place['addressComponents'] ?? []) as $c) {
                    if (in_array($type, (array) ($c['types'] ?? []), true)) {
                        return trim((string) ($c['longText'] ?? '')) ?: null;
                    }
                }
            }

            return null;
        };

        $theirState = $part('administrative_area_level_1');
        if ($state !== '' && ($theirState === null || mb_strtolower($theirState) !== mb_strtolower($state))) {
            return null;
        }

        $lat = $place['location']['latitude'] ?? null;
        $lng = $place['location']['longitude'] ?? null;

        return [
            'name' => mb_substr($name, 0, 250),
            'address' => mb_substr(trim((string) ($place['formattedAddress'] ?? '')), 0, 250) ?: null,
            'area' => $part('sublocality_level_1', 'sublocality', 'neighborhood', 'sublocality_level_2'),
            'city' => $part('locality') ?? $searchedCity,
            'state' => $theirState,
            'latitude' => is_numeric($lat) ? (float) $lat : null,
            'longitude' => is_numeric($lng) ? (float) $lng : null,
            'phone' => trim((string) ($place['nationalPhoneNumber'] ?? '')) ?: null,
            'website' => mb_substr(trim((string) ($place['websiteUri'] ?? '')), 0, 480) ?: null,
            'maps_url' => mb_substr(trim((string) ($place['googleMapsUri'] ?? '')), 0, 480) ?: null,
            'primary_type' => $primary ?: null,
            'google_types' => $types,
        ];
    }

    /**
     * Google's "locality" for a place on the edge of a city is the village it stands in
     * (Kanuru, Poranki, Gollapudi), which would scatter one city's venues over a dozen
     * city pages. Anything within reach of the searched city's own venues is filed under
     * that city, with the village kept as its area. Places further out — another town
     * that happened to match — keep their own.
     *
     * @param  array<string, array<string, mixed>>  $kept
     * @return array<string, array<string, mixed>>
     */
    private function foldSuburbsIntoCity(array $kept, string $city): array
    {
        $own = array_filter($kept, static fn (array $f): bool => mb_strtolower((string) $f['city']) === mb_strtolower($city)
            && $f['latitude'] !== null && $f['longitude'] !== null);
        if (count($own) < 3) {
            return $kept;
        }

        $median = static function (array $values): float {
            sort($values);

            return (float) $values[intdiv(count($values), 2)];
        };
        $centre = [
            'lat' => $median(array_column($own, 'latitude')),
            'lng' => $median(array_column($own, 'longitude')),
        ];

        foreach ($kept as $id => $facts) {
            if (mb_strtolower((string) $facts['city']) === mb_strtolower($city)) {
                continue;
            }
            $metres = NameMatcher::metres($centre, ['lat' => $facts['latitude'], 'lng' => $facts['longitude']]);
            if ($metres !== null && $metres <= self::CITY_REACH_M) {
                $kept[$id]['area'] = $facts['area'] ?: $facts['city'];
                $kept[$id]['city'] = $city;
            }
        }

        return $kept;
    }

    /**
     * The sports a place offers: what its name says, or — when the name names none — what it
     * was found under, if that is specific. A big arena comes back for every search; being
     * found under five sports says nothing, so then it is left for the reviewer.
     *
     * @param  list<string>  $foundUnder
     * @return list<string>
     */
    private function sports(string $name, array $foundUnder): array
    {
        $sports = [];
        foreach (self::SPORT_WORDS as $sport => $pattern) {
            if (preg_match($pattern, $name)) {
                $sports[] = $sport;
            }
        }
        if ($sports === [] && count($foundUnder) <= 2) {
            $sports = $foundUnder;
        }

        return array_values(array_unique($sports));
    }

    /**
     * @param  array<string, mixed>  $facts
     * @param  list<Venue>  $venues
     */
    private function alreadyAVenue(string $placeId, array $facts, array $venues): ?Venue
    {
        $mine = ['name' => (string) $facts['name'], 'lat' => $facts['latitude'], 'lng' => $facts['longitude']];

        foreach ($venues as $venue) {
            if ($venue->place_id !== null && $venue->place_id === $placeId) {
                return $venue;
            }
            try {
                $theirs = ['name' => (string) $venue->name, 'lat' => $venue->latitude, 'lng' => $venue->longitude];
                $metres = NameMatcher::metres($mine, $theirs);
                if ($metres !== null && $metres <= 300 && NameMatcher::same($mine, $theirs)) {
                    return $venue;
                }
            } catch (Throwable) {
                // A venue with a broken pin just can't be matched by distance.
            }
        }

        return null;
    }
}
