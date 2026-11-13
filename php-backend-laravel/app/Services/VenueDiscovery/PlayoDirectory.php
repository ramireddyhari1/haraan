<?php

declare(strict_types=1);

namespace App\Services\VenueDiscovery;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Which venues Playo lists in a city — used only to answer "is this place also on
 * Playo?" for a candidate found on Google.
 *
 * Two public pages, both open to crawlers in playo.co/robots.txt, each read at most
 * once a day: the venue sitemap (every venue URL, whose slug carries the name, area
 * and city) and the city page (its first venues, with map pins). Nothing of Playo's
 * is copied into Haraan: a match keeps the listing's name and link as evidence for
 * the reviewer, and no descriptions, prices, ratings or photos are read.
 */
final class PlayoDirectory
{
    private const SITEMAP = 'https://playo.co/sitemaps/sitemap.xml';

    private const TTL_HOURS = 24;

    /** Other spellings a city goes by in Playo's URLs. */
    private const ALIASES = [
        'visakhapatnam' => ['visakhapatnam', 'vizag', 'vishakhapatnam'],
        'rajahmundry' => ['rajahmundry', 'rajamahendravaram', 'rajamundry'],
        'kadapa' => ['kadapa', 'cuddapah'],
        'anantapur' => ['anantapur', 'anantapuramu', 'ananthapur'],
        'tirupati' => ['tirupati', 'tirupathi'],
        'vijayawada' => ['vijayawada', 'vijaywada'],
    ];

    /**
     * @return list<array{name:string, area:?string, url:string, lat:?float, lng:?float}>
     */
    public static function venuesIn(string $city): array
    {
        $key = 'venuediscovery:playo:city:' . Str::slug($city);

        $cached = Cache::get($key);
        if (is_array($cached)) {
            return $cached;
        }

        $byUrl = [];
        foreach (self::fromSitemap($city) as $v) {
            $byUrl[strtolower($v['url'])] = $v;
        }
        // The city page knows the pin and the real spelling — let it win over the slug.
        foreach (self::fromCityPage($city) as $v) {
            $byUrl[strtolower($v['url'])] = $v;
        }

        $venues = array_values($byUrl);
        // An empty answer is cached briefly: it may be a blip, and a day of "not on Playo"
        // for a whole city would mislead every review.
        Cache::put($key, $venues, $venues === [] ? now()->addMinutes(20) : now()->addHours(self::TTL_HOURS));

        return $venues;
    }

    /** @return list<string> */
    public static function slugsFor(string $city): array
    {
        $slug = Str::slug($city);

        return self::ALIASES[$slug] ?? [$slug];
    }

    /** @return list<array{name:string, area:?string, url:string, lat:?float, lng:?float}> */
    private static function fromSitemap(string $city): array
    {
        $urls = Cache::remember('venuediscovery:playo:sitemap', now()->addHours(self::TTL_HOURS), function (): array {
            try {
                $res = self::client()->get(self::SITEMAP);
                if (! $res->successful()) {
                    Log::warning('Playo sitemap fetch failed', ['status' => $res->status()]);

                    return [];
                }
                preg_match_all('#<loc>\s*(https://playo\.co/venues/[^<\s]+/[^<\s]+)\s*</loc>#i', $res->body(), $m);

                return array_values(array_unique($m[1] ?? []));
            } catch (Throwable $e) {
                Log::warning('Playo sitemap errored: ' . $e->getMessage());

                return [];
            }
        });
        if ($urls === []) {
            Cache::forget('venuediscovery:playo:sitemap');
        }

        $out = [];
        foreach ($urls as $url) {
            $path = explode('/', (string) parse_url($url, PHP_URL_PATH));
            // /venues/{Area}/{name-area-city}
            if (count($path) < 4) {
                continue;
            }
            $area = str_replace('-', ' ', urldecode($path[2]));
            $slug = strtolower(urldecode($path[3]));

            foreach (self::slugsFor($city) as $citySlug) {
                if (! str_ends_with($slug, '-' . $citySlug)) {
                    continue;
                }
                $name = substr($slug, 0, -strlen('-' . $citySlug));
                // The area is appended to the slug (sometimes twice) — take it back off.
                $areaSlug = Str::slug($area);
                while ($areaSlug !== '' && str_ends_with($name, '-' . $areaSlug)) {
                    $name = substr($name, 0, -strlen('-' . $areaSlug));
                }
                $name = trim(str_replace('-', ' ', $name));
                if ($name !== '') {
                    $out[] = ['name' => Str::title($name), 'area' => trim($area) ?: null, 'url' => $url, 'lat' => null, 'lng' => null];
                }
                break;
            }
        }

        return $out;
    }

    /** @return list<array{name:string, area:?string, url:string, lat:?float, lng:?float}> */
    private static function fromCityPage(string $city): array
    {
        foreach (self::slugsFor($city) as $citySlug) {
            try {
                $res = self::client()->get('https://playo.co/venues/' . $citySlug);
                if (! $res->successful()) {
                    continue;
                }
                if (! preg_match('#<script id="__NEXT_DATA__"[^>]*>(.*?)</script>#s', $res->body(), $m)) {
                    continue;
                }
                $list = data_get(json_decode($m[1], true), 'props.pageProps.venuesList');
                if (! is_array($list) || $list === []) {
                    continue;
                }

                $out = [];
                foreach ($list as $v) {
                    $name = trim((string) ($v['name'] ?? ''));
                    $key = trim((string) ($v['activeKey'] ?? ''));
                    $area = trim((string) ($v['area'] ?? ''));
                    // The page falls back to other cities when it has little — keep this one's.
                    $theirCity = Str::slug((string) ($v['city'] ?? ''));
                    if ($name === '' || $key === '' || ! in_array($theirCity, self::slugsFor($city), true)) {
                        continue;
                    }
                    $coords = $v['geoLocation']['coordinates'] ?? null;
                    $out[] = [
                        'name' => $name,
                        'area' => $area ?: null,
                        'url' => 'https://playo.co/venues/' . str_replace(' ', '-', $area ?: $citySlug) . '/' . $key,
                        'lat' => is_array($coords) && isset($coords[1]) ? (float) $coords[1] : null,
                        'lng' => is_array($coords) && isset($coords[0]) ? (float) $coords[0] : null,
                    ];
                }

                return $out;
            } catch (Throwable $e) {
                Log::warning('Playo city page errored: ' . $e->getMessage());
            }
        }

        return [];
    }

    private static function client(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::timeout(25)->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (compatible; venue-directory-check/1.0)',
            'Accept' => 'text/html,application/xml',
        ]);
    }
}
