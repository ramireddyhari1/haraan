@extends('site.layout')
@section('content')
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
