@extends('site.layout')
@section('content')

{{-- ============================================================= --}}
{{-- MOBILE APP-STYLE HOME (mirrors the Android app; ≤720px only)   --}}
{{-- ============================================================= --}}
@php
    $mEvents = (isset($events) && $events->count()) ? $events : collect();
    $catCount = fn($c) => (isset($events) ? $events->where('category', $c)->count() : 0);
@endphp
<div class="mhome">
    {{-- Centered brand lockup at the top of the feed (matches /events). --}}
    <div class="mbrandmark" aria-hidden="true">
        <img src="{{ asset('images/haraan-logo.png') }}" alt="Haraan">
        <span>Where moments come alive</span>
    </div>
    <div class="mhome__greet">
        <div>
            <p class="mhome__hi">Hello 👋</p>
            <h2 class="mhome__title">Discover in {{ $selectedCity ?? 'All India' }}</h2>
        </div>
    </div>

    {{-- For You: full-bleed poster carousel (mirrors HaraanEventCard) --}}
    <div class="mhome__head">
        <h3>For You</h3>
        <a href="/events">See all</a>
    </div>
    <div class="mhome__scroll">
        @forelse($mEvents as $ev)
            @php $img = $ev->heroImageUrl() ?? '/bv-white.png'; @endphp
            <a class="mposter" href="/events/{{ $ev->id }}" style="background-image:url('{{ $img }}')">
                <span class="mposter__grad"></span>
                <span class="mposter__cat">{{ $ev->category ?? 'Event' }}</span>
                @if(!empty($ev->rating) && $ev->rating > 0)
                    <span class="mposter__rating"><i>★</i>{{ number_format($ev->rating, 1) }}</span>
                @else
                    <span class="mposter__rating mposter__rating--soon">NEW</span>
                @endif
                <div class="mposter__overlay">
                    <div class="mposter__text">
                        <p class="mposter__date">{{ optional($ev->date)->format('D, M j • g:i A') }}</p>
                        <h4>{{ $ev->title }}</h4>
                        <p class="mposter__meta">📍 {{ $ev->venue }} · {{ $ev->price ? '₹'.number_format($ev->price) : 'Free' }}</p>
                    </div>
                    <span class="mposter__book" aria-label="Book">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                    </span>
                </div>
            </a>
        @empty
            <a class="mposter" href="/events" style="background:linear-gradient(135deg,#2563EB,#1e40af)">
                <span class="mposter__grad"></span>
                <span class="mposter__cat">Events</span>
                <div class="mposter__overlay">
                    <div class="mposter__text">
                        <h4>Live nights & shows</h4>
                        <p class="mposter__meta">📍 Near you</p>
                    </div>
                    <span class="mposter__book" aria-label="Explore">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                    </span>
                </div>
            </a>
        @endforelse
    </div>

    {{-- Trending: ranked Top-10 (mirrors TrendingRowSection) --}}
    @if($mEvents->count())
    <div class="mhome__head"><h3>Trending</h3><a href="/events">See all</a></div>
    <div class="mhome__scroll mhome__scroll--sm">
        @foreach($mEvents->take(10) as $ev)
            @php $img = $ev->heroImageUrl() ?? '/bv-white.png'; @endphp
            <a class="mtrend" href="/events/{{ $ev->id }}">
                <span class="mtrend__rank">{{ $loop->iteration }}</span>
                <div class="mtrend__img" style="background-image:url('{{ $img }}')">
                    <span class="mtrend__grad"></span>
                    <span class="mtrend__sold"><i>🔥</i>Trending</span>
                    <h5>{{ $ev->title }}</h5>
                </div>
                <p>{{ $ev->price ? '₹'.number_format($ev->price) : 'Free' }}</p>
            </a>
        @endforeach
    </div>
    @endif

    {{-- Categories --}}
    <div class="mhome__head"><h3>Categories</h3></div>
    <div class="mhome__cats">
        <a href="/events?category=Concerts" class="mcat mcat--blue">
            <span class="mcat__ico">🎵</span>
            <span class="mcat__txt">
                <strong>Concerts</strong>
                <small>{{ $catCount('Concerts') ?: 245 }} events</small>
            </span>
        </a>
        <a href="/events?category=Comedy" class="mcat mcat--blue">
            <span class="mcat__ico">🎤</span>
            <span class="mcat__txt">
                <strong>Standup</strong>
                <small>{{ $catCount('Comedy') ?: 54 }} shows</small>
            </span>
        </a>
        <a href="/gamehub" class="mcat mcat--green">
            <span class="mcat__ico">🏏</span>
            <span class="mcat__txt">
                <strong>GameHub</strong>
                <small>Turf & slots</small>
            </span>
        </a>
    </div>

    {{-- Explore Nearby: 2-col grid of vertical cards (mirrors EventListCard) --}}
    @if($mEvents->count())
    <div class="mhome__head mhome__head--stack">
        <span class="mhome__eyebrow">Popular near you</span>
        <h3>Explore Nearby</h3>
    </div>
    <div class="mhome__grid">
        @foreach($mEvents as $ev)
            @php $img = $ev->heroImageUrl() ?? '/bv-white.png'; @endphp
            <a class="mcard" href="/events/{{ $ev->id }}">
                <div class="mcard__img" style="background-image:url('{{ $img }}')">
                    <span class="mcard__fast"><i>⚡</i>Fast filling</span>
                </div>
                <p class="mcard__date">{{ optional($ev->date)->format('D, M j') }}</p>
                <h4 class="mcard__title">{{ $ev->title }}</h4>
                <p class="mcard__venue">📍 {{ $ev->venue }}</p>
                <p class="mcard__price"><span>₹</span>{{ $ev->price ? number_format($ev->price) : '0' }}</p>
            </a>
        @endforeach
    </div>
    @endif

