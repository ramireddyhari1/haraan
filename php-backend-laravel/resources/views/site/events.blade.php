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
                    $img = (is_array($ev->images) && count($ev->images)) ? $ev->images[0] : '/bv-white.png';
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
                         delay the largest paint. The rest of the rail can wait. --}}
                    <img class="mfy__img" src="{{ $img }}" alt=""
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

    {{-- Trending: real, ranked by actual ticket sales (from the controller).
         Sits BEFORE Categories, as in the app's feed. --}}
    @if($mTrending->count())
    <div class="mhome__head"><h3>Trending</h3><a href="/events">See all</a></div>
    <div class="mhome__scroll mhome__scroll--sm">
        @foreach($mTrending as $ev)
            @php
                $img = (is_array($ev->images) && count($ev->images)) ? $ev->images[0] : '/bv-white.png';
                $sold = (int) ($ev->tickets_sold ?? 0);
            @endphp
            <a class="mtrend" href="/events/{{ $ev->id }}">
                <span class="mtrend__rank" aria-hidden="true">{{ $loop->iteration }}</span>
                <div class="mtrend__img" style="background-image:url('{{ $img }}')">
                    <div class="mtrend__grad"></div>
                    <h5>{{ $ev->title }}</h5>
                    @if($sold > 0)<span class="mtrend__sold">🎟 {{ number_format($sold) }} booked</span>@endif
                </div>
                <p>{{ $ev->price ? '₹'.number_format($ev->price) : 'Free' }}</p>
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
                $img = (is_array($ev->images) && count($ev->images)) ? $ev->images[0] : '/bv-white.png';
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
 * "For You" rail — a port of the app's InfiniteLoopBookPager (MainScreen.kt).
 *
 * The stacked "book" look isn't decoration, it's the pager's graphicsLayer: pages
 * to the RIGHT of the focal one are dragged back left by 85% of a page width and
 * scaled down toward 0.88, so they pack into a stack with only a sliver showing;
 * pages already swiped past are left alone to slide away. That maths is reproduced
 * here 1:1 — pageOffset included — because approximating it reads visibly different
 * from the app (evenly-spaced cards with a wide peek, rather than a tight stack).
 *
 * Divergence: the app fakes infinity with Int.MAX_VALUE virtual pages. This wraps
 * back to the first page instead, which is equivalent for a rail this size.
 */
(() => {
    const pager = document.querySelector('[data-mpager]');
    if (!pager) return;
    const originals = [...pager.querySelectorAll('[data-mpager-page]')];
    if (!originals.length) return;
    const N = originals.length;
    const loops = N > 1;

    /* A real loop, the way the app fakes one with Int.MAX_VALUE virtual pages.
       Clone the strip either side and park the viewer on the middle copy, so there is
       always a page to slide into in both directions; once a slide settles in a copy,
       shift scrollLeft by one strip — the content there is identical, so the jump is
       invisible.

       This replaces a rewind that smooth-scrolled from the last card all the way back
       across every card, which was the worst moment in the rail. The leading copy also
       restores the sliver of the previous poster in the left gutter, which the app
       shows and a hard stop at scrollLeft 0 could not. */
    if (loops) {
        const lead = originals.map(p => p.cloneNode(true));
        const tail = originals.map(p => p.cloneNode(true));
        for (const c of [...lead, ...tail]) {
            c.setAttribute('aria-hidden', 'true');
            c.querySelectorAll('a').forEach(a => a.setAttribute('tabindex', '-1'));
        }
        lead.reverse().forEach(c => pager.prepend(c));
        tail.forEach(c => pager.append(c));
    }

    const pages = [...pager.querySelectorAll('[data-mpager-page]')];
    const clamp01 = (v) => Math.min(1, Math.max(0, v));
    // A page is (screen − contentPadding) wide; read it off the element so the
    // maths survives a resize rather than assuming a viewport width.
    const pageWidth = () => pages[0].getBoundingClientRect().width || 1;
    // Where the real (non-clone) strip starts.
    const home = () => (loops ? N * pageWidth() : 0);

    let raf = null;
    const layout = () => {
        const p = pageWidth();
        // Compose: pageOffset = (currentPage - page) + currentPageOffsetFraction,
        // which is exactly (scrollLeft / pageWidth) - index.
        const scrolled = pager.scrollLeft / p;
        const padLeft = parseFloat(getComputedStyle(pager).paddingLeft) || 0;
        const port = pager.clientWidth;

        pages.forEach((page, i) => {
            const pageOffset = scrolled - i;

            /* Cull anything whose LAYOUT box is off screen — the pager only ever shows
               the previous sliver, the focal card, and the one stacked behind it.

               This is not an optimisation, it's the effect. Compose composes only the
               pages whose layout box intersects the viewport, so distant pages simply
               don't exist; here they all do, and the pull-left below would drag every
               one of them into the stack — six cards fanned across the poster instead
               of one peeking. Hidden rather than removed: the boxes still have to hold
               the scroll width open. */
            const naturalLeft = padLeft - pageOffset * p;
            if (naturalLeft > port || naturalLeft + p < 0) {
                page.style.visibility = 'hidden';
                return;
            }
            page.style.visibility = '';

            if (pageOffset < 0) {
                // Upcoming: pull left into the stack and scale down with distance.
                const tx = pageOffset * p * 0.85;
                const scale = 0.88 + (1.0 - 0.88) * clamp01(1 - Math.abs(pageOffset));
                page.style.transform = `translateX(${tx}px) scale(${scale})`;
            } else {
                // Swiped past: let it slide away natively.
                page.style.transform = 'translateX(0px) scale(1)';
            }
            // The focal card stays on top of the stack.
            page.style.zIndex = Math.abs(pageOffset) < 0.5 ? '2' : '1';
        });
    };
    const onScroll = () => {
        if (raf) return;
        raf = requestAnimationFrame(() => { raf = null; layout(); });
    };

    pager.addEventListener('scroll', onScroll, {passive: true});
    window.addEventListener('resize', () => { recentre(true); layout(); });

    /* Hop back to the middle copy once a slide has settled in one of the outer ones.
       Identical content on both sides, so nothing moves on screen. */
    const recentre = (force = false) => {
        if (!loops) return;
        const strip = N * pageWidth();
        const x = pager.scrollLeft;
        if (force) { pager.scrollLeft = strip + (((x - strip) % strip) + strip) % strip; }
        else if (x >= strip * 2 - 1) { pager.scrollLeft = x - strip; }
        else if (x < strip - 1) { pager.scrollLeft = x + strip; }
        else { return; }
        layout();
    };
    if ('onscrollend' in window) {
        pager.addEventListener('scrollend', () => recentre());
    }

    // Auto-scroll every 3s, as in the pager's LaunchedEffect. Skipped for anyone who
    // prefers reduced motion, and for a hidden tab.
    const still = window.matchMedia('(prefers-reduced-motion: reduce)');

    /* Hands off for a beat after a touch. The old code resumed the instant a finger
       lifted, so the rail could yank the card away mid-read. */
    let lastTouch = 0;
    const touched = () => { lastTouch = Date.now(); };
    for (const e of ['pointerdown', 'touchstart', 'wheel']) {
        pager.addEventListener(e, touched, {passive: true});
    }

    setInterval(() => {
        if (still.matches || document.hidden || !loops) return;
        if (Date.now() - lastTouch < 6000) return;

        const p = pageWidth();
        // Round off the settled position, never a scrollLeft caught mid-animation.
        const next = Math.round(pager.scrollLeft / p) + 1;
        pager.scrollTo({left: next * p, behavior: 'smooth'});

        // Browsers without scrollend (Safari < 17) still need the hop.
        if (!('onscrollend' in window)) setTimeout(() => recentre(), 700);
    }, 3000);

    // Start on the real strip, not the leading copy.
    if (loops) pager.scrollLeft = home();
    layout();
})();
</script>
@endsection
