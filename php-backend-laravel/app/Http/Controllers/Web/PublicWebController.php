<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\EventService;
use App\Services\LeaderboardService;
use App\Support\CityResolver;
use Illuminate\Support\Collection;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\Ad;
use App\Models\Event;
use App\Models\HostProfile;
use App\Models\LiveMatch;
use App\Models\User;
use App\Models\Venue;

final class PublicWebController extends Controller
{
    /**
     * The GameHub sport chips, in the app's order (MainScreen.kt `sports`). "All" is the
     * unfiltered lead chip, not a sport. Keep this list and the app's in step.
     */
    private const GAMEHUB_SPORTS = ['All', 'Cricket', 'Football', 'Badminton', 'Basketball'];

    public function home(): View
    {
        $events = $this->eventFeed(6);

        $city = CityResolver::selected();
        $listingCount = Event::query()->where('status', 'published')
                ->when($city, fn ($q) => $q->where('city', $city))->count()
            + Venue::query()->where('is_active', true)
                ->when($city, fn ($q) => $q->where('city', $city))->count();

        return view('site.home', [
            'title' => 'Haraan - Home',
            'events' => $events,
            'listingCount' => $listingCount,
        ]);
    }

    public function events(): View
    {
        // The Categories row used to be decorative — ?category= highlighted a card and
        // filtered nothing. The app filters every rail off its selected category
        // (MainScreen.kt `filteredEvents`), so each feed below takes it too.
        $category = $this->selectedCategory();

        $events = $this->eventFeed(8, $category);
        $trending = $this->trendingFeed(8, $category);
        $bannerEvents = app(EventService::class)->getBannerEvents();

        // The listing is one page with an optional ?category= / ?city= filter, so
        // every variant canonicalises to /events and only the unfiltered page is
        // indexed — thin near-duplicates dilute the listing's ranking.
        $seoCity = CityResolver::selected();

        // `/` and `/events` render the same listing, but the root is the brand's
        // front door: its title/description must LEAD with "Haraan" so a search for
        // the brand name resolves to it (Google otherwise reads "haraan" as the
        // singer Hariharan). /events stays keyword-forward for "events near you".
        $isHome = request()->path() === '/';

        return view('site.events', [
            'title' => $isHome
                ? 'Haraan — Events, Turf Booking & Live Sports'
                : ($seoCity ? 'Events in ' . $seoCity : 'Events near you'),
            'seo' => [
                'title' => $isHome
                    ? 'Haraan — Book Events, Sports Turfs & Live Scores'
                    : ($seoCity
                        ? 'Events in ' . $seoCity . ' — book tickets'
                        : 'Events near you — book tickets'),
                'description' => $isHome
                    ? 'Haraan is your app for booking concerts, comedy, workshops and sports '
                        . 'events, reserving cricket turfs and badminton courts, and following '
                        . 'live gully-cricket scores — all in one place.'
                    : 'Concerts, comedy nights, workshops, sports and festivals'
                        . ($seoCity ? ' in ' . $seoCity : '')
                        . '. Browse what\'s on, pick your tickets and pay in seconds on Haraan.',
                // Each keeps its own canonical so the homepage stays the homepage.
                'canonical' => $isHome ? url('/') : url('/events'),
                // City comes from a cookie (crawlers never send one), so only the
                // ?category= filter can produce a near-duplicate worth suppressing.
                'robots' => $category !== null
                    ? 'noindex,follow'
                    : 'index,follow,max-image-preview:large',
            ],
            'events' => $events,
            'forYou' => $this->railFeed('for_you', 20, $category),
            'eventsAd' => $this->usableAd('events'),
            'catRow' => $this->categoryCards(),
            'trending' => $trending,
            'bannerEvents' => $bannerEvents,
            'categories' => ['All', 'Concerts', 'Workshops', 'Nightlife', 'Comedy', 'Sports', 'Festivals'],
        ]);
    }

    public function eventDetail(Request $request, string $id): View
    {
        $event = Event::query()->with('partner.hostProfile')->findOrFail($id);

        // Record the web page view for the organiser's analytics funnel (the app
        // already records API opens via EventsController). Best-effort + swallowed
        // inside the recorder. Skip the organiser's own previews so their funnel
        // reflects real visitors, not their own edits.
        if ($request->user()?->id !== $event->partner_id) {
            \App\Support\EventViewRecorder::record($event, $request);
        }

        // Link "Hosted by" to the organiser's public page, but only if it's live.
        $hostProfile = $event->partner?->hostProfile;
        $hostProfile = $hostProfile && $hostProfile->isLive() ? $hostProfile : null;

        // "More events you may like" — real published events, never this one.
        // Prefer the same category, then the same city, then anything upcoming,
        // so the rail is relevant without inventing data. Ordered soonest-first.
        $similar = Event::query()
            ->where('status', 'published')
            ->with('ticketTypes')
            ->whereKeyNot($event->getKey())
            ->orderByRaw('CASE WHEN category = ? THEN 0 ELSE 1 END', [$event->category])
            ->orderByRaw('CASE WHEN city = ? THEN 0 ELSE 1 END', [$event->city])
            ->orderByRaw('CASE WHEN date >= ? THEN 0 ELSE 1 END', [now()->startOfDay()])
            ->orderBy('date')
            ->limit(6)
            ->get();

        return view('site.event', [
            'title' => $event->title,
            'event' => $event,
            'id'    => $id,
            'hostProfile' => $hostProfile,
            'similar' => $similar,
            'seo'   => $this->eventSeo($event),
        ]);
    }

