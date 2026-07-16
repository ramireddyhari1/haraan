@extends('site.layout')
@section('content')
@php
    $activeCategory = request()->query('category', 'All');
    $featuredEvent = $events->first();
    $listedEvents = $events->slice(1)->values();
    $mEvents = $events;
    // "Trending" is real: events ranked by actual ticket sales (from the
    // controller). Empty until there's genuine demand — the section below
    // hides itself rather than faking a trending list.
    $mTrending = $trending ?? collect();
    $catCount = fn($c) => $events->where('category', $c)->count();
    $categoryCards = [
        ['title' => 'All', 'iconImage' => 'https://cdn-icons-png.flaticon.com/512/4784/4784185.png', 'iconAlt' => 'All inclusive', 'href' => '/events', 'from' => '#fff5d8', 'to' => '#ffe4a4', 'accent' => '#d08a00', 'selected' => true],
        ['title' => 'Concerts', 'iconImage' => 'https://cdn-icons-png.flaticon.com/512/8295/8295821.png', 'iconAlt' => 'Stage', 'href' => '/events?category=Concerts', 'from' => '#edf2ff', 'to' => '#d7ddff', 'accent' => '#4b63e6'],
        ['title' => 'Workshops', 'iconImage' => 'https://cdn-icons-png.flaticon.com/512/1535/1535041.png', 'iconAlt' => 'Round table', 'href' => '/events?category=Workshops', 'from' => '#fff0dc', 'to' => '#ffe0b7', 'accent' => '#c86f00'],
        ['title' => 'Nightlife', 'iconImage' => 'https://cdn-icons-png.flaticon.com/512/10422/10422585.png', 'iconAlt' => 'Drink', 'href' => '/events?category=Nightlife', 'from' => '#fff7d9', 'to' => '#ffe5a6', 'accent' => '#c38300', 'size' => 'large'],
        ['title' => 'Comedy', 'iconImage' => 'https://cdn-icons-png.flaticon.com/512/8149/8149311.png', 'iconAlt' => 'Comedy', 'href' => '/events?category=Comedy', 'from' => '#fff1df', 'to' => '#ffd5a9', 'accent' => '#d06a00', 'size' => 'large'],
        ['title' => 'Festivals', 'iconImage' => 'https://cdn-icons-png.flaticon.com/512/3268/3268817.png', 'iconAlt' => 'Festivals', 'href' => '/events?category=Festivals', 'from' => '#ffeef1', 'to' => '#ffd7e1', 'accent' => '#c04b72'],
    ];
@endphp

<link rel="stylesheet" href="/css/banner.css">

