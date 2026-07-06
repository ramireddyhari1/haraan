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
            @php $img = (is_array($ev->images) && count($ev->images)) ? $ev->images[0] : '/bv-white.png'; @endphp
            <a class="mposter" href="/events/{{ $ev->id }}">
                <div class="mposter__img" style="background-image:url('{{ $img }}')">
                    <span class="mposter__badge">Filling fast</span>
                    <span class="mposter__cat">{{ $ev->category ?? 'Event' }}</span>
                </div>
                <div class="mposter__body">
                    <h4>{{ $ev->title }}</h4>
                    <p class="mposter__date">{{ optional($ev->date)->format('D, M j • g:i A') }}</p>
                    <p class="mposter__venue">📍 {{ $ev->venue }}</p>
                    <div class="mposter__foot">
                        <span class="mposter__price">{{ $ev->price ? '₹'.number_format($ev->price) : 'Free' }}</span>
                        <span class="mposter__book">Book</span>
                    </div>
                </div>
            </a>
        @empty
            <a class="mposter" href="/events">
                <div class="mposter__img" style="background:linear-gradient(135deg,#2563EB,#1e40af)"></div>
                <div class="mposter__body">
                    <h4>Live nights & shows</h4>
                    <p class="mposter__venue">📍 Near you</p>
                    <div class="mposter__foot"><span class="mposter__price">Explore</span><span class="mposter__book">Open</span></div>
                </div>
            </a>
        @endforelse
    </div>

    {{-- Trending: compact row --}}
    @if($mEvents->count())
    <div class="mhome__head"><h3>Trending</h3><a href="/events">See all</a></div>
    <div class="mhome__scroll mhome__scroll--sm">
        @foreach($mEvents as $ev)
            @php $img = (is_array($ev->images) && count($ev->images)) ? $ev->images[0] : '/bv-white.png'; @endphp
            <a class="mtrend" href="/events/{{ $ev->id }}">
                <div class="mtrend__img" style="background-image:url('{{ $img }}')"></div>
                <h5>{{ $ev->title }}</h5>
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
            <strong>Concerts</strong>
            <small>{{ $catCount('Concerts') ?: 245 }} events</small>
        </a>
        <a href="/events?category=Comedy" class="mcat mcat--blue">
            <span class="mcat__ico">🎤</span>
            <strong>Standup</strong>
            <small>{{ $catCount('Comedy') ?: 54 }} shows</small>
        </a>
        <a href="/gamehub" class="mcat mcat--green">
            <span class="mcat__ico">🏏</span>
            <strong>GameHub</strong>
            <small>Turf & slots</small>
        </a>
    </div>

    {{-- Popular near you: feed --}}
    @if($mEvents->count())
    <div class="mhome__head"><h3>Popular near you</h3><a href="/events">See all</a></div>
    <div class="mhome__feed">
        @foreach($mEvents as $ev)
            @php $img = (is_array($ev->images) && count($ev->images)) ? $ev->images[0] : '/bv-white.png'; @endphp
            <a class="mrow" href="/events/{{ $ev->id }}">
                <div class="mrow__img" style="background-image:url('{{ $img }}')"></div>
                <div class="mrow__body">
                    <span class="mrow__cat">{{ $ev->category ?? 'Event' }}</span>
                    <h4>{{ $ev->title }}</h4>
                    <p class="mrow__date">{{ optional($ev->date)->format('D, M j') }}</p>
                    <p class="mrow__venue">📍 {{ $ev->venue }}</p>
                </div>
                <span class="mrow__price">{{ $ev->price ? '₹'.number_format($ev->price) : 'Free' }}</span>
            </a>
        @endforeach
    </div>
    @endif
</div>

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
@endsection