    /**
     * Search metadata for an event page: a rich description plus schema.org
     * `Event` markup, which is what earns the date/venue/price rich result on
     * Google (and the "Events" carousel). Offers use the cheapest live tier —
     * Google reads `lowPrice`-style single offers fine and it matches the "from
     * ₹x" the page shows.
     *
     * @return array<string, mixed>
     */
    private function eventSeo(Event $event): array
    {
        $where = trim(implode(', ', array_filter([$event->venue, $event->city])));
        $when = $event->date?->format('D, j M Y');

        $desc = trim(strip_tags((string) $event->description));
        if ($desc === '') {
            $desc = trim(implode(' · ', array_filter([
                $event->title,
                $when,
                $where,
            ]))) . '. Book tickets on Haraan.';
        }

        // Start time: the date column is a datetime, the clock is a separate
        // free-text column ("07:30 PM"). Fold them together when it parses.
        $start = $event->date?->copy();
        if ($start && filled($event->time)) {
            try {
                $t = \Illuminate\Support\Carbon::parse((string) $event->time);
                $start->setTime((int) $t->format('H'), (int) $t->format('i'));
            } catch (\Throwable) {
                // Unparseable clock text — the date alone is still valid markup.
            }
        }

        $tiers = $event->ticketTypes()->get(['price']);
        $price = $tiers->isNotEmpty() ? (float) $tiers->min('price') : (float) $event->price;

        $schema = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Event',
            'name' => $event->title,
            'url' => url('/events/' . $event->id),
            'description' => \Illuminate\Support\Str::limit($desc, 300),
            'image' => array_values(array_filter($event->imageUrls())) ?: null,
            // The stored date/time is IST wall-clock (the app runs on UTC), so
            // stamp the offset without shifting the hands — otherwise Google shows
            // an event that starts 5½ hours early.
            'startDate' => $start
                ? \Illuminate\Support\Carbon::parse($start->format('Y-m-d H:i:s'), 'Asia/Kolkata')->toIso8601String()
                : null,
            'eventStatus' => 'https://schema.org/EventScheduled',
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'location' => $where === '' ? null : array_filter([
                '@type' => 'Place',
                'name' => $event->venue ?: $event->city,
                'address' => array_filter([
                    '@type' => 'PostalAddress',
                    'streetAddress' => $event->location ?: null,
                    'addressLocality' => $event->city ?: null,
                    'addressCountry' => 'IN',
                ]),
            ]),
            'organizer' => $event->partner?->hostProfile?->isLive()
                ? [
                    '@type' => 'Organization',
                    'name' => $event->partner->hostProfile->display_name,
                    'url' => url('/host/' . $event->partner->hostProfile->slug),
                ]
                : null,
            'offers' => array_filter([
                '@type' => 'Offer',
                'url' => url('/events/' . $event->id),
                'price' => number_format($price, 2, '.', ''),
                'priceCurrency' => 'INR',
                'availability' => ($event->available_slots ?? 1) > 0
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/SoldOut',
            ]),
            'aggregateRating' => ($event->ratings_count ?? 0) > 0 ? [
                '@type' => 'AggregateRating',
                'ratingValue' => (string) round((float) $event->rating, 1),
                'reviewCount' => (int) $event->ratings_count,
            ] : null,
        ], static fn ($v) => $v !== null && $v !== []);

        return [
            'title' => trim($event->title . ($where !== '' ? ' — ' . $where : '')),
            'description' => $desc,
            'image' => $event->heroImageUrl(),
            'type' => 'event',
            'canonical' => url('/events/' . $event->id),
            'jsonld' => $schema,
        ];
    }

    /**
     * A partner's public organiser page — hero, about and their upcoming events.
     * 404s unless the profile exists and is live (opted in + name & about set).
     */
    public function hostProfile(string $slug): View
    {
        $profile = HostProfile::query()->where('slug', $slug)->first();

        abort_if($profile === null || ! $profile->isLive(), 404);

        $isVenue = $profile->isVenueLane();
        $events = $isVenue ? collect() : $profile->upcomingEventsQuery()->limit(24)->get();
        $pastEvents = $isVenue ? collect() : $profile->pastEventsQuery()->limit(12)->get();
        $venues = $isVenue ? $profile->venuesQuery()->limit(24)->get() : collect();
        $viewer = auth()->user();
        $isOwner = $viewer !== null && $viewer->id === $profile->user_id;

        // Count one view per visitor per day (owner's own visits don't count).
        $seenKey = 'hpv_' . $profile->id . '_' . today()->toDateString();
        if (! $isOwner && ! session()->has($seenKey)) {
            $profile->recordView();
            session()->put($seenKey, 1);
        }

        return view('site.host', [
            'title' => $profile->display_name . ' · Haraan',
            'profile' => $profile,
            'lane' => $isVenue ? 'venue' : 'event',
            'events' => $events,
            'pastEvents' => $pastEvents,
            'venues' => $venues,
            'followers' => $profile->followersCount(),
            'isFollowing' => $profile->isFollowedBy($viewer),
            'isOwner' => $isOwner,
            'rating' => $profile->ratingSummary(),
        ]);
    }

    /** Toggle following an organiser (auth). Returns to the page. */
    public function followHost(Request $request, string $slug): \Illuminate\Http\RedirectResponse
    {
        $profile = HostProfile::query()->where('slug', $slug)->first();

        abort_if($profile === null || ! $profile->isLive(), 404);

        $profile->toggleFollow($request->user());

        return back();
    }

    public function gamehub(): View
    {
        $city = CityResolver::selected();

        $venues = Venue::query()
            ->where('is_active', true)
            ->when($city, fn ($q) => $q->where('city', $city))
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Venue $v) => $this->decorateVenueCard($v));

        // Real per-sport venue counts for the "Explore by Sport" tiles.
        $sportCounts = Venue::query()
            ->where('is_active', true)
            ->when($city, fn ($q) => $q->where('city', $city))
            ->selectRaw('category, COUNT(*) as c')
            ->groupBy('category')
            ->pluck('c', 'category');

        // Live now: real in-progress matches for the mobile live strip (app parity).
        $liveMatches = LiveMatch::where('status', 'Live')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get()
            ->map(fn (LiveMatch $m) => $this->decorateLiveStrip($m))
            ->all();

        // The app's sport chips are a global filter over the whole GameHub screen
        // (MainScreen.kt `selectedSport`): they narrow the venues AND the ActionBoard.
        // Here they're ?sport= links, as the Events categories already are — a reload
        // costs a beat, but the popular/more split below is then recomputed over the
        // filtered set exactly as the app recomputes it, instead of a JS hide that
        // would leave the split stale.
        $selectedSport = $this->selectedSport();
        $filteredVenues = $selectedSport === 'All'
            ? $venues
            : $venues->where('category', $selectedSport)->values();

        // One catalogue, two presentations — no venue appears twice. Mirrors the app:
        // the reel is the top 5 by rating, "more" is strictly what the reel didn't show.
        $popularVenues = $filteredVenues->sortByDesc(fn ($v) => (float) $v->rating)->take(5)->values();
        $moreVenues = $filteredVenues->whereNotIn('id', $popularVenues->pluck('id'))->values();

        return view('site.gamehub', [
            'title'         => 'GameHub — book turfs, courts & play',
            'seo'           => [
                'title' => 'Book turfs, courts and sports venues'
                    . ($city ? ' in ' . $city : ' near you'),
                'description' => 'Book cricket turfs, football grounds and badminton courts'
                    . ($city ? ' in ' . $city : ' near you')
                    . ' by the hour on Haraan — plus live gully-cricket scores and player rankings.',
                'canonical' => url('/gamehub'),
                'robots' => request()->query('sport') ? 'noindex,follow' : 'index,follow,max-image-preview:large',
            ],
            'venues'        => $venues,
            'sportCounts'   => $sportCounts,
            'liveMatches'   => $liveMatches,
            'topPlayer'     => $this->topRankedPlayer($city),
            'selectedSport' => $selectedSport,
            'sportChips'    => self::GAMEHUB_SPORTS,
            'popularVenues' => $popularVenues,
            'moreVenues'    => $moreVenues,
        ]);
    }

    /**
     * Rank #1 for the GameHub "Top Player" widget — the app's LeaderboardHomeWidget,
     * reading the same monthly ranked-XP board its API serves (LeaderboardService), so
     * the two can't drift.
     *
     * The app keys the board off the GPS-resolved district; the web only knows a chosen
     * city, which is the nearest equivalent it has. A city that isn't a district name
     * simply finds nobody, and the widget shows its honest empty state — the same thing
     * the app does when a district has no ranked players. Never a placeholder.
     */
    private function topRankedPlayer(?string $city): ?array
    {
        if (empty($city)) {
            return null;
        }

        return app(LeaderboardService::class)
            ->monthly('district', null, $city, 1)[0] ?? null;
    }

    /**
     * Flatten a LiveMatch into a clean two-row scorecard for the GameHub live
     * strip. Wickets are counted from over_summary (a "W" ball), avoiding the
     * heavier inline parser the full ActionBoard page uses.
     */
    private function decorateLiveStrip(LiveMatch $m): array
    {
        $overs = is_array($m->over_summary)
            ? $m->over_summary
            : (json_decode((string) $m->over_summary, true) ?: []);

        $wkts = static function (string $side) use ($overs): int {
            $w = 0;
            foreach ($overs as $o) {
                if (($o['batting'] ?? 'home') !== $side) {
                    continue;
                }
                foreach ((array) ($o['balls'] ?? []) as $b) {
                    if (is_string($b) && strtoupper(trim($b)) === 'W') {
                        $w++;
                    }
                }
            }
            return $w;
        };

        $battingHome = 'home';
        if (! empty($overs)) {
            $battingHome = ($overs[array_key_last($overs)]['batting'] ?? 'home');
        }
        $battingHome = $battingHome === 'home';

        $homeWkts = $wkts('home');
        $awayWkts = $wkts('away');
        $homeBatted = $battingHome || (int) $m->home_score > 0 || $homeWkts > 0;
        $awayBatted = ! $battingHome || (int) $m->away_score > 0 || $awayWkts > 0;

        return [
            'id'          => $m->id,
            'competition' => $m->competition,
            'home' => [
                'abbr'    => $m->home,
                'name'    => $m->home_full ?: $m->home,
                'logo'    => (string) ($m->home_logo ?? ''),
                'emblem'  => (string) ($m->home_emblem ?? ''),
                'score'   => $homeBatted ? ($m->home_score . '/' . $homeWkts) : 'Yet to bat',
                'overs'   => $battingHome ? $m->overs : '',
                'batting' => $battingHome,
            ],
            'away' => [
                'abbr'    => $m->away,
                'name'    => $m->away_full ?: $m->away,
                'logo'    => (string) ($m->away_logo ?? ''),
                'emblem'  => (string) ($m->away_emblem ?? ''),
                'score'   => $awayBatted ? ($m->away_score . '/' . $awayWkts) : 'Yet to bat',
                'overs'   => $battingHome ? '' : $m->overs,
                'batting' => ! $battingHome,
            ],
        ];
    }

    public function gamehubDetail(string $id): View
    {
        $venue = Venue::query()
            ->where('is_active', true)
            ->with(['reviews' => fn ($q) => $q->where('is_active', true)->latest(), 'partner.hostProfile'])
            ->findOrFail($id);

        // Link to the owner's public page when they have a live one.
        $hostProfile = $venue->partner?->hostProfile;
        $hostProfile = $hostProfile && $hostProfile->isLive() ? $hostProfile : null;

        return view('site.gamehub-detail', [
            'hostProfile' => $hostProfile,
            'title' => $venue->name . ($venue->city ? ' — ' . $venue->city : ''),
            'id'    => $id,
            'venue' => $this->decorateVenueDetail($venue),
            'seo'   => $this->venueSeo($venue),
        ]);
    }

    /**
     * Search metadata for a venue page. `SportsActivityLocation` is the schema.org
     * type Google understands for turfs/courts — it carries the address, price
     * range and rating into the local result.
     *
     * @return array<string, mixed>
     */
    private function venueSeo(Venue $venue): array
    {
        $sports = implode(', ', array_slice($venue->sportsList(), 0, 3));
        $image = \App\Support\MediaUrl::resolveMany(is_array($venue->images) ? $venue->images : [])[0] ?? null;

        $desc = trim(strip_tags((string) ($venue->tagline ?: $venue->about))) ?: trim(
            'Book ' . ($sports ?: 'a slot') . ' at ' . $venue->name
            . ($venue->city ? ' in ' . $venue->city : '')
            . ($venue->price ? ' from ₹' . $venue->price . '/hr' : '') . ' on Haraan.'
        );

        $schema = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'SportsActivityLocation',
            'name' => $venue->name,
            'url' => url('/gamehub/' . $venue->id),
            'description' => \Illuminate\Support\Str::limit($desc, 300),
            'image' => $image,
            'address' => array_filter([
                '@type' => 'PostalAddress',
                'streetAddress' => $venue->address ?: $venue->location ?: null,
                'addressLocality' => $venue->city ?: null,
                'addressCountry' => 'IN',
            ]),
            'geo' => ($venue->latitude && $venue->longitude) ? [
                '@type' => 'GeoCoordinates',
                'latitude' => $venue->latitude,
                'longitude' => $venue->longitude,
            ] : null,
            'priceRange' => $venue->price ? '₹' . $venue->price : null,
            'aggregateRating' => ($venue->ratings_count ?? 0) > 0 ? [
                '@type' => 'AggregateRating',
                'ratingValue' => (string) round((float) $venue->rating, 1),
                'reviewCount' => (int) $venue->ratings_count,
            ] : null,
        ], static fn ($v) => $v !== null && $v !== []);

        return [
            'title' => $venue->name . ($venue->city ? ' — ' . $venue->city : ''),
            'description' => $desc,
            'image' => $image,
            'canonical' => url('/gamehub/' . $venue->id),
            'jsonld' => $schema,
        ];
    }

    /**
     * The viewer's coordinates for ActionBoard proximity sorting. A precise device
     * fix (browser geolocation, stored in the `hb_geo` cookie as "lat,lng") wins;
     * otherwise the chosen city is geocoded via Google Maps. Returns [null, null]
     * when neither is available (proximity then falls back to district/state names).
     *
     * @return array{0: ?float, 1: ?float}
     */
    private function viewerLatLng(): array
    {
        $geo = (string) request()->cookie('hb_geo', '');
        if (preg_match('/^(-?\d{1,2}(?:\.\d+)?),(-?\d{1,3}(?:\.\d+)?)$/', $geo, $m)) {
            $lat = (float) $m[1];
            $lng = (float) $m[2];
            if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180) {
                return [$lat, $lng];
            }
        }

        $city = CityResolver::selected();
        if ($city !== null && $city !== '') {
            return $this->geocodeCity($city);
        }

        return [null, null];
    }

    private function geocodeCity(string $city): array
    {
        return $this->geocode($city . ', India');
    }

    /**
     * Geocode a free-form place string to [lat, lng] via the Google Maps Geocoding
     * API, cached for 30 days (places don't move). Returns [null, null] on any failure
     * or when no API key is configured, so callers silently fall back.
     *
     * @return array{0: ?float, 1: ?float}
     */
    private function geocode(string $address): array
    {
        $address = trim($address);
        $key = (string) config('services.google_maps.key');
        if ($address === '' || $key === '') {
            return [null, null];
        }

        return \Illuminate\Support\Facades\Cache::remember(
            'geocode:' . md5(mb_strtolower($address)),
            now()->addDays(30),
            function () use ($address, $key): array {
                try {
                    $resp = \Illuminate\Support\Facades\Http::timeout(4)
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

    /**
     * Fill in a match's coordinates from its place names (locality → district → state)
     * via Google geocoding, IN MEMORY only, so MatchProximity can measure true distance
     * even though these rows were created without a GPS fix. No-op when it already has
     * coordinates or has no usable place name.
     */
    private function ensureMatchCoords(LiveMatch $m): void
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

    public function actionBoard(): View
    {
        $matches = LiveMatch::orderBy('created_at', 'desc')->get()->toArray();

        // ── Mobile app-parity feed (mirrors Api\LiveMatchController::index) ──
        // Same visibility rules as the app: signed-in users get their district's
        // LOCAL matches + FEATURED; guests get FEATURED only. Private never listed.
        $viewer = auth()->user();
        $feed = LiveMatch::query()
            ->visibleTo($viewer)
            ->orderByDesc('updated_at')
            ->limit(200)
            ->get();

        // Same ranking as the app, now with a real viewer position: a precise device
        // fix from the browser (cookie set by the page's geolocation JS) if we have it,
        // otherwise the viewer's chosen city geocoded via Google Maps. Either gives
        // MatchProximity real coordinates so matches sort by true km distance; admin
        // featured still leads (MatchProximity::sortKey #1) regardless of distance.
        [$viewerLat, $viewerLng] = $this->viewerLatLng();
        // Give each match real coordinates (geocoded from its place names, cached) so
        // distance is measurable against the viewer even though these rows carry no GPS.
        if ($viewerLat !== null && $viewerLng !== null) {
            $feed->each(fn (LiveMatch $m) => $this->ensureMatchCoords($m));
        }
        $near = new \App\Support\MatchProximity(
            latitude: $viewerLat,
            longitude: $viewerLng,
            district: (string) ($viewer->district ?? ''),
            state: (string) ($viewer->state ?? ''),
        );
        $feed = $near->sort($feed)
            ->take(40)
            ->map(function (LiveMatch $m) use ($viewer, $near): array {
                // Which side is batting (latest over's tag)? Drives score/overs
                // attribution and puts the batting side on top of the card.
                $overSummary = is_array($m->over_summary) ? $m->over_summary : [];
                $battingTeam = 1;
                for ($i = count($overSummary) - 1; $i >= 0; $i--) {
                    $tag = $overSummary[$i]['batting'] ?? null;
                    if ($tag !== null && $tag !== '') {
                        $battingTeam = ($tag === $m->away || $tag === 'away') ? 2 : 1;
                        break;
                    }
                }
                $overs = (string) ($m->overs ?? '');
                $scoreText = (string) ($m->score_text ?: '');
                return [
                    'id'          => (string) $m->id,
                    'team1'       => (string) $m->home,
                    'team2'       => (string) $m->away,
                    // Team crests: uploaded logo path/URL + default emblem key (action1..4)
                    // so the list can render real icons, not just monograms.
                    'team1Logo'   => (string) ($m->home_logo ?? ''),
                    'team1Emblem' => (string) ($m->home_emblem ?? ''),
                    'team2Logo'   => (string) ($m->away_logo ?? ''),
                    'team2Emblem' => (string) ($m->away_emblem ?? ''),
                    'score1'      => ($battingTeam === 1 && $scoreText !== '') ? $scoreText : (string) ($m->home_score ?? 0),
                    'score2'      => ($battingTeam === 2 && $scoreText !== '') ? $scoreText : (string) ($m->away_score ?? 0),
                    'overs1'      => $battingTeam === 2 ? '' : $overs,
                    'overs2'      => $battingTeam === 2 ? $overs : '',
                    'battingTeam' => $battingTeam,
                    'status'      => (string) ($m->status ?? ''),
                    'venue'       => (string) ($m->venue ?? ''),
                    'competition' => (string) ($m->competition ?? ''),
                    'isLive'      => strtolower((string) $m->status) === 'live',
                    'visibility'  => (string) ($m->visibility ?? LiveMatch::VIS_LOCAL),
                    'district'    => (string) ($m->district ?? ''),
                    'locality'    => (string) ($m->locality ?? ''),
                    'isMine'      => $viewer !== null && (int) $m->user_id === (int) $viewer->id,
                    // Grouping hint only (see Api\LiveMatchController::index) —
                    // everyone sees every public match; false for guests.
                    'isLocalToViewer' => $viewer !== null
                        && (string) ($viewer->district ?? '') !== ''
                        && (string) $m->district === (string) $viewer->district,
                    'isFeatured'  => (string) $m->visibility === LiveMatch::VIS_FEATURED,
                    'distanceKm'  => ($d = $near->distanceKm($m)) === null ? null : round($d, 1),
                ];
            })
            ->values();

        // District Home snapshot + ranked-XP boards — same sources as the app's
        // District/State tabs. Guests (no district) get honest empty states.
        $districtSummary = null;
        $districtBoard = [];
        $stateBoard = [];
        $leaderboards = app(\App\Services\LeaderboardService::class);
        if ($viewer !== null && !empty($viewer->district)) {
            $districtSummary = app(\App\Services\DistrictService::class)
                ->summary((string) $viewer->district, $viewer->state !== null ? (string) $viewer->state : null);
            $districtBoard = $leaderboards->monthly('district', null, (string) $viewer->district, 50);
        }
        if ($viewer !== null && !empty($viewer->state)) {
            $stateBoard = $leaderboards->monthly('state', null, (string) $viewer->state, 50);
        }

        return view('site.actionboard', [
            'title' => 'Action Board',
            'matches' => $matches,
            'abFeed' => $feed,
            'abDistrictSummary' => $districtSummary,
            'abDistrictBoard' => $districtBoard,
            'abStateBoard' => $stateBoard,
        ]);
    }

    /**
     * The web ActionBoard match-detail pages mirror the app's Match Details screen
     * (Info · Commentary · Live · Scorecard). All four render from the SAME assembled
     * payload the app consumes — score + live crease + replayed innings cards +
     * commentary — via LiveMatchController::detailPayload().
     */
    private function matchDetailFor(string $id): array
    {
        $match = LiveMatch::findOrFail($id);
        $viewer = auth()->user();
        return app(\App\Http\Controllers\Api\LiveMatchController::class)->detailPayload($match, $viewer);
    }

    public function actionBoardMatchLive(string $id): View
    {
        return view('site.actionboard-match-live', ['title' => 'Live Match', 'detail' => $this->matchDetailFor($id), 'id' => $id, 'activeTab' => 'live']);
    }

    public function actionBoardMatchInfo(string $id): View
    {
        return view('site.actionboard-match-info', ['title' => 'Match Info', 'detail' => $this->matchDetailFor($id), 'id' => $id, 'activeTab' => 'info']);
    }

    public function actionBoardMatchCommentary(string $id): View
    {
        return view('site.actionboard-match-commentary', ['title' => 'Commentary', 'detail' => $this->matchDetailFor($id), 'id' => $id, 'activeTab' => 'commentary']);
    }

    public function actionBoardMatchScorecard(string $id): View
    {
        return view('site.actionboard-match-scorecard', ['title' => 'Match Scorecard', 'detail' => $this->matchDetailFor($id), 'id' => $id, 'activeTab' => 'scorecard']);
    }

    public function login(): View
    {
        return view('site.auth.login', ['title' => 'Login']);
    }

    public function register(): View
    {
        return view('site.auth.register', ['title' => 'Register']);
    }

    public function profile(): View
    {
        return view('site.profile', ['title' => 'My Profile']);
    }


    public function getPlayerDetails($playerId)
    {
        $user = \App\Models\User::where('player_id', $playerId)->first();
        if ($user) {
            return response()->json([
                'success' => true,
                'name' => $user->name,
                'role' => $user->player_role ?? 'Unknown',
                'style' => $user->playing_style ?? 'Unknown'
            ]);
        }
        return response()->json(['success' => false]);
    }

    public function showPlayerProfile(string $player_id): View
    {
        $player = \App\Models\User::where('player_id', $player_id)->firstOrFail();
        
        // Find recent matches where this player is in the squad
        $allMatches = \App\Models\LiveMatch::orderBy('created_at', 'desc')->get();
        $recentMatches = [];
        
        foreach ($allMatches as $match) {
            $inHome = is_array($match->home_squad) && collect($match->home_squad)->contains(function ($p) use ($player_id) {
                $id = is_array($p) ? ($p['id'] ?? null) : $p;
                return (string)$id === (string)$player_id;
            });
            $inAway = is_array($match->away_squad) && collect($match->away_squad)->contains(function ($p) use ($player_id) {
                $id = is_array($p) ? ($p['id'] ?? null) : $p;
                return (string)$id === (string)$player_id;
            });
            
            if ($inHome || $inAway) {
                $recentMatches[] = $match;
            }
        }
        
        return view('site.player-profile', [
            'title' => $player->name . ' - Player Profile',
            'player' => $player,
            'recentMatches' => $recentMatches
        ]);
    }

    public function search(): View
    {
        $query = request()->input('q', '');
        $type = request()->input('type', 'all');
        
        $city = CityResolver::selected();
        $results = [];
        if ($query !== '') {
            $like = '%' . $query . '%';

            if ($type === 'all' || $type === 'events') {
                $events = Event::query()
                    ->where('status', 'published')
                    ->when($city, fn ($q) => $q->where('city', $city))
                    ->where(function ($w) use ($like) {
                        $w->where('title', 'like', $like)
                            ->orWhere('venue', 'like', $like)
                            ->orWhere('category', 'like', $like);
                    })
                    ->orderBy('date', 'desc')
                    ->limit(12)
                    ->get(['id', 'title', 'category', 'venue']);

                if ($events->isNotEmpty()) {
                    $results['events'] = $events->map(fn (Event $e) => [
                        'id'       => $e->id,
                        'title'    => $e->title,
                        'category' => $e->category ?: 'Event',
                        'venue'    => $e->venue ?: 'Mumbai',
                    ])->all();
                }
            }

            if ($type === 'all' || $type === 'venues') {
                $venues = Venue::query()
                    ->where('is_active', true)
                    ->when($city, fn ($q) => $q->where('city', $city))
                    ->where(function ($w) use ($like) {
                        $w->where('name', 'like', $like)
                            ->orWhere('category', 'like', $like)
                            ->orWhere('location', 'like', $like);
                    })
                    ->orderByDesc('is_featured')
                    ->limit(12)
                    ->get(['id', 'name', 'category', 'location']);

                if ($venues->isNotEmpty()) {
                    $results['venues'] = $venues->map(fn (Venue $v) => [
                        'id'       => $v->id,
                        'title'    => $v->name,
                        'sport'    => $v->category ?: 'Sport',
                        'location' => $v->location ?: 'Mumbai',
                    ])->all();
                }
            }
        }
        
        return view('site.search', [
            'title' => "Search Results for \"$query\"",
            'query' => $query,
            'type' => $type,
            'results' => $results,
        ]);
    }

    /**
     * Lightweight JSON autocomplete for the header search bar.
     *
     * Returns a small, city-scoped set of matching events and venues so the
     * topbar can render live suggestions as the user types. Mirrors the query
     * shape of search() but capped tight for latency.
     */
    public function searchSuggest(Request $request): JsonResponse
    {
        $query = trim((string) $request->input('q', ''));

        if (mb_strlen($query) < 2) {
            return response()->json(['events' => [], 'venues' => []]);
        }

        $city = CityResolver::selected();
        $like = '%' . $query . '%';

        $events = Event::query()
            ->where('status', 'published')
            ->when($city, fn ($q) => $q->where('city', $city))
            ->where(function ($w) use ($like) {
                $w->where('title', 'like', $like)
                    ->orWhere('venue', 'like', $like)
                    ->orWhere('category', 'like', $like);
            })
            ->orderBy('date', 'desc')
            ->limit(5)
            ->get(['id', 'title', 'category', 'venue'])
            ->map(fn (Event $e) => [
                'id'    => $e->id,
                'title' => $e->title,
                'meta'  => trim(($e->category ?: 'Event') . ' · ' . ($e->venue ?: 'Mumbai'), ' ·'),
                'url'   => '/events/' . $e->id,
            ])->all();

        $venues = Venue::query()
            ->where('is_active', true)
            ->when($city, fn ($q) => $q->where('city', $city))
            ->where(function ($w) use ($like) {
                $w->where('name', 'like', $like)
                    ->orWhere('category', 'like', $like)
                    ->orWhere('location', 'like', $like);
            })
            ->orderByDesc('is_featured')
            ->limit(5)
            ->get(['id', 'name', 'category', 'location'])
            ->map(fn (Venue $v) => [
                'id'    => $v->id,
                'title' => $v->name,
                'meta'  => trim(($v->category ?: 'Venue') . ' · ' . ($v->location ?: 'Mumbai'), ' ·'),
                'url'   => '/gamehub/' . $v->id,
            ])->all();

        return response()->json(['events' => $events, 'venues' => $venues]);
    }

    /* ------------------------------------------------------------------ */
    /*  Leaderboard (batting / bowling career boards)                      */
    /* ------------------------------------------------------------------ */

    public function leaderboard(): View
    {
        $scope = request()->query('scope', 'country');
        if (!in_array($scope, ['country', 'state', 'district'], true)) {
            $scope = 'country';
        }

        $states = User::query()
            ->where('is_guest', false)
            ->whereNotNull('state')
            ->where('state', '!=', '')
            ->distinct()
            ->orderBy('state')
            ->pluck('state')
            ->values()
            ->all();

        $districts = User::query()
            ->where('is_guest', false)
            ->whereNotNull('district')
            ->where('district', '!=', '')
            ->distinct()
            ->orderBy('district')
            ->pluck('district')
            ->values()
            ->all();

        $selectedState    = request()->query('state', $states[0] ?? 'Andhra Pradesh');
        $selectedDistrict = request()->query('district', $districts[0] ?? 'Kadapa');

        $scopeFilter = function ($query) use ($scope, $selectedState, $selectedDistrict) {
            $query->where('is_guest', false);
            if ($scope === 'state') {
                $query->where('state', $selectedState);
            } elseif ($scope === 'district') {
                $query->where('district', $selectedDistrict);
            }
            return $query;
        };

        $battingLeaderboard = $scopeFilter(User::query())
            ->where('career_runs', '>', 0)
            ->orderByDesc('career_runs')
            ->limit(50)
            ->get();

        $bowlingLeaderboard = $scopeFilter(User::query())
            ->where('career_wickets', '>', 0)
            ->orderByDesc('career_wickets')
            ->limit(50)
            ->get();

        return view('site.leaderboard', [
            'title'              => 'Leaderboard',
            'scope'              => $scope,
            'states'             => $states,
            'districts'          => $districts,
            'selectedState'      => $selectedState,
            'selectedDistrict'   => $selectedDistrict,
            'battingLeaderboard' => $battingLeaderboard,
            'bowlingLeaderboard' => $bowlingLeaderboard,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  ActionBoard match JSON (polled by the live scoreboards)            */
    /* ------------------------------------------------------------------ */

    public function actionBoardMatchJson(string $id): JsonResponse
    {
        return response()->json(LiveMatch::findOrFail($id));
    }

    public function actionBoardMatchesJson(): JsonResponse
    {
        return response()->json(LiveMatch::orderBy('created_at', 'desc')->get());
    }

    /* ------------------------------------------------------------------ */
    /*  Player directory (squad builder + profile-setup claiming)          */
    /* ------------------------------------------------------------------ */

    public function searchPlayers(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $players = User::query()
            ->where('is_guest', false)
            ->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                    ->orWhere('player_id', 'like', "%{$q}%");
            })
            ->orderBy('name')
            ->limit(10)
            ->get();

        return response()->json($players->map(fn (User $u) => [
            'id'       => $u->player_id,
            'name'     => $u->name,
            'role'     => $u->player_role ?: 'Player',
            'style'    => $u->batting_style ?: ($u->playing_style ?: ''),
            'district' => $u->district ?: '—',
        ])->all());
    }

    public function createGuestPlayer(Request $request): JsonResponse
    {
        $validated = $request->validate(['name' => 'required|string|max:255']);
        $name = trim($validated['name']);
        if ($name === '') {
            return response()->json(['success' => false], 422);
        }

        $guest = User::create([
            'name'          => $name,
            'email'         => 'guest_' . Str::random(16) . '@guest.haraan',
            'password'      => Hash::make(Str::random(24)),
            'role'          => 'user',
            'status'        => 'active',
            'is_guest'      => true,
            'player_role'   => 'All-rounder',
            'playing_style' => 'Unknown',
        ]);

        return response()->json([
            'success' => true,
            'id'      => $guest->player_id,
            'name'    => $guest->name,
            'role'    => $guest->player_role,
            'style'   => $guest->playing_style,
        ]);
    }

    public function getClaimablePlayers(Request $request): JsonResponse
    {
        $name = trim((string) $request->query('name', ''));
        if (mb_strlen($name) < 2) {
            return response()->json([]);
        }

        $guests = User::query()
            ->where('is_guest', true)
            ->where('name', 'like', "%{$name}%")
            ->orderBy('name')
            ->limit(10)
            ->get();

        return response()->json($guests->map(function (User $g) {
            $match = LiveMatch::query()
                ->where('home_squad', 'like', "%{$g->player_id}%")
                ->orWhere('away_squad', 'like', "%{$g->player_id}%")
                ->orderByDesc('created_at')
                ->first();

            $playedWith = $match
                ? ($match->title ?: trim(($match->home ?? '') . ' vs ' . ($match->away ?? '')))
                : 'Guest match record';

            return [
                'id'          => $g->id,
                'name'        => $g->name,
                'player_id'   => $g->player_id,
                'played_with' => $playedWith !== 'vs' ? $playedWith : 'Guest match record',
            ];
        })->all());
    }

    /* ------------------------------------------------------------------ */
    /*  ActionBoard profile setup (cricket onboarding)                     */
    /* ------------------------------------------------------------------ */

    public function showProfileSetupForm(): View
    {
        return view('site.profile-setup', [
            'title' => 'Complete Your Profile',
            'user'  => auth()->user(),
        ]);
    }

    public function saveProfileSetup(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return redirect()->route('site.login');
        }

        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'state'         => 'required|string|max:255',
            'district'      => 'required|string|max:255',
            'batting_style' => 'nullable|string|max:100',
            'bowling_style' => 'nullable|string|max:100',
            'claim_user_id' => 'nullable|integer',
            'photo'         => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
        ]);

        // Merge a claimed guest profile's career stats into this account.
        if (!empty($validated['claim_user_id'])) {
            $guest = User::query()
                ->where('id', $validated['claim_user_id'])
                ->where('is_guest', true)
                ->first();

            if ($guest) {
                $user->career_runs          += (int) $guest->career_runs;
                $user->career_balls         += (int) $guest->career_balls;
                $user->career_matches       += (int) $guest->career_matches;
                $user->career_wickets       += (int) $guest->career_wickets;
                $user->career_runs_conceded += (int) $guest->career_runs_conceded;
                $guest->delete();
            }
        }

        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('avatars', 'public');
            $user->avatar = '/storage/' . $path;
        }

        $user->name          = $validated['name'];
        $user->state         = $validated['state'];
        $user->district      = $validated['district'];
        $user->batting_style = $validated['batting_style'] ?? $user->batting_style;
        $user->bowling_style = $validated['bowling_style'] ?? $user->bowling_style;
        $user->player_role   = $user->player_role ?: 'All-rounder';
        $user->primary_sport = $user->primary_sport ?: 'Cricket';

        $attrs = $user->sport_attributes ?? [];
        $attrs['role']    = $attrs['role'] ?? $user->player_role;
        $attrs['batting'] = $validated['batting_style'] ?? ($attrs['batting'] ?? '');
        $attrs['bowling'] = $validated['bowling_style'] ?? ($attrs['bowling'] ?? '');
        $user->sport_attributes = $attrs;

        $user->is_guest = false;
        // The User model's saving hook mints a structured player_id from state + district.
        $user->save();

        return redirect()
            ->route('site.gamehub.actionboard')
            ->with('success', 'Your cricket profile is ready.');
    }

    /* ------------------------------------------------------------------ */
    /*  GameHub venue view-model helpers                                   */
    /* ------------------------------------------------------------------ */

    /** Normalize a stored venue image path into a usable URL. */
    private function venueImageUrl(?string $path): string
    {
        if (!$path) {
            return asset('gamehub.png');
        }
        if (preg_match('/^(http|https):\/\//', $path) || str_starts_with($path, '/')) {
            return $path;
        }
        return asset('storage/' . ltrim($path, '/'));
    }

    /** Compact venue shape for the GameHub browse grid. */
    private function decorateVenueCard(Venue $v): object
    {
        $images = is_array($v->images) ? $v->images : [];

        return (object) [
            'id'       => $v->id,
            'title'    => $v->name,
            'image'    => $this->venueImageUrl($images[0] ?? null),
            'location' => $v->location,
            'category' => $v->category,
            'rating'   => $v->rating ?: '0.0',
            'reviews'  => (int) ($v->reviews_count ?? 0),
            'price'    => (int) ($v->price ?? 0),
            'badge'    => $v->is_featured ? 'Featured' : null,
            // The app's cards carry these two (VenueItem.tagline / .sports); the web card
            // shape predates them. Additive — the desktop markup ignores both.
            'tagline'  => (string) ($v->tagline ?? ''),
            'sports'   => $v->sportsList(),
        ];
    }

    /** Full venue shape for the GameHub detail page. */
    private function decorateVenueDetail(Venue $v): object
    {
        $images  = is_array($v->images) ? $v->images : [];
        $gallery = array_values(array_map(
            fn ($p) => $this->venueImageUrl($p),
            array_slice($images, 1)
        ));

        $amenities = is_array($v->amenities) ? $v->amenities : [];

        // The detail scheduler needs at least one sport, each with a bookable court.
        // Courts are real, sport-aware units now: group them by the sports each hosts so the
        // scheduler's "pick sport → pick court" flow matches what the venue actually has.
        $sports = $v->sportsList();
        if ($sports === []) {
            $sports = [$v->category ?: 'Cricket'];
        }

        $courts = [];
        foreach ($v->courtsBySport() as $sport => $list) {
            $names = array_map(fn ($c) => $c->name, $list);
            if ($names !== []) {
                $courts[$sport] = array_values($names);
            }
        }
        // Every offered sport must resolve to at least one court or the scheduler breaks.
        foreach ($sports as $sport) {
            if (empty($courts[$sport])) {
                $courts[$sport] = ['Court 1'];
            }
        }

        // Per-court hourly rate keyed by court name (null → venue price). The scheduler prices
        // each slot by the selected court so a premium pitch costs more than a practice court.
        $basePrice = (int) ($v->price ?? 0);
        $courtPrices = [];
        $courtPeak = [];
        foreach ($v->courts as $c) {
            $courtPrices[$c->name] = $c->price ?? $basePrice;
            // Peak pricing for the scheduler: applied by time window (evenings cost more). The
            // backend stays authoritative on weekday precision when a booking is actually made.
            if ($c->peak_price !== null && $c->peak_start !== null && $c->peak_end !== null) {
                $courtPeak[$c->name] = [
                    'price' => (int) $c->peak_price,
                    'start' => $c->peak_start,
                    'end' => $c->peak_end,
                ];
            }
        }

        $reviewsList = $v->reviews->map(fn ($r) => (object) [
            'user'    => $r->name,
            'date'    => $r->ago ?: 'recently',
            'rating'  => (int) $r->rating,
            'comment' => $r->text ?: '',
        ])->all();

        return (object) [
            'id'           => $v->id,
            'title'        => $v->name,
            'image'        => $this->venueImageUrl($images[0] ?? null),
            'gallery'      => $gallery,
            'location'     => $v->location,
            'category'     => $v->category,
            'rating'       => $v->rating ?: '0.0',
            'reviews'      => (int) ($v->reviews_count ?? 0),
            'reviews_list' => $reviewsList,
            'price'        => (int) ($v->price ?? 0),
            'hours'        => $v->displayHours() ?: '6:00 AM – 11:00 PM',
            'cancellation' => $v->cancellationText(),
            // The app's VenueDetailScreen reads these (address line, "Show in Map"/
            // "Get directions", the "Good to know" checklist, the rating summary);
            // the web shape predates them. Additive — the desktop markup ignores them.
            'address'      => (string) ($v->address ?? ''),
            'latitude'     => $v->latitude,
            'longitude'    => $v->longitude,
            'map_link'     => (string) ($v->map_link ?? ''),
            'rules'        => is_array($v->rules) ? array_values(array_filter($v->rules)) : [],
            'ratings_count' => (int) ($v->ratings_count ?? 0),
            'is_bookable'  => (bool) ($v->is_bookable ?? true),
            'badge'        => $v->is_featured ? 'Featured' : null,
            'description'  => $v->about ?: 'A premium sports facility with well-maintained playing surfaces and modern amenities.',
            'amenities'    => $amenities,
            'sports'       => $sports,
            'courts'       => $courts,
            'court_prices' => $courtPrices,
            'court_peak'   => $courtPeak,
        ];
    }

    /**
     * The "Trending" rail. Now the admin's `trending` placement, via {@see railFeed()},
     * because that is what the app's row is (MainScreen.kt `trendingEvents`) and the
     * two must agree.
     *
     * This replaces a version ranked by real ticket sales, which was the more honest
     * reading of the word but rendered nothing: no event has a booking yet, so the
     * whole section hid itself and the website was simply missing a row the app has.
     * The tag is a real editorial signal — an admin picked those events — but note the
     * label promises popularity it isn't measuring. If the sales ranking should come
     * back once bookings exist, it belongs on top of the placement, not instead of it.
     *
     * @return Collection<int, Event>
     */
    private function trendingFeed(int $limit = 8, ?string $category = null): Collection
    {
        return $this->railFeed('trending', $limit, $category);
    }

    /**
     * Local-first ordering shared by every event feed: the selected city sorts
     * ahead of all others, without excluding anyone. Applied at the DB level (a
     * CASE in the ORDER BY) so it composes with take($limit) — the selected city
     * wins even when its events aren't the most recent. A caller adds its own
     * secondary order (date / created_at) as the within-group tiebreak.
     *
     * lower() both sides because the city column is free-text and mixed-case.
     * No city selected ("All India") → a no-op, leaving the caller's order.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function orderLocalFirst($query, ?string $city)
    {
        return $query->when(
            $city !== null && $city !== '',
            fn ($q) => $q->orderByRaw('CASE WHEN lower(city) = lower(?) THEN 0 ELSE 1 END', [$city])
        );
    }

    /** The category the viewer picked, or null for "All" (= no filter). */
    private function selectedCategory(): ?string
    {
        $category = trim((string) request()->query('category', ''));

        return ($category === '' || strcasecmp($category, 'All') === 0) ? null : $category;
    }

    /**
     * The GameHub sport chip in play. Whitelisted against the app's own chip row
     * (MainScreen.kt `sports`) so ?sport= can only ever be one of those — an unknown
     * value falls back to "All" rather than rendering a chip row where nothing is lit
     * next to an empty venue list.
     *
     * Returns "All" (not null) because the chips are a closed set the view lights up
     * by name, unlike the open-ended event categories above.
     */
    private function selectedSport(): string
    {
        $sport = trim((string) request()->query('sport', ''));

        foreach (self::GAMEHUB_SPORTS as $known) {
            if (strcasecmp($sport, $known) === 0) {
                return $known;
            }
        }

        return 'All';
    }

    /**
     * The sponsored slot at the top of the Events feed — the app's AdSpaceBanner.
     *
     * Returns the creative only if it can actually carry the slot: a bare title with
     * no image and no link renders as a dead "Shop Now" and is worse than an empty
     * space. The app falls back to a bundled sample ad when the API has nothing; the
     * public site must not, because that invents an advertiser.
     */
    private function usableAd(string $placement): ?Ad
    {
        $ad = Ad::query()
            ->where('placement', $placement)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->orderBy('sort_order')
            ->first();

        if ($ad === null || trim((string) $ad->title) === '') {
            return null;
        }

        // Needs something to look at or somewhere to go — otherwise it's just noise.
        $hasImage = trim((string) $ad->image_url) !== '';
        $hasLink  = trim((string) $ad->link_url) !== '';

        return ($hasImage || $hasLink) ? $ad : null;
    }

    /**
     * The Categories cards — "All" plus the categories that actually have events,
     * biggest first, with real counts.
     *
     * Built from the data rather than hardcoded like the app's row, which names
     * Concerts / Standup and shows "245 Events" / "54 Shows". Neither is real: the
     * admin's events are categorised "Music" and "GENERAL", so those cards read
     * "0 events" on the web and filter to an empty list in the app. A row that
     * advertises zero is worse than no row — this one can only show what exists.
     *
     * @return list<array{title: string, href: string, stat: string, on: bool}>
     */
    private function categoryCards(int $limit = 2): array
    {
        $active = (string) request()->query('category', 'All');

        $cards = [[
            'title' => 'All',
            'href'  => '/events',
            'stat'  => Event::query()->where('status', 'published')->count() . ' Total',
            'on'    => $active === 'All',
        ]];

        $top = Event::query()
            ->where('status', 'published')
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->selectRaw('category, count(*) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();

        foreach ($top as $row) {
            $cards[] = [
                'title' => ucfirst(strtolower((string) $row->category)),
                'href'  => '/events?category=' . urlencode((string) $row->category),
                'stat'  => $row->total . ' ' . ($row->total === 1 ? 'Event' : 'Events'),
                'on'    => strcasecmp($active, (string) $row->category) === 0,
            ];
        }

        return $cards;
    }

    /**
     * One curated rail — `for_you` or `trending` — mirroring the app's rule
     * (MainScreen.kt `forYouEvents` / `trendingEvents`): take the same base list the
     * app reads from /api/events (newest first), float the viewer's city to the top
     * WITHOUT filtering other cities out, then narrow to the rail's placement.
     * Untagged events show everywhere, and an empty rail falls back to the full list
     * rather than rendering blank.
     *
     * NB this deliberately does NOT reuse {@see eventFeed()}, which hard-filters to
     * the selected city — the app floats, it doesn't filter, and the rails have to
     * match it.
     *
     * One deliberate divergence from the app: `published` only. /api/events applies
     * no status filter, so the app would surface a draft; the public site must not.
     */
    private function railFeed(string $rail, int $limit = 20, ?string $category = null): Collection
    {
        $city = CityResolver::selected();

        // Local-first, but never a filter: the selected city floats to the top and
        // every other city follows. Ordered in the DB so a city whose events aren't
        // among the newest still surfaces first (a post-fetch sort of take($limit)
        // could never see them). Newest-first is the tiebreak inside each group.
        $events = $this->orderLocalFirst(
            Event::query()
                ->where('status', 'published')
                ->when($category, fn ($q) => $q->where('category', $category)),
            $city
        )
            ->orderByDesc('created_at')
            ->take($limit)
            ->get();

        // A poster-less event renders the bv-white placeholder — a logo on black. Both
        // rails are poster-led, so both need real artwork. The event still shows in
        // Explore Nearby, which leads with text.
        $events = $events->filter(fn (Event $e): bool => is_array($e->images) && count($e->images) > 0)->values();

        $tagged = $events->filter(function (Event $e) use ($rail): bool {
            $placements = $e->placements;

            return empty($placements) || in_array($rail, $placements, true);
        })->values();

        return $tagged->isNotEmpty() ? $tagged : $events;
    }

    private function eventFeed(int $limit = 6, ?string $category = null): Collection
    {
        $city = CityResolver::selected();

        // Local-first, not city-filtered: all published events show, the selected
        // city floats to the top (ordered in the DB so it wins even when its events
        // aren't the newest), then everything else by date.
        $events = $this->orderLocalFirst(
            Event::query()
                ->where('status', 'published')
                ->when($category, fn ($q) => $q->where('category', $category)),
            $city
        )
            ->orderBy('date', 'desc')
            ->take($limit)
            ->get();

        // Only real, admin-created events ever reach the public feed — no invented demo
        // cards. When there are none (fresh install, or everything is still a draft),
        // this returns empty and the view shows its "no events yet" state.
        return $events;
    }
}
