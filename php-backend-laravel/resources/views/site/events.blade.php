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

    {{-- For You: poster carousel --}}
    <div class="mhome__head">
        <h3>For You</h3>
        <a href="/events">See all</a>
    </div>
    <div class="mhome__scroll">
        @forelse($mEvents as $ev)
            @php
                $img = (is_array($ev->images) && count($ev->images)) ? $ev->images[0] : '/bv-white.png';
                $soon = optional($ev->date)->between(now(), now()->addDays(7)) ?? false;
            @endphp
            @php $priceLabel = $ev->price ? '₹'.number_format($ev->price) : 'Free'; @endphp
            <a class="mposter" href="/events/{{ $ev->id }}" style="background-image:url('{{ $img }}')">
                <span class="mposter__cat">{{ $ev->category ?? 'Event' }}</span>
                @if(!empty($ev->rating) && $ev->rating > 0)
                    <span class="mposter__rating"><i>★</i>{{ number_format($ev->rating, 1) }}</span>
                @elseif($soon)
                    <span class="mposter__rating mposter__rating--soon">This week</span>
                @endif
                <div class="mposter__grad"></div>
                <div class="mposter__overlay">
                    <div class="mposter__text">
                        <p class="mposter__date">{{ optional($ev->date)->format('D, M j • g:i A') }}</p>
                        <h4>{{ $ev->title }}</h4>
                        <p class="mposter__meta">{{ $ev->venue }} &nbsp;•&nbsp; {{ $priceLabel }}</p>
                    </div>
                    <span class="mposter__book" aria-label="Book tickets">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8.5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2 1.8 1.8 0 0 0 0 3.5 2 2 0 0 1-2 2H6a2 2 0 0 1-2-2 1.8 1.8 0 0 0 0-3.5Z"/><path d="M14 6.5v11" stroke-dasharray="1.5 2"/></svg>
                    </span>
                </div>
            </a>
        @empty
            <a class="mposter" href="/events" style="background:linear-gradient(135deg,#2563EB,#1e40af)">
                <div class="mposter__grad"></div>
                <div class="mposter__overlay">
                    <div class="mposter__text">
                        <h4>Live nights &amp; shows</h4>
                        <p class="mposter__meta">Near you &nbsp;•&nbsp; Explore</p>
                    </div>
                </div>
            </a>
        @endforelse
    </div>

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
@endsection
