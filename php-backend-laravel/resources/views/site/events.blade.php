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
    <div class="mhome__greet">
        <div>
            <p class="mhome__hi">Hello 👋</p>
            <h2 class="mhome__title">Discover in {{ $selectedCity ?? 'All India' }}</h2>
        </div>
    </div>

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
                    <span class="mfy__foot">
                        <span class="mfy__text">
                            <span class="mfy__date">{{ $whenLine }}</span>
                            <span class="mfy__title">{{ $ev->title }}</span>
                            <span class="mfy__meta">{{ $venueLabel }} • {{ $priceLabel }}</span>
                        </span>
                        <span class="mfy__book" aria-label="Book tickets">
                            {{-- Material's confirmation_number — the app's Icons.Default.ConfirmationNumber --}}
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true"><path d="M22 10V6c0-1.11-.9-2-2-2H4c-1.1 0-1.99.89-1.99 2v4c1.1 0 1.99.9 1.99 2s-.89 2-2 2v4c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2v-4c-1.1 0-2-.9-2-2s.9-2 2-2zm-9 7.5h-2v-2h2v2zm0-4.5h-2v-2h2v2zm0-4.5h-2v-2h2v2z"/></svg>
                        </span>
                    </span>
                </a>
                </div>
            @endforeach
    </div>
    @endif

    {{-- Categories --}}
    <div class="mhome__head"><h3>Categories</h3></div>
    <div class="mhome__cats">
        <a href="/events?category=Concerts" class="mcat mcat--blue">
            <span class="mcat__ico">🎵</span>
            <span class="mcat__txt"><strong>Concerts</strong><small>{{ $catCount('Concerts') ?: 0 }} events</small></span>
        </a>
        <a href="/events?category=Comedy" class="mcat mcat--blue">
            <span class="mcat__ico">🎤</span>
            <span class="mcat__txt"><strong>Comedy</strong><small>{{ $catCount('Comedy') ?: 0 }} shows</small></span>
        </a>
        <a href="/gamehub" class="mcat mcat--green">
            <span class="mcat__ico">🏏</span>
            <span class="mcat__txt"><strong>GameHub</strong><small>Turf & slots</small></span>
        </a>
    </div>

    {{-- Trending: real, ranked by actual ticket sales (from the controller) --}}
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
    const pages = [...pager.querySelectorAll('[data-mpager-page]')];
    if (!pages.length) return;

    const clamp01 = (v) => Math.min(1, Math.max(0, v));
    // A page is (screen − contentPadding) wide; read it off the element so the
    // maths survives a resize rather than assuming a viewport width.
    const pageWidth = () => pages[0].getBoundingClientRect().width || 1;

    let raf = null;
    const layout = () => {
        const p = pageWidth();
        // Compose: pageOffset = (currentPage - page) + currentPageOffsetFraction,
        // which is exactly (scrollLeft / pageWidth) - index.
        const scrolled = pager.scrollLeft / p;

        pages.forEach((page, i) => {
            const pageOffset = scrolled - i;

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
    window.addEventListener('resize', layout);

    // Auto-scroll every 3s, as in the pager's LaunchedEffect. Pauses while the
    // reader is touching it (the app checks isScrollInProgress) or the tab is
    // hidden, and stays put for anyone who prefers reduced motion.
    const still = window.matchMedia('(prefers-reduced-motion: reduce)');
    let held = false;
    for (const e of ['pointerdown', 'touchstart']) {
        pager.addEventListener(e, () => { held = true; }, {passive: true});
    }
    for (const e of ['pointerup', 'touchend', 'mouseleave']) {
        pager.addEventListener(e, () => { held = false; }, {passive: true});
    }

    setInterval(() => {
        if (held || still.matches || document.hidden || pages.length < 2) return;
        const p = pageWidth();
        const next = Math.round(pager.scrollLeft / p) + 1;
        pager.scrollTo({
            left: (next >= pages.length ? 0 : next) * p,
            behavior: 'smooth',
        });
    }, 3000);

    layout();
})();
</script>
@endsection
