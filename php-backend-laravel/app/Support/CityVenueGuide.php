<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Venue;
use App\Services\VenueDiscovery\DiscoverySettings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * What Haraan actually knows about playing sport in a city — read from the venues it
 * shows (bookable ones and listed ones), never invented — and the search copy built
 * from it: title, description, the opening line, the areas, the questions people ask.
 *
 * Every city page used the same sentence with the city name swapped in ("Turfs in X —
 * Book Cricket, Box Cricket & Badminton Courts"), even where nothing could be booked.
 * Search engines file pages like that as duplicates. Here each page says what is true for
 * that city: how many venues, which sports, which areas, who opens early, what can be
 * booked online.
 */
final class CityVenueGuide
{
    /** Sport pages under /venues/{city}/{sport}: slug => label, and the sport each counts. */
    public const SPORTS = [
        'cricket' => ['Cricket', 'Cricket'],
        'box-cricket' => ['Box Cricket', 'Cricket'],
        'football' => ['Football', 'Football'],
        'badminton' => ['Badminton', 'Badminton'],
        'pickleball' => ['Pickleball', 'Pickleball'],
        'tennis' => ['Tennis', 'Tennis'],
        'basketball' => ['Basketball', 'Basketball'],
        'volleyball' => ['Volleyball', 'Volleyball'],
    ];

    /** Below this many real venues a page says too little to offer to search. */
    public const MIN_TO_INDEX = 3;

    /** What a venue of each sport is called, for titles and copy. */
    private const NOUN = [
        'Cricket' => ['cricket ground', 'cricket grounds'],
        'Box Cricket' => ['box cricket turf', 'box cricket turfs'],
        'Football' => ['football turf', 'football turfs'],
        'Badminton' => ['badminton court', 'badminton courts'],
        'Pickleball' => ['pickleball court', 'pickleball courts'],
        'Tennis' => ['tennis court', 'tennis courts'],
        'Basketball' => ['basketball court', 'basketball courts'],
        'Volleyball' => ['volleyball court', 'volleyball courts'],
    ];

    /**
     * Every venue shown in a city, bookable first, as plain rows.
     *
     * @return Collection<int, array{id:int,name:string,area:string,sports:list<string>,bookable:bool,url:string,open:?int,close:?int,price:int}>
     */
    public static function venues(string $city): Collection
    {
        $key = 'cityguide:venues:v2:' . Str::slug($city);

        // Cached as a plain array: the cache store refuses to rebuild objects it didn't expect.
        return collect(Cache::remember($key, now()->addMinutes(10), function () use ($city): array {
            $rows = collect();
            $lc = mb_strtolower($city);

            $bookable = Venue::published()
                // The venue's own city; one with no city falls back to its area text. Never
                // the address — "Eluru Road" is a street in Vijayawada.
                ->where(fn ($q) => $q->whereRaw('lower(city) = ?', [$lc])
                    ->orWhere(fn ($n) => $n->where(fn ($e) => $e->whereNull('city')->orWhere('city', ''))
                        ->where('location', 'like', "%{$city}%")))
                ->get();
            foreach ($bookable as $v) {
                $rows->push(self::row($v, true, $city));
            }

            if (DiscoverySettings::publicPages()) {
                foreach (Venue::listed()->whereRaw('lower(city) = ?', [$lc])->orderBy('name')->get() as $v) {
                    $rows->push(self::row($v, false, $city));
                }
            }

            return $rows->unique('id')->values()->all();
        }));
    }

    /** Venues of one sport page (null = all), with Box Cricket counting cricket venues. */
    public static function forSport(Collection $venues, ?string $sportSlug): Collection
    {
        if ($sportSlug === null || ! isset(self::SPORTS[$sportSlug])) {
            return $venues;
        }
        $sport = self::SPORTS[$sportSlug][1];

        return $venues->filter(fn (array $v) => in_array($sport, $v['sports'], true))->values();
    }

    /**
     * Numbers for a city or one of its sport pages.
     *
     * @return array{total:int,bookable:int,sports:array<string,int>,areas:array<string,list<array>>,early:list<array>,late:list<array>,allDay:list<array>,fromPrice:?int}
     */
    public static function stats(Collection $venues): array
    {
        $sports = [];
        foreach (self::SPORTS as $slug => [$label, $sport]) {
            if ($slug === 'box-cricket') {
                continue;
            }
            $n = $venues->filter(fn (array $v) => in_array($sport, $v['sports'], true))->count();
            if ($n > 0) {
                $sports[$slug] = $n;
            }
        }
        arsort($sports);

        $areas = $venues->filter(fn (array $v) => $v['area'] !== '')
            ->groupBy('area')
            ->sortByDesc(fn (Collection $g) => $g->count())
            ->map(fn (Collection $g) => $g->values()->all())
            ->all();

        $prices = $venues->where('bookable', true)->pluck('price')->filter(fn ($p) => $p > 0);

        return [
            'total' => $venues->count(),
            'bookable' => $venues->where('bookable', true)->count(),
            'sports' => $sports,
            'areas' => $areas,
            'early' => $venues->filter(fn ($v) => $v['open'] !== null && $v['open'] !== $v['close'] && $v['open'] <= 6 * 60)->values()->all(),
            'late' => $venues->filter(fn ($v) => $v['close'] !== null && $v['open'] !== $v['close'] && ($v['close'] >= 22 * 60 || $v['close'] < $v['open']))->values()->all(),
            'allDay' => $venues->filter(fn ($v) => $v['open'] !== null && $v['open'] === $v['close'])->values()->all(),
            'fromPrice' => $prices->isEmpty() ? null : (int) $prices->min(),
        ];
    }

    /**
     * Title, description, H1 subject and opening line for a city (or sport) page.
     *
     * @return array{title:string,description:string,h1:string,lead:string}
     */
    public static function copy(string $city, ?string $sportSlug, array $s): array
    {
        $label = $sportSlug ? self::SPORTS[$sportSlug][0] : null;
        [$one, $many] = $label ? self::NOUN[$label] : ['sports venue', 'sports venues'];
        $n = $s['total'];
        $noun = $n === 1 ? $one : $many;
        $topAreas = array_slice(array_keys($s['areas']), 0, 3);
        $sportWords = self::sportWords($s['sports'], $label === null ? 3 : 0);
        // The snippet has room for two areas next to the sports, three on a sport page.
        $descAreas = array_slice($topAreas, 0, $label === null ? 2 : 3);

        // "Turfs & courts in Vijayawada" is what people type; "sports venues" is not.
        $h1 = $label ? ucfirst($many) . ' in ' . $city : 'Turfs & courts in ' . $city;

        if ($n === 0) {
            return [
                'title' => ucwords($many) . ' in ' . $city . ' | Haraan',
                'description' => 'Haraan doesn’t list any ' . $many . ' in ' . $city . ' yet. Own one? List it on Haraan and take bookings online.',
                'h1' => $h1,
                'lead' => '',
            ];
        }

        // The words searchers use first ("Box Cricket Turfs in Vijayawada"), then what the
        // page answers ("12 Venues, Timings & Prices") — short enough not to be cut off.
        // Prices only when a venue here has a real one.
        $title = ($label ? ucwords($many) : 'Turfs & Courts') . ' in ' . $city
            . ': ' . $n . ($n === 1 ? ' Venue' : ' Venues')
            . ', Timings & ' . ($s['fromPrice'] ? 'Prices' : 'Photos')
            . ' | Haraan';

        // The snippet leads with what a searcher can do, and names real places — never
        // "none take bookings", which only gives a reason not to click.
        $hours = array_filter([
            $s['early'] ? count($s['early']) . ' open by 6 AM' : '',
            $s['late'] ? count($s['late']) . ' open till 10 PM or later' : '',
        ]);
        $desc = ($s['bookable'] > 0 && $s['fromPrice']
                ? 'Book ' . ($label ? $many : 'turfs & courts') . ' in ' . $city . ' from ₹' . number_format($s['fromPrice']) . '/hr. ' . $n . ' ' . ($n === 1 ? 'venue' : 'venues')
                : $n . ' ' . $noun . ' in ' . $city)
            . ($label === null && $sportWords !== '' ? ' for ' . strtolower($sportWords) : '')
            . ($descAreas ? ', including ' . self::listWords($descAreas) : '')
            . ' — timings, photos & directions';
        // Hours facts only while the snippet still fits (Google cuts at ~155 characters).
        foreach ($hours as $h) {
            if (mb_strlen($desc . '; ' . $h . '.') <= 155) {
                $desc .= (str_contains($desc, ';') ? ', ' : '; ') . $h;
            }
        }
        $desc .= '.';

        $lead = 'Haraan lists ' . $n . ' ' . $noun . ' in ' . $city
            . ($topAreas ? ', in ' . self::listWords($topAreas) . (count($s['areas']) > 3 ? ' and ' . (count($s['areas']) - 3) . (count($s['areas']) - 3 === 1 ? ' more area' : ' more areas') : '') : '')
            . '. '
            . ($s['bookable'] > 0
                ? ($s['bookable'] === $n
                    ? ($n === 1 ? 'It takes bookings on Haraan' : 'All of them take bookings on Haraan')
                    : $s['bookable'] . ($s['bookable'] === 1 ? ' takes' : ' take') . ' bookings on Haraan')
                    . ($s['fromPrice'] ? ', from ₹' . number_format($s['fromPrice']) . ' an hour.' : '.')
                : 'None take online bookings here yet — open one for its address, timings and directions, or ask to book and we’ll tell the owner.');

        return ['title' => $title, 'description' => $desc, 'h1' => $h1, 'lead' => $lead];
    }

    /**
     * Questions answered from the venues themselves — each answer names real places.
     *
     * @return list<array{question:string,answer:string}>
     */
    public static function faqs(string $city, ?string $sportSlug, array $s): array
    {
        if ($s['total'] === 0) {
            return [];
        }
        $label = $sportSlug ? self::SPORTS[$sportSlug][0] : null;
        [, $many] = $label ? self::NOUN[$label] : ['', 'sports venues'];
        $names = static fn (array $rows, int $k = 4): string => self::listWords(array_slice(array_column($rows, 'name'), 0, $k));
        $faqs = [];

        $faqs[] = [
            'question' => 'How many ' . $many . ' are there in ' . $city . '?',
            'answer' => 'Haraan lists ' . $s['total'] . ' ' . $many . ' in ' . $city
                . ($label === null && $s['sports'] ? ': ' . self::countWords($s['sports']) : '')
                . '. Each listing shows the address, opening hours and a map link.',
        ];

        $areas = array_slice($s['areas'], 0, 3, true);
        if (count($s['areas']) >= 2) {
            $parts = [];
            foreach ($areas as $area => $rows) {
                $parts[] = $area . ' (' . $names($rows, 2) . ')';
            }
            $faqs[] = [
                'question' => 'Which areas of ' . $city . ' have the most ' . $many . '?',
                'answer' => 'Most are in ' . implode(', ', $parts) . '.',
            ];
        }

        if ($s['early'] || $s['allDay']) {
            $faqs[] = [
                'question' => 'Which ' . $many . ' in ' . $city . ' open early in the morning?',
                'answer' => trim(($s['early'] ? $names($s['early']) . ' open by 6 AM. ' : '')
                    . ($s['allDay'] ? $names($s['allDay'], 3) . ' ' . (count($s['allDay']) === 1 ? 'is' : 'are') . ' open 24 hours.' : ''))
                    . ' Timings come from each venue’s listing — call before you go.',
            ];
        }
        if ($s['late']) {
            $faqs[] = [
                'question' => 'Which ' . $many . ' in ' . $city . ' are open late at night?',
                'answer' => $names($s['late']) . ' stay open until 10 PM or later.',
            ];
        }

        $faqs[] = $s['bookable'] > 0
            ? [
                'question' => 'Can I book a ' . rtrim($many, 's') . ' in ' . $city . ' online?',
                'answer' => 'Yes — ' . $s['bookable'] . ' ' . ($s['bookable'] === 1 ? 'venue takes' : 'venues take') . ' bookings on Haraan'
                    . ($s['fromPrice'] ? ', from ₹' . number_format($s['fromPrice']) . ' an hour' : '')
                    . '. Pick a court and an open slot and pay online. The others show their phone number and directions.',
            ]
            : [
                'question' => 'Can I book a ' . rtrim($many, 's') . ' in ' . $city . ' online?',
                'answer' => 'Not on Haraan yet. Each listing has the venue’s phone number and directions, and an “Ask to book” button — we pass the request to the owner and message you when online booking opens.',
            ];

        $faqs[] = [
            'question' => 'I run a sports venue in ' . $city . '. How do I list it?',
            'answer' => 'Apply at haraan.app/become-a-partner. Once it’s set up you take bookings online and keep walk-ins in the same calendar.',
        ];

        return $faqs;
    }

    /**
     * Structured data for the page: the venues as an ItemList, and the FAQs.
     *
     * @return list<array<string, mixed>>
     */
    public static function jsonLd(string $pageUrl, string $h1, Collection $venues, array $faqs): array
    {
        $blocks = [];
        if ($venues->isNotEmpty()) {
            $blocks[] = [
                '@context' => 'https://schema.org',
                '@type' => 'ItemList',
                'name' => $h1,
                'url' => $pageUrl,
                'numberOfItems' => $venues->count(),
                'itemListElement' => $venues->take(50)->values()->map(fn (array $v, int $i) => [
                    '@type' => 'ListItem',
                    'position' => $i + 1,
                    'url' => $v['url'],
                    'name' => $v['name'],
                ])->all(),
            ];
        }
        if ($faqs) {
            $blocks[] = [
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => array_map(static fn (array $f) => [
                    '@type' => 'Question',
                    'name' => $f['question'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['answer']],
                ], $faqs),
            ];
        }

        return $blocks;
    }

    /** How far "near Benz Circle" reaches — a short drive or ride across town. */
    public const NEAR_KM = 3.0;

    /**
     * "Turfs near {area}" pages for a city: one per area its venues are in, centred on the
     * middle of that area's venue pins, listing every venue in the city within NEAR_KM by
     * distance. Areas are Google's neighbourhood names, so most hold one or two venues —
     * counting what is *near* is what makes the page worth having, and it is what people
     * search ("box cricket near Benz Circle").
     *
     * A page is offered to search only with MIN_TO_INDEX venues and only when no larger
     * area already lists exactly the same venues (two pages saying the same thing).
     *
     * @return array<string, array{name:string,slug:string,lat:float,lng:float,own:int,venues:list<array>,indexable:bool}>
     */
    public static function nearPages(Collection $venues): array
    {
        $pinned = $venues->filter(fn (array $v) => $v['lat'] !== null && $v['lng'] !== null)->values();

        $pages = [];
        foreach ($pinned->filter(fn (array $v) => $v['area'] !== '')->groupBy(fn (array $v) => mb_strtolower($v['area'])) as $group) {
            $name = (string) $group->first()['area'];
            $slug = Str::slug($name);
            if ($slug === '' || isset($pages[$slug])) {
                continue;
            }
            $lat = (float) $group->avg('lat');
            $lng = (float) $group->avg('lng');
            $pages[$slug] = [
                'name' => $name,
                'slug' => $slug,
                'lat' => $lat,
                'lng' => $lng,
                'own' => $group->count(),
                'venues' => self::nearest($pinned, $lat, $lng, self::NEAR_KM),
                'indexable' => false,
            ];
        }

        uasort($pages, fn (array $a, array $b) => [count($b['venues']), $b['own'], $a['name']] <=> [count($a['venues']), $a['own'], $b['name']]);

        $seen = [];
        foreach ($pages as $slug => $p) {
            $ids = array_column($p['venues'], 'id');
            sort($ids);
            $sig = implode(',', $ids);
            if (count($ids) >= self::MIN_TO_INDEX && ! isset($seen[$sig])) {
                $pages[$slug]['indexable'] = true;
                $seen[$sig] = true;
            }
        }

        return $pages;
    }

    /**
     * Venues within $km of a point, nearest first, each with its distance ('km').
     *
     * @return list<array>
     */
    public static function nearest(Collection $venues, float $lat, float $lng, float $km, ?int $exceptId = null): array
    {
        return $venues
            ->filter(fn (array $v) => $v['lat'] !== null && $v['lng'] !== null && $v['id'] !== $exceptId)
            ->map(fn (array $v) => $v + ['km' => self::km($lat, $lng, $v['lat'], $v['lng'])])
            ->filter(fn (array $v) => $v['km'] <= $km)
            ->sortBy('km')
            ->values()
            ->all();
    }

    /** "450 m", "1.8 km". */
    public static function distanceLabel(float $km): string
    {
        return $km < 1 ? max(50, (int) (round($km * 20) * 50)) . ' m' : number_format($km, 1) . ' km';
    }

    /** Great-circle distance in km. */
    public static function km(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /** "6 AM–11 PM", "Open 24 hours", or '' — from a row's usual weekday hours. */
    public static function hoursLabel(array $v): string
    {
        if ($v['open'] === null || $v['close'] === null) {
            return '';
        }
        if ($v['open'] === $v['close']) {
            return 'Open 24 hours';
        }
        $fmt = static function (int $m): string {
            $h = intdiv($m, 60) % 24;
            $mm = $m % 60;

            return ($h % 12 ?: 12) . ($mm ? ':' . str_pad((string) $mm, 2, '0', STR_PAD_LEFT) : '') . ($h < 12 ? ' AM' : ' PM');
        };

        return $fmt($v['open']) . '–' . $fmt($v['close']);
    }

    /**
     * Copy and questions for a "near {area}" page, from the venues on it.
     *
     * @return array{title:string,description:string,h1:string,lead:string,faqs:list<array{question:string,answer:string}>}
     */
    public static function nearCopy(string $city, array $page): array
    {
        $rows = collect($page['venues']);
        $n = $rows->count();
        $area = $page['name'];
        $s = self::stats($rows);
        $sportWords = self::sportWords($s['sports'], 3);
        $names = static fn (iterable $r, int $k = 3): string => self::listWords(array_slice(array_column(is_array($r) ? $r : $r->all(), 'name'), 0, $k));
        $closest = $rows->first();

        $title = 'Turfs & Courts near ' . $area . ', ' . $city . ': ' . $n . ($n === 1 ? ' Venue' : ' Venues') . ' | Haraan';
        $desc = $n . ' ' . ($n === 1 ? 'sports venue' : 'sports venues') . ' within ' . (int) self::NEAR_KM . ' km of ' . $area . ', ' . $city
            . ($sportWords !== '' ? ' for ' . strtolower($sportWords) : '')
            . ' — ' . $names($rows, 2) . ($n > 2 ? ' and more' : '')
            . '. Distance, timings, photos & directions.';
        $lead = $n === 0 ? '' : 'Haraan lists ' . $n . ' ' . ($n === 1 ? 'venue' : 'venues') . ' within ' . (int) self::NEAR_KM . ' km of ' . $area
            . '. The closest is ' . $closest['name'] . ', ' . self::distanceLabel($closest['km']) . ' away'
            . ($s['bookable'] > 0 ? '; ' . $s['bookable'] . ($s['bookable'] === 1 ? ' takes' : ' take') . ' bookings on Haraan' . ($s['fromPrice'] ? ' from ₹' . number_format($s['fromPrice']) . ' an hour' : '') : '')
            . '.';

        $faqs = [];
        if ($n > 0) {
            $faqs[] = [
                'question' => 'Which is the nearest turf to ' . $area . ', ' . $city . '?',
                'answer' => $closest['name'] . ' is the closest Haraan lists, about ' . self::distanceLabel($closest['km']) . ' from the middle of ' . $area . '.'
                    . ($n > 1 ? ' ' . $names($rows->slice(1), 3) . ' are also within ' . (int) self::NEAR_KM . ' km.' : ''),
            ];
        }
        foreach (array_slice($s['sports'], 0, 3, true) as $slug => $count) {
            $sport = self::SPORTS[$slug][1];
            [$one, $many] = self::NOUN[self::SPORTS[$slug][0]];
            $here = $rows->filter(fn (array $v) => in_array($sport, $v['sports'], true));
            $faqs[] = [
                'question' => 'Where can I play ' . strtolower(self::SPORTS[$slug][0]) . ' near ' . $area . '?',
                'answer' => $count . ' ' . ($count === 1 ? $one : $many) . ' near ' . $area . ': '
                    . $here->take(4)->map(fn (array $v) => $v['name'] . ' (' . self::distanceLabel($v['km']) . ')')->implode(', ') . '.',
            ];
        }
        if ($s['early'] || $s['late']) {
            $faqs[] = [
                'question' => 'Which venues near ' . $area . ' open early or stay open late?',
                'answer' => trim(($s['early'] ? $names($s['early']) . ' open by 6 AM. ' : '') . ($s['late'] ? $names($s['late']) . ' stay open until 10 PM or later.' : ''))
                    . ' Timings come from each venue’s listing — call before you go.',
            ];
        }

        return [
            'title' => $title,
            'description' => $desc,
            'h1' => 'Turfs & courts near ' . $area . ', ' . $city,
            'lead' => $lead,
            'faqs' => $faqs,
        ];
    }

    /** The state hub's slug ("andhra-pradesh"), or null when discovery has no state set. */
    public static function stateSlug(): ?string
    {
        $state = DiscoverySettings::state();

        return $state === '' ? null : Str::slug($state);
    }

    /** Is this city one of the state's cities (so its pages link up to the state hub)? */
    public static function inState(string $city): bool
    {
        return collect(DiscoverySettings::cities())->contains(fn (string $c) => strcasecmp($c, $city) === 0);
    }

    // ── helpers ────────────────────────────────────────────────────────────────────

    /** @return array{id:int,name:string,area:string,sports:list<string>,bookable:bool,url:string,open:?int,close:?int,price:int} */
    private static function row(Venue $v, bool $bookable, string $city): array
    {
        $area = trim((string) $v->location);
        if (strcasecmp($area, $city) === 0 || str_contains(mb_strtolower($area), ',')) {
            $area = trim(explode(',', $area)[0]);
            if (strcasecmp($area, $city) === 0) {
                $area = '';
            }
        }
        // "tadepalle" and "Tadepalle" are one area; typed lower-case, it's title-cased.
        if ($area !== '' && mb_strtolower($area) === $area) {
            $area = mb_convert_case($area, MB_CASE_TITLE);
        }

        // Today's span is noise for "opens early"; take the most common weekday hours.
        $open = $close = null;
        if (is_array($v->hours_json) && $v->hours_json !== []) {
            $spans = [];
            foreach ($v->hours_json as $day) {
                if (is_array($day) && ! empty($day['open']) && ! empty($day['close']) && empty($day['closed'])) {
                    $spans[] = $day['open'] . '-' . $day['close'];
                }
            }
            if ($spans) {
                $common = array_search(max(array_count_values($spans)), array_count_values($spans), true);
                [$o, $c] = explode('-', (string) $common);
                $open = self::minutes($o);
                $close = self::minutes($c);
            }
        }

        return [
            'id' => (int) $v->id,
            'name' => (string) $v->name,
            'area' => $area,
            'sports' => $bookable || $v->sports ? array_values(array_unique($v->sportsList())) : [],
            'bookable' => $bookable,
            'url' => SeoUrl::venue($v),
            'open' => $open,
            'close' => $close,
            'price' => $bookable ? (int) $v->fromPrice() : 0,
            'lat' => $v->latitude !== null && $v->latitude !== '' ? (float) $v->latitude : null,
            'lng' => $v->longitude !== null && $v->longitude !== '' ? (float) $v->longitude : null,
        ];
    }

    private static function minutes(string $hm): int
    {
        [$h, $m] = array_map('intval', explode(':', $hm) + [0, 0]);

        return $h * 60 + $m;
    }

    /** "Cricket, Badminton & Pickleball" from the most common sports. */
    private static function sportWords(array $sports, int $max): string
    {
        if ($max === 0) {
            return '';
        }
        $labels = array_map(fn (string $slug) => self::SPORTS[$slug][0], array_slice(array_keys($sports), 0, $max));

        return count($labels) > 1 ? implode(', ', array_slice($labels, 0, -1)) . ' & ' . end($labels) : ($labels[0] ?? '');
    }

    /** "9 cricket grounds, 7 badminton courts and 4 pickleball courts". */
    private static function countWords(array $sports): string
    {
        $parts = [];
        foreach ($sports as $slug => $n) {
            [$one, $many] = self::NOUN[self::SPORTS[$slug][0]];
            $parts[] = $n . ' ' . ($n === 1 ? $one : $many);
        }

        return self::listWords($parts);
    }

    /** @param list<string> $items */
    private static function listWords(array $items): string
    {
        $items = array_values(array_filter($items, fn ($i) => $i !== ''));

        return match (count($items)) {
            0 => '',
            1 => $items[0],
            default => implode(', ', array_slice($items, 0, -1)) . ' and ' . end($items),
        };
    }
}
