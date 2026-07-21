<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\LiveMatch;

/**
 * Ranks the ActionBoard feed for a given viewer position: "starred first, then
 * nearest, then live, then fresh".
 *
 * Proximity is deliberately HYBRID. Matches created from 2026-07-21 carry a real
 * GPS fix (the app demands one), so those can be measured in kilometres. Every
 * match created before that has only place *names*, and no amount of wishing will
 * give it coordinates. Rather than bury the old ones or ignore the new precision,
 * both are folded into the same coarse bucket, with true distance used as the
 * tiebreak wherever both sides actually have it.
 */
final class MatchProximity
{
    /** Bucket boundaries in km, paired with the text tier they correspond to. */
    private const NEAR_KM = 5.0;     // ~same village/neighbourhood
    private const DISTRICT_KM = 40.0;  // ~same district
    private const REGION_KM = 150.0; // ~same state region

    public const BUCKET_HERE = 0;
    public const BUCKET_DISTRICT = 1;
    public const BUCKET_REGION = 2;
    public const BUCKET_FAR = 3;

    public function __construct(
        private readonly ?float $latitude = null,
        private readonly ?float $longitude = null,
        private readonly string $locality = '',
        private readonly string $district = '',
        private readonly string $state = '',
    ) {
    }

    /** True when we know anything at all about where the viewer is. */
    public function isKnown(): bool
    {
        return ($this->latitude !== null && $this->longitude !== null)
            || $this->locality !== ''
            || $this->district !== '';
    }

    /**
     * Great-circle distance in km, or null when either side lacks a fix.
     */
    public function distanceKm(LiveMatch $match): ?float
    {
        if ($this->latitude === null || $this->longitude === null) {
            return null;
        }
        if ($match->latitude === null || $match->longitude === null) {
            return null;
        }

        $earthKm = 6371.0;
        $lat1 = deg2rad((float) $this->latitude);
        $lat2 = deg2rad((float) $match->latitude);
        $dLat = $lat2 - $lat1;
        $dLon = deg2rad((float) $match->longitude - (float) $this->longitude);

        $a = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLon / 2) ** 2;

        return $earthKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Coarse proximity bucket. Uses measured distance when available and falls
     * back to name matching otherwise, so a legacy match with a known village
     * still ranks alongside a modern one 3km away.
     */
    public function bucket(LiveMatch $match): int
    {
        $km = $this->distanceKm($match);
        if ($km !== null) {
            return match (true) {
                $km <= self::NEAR_KM => self::BUCKET_HERE,
                $km <= self::DISTRICT_KM => self::BUCKET_DISTRICT,
                $km <= self::REGION_KM => self::BUCKET_REGION,
                default => self::BUCKET_FAR,
            };
        }

        if ($this->locality !== '' && $this->matches($this->locality, (string) $match->locality)) {
            return self::BUCKET_HERE;
        }
        if ($this->district !== '' && $this->matches($this->district, (string) $match->district)) {
            return self::BUCKET_DISTRICT;
        }
        if ($this->state !== '' && $this->matches($this->state, (string) $match->state)) {
            return self::BUCKET_REGION;
        }

        return self::BUCKET_FAR;
    }

    /**
     * The full sort key, most significant first. Sorting an array of matches by
     * this ascending produces the feed order.
     *
     * @return array<int, float|int>
     */
    public function sortKey(LiveMatch $match): array
    {
        $status = strtolower((string) $match->status);

        return [
            // 1. Admin-starred matches always lead — that's what featuring means.
            (string) $match->visibility === LiveMatch::VIS_FEATURED ? 0 : 1,
            // 2. Finished matches sink: a completed game nearby should never
            //    outrank one being played right now.
            $status === 'completed' ? 1 : 0,
            // 3. How close it is.
            $this->bucket($match),
            // 4. Live before scheduled, within the same bucket.
            $status === 'live' ? 0 : 1,
            // 5. Measured distance as a fine tiebreak; unmeasurable sorts after.
            $this->distanceKm($match) ?? PHP_FLOAT_MAX,
            // 6. Freshest last resort.
            -(int) ($match->updated_at?->getTimestamp() ?? 0),
        ];
    }

    /**
     * Order a collection of matches by this viewer's position.
     *
     * @template T of \Illuminate\Support\Collection<int, LiveMatch>
     * @param  T  $matches
     * @return T
     */
    public function sort($matches)
    {
        return $matches->sortBy(fn (LiveMatch $m): array => $this->sortKey($m))->values();
    }

    /** Loose place-name comparison — casing and stray spacing shouldn't matter. */
    private function matches(string $a, string $b): bool
    {
        $norm = static fn (string $s): string => preg_replace('/\s+/u', ' ', trim(mb_strtolower($s))) ?? '';

        $a = $norm($a);
        $b = $norm($b);

        return $a !== '' && $a === $b;
    }
}
