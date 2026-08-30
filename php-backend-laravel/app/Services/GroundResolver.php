<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LiveMatch;
use App\Models\MatchGround;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Turns the venue somebody typed into the ground they actually played at.
 *
 * Two identities, tried in that order:
 *
 *   1. **Google's place_id**, when Places can find the ground. This is the strong one:
 *      it survives spelling, word order and the district being included or not.
 *   2. **A normalised name key**, when it cannot. Most gully grounds are not in Places
 *      — a school field or a stretch of road has no listing — so this is the common
 *      case, not the fallback for emergencies. It already merges the duplicate this
 *      feature exists because of ("Saipeta Ground, Kadapa" / "Saipeta Ground").
 *
 * With no Maps key configured the whole thing still works on identity 2. That matters:
 * ground stats must not be a paid feature that silently stops working when a key
 * expires.
 */
final class GroundResolver
{
    /** Places lookups are cached hard — grounds do not move or get renamed. */
    private const CACHE_DAYS = 60;

    private const TIMEOUT_SECONDS = 6;

    /**
     * Find or create the canonical ground for a match, and stamp it on the match.
     *
     * Returns null when the match names no venue at all, which is a real state: a
     * match created in a hurry has nothing to resolve and simply has no ground.
     */
    public function resolve(LiveMatch $match): ?MatchGround
    {
        $venue = trim((string) ($match->venue ?? ''));
        if ($venue === '') {
            return null;
        }

        $district = trim((string) ($match->district ?? ''));
        $locality = trim((string) ($match->locality ?? ''));
        $nameKey = MatchGround::nameKey($venue, $district, $locality);
        if ($nameKey === '') {
            return null;
        }

        // Ask Places, but only when the match has somewhere to search around: an
        // unbiased text search for "School Ground" returns a school on another
        // continent, which would merge two unrelated grounds into one row.
        $place = null;
        if ($match->latitude !== null && $match->longitude !== null) {
            $place = $this->findPlace($venue, (float) $match->latitude, (float) $match->longitude);
            // Places will always return SOMETHING. Left unguarded it renamed
            // "Open Court, Proddatur" to "DISTRICT SESSIONS JUDGE COURT" and a maidan
            // to "Barkaas Arabic Restaurant" — it matched the generic word and handed
            // back the nearest business with it. A ground called by the wrong name is
            // worse than a ground Google could not find, so a candidate that does not
            // look like the same place is discarded entirely.
            if ($place !== null && ! $this->isSamePlace($venue, (string) ($place['name'] ?? ''), $district, $locality)) {
                $place = null;
            }
        }

        $ground = null;
        if ($place !== null && ($place['place_id'] ?? '') !== '') {
            $ground = MatchGround::where('place_id', $place['place_id'])->first();
        }
        if ($ground === null) {
            // Name key is scoped by district: two "Youth Club"s in different towns are
            // two grounds, and merging them would be worse than not merging at all.
            $ground = MatchGround::where('name_key', $nameKey)
                ->when($district !== '', fn ($q) => $q->where('district', $district))
                ->first();
        }

        if ($ground === null) {
            $ground = new MatchGround([
                'name_key' => $nameKey,
                'name' => $venue,
                'district' => $district ?: null,
                'locality' => $locality ?: null,
            ]);
        }

        // Fill in whatever this match knows that the ground does not yet. A ground row
        // improves as matches are played at it; it is never overwritten by a match that
        // knows less than the row already does.
        if ($place !== null) {
            $ground->place_id = $ground->place_id ?: ($place['place_id'] ?: null);
            $ground->formatted_address = $ground->formatted_address ?: ($place['address'] ?: null);
            $ground->name = $place['name'] ?: $ground->name;
            $ground->latitude = $ground->latitude ?? $place['lat'];
            $ground->longitude = $ground->longitude ?? $place['lng'];
        }
        $ground->latitude = $ground->latitude ?? ($match->latitude !== null ? (float) $match->latitude : null);
        $ground->longitude = $ground->longitude ?? ($match->longitude !== null ? (float) $match->longitude : null);
        $ground->district = $ground->district ?: ($district ?: null);
        $ground->locality = $ground->locality ?: ($locality ?: null);
        $ground->save();

        if ((int) $match->ground_id !== (int) $ground->id) {
            $match->forceFill(['ground_id' => $ground->id])->saveQuietly();
        }

        return $ground;
    }

