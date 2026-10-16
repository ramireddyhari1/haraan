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
        $xml = Cache::remember('sitemap.xml.v3', now()->addHour(), fn (): string => $this->build());

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

        // Curated hubs are indexable with or without listings (see SeoUrl), with the
        // sport pages the Kadapa push targeted.
        foreach (SeoUrl::CURATED_VENUE_CITIES as $citySlug) {
            $venueCities[$citySlug] ??= ['lastmod' => null, 'sports' => []];
            foreach (['cricket', 'box-cricket', 'badminton', 'football'] as $sportSlug) {
                $venueCities[$citySlug]['sports'][$sportSlug] = true;
            }
        }

        // "Turfs in <city>" + "<sport> turfs in <city>" — only where a real venue
        // backs the page (an empty one is noindex, so listing it would contradict it).
        foreach ($venueCities as $citySlug => $info) {
            $add('/venues/'.$citySlug, $info['lastmod'], '0.8', 'weekly');
            foreach (array_keys($info['sports']) as $sportSlug) {
                $add('/venues/'.$citySlug.'/'.$sportSlug, $info['lastmod'], '0.7', 'weekly');
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
