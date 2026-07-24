<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\HostProfile;
use App\Models\Venue;
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
    public function index(): Response
    {
        $xml = Cache::remember('sitemap.xml', now()->addHour(), fn (): string => $this->build());

        return response($xml, 200)->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    private function build(): string
    {
        /** @var list<array{loc: string, lastmod: ?string, priority: string, changefreq: string}> $urls */
        $urls = [];

        $add = static function (string $path, ?Carbon $lastmod, string $priority, string $changefreq) use (&$urls): void {
            $urls[] = [
                'loc' => url($path),
                'lastmod' => $lastmod?->toAtomString(),
                'priority' => $priority,
                'changefreq' => $changefreq,
            ];
        };

        // Landing pages.
        $add('/', null, '1.0', 'daily');
        $add('/events', null, '0.9', 'daily');
        $add('/gamehub', null, '0.9', 'daily');
        $add('/gamehub/leaderboard', null, '0.5', 'daily');

        // Published, not-yet-finished events (a past event page is a dead end for
        // searchers — it stays reachable, it just isn't worth re-crawling).
        Event::query()
            ->whereRaw('lower(status) = ?', ['published'])
            ->where(function ($q): void {
                $q->whereNull('date')->orWhere('date', '>=', now()->subDays(2));
            })
            ->orderByDesc('id')
            ->limit(5000)
            ->get(['id', 'updated_at'])
            ->each(fn (Event $e) => $add('/events/' . $e->id, $e->updated_at, '0.8', 'daily'));

        Venue::query()
            ->where('is_active', true)
            ->orderByDesc('id')
            ->limit(5000)
            ->get(['id', 'updated_at'])
            ->each(fn (Venue $v) => $add('/gamehub/' . $v->id, $v->updated_at, '0.7', 'weekly'));

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
