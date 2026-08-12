<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Event;
use App\Models\HiddenPlacePhoto;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The venue's own photos from its Google Maps listing — the interior, the stage,
 * the lighting. What a buyer means by "what's this place like".
 *
 * A map says where a venue is and Street View says what the wall outside looks
 * like; neither answers that question. The listing's photo set does, because it
 * is mostly shot by people who were actually inside.
 *
 * Three constraints shape this class:
 *
 *  1. **A place_id is the handle.** Photos hang off a Google listing, not off
 *     coordinates. Events picked before we stored one (or picked without the
 *     search box) are backfilled here, once, and the id is then permanent.
 *
 *  2. **Photo references expire; ids don't.** Google's terms allow storing a
 *     place_id indefinitely but cap caching of the content itself at 30 days,
 *     so the catalogue is cached to exactly that and never written to the
 *     event's own gallery.
 *
 *  3. **Every photo carries its author.** Google requires the contributor
 *     attribution to be displayed wherever the photo is, so `credit` travels
 *     with the photo and the view renders it. Don't drop it.
 */
final class PlacePhotos
{
    /** Google's caching ceiling for Maps content. */
    private const TTL_DAYS = 30;

    /** Failures are retried within the hour rather than cached as a verdict. */
    private const TTL_ERROR_MINUTES = 60;

    /** Most photo sets run long and get repetitive; the first few are the good ones. */
    private const MAX_PHOTOS = 6;

    /**
     * Below this the strip looks like an accident rather than a gallery, so the
     * section doesn't render at all.
     */
    private const MIN_PHOTOS = 3;

    private const TIMEOUT = 8;

    /** How far from the pin a text match may sit and still be the same venue. */
    private const BIAS_RADIUS_M = 2000.0;

    public static function key(): string
    {
        return trim((string) config('services.google_maps.server_key'));
    }

    public static function configured(): bool
    {
        return self::key() !== '';
    }

