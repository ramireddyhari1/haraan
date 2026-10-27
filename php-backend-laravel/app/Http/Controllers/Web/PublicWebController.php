<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Api\LiveMatchController;
use App\Http\Controllers\Api\MatchJoinController;
use App\Http\Controllers\Api\MatchesController;
use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\Event;
use App\Models\HostProfile;
use App\Models\LiveMatch;
use App\Models\User;
use App\Models\Venue;
use App\Services\AdTracker;
use App\Services\BookingService;
use App\Services\DistrictService;
use App\Services\EventService;
use App\Services\Insights\SportInsights;
use App\Services\LeaderboardService;
use App\Services\Membership\EntitlementDenied;
use App\Services\Membership\MemberEntitlements;
use App\Services\Membership\SportInsightsAccess;
use App\Services\Stats\MatchPlayerStatsService;
use App\Support\CityResolver;
use App\Support\EventViewRecorder;
use App\Support\MatchGeocoder;
use App\Support\MatchProximity;
use App\Support\MediaUrl;
use App\Support\SeoUrl;
use App\Support\Membership\MemberFeature;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Support\BusinessClock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class PublicWebController extends Controller
{
    /**
     * The Pulse sport chips, in the app's order (MainScreen.kt `sports`). "All" is the
     * unfiltered lead chip, not a sport. Keep this list and the app's in step.
     */
    private const GAMEHUB_SPORTS = ['All', 'Cricket', 'Football', 'Badminton', 'Basketball'];

    /** Every sport ?sport= may name. The desktop chips show those that have venues. */
    private const GAMEHUB_FILTER_SPORTS = ['Cricket', 'Football', 'Badminton', 'Basketball', 'Pickleball', 'Tennis', 'Volleyball', 'Swimming'];

    public function home(): View
    {
        $events = $this->eventFeed(6);

        $city = CityResolver::selected();
        $listingCount = Event::query()->where('status', 'published')
            ->when($city, fn ($q) => $q->where('city', $city))->count()
            + Venue::published()
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
                : ($seoCity ? 'Events in '.$seoCity : 'Events near you'),
            'seo' => [
                'title' => $isHome
                    ? 'Haraan — Book Events, Sports Turfs & Live Scores'
                    : ($seoCity
                        ? 'Events in '.$seoCity.' — book tickets'
                        : 'Events near you — book tickets'),
                // Keep this under the partial's 158-char cap: at 164 it used to be
                // cut mid-word, so results ended "...live gully-cricket s...".
                'description' => $isHome
                    ? 'Haraan — book concerts, comedy, workshops and sports events near you, '
                        .'reserve cricket turfs and badminton courts, and follow live '
                        .'gully-cricket scores.'
                    : 'Concerts, comedy nights, workshops, sports and festivals'
                        .($seoCity ? ' in '.$seoCity : '')
                        .'. Browse what\'s on, pick your tickets and pay in seconds on Haraan.',
                // Each keeps its own canonical so the homepage stays the homepage.
                'canonical' => $isHome ? url('/') : url('/events'),
                // City comes from a cookie (crawlers never send one), so only the
                // ?category= filter can produce a near-duplicate worth suppressing.
                'robots' => $category !== null
                    ? 'noindex,follow'
                    : 'index,follow,max-image-preview:large',
                // The homepage is the root of the trail, so it gets no crumbs.
                'breadcrumbs' => $isHome ? null : [
                    ['name' => 'Haraan', 'url' => url('/')],
                    ['name' => 'Events'],
                ],
            ],
            // The page's one <h1>. The layout is app-style (sections, no headline), so
            // it was the only public page with no H1 at all — search engines read the
            // H1 as the page's topic.
            'pageHeading' => $isHome
                ? 'Haraan — book events, sports turfs and courts, and follow live scores'
                : ($seoCity ? 'Events in '.$seoCity : 'Events near you'),
            'events' => $events,
            'forYou' => $this->railFeed('for_you', 20, $category),
            'eventsAd' => $this->usableAd('events'),
            'catRow' => $this->categoryCards(),
            'trending' => $trending,
            'bannerEvents' => $bannerEvents,
            'categories' => ['All', 'Concerts', 'Workshops', 'Nightlife', 'Comedy', 'Sports', 'Festivals'],
        ]);
    }

    public function eventDetail(Request $request, string $id): View|RedirectResponse
    {
        $viewer = $request->user();

        $query = Event::query()->with('partner.hostProfile');

        // Only a published event is public. This page used to be an unfiltered
        // findOrFail, so a draft's title, poster, tiers and host leaked to anyone
        // guessing an id (and to crawlers), even though booking and /api/events
        // were already published-only. lower(): status casing is mixed in this DB.
        //
        // The exception is a preview: the organiser who owns the event (desk staff
        // share the owner's partner account, hence effectivePartnerId) and internal
        // staff can still open their own unpublished event to check it before it
        // goes live. Everyone else gets a 404, not a 403 — a stranger shouldn't
        // learn that the id exists at all.
        if (! $this->mayPreviewAnyEvent($viewer)) {
            $query->where(function ($q) use ($viewer): void {
                $q->whereRaw('lower(status) = ?', ['published']);

                if ($viewer !== null) {
                    $q->orWhere('partner_id', $viewer->effectivePartnerId());
                }
            });
        }

        $event = $query->findOrFail(SeoUrl::id($id));

        // One URL per event: /events/31 (old app shares, Google's existing index) and
        // any stale slug after a rename 301 to the current /events/31-title, so the
        // ranking signals all land on a single address.
        $canonicalSegment = SeoUrl::segment($event, 'title');
        if ($id !== $canonicalSegment) {
            $qs = $request->getQueryString();

            return redirect()->to(url('/events/'.$canonicalSegment).($qs ? '?'.$qs : ''), 301);
        }

        $isPreview = strtolower((string) $event->status) !== 'published';

        // Record the web page view for the organiser's analytics funnel (the app
        // already records API opens via EventsController). Best-effort + swallowed
        // inside the recorder. Skip the organiser's own previews so their funnel
        // reflects real visitors, not their own edits — and skip unpublished
        // previews entirely, which have no real visitors by definition.
        if (! $isPreview && $viewer?->id !== $event->partner_id) {
            EventViewRecorder::record($event, $request);
        }

        // Link "Hosted by" to the organiser's public page, but only if it's live.
        $hostProfile = $event->partner?->hostProfile;
        $hostProfile = $hostProfile && $hostProfile->isLive() ? $hostProfile : null;

        // "More events you may like" — real published events, never this one.
        // Prefer the same category, then the same city, then anything upcoming,
        // so the rail is relevant without inventing data. Ordered soonest-first.
        $similar = Event::query()
            ->where('status', 'published')
            ->notFinished()
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
            // Numeric — the page builds /events/{id}/book and API calls from it.
            'id' => (string) $event->id,
            'hostProfile' => $hostProfile,
            'similar' => $similar,
            // A draft preview must never be indexed, even though only its owner and
            // internal staff can reach it: preview URLs get pasted into chats, and a
            // draft must not end up outranking the real listing once it publishes.
            'seo' => $isPreview
                ? [...$this->eventSeo($event), 'robots' => 'noindex,nofollow']
                : $this->eventSeo($event),
        ]);
    }

    /**
     * Internal staff who may open any event, published or not — super-admins and
     * the OPS desk that runs the Events workspace. Deliberately NOT canManage(),
     * which is also true for every event PARTNER: that would let one organiser read
     * another's unreleased line-up.
     */
    private function mayPreviewAnyEvent(?User $viewer): bool
    {
        return $viewer !== null && ($viewer->isSuperAdmin() || $viewer->hasRoleEither(['OPS']));
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
            ]))).'. Book tickets on Haraan.';
        }

        // Start time: the date column is a datetime, the clock is a separate
        // free-text column ("07:30 PM"). Fold them together when it parses.
        $start = $event->date?->copy();
        if ($start && filled($event->time)) {
            try {
                $t = Carbon::parse((string) $event->time);
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
            'url' => SeoUrl::event($event),
            'description' => Str::limit($desc, 300),
            'image' => array_values(array_filter($event->imageUrls())) ?: null,
            // The stored date/time is IST wall-clock (the app runs on UTC), so
            // stamp the offset without shifting the hands — otherwise Google shows
            // an event that starts 5½ hours early.
            'startDate' => $start
                ? Carbon::parse($start->format('Y-m-d H:i:s'), 'Asia/Kolkata')->toIso8601String()
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
                    'url' => url('/host/'.$event->partner->hostProfile->slug),
                ]
                : null,
            'offers' => array_filter([
                '@type' => 'Offer',
                'url' => SeoUrl::event($event),
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
            'title' => trim($event->title.($where !== '' ? ' — '.$where : '')),
            'description' => $desc,
            'image' => $event->heroImageUrl(),
            'type' => 'event',
            'canonical' => SeoUrl::event($event),
            'jsonld' => $schema,
            'breadcrumbs' => array_values(array_filter([
                ['name' => 'Haraan', 'url' => url('/')],
                ['name' => 'Events', 'url' => url('/events')],
                // The city hub, when the event has a city — internal links from every
                // event page are what lets "events in <city>" rank.
                filled($event->city)
                    ? ['name' => 'Events in '.trim((string) $event->city), 'url' => route('site.events.city', ['city' => SeoUrl::city((string) $event->city)])]
                    : null,
                ['name' => $event->title],
            ])),
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
        $seenKey = 'hpv_'.$profile->id.'_'.today()->toDateString();
        if (! $isOwner && ! session()->has($seenKey)) {
            $profile->recordView();
            session()->put($seenKey, 1);
        }

        return view('site.host', [
            'title' => $profile->display_name.' · Haraan',
            'seo' => $this->hostSeo($profile, $isVenue),
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

    /**
     * Search metadata for an organiser / venue-owner brand page.
     *
     * Lives here rather than in site/host.blade.php so every public page assembles
     * its metadata the same way (see eventSeo/venueSeo) — the view's copy silently
     * shadowed this one.
     *
     * @return array<string, mixed>
     */
    private function hostSeo(HostProfile $profile, bool $isVenue): array
    {
        $url = url('/host/'.$profile->slug);
        $noun = $isVenue ? 'Sports venues' : 'Events';
        $rating = $profile->ratingSummary();

        // Tagline first: it's written as a one-line pitch, which is what a meta
        // description wants. `about` is the fallback and gets truncated.
        $desc = trim((string) ($profile->tagline ?: $profile->about));
        if ($desc === '') {
            $desc = $noun.' by '.$profile->display_name
                .($profile->city ? ' in '.$profile->city : '')
                .'. See what\'s on and book on Haraan.';
        }

        $schema = array_filter([
            '@context' => 'https://schema.org',
            // A venue owner is a physical place searchers can visit; an event
            // organiser is not.
            '@type' => $isVenue ? 'LocalBusiness' : 'Organization',
            'name' => $profile->display_name,
            'url' => $url,
            'description' => Str::limit(strip_tags($desc), 300),
            'logo' => $profile->logoUrl(),
            'image' => $profile->coverUrl() ?: $profile->logoUrl(),
            'address' => $profile->city ? [
                '@type' => 'PostalAddress',
                'addressLocality' => $profile->city,
                'addressCountry' => 'IN',
            ] : null,
            'sameAs' => array_values(array_filter(
                array_merge([$profile->website], array_values((array) ($profile->socials ?? []))),
                static fn ($v) => is_string($v) && str_starts_with($v, 'http'),
            )) ?: null,
            'aggregateRating' => $rating['count'] > 0 && $rating['avg'] !== null ? [
                '@type' => 'AggregateRating',
                'ratingValue' => (string) $rating['avg'],
                'reviewCount' => $rating['count'],
            ] : null,
        ], static fn ($v) => $v !== null && $v !== []);

        return [
            'title' => $profile->display_name.' — '.$noun.' on Haraan',
            'description' => $desc,
            'image' => $profile->coverUrl() ?: $profile->logoUrl(),
            'type' => 'profile',
            'canonical' => $url,
            'jsonld' => $schema,
            'breadcrumbs' => [
                ['name' => 'Haraan', 'url' => url('/')],
                ['name' => $isVenue ? 'Pulse' : 'Events', 'url' => url($isVenue ? '/gamehub' : '/events')],
                ['name' => $profile->display_name],
            ],
        ];
    }

    /** Toggle following an organiser (auth). Returns to the page. */
    public function followHost(Request $request, string $slug): RedirectResponse
    {
        $profile = HostProfile::query()->where('slug', $slug)->first();

        abort_if($profile === null || ! $profile->isLive(), 404);

        $profile->toggleFollow($request->user());

        return back();
    }

    public function gamehub(): View
    {
        $city = CityResolver::selected();
        [$userLat, $userLng] = $this->viewerLatLng();

        $allVenues = Venue::published()
            ->with(['courts', 'slots'])
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->get();

        if ($userLat !== null && $userLng !== null) {
            // Compute real distance to each venue
            $venuesWithDistance = $allVenues->map(function (Venue $v) use ($userLat, $userLng) {
                $km = ($v->latitude !== null && $v->longitude !== null)
                    ? $this->haversineKm($userLat, $userLng, (float) $v->latitude, (float) $v->longitude)
                    : null;
                $card = $this->decorateVenueCard($v);
                if ($km !== null) {
                    $card->distance = $this->formatKm($km);
                    $card->distance_km = $km;
                }
                return $card;
            });

            // 1. Pinned venues within radius (30 km, expanding up to 50 km if none within 30 km)
            $pinned = $venuesWithDistance->filter(fn ($v) => isset($v->distance_km));
            $within30 = $pinned->filter(fn ($v) => $v->distance_km <= 30)->sortBy('distance_km')->values();

            if ($within30->isNotEmpty()) {
                $nearby = $within30;
            } else {
                $within50 = $pinned->filter(fn ($v) => $v->distance_km <= 50)->sortBy('distance_km')->values();
                $nearby = $within50->isNotEmpty() ? $within50 : $pinned->sortBy('distance_km')->take(10)->values();
            }

            // 2. Unpinned venues matching chosen city (if any)
            $unpinned = $venuesWithDistance->filter(fn ($v) => !isset($v->distance_km));
            if ($city) {
                $unpinned = $unpinned->filter(fn ($v) =>
                    strcasecmp((string) $v->city, $city) === 0 ||
                    stripos((string) $v->location, $city) !== false ||
                    stripos((string) $v->title, $city) !== false
                );
            }

            $venues = $nearby->concat($unpinned)->values();
        } else {
            // No coordinates available: filter by chosen city (if any), matching city or location
            if ($city) {
                $venues = $allVenues->filter(function (Venue $v) use ($city) {
                    return strcasecmp((string) $v->city, $city) === 0 ||
                           stripos((string) $v->location, $city) !== false ||
                           stripos((string) $v->address, $city) !== false;
                })->map(fn (Venue $v) => $this->decorateVenueCard($v))->values();
            } else {
                $venues = $allVenues->map(fn (Venue $v) => $this->decorateVenueCard($v))->values();
            }
        }

        // Real per-sport venue counts for the "Explore by Sport" tiles.
        $sportCounts = $venues->groupBy('category')->map->count();

        // Live now: real in-progress matches for the mobile live strip (app parity).
        $liveMatches = LiveMatch::where('status', 'Live')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get()
            ->map(fn (LiveMatch $m) => $this->decorateLiveStrip($m))
            ->all();

        // The app's sport chips are a global filter over the whole Pulse screen
        // (MainScreen.kt `selectedSport`): they narrow the venues AND the ActionBoard.
        // Here they're ?sport= links, as the Events categories already are — a reload
        // costs a beat, but the popular/more split below is then recomputed over the
        // filtered set exactly as the app recomputes it, instead of a JS hide that
        // would leave the split stale.
        $selectedSport = $this->selectedSport();
        // A venue counts for every sport it sells, not just its primary category.
        $filteredVenues = $selectedSport === 'All'
            ? $venues
            : $venues->filter(fn ($v) => collect($v->sports ?: [$v->category])
                ->contains(fn ($s) => strcasecmp((string) $s, $selectedSport) === 0))->values();

        // One catalogue, two presentations — no venue appears twice. Mirrors the app:
        // the reel is the top 5 by rating, "more" is strictly what the reel didn't show.
        $popularVenues = $filteredVenues->sortByDesc(fn ($v) => (float) $v->rating)->take(5)->values();
        $moreVenues = $filteredVenues->whereNotIn('id', $popularVenues->pluck('id'))->values();

        return view('site.gamehub', [
            'title' => 'Pulse — book turfs, courts & play',
            'seo' => [
                'title' => 'Book turfs, courts and sports venues'
                    .($city ? ' in '.$city : ' near you'),
                'description' => 'Book cricket turfs, football grounds and badminton courts'
                    .($city ? ' in '.$city : ' near you')
                    .' by the hour on Haraan — plus live gully-cricket scores and player rankings.',
                'canonical' => url('/gamehub'),
                'robots' => request()->query('sport') ? 'noindex,follow' : 'index,follow,max-image-preview:large',
                'breadcrumbs' => [
                    ['name' => 'Haraan', 'url' => url('/')],
                    ['name' => 'Pulse'],
                ],
            ],
            'venues' => $venues,
            'sportCounts' => $sportCounts,
            'liveMatches' => $liveMatches,
            'topPlayer' => $this->topRankedPlayer($city),
            'selectedSport' => $selectedSport,
            'sportChips' => self::GAMEHUB_SPORTS,
            'popularVenues' => $popularVenues,
            'moreVenues' => $moreVenues,
        ]);
    }

    /**
     * Rank #1 for the Pulse "Top Player" widget — the app's LeaderboardHomeWidget,
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
     * Flatten a LiveMatch into a clean two-row scorecard for the Pulse live
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
            'id' => $m->id,
            'competition' => $m->competition,
            'home' => [
                'abbr' => $m->home,
                'name' => $m->home_full ?: $m->home,
                'logo' => (string) ($m->home_logo ?? ''),
                'emblem' => (string) ($m->home_emblem ?? ''),
                'score' => $homeBatted ? ($m->home_score.'/'.$homeWkts) : 'Yet to bat',
                'overs' => $battingHome ? $m->overs : '',
                'batting' => $battingHome,
            ],
            'away' => [
                'abbr' => $m->away,
                'name' => $m->away_full ?: $m->away,
                'logo' => (string) ($m->away_logo ?? ''),
                'emblem' => (string) ($m->away_emblem ?? ''),
                'score' => $awayBatted ? ($m->away_score.'/'.$awayWkts) : 'Yet to bat',
                'overs' => $battingHome ? '' : $m->overs,
                'batting' => ! $battingHome,
            ],
        ];
    }

    public function gamehubDetail(Request $request, string $id): View|RedirectResponse
    {
        $venue = Venue::published()
            ->with([
                'reviews' => fn ($q) => $q->where('is_active', true)->latest(),
                'partner.hostProfile',
                'slots',
                // Only courts that can be sold: checkout refuses an inactive one by name and
                // would fall back to a different court than the one the player picked.
                'courts' => fn ($q) => $q->where('is_active', true),
            ])
            ->findOrFail(SeoUrl::id($id));

        // Same single-URL rule as events: /gamehub/5 → /gamehub/5-venue-name.
        $canonicalSegment = SeoUrl::segment($venue, 'name');
        if ($id !== $canonicalSegment) {
            $qs = $request->getQueryString();

            return redirect()->to(url('/gamehub/'.$canonicalSegment).($qs ? '?'.$qs : ''), 301);
        }

        // Link to the owner's public page when they have a live one.
        $hostProfile = $venue->partner?->hostProfile;
        $hostProfile = $hostProfile && $hostProfile->isLive() ? $hostProfile : null;

        $decorated = $this->decorateVenueDetail($venue);
        $faqs = $this->venueFaqs($venue, $decorated);
        $seo = $this->venueSeo($venue, $decorated, $faqs);

        return view('site.gamehub-detail', [
            'hostProfile' => $hostProfile,
            'title' => $seo['title'],
            // Numeric — the page posts to /gamehub/{id}/book with it.
            'id' => (string) $venue->id,
            'venue' => $decorated,
            'venueFaqs' => $faqs,
            'venueSummary' => $this->venueSummary($venue, $decorated),
            'venueLinks' => $this->venueLinks($venue),
            'seo' => $seo,
        ]);
    }

    /**
     * Sport pages under /venues/{city}/{sport}, slug => label. The city hub only
     * answers these; a venue page links to the ones its own sports map onto.
     */
    private const CITY_VENUE_SPORTS = [
        'cricket' => 'Cricket',
        'box-cricket' => 'Box Cricket',
        'football' => 'Football',
        'badminton' => 'Badminton',
        'basketball' => 'Basketball',
        'tennis' => 'Tennis',
        'pickleball' => 'Pickleball',
        'volleyball' => 'Volleyball',
    ];

    /**
     * The venue's area ("Tadepalle") for titles and copy, or null when it's blank,
     * just the city again, or a full street address pasted into the field.
     */
    private static function venueArea(Venue $venue): ?string
    {
        $city = trim((string) $venue->city);
        $area = trim((string) $venue->location);
        if ($city !== '') {
            // "Benz Circle, Vijayawada" → "Benz Circle"
            $area = trim((string) preg_replace('/[,\s]*'.preg_quote($city, '/').'\s*$/iu', '', $area), " ,");
        }

        if ($area === '' || mb_strlen($area) > 40 || strcasecmp($area, $city) === 0) {
            return null;
        }

        // Typed all-lowercase in /control ("tadepalle") — it leads the page title.
        return mb_strtolower($area) === $area ? mb_convert_case($area, MB_CASE_TITLE) : $area;
    }

    /** The cheapest rate checkout charges ({@see Venue::fromPrice()}); 0 when unpriced. */
    private static function venueFromPrice(object $d): int
    {
        return (int) $d->from_price;
    }

    /**
     * A plain overview written from the venue's data (sports, courts, hours,
     * amenities, price) — shown under "About" next to the owner's own text, which
     * is often just a slogan. Nothing is stated that the venue hasn't set.
     */
    private function venueSummary(Venue $venue, object $d): string
    {
        $where = implode(', ', array_filter([self::venueArea($venue), trim((string) $venue->city)]));
        $sports = array_values(array_filter((array) $d->sports));
        $courtCount = array_sum(array_map('count', (array) $d->courts));
        $amenities = array_slice(array_values(array_filter((array) $d->amenities)), 0, 5);
        $from = self::venueFromPrice($d);
        $and = static fn (array $l): string => (string) Str::of(implode(', ', $l))->replaceLast(', ', ' and ');

        $lines = [
            $venue->name.($where !== '' ? " in {$where}" : '')
                .($sports !== [] ? ' offers '.$and($sports) : ' is a sports venue')
                .($courtCount > 1 ? " across {$courtCount} courts" : '').'.',
        ];
        if (filled($d->hours)) {
            $lines[] = "It is open {$d->hours}.";
        }
        if ($amenities !== []) {
            $lines[] = 'Facilities include '.$and($amenities).'.';
        }
        if ($from > 0) {
            $lines[] = 'Slots start from ₹'.number_format($from).' per hour'
                .($d->is_bookable ? ', and you can book online on Haraan with instant confirmation.' : '.');
        }

        return implode(' ', $lines);
    }

    /**
     * Internal links from a venue page: its sports' city pages, the city hub, and
     * other published venues in the same city.
     *
     * @return array{sports: list<array{label: string, url: string}>, nearby: list<array{name: string, area: ?string, url: string}>}
     */
    private function venueLinks(Venue $venue): array
    {
        $city = trim((string) $venue->city);
        if ($city === '') {
            return ['sports' => [], 'nearby' => []];
        }
        $citySlug = SeoUrl::city($city);

        $sports = [];
        foreach ($venue->sportsList() as $sport) {
            $slug = Str::slug($sport);
            if (isset(self::CITY_VENUE_SPORTS[$slug])) {
                $sports[$slug] = [
                    'label' => self::CITY_VENUE_SPORTS[$slug].' turfs in '.$city,
                    'url' => route('site.venues.city.sport', ['city' => $citySlug, 'sport' => $slug]),
                ];
            }
        }
        $sports[] = ['label' => 'All turfs in '.$city, 'url' => route('site.venues.city', ['city' => $citySlug])];

        $nearby = Venue::published()
            ->whereKeyNot($venue->id)
            ->whereRaw('lower(city) = ?', [Str::lower($city)])
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->limit(4)
            ->get()
            ->map(fn (Venue $v) => ['name' => $v->name, 'area' => self::venueArea($v), 'url' => SeoUrl::venue($v)])
            ->all();

        return ['sports' => array_values($sports), 'nearby' => $nearby];
    }

    /**
     * Local SEO landing page for sports venues by city and optional sport.
     * e.g., /venues/kadapa, /venues/kadapa/cricket, /venues/kadapa/badminton
     */
    public function cityVenues(string $city, ?string $sport = null): View
    {
        $cityRaw = trim(urldecode($city));
        $cityName = CityResolver::detect($cityRaw) ?: ucfirst($cityRaw);
        $citySlug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $cityName) ?: 'kadapa');

        $sportMap = self::CITY_VENUE_SPORTS;

        $sportSlug = $sport ? strtolower(trim(urldecode($sport))) : null;
        $sportLabel = $sportSlug && isset($sportMap[$sportSlug]) ? $sportMap[$sportSlug] : ($sportSlug ? ucfirst($sportSlug) : null);

        // Fetch published venues in this city
        $venuesQuery = Venue::published()
            ->where(function ($q) use ($cityName): void {
                $q->whereRaw('lower(city) = ?', [strtolower($cityName)])
                    ->orWhere('location', 'like', "%{$cityName}%")
                    ->orWhere('address', 'like', "%{$cityName}%");
            });

        if ($sportLabel !== null) {
            $sportCheck = $sportLabel === 'Box Cricket' ? 'cricket' : strtolower($sportLabel);
            $venuesQuery->where(function ($q) use ($sportCheck): void {
                $q->whereRaw('lower(category) = ?', [$sportCheck])
                    ->orWhere('sports', 'like', "%{$sportCheck}%");
            });
        }

        $venues = $venuesQuery->with(['courts', 'slots'])
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Venue $v) => $this->decorateVenueCard($v));

        // Featured / nearby venues fallback if city has few listings yet
        $featuredVenues = collect();
        if ($venues->count() < 3) {
            $featuredVenues = Venue::published()
                ->with(['courts', 'slots'])
                ->orderByDesc('is_featured')
                ->limit(6)
                ->get()
                ->map(fn (Venue $v) => $this->decorateVenueCard($v));
        }

        // Live / recent grassroots matches in this area for community engagement
        $localMatches = LiveMatch::query()
            ->where('is_private', false)
            ->where(function ($q) use ($cityName): void {
                $q->where('competition', 'like', "%{$cityName}%")
                    ->orWhere('home_full', 'like', "%{$cityName}%")
                    ->orWhere('away_full', 'like', "%{$cityName}%")
                    ->orWhere('home', 'like', "%{$cityName}%")
                    ->orWhere('away', 'like', "%{$cityName}%");
            })
            ->orderByDesc('created_at')
            ->limit(4)
            ->get();

        // High-intent SEO titles & descriptions
        $pageTitle = $sportLabel
            ? "Best {$sportLabel} Turfs in {$cityName} — Book Slots Online"
            : "Turfs in {$cityName} — Book Cricket, Box Cricket & Badminton Courts";

        $metaTitle = $sportLabel
            ? "{$sportLabel} Turfs in {$cityName} | Compare Slots & Prices | Haraan"
            : "Turfs in {$cityName} | Book Cricket, Box Cricket & Badminton | Haraan";

        $metaDesc = $sportLabel
            ? "Looking for {$sportLabel} turfs in {$cityName}? Compare currently available venues, hourly slot prices and facilities, then book online with Haraan."
            : "Looking for turfs in {$cityName}? Compare currently available cricket turfs, box cricket venues and badminton courts, then book online with Haraan.";

        $canonicalUrl = url('/venues/' . $citySlug . ($sportSlug ? '/' . $sportSlug : ''));

        $breadcrumbs = [
            ['name' => 'Haraan', 'url' => url('/')],
            ['name' => 'Pulse Venues', 'url' => route('site.gamehub')],
            ['name' => $cityName, 'url' => url('/venues/' . $citySlug)],
        ];
        if ($sportLabel) {
            $breadcrumbs[] = ['name' => $sportLabel];
        }

        // FAQs for rich snippet inclusion
        $faqs = [
            [
                'question' => "Where can I find turfs in {$cityName}?",
                'answer' => "Haraan lists currently available sports venues in {$cityName} in one place. Open a venue listing to check its location, sports, booking slots, facilities and current hourly price before you book.",
            ],
            [
                'question' => "How much does a turf cost per hour in {$cityName}?",
                'answer' => "Prices depend on the venue, sport, time and day. Haraan shows the current hourly rate on each venue listing, so you can compare options before confirming your slot.",
            ],
            [
                'question' => "Can I book a box cricket turf for an evening match in {$cityName}?",
                'answer' => "Choose a venue with a suitable available evening slot. Each listing shows its opening hours and facilities, including floodlights where the venue has provided that information.",
            ],
            [
                'question' => "How can sports turf owners in {$cityName} list their grounds on Haraan?",
                'answer' => "Venue managers and turf owners in {$cityName} can sign up via the Haraan Partner Portal to accept online slot reservations, manage schedules, and collect payments.",
            ],
        ];

        $faqSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(static fn (array $f) => [
                '@type' => 'Question',
                'name' => $f['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $f['answer'],
                ],
            ], $faqs),
        ];

        return view('site.city-venues', [
            'cityName' => $cityName,
            'citySlug' => $citySlug,
            'sportLabel' => $sportLabel,
            'sportSlug' => $sportSlug,
            'venues' => $venues,
            'featuredVenues' => $featuredVenues,
            'localMatches' => $localMatches,
            'faqs' => $faqs,
            'title' => $pageTitle,
            'seo' => [
                'title' => $metaTitle,
                'description' => $metaDesc,
                'canonical' => $canonicalUrl,
                // A city/sport with no venue of its own only shows the fallback rail —
                // a thin page that shouldn't compete in search until real listings exist.
                // Curated hubs (Kadapa: its own local guide, areas and FAQ) stay indexed
                // either way — that copy is the page's content, not the listings.
                'robots' => $venues->isEmpty() && ! in_array($citySlug, SeoUrl::CURATED_VENUE_CITIES, true)
                    ? 'noindex,follow'
                    : 'index,follow,max-image-preview:large',
                'breadcrumbs' => $breadcrumbs,
                'jsonld' => $faqSchema,
            ],
        ]);
    }

    /**
     * Search metadata for a venue page. `SportsActivityLocation` is the schema.org
     * type Google understands for turfs/courts — it carries the address, price
     * range and rating into the local result.
     *
     * @return array<string, mixed>
     */
    private function venueSeo(Venue $venue, object $d, array $faqs = []): array
    {
        $sports = implode(', ', array_slice($venue->sportsList(), 0, 3));
        $images = MediaUrl::resolveMany(is_array($venue->images) ? $venue->images : []);
        $image = $images[0] ?? null;
        $where = implode(', ', array_filter([self::venueArea($venue), trim((string) $venue->city)]));
        $from = self::venueFromPrice($d);

        // Searchers type "<venue> <area>", "box cricket turf <city>", "<venue> timings" —
        // the title and snippet carry those words. The owner's tagline is a slogan, so
        // it's no longer the snippet (it stays on the page).
        $title = $venue->name.($where !== '' ? ' - '.$where : '').' - Book Online';
        $desc = 'Book '.$venue->name.($where !== '' ? ', '.$where : '').' online.'
            .($sports !== '' ? ' '.$sports.($from > 0 ? ' from ₹'.number_format($from).'/hr' : '').'.' : '')
            .' Check photos, timings, amenities and live slot availability on Haraan.';

        $schema = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'SportsActivityLocation',
            'name' => $venue->name,
            'url' => SeoUrl::venue($venue),
            'description' => Str::limit($this->venueSummary($venue, $d), 600),
            'image' => count($images) > 1 ? array_slice($images, 0, 10) : $image,
            'openingHoursSpecification' => $venue->openingHoursSchema() ?: null,
            'amenityFeature' => array_values(array_map(static fn ($a) => [
                '@type' => 'LocationFeatureSpecification',
                'name' => (string) $a,
                'value' => true,
            ], array_filter((array) $d->amenities))) ?: null,
            'potentialAction' => $d->is_bookable ? [
                '@type' => 'ReserveAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => SeoUrl::venue($venue),
                    'inLanguage' => 'en-IN',
                    'actionPlatform' => [
                        'https://schema.org/DesktopWebPlatform',
                        'https://schema.org/MobileWebPlatform',
                        'https://schema.org/AndroidPlatform',
                    ],
                ],
                'result' => ['@type' => 'Reservation', 'name' => 'Book a slot at '.$venue->name],
            ] : null,
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
            'priceRange' => $from > 0 ? '₹'.number_format($from).'+ per hour' : null,
            // Ratings are derived from real, visible reviews only (Venue::refreshRating).
            'aggregateRating' => ($venue->ratings_count ?? 0) > 0 && (float) $venue->rating > 0 ? [
                '@type' => 'AggregateRating',
                'ratingValue' => (string) round((float) $venue->rating, 1),
                'ratingCount' => (int) $venue->ratings_count,
                'bestRating' => '5',
                'worstRating' => '1',
            ] : null,
        ], static fn ($v) => $v !== null && $v !== []);

        return [
            'title' => $title,
            'description' => $desc,
            'image' => $image,
            'canonical' => SeoUrl::venue($venue),
            'jsonld' => $faqs ? [$schema, self::faqSchema($faqs)] : $schema,
            'breadcrumbs' => array_values(array_filter([
                ['name' => 'Haraan', 'url' => url('/')],
                ['name' => 'Pulse', 'url' => url('/gamehub')],
                filled($venue->city)
                    ? ['name' => 'Turfs in '.trim((string) $venue->city), 'url' => route('site.venues.city', ['city' => SeoUrl::city((string) $venue->city)])]
                    : null,
                ['name' => $venue->name],
            ])),
        ];
    }

    /**
     * Questions a searcher actually types about a turf, answered from the venue's
     * own data — never filler. A question is skipped when the venue hasn't given us
     * the fact (no hours set → no "when is it open"). Rendered visibly on the page
     * AND as FAQPage markup; Google only honours FAQ markup that's on the page.
     *
     * @return list<array{question: string, answer: string}>
     */
    private function venueFaqs(Venue $venue, object $d): array
    {
        $name = $venue->name;
        $where = trim(implode(', ', array_filter([trim((string) $venue->location), trim((string) $venue->city)])));
        $faqs = [];

        $sports = array_values(array_filter((array) $d->sports));
        if ($sports !== []) {
            $courtCount = array_sum(array_map('count', (array) $d->courts));
            $faqs[] = [
                'question' => "Which sports can I play at {$name}?",
                'answer' => "{$name} hosts ".Str::of(implode(', ', $sports))->replaceLast(', ', ' and ')
                    .($courtCount > 1 ? " across {$courtCount} courts" : '')
                    .'. Pick the sport when you book a slot on Haraan.',
            ];
        }

        $from = self::venueFromPrice($d);
        if ($from > 0) {
            $faqs[] = [
                'question' => "How much does it cost to book {$name}?",
                'answer' => "Slots at {$name} start from ₹".number_format($from).' per hour'
                    .((array) $d->court_peak !== [] ? '; peak hours cost more' : '')
                    .'. Haraan shows the exact price for your court and time before you pay.',
            ];
        }

        if (filled($d->hours)) {
            $faqs[] = [
                'question' => "What are the timings of {$name}?",
                'answer' => "{$name} is open {$d->hours}. Open slots for the next 7 days are listed on this page.",
            ];
        }

        $address = trim((string) ($d->address ?: $where));
        if ($address !== '') {
            $faqs[] = [
                'question' => "Where is {$name} located?",
                'answer' => "{$name} is at {$address}. Use \"Get directions\" on this page to open it in Google Maps.",
            ];
        }

        if ($d->is_bookable) {
            $faqs[] = [
                'question' => "How do I book {$name} online?",
                'answer' => 'Tap Book Now, choose a date, court and slot, then pay online. Your booking is confirmed instantly on Haraan.',
            ];
        }

        return $faqs;
    }

    /**
     * @param list<array{question: string, answer: string}> $faqs
     * @return array<string, mixed>
     */
    private static function faqSchema(array $faqs): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(static fn (array $f) => [
                '@type' => 'Question',
                'name' => $f['question'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['answer']],
            ], $faqs),
        ];
    }

    /**
     * Local landing page for events in one city: /events/in/goa. Lists that city's
     * upcoming published events, then its recent past ones (they still answer
     * "what happens in Goa"), with an ItemList + FAQ for search. A city with no
     * published events at all 404s rather than serving an empty, thin page.
     */
    public function cityEvents(string $city): View
    {
        $slug = SeoUrl::city(urldecode($city));

        // The page exists for exactly the cities that have published events — the
        // stored spelling ("Bengaluru") is matched by its slug, so no alias table.
        $cityName = Event::query()
            ->whereRaw('lower(status) = ?', ['published'])
            ->whereNotNull('city')
            ->distinct()
            ->pluck('city')
            ->map(fn ($c) => trim((string) $c))
            ->first(fn (string $c) => $c !== '' && SeoUrl::city($c) === $slug);

        abort_if($cityName === null, 404);

        $base = fn () => Event::query()
            ->with('ticketTypes')
            ->whereRaw('lower(status) = ?', ['published'])
            ->whereRaw('lower(trim(city)) = ?', [mb_strtolower($cityName)]);

        $upcoming = $base()->notFinished()->orderBy('date')->limit(40)->get();
        $past = $base()->whereNotIn('id', $upcoming->modelKeys())
            ->orderByDesc('date')->limit(12)->get();

        $categories = $upcoming->pluck('category')->filter()->unique()->values()->all();
        $catPhrase = $categories !== []
            ? (string) Str::of(implode(', ', array_map('strtolower', array_slice($categories, 0, 3))))->replaceLast(', ', ' and ')
            : 'concerts, comedy and workshops';

        $faqs = [
            [
                'question' => "What events are happening in {$cityName}?",
                'answer' => $upcoming->isNotEmpty()
                    ? "Haraan lists {$upcoming->count()} upcoming ".Str::plural('event', $upcoming->count())." in {$cityName}, including "
                        .$upcoming->take(3)->pluck('title')->implode(', ').'. Open any event to see dates, prices and the venue.'
                    : "There are no upcoming events listed in {$cityName} right now. New events are added regularly — check back soon.",
            ],
            [
                'question' => "How do I book event tickets in {$cityName}?",
                'answer' => 'Pick an event, choose your ticket type and pay online with UPI or card. Your entry pass with a QR code arrives by email and in the Haraan app.',
            ],
            [
                'question' => "How can I list my event in {$cityName} on Haraan?",
                'answer' => "Organisers in {$cityName} can sign up through the Haraan Partner portal to publish events, sell tickets and check guests in at the door.",
            ],
        ];

        $itemList = $upcoming->isEmpty() ? null : [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => "Upcoming events in {$cityName}",
            'itemListElement' => $upcoming->values()->map(fn (Event $e, int $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'url' => SeoUrl::event($e),
                'name' => $e->title,
            ])->all(),
        ];

        return view('site.city-events', [
            // The layout appends " | Haraan" to this for the <title>.
            'title' => "Events in {$cityName} — Concerts, Comedy & Shows",
            'cityName' => $cityName,
            'citySlug' => $slug,
            'upcoming' => $upcoming,
            'past' => $past,
            'faqs' => $faqs,
            'catPhrase' => $catPhrase,
            'hasVenues' => Venue::published()->whereRaw('lower(trim(city)) = ?', [mb_strtolower($cityName)])->exists(),
            'seo' => [
                'title' => "Events in {$cityName} — Concerts, Comedy & Shows",
                'description' => Str::limit(
                    "Upcoming events in {$cityName}: {$catPhrase} and more. See dates, venues and ticket prices, then book online in seconds on Haraan.",
                    158
                ),
                'canonical' => route('site.events.city', ['city' => $slug]),
                // Only past events left → still worth a visit, but it shouldn't
                // compete for "events in <city>" until something is on again.
                'robots' => $upcoming->isEmpty() ? 'noindex,follow' : 'index,follow,max-image-preview:large',
                'breadcrumbs' => [
                    ['name' => 'Haraan', 'url' => url('/')],
                    ['name' => 'Events', 'url' => url('/events')],
                    ['name' => $cityName],
                ],
                'jsonld' => array_values(array_filter([$itemList, self::faqSchema($faqs)])),
            ],
        ]);
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

        $cLat = request()->cookie('haraan_lat');
        $cLng = request()->cookie('haraan_lng');
        if (is_numeric($cLat) && is_numeric($cLng)) {
            $lat = (float) $cLat;
            $lng = (float) $cLng;
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
        return $this->geocode($city.', India');
    }

    /**
     * Geocode a free-form place string to [lat, lng]. Delegates to the shared
     * {@see MatchGeocoder} so the web and app feeds resolve places identically.
     *
     * @return array{0: ?float, 1: ?float}
     */
    private function geocode(string $address): array
    {
        return (new MatchGeocoder)->geocode($address);
    }

    /**
     * Fill in a match's coordinates from its place names so MatchProximity can
     * measure true distance even though these rows were created without a GPS fix.
     * Delegates to the shared {@see MatchGeocoder} (same logic the app feed uses).
     */
    private function ensureMatchCoords(LiveMatch $m): void
    {
        (new MatchGeocoder)->ensureCoords($m);
    }

    public function actionBoard(): View
    {
        // Bounded: this used to load every match ever played (JSON squads and all) on
        // each page view. Private matches never surface in a public list.
        $matches = LiveMatch::query()
            ->where('is_private', false)
            ->orderByDesc('created_at')
            ->limit(60)
            ->get()
            ->toArray();

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
        $near = new MatchProximity(
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
                // Same rules as Api\LiveMatchController::index, so the web card can't read
                // differently from the app's. score_text is a CRICKET line ("120/4"); every
                // other sport derives home_score/away_score and keeps the whole scoreline
                // in score_text ("2 - 1"), which printed as team 1's score here.
                $isCricket = strtolower((string) ($m->sport ?: 'cricket')) === 'cricket';
                $setRally = ['', ''];
                if (! $isCricket) {
                    // A set sport's big number is sets won; the current game's points sit
                    // in the small slot cricket uses for overs ("1 (17)").
                    $state = is_array($m->sport_state) ? $m->sport_state : [];
                    $current = $state['current'] ?? null;
                    $points = $state['points'] ?? null;
                    if (is_array($points) && count($points) >= 2) {
                        $setRally = [(string) $points[0], (string) $points[1]];
                    } elseif (is_array($current) && count($current) >= 2 && ((int) $current[0] > 0 || (int) $current[1] > 0)) {
                        $setRally = [(string) $current[0], (string) $current[1]];
                    }
                }
                $scoreText = $isCricket ? (string) ($m->score_text ?: '') : '';
                $finished = $m->isFinished() || LiveMatch::statusMeansFinished((string) $m->status);

                return [
                    'id' => (string) $m->id,
                    // Which sport this match is — the key the app filters on ("table_tennis",
                    // not "Table Tennis"). Without it the web board could only ever pretend
                    // to switch sports, because every row looked like cricket.
                    'sport' => strtolower((string) ($m->sport ?: 'cricket')),
                    'team1' => (string) $m->home,
                    'team2' => (string) $m->away,
                    // The names people know the sides by; `home`/`away` are 2–3 letter codes.
                    'team1Full' => (string) ($m->home_full ?: $m->home),
                    'team2Full' => (string) ($m->away_full ?: $m->away),
                    'crr' => (string) ($m->crr ?? ''),
                    // Team crests: uploaded logo path/URL + default emblem key (action1..4)
                    // so the list can render real icons, not just monograms.
                    'team1Logo' => (string) ($m->home_logo ?? ''),
                    'team1Emblem' => (string) ($m->home_emblem ?? ''),
                    'team2Logo' => (string) ($m->away_logo ?? ''),
                    'team2Emblem' => (string) ($m->away_emblem ?? ''),
                    'score1' => ($battingTeam === 1 && $scoreText !== '') ? $scoreText : (string) ($m->home_score ?? 0),
                    'score2' => ($battingTeam === 2 && $scoreText !== '') ? $scoreText : (string) ($m->away_score ?? 0),
                    'overs1' => $isCricket ? ($battingTeam === 2 ? '' : $overs) : $setRally[0],
                    'overs2' => $isCricket ? ($battingTeam === 2 ? $overs : '') : $setRally[1],
                    'battingTeam' => $isCricket ? $battingTeam : 1,
                    'isCricket' => $isCricket,
                    'status' => (string) ($m->status ?? ''),
                    'venue' => (string) ($m->venue ?? ''),
                    'competition' => (string) ($m->competition ?? ''),
                    // In play = live and not ended (the app's isInPlay). Cricket writes its
                    // result into status, so "finished" is completed_at / a result line.
                    'isLive' => ! $finished && strtolower((string) $m->status) === 'live',
                    'isFinished' => $finished,
                    'isScheduled' => ! $finished && strtolower((string) $m->status) === 'scheduled',
                    'scheduledAt' => $m->scheduled_at?->toIso8601String(),
                    'visibility' => (string) ($m->visibility ?? LiveMatch::VIS_LOCAL),
                    'district' => (string) ($m->district ?? ''),
                    'locality' => (string) ($m->locality ?? ''),
                    'isMine' => $viewer !== null && (int) $m->user_id === (int) $viewer->id,
                    // Grouping hint only (see Api\LiveMatchController::index) —
                    // everyone sees every public match; false for guests.
                    'isLocalToViewer' => $viewer !== null
                        && (string) ($viewer->district ?? '') !== ''
                        && (string) $m->district === (string) $viewer->district,
                    'isFeatured' => (string) $m->visibility === LiveMatch::VIS_FEATURED,
                    'distanceKm' => ($d = $near->distanceKm($m)) === null ? null : round($d, 1),
                ];
            })
            ->values();

        // District Home snapshot + ranked-XP boards — same sources as the app's
        // District/State tabs. Guests (no district) get honest empty states.
        $districtSummary = null;
        $districtBoard = [];
        $stateBoard = [];
        $leaderboards = app(LeaderboardService::class);
        if ($viewer !== null && ! empty($viewer->district)) {
            $districtSummary = app(DistrictService::class)
                ->summary((string) $viewer->district, $viewer->state !== null ? (string) $viewer->state : null);
            $districtBoard = $leaderboards->monthly('district', null, (string) $viewer->district, 50);
        }
        if ($viewer !== null && ! empty($viewer->state)) {
            $stateBoard = $leaderboards->monthly('state', null, (string) $viewer->state, 50);
        }

        // Scheduled tab — the app's two lanes, read through the SAME API controllers the
        // app calls so the web can never disagree with it: "Mine" (the signed-in creator's
        // not-yet-started matches) and "Open near me" (public matches looking for players,
        // ranked by the same MatchProximity as the feed).
        $scheduledMine = [];
        if ($viewer !== null) {
            $req = Request::create('/api/matches/scheduled', 'GET');
            $req->attributes->set('auth_user', $viewer);
            $scheduledMine = (array) (app(MatchesController::class)->scheduled($req)->getData(true)['data'] ?? []);
        }
        $openReq = Request::create('/api/matches/open', 'GET', array_filter([
            'lat' => $viewerLat, 'lng' => $viewerLng,
            'district' => $viewer?->district, 'state' => $viewer?->state,
        ], fn ($v) => $v !== null && $v !== ''));
        $openReq->attributes->set('auth_user', $viewer);
        $scheduledOpen = (array) (app(MatchJoinController::class)->open($openReq)->getData(true)['data'] ?? []);

        return view('site.actionboard', [
            'title' => 'ActionBoard — Live Scores & Matches',
            'matches' => $matches,
            'abFeed' => $feed,
            'abScheduledMine' => $scheduledMine,
            'abScheduledOpen' => $scheduledOpen,
            'abDistrictSummary' => $districtSummary,
            'abDistrictBoard' => $districtBoard,
            'abStateBoard' => $stateBoard,
            'seo' => [
                'title' => 'ActionBoard — Live Cricket Scores & Grassroots Matches',
                'description' => 'Follow live gully cricket scores, ball-by-ball commentary, local match scorecards, and district leaderboards on Haraan ActionBoard.',
                'canonical' => route('site.gamehub.actionboard'),
                'breadcrumbs' => [
                    ['name' => 'Haraan', 'url' => url('/')],
                    ['name' => 'Pulse', 'url' => route('site.gamehub')],
                    ['name' => 'ActionBoard'],
                ],
            ],
        ]);
    }

    /**
     * The web ActionBoard match-detail pages mirror the app's Match Details screen
     * (Info · Commentary · Live · Scorecard). All four render from the SAME assembled
     * payload the app consumes — score + live crease + replayed innings cards +
     * commentary — via LiveMatchController::detailPayload().
     */
    /**
     * The match at {id}, if this visitor may see it — the same rule as the app's detail
     * endpoint. A private match is a 404 to anyone who isn't its creator or in its squads,
     * rather than a page anyone could open by counting ids.
     */
    private function visibleMatch(string $id): LiveMatch
    {
        $match = LiveMatch::query()->find((int) $id);
        $viewer = auth()->user();
        abort_if($match === null || ! $match->isVisibleTo($viewer instanceof User ? $viewer : null), 404);

        return $match;
    }

    private function matchDetailFor(string $id): array
    {
        $match = $this->visibleMatch($id);
        $viewer = auth()->user();

        return app(LiveMatchController::class)->detailPayload($match, $viewer);
    }

    /**
     * Search metadata for ActionBoard cricket and multisport match pages.
     *
     * @param array<string, mixed> $detail
     * @return array<string, mixed>
     */
    private function matchSeo(LiveMatch $match, array $detail, string $tabLabel = 'Live Score'): array
    {
        $homeName = trim((string) ($detail['team1Full'] ?? '')) ?: trim((string) ($detail['team1'] ?? '')) ?: ($match->home_full ?: $match->home ?: 'Home');
        $awayName = trim((string) ($detail['team2Full'] ?? '')) ?: trim((string) ($detail['team2'] ?? '')) ?: ($match->away_full ?: $match->away ?: 'Away');
        $comp = trim((string) ($detail['formatLabel'] ?? $match->competition ?? ''));
        $status = trim((string) ($detail['status'] ?? $match->status ?? ''));
        $sport = ucfirst((string) ($match->sport ?: 'Cricket'));

        $matchTitle = "{$homeName} vs {$awayName}";
        $fullTitle = "{$matchTitle} — {$tabLabel}" . ($comp !== '' ? " · {$comp}" : '');

        $desc = "Follow {$matchTitle} ({$sport}) on Haraan ActionBoard. "
            . ($status !== '' ? "Status: {$status}. " : '')
            . "Live ball-by-ball updates, commentary, scorecard, and match insights.";

        $schema = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'SportsEvent',
            'name' => $matchTitle . ($comp !== '' ? " — {$comp}" : ''),
            'sport' => $sport,
            'url' => route('site.gamehub.actionboard.match', ['id' => $match->id]),
            'homeTeam' => [
                '@type' => 'SportsTeam',
                'name' => $homeName,
            ],
            'awayTeam' => [
                '@type' => 'SportsTeam',
                'name' => $awayName,
            ],
            'eventStatus' => $match->status === 'Live'
                ? 'https://schema.org/EventScheduled'
                : ($match->status === 'Finished' ? 'https://schema.org/EventCompleted' : 'https://schema.org/EventScheduled'),
        ]);

        return [
            'title' => $fullTitle,
            'description' => Str::limit($desc, 158),
            'canonical' => route('site.gamehub.actionboard.match', ['id' => $match->id]),
            'robots' => $match->is_private ? 'noindex,nofollow' : 'index,follow,max-image-preview:large',
            'breadcrumbs' => [
                ['name' => 'Haraan', 'url' => url('/')],
                ['name' => 'ActionBoard', 'url' => route('site.gamehub.actionboard')],
                ['name' => $matchTitle],
            ],
            'jsonld' => $schema,
        ];
    }

    private function isCricket(LiveMatch $match): bool
    {
        return strtolower((string) ($match->sport ?: 'cricket')) === 'cricket';
    }

    /**
     * Every sport that isn't cricket gets its own page — the app's per-sport detail screen —
     * instead of cricket's overs, scorecard and last-ball hero with every number blank.
     */
    private function sportMatchView(LiveMatch $match, string $tab): View
    {
        // Team sports carry a Line-ups tab in the app (BoardLineups); racket sports don't.
        // The app's per-sport tab rows: football has Stats, basketball a Box score (which
        // is its player view, so an old ?tab=players link lands there).
        $tabs = match (strtolower((string) $match->sport)) {
            'football' => ['summary', 'stats', 'timeline', 'players', 'lineups', 'insights'],
            'basketball' => ['summary', 'box', 'timeline', 'lineups', 'insights'],
            'kabaddi', 'volleyball' => ['summary', 'timeline', 'players', 'lineups', 'insights'],
            default => ['summary', 'timeline', 'players', 'insights'],
        };
        if ($tab === 'players' && ! in_array('players', $tabs, true)) {
            $tab = 'box';
        }
        $tab = in_array($tab, $tabs, true) ? $tab : 'summary';
        $viewer = auth()->user();
        $member = $viewer instanceof User ? $viewer : null;

        // Advanced insights are a member plan feature per sport — same gate as the API, and
        // decided before SportInsights is built so a locked tab costs nothing.
        $insightsLock = null;
        if ($tab === 'insights') {
            try {
                app(SportInsightsAccess::class)->authorizeMatch($member, $match);
            } catch (EntitlementDenied $denied) {
                $insightsLock = ['message' => $denied->getMessage(), 'code' => $denied->reason, 'signed_in' => $member !== null];
            }
        }

        $homeName = $match->home_full ?: $match->home ?: 'Home';
        $awayName = $match->away_full ?: $match->away ?: 'Away';
        $sportTitle = "{$homeName} vs {$awayName}";
        $sportName = ucfirst((string) ($match->sport ?: 'Match'));

        return view('site.sport-match', [
            'title' => $sportTitle,
            'match' => $match,
            'detail' => app(LiveMatchController::class)->detailPayload($match, $viewer instanceof User ? $viewer : null),
            'players' => $tab === 'players' || $tab === 'summary'
                ? app(MatchPlayerStatsService::class)->forMatch($match)
                : [],
            'insights' => $tab === 'insights' && $insightsLock === null
                ? app(SportInsights::class)->for($match)
                : null,
            'insightsLock' => $insightsLock,
            'matchAd' => $this->matchAd($match),
            'tab' => $tab,
            'tabs' => $tabs,
            'seo' => [
                'title' => "{$sportTitle} — {$sportName} Match",
                'description' => "Follow {$sportTitle} on Haraan ActionBoard. Live {$sportName} scores, match stats, and player line-ups.",
                'canonical' => route('site.gamehub.actionboard.match', ['id' => $match->id]),
                'robots' => $match->is_private ? 'noindex,nofollow' : 'index,follow,max-image-preview:large',
                'breadcrumbs' => [
                    ['name' => 'Haraan', 'url' => url('/')],
                    ['name' => 'ActionBoard', 'url' => route('site.gamehub.actionboard')],
                    ['name' => $sportTitle],
                ],
            ],
        ]);
    }

    /** The live-board sponsor slot on the web, counted as an impression when shown. */
    private function matchAd(LiveMatch $match): ?Ad
    {
        return $this->usableAd('match_live');
    }

    public function actionBoardMatchLive(Request $request, string $id): View
    {
        $match = $this->visibleMatch($id);
        if (! $this->isCricket($match)) {
            return $this->sportMatchView($match, (string) $request->query('tab', 'summary'));
        }

        $detail = $this->matchDetailFor($id);
        $seo = $this->matchSeo($match, $detail, 'Live Score');

        return view('site.actionboard-match-live', [
            'title' => $seo['title'],
            'detail' => $detail,
            'id' => $id,
            'activeTab' => 'live',
            'seo' => $seo,
        ]);
    }

    public function actionBoardMatchInfo(string $id): View|RedirectResponse
    {
        $match = $this->visibleMatch($id);
        if (! $this->isCricket($match)) {
            return redirect()->route('site.gamehub.actionboard.match', ['id' => $id, 'tab' => 'summary']);
        }

        $detail = $this->matchDetailFor($id);
        $seo = $this->matchSeo($match, $detail, 'Match Info');

        return view('site.actionboard-match-info', [
            'title' => $seo['title'],
            'detail' => $detail,
            'id' => $id,
            'activeTab' => 'info',
            'seo' => $seo,
        ]);
    }

    public function actionBoardMatchCommentary(string $id): View|RedirectResponse
    {
        $match = $this->visibleMatch($id);
        if (! $this->isCricket($match)) {
            return redirect()->route('site.gamehub.actionboard.match', ['id' => $id, 'tab' => 'timeline']);
        }

        $detail = $this->matchDetailFor($id);
        $seo = $this->matchSeo($match, $detail, 'Commentary');

        return view('site.actionboard-match-commentary', [
            'title' => $seo['title'],
            'detail' => $detail,
            'id' => $id,
            'activeTab' => 'commentary',
            'seo' => $seo,
        ]);
    }

    /**
     * "Request to join" from the web Scheduled tab's Open-near-me lane — the session user
     * goes through the very same MatchJoinController action the app's JWT call does, so
     * every rule (own match, not open, already started, duplicate) is enforced once.
     */
    public function actionBoardJoin(Request $request, string $id): JsonResponse
    {
        $request->attributes->set('auth_user', $request->user());

        return app(MatchJoinController::class)->requestJoin($request, $id);
    }

    public function actionBoardCancelJoin(Request $request, string $id): JsonResponse
    {
        $request->attributes->set('auth_user', $request->user());

        return app(MatchJoinController::class)->cancelJoin($request, $id);
    }

    /**
     * Follow / unfollow a player from the web (MVP cards). The session user goes through
     * PlayersController's own actions, so self-follow, blocks and the follow-state
     * payload are exactly the app's.
     */
    public function followPlayer(Request $request, string $player): JsonResponse
    {
        $request->attributes->set('auth_user', $request->user());

        return app(\App\Http\Controllers\Api\PlayersController::class)->follow($request, $player);
    }

    public function unfollowPlayer(Request $request, string $player): JsonResponse
    {
        $request->attributes->set('auth_user', $request->user());

        return app(\App\Http\Controllers\Api\PlayersController::class)->unfollow($request, $player);
    }

    public function actionBoardMatchMvp(string $id): View|RedirectResponse
    {
        $match = $this->visibleMatch($id);
        if (! $this->isCricket($match)) {
            return redirect()->route('site.gamehub.actionboard.match', ['id' => $id, 'tab' => 'players']);
        }

        $detail = $this->matchDetailFor($id);
        $seo = $this->matchSeo($match, $detail, 'MVP & Top Performers');

        return view('site.actionboard-match-mvp', [
            'title' => $seo['title'],
            'detail' => $detail,
            'id' => $id,
            'activeTab' => 'mvp',
            'seo' => $seo,
        ]);
    }

    /**
     * Cricket Insights — the app's Insights board. Same plan gate as the API
     * (SportInsightsAccess), checked before CricketInsights replays anything, so a
     * locked viewer costs nothing. The figures are CricketInsights::facts(), the very
     * payload GET /api/live-matches/{id}/insights sends the app.
     */
    public function actionBoardMatchInsights(string $id): View|RedirectResponse
    {
        $match = $this->visibleMatch($id);
        if (! $this->isCricket($match)) {
            return redirect()->route('site.gamehub.actionboard.match', ['id' => $id, 'tab' => 'insights']);
        }
        $viewer = auth()->user();
        $member = $viewer instanceof User ? $viewer : null;

        $lock = null;
        $facts = null;
        try {
            app(SportInsightsAccess::class)->authorizeMatch($member, $match);
            $facts = app(\App\Services\CricketInsights::class)->facts($match);
        } catch (EntitlementDenied $denied) {
            $lock = ['message' => $denied->getMessage(), 'code' => $denied->reason, 'signed_in' => $member !== null];
        }

        // The ground card is public (GET /api/matches/{id}/ground sits outside the plan
        // gate) — the app shows it even on a locked or not-yet-scored Insights tab.
        $ground = null;
        try {
            $req = Request::create('/api/matches/'.$match->id.'/ground', 'GET');
            $req->attributes->set('auth_user', $member);
            $ground = app(MatchesController::class)->ground($req, (string) $match->id)->getData(true)['data'] ?? null;
        } catch (\Throwable $e) {
            report($e);
        }

        $detail = $this->matchDetailFor($id);
        $seo = $this->matchSeo($match, $detail, 'Match Insights');

        return view('site.actionboard-match-insights', [
            'ground' => $ground,
            'title' => $seo['title'],
            'detail' => $detail,
            'id' => $id,
            'activeTab' => 'insights',
            'insights' => $facts,
            'insightsLock' => $lock,
            'seo' => $seo,
        ]);
    }

    public function actionBoardMatchScorecard(string $id): View|RedirectResponse
    {
        $match = $this->visibleMatch($id);
        if (! $this->isCricket($match)) {
            return redirect()->route('site.gamehub.actionboard.match', ['id' => $id, 'tab' => 'players']);
        }

        $detail = $this->matchDetailFor($id);
        $seo = $this->matchSeo($match, $detail, 'Full Scorecard');

        return view('site.actionboard-match-scorecard', [
            'title' => $seo['title'],
            'detail' => $detail,
            'id' => $id,
            'activeTab' => 'scorecard',
            'seo' => $seo,
        ]);
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
        $user = User::where('player_id', $playerId)->first();
        if ($user) {
            return response()->json([
                'success' => true,
                'name' => $user->name,
                'role' => $user->player_role ?? 'Unknown',
                'style' => $user->playing_style ?? 'Unknown',
            ]);
        }

        return response()->json(['success' => false]);
    }

    public function showPlayerProfile(string $player_id): View
    {
        $player = User::where('player_id', $player_id)->firstOrFail();

        // Find recent matches where this player is in the squad
        // LIKE on the squad JSON narrows the scan in SQL (a superset: the exact id check
        // below still decides); without it every match ever was loaded per profile view.
        $needle = '%'.$player_id.'%';
        $allMatches = LiveMatch::query()
            ->where(fn ($q) => $q->where('home_squad', 'like', $needle)->orWhere('away_squad', 'like', $needle))
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();
        $recentMatches = [];

        foreach ($allMatches as $match) {
            $inHome = is_array($match->home_squad) && collect($match->home_squad)->contains(function ($p) use ($player_id) {
                $id = is_array($p) ? ($p['id'] ?? null) : $p;

                return (string) $id === (string) $player_id;
            });
            $inAway = is_array($match->away_squad) && collect($match->away_squad)->contains(function ($p) use ($player_id) {
                $id = is_array($p) ? ($p['id'] ?? null) : $p;

                return (string) $id === (string) $player_id;
            });

            if ($inHome || $inAway) {
                $recentMatches[] = $match;
            }
        }

        $playerRole = filled($player->player_role) ? (string) $player->player_role : 'Cricket Player';
        $locationBits = array_filter([$player->district, $player->state]);
        $locationStr = implode(', ', $locationBits);
        $desc = "{$player->name} ({$playerRole})"
            . ($locationStr !== '' ? " from {$locationStr}" : '')
            . '. View career cricket stats, recent match performance, and rankings on Haraan.';

        $avatarUrl = \App\Support\MediaUrl::resolve($player->avatar);

        return view('site.player-profile', [
            'title' => $player->name.' — Cricket Profile',
            'player' => $player,
            'recentMatches' => $recentMatches,
            'seo' => [
                'title' => $player->name.' — Cricket Stats & Ranking',
                'description' => Str::limit($desc, 158),
                'canonical' => url('/player/'.$player->player_id),
                'breadcrumbs' => [
                    ['name' => 'Haraan', 'url' => url('/')],
                    ['name' => 'Leaderboard', 'url' => url('/gamehub/leaderboard')],
                    ['name' => $player->name],
                ],
                'jsonld' => array_filter([
                    '@context' => 'https://schema.org',
                    '@type' => 'Person',
                    'name' => $player->name,
                    'jobTitle' => $playerRole,
                    'url' => url('/player/'.$player->player_id),
                    'image' => $avatarUrl ?: null,
                    'address' => $locationStr !== '' ? array_filter([
                        '@type' => 'PostalAddress',
                        'addressLocality' => $player->district ?: null,
                        'addressRegion' => $player->state ?: null,
                        'addressCountry' => 'IN',
                    ]) : null,
                ]),
            ],
        ]);
    }

    public function search(): View
    {
        $query = request()->input('q', '');
        $type = request()->input('type', 'all');

        $city = CityResolver::selected();
        $results = [];
        if ($query !== '') {
            $like = '%'.$query.'%';

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
                        'id' => $e->id,
                        'title' => $e->title,
                        'category' => $e->category ?: 'Event',
                        'venue' => $e->venue ?: 'Mumbai',
                    ])->all();
                }
            }

            if ($type === 'all' || $type === 'venues') {
                $venues = Venue::published()
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
                        'id' => $v->id,
                        'title' => $v->name,
                        'sport' => $v->category ?: 'Sport',
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
        $like = '%'.$query.'%';

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
                'id' => $e->id,
                'title' => $e->title,
                'meta' => trim(($e->category ?: 'Event').' · '.($e->venue ?: 'Mumbai'), ' ·'),
                'url' => SeoUrl::event($e),
            ])->all();

        $venues = Venue::published()
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
                'id' => $v->id,
                'title' => $v->name,
                'meta' => trim(($v->category ?: 'Venue').' · '.($v->location ?: 'Mumbai'), ' ·'),
                'url' => SeoUrl::venue($v),
            ])->all();

        return response()->json(['events' => $events, 'venues' => $venues]);
    }

    /* ------------------------------------------------------------------ */
    /*  Leaderboard (batting / bowling career boards) */
    /* ------------------------------------------------------------------ */

    public function leaderboard(): View
    {
        $scope = request()->query('scope', 'country');
        if (! in_array($scope, ['country', 'state', 'district'], true)) {
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

        $selectedState = request()->query('state', $states[0] ?? 'Andhra Pradesh');
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
            'title' => 'Leaderboard',
            'scope' => $scope,
            'states' => $states,
            'districts' => $districts,
            'selectedState' => $selectedState,
            'selectedDistrict' => $selectedDistrict,
            'battingLeaderboard' => $battingLeaderboard,
            'bowlingLeaderboard' => $bowlingLeaderboard,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  ActionBoard match JSON (polled by the live scoreboards) */
    /* ------------------------------------------------------------------ */

    /**
     * The match as the app's detail endpoint serves it. This used to return the raw row —
     * any match, private ones included, with its share code — to anyone who asked.
     */
    public function actionBoardMatchJson(string $id): JsonResponse
    {
        return response()->json($this->matchDetailFor($id));
    }

    /**
     * Public matches only, newest first, and never the columns that unlock a private match
     * or identify its players. The raw table dump that stood here leaked every join code.
     */
    public function actionBoardMatchesJson(): JsonResponse
    {
        $matches = LiveMatch::query()
            ->where('is_private', false)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get(['id', 'title', 'sport', 'home', 'away', 'home_full', 'away_full', 'home_score',
                'away_score', 'score_text', 'status', 'venue', 'competition', 'created_at', 'completed_at']);

        return response()->json($matches);
    }

    /* ------------------------------------------------------------------ */
    /*  Player directory (squad builder + profile-setup claiming) */
    /* ------------------------------------------------------------------ */

    public function searchPlayers(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $handle = User::normalizeUsername(ltrim($q, '@'));

        $players = User::query()
            ->where('is_guest', false)
            // Same rule as the app's /players/find: a player who switched discovery off
            // must not surface in a directory search. Null means "predates the toggle",
            // which is treated as discoverable.
            ->where(function ($w) {
                $w->whereNull('privacy_discoverable')->orWhere('privacy_discoverable', true);
            })
            ->where(function ($w) use ($q, $handle) {
                $w->where('name', 'like', "%{$q}%")
                    ->orWhere('player_id', 'like', "%{$q}%");
                if ($handle !== '') {
                    $w->orWhere('username', 'like', "%{$handle}%");
                }
            })
            ->orderBy('name')
            ->limit(10)
            ->get();

        // 'username' is additive — existing website callers keep reading the same keys.
        return response()->json($players->map(fn (User $u) => [
            'id' => $u->player_id,
            'username' => $u->username,
            'name' => $u->name,
            'role' => $u->player_role ?: 'Player',
            'style' => $u->batting_style ?: ($u->playing_style ?: ''),
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
            'name' => $name,
            'email' => 'guest_'.Str::random(16).'@guest.haraan',
            'password' => Hash::make(Str::random(24)),
            'role' => 'user',
            'status' => 'active',
            'is_guest' => true,
            'player_role' => 'All-rounder',
            'playing_style' => 'Unknown',
        ]);

        return response()->json([
            'success' => true,
            'id' => $guest->player_id,
            'name' => $guest->name,
            'role' => $guest->player_role,
            'style' => $guest->playing_style,
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
                ? ($match->title ?: trim(($match->home ?? '').' vs '.($match->away ?? '')))
                : 'Guest match record';

            return [
                'id' => $g->id,
                'name' => $g->name,
                'player_id' => $g->player_id,
                'played_with' => $playedWith !== 'vs' ? $playedWith : 'Guest match record',
            ];
        })->all());
    }

    /* ------------------------------------------------------------------ */
    /*  ActionBoard profile setup (cricket onboarding) */
    /* ------------------------------------------------------------------ */

    public function showProfileSetupForm(): View
    {
        return view('site.profile-setup', [
            'title' => 'Complete Your Profile',
            'user' => auth()->user(),
        ]);
    }

    public function saveProfileSetup(Request $request)
    {
        $user = auth()->user();
        if (! $user) {
            return redirect()->route('site.login');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'state' => 'required|string|max:255',
            'district' => 'required|string|max:255',
            'batting_style' => 'nullable|string|max:100',
            'bowling_style' => 'nullable|string|max:100',
            'claim_user_id' => 'nullable|integer',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
        ]);

        // Merge a claimed guest profile's career stats into this account.
        if (! empty($validated['claim_user_id'])) {
            $guest = User::query()
                ->where('id', $validated['claim_user_id'])
                ->where('is_guest', true)
                ->first();

            if ($guest) {
                $user->career_runs += (int) $guest->career_runs;
                $user->career_balls += (int) $guest->career_balls;
                $user->career_matches += (int) $guest->career_matches;
                $user->career_wickets += (int) $guest->career_wickets;
                $user->career_runs_conceded += (int) $guest->career_runs_conceded;
                $guest->delete();
            }
        }

        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('avatars', 'public');
            $user->avatar = '/storage/'.$path;
        }

        $user->name = $validated['name'];
        $user->state = $validated['state'];
        $user->district = $validated['district'];
        $user->batting_style = $validated['batting_style'] ?? $user->batting_style;
        $user->bowling_style = $validated['bowling_style'] ?? $user->bowling_style;
        $user->player_role = $user->player_role ?: 'All-rounder';
        $user->primary_sport = $user->primary_sport ?: 'Cricket';

        $attrs = $user->sport_attributes ?? [];
        $attrs['role'] = $attrs['role'] ?? $user->player_role;
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
    /*  Pulse venue view-model helpers */
    /* ------------------------------------------------------------------ */

    /** Normalize a stored venue image path into a usable URL. */
    private function venueImageUrl(?string $path): string
    {
        if (! $path) {
            return asset('gamehub.png');
        }
        if (preg_match('/^(http|https):\/\//', $path) || str_starts_with($path, '/')) {
            return $path;
        }

        // The 1600px WebP copy when it exists; views add the other widths as a srcset.
        return \App\Support\VenuePhoto::url($path) ?? asset('storage/'.ltrim($path, '/'));
    }

    /** Compact venue shape for the Pulse browse grid. */
    private function decorateVenueCard(Venue $v): object
    {
        $images = is_array($v->images) ? $v->images : [];

        return (object) [
            'id' => $v->id,
            'title' => $v->name,
            'image' => ! empty($images) ? $this->venueImageUrl($images[0]) : null,
            'location' => $v->location,
            'city' => $v->city,
            'distance' => $v->distance,
            'category' => $v->category,
            'rating' => ($v->ratings_count > 0 && $v->rating) ? $v->rating : null,
            'reviews' => (int) ($v->reviews_count ?? 0),
            // What checkout really charges at its cheapest — never the bare venue price,
            // which a slot or court price overrides ({@see Venue::fromPrice()}).
            'price' => $v->fromPrice(),
            'badge' => $v->is_featured ? 'Featured' : null,
            // The app's cards carry these two (VenueItem.tagline / .sports); the web card
            // shape predates them. Additive — the desktop markup ignores both.
            'tagline' => (string) ($v->tagline ?? ''),
            'sports' => $v->sportsList(),
            // Facts for the mobile reel card, all read from the venue's own setup:
            // how many courts it sells, its cheapest court rate, and today's hours.
            ...$this->venueCardFacts($v),
        ];
    }

    /**
     * Court count, lowest hourly rate and an "open now" line for a venue card.
     * Hours come from the venue's structured week (venue clock, IST), so the line
     * says exactly what the slot board will offer — no hours set means no line.
     *
     * @return array{courts: int, from_price: int, open_state: ?string, open_now: bool}
     */
    private function venueCardFacts(Venue $v): array
    {
        $courts = $v->courts->where('is_active', true);
        $from = $v->fromPrice();

        $state = null;
        $openNow = false;
        if (is_array($v->hours_json) && $v->hours_json !== []) {
            $now = \App\Support\BusinessClock::now();
            $minute = (int) $now->format('G') * 60 + (int) $now->format('i');
            $label = static fn (int $m): string => $m >= 1440 ? 'midnight' : date('g:i A', mktime(0, $m));
            $short = static fn (string $l): string => str_replace(':00 ', ' ', $l);

            foreach ($v->windowsForWeekday($now->format('D')) as [$open, $close]) {
                if ($minute >= $open && $minute < $close) {
                    $state = 'Open till ' . $short($label($close));
                    $openNow = true;
                    break;
                }
                if ($minute < $open) {
                    $state = 'Opens ' . $short($label($open));
                    break;
                }
            }
            $state ??= 'Closed now';
        }

        return ['courts' => $courts->count(), 'from_price' => $from, 'open_state' => $state, 'open_now' => $openNow];
    }

    /** Great-circle distance in km between two lat/lng points (haversine, Earth R = 6371 km). */
    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng / 2) * sin($dLng / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }

    /** Human distance: "800 m away" under 1 km, "1.2 km away" under 10, "12 km away" beyond. */
    private function formatKm(float $km): string
    {
        if ($km < 1.0) {
            $m = (int) max(50, round($km * 1000));
            return "{$m} m away";
        }
        if ($km < 10.0) {
            $formatted = number_format($km, 1);
            return "{$formatted} km away";
        }
        $intKm = (int) round($km);
        return "{$intKm} km away";
    }

    /** Full venue shape for the Pulse detail page. */
    private function decorateVenueDetail(Venue $v): object
    {
        $images = is_array($v->images) ? $v->images : [];
        $gallery = array_values(array_map(
            fn ($p) => $this->venueImageUrl($p),
            array_slice($images, 1)
        ));

        $amenities = is_array($v->amenities) ? $v->amenities : [];

        // Group actual courts by the sports each hosts.
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

        // Per-court "from" rate keyed by court name — the cheapest that court really sells at,
        // slot prices included ({@see Venue::fromPriceFor()}). It was the bare court/venue
        // price, so a court card said ₹781 while every slot on it charged ₹800.
        $courtPrices = [];
        $courtPeak = [];
        foreach ($v->courts as $c) {
            $courtPrices[$c->name] = $v->fromPriceFor($c);
            // Peak pricing for the scheduler: applied by time window (evenings cost more). The
            // backend stays authoritative on weekday precision when a booking is actually made.
            if ($c->peak_price !== null && (int) $c->peak_price > 0 && $c->peak_start !== null && $c->peak_end !== null) {
                $courtPeak[$c->name] = [
                    'price' => (int) $c->peak_price,
                    'start' => $c->peak_start,
                    'end' => $c->peak_end,
                ];
            }
        }

        // The admin's own slot rows for each day the date strip offers (/control → venue →
        // slots, or generated from its hours). The scheduler renders exactly these and asks
        // /api/venues/{id}/availability which are taken; it used to draw a fixed 6 AM–11 PM
        // grid with a random 30% marked "Reserved", whatever the venue had set up.
        $step = max(30, (int) ($v->slot_minutes ?: 60));
        $label = static fn (int $m): string => Carbon::today()->addMinutes($m)->format('h:i A');
        $venuePrice = (int) ($v->price ?? 0);
        $slotsByDate = [];
        for ($i = 0; $i < 7; $i++) {
            $date = BusinessClock::todayDate()->addDays($i);
            $slotsByDate[$date->toDateString()] = $v->slotsOn($date)->map(function ($s) use ($step, $label, $v, $date, $venuePrice) {
                $start = BookingService::timeToMinutes($s->time);
                if ($start === null) {
                    return null;
                }
                $end = min(24 * 60, $start + $step);

                // Each court's rate for this slot on this date, by the same rateFor() the
                // review page and checkout charge (slot price, per-court price, peak days,
                // pricing rules). The board used to re-derive it in JS from the court rate
                // and peak window alone, and showed a different price than it charged.
                $rates = [];
                foreach ($v->courts as $c) {
                    if ($s->allowsCourt($c)) {
                        $rates[$c->name] = $c->rateFor($date->copy(), $s->time, $venuePrice, $s->priceForCourt($c));
                    }
                }
                if ($v->courts->isEmpty()) {
                    $rates[''] = max(0, (int) ((float) $s->price > 0 ? $s->price : $venuePrice));
                }

                return [
                    'id' => (int) $s->id,
                    'start' => $start,
                    'time' => $label($start).' - '.$label($end),
                    'sports' => $s->sportsList(),
                    'open' => (bool) $s->is_available,
                    'rates' => (object) $rates,
                ];
            })->filter()->values()->all();
        }
        $courtIds = $v->courts->mapWithKeys(fn ($c) => [$c->name => (int) $c->id])->all();

        $reviewsList = $v->reviews->map(fn ($r) => (object) [
            'user' => $r->name,
            'date' => $r->agoLabel(),
            'rating' => (int) $r->rating,
            'comment' => $r->text ?: '',
            'played' => $r->isFromBooker(),
        ])->all();
        // The signed-in viewer's own review (visible or hidden), to prefill RATE VENUE.
        $mine = auth()->check()
            ? \App\Models\VenueReview::query()->where('venue_id', $v->id)->where('user_id', auth()->id())->first()
            : null;

        return (object) [
            'id' => $v->id,
            'title' => $v->name,
            'image' => ! empty($images) ? $this->venueImageUrl($images[0]) : null,
            'gallery' => $gallery,
            'location' => $v->location,
            'category' => $v->category,
            'rating' => ($v->ratings_count > 0 && $v->rating) ? $v->rating : null,
            'reviews' => (int) ($v->reviews_count ?? 0),
            'reviews_list' => $reviewsList,
            'my_review' => $mine ? ['rating' => (int) $mine->rating, 'text' => (string) $mine->text] : null,
            'is_owner' => auth()->check() && (int) $v->partner_id === (int) auth()->id(),
            'price' => (int) ($v->price ?? 0),
            // The "from ₹X/hr" everywhere on the page (bar, sidebar, About) — what checkout
            // really charges at its cheapest, not the bare venue price.
            'from_price' => $v->fromPrice(),
            // The booking sheet estimates the total from these; the server recomputes it.
            'convenience_fee_type' => (string) ($v->convenience_fee_type ?? 'none'),
            'convenience_fee_value' => (float) ($v->convenience_fee_value ?? 0),
            // Every fee (convenience + the admin's named ones), for the same estimate.
            'fee_rules' => $v->feeRules(),
            // Falls back to the slot times when the weekly hours aren't set.
            'hours' => $v->publicHours(),
            // Mon…Sun [open, close] minutes — drawn as the desktop page's week of hours.
            'week' => $v->publicWeek(),
            'cancellation' => $v->cancellationText(),
            // The app's VenueDetailScreen reads these (address line, "Show in Map"/
            // "Get directions", the "Good to know" checklist, the rating summary);
            // the web shape predates them. Additive — the desktop markup ignores them.
            'address' => (string) ($v->address ?? ''),
            'latitude' => $v->latitude,
            'longitude' => $v->longitude,
            'map_link' => (string) ($v->map_link ?? ''),
            'rules' => is_array($v->rules) ? array_values(array_filter($v->rules)) : [],
            'ratings_count' => (int) ($v->ratings_count ?? 0),
            'is_bookable' => (bool) ($v->is_bookable && $v->isPublished()),
            'badge' => $v->is_featured ? 'Featured' : null,
            'description' => (string) ($v->about ?? ''),
            'amenities' => $amenities,
            'sports' => $sports,
            'courts' => $courts,
            'court_prices' => $courtPrices,
            'court_peak' => $courtPeak,
            'court_ids' => $courtIds,
            'slots_by_date' => $slotsByDate,
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
     * @param  Builder  $query
     * @return Builder
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
     * The Pulse sport chip in play. Whitelisted against the app's own chip row
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

        foreach (array_merge(self::GAMEHUB_SPORTS, self::GAMEHUB_FILTER_SPORTS) as $known) {
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
        // Ad-free is a member plan feature, and it covers the website as well as the app
        // (the app's /api/ads already honours it). No impression is counted either.
        $viewer = auth()->user();
        if ($viewer instanceof User
            && app(MemberEntitlements::class)->allows($viewer, MemberFeature::ADS_HIDDEN)) {
            return null;
        }

        $ad = Ad::query()
            ->serving($placement)
            ->orderBy('sort_order')
            ->first();

        if ($ad === null || trim((string) $ad->title) === '') {
            return null;
        }

        // Needs something to look at or somewhere to go — otherwise it's just noise.
        $hasImage = trim((string) $ad->image_url) !== '';
        $hasLink = trim((string) $ad->link_url) !== '';

        if (! ($hasImage || $hasLink)) {
            return null;
        }

        // The page render IS the impression on the web — one per session per 30 minutes.
        app(AdTracker::class)->impression(
            $ad, $placement, 'web', request()->hasSession() ? request()->session()->getId() : null, auth()->id(),
        );

        return $ad;
    }

    /**
     * GET /go/ad/{id}?p=placement — a web ad click: count it, then send the viewer on to the
     * advertiser. Only an http(s) destination on a serving ad is followed.
     */
    public function adClick(Request $request, string $id): RedirectResponse
    {
        $ad = Ad::query()->find((int) $id);
        $target = $ad?->link_url;
        if ($ad === null || $target === null || ! $ad->isServing()) {
            return redirect('/events');
        }

        app(AdTracker::class)->click(
            $ad, (string) $ad->placement, 'web',
            $request->hasSession() ? $request->session()->getId() : null, auth()->id(),
        );

        return redirect()->away($target);
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
            'href' => '/events',
            'stat' => Event::query()->where('status', 'published')->count().' Total',
            'on' => $active === 'All',
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
                'href' => '/events?category='.urlencode((string) $row->category),
                'stat' => $row->total.' '.($row->total === 1 ? 'Event' : 'Events'),
                'on' => strcasecmp($active, (string) $row->category) === 0,
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
                // See eventFeed(): fromPrice() reads the relation, so eager-load it.
                ->with('ticketTypes')
                ->notFinished()
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
                // Cards price off the tiers via Event::fromPrice(); without this the
                // rail is one query per card.
                ->with('ticketTypes')
                ->notFinished()
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
