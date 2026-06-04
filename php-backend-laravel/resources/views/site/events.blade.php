@extends('site.layout')
@section('content')
@php
    $activeCategory = request()->query('category', 'All');
    $featuredEvent = $events->first();
    $listedEvents = $events->slice(1)->values();
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
