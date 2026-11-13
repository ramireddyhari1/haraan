<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\VenueDiscovery\DiscoverySettings;
use App\Support\CityResolver;
use App\Support\CityVenueGuide;
use App\Support\SeoUrl;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * /venues/{city}/near/{area} — "Turfs & courts near Benz Circle, Vijayawada": every venue
 * Haraan shows within CityVenueGuide::NEAR_KM of that area, nearest first, with the
 * distance. People search a neighbourhood, not a city ("box cricket near Benz Circle"),
 * and few sites answer that, so these are the searches a new site can win first.
 *
 * Areas and pins are the venues' own; nothing is invented. A page with too few venues,
 * or one that lists exactly what a larger area already lists, stays out of search
 * ({@see CityVenueGuide::nearPages()}).
 */
final class NearVenuesController extends Controller
{
    public function show(string $city, string $area): View|RedirectResponse
    {
        $cityName = CityResolver::detect(trim(urldecode($city))) ?: ucfirst(trim(urldecode($city)));
        $citySlug = SeoUrl::city($cityName);
        if ($citySlug !== $city) {
            return redirect()->to(url('/venues/' . $citySlug . '/near/' . $area), 301);
        }

        $venues = CityVenueGuide::venues($cityName);
        $pages = CityVenueGuide::nearPages($venues);
        $page = $pages[$area] ?? null;
        abort_if($page === null || $page['venues'] === [], 404);

        $copy = CityVenueGuide::nearCopy($cityName, $page);
        $canonical = url('/venues/' . $citySlug . '/near/' . $page['slug']);

        $crumbs = [
            ['name' => 'Haraan', 'url' => url('/')],
            ['name' => 'Pulse Venues', 'url' => route('site.gamehub')],
        ];
        if (($stateSlug = CityVenueGuide::stateSlug()) !== null && CityVenueGuide::inState($cityName)) {
            $crumbs[] = ['name' => DiscoverySettings::state(), 'url' => url('/venues/' . $stateSlug)];
        }
        $crumbs[] = ['name' => $cityName, 'url' => url('/venues/' . $citySlug)];
        $crumbs[] = ['name' => 'Near ' . $page['name']];

        // Other areas of the city worth a page, nearest to this one first.
        $others = collect($pages)
            ->filter(fn (array $p) => $p['indexable'] && $p['slug'] !== $page['slug'])
            ->sortBy(fn (array $p) => CityVenueGuide::km($page['lat'], $page['lng'], $p['lat'], $p['lng']))
            ->take(10)
            ->map(fn (array $p) => ['name' => $p['name'], 'url' => url('/venues/' . $citySlug . '/near/' . $p['slug']), 'count' => count($p['venues'])])
            ->values()
            ->all();

        $stats = CityVenueGuide::stats(collect($page['venues']));
        $sportLinks = [];
        foreach (array_keys($stats['sports']) as $slug) {
            $sportLinks[] = ['label' => CityVenueGuide::SPORTS[$slug][0] . ' in ' . $cityName, 'url' => url('/venues/' . $citySlug . '/' . $slug)];
        }

        $jsonld = CityVenueGuide::jsonLd($canonical, $copy['h1'], collect($page['venues']), $copy['faqs']);

        return view('site.venue-near', [
            'title' => preg_replace('/\s*\|\s*Haraan$/', '', $copy['title']),
            'cityName' => $cityName,
            'citySlug' => $citySlug,
            'page' => $page,
            'copy' => $copy,
            'others' => $others,
            'sportLinks' => $sportLinks,
            'seo' => [
                'title' => $copy['title'],
                'description' => $copy['description'],
                'canonical' => $canonical,
                'robots' => $page['indexable'] ? 'index,follow,max-image-preview:large' : 'noindex,follow',
                'breadcrumbs' => $crumbs,
                'jsonld' => $jsonld,
            ],
        ]);
    }
}
