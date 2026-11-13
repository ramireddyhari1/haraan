<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Venue;
use App\Services\VenueDiscovery\DiscoverySettings;
use App\Support\CityVenueGuide;
use App\Support\SeoUrl;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;

/**
 * /venues/andhra-pradesh — the state hub: every city with venues on Haraan, how many,
 * which sports, each linking to its city and sport pages. The page search engines and
 * people land on for "sports venues in Andhra Pradesh", and the one link that ties the
 * city pages together. The state is the one set in /control → Discovery settings.
 */
final class StateVenuesController extends Controller
{
    public function show(): View
    {
        $state = DiscoverySettings::state();
        $stateSlug = (string) CityVenueGuide::stateSlug();

        $cities = Cache::remember('cityguide:state:' . $stateSlug, now()->addMinutes(10), function () use ($state): array {
            // The discovery cities, plus any other town in the state with a bookable venue.
            $names = collect(DiscoverySettings::cities());
            $names = $names->merge(
                Venue::published()->where('address', 'like', '%' . $state . '%')->pluck('city')->filter()
            )->map(fn ($c) => trim((string) $c))->filter()->unique(fn ($c) => mb_strtolower($c));

            $rows = [];
            foreach ($names as $city) {
                $stats = CityVenueGuide::stats(CityVenueGuide::venues($city));
                if ($stats['total'] === 0) {
                    continue;
                }
                $rows[] = ['name' => $city, 'slug' => SeoUrl::city($city), 'stats' => $stats];
            }
            usort($rows, fn ($a, $b) => $b['stats']['total'] <=> $a['stats']['total']);

            return $rows;
        });

        $total = array_sum(array_map(fn ($c) => $c['stats']['total'], $cities));
        $bookable = array_sum(array_map(fn ($c) => $c['stats']['bookable'], $cities));
        $sports = [];
        foreach ($cities as $c) {
            foreach ($c['stats']['sports'] as $slug => $n) {
                $sports[$slug] = ($sports[$slug] ?? 0) + $n;
            }
        }
        arsort($sports);

        $url = url('/venues/' . $stateSlug);
        $cityWords = implode(', ', array_slice(array_column($cities, 'name'), 0, 4));
        $sportWords = implode(', ', array_map(fn ($s) => CityVenueGuide::SPORTS[$s][0], array_slice(array_keys($sports), 0, 4)));

        $faqs = $total === 0 ? [] : [
            [
                'question' => 'Which cities in ' . $state . ' have the most sports venues?',
                'answer' => implode(', ', array_map(fn ($c) => $c['name'] . ' (' . $c['stats']['total'] . ')', array_slice($cities, 0, 5))) . '.',
            ],
            [
                'question' => 'Which sports can I play in ' . $state . '?',
                'answer' => 'Haraan lists venues for ' . implode(', ', array_map(fn ($s, $n) => strtolower(CityVenueGuide::SPORTS[$s][0]) . ' (' . $n . ')', array_keys($sports), $sports)) . '.',
            ],
            [
                'question' => 'Can I book these venues online?',
                'answer' => $bookable > 0
                    ? $bookable . ' take bookings on Haraan. The rest show their address, timings, phone and directions, with an “Ask to book” button that tells the owner you want to play there.'
                    : 'Not on Haraan yet. Each venue shows its address, timings, phone and directions, with an “Ask to book” button that tells the owner you want to play there.',
            ],
        ];

        $jsonLd = [[
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => 'Sports venues in ' . $state . ' by city',
            'url' => $url,
            'itemListElement' => array_map(fn ($c, $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'url' => url('/venues/' . $c['slug']),
                'name' => 'Sports venues in ' . $c['name'],
            ], $cities, array_keys($cities)),
        ]];
        if ($faqs) {
            $jsonLd[] = [
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => array_map(fn ($f) => [
                    '@type' => 'Question',
                    'name' => $f['question'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['answer']],
                ], $faqs),
            ];
        }

        return view('site.state-venues', [
            'state' => $state,
            'cities' => $cities,
            'total' => $total,
            'bookable' => $bookable,
            'sports' => $sports,
            'faqs' => $faqs,
            'title' => $total . ' Sports Venues in ' . $state . ' — ' . count($cities) . ' Cities' . ($sportWords !== '' ? ', ' . $sportWords : ''),
            'seo' => [
                'title' => $total . ' Sports Venues in ' . $state . ' — ' . count($cities) . ' Cities' . ($sportWords !== '' ? ', ' . $sportWords : '') . ' | Haraan',
                'description' => $total . ' turfs, courts and grounds across ' . count($cities) . ' cities in ' . $state
                    . ($cityWords !== '' ? ' — ' . $cityWords . ' and more' : '')
                    . '. Addresses, timings, photos and directions for every venue' . ($bookable > 0 ? ', and online booking where it’s on Haraan.' : '.'),
                'canonical' => $url,
                'robots' => $total >= CityVenueGuide::MIN_TO_INDEX ? 'index,follow,max-image-preview:large' : 'noindex,follow',
                'breadcrumbs' => [
                    ['name' => 'Haraan', 'url' => url('/')],
                    ['name' => 'Pulse Venues', 'url' => route('site.gamehub')],
                    ['name' => $state],
                ],
                'jsonld' => $jsonLd,
            ],
        ]);
    }
}