{{-- ============================================================= --}}
{{-- MOBILE APP-STYLE EVENTS HOME (mirrors the Android app; ≤720px) --}}
{{-- ============================================================= --}}
<div class="mhome">
    {{-- Centered brand lockup between the tabs and the feed (user-placed;
         replaces the old top strip so the wordmark appears exactly once). --}}
    <div class="mbrandmark" aria-hidden="true">
        <img src="{{ asset('images/haraan-logo.png') }}" alt="Haraan">
        <span>Discover. Book. Play.</span>
    </div>

    {{-- The app's feed opens with the sponsored slot (AdSpaceBanner), NOT a greeting —
         the header already says hello, and a second "Hello 👋 / Discover in <city>" was
         spending the most valuable space on the page saying it twice.

         Rendered only when the admin's creative is actually usable: the app falls back
         to a bundled sample ad, which on the web would be inventing an advertiser. An
         empty slot is honest; a fake one isn't. --}}
    @if($eventsAd)
        <a class="mad" @if($eventsAd->link_url) href="{{ $eventsAd->link_url }}" rel="sponsored noopener" @endif>
            @if($eventsAd->image_url)
                <img class="mad__img" src="{{ $eventsAd->image_url }}" alt="" fetchpriority="high">
            @endif
            <span class="mad__body">
                <span class="mad__label">Sponsored</span>
                <strong class="mad__title">{{ $eventsAd->title }}</strong>
                @if($eventsAd->subtitle)<span class="mad__sub">{{ $eventsAd->subtitle }}</span>@endif
            </span>
            @if($eventsAd->cta_text && $eventsAd->link_url)
                <span class="mad__cta">{{ $eventsAd->cta_text }}</span>
            @endif
        </a>
    @endif

    {{-- For You — a 1:1 port of the app's rail (MainScreen.kt InfiniteLoopBookPager
         + HaraanEventCard). No "See all": the app passes SectionHeader no action, so
         it doesn't render one. Card values track the app's tokens — change both together.

         Deliberate divergence: the app's pager early-returns on an empty list and
         leaves the header stranded above a gap. Here the whole section goes, rather
         than shipping an orphan heading. --}}
    @if($forYou->count())
    <div class="mhome__head mhome__head--bare">
        <h3>For You</h3>
    </div>
    <div class="mpager" data-mpager>
            @foreach($forYou as $ev)
                @php
                    $img = $ev->heroImageUrl() ?? '/bv-white.png';
                    // Mirrors EventRepository.formatWhen(): "27 Jun • 12:00 AM", then
                    // uppercased by the card. Not the site's old "Sat, Jun 27" shape.
                    $whenParts = array_filter([
                        optional($ev->date)?->format('j M'),
                        trim((string) $ev->time) ?: null,
                    ]);
                    $whenLine = implode(' • ', $whenParts);
                    // Mirrors EventRepository.formatPrice(): "₹249 onwards" / "Free".
                    $priceLabel = ($ev->price > 0) ? '₹' . number_format((float) $ev->price) . ' onwards' : 'Free';
                    // Mirrors the app: venue, falling back to location.
                    $venueLabel = trim((string) $ev->venue) ?: trim((string) $ev->location);
                @endphp
                <div class="mfy__page" data-mpager-page>
                {{-- The app's card, 1:1 on geometry: full page width (the app's own
                     0.72f is dead code — see the CSS) at aspectRatio 0.75. Portrait
                     3:4 artwork suits it best; a landscape banner loses its sides. --}}
                <a class="mfy" href="/events/{{ $ev->id }}">
                    {{-- The first poster is the hero of the page — lazy-loading it would
                         delay the largest paint. The rest of the rail can wait.

                         decoding="async" is load-bearing, not a nicety: these posters are
                         1600×900 landing in a 283px slot (5.7× oversized), and a synchronous
                         decode of one costs tens of ms on the main thread — landing right in
                         the middle of a swipe. That was the stutter. --}}
                    <img class="mfy__img" src="{{ $img }}" alt=""
                         decoding="async"
                         loading="{{ $loop->first ? 'eager' : 'lazy' }}"
                         @if($loop->first) fetchpriority="high" @endif>
                    <span class="mfy__grad"></span>
                    <span class="mfy__cat">{{ $ev->category ?? 'Event' }}</span>
                    {{-- Star only when the event genuinely has a rating — the app
                         fabricates nothing here, and neither does this. --}}
                    @if(!empty($ev->rating) && $ev->rating > 0)
                        <span class="mfy__rating"><i>★</i><b>{{ number_format($ev->rating, 1) }}</b></span>
                    @endif

                    {{-- Facts over the art: a tall poster has room for a scrim, and it
                         keeps the card compact. No ticket button — the app's sits INSIDE
                         its own card link and fires the same onClick, a decorative
                         affordance that ate 27% of the width and truncated the price
                         ("Quake Arena • ₹59…"). The whole card is the tap target. --}}
                    <span class="mfy__foot">
                        <span class="mfy__date">{{ $whenLine }}</span>
                        <span class="mfy__title">{{ $ev->title }}</span>
                        <span class="mfy__venue">{{ $venueLabel }}</span>
                        {{-- Its own line. At the app's card width (204px at 375) the
                             text column is 172px, and "venue · price" needs 183 — one
                             line would ellipsise the venue away. --}}
                        <span class="mfy__price">{{ $priceLabel }}</span>
                    </span>
                </a>
                </div>
            @endforeach
    </div>
    @endif

    {{-- Trending — the app's TrendingRowSection: a 140×160 poster with an oversized
         rank number bleeding off its left edge. No price and no "booked" badge; the
         app's card carries the title alone, and no "See all" (its SectionHeader gets
         no action). Sits BEFORE Categories, as in the app's feed.

         The row is the admin's `trending` placement, same as the app. It used to be
         ranked by real ticket sales, which meant it rendered nothing at all — no event
         has a booking yet. See PublicWebController::trendingFeed(). --}}
    @if($mTrending->count())
    <div class="mhome__head mhome__head--bare"><h3>Trending</h3></div>
    <div class="mtrends">
        @foreach($mTrending as $ev)
            @php $img = $ev->heroImageUrl() ?? '/bv-white.png'; @endphp
            <a class="mtrend" href="/events/{{ $ev->id }}">
                <span class="mtrend__rank" aria-hidden="true">{{ $loop->iteration }}</span>
                <span class="mtrend__img">
                    <img src="{{ $img }}" alt="" loading="lazy" decoding="async">
                    <span class="mtrend__grad"></span>
                    <span class="mtrend__title">{{ $ev->title }}</span>
                </span>
            </a>
        @endforeach
    </div>
    @endif

    {{-- Categories — the app's HaraanCategoryCard row (icons, stat line, active card
         in EventsBlue), but built from categories that actually have events. Never
         emoji: they re-render per OS and read as a template.

         The app hardcodes "Concerts / 245 Events" and "Standup / 54 Shows"; no event
         is categorised either, so that row advertises stock it doesn't have. See
         PublicWebController::categoryCards(). --}}
    @if(count($catRow) > 1)
    <div class="mhome__head mhome__head--bare"><h3>Categories</h3></div>
    <div class="mhome__cats">
        @php
            // Line icons keyed by category. Unknown categories get the neutral ticket.
            $catIcons = [
                'all'      => '<rect x="3" y="3" width="7" height="7" rx="1.5"></rect><rect x="14" y="3" width="7" height="7" rx="1.5"></rect><rect x="3" y="14" width="7" height="7" rx="1.5"></rect><rect x="14" y="14" width="7" height="7" rx="1.5"></rect>',
                'music'    => '<path d="M9 18V5l12-2v13"></path><circle cx="6" cy="18" r="3"></circle><circle cx="18" cy="16" r="3"></circle>',
                'concerts' => '<path d="M9 18V5l12-2v13"></path><circle cx="6" cy="18" r="3"></circle><circle cx="18" cy="16" r="3"></circle>',
                'comedy'   => '<path d="M12 1a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"></path><path d="M19 10v1a7 7 0 0 1-14 0v-1"></path><line x1="12" y1="18" x2="12" y2="22"></line>',
                'standup'  => '<path d="M12 1a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"></path><path d="M19 10v1a7 7 0 0 1-14 0v-1"></path><line x1="12" y1="18" x2="12" y2="22"></line>',
                'nightlife'=> '<path d="M5 4h14l-7 8z"></path><line x1="12" y1="12" x2="12" y2="20"></line><line x1="8" y1="20" x2="16" y2="20"></line>',
                'workshops'=> '<path d="M14.7 6.3a4 4 0 0 1-5.4 5.4L4 17v3h3l5.3-5.3a4 4 0 0 1 5.4-5.4l-2.6 2.6 2 2 2.6-2.6z"></path>',
                'festivals'=> '<path d="M12 2v20"></path><path d="M12 4l8 4-8 4"></path>',
                'sports'   => '<circle cx="12" cy="12" r="9"></circle><path d="M12 3a15 15 0 0 1 0 18M3 12h18"></path>',
                'default'  => '<path d="M3 9a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2 2 2 0 0 0 0 4 2 2 0 0 1-2 2H5a2 2 0 0 1-2-2 2 2 0 0 0 0-4z"></path><line x1="14" y1="7" x2="14" y2="17" stroke-dasharray="1.5 2"></line>',
            ];
        @endphp
        @foreach($catRow as $c)
            @php $icon = $catIcons[strtolower($c['title'])] ?? $catIcons['default']; @endphp
            <a href="{{ $c['href'] }}" class="mcat {{ $c['on'] ? 'is-on' : '' }}">
                <span class="mcat__ico">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icon !!}</svg>
                </span>
                <strong class="mcat__name">{{ $c['title'] }}</strong>
                <small class="mcat__stat">{{ $c['stat'] }}</small>
            </a>
        @endforeach
    </div>
    @endif

    {{-- Explore Nearby: 2-column grid (mirrors the app's EventListCard) --}}
    @if($mEvents->count())
    <div class="mhome__head mhome__head--stack">
        <span class="mhome__eyebrow">Handpicked experiences</span>
        <h3>Explore Nearby</h3>
    </div>
    <div class="mhome__grid">
        @foreach($mEvents as $ev)
            @php
                $img = $ev->heroImageUrl() ?? '/bv-white.png';
                $soon = optional($ev->date)->between(now(), now()->addDays(7)) ?? false;
                $hasOnwards = $ev->price > 0;
            @endphp
            <a class="mcard" href="/events/{{ $ev->id }}">
                <div class="mcard__img" style="background-image:url('{{ $img }}')">
                    @if($soon)<span class="mcard__fast"><i>⚡</i>FILLING FAST</span>@endif
                </div>
                <p class="mcard__date">{{ optional($ev->date)->format('D, j M') }}</p>
                <h4 class="mcard__title">{{ $ev->title }}</h4>
                <p class="mcard__venue">{{ $ev->venue }}</p>
                <p class="mcard__price">{{ $ev->price ? '₹'.number_format($ev->price) : 'Free' }}@if($hasOnwards)<span> onwards</span>@endif</p>
            </a>
        @endforeach
    </div>
    @endif

</div>

<section class="page-shell events-page theme-events">
    <!-- Premium Banner Carousel -->
    <section class="banner-carousel" id="eventBanner">
        <div class="banner-track" id="bannerTrack">
            @foreach($bannerEvents as $index => $event)
                <div class="banner-item">
                    <div class="banner-item__bg">
                        <img src="{{ $event['poster'] }}" alt="">
                    </div>
                    
                    <div class="banner-item__left">
                        <p class="date">{{ $event['meta'] }}</p>
                        <h2>{{ $event['title'] }}</h2>
                        <p class="venue">{{ $event['venue'] }}</p>
                        <p class="price">{{ $event['price'] }}</p>
                        <a href="/events/{{ $event['id'] }}" class="book-btn">Book tickets</a>
                    </div>

                    <div class="banner-item__right">
                        <img src="{{ $event['poster'] }}" alt="{{ $event['title'] }}">
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Navigation Controls -->
        <button class="banner-arrow banner-arrow--prev" onclick="moveBanner(-1)">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
        </button>
        <button class="banner-arrow banner-arrow--next" onclick="moveBanner(1)">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
        </button>

        <div class="banner-dots" id="bannerDots">
            @foreach($bannerEvents as $index => $event)
                <div class="banner-dot {{ $index === 0 ? 'is-active' : '' }}" onclick="goToSlide({{ $index }})"></div>
            @endforeach
        </div>
    </section>

    <section class="events-section events-section--filters">
        <div class="event-explore-grid event-explore-grid--filters" aria-label="Event categories">
            @foreach ($categoryCards as $category)
                <a
                    class="event-explore-card {{ ($category['title'] === $activeCategory || ($category['title'] === 'All' && $activeCategory === 'All')) ? 'is-active' : '' }}"
                    href="{{ $category['href'] }}"
                    style="--card-from: {{ $category['from'] }}; --card-to: {{ $category['to'] }}; --card-accent: {{ $category['accent'] }};"
                >
                    <span class="event-explore-card__title">{{ $category['title'] }}</span>
                    <span class="event-explore-card__icon {{ !empty($category['iconImage']) ? 'event-explore-card__icon--image' : '' }} {{ $category['title'] === 'Concerts' ? 'event-explore-card__icon--concerts' : '' }} {{ !empty($category['size']) && $category['size'] === 'large' ? 'event-explore-card__icon--large' : '' }}" aria-hidden="true">
                        @if (!empty($category['iconImage']))
                            <img src="{{ $category['iconImage'] }}" alt="{{ $category['iconAlt'] ?? $category['title'] }}">
                        @else
                            {{ $category['icon'] }}
                        @endif
                    </span>
                </a>
            @endforeach
        </div>
    </section>


    <section class="events-section">
        <div class="section-shell__header">
            <div>
                <p class="eyebrow eyebrow--soft">Handpicked experiences</p>
                <h2>Trending events in Mumbai</h2>
            </div>
        </div>

        <div class="events-grid-list">
            @foreach ($listedEvents as $event)
                <a class="event-list-card" href="/events/{{ $event->id }}">
                    <div class="event-list-card__media">
                        @php
                            $poster = null;
                            if (!empty($event->images) && is_array($event->images)) {
                                $poster = $event->images[0] ?? null;
                            }
                            // Normalize poster URL:
                            // - external URLs (http/https) are used as-is
                            // - absolute paths starting with / are used as-is
                            // - otherwise assume storage path and prefix with storage URL
                            if ($poster) {
                                if (preg_match('/^(http|https):\/\//', $poster) || strpos($poster, '/') === 0) {
                                    $posterUrl = $poster;
                                } else {
                                    $posterUrl = asset('storage/' . ltrim($poster, '/'));
                                }
                            } else {
                                $posterUrl = asset('events.png');
                            }
                        @endphp
                        <img src="{{ $posterUrl }}" alt="{{ $event->title }}">
                    </div>
                    <div class="event-list-card__content">
                        <p class="event-list-card__date">{{ optional($event->date)->format('D, j M, g:i A') }}</p>
                        <h3 class="event-list-card__title">{{ $event->title }}</h3>
                        <p class="event-list-card__venue">{{ $event->venue }}</p>
                        <p class="event-list-card__price">₹{{ number_format((float) $event->price) }} onwards</p>
                    </div>
                </a>
            @endforeach
        </div>
    </section>
    <script>
    let currentIndex = 0;
    const track = document.getElementById('bannerTrack');
    const dots = document.querySelectorAll('.banner-dot');
    const totalSlides = {{ count($bannerEvents) }};
    let autoPlayInterval;

    function updateBanner() {
        if (!track) return;
        track.style.transform = `translateX(-${currentIndex * 100}%)`;
        dots.forEach((dot, index) => {
            dot.classList.toggle('is-active', index === currentIndex);
        });
    }

    function moveBanner(direction) {
        stopAutoPlay();
        currentIndex = (currentIndex + direction + totalSlides) % totalSlides;
        updateBanner();
        startAutoPlay();
    }

    function goToSlide(index) {
        stopAutoPlay();
        currentIndex = index;
        updateBanner();
        startAutoPlay();
    }

    function startAutoPlay() {
        stopAutoPlay();
        autoPlayInterval = setInterval(() => {
            currentIndex = (currentIndex + 1) % totalSlides;
            updateBanner();
        }, 5000);
    }

    function stopAutoPlay() {
        if (autoPlayInterval) clearInterval(autoPlayInterval);
    }

    // Initialize
    document.addEventListener('DOMContentLoaded', () => {
        updateBanner();
        startAutoPlay();
    });
    </script>
</section>

<script>
/**
 * "For You" rail.
 *
 * Deliberately NOT a port of the app's InfiniteLoopBookPager. Its look — upcoming
 * pages dragged left 0.85×page and scaled toward 0.88 — comes from a graphicsLayer
 * recomputed on every scroll frame. Compose runs that on the same frame as the
 * scroll; the web can't. Touch scrolling is driven on the compositor thread while
 * script runs on the main one, so the stacked card lagged the card under your finger
 * and the rail stuttered. The effect was the cause, so the effect is gone.
 *
 * What's left is a native scroll-snap carousel: the browser owns every frame of the
 * motion. No scroll listener, nothing per-frame — the only script here runs once the
 * rail has already come to rest.
 */
(() => {
    const pager = document.querySelector('[data-mpager]');
    if (!pager) return;
    const originals = [...pager.querySelectorAll('[data-mpager-page]')];
    const N = originals.length;
    if (N < 2) return;

    /* A real loop, the way the app fakes one with Int.MAX_VALUE virtual pages — but
       with TWO clones, not two strips.

       Since the rail snaps a card at a time you can only ever be one card past either
       end, so one spare each side is enough: [last', R0…Rn, first']. Sit on R0, and
       when a swipe settles on a spare, hop one strip — identical content, so nothing
       moves on screen. Cloning the whole strip (the first attempt) put NINE 1600×900
       posters in this rail for three events; each is 5.7× oversized for its 283px slot,
       and decoding one costs real main-thread time mid-swipe. Two clones, not eight. */
    const lead = originals[N - 1].cloneNode(true);
    const tail = originals[0].cloneNode(true);
    for (const c of [lead, tail]) {
        c.setAttribute('aria-hidden', 'true');
        c.querySelectorAll('a').forEach(a => a.setAttribute('tabindex', '-1'));
        // A spare is never the LCP candidate; let the real hero win the priority.
        c.querySelectorAll('img').forEach(i => {
            i.setAttribute('loading', 'lazy');
            i.removeAttribute('fetchpriority');
        });
    }
    pager.prepend(lead);
    pager.append(tail);

    const pages = [...pager.querySelectorAll('[data-mpager-page]')];
    // Snap pitch = card + gap. Measured off the DOM so a resize can't stale it.
    const pitch = () => {
        const a = pages[0].getBoundingClientRect();
        const b = pages[1].getBoundingClientRect();
        return Math.round(b.left - a.left) || Math.round(a.width);
    };
    // The real cards start at index 1, after the leading spare.
    const home = () => pitch();

    /* Hop off a spare once a swipe has settled on it. Fires on scrollend — never
       mid-gesture, so it can't fight the finger. */
    const recentre = () => {
        const p = pitch();
        const x = pager.scrollLeft;
        if (x >= (N + 1) * p - 1) pager.scrollLeft = x - N * p;   // past the end
        else if (x < p - 1) pager.scrollLeft = x + N * p;          // before the start
    };
    const hasScrollEnd = 'onscrollend' in window;
    if (hasScrollEnd) pager.addEventListener('scrollend', recentre);

    window.addEventListener('resize', () => { pager.scrollLeft = home(); });

    // Auto-advance, as in the pager's LaunchedEffect. scrollTo({behavior:'smooth'}) is
    // the browser's own animation, so this stays off the main thread too.
    const still = window.matchMedia('(prefers-reduced-motion: reduce)');
    let lastTouch = 0;
    for (const e of ['pointerdown', 'touchstart', 'wheel']) {
        pager.addEventListener(e, () => { lastTouch = Date.now(); }, {passive: true});
    }

    setInterval(() => {
        if (still.matches || document.hidden) return;
        // Hands off for a beat after a touch: the rail must never yank a card away
        // from someone who is reading it.
        if (Date.now() - lastTouch < 6000) return;

        const p = pitch();
        pager.scrollTo({left: (Math.round(pager.scrollLeft / p) + 1) * p, behavior: 'smooth'});
        if (!hasScrollEnd) setTimeout(recentre, 700);   // Safari < 17
    }, 3000);

    pager.scrollLeft = home();
})();
</script>
@endsection
