<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\HostProfile;
use App\Models\LiveMatch;
use App\Models\Venue;
use App\Support\SeoUrl;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * XML sitemap for haraan.app — the list of URLs we want Google to crawl.
 *
 * Generated on request (the catalogue is small) and cached for an hour, so a new
 * event is discoverable within the hour without a build step. Only genuinely
 * public, indexable pages belong here: anything behind auth, any filtered
 * duplicate, and any noindex page must stay out — listing them wastes crawl
 * budget and contradicts the meta robots tag.
 */
class SitemapController extends Controller
{
    /** Sport slugs PublicWebController::cityVenues() has a landing page for. */
    private const CITY_SPORTS = ['cricket', 'box-cricket', 'football', 'badminton', 'basketball', 'tennis', 'pickleball', 'volleyball'];

    public function index(): Response
    {
        $xml = Cache::remember('sitemap.xml.v5', now()->addHour(), fn (): string => $this->build());

        // Anonymous, identical for everyone — let crawlers and any CDN cache it for
        // an hour (matches the app-cache TTL above).
        return response($xml, 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    private function build(): string
    {
        /** @var list<array{loc: string, lastmod: ?string, priority: string, changefreq: string}> $urls */
        $urls = [];

        $add = static function (string $path, ?Carbon $lastmod, string $priority, string $changefreq) use (&$urls): void {
            $urls[] = [
                'loc' => str_starts_with($path, 'http') ? $path : url($path),
                'lastmod' => $lastmod?->toAtomString(),
                'priority' => $priority,
                'changefreq' => $changefreq,
            ];
        };

        // Landing pages.
        $add('/', null, '1.0', 'daily');
        $add('/events', null, '0.9', 'daily');
        $add('/gamehub', null, '0.9', 'daily');
        $add('/gamehub/actionboard', null, '0.8', 'daily');
        $add('/gamehub/leaderboard', null, '0.5', 'daily');
        // The partner console door — public + indexable (the console itself is not).
        $add('/partner/login', null, '0.4', 'monthly');

        // Public legal pages (compliance and trust signals).
        $add('/legal/terms', null, '0.3', 'monthly');
        $add('/legal/privacy', null, '0.3', 'monthly');

        // Venues: the same published() gate the detail page uses, so nothing listed
        // here 404s. Their cities and sports drive the local landing pages below.
        $venues = Venue::published()->orderByDesc('id')->limit(5000)->get();
        $venueCities = [];
        foreach ($venues as $v) {
            $add(SeoUrl::venue($v), $v->updated_at, '0.7', 'weekly');

            $city = trim((string) $v->city);
            if ($city === '') {
                continue;
            }
            $citySlug = SeoUrl::city($city);
            $venueCities[$citySlug] ??= ['lastmod' => null, 'sports' => []];
            $venueCities[$citySlug]['name'] ??= $city;
            if ($v->updated_at && ($venueCities[$citySlug]['lastmod'] === null || $v->updated_at->gt($venueCities[$citySlug]['lastmod']))) {
                $venueCities[$citySlug]['lastmod'] = $v->updated_at;
            }
            foreach ($v->sportsList() ?: [$v->category] as $sport) {
                $sportSlug = SeoUrl::city((string) $sport);
                if (in_array($sportSlug, self::CITY_SPORTS, true)) {
                    $venueCities[$citySlug]['sports'][$sportSlug] = true;
                    // Box cricket is played on cricket turfs — PublicWebController::cityVenues
                    // matches it against cricket venues, so it has listings wherever cricket does.
                    if ($sportSlug === 'cricket') {
                        $venueCities[$citySlug]['sports']['box-cricket'] = true;
                    }
                }
            }
        }

        // Listed venues (owner not on Haraan yet): only the pages that say where the place
        // is and what is played there — the rest are noindex, so they stay out of here too.
        if (\App\Services\VenueDiscovery\DiscoverySettings::publicPages() && \App\Support\PlatformRules::bool('venue_discovery.index_pages')) {
            $listedPerCity = [];
            foreach (Venue::listed()->orderByDesc('id')->limit(10000)->get() as $v) {
                if (! $v->isIndexableListing()) {
                    continue;
                }
                $add(SeoUrl::venue($v), $v->updated_at, '0.5', 'monthly');
                $city = trim((string) $v->city);
                if ($city !== '') {
                    $venueCities[SeoUrl::city($city)] ??= ['lastmod' => null, 'sports' => [], 'name' => $city];
                }
            }
            unset($listedPerCity);
        }

        // The state hub (/venues/andhra-pradesh) — every city with venues, linked.
        if (($stateSlug = \App\Support\CityVenueGuide::stateSlug()) !== null) {
            $add('/venues/'.$stateSlug, null, '0.8', 'weekly');
        }

        // City and sport pages, each only when it is indexable — the same rule the page's
        // own robots tag uses (CityVenueGuide::MIN_TO_INDEX real venues, bookable or listed),
        // so the sitemap never offers a page that tells Google not to index it. Curated
        // hubs (Kadapa) keep their city page either way.
        foreach (SeoUrl::CURATED_VENUE_CITIES as $citySlug) {
            $venueCities[$citySlug] ??= ['lastmod' => null, 'sports' => [], 'name' => ucfirst($citySlug)];
        }
        foreach ($venueCities as $citySlug => $info) {
            $name = (string) ($info['name'] ?? ucfirst($citySlug));
            $cityVenues = \App\Support\CityVenueGuide::venues($name);
            $stats = \App\Support\CityVenueGuide::stats($cityVenues);
            $curated = in_array($citySlug, SeoUrl::CURATED_VENUE_CITIES, true);
            // "Near {area}" pages — only the ones their own robots tag lets in.
            foreach (\App\Support\CityVenueGuide::nearPages($cityVenues) as $near) {
                if ($near['indexable']) {
                    $add('/venues/'.$citySlug.'/near/'.$near['slug'], $info['lastmod'], '0.6', 'weekly');
                }
            }
            if ($stats['total'] < \App\Support\CityVenueGuide::MIN_TO_INDEX && ! $curated) {
                continue;
            }
            $add('/venues/'.$citySlug, $info['lastmod'], '0.8', 'weekly');
            foreach ($stats['sports'] as $sportSlug => $n) {
                if ($n < \App\Support\CityVenueGuide::MIN_TO_INDEX) {
                    continue;
                }
                $add('/venues/'.$citySlug.'/'.$sportSlug, $info['lastmod'], '0.7', 'weekly');
                // Box cricket is played on cricket turfs — its page counts the same venues.
                if ($sportSlug === 'cricket') {
                    $add('/venues/'.$citySlug.'/box-cricket', $info['lastmod'], '0.7', 'weekly');
                }
            }
        }

        // Published, not-yet-finished events (a past event page is a dead end for
        // searchers — it stays reachable, it just isn't worth re-crawling).
        $events = Event::query()
            ->whereRaw('lower(status) = ?', ['published'])
            ->notFinished()
            ->orderByDesc('id')
            ->limit(5000)
            ->get(['id', 'title', 'city', 'updated_at']);
        $eventCities = [];
        foreach ($events as $e) {
            $add(SeoUrl::event($e), $e->updated_at, '0.8', 'daily');
            $city = trim((string) $e->city);
            if ($city !== '') {
                $eventCities[SeoUrl::city($city)] = true;
            }
        }

        // "Events in <city>" hubs — cities with something upcoming (the page itself
        // is noindex when only past events remain).
        foreach (array_keys($eventCities) as $citySlug) {
            $add('/events/in/'.$citySlug, null, '0.8', 'daily');
        }

        // ActionBoard matches the admin FEATURED — real fixtures ("Badvel Bulls vs
        // Jammalamadugu Lions"). Every public match is crawlable from the board, but
        // most are practice/test games ("test team 1 vs test team 2"); submitting
        // those would tell Google the site is thin. Private matches are noindex.
        LiveMatch::query()
            ->where('is_private', false)
            ->where('visibility', LiveMatch::VIS_FEATURED)
            ->orderByDesc('id')
            ->limit(3000)
            ->get(['id', 'updated_at'])
            ->each(fn (LiveMatch $m) => $add('/gamehub/actionboard/match/'.$m->id, $m->updated_at, '0.5', 'weekly'));

        // Organiser / venue-owner brand pages, live ones only (HostProfile::isLive()
        // is the same gate the route uses — a non-live slug 404s).
        HostProfile::query()
            ->orderByDesc('id')
            ->limit(2000)
            ->get()
            ->filter(fn (HostProfile $p) => $p->isLive())
            ->each(fn (HostProfile $p) => $add('/host/' . $p->slug, $p->updated_at, '0.6', 'weekly'));

        $body = '';
        foreach ($urls as $u) {
            $body .= "  <url>\n    <loc>" . htmlspecialchars($u['loc'], ENT_XML1) . "</loc>\n"
                . ($u['lastmod'] ? '    <lastmod>' . $u['lastmod'] . "</lastmod>\n" : '')
                . '    <changefreq>' . $u['changefreq'] . "</changefreq>\n"
                . '    <priority>' . $u['priority'] . "</priority>\n  </url>\n";
        }

        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            . "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n"
            . $body
            . "</urlset>\n";
    }
}