    /**
     * Does this candidate plausibly name the same place the scorer typed?
     *
     * Compared on DISTINCTIVE words only. Every ground in the country shares "ground",
     * "court", "club", "school" and "stadium" with every other one, so an overlap on
     * those says nothing — it is precisely what produced a restaurant and a courthouse.
     * What has to match is the name part: Saipeta, Fathima, Proddatur.
     */
    private function isSamePlace(string $typed, string $candidate, string $district = '', string $locality = ''): bool
    {
        if ($candidate === '') {
            return false;
        }

        // The town name is shared by every place in the town, so it is not evidence of
        // anything. Without this, "Open Court, Proddatur" matched "Proddatur Court
        // Complex" on the one word they were always going to have in common.
        $generic = array_merge(
            array_filter(preg_split('/[^a-z0-9]+/u', mb_strtolower($district . ' ' . $locality)) ?: []),
            [
            'ground', 'grounds', 'court', 'courts', 'club', 'school', 'stadium', 'field',
            'academy', 'complex', 'sports', 'cricket', 'turf', 'park', 'arena', 'centre',
            'center', 'the', 'and', 'of', 'open', 'new', 'old', 'high', 'public',
            ],
        );
        $tokens = function (string $value) use ($generic): array {
            $clean = preg_replace('/[^a-z0-9]+/u', ' ', mb_strtolower($value)) ?? '';
            $words = array_filter(
                preg_split('/\s+/', trim($clean)) ?: [],
                // Two-letter fragments carry no identity either.
                fn ($w) => $w !== '' && mb_strlen($w) > 2 && ! in_array($w, $generic, true),
            );

            return array_values(array_unique($words));
        };

        $typedWords = $tokens($typed);
        $candidateWords = $tokens($candidate);
        if ($typedWords === [] || $candidateWords === []) {
            // Nothing distinctive on one side — "Open Court" against "Sessions Court".
            // No evidence they are the same place, so they are not treated as one.
            return false;
        }

        return array_intersect($typedWords, $candidateWords) !== [];
    }

    /**
     * Google Places "Find Place", biased to where the match was played.
     *
     * @return array{place_id: string, name: string, address: string, lat: ?float, lng: ?float}|null
     */
    private function findPlace(string $venue, float $lat, float $lng): ?array
    {
        $key = trim((string) config('services.google_maps.server_key'));
        if ($key === '') {
            return null;
        }

        // Rounded to ~1km in the cache key: the same ground searched from two corners
        // of the same field must be one lookup, not two.
        $cacheKey = 'ground_place:' . md5(mb_strtolower($venue) . '|' . round($lat, 2) . '|' . round($lng, 2));

        return Cache::remember($cacheKey, now()->addDays(self::CACHE_DAYS), function () use ($venue, $lat, $lng, $key) {
            try {
                $response = Http::timeout(self::TIMEOUT_SECONDS)->get(
                    'https://maps.googleapis.com/maps/api/place/findplacefromtext/json',
                    [
                        'input' => $venue,
                        'inputtype' => 'textquery',
                        'fields' => 'place_id,name,formatted_address,geometry',
                        // 30km: a ground is local to the match, and a wider circle starts
                        // matching the next town's field of the same name.
                        'locationbias' => "circle:30000@{$lat},{$lng}",
                        'key' => $key,
                    ],
                );

                $candidate = data_get($response->json(), 'candidates.0');
                if (! is_array($candidate)) {
                    return null;
                }

                return [
                    'place_id' => (string) ($candidate['place_id'] ?? ''),
                    'name' => (string) ($candidate['name'] ?? ''),
                    'address' => (string) ($candidate['formatted_address'] ?? ''),
                    'lat' => isset($candidate['geometry']['location']['lat'])
                        ? (float) $candidate['geometry']['location']['lat'] : null,
                    'lng' => isset($candidate['geometry']['location']['lng'])
                        ? (float) $candidate['geometry']['location']['lng'] : null,
                ];
            } catch (\Throwable $e) {
                Log::warning('ground place lookup failed: ' . $e->getMessage(), ['venue' => $venue]);

                return null;
            }
        });
    }
}