    /**
     * The venue photos to show for this event, already trimmed and credited.
     *
     * Returns [] — meaning "draw no section" — whenever we can't do it properly:
     * no key, no resolvable listing, or too few photos to look deliberate.
     *
     * @return list<array{index:int, credit:string, credit_uri:string}>
     */
    public static function forEvent(Event $event): array
    {
        if (! self::configured()) {
            return [];
        }

        $placeId = self::placeIdFor($event);
        if ($placeId === null) {
            return [];
        }

        if (self::blocked($placeId)) {
            return [];
        }

        $catalog = self::catalog($placeId);
        if (count($catalog) < self::MIN_PHOTOS) {
            return [];
        }

        $out = [];
        foreach (array_slice($catalog, 0, self::MAX_PHOTOS) as $i => $photo) {
            $out[] = [
                'index'      => $i,
                'credit'     => (string) ($photo['credit'] ?? ''),
                'credit_uri' => (string) ($photo['credit_uri'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * The Google photo reference behind one slot of the strip, for the proxy to
     * fetch. Null when the index is past the end or the catalogue has expired.
     */
    public static function photoName(Event $event, int $index): ?string
    {
        if (! self::configured() || $index < 0 || $index >= self::MAX_PHOTOS) {
            return null;
        }

        $placeId = self::placeIdFor($event);
        if ($placeId === null || self::blocked($placeId)) {
            return null;
        }

        return self::catalog($placeId)[$index]['name'] ?? null;
    }

    /**
     * Has an admin switched this venue's photos off?
     *
     * Checked on the image route as well as the page, so hiding actually takes
     * the photos away rather than just removing the strip that pointed at them —
     * a URL someone had already copied would otherwise keep working.
     *
     * Cached, because this runs on every event page and image request and the
     * answer changes about once a year. HiddenPlacePhoto flushes the key on
     * write, so a toggle takes effect immediately.
     */
    public static function blocked(string $placeId): bool
    {
        return (bool) Cache::remember(
            self::blockCacheKey($placeId),
            now()->addDay(),
            static fn (): bool => HiddenPlacePhoto::query()->where('place_id', $placeId)->exists()
        );
    }

    public static function blockCacheKey(string $placeId): string
    {
        return 'placephotos:blocked:' . $placeId;
    }

    /**
     * This event's Google listing id: the stored one when the host picked the
     * venue from the search box, otherwise resolved once from the venue text and
     * the pin, then persisted so it is never looked up twice.
     */
    public static function placeIdFor(Event $event): ?string
    {
        $stored = trim((string) ($event->place_id ?? ''));
        if ($stored !== '') {
            return $stored;
        }

        // A miss is cached too. Without this, an event whose venue Google simply
        // doesn't know would re-run a billable search on every single page view.
        $missKey = 'placephotos:noid:' . $event->getKey();
        if (Cache::get($missKey) !== null) {
            return null;
        }

        $resolved = self::resolve($event);
        if ($resolved === null) {
            Cache::put($missKey, true, now()->addDays(self::TTL_DAYS));

            return null;
        }

        // saveQuietly: this is a lookup being memoised, not an edit anyone made.
        // A normal save would fire the content-changed broadcast and tell every
        // client the event was updated.
        $event->place_id = $resolved['id'];
        $event->saveQuietly();

        // The search already returned the photos, so seed the catalogue from it
        // and the whole backfill costs exactly one billable call.
        if ($resolved['photos'] !== []) {
            Cache::put('placephotos:cat:' . $resolved['id'], $resolved['photos'], now()->addDays(self::TTL_DAYS));
        }

        return $resolved['id'];
    }

    /**
     * Find the Google listing for an event's venue by name, biased to the pin so
     * "Central" resolves to the one in this city rather than another state.
     *
     * @return array{id:string, photos:list<array{name:string,credit:string,credit_uri:string}>}|null
     */
    private static function resolve(Event $event): ?array
    {
        $query = trim(implode(' ', array_filter([
            trim((string) $event->venue),
            trim((string) ($event->location ?: '')),
            trim((string) ($event->city ?: '')),
        ])));

        // Without a venue name there's nothing to search for — coordinates alone
        // would just return whatever business is nearest, which is not the venue.
        if (trim((string) $event->venue) === '') {
            return null;
        }

        $body = ['textQuery' => $query, 'maxResultCount' => 1];

        if ($event->hasCoordinates()) {
            $body['locationBias'] = ['circle' => [
                'center' => ['latitude' => (float) $event->latitude, 'longitude' => (float) $event->longitude],
                'radius' => self::BIAS_RADIUS_M,
            ]];
        }

        try {
            $res = Http::timeout(self::TIMEOUT)
                ->withHeaders([
                    'X-Goog-Api-Key'   => self::key(),
                    'X-Goog-FieldMask' => 'places.id,places.displayName,places.photos',
                ])
                ->post('https://places.googleapis.com/v1/places:searchText', $body);

            if (! $res->successful()) {
                Log::warning('Place search failed', ['status' => $res->status()]);

                return null;
            }

            $place = $res->json('places.0');
            if (! is_array($place) || ! is_string($place['id'] ?? null)) {
                return null;
            }

            // Text search ALWAYS answers with its best guess, and near a pin its
            // best guess for a venue Google has never heard of is simply the
            // neighbouring business — a search for a made-up hall on Church
            // Street came back as a bar called Pixi. Publishing that would put a
            // stranger's interior on the page under our venue's name, which is
            // worse than showing nothing. So the name has to actually agree.
            $matched = (string) ($place['displayName']['text'] ?? '');
            if (! self::namesAgree((string) $event->venue, $matched)) {
                Log::info('Place match rejected', ['venue' => $event->venue, 'matched' => $matched]);

                return null;
            }

            return ['id' => $place['id'], 'photos' => self::normalise($place['photos'] ?? [])];
        } catch (Throwable $e) {
            Log::warning('Place search errored: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Is the listing Google returned plausibly the venue we asked about?
     *
     * Compares word sets rather than strings, because a real match is routinely
     * a superset ("The Comedy Theatre - Indiranagar" → "…, Bangalore") or
     * differently punctuated. Most of the venue's own words must appear in the
     * match; a near-miss like "Phoenix Marketcity" → "Phoenix Mall of Asia"
     * falls under the bar, and an unrelated business shares nothing at all.
     */
    private static function namesAgree(string $venue, string $candidate): bool
    {
        $tokens = static function (string $s): array {
            $s = mb_strtolower(trim($s));
            $s = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $s) ?? '';

            return array_values(array_unique(array_filter(
                explode(' ', $s),
                // One- and two-letter fragments carry no identity ("of", "at").
                static fn (string $t): bool => mb_strlen($t) > 2
            )));
        };

        $want = $tokens($venue);
        $got  = $tokens($candidate);

        // Nothing to compare on — refuse rather than guess.
        if ($want === [] || $got === []) {
            return false;
        }

        $shared = count(array_intersect($want, $got));

        return ($shared / count($want)) >= 0.6;
    }

    /**
     * The listing's photo references, cached for the full 30 days Google allows.
     *
     * @return list<array{name:string,credit:string,credit_uri:string}>
     */
    private static function catalog(string $placeId): array
    {
        $cacheKey = 'placephotos:cat:' . $placeId;

        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        try {
            $res = Http::timeout(self::TIMEOUT)
                ->withHeaders([
                    'X-Goog-Api-Key'   => self::key(),
                    'X-Goog-FieldMask' => 'photos',
                ])
                ->get('https://places.googleapis.com/v1/places/' . $placeId);

            if (! $res->successful()) {
                Cache::put($cacheKey, [], now()->addMinutes(self::TTL_ERROR_MINUTES));

                return [];
            }

            $photos = self::normalise($res->json('photos') ?? []);
            Cache::put($cacheKey, $photos, now()->addDays(self::TTL_DAYS));

            return $photos;
        } catch (Throwable $e) {
            Log::warning('Place photos errored: ' . $e->getMessage());
            Cache::put($cacheKey, [], now()->addMinutes(self::TTL_ERROR_MINUTES));

            return [];
        }
    }

    /**
     * Keep only what we actually render: the reference and who took it. The rest
     * of Google's payload is large and would bloat every cache entry.
     *
     * @param  mixed  $photos
     * @return list<array{name:string,credit:string,credit_uri:string}>
     */
    private static function normalise($photos): array
    {
        $out = [];

        foreach ((array) $photos as $photo) {
            if (! is_array($photo) || ! is_string($photo['name'] ?? null)) {
                continue;
            }

            $author = $photo['authorAttributions'][0] ?? [];

            $out[] = [
                'name'       => $photo['name'],
                'credit'     => trim((string) ($author['displayName'] ?? '')),
                'credit_uri' => trim((string) ($author['uri'] ?? '')),
            ];

            if (count($out) >= self::MAX_PHOTOS) {
                break;
            }
        }

        return $out;
    }

    /**
     * The image bytes for a photo reference, or null when Google won't serve it.
     *
     * The default size is deliberately small. Ask for more than the source and
     * Google hands back the original upload — which for these sets runs to
     * 600 KB+ each, six of them, on a phone. These cap the longest edge at
     * roughly 2× the tile so the strip stays sharp on a retina screen and still
     * weighs about a tenth of that.
     */
    public static function fetchMedia(string $photoName, int $maxHeight = 420, int $maxWidth = 760): ?string
    {
        if (! self::configured()) {
            return null;
        }

        try {
            $res = Http::timeout(self::TIMEOUT)
                ->get('https://places.googleapis.com/v1/' . $photoName . '/media', [
                    'maxHeightPx' => $maxHeight,
                    'maxWidthPx'  => $maxWidth,
                    'key'         => self::key(),
                ]);

            if (! $res->successful()) {
                return null;
            }

            $body = $res->body();

            return $body === '' ? null : $body;
        } catch (Throwable $e) {
            Log::warning('Place photo media errored: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * The content type of some image bytes, from its magic number.
     *
     * Places photos are NOT all JPEG — a good share of user uploads come back as
     * PNG, and screenshots sometimes as WebP. Serving those under a hardcoded
     * image/jpeg leaves the browser to sniff its way out, so read the real type
     * off the first bytes instead. Cheap enough to do per request, which means
     * the disk cache needs no second file to remember the format.
     */
    public static function mimeOf(string $bytes): string
    {
        $magic = substr($bytes, 0, 12);

        return match (true) {
            str_starts_with($magic, "\xFF\xD8\xFF")                                   => 'image/jpeg',
            str_starts_with($magic, "\x89PNG\r\n\x1A\n")                              => 'image/png',
            str_starts_with($magic, 'RIFF') && substr($magic, 8, 4) === 'WEBP'        => 'image/webp',
            str_starts_with($magic, 'GIF8')                                           => 'image/gif',
            default                                                                    => 'image/jpeg',
        };
    }

    public static function ttlDays(): int
    {
        return self::TTL_DAYS;
    }

    public static function maxPhotos(): int
    {
        return self::MAX_PHOTOS;
    }
}
