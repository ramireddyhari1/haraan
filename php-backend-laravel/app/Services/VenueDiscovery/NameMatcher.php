<?php

declare(strict_types=1);

namespace App\Services\VenueDiscovery;

/**
 * Decides whether two listings are the same sports venue.
 *
 * Venue names are mostly generic words — "Sky Box Cricket", "Turf Play Arena" — so a
 * plain word overlap matches half a city. The words that identify a place are the ones
 * left after the sport and venue-type words are set aside ("sky"), and the pin: two
 * listings a few hundred metres apart that share an identifying word are one venue.
 */
final class NameMatcher
{
    /** Words every venue name uses; they say what kind of place it is, not which one. */
    private const GENERIC = [
        'the', 'and', 'sports', 'sport', 'arena', 'turf', 'turfs', 'box', 'cricket', 'football', 'futsal',
        'badminton', 'pickleball', 'tennis', 'basketball', 'volleyball', 'court', 'courts', 'academy',
        'club', 'ground', 'grounds', 'indoor', 'outdoor', 'stadium', 'play', 'zone', 'center', 'centre',
        'complex', 'nets', 'net', 'cafe', 'hub', 'park', 'games', 'game',
        'pickle', 'ball', 'shuttle', 'basket', 'volley', 'foot', 'paddle',
    ];

    /** Metres within which two pins can be the same building. */
    private const NEAR_M = 450.0;

    /**
     * @param  array{name:string, lat?:?float, lng?:?float}  $a
     * @param  array{name:string, lat?:?float, lng?:?float}  $b
     * @param  list<string>  $placeWords  city and area words to ignore ("vijayawada", "kanuru")
     */
    public static function same(array $a, array $b, array $placeWords = []): bool
    {
        $ta = self::tokens($a['name']);
        $tb = self::tokens($b['name']);
        if ($ta === [] || $tb === []) {
            return false;
        }

        $ignore = array_merge(self::GENERIC, array_map('mb_strtolower', $placeWords));
        $da = array_values(array_diff($ta, $ignore));
        $db = array_values(array_diff($tb, $ignore));

        $sharedAll = self::shared($ta, $tb);
        $sharedOwn = self::shared($da, $db);
        $overlap = $sharedAll / max(1, min(count($ta), count($tb)));

        $metres = self::metres($a, $b);
        if ($metres !== null) {
            if ($metres > 3000) {
                return false; // Same name across town is a branch or a coincidence, not this place.
            }
            // Next door and sharing an identifying word — or, when neither name has one
            // ("Pickleball Arena"), the same generic name. Generic words alone never match
            // two named venues: every badminton hall shares "badminton".
            $bothGeneric = $da === [] && $db === [];
            if ($metres <= self::NEAR_M && ($sharedOwn >= 1 || ($bothGeneric && $overlap >= 0.75))) {
                return true;
            }
        }

        // No pin to lean on (or a sloppy one): the identifying words have to carry it.
        $own = min(count($da), count($db));
        if ($own === 0) {
            return false;
        }
        if ($sharedOwn / $own < 0.75) {
            return false;
        }

        // A single shared word ("sky") is thin — ask the rest of the name to agree too.
        return $sharedOwn >= 2 || $overlap >= 0.6;
    }

    /**
     * The words that identify this venue: its name without sport, venue-type and place words.
     *
     * @param  list<string>  $placeWords
     * @return list<string>
     */
    public static function distinctive(string $name, array $placeWords = []): array
    {
        return array_values(array_diff(
            self::tokens($name),
            self::GENERIC,
            array_map('mb_strtolower', $placeWords)
        ));
    }

    /** @return list<string> */
    public static function tokens(string $name): array
    {
        $s = mb_strtolower(trim($name));
        $s = str_replace(['&', "'", '’'], [' and ', '', ''], $s);
        $s = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $s) ?? '';

        return array_values(array_unique(array_filter(
            explode(' ', $s),
            static fn (string $t): bool => mb_strlen($t) > 2 || ctype_digit($t)
        )));
    }

    /** Count of words in $a that $b also has, allowing one typo in longer words. */
    private static function shared(array $a, array $b): int
    {
        $n = 0;
        foreach ($a as $x) {
            foreach ($b as $y) {
                if ($x === $y || (strlen($x) >= 5 && strlen($y) >= 5 && levenshtein($x, $y) <= 1)) {
                    $n++;

                    continue 2;
                }
            }
        }

        return $n;
    }

    /** @return ?float metres between the two pins, or null when either has none */
    public static function metres(array $a, array $b): ?float
    {
        foreach ([$a, $b] as $p) {
            if (! isset($p['lat'], $p['lng']) || ! is_numeric($p['lat']) || ! is_numeric($p['lng'])) {
                return null;
            }
        }

        $lat1 = deg2rad((float) $a['lat']);
        $lat2 = deg2rad((float) $b['lat']);
        $dLat = $lat2 - $lat1;
        $dLng = deg2rad((float) $b['lng'] - (float) $a['lng']);
        $h = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLng / 2) ** 2;

        return 6371000.0 * 2 * asin(min(1.0, sqrt($h)));
    }
}
