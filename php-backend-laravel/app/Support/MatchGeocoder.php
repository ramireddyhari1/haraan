<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\LiveMatch;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Turns free-form place *names* into coordinates via the Google Maps Geocoding
 * API, cached for 30 days (places don't move). Its whole reason to exist is the
 * ActionBoard: most live matches were created without a GPS fix and carry only a
 * locality/district/state, yet both the website and the app want to rank them by
 * true km distance. Geocoding those names — in memory, never persisted — gives
 * {@see MatchProximity} something real to measure against the viewer.
 *
 * Shared by the web ActionBoard (PublicWebController) and the app feed
 * (Api\LiveMatchController) so the two stay in lockstep.
 */
final class MatchGeocoder
{
    /**
     * Fill a match's coordinates from its place names (locality → district →
     * state), in memory only. No-op when it already has a real GPS fix or has no
     * usable place name, so a modern GPS-stamped match is never overwritten.
     */
    public function ensureCoords(LiveMatch $m): void
    {
        if ($m->latitude !== null && $m->longitude !== null) {
            return;
        }
        $place = trim(implode(', ', array_filter([
            trim((string) ($m->locality ?? '')),
            trim((string) ($m->district ?? '')),
            trim((string) ($m->state ?? '')),
        ])));
        if ($place === '') {
            return;
        }
        [$lat, $lng] = $this->geocode($place . ', India');
        if ($lat !== null && $lng !== null) {
            $m->latitude = $lat;
            $m->longitude = $lng;
        }
    }

    /**
     * Geocode a free-form address to [lat, lng], cached 30 days. Returns
     * [null, null] on any failure or when no API key is configured, so callers
     * silently fall back to name-tier proximity.
     *
     * @return array{0: ?float, 1: ?float}
     */
    public function geocode(string $address): array
    {
        $address = trim($address);
        $key = (string) config('services.google_maps.key');
        if ($address === '' || $key === '') {
            return [null, null];
        }

        return Cache::remember(
            'geocode:' . md5(mb_strtolower($address)),
            now()->addDays(30),
            function () use ($address, $key): array {
                try {
                    $resp = Http::timeout(4)
                        ->get('https://maps.googleapis.com/maps/api/geocode/json', [
                            'address' => $address,
                            'key' => $key,
                        ]);
                    $loc = $resp->json('results.0.geometry.location');
                    if (is_array($loc) && isset($loc['lat'], $loc['lng'])) {
                        return [(float) $loc['lat'], (float) $loc['lng']];
                    }
                } catch (\Throwable $e) {
                    // fall through to the null fix
                }

                return [null, null];
            }
        );
    }
}