</div>

<div class="home-desktop-only">
<section class="hero">
    <div class="hero__copy">
        <p class="eyebrow eyebrow--dark">Curated entertainment platform</p>
        <h1>Book events, manage games, and move faster.</h1>
        <p>
            A clean, production-style interface for discovery, bookings, and operations.
            Built to feel dependable, not flashy.
        </p>

        <div class="hero__actions">
            <a href="/events" class="btn btn--solid">Browse events</a>
            <a href="/gamehub" class="btn btn--ghost btn--ghost-dark">Open GameHub</a>
        </div>

        <div class="hero__stats">
            <div>
                <strong>{{ ($listingCount ?? 0) > 0 ? number_format($listingCount) : '—' }}</strong>
                <span>Live listings</span>
            </div>
            <div>
                <strong>24/7</strong>
                <span>Access</span>
            </div>
            <div>
                <strong>1 app</strong>
                <span>For events and turf</span>
            </div>
        </div>
    </div>

    <div class="hero__panel">
        <div class="hero__panel-card hero__panel-card--events">
            <span class="hero__panel-label">Events</span>
            <h2>Concerts and curated experiences</h2>
            <p>Reliable discovery, sharper presentation, and quicker booking paths.</p>
        </div>
        <div class="hero__panel-card hero__panel-card--gamehub">
            <span class="hero__panel-label">GameHub</span>
            <h2>Courts, slots, and turf reservations</h2>
            <p>Structured booking flows designed for speed and clarity.</p>
        </div>
    </div>
</section>

<section class="quick-grid">
    <article class="quick-card">
        <span class="quick-card__label">Trending now</span>
        <h3>Concert nights and local showcases</h3>
        <p>Featured events, trusted organizers, and quick booking access.</p>
    </article>
    <article class="quick-card">
        <span class="quick-card__label">Sports booking</span>
        <h3>Cricket, turf, and game slots</h3>
        <p>See availability, reserve a slot, and keep every booking organized.</p>
    </article>
    <article class="quick-card">
        <span class="quick-card__label">Fast access</span>
        <h3>Accounts, login, and profile</h3>
        <p>Use one shared interface for guests, admins, and partners.</p>
    </article>
</section>

<section class="section-shell">
    <div class="section-shell__header">
        <div>
            <p class="eyebrow eyebrow--soft">Featured events</p>
            <h2>Popular experiences</h2>
        </div>
        <a href="/events" class="text-link">View all</a>
    </div>

    <div class="feature-list">
        @if(isset($events) && $events->count())
            <div class="events-grid">
                @foreach($events as $event)
                    @include('components.event-card', ['event' => $event])
                @endforeach
            </div>
        @else
            <div class="feature-item">
                <div class="feature-item__thumb feature-item__thumb--orange"></div>
                <div>
                    <h3>Mumbai Live Nights</h3>
                    <p>Concerts, festivals, and premium seating experiences.</p>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-item__thumb feature-item__thumb--green"></div>
                <div>
                    <h3>Premium Open Mic Night</h3>
                    <p>Hilarious stand-up sets and local artist showcases.</p>
                </div>
            </div>
        @endif
    </div>
</section>
</div>{{-- /.home-desktop-only --}}
@endsection
