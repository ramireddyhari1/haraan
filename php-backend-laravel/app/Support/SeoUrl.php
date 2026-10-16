<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Public, keyword-bearing URLs for event and venue pages:
 * /events/31-sunburn-arena-goa and /gamehub/5-green-turf-kadapa instead of bare ids.
 *
 * The leading number is the only thing the routes look up, so an old numeric link
 * (app shares, WhatsApp messages, Google's existing index) keeps working — the
 * detail actions 301 any non-canonical spelling to the one built here. The slug is
 * derived from the title ALONE so every caller (model, decorated card object or
 * search-result array) produces the identical URL; a link that differs from the
 * canonical would cost every visitor a redirect hop.
 */
final class SeoUrl
{
    /**
     * City venue hubs with hand-written local content (/venues/kadapa and its sport
     * pages). They stay indexed and in the sitemap even before a venue there is
     * published; other cities only once they have real listings.
     */
    public const CURATED_VENUE_CITIES = ['kadapa'];

    /** Route constraint for `{id}` segments that accept "31" or "31-some-slug". */
    public const SEGMENT = '[0-9]+(?:-[A-Za-z0-9-]*)?';

    public static function event(object|array $event): string
    {
        return url('/events/'.self::segment($event, 'title'));
    }

    public static function venue(object|array $venue): string
    {
        // Models carry `name`; the decorated Pulse cards and search arrays carry `title`.
        return url('/gamehub/'.self::segment($venue, 'name', 'title'));
    }

    /** "31-sunburn-arena-goa", or just "31" when the title has no Latin letters. */
    public static function segment(object|array $item, string ...$titleKeys): string
    {
        $id = (int) data_get($item, 'id');
        $title = '';
        foreach ($titleKeys as $key) {
            $title = trim((string) data_get($item, $key, ''));
            if ($title !== '') {
                break;
            }
        }

        $slug = self::slug($title);

        return $slug === '' ? (string) $id : $id.'-'.$slug;
    }

    /** The leading numeric id of a "31-some-slug" segment. */
    public static function id(string $segment): int
    {
        return (int) strtok($segment, '-');
    }

    public static function slug(string $text): string
    {
        $slug = Str::slug($text);
        if (strlen($slug) > 60) {
            // Cut at the last word boundary so a long title doesn't end mid-word.
            $cut = substr($slug, 0, 61);
            $slug = str_contains($cut, '-') ? substr($cut, 0, (int) strrpos($cut, '-')) : substr($slug, 0, 60);
        }

        return trim($slug, '-');
    }

    /** "Kadapa" → "kadapa", "Navi Mumbai" → "navi-mumbai". */
    public static function city(string $city): string
    {
        return Str::slug($city);
    }
}
