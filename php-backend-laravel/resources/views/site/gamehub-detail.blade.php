@extends('site.layout')
@section('footer_icon_secondary', '#16a34a')

@section('content')

{{-- ================================================================= --}}
{{-- MOBILE APP-STYLE VENUE DETAIL (mirrors the app; ≤720px)            --}}
{{--                                                                    --}}
{{-- A port of VenueDetailScreen.kt — a "view → trust → book" page:      --}}
{{-- hero gallery → white sheet lapping 24px over it → title/rating →    --}}
{{-- hours → address + Show in Map → rating summary → Available Sports → --}}
{{-- Amenities → About → Good to know → Location → Reviews, with a       --}}
{{-- sticky Book Now bar. Like the app, this page has no site header:    --}}
{{-- the back/save/share circles float on the hero instead.             --}}
{{--                                                                    --}}
{{-- Booking and reviews REUSE the desktop widgets rather than cloning   --}}
{{-- them: the sticky bar and RATE VENUE move those live nodes into a    --}}
{{-- sheet (see the script), so the working scheduler/review JS keeps    --}}
{{-- its element ids and listeners. A second copy would be a second      --}}
{{-- source of truth for real money. --}}
{{-- ================================================================= --}}
@php
    // Hero gallery: the primary image, then any gallery shots (the app pages
    // through detail.images and shows dots only when there's more than one).
    $mvImages = array_values(array_filter(array_merge([$venue->image], $venue->gallery ?? [])));

    // The app's openMap(): an admin-set map link wins, else a coordinate query,
    // else a name+address search. Never a fabricated pin.
    $mvQuery = ($venue->latitude !== null && $venue->longitude !== null)
        ? $venue->latitude . ',' . $venue->longitude
        : trim($venue->title . ' ' . ($venue->address ?: $venue->location));
    $mvMapUrl = $venue->map_link ?: 'https://www.google.com/maps/search/?api=1&query=' . urlencode($mvQuery);

    // "Good to know" = cancellation policy first, then the admin-authored rules.
    $mvGoodToKnow = array_values(array_filter(array_merge(
        [$venue->cancellation],
        $venue->rules ?? []
    )));

    $mvScore = (float) $venue->rating;
    $mvAddress = $venue->address ?: $venue->location;

    // The app's sportIcon(): cricket / football / basketball, else a racket for
    // badminton + the other racquet sports.
    $mvCat = strtolower($venue->category);
    $mvSportGlyph = match (true) {
        str_contains($mvCat, 'cricket') => '<path d="M15.5 3.5a2.1 2.1 0 0 1 3 3L9 16l-3 1 1-3z"></path><circle cx="6.5" cy="17.5" r="3"></circle>',
        str_contains($mvCat, 'football'), str_contains($mvCat, 'soccer') => '<circle cx="12" cy="12" r="9"></circle><path d="M12 7.5 8.5 10l1.3 4h4.4l1.3-4z"></path><path d="M12 3v4.5M4 9.5 8.5 10M20 9.5 15.5 10M7 20l2.8-6M17 20l-2.8-6"></path>',
        str_contains($mvCat, 'basketball') => '<circle cx="12" cy="12" r="9"></circle><path d="M12 3v18M3 12h18M5.6 5.6a12 12 0 0 0 12.8 12.8M18.4 5.6A12 12 0 0 1 5.6 18.4"></path>',
        default => '<ellipse cx="9.5" cy="9.5" rx="6.5" ry="5.5" transform="rotate(-45 9.5 9.5)"></ellipse><path d="M13.5 13.5 20 20"></path><path d="M6.5 6.5 12.5 12.5M6.5 12.5 12.5 6.5"></path>',
    };

    // The app's amenityIcon(): free-text label → the closest glyph, falling back to a
    // check. Same keyword buckets and same order, so a label resolves identically here.
    $mvAmenityIcon = function (string $amenity): string {
        $a = strtolower($amenity);
        $has = fn (string ...$needles) => array_reduce($needles, fn ($c, $n) => $c || str_contains($a, $n), false);

        return match (true) {
            $has('wifi', 'wi-fi', 'internet') => '<path d="M5 12.5a10 10 0 0 1 14 0M8.5 16a5 5 0 0 1 7 0M2 9a14 14 0 0 1 20 0"></path><circle cx="12" cy="19.5" r="1"></circle>',
            $has('park') => '<path d="M5 17h14M6 17v-4.5L7.5 8h9l1.5 4.5V17"></path><circle cx="8" cy="17" r="1.5"></circle><circle cx="16" cy="17" r="1.5"></circle>',
            $has('wash', 'toilet', 'restroom', 'rest room') => '<circle cx="8" cy="4.5" r="2"></circle><path d="M6 21v-5H4.5l2-6.5h3L11 16H9.5v5z"></path><circle cx="17" cy="4.5" r="2"></circle><path d="M14 21 17 9l3 12M15 15h4"></path>',
            $has('shower') => '<path d="M4 21V6a3 3 0 0 1 6 0v1M9 9h11M12 12v.01M16 12v.01M20 12v.01M14 16v.01M18 16v.01"></path>',
            $has('chang', 'locker') => '<path d="M12 4a2 2 0 1 0-2 2c0 1 2 1.5 2 2.5L3.5 15c-1 .7-.5 2.5 1 2.5h15c1.5 0 2-1.8 1-2.5L12 8.5"></path>',
            $has('cafe', 'coffee', 'canteen') => '<path d="M4 8h13v6a5 5 0 0 1-5 5H9a5 5 0 0 1-5-5z"></path><path d="M17 9h2a2.5 2.5 0 0 1 0 5h-2M6 3v2M10 3v2M14 3v2"></path>',
            $has('food', 'restaurant', 'kitchen') => '<path d="M5 3v8a2 2 0 0 0 4 0V3M7 11v10M17 3c-1.5 1-2.5 3-2.5 5.5S15.5 13 17 13v8"></path>',
            $has('water', 'drink') => '<path d="M12 3s6 6.5 6 10.5A6 6 0 0 1 6 13.5C6 9.5 12 3 12 3z"></path>',
            $has('light', 'flood') => '<path d="M9 18h6M10 21h4M12 3a6 6 0 0 1 4 10.5c-.6.6-1 1.4-1 2.2H9c0-.8-.4-1.6-1-2.2A6 6 0 0 1 12 3z"></path>',
            $has('a/c', 'air', 'cool') => '<path d="M4 8h16M4 12h16M4 16h16"></path>',
            $has('cctv', 'secur', 'guard', 'safe') => '<path d="M12 3l8 3.5v5c0 5-3.4 8.7-8 9.5-4.6-.8-8-4.5-8-9.5v-5z"></path>',
            $has('seat', 'gallery') => '<path d="M5 18v-6a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v6M3 18h18M7 10V6a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v4"></path>',
            $has('equip', 'gear', 'gym', 'kit') => '<path d="M4 9v6M7 7v10M17 7v10M20 9v6M7 12h10"></path>',
            default => '<polyline points="20 6 9 17 4 12"></polyline>',
        };
    };
@endphp

<div class="mven" data-venue-id="{{ $venue->id }}">
    {{-- ── 1. Hero gallery ───────────────────────────────────────────────── --}}
    <div class="mven__hero">
        <div class="mven__shots" @if(count($mvImages) > 1) data-mven-shots @endif>
            @foreach($mvImages as $shot)
                <img class="mven__shot" src="{{ $shot }}" alt="{{ $venue->title }}"
                     @if($loop->first) fetchpriority="high" @else loading="lazy" @endif decoding="async">
            @endforeach
        </div>
        <span class="mven__scrim" aria-hidden="true"></span>

        @if(count($mvImages) > 1)
            <div class="mven__dots" aria-hidden="true">
                @foreach($mvImages as $i => $shot)
                    <span class="mven__dot {{ $i === 0 ? 'is-on' : '' }}"></span>
                @endforeach
            </div>
        @endif

        {{-- Floating controls — the app's CircleButtons over the hero. --}}
        <div class="mven__controls">
            <button type="button" class="mven__circle" data-back aria-label="Back">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            </button>
            <div class="mven__controls-right">
                {{-- Real save, persisted in localStorage — the web twin of the app's
                     FavoritesStore (device-local too). Not a heart that forgets. --}}
                <button type="button" class="mven__circle" data-mven-fav aria-pressed="false" aria-label="Save venue">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1.1L12 21.2l7.8-7.8 1-1.1a5.5 5.5 0 0 0 0-7.7z"></path></svg>
                </button>
                <button type="button" class="mven__circle" data-mven-share aria-label="Share">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle><circle cx="18" cy="19" r="3"></circle><line x1="8.6" y1="13.5" x2="15.4" y2="17.5"></line><line x1="15.4" y1="6.5" x2="8.6" y2="10.5"></line></svg>
                </button>
            </div>
        </div>
    </div>

    {{-- ── 2. Content sheet (laps 24px over the hero) ────────────────────── --}}
    <div class="mven__sheet">
        <div class="mven__head">
            <h1 class="mven__title">{{ $venue->title }}</h1>
            @if($mvScore > 0)
                <span class="mven__score">
                    <svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor" aria-hidden="true"><path d="m12 17.3 6.2 3.7-1.6-7 5.4-4.7-7.1-.6L12 2 9.1 8.7 2 9.3l5.4 4.7-1.6 7z"></path></svg>
                    <b>{{ number_format($mvScore, 1) }}</b>
                    @if($venue->ratings_count > 0)<i>({{ $venue->ratings_count }})</i>@endif
                </span>
            @endif
        </div>

        @if($venue->hours)
            <div class="mven__hours">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><polyline points="12 7 12 12 15 14"></polyline></svg>
                {{ $venue->hours }}
            </div>
        @endif

        <div class="mven__where">
            <svg class="mven__pin" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
            <span class="mven__addr">{{ $mvAddress }}</span>
            {{-- The app's "Show in Map" pill. No distance line: that needs a real GPS
                 fix, which the site doesn't have — the app omits it too when blank. --}}
            <a class="mven__mappill" href="{{ $mvMapUrl }}" target="_blank" rel="noopener">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                Show in Map
            </a>
        </div>

        {{-- ── 3. Rating summary + RATE VENUE ────────────────────────────── --}}
        <div class="mven__rule"></div>
        <div class="mven__rating">
            <div class="mven__rating-copy">
                @if($venue->ratings_count > 0)
                    <div class="mven__rating-top">
                        <strong>{{ number_format($mvScore, 1) }}</strong>
                        <span class="mven__stars" aria-hidden="true">
                            @for($i = 1; $i <= 5; $i++)
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="{{ $i <= floor($mvScore) ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.6"><path d="m12 17.3 6.2 3.7-1.6-7 5.4-4.7-7.1-.6L12 2 9.1 8.7 2 9.3l5.4 4.7-1.6 7z"></path></svg>
                            @endfor
                        </span>
                    </div>
                    <span class="mven__rating-sub">{{ $venue->ratings_count }} ratings · {{ $venue->reviews }} reviews</span>
                @else
                    <strong class="mven__rating-none">No ratings yet</strong>
                    <span class="mven__rating-sub">Be the first to rate this venue</span>
                @endif
            </div>
            <button type="button" class="mven__rate" data-mven-rate>
                <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><path d="m12 17.3 6.2 3.7-1.6-7 5.4-4.7-7.1-.6L12 2 9.1 8.7 2 9.3l5.4 4.7-1.6 7z"></path></svg>
                RATE VENUE
            </button>
        </div>

        {{-- ── 4. Available Sports → the booking sheet's pricing ──────────── --}}
        <div class="mven__rule"></div>
        <h2 class="mven__h2">Available Sports</h2>
        <button type="button" class="mven__sport" data-mven-book>
            <span class="mven__sport-ico">
                <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $mvSportGlyph ?? '' !!}</svg>
            </span>
            <span class="mven__sport-copy">
                <strong>{{ $venue->category }}</strong>
                <small>View pricing</small>
            </span>
            <svg class="mven__chev" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"></polyline></svg>
        </button>

        {{-- ── 5. Amenities ───────────────────────────────────────────────── --}}
        @if(count($venue->amenities))
            <div class="mven__rule"></div>
            <h2 class="mven__h2">Amenities</h2>
            <div class="mven__amenities">
                @foreach($venue->amenities as $amenity)
                    <span class="mven__amenity">
                        <span class="mven__amenity-ico">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $mvAmenityIcon($amenity) !!}</svg>
                        </span>
                        {{ $amenity }}
                    </span>
                @endforeach
            </div>
        @endif

        {{-- ── 6. About ───────────────────────────────────────────────────── --}}
        @if($venue->description)
            <div class="mven__rule"></div>
            <h2 class="mven__h2">About this venue</h2>
            <p class="mven__body">{{ $venue->description }}</p>
        @endif

        {{-- ── 7. Good to know ────────────────────────────────────────────── --}}
        @if(count($mvGoodToKnow))
            <div class="mven__rule"></div>
            <h2 class="mven__h2">Good to know</h2>
            <ul class="mven__know">
                @foreach($mvGoodToKnow as $rule)
                    <li>
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        {{ $rule }}
                    </li>
                @endforeach
            </ul>
        @endif

        {{-- ── 8. Location ────────────────────────────────────────────────── --}}
        <div class="mven__rule"></div>
        <h2 class="mven__h2">Location</h2>
        <p class="mven__body">{{ $mvAddress }}</p>
        <a class="mven__dir" href="{{ $mvMapUrl }}" target="_blank" rel="noopener">Get directions</a>

        {{-- ── 9. Reviews ─────────────────────────────────────────────────── --}}
        @if(count($venue->reviews_list))
            <div class="mven__rule"></div>
            <h2 class="mven__h2">Reviews ({{ $venue->reviews }})</h2>
            <div class="mven__reviews">
                @foreach($venue->reviews_list as $review)
                    <div class="mven__review">
                        <div class="mven__review-head">
                            <span class="mven__review-face">{{ mb_strtoupper(mb_substr($review->user, 0, 1)) }}</span>
                            <span class="mven__review-who">
                                <strong>{{ $review->user }}</strong>
                                <small>{{ $review->date }}</small>
                            </span>
                            <span class="mven__review-score">
                                <svg viewBox="0 0 24 24" width="13" height="13" fill="currentColor" aria-hidden="true"><path d="m12 17.3 6.2 3.7-1.6-7 5.4-4.7-7.1-.6L12 2 9.1 8.7 2 9.3l5.4 4.7-1.6 7z"></path></svg>
                                <b>{{ $review->rating }}</b>
                            </span>
                        </div>
                        @if($review->comment)<p class="mven__review-text">{{ $review->comment }}</p>@endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ── 10. Sticky book bar ───────────────────────────────────────────── --}}
    <div class="mven__bar">
        <span class="mven__bar-price">
            @if($venue->price > 0)
                <b>₹{{ number_format($venue->price) }}</b><i>/hr</i>
            @else
                {{-- The app guards this so the bar never shows a bare "₹0 /hr". --}}
                <span class="mven__bar-noprice">Tap to see slots</span>
            @endif
        </span>
        <button type="button" class="mven__book" data-mven-book @unless($venue->is_bookable) disabled @endunless>
            {{ $venue->is_bookable ? 'Book Now' : 'Not bookable' }}
        </button>
    </div>
</div>

{{-- The sheet the booking widget and review form are moved into on first open. --}}
<div class="mven__modal" data-mven-modal hidden>
    <div class="mven__modal-backdrop" data-mven-close></div>
    <div class="mven__modal-card" role="dialog" aria-modal="true" aria-labelledby="mvenModalTitle">
        <div class="mven__modal-head">
            <h2 id="mvenModalTitle" class="mven__modal-title">Book a slot</h2>
            <button type="button" class="mven__modal-x" data-mven-close aria-label="Close">
                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
        </div>
        <div class="mven__modal-body" data-mven-modal-body></div>
    </div>
</div>

<section class="page-shell gamehub-detail-container theme-gamehub">
    
    <!-- Breadcrumbs / Back navigation -->
    <div class="detail-actions-row">
        <div class="detail-actions-buttons">
            <button onclick="toggleFavorite(this)" class="action-round-btn">
                <svg id="fav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#666" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
            </button>
            <button onclick="shareVenue()" class="action-round-btn">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#666" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle><circle cx="18" cy="19" r="3"></circle><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line></svg>
            </button>
        </div>
    </div>

    <!-- Title & Location Header -->
    <div class="detail-header">
        <div class="detail-header__badges">
            <span class="detail-badge">{{ $venue->category }}</span>
            @if(isset($venue->badge))
                <span class="detail-badge detail-badge--dark">{{ $venue->badge }}</span>
            @endif
        </div>
        <h1 class="detail-header__title">{{ $venue->title }}</h1>
        <div class="detail-header__meta">
            <span class="detail-meta-item">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                {{ $venue->location }}
            </span>
            <span class="detail-meta-divider">|</span>
            <span class="detail-meta-item">
                <span class="detail-meta-star">★</span>
                <strong>{{ $venue->rating }}</strong> ({{ $venue->reviews }} verified reviews)
            </span>
            <span class="detail-meta-divider">|</span>
            <span class="detail-meta-item">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                {{ $venue->hours }}
            </span>
        </div>
    </div>

    <!-- Gallery Grid (Airbnb Style) -->
    <div class="gallery-airbnb-grid">
        <!-- Large Featured Image -->
        <div class="gallery-featured-wrapper">
            <img class="gallery-featured-img" src="{{ $venue->image }}" alt="{{ $venue->title }} featured">
        </div>
        <!-- Right Column Gallery Images -->
        <div class="gallery-thumbs-wrapper">
            @foreach(array_slice($venue->gallery ?? [], 0, 2) as $index => $galImage)
                <div class="gallery-thumb-wrapper">
                    <img class="gallery-thumb-img" src="{{ $galImage }}" alt="Gallery view {{ $index + 1 }}">
                </div>
            @endforeach
        </div>
    </div>

    <!-- Two-Column Layout (Content vs Sticky Booking Sidebar) -->
    <div class="detail-two-column">
        
        <!-- Left Column: Details, Slots, Amenities, Reviews -->
        <div>
            <!-- About Section -->
            <div class="detail-card-panel">
                <h3 class="detail-card-panel__title">About This Facility</h3>
                <p class="detail-card-panel__text">{{ $venue->description }}</p>
            </div>

            <!-- Court Booking Scheduler (Core Widget) -->
            <div id="booking-widget" class="detail-card-panel">
                <h3 class="detail-card-panel__title detail-card-panel__title--compact">Select Booking Slot</h3>
                <p class="detail-card-panel__subtitle">Click on one or more available green slots below to queue your booking.</p>

                <!-- Date Picker Strip -->
                <div class="date-picker-strip">
                    @php
                        $days = ['Today', 'Tomorrow', 'Fri, 22 May', 'Sat, 23 May', 'Sun, 24 May', 'Mon, 25 May', 'Tue, 26 May'];
                    @endphp
                    @foreach($days as $index => $day)
                        <button onclick="selectDate(this, '{{ $day }}')" class="date-pill {{ $index === 0 ? 'is-active' : '' }}">
                            <span class="date-pill__day">{{ $index === 0 ? 'Today' : ($index === 1 ? 'Tomorrow' : explode(', ', $day)[0]) }}</span>
                            <span class="date-pill__date">
                                {{ $index < 2 ? date('d M', strtotime("+$index days")) : explode(', ', $day)[1] }}
                            </span>
                        </button>
                    @endforeach
                </div>

                <!-- Sport Selector Tabs (if multiple sports exist) -->
                @if(count($venue->sports) > 1)
                    <div class="mb-24">
                        <label class="sidebar-label">Select Sport</label>
                        <div class="sports-tab-strip">
                            @foreach($venue->sports as $sport)
                                <button onclick="selectSport('{{ $sport }}')" class="sport-tab" id="sport-tab-{{ $sport }}">
                                    <img src="{{ 
                                        $sport === 'Cricket' ? 'https://cdn-icons-png.flaticon.com/512/5140/5140374.png' : (
                                        $sport === 'Football' ? 'https://cdn-icons-png.flaticon.com/512/7711/7711842.png' : (
                                        $sport === 'Badminton' ? 'https://cdn-icons-png.flaticon.com/512/3012/3012437.png' : (
                                        $sport === 'Swimming' ? 'https://cdn-icons-png.flaticon.com/512/3144/3144883.png' : (
                                        $sport === 'Tennis' ? 'https://cdn-icons-png.flaticon.com/512/3132/3132644.png' : 'https://cdn-icons-png.flaticon.com/512/889/889505.png'))))
                                    }}" alt="{{ $sport }} Icon" />
                                    {{ $sport }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Court / Sub-Venue Selector Pills -->
                <div class="mb-28">
                    <label class="sidebar-label">Select Court / Pitch / Lane</label>
                    <div id="court-selector-container" class="court-selector-container">
                        <!-- Dynamically populated in JavaScript -->
                    </div>
                </div>

                <!-- Dynamic Slots Grid Container -->
                <div id="slots-grid-container">
                    <!-- Morning, Afternoon, Evening slots rendered here -->
                </div>
            </div>

            <!-- Amenities Section -->
            <div class="detail-card-panel">
                <h3 class="detail-card-panel__title mb-20">Amenities Offered</h3>
                @php
                    $amenityIcons = [
                        'Professional Floodlights' => 'https://cdn-icons-png.flaticon.com/512/14881/14881968.png',
                        'Floodlights' => 'https://cdn-icons-png.flaticon.com/512/14881/14881968.png',
                        'First Aid Kit' => 'https://cdn-icons-png.flaticon.com/512/12252/12252777.png',
                        'First Aid' => 'https://cdn-icons-png.flaticon.com/512/12252/12252777.png',
                        'Washrooms' => 'https://cdn-icons-png.flaticon.com/512/13132/13132308.png',
                        'Changing Rooms' => 'https://cdn-icons-png.flaticon.com/512/13132/13132308.png',
                        'Locker Rooms' => 'https://cdn-icons-png.flaticon.com/512/13132/13132308.png',
                        'Shower Rooms' => 'https://cdn-icons-png.flaticon.com/512/13132/13132308.png',
                        'Showers' => 'https://cdn-icons-png.flaticon.com/512/13132/13132308.png',
                        'Separate Steam Rooms' => 'https://cdn-icons-png.flaticon.com/512/13132/13132308.png',
                        'Drinking Water' => 'https://cdn-icons-png.flaticon.com/512/1078/1078844.png',
                        'Drinking Water Station' => 'https://cdn-icons-png.flaticon.com/512/1078/1078844.png',
                        'Free Parking' => 'https://cdn-icons-png.flaticon.com/512/8571/8571768.png',
                        'Valet Parking' => 'https://cdn-icons-png.flaticon.com/512/8571/8571768.png',
                        'Covered Batting Nets' => 'https://cdn-icons-png.flaticon.com/512/9957/9957884.png',
                        'FIFA-Approved Turf' => 'https://cdn-icons-png.flaticon.com/512/33/33736.png',
                        'Spectator Seating' => 'https://cdn-icons-png.flaticon.com/512/2822/2822557.png',
                        'Refreshment Lounge' => 'https://cdn-icons-png.flaticon.com/512/2738/2738730.png',
                        'Cafe' => 'https://cdn-icons-png.flaticon.com/512/2738/2738730.png',
                        'Air Conditioning' => 'https://cdn-icons-png.flaticon.com/512/959/959740.png',
                        'Yonex Synthetic Mats' => 'https://cdn-icons-png.flaticon.com/512/33/33736.png',
                        'Racket Rental' => 'https://cdn-icons-png.flaticon.com/512/2906/2906803.png',
                        'Shuttle Shop' => 'https://cdn-icons-png.flaticon.com/512/1162/1162456.png',
                        'Temperature Controlled' => 'https://cdn-icons-png.flaticon.com/512/1684/1684375.png',
                        'Olympic Lanes' => 'https://cdn-icons-png.flaticon.com/512/3144/3144860.png',
                        'Qualified Lifeguards' => 'https://cdn-icons-png.flaticon.com/512/1012/1012399.png',
                        'Towels Provided' => 'https://cdn-icons-png.flaticon.com/512/2913/2913508.png',
                        'Imported Red Clay' => 'https://cdn-icons-png.flaticon.com/512/33/33736.png',
                        'Ball Boy Service' => 'https://cdn-icons-png.flaticon.com/512/1012/1012399.png',
                        'Tennis Coach Access' => 'https://cdn-icons-png.flaticon.com/512/1012/1012399.png',
                        'Lounge' => 'https://cdn-icons-png.flaticon.com/512/2738/2738730.png',
                        'Acrylic Court Finish' => 'https://cdn-icons-png.flaticon.com/512/33/33736.png',
                        'Official Flex Rims' => 'https://cdn-icons-png.flaticon.com/512/33/33736.png',
                        'Chain Nets' => 'https://cdn-icons-png.flaticon.com/512/33/33736.png',
                        '24/7 Access' => 'https://cdn-icons-png.flaticon.com/512/3567/3567478.png',
                        'Spectator Fence' => 'https://cdn-icons-png.flaticon.com/512/2822/2822557.png',
                    ];
                @endphp
                <div class="amenities-grid">
                    @foreach($venue->amenities as $amenity)
                        @php
                            $iconUrl = $amenityIcons[$amenity] ?? 'https://cdn-icons-png.flaticon.com/512/109/109602.png';
                        @endphp
                        <div class="amenity-item">
                            <img src="{{ $iconUrl }}" alt="{{ $amenity }} icon">
                            <strong>{{ $amenity }}</strong>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Rules & Policies Section -->
            <div class="detail-card-panel">
                <h3 class="detail-card-panel__title mb-20">Rules & Cancellation</h3>
                
                <div class="mb-20">
                    <button onclick="toggleAccordion('rules-body', 'rules-chevron')" class="accordion-header">
                        <h4 class="accordion-title">Venue Guidelines</h4>
                        <span id="rules-chevron" class="accordion-chevron">▾</span>
                    </button>
                    <div id="rules-body" class="accordion-body">
                        <ul>
                            <li>Non-marking shoes are strictly mandatory for all indoor court facilities.</li>
                            <li>Please report at least 10 minutes prior to the booked slot duration.</li>
                            <li>No pets, glass containers, or alcoholic beverages allowed inside the playing area.</li>
                            <li>Follow instructions from the ground staff for safety and court allocation.</li>
                        </ul>
                    </div>
                </div>

                <hr class="detail-divider">

                <div class="mb-20">
                    <button onclick="toggleAccordion('cancel-body', 'cancel-chevron')" class="accordion-header">
                        <h4 class="accordion-title">Cancellation Policy</h4>
                        <span id="cancel-chevron" class="accordion-chevron">▾</span>
                    </button>
                    <div id="cancel-body" class="accordion-body">
                        <ul>
                            @if (!empty($venue->cancellation))
                                <li>{{ $venue->cancellation }}</li>
                            @else
                                <li>Free cancellation up to 6 hours before the booked slot time.</li>
                                <li>50% refund for cancellations done between 6 hours and 2 hours of the slot.</li>
                                <li>No refunds allowed for cancellations within 2 hours of the slot.</li>
                            @endif
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Reviews Section -->
            <div class="detail-card-panel">
                <div class="reviews-section-header">
                    <h3>User Reviews</h3>
                    <span id="reviews-count-badge" class="reviews-count-badge">
                        {{ count($venue->reviews_list) }} Reviews
                    </span>
                </div>

                <!-- Live reviews list -->
                <div id="reviews-list-container">
                    @foreach($venue->reviews_list as $review)
                        <div class="review-card">
                            <div class="review-card__header">
                                <div class="review-user-info">
                                    <div class="review-user-avatar">
                                        {{ substr($review->user, 0, 1) }}
                                    </div>
                                    <div>
                                        <strong class="review-user-name">{{ $review->user }}</strong>
                                        <span class="review-date">{{ $review->date }}</span>
                                    </div>
                                </div>
                                <div class="review-star-rating">
                                    @for($i = 0; $i < 5; $i++)
                                        <span class="review-star {{ $i < $review->rating ? 'is-active' : '' }}">★</span>
                                    @endfor
                                </div>
                            </div>
                            <p class="review-comment">{{ $review->comment }}</p>
                        </div>
                    @endforeach
                </div>

                <!-- Add Review Form (Mock Live Action) -->
                <div class="review-form-card">
                    <h4>Add Your Review</h4>
                    <div class="rating-selector-row">
                        <span class="rating-selector-label">Your Rating:</span>
                        <div class="rating-selector-stars" id="rating-selector">
                            <span onclick="setFormRating(1)" class="star-btn">★</span>
                            <span onclick="setFormRating(2)" class="star-btn">★</span>
                            <span onclick="setFormRating(3)" class="star-btn">★</span>
                            <span onclick="setFormRating(4)" class="star-btn">★</span>
                            <span onclick="setFormRating(5)" class="star-btn">★</span>
                        </div>
                    </div>
                    <div class="mb-16">
                        <input type="text" id="review-user" placeholder="Your Name" class="review-input-field">
                    </div>
                    <div class="mb-16">
                        <textarea id="review-comment" placeholder="Write your review here..." rows="4" class="review-input-field review-comment-textarea"></textarea>
                    </div>
                    <button onclick="submitReview()" class="review-submit-btn">
                        Submit Review
                    </button>
                </div>
            </div>
        </div>

        <!-- Right Column: Sticky Booking Card -->
        <div class="sticky-sidebar-container">
            <div class="sticky-booking-card">
                <div class="price-row">
                    <span class="price-row__label">Rate per hour</span>
                    <div class="price-row__value">
                        <strong id="rate-per-hour">₹{{ number_format($venue->price) }}</strong>
                        <span>/ hr</span>
                    </div>
                </div>

                <hr class="detail-divider mb-20">

                <div class="mb-16">
                    <label class="sidebar-label">Date</label>
                    <div id="selected-date-text" class="selected-date-preview">Today</div>
                </div>

                <div class="mb-24">
                    <label class="sidebar-label">Selected Slots (<span id="slots-count">0</span>)</label>
                    <div id="selected-slots-list" class="selected-slots-preview-list">
                        <span class="no-slots-placeholder">No slots selected. Click slots on the calendar grid.</span>
                    </div>
                </div>

                <!-- Price Calculator -->
                <div id="price-calculator" class="price-calculator-panel">
                    <div class="calc-row">
                        <span>Subtotal (<span id="calc-hours">0</span> hr)</span>
                        <span id="calc-subtotal">₹0</span>
                    </div>
                    <div class="calc-row">
                        <span>GST (18%)</span>
                        <span id="calc-gst">₹0</span>
                    </div>
                    <div class="calc-row">
                        <span>Platform Fee</span>
                        <span>₹50</span>
                    </div>
                    <hr class="dashed-divider">
                    <div class="calc-row calc-row--bold">
                        <span>Estimated Total</span>
                        <span id="calc-total" class="total-green">₹0</span>
                    </div>
                </div>

                <button id="book-now-button" disabled onclick="checkoutBooking()" class="book-now-button-widget">
                    Select slots to book
                </button>

                <p class="booking-notice-text">You won't be charged yet. Instant digital confirmation and invoice will be generated.</p>
            </div>
        </div>

    </div>

</section>

<!-- Success Checkout Overlay Modal -->
<div id="success-modal" class="success-overlay-modal">
    <div class="success-modal-card">
        <div class="success-check-badge">✓</div>
        <h2 class="success-modal-title">Booking Confirmed!</h2>
        <p class="success-modal-description">Your court slots at <strong>{{ $venue->title }}</strong> have been locked and reserved successfully.</p>
        
        <div class="success-receipt-box">
            <div class="success-receipt-row"><strong>Booking Reference:</strong> <span class="receipt-ref-id" id="modal-ref-id">BV-GH-49291</span></div>
            <div class="success-receipt-row"><strong>Date:</strong> <span class="receipt-highlight" id="modal-date">Today</span></div>
            <div class="success-receipt-row"><strong>Slots:</strong> <span class="receipt-highlight" id="modal-slots">...</span></div>
            <div class="success-receipt-row"><strong>Total Amount:</strong> <span class="receipt-total" id="modal-total">₹0</span></div>
        </div>

        <button onclick="closeSuccessModal()" class="success-done-btn">
            Done & Return to GameHub
        </button>
    </div>
</div>

<script>
    const venueCourts = @json($venue->courts);
    const venueSports = @json($venue->sports);
    const courtPrices = @json($venue->court_prices ?? new \stdClass);
    const courtPeak = @json($venue->court_peak ?? new \stdClass);
    const venueBasePrice = {{ (int) $venue->price }};

    // Base hourly rate for the currently-selected court (falls back to the venue base price).
    function currentRate() {
        return courtPrices[selectedCourt] ?? venueBasePrice;
    }

    // "06:00 AM" / "18:00" → minutes-from-midnight, or null.
    function timeMin(label) {
        if (!label) return null;
        const m = String(label).trim().match(/(\d{1,2}):(\d{2})\s*([AaPp][Mm])?/);
        if (!m) return null;
        let h = parseInt(m[1], 10);
        const ap = (m[3] || '').toUpperCase();
        if (ap === 'PM' && h !== 12) h += 12;
        if (ap === 'AM' && h === 12) h = 0;
        return h * 60 + parseInt(m[2], 10);
    }

    // Rate for a specific slot on the selected court: peak when the slot's start time falls in
    // the court's peak window, else the base rate.
    function slotRate(slotStr) {
        const base = currentRate();
        const p = courtPeak[selectedCourt];
        if (!p) return base;
        const t = timeMin(String(slotStr).split(' - ')[0]);
        const s = timeMin(p.start), e = timeMin(p.end);
        if (t != null && s != null && e != null && t >= s && t < e) return p.price;
        return base;
    }
    let selectedDate = 'Today';
    let selectedSport = venueSports[0];
    let selectedCourt = venueCourts[selectedSport][0];
    let selectedSlots = [];
    let currentFormRating = 0;

    function toggleFavorite(btn) {
        const svg = document.getElementById('fav-icon');
        if (svg.getAttribute('fill') === 'none') {
            svg.setAttribute('fill', '#16a34a');
            svg.setAttribute('stroke', '#16a34a');
            btn.style.borderColor = '#bbf7d0';
        } else {
            svg.setAttribute('fill', 'none');
            svg.setAttribute('stroke', '#666');
            btn.style.borderColor = '#e5e7eb';
        }
    }

    function shareVenue() {
        if (navigator.share) {
            navigator.share({
                title: '{{ $venue->title }}',
                url: window.location.href
            }).catch(console.error);
        } else {
            alert('Sharing link copied to clipboard: ' + window.location.href);
            navigator.clipboard.writeText(window.location.href);
        }
    }

    function selectDate(element, date) {
        document.querySelectorAll('.date-pill').forEach(btn => btn.classList.remove('is-active'));
        element.classList.add('is-active');

        selectedDate = date;
        document.getElementById('selected-date-text').innerText = date;
        
        // Reset selected slots when switching days to simulate a real scheduler
        selectedSlots = [];
        updatePriceBreakdown();
        renderSlots();
    }

    function selectSport(sport) {
        selectedSport = sport;
        selectedCourt = venueCourts[sport][0];

        // Update sport tabs styling
        document.querySelectorAll('.sport-tab').forEach(btn => btn.classList.remove('is-active'));
        
        const activeTab = document.getElementById('sport-tab-' + sport);
        if (activeTab) {
            activeTab.classList.add('is-active');
        }

        renderCourtSelector();
        // Clear selected slots on sport change to avoid invalid court cross-bookings
        selectedSlots = [];
        updatePriceBreakdown();
        renderSlots();
    }

    function selectCourt(court) {
        selectedCourt = court;
        renderCourtSelector();
        renderSlots();
    }

    function renderCourtSelector() {
        const container = document.getElementById('court-selector-container');
        if (!container) return;

        const courts = venueCourts[selectedSport] || [];
        container.innerHTML = courts.map(court => {
            const isActive = court === selectedCourt;
            const activeClass = isActive ? 'is-active' : '';
            return `
                <button onclick="selectCourt('${court}')" class="court-pill ${activeClass}">
                    ${court}
                </button>
            `;
        }).join('');
    }

    const morningSlots = ['06:00 AM - 07:00 AM', '07:00 AM - 08:00 AM', '08:00 AM - 09:00 AM', '09:00 AM - 10:00 AM', '10:00 AM - 11:00 AM', '11:00 AM - 12:00 PM'];
    const afternoonSlots = ['12:00 PM - 01:00 PM', '01:00 PM - 02:00 PM', '02:00 PM - 03:00 PM', '03:00 PM - 04:00 PM', '04:00 PM - 05:00 PM'];
    const eveningSlots = ['05:00 PM - 06:00 PM', '06:00 PM - 07:00 PM', '07:00 PM - 08:00 PM', '08:00 PM - 09:00 PM', '09:00 PM - 10:00 PM', '10:00 PM - 11:00 PM'];

    function isSlotBooked(date, sport, court, slot) {
        let str = date + sport + court + slot;
        let hash = 0;
        for (let i = 0; i < str.length; i++) {
            hash = (hash << 5) - hash + str.charCodeAt(i);
            hash |= 0;
        }
        return Math.abs(hash) % 10 < 3; // 30% deterministic booked slots
    }

    function renderSlots() {
        const container = document.getElementById('slots-grid-container');
        if (!container) return;

        let html = '';
        const rate = currentRate();

        // Reflect the selected court's rate in the sticky booking card header.
        const rateEl = document.getElementById('rate-per-hour');
        if (rateEl) rateEl.innerText = '₹' + rate.toLocaleString();

        const renderGroup = (title, slots) => {
            let groupHtml = `
                <div class="slots-group">
                    <h4 class="slots-group__title">${title}</h4>
                    <div class="slots-grid">
            `;

            slots.forEach((slot) => {
                const isBooked = isSlotBooked(selectedDate, selectedSport, selectedCourt, slot);
                const slotKey = `${selectedDate}_${selectedSport}_${selectedCourt}_${slot}`;
                const isSelected = selectedSlots.some(s => s.key === slotKey);
                const r = slotRate(slot);
                const isPeak = r > rate;

                const slotClass = isBooked ? 'is-booked' : (isSelected ? 'is-selected' : '');
                const onclickAttr = isBooked ? '' : `onclick="toggleSlot(this, '${slot}', ${r})"`;

                groupHtml += `
                    <div ${onclickAttr} class="slot-item ${slotClass}" data-key="${slotKey}">
                        <div class="slot-item__time">${slot.split(' - ')[0]}</div>
                        <div class="slot-item__price">${isBooked ? 'Reserved' : '₹' + r.toLocaleString() + (isPeak ? ' <span style=\"color:#16a34a;font-weight:600\">peak</span>' : '')}</div>
                    </div>
                `;
            });

            groupHtml += `
                    </div>
                </div>
            `;
            return groupHtml;
        };

        html += renderGroup('Morning', morningSlots);
        html += renderGroup('Afternoon', afternoonSlots);
        html += renderGroup('Evening', eveningSlots);

        container.innerHTML = html;
    }

    function toggleSlot(element, slot, rate) {
        const slotKey = `${selectedDate}_${selectedSport}_${selectedCourt}_${slot}`;
        if (element.classList.contains('is-selected')) {
            element.classList.remove('is-selected');
            selectedSlots = selectedSlots.filter(s => s.key !== slotKey);
        } else {
            element.classList.add('is-selected');
            selectedSlots.push({
                key: slotKey,
                date: selectedDate,
                sport: selectedSport,
                court: selectedCourt,
                time: slot,
                price: rate
            });
        }
        updatePriceBreakdown();
    }

    function removeSelectedSlot(key) {
        selectedSlots = selectedSlots.filter(s => s.key !== key);
        updatePriceBreakdown();
        renderSlots();
    }

    function updatePriceBreakdown() {
        const slotsCount = selectedSlots.length;
        document.getElementById('slots-count').innerText = slotsCount;
        
        const listDiv = document.getElementById('selected-slots-list');
        const calcBlock = document.getElementById('price-calculator');
        const bookBtn = document.getElementById('book-now-button');

        if (slotsCount === 0) {
            listDiv.innerHTML = '<span class="no-slots-placeholder">No slots selected. Click slots on the calendar grid.</span>';
            calcBlock.style.display = 'none';
            bookBtn.disabled = true;
            bookBtn.className = 'book-now-button-widget';
            bookBtn.innerText = 'Select slots to book';
        } else {
            listDiv.innerHTML = selectedSlots.map(s => `
                <div class="selected-slot-item-pill">
                    <div class="selected-slot-item-pill__header">
                        ${s.sport} • ${s.court}
                    </div>
                    <div class="selected-slot-item-pill__details">
                        <span class="selected-slot-item-pill__time">${s.time} (${s.date})</span>
                        <span class="selected-slot-item-pill__price">₹${s.price.toLocaleString()}</span>
                    </div>
                    <button onclick="removeSelectedSlot('${s.key}')" class="selected-slot-item-pill__remove">×</button>
                </div>
            `).join('');

            const subtotal = selectedSlots.reduce((sum, s) => sum + s.price, 0);
            const gst = Math.round(subtotal * 0.18);
            const platformFee = 50;
            const total = subtotal + gst + platformFee;

            document.getElementById('calc-hours').innerText = slotsCount;
            document.getElementById('calc-subtotal').innerText = '₹' + subtotal.toLocaleString();
            document.getElementById('calc-gst').innerText = '₹' + gst.toLocaleString();
            document.getElementById('calc-total').innerText = '₹' + total.toLocaleString();

            calcBlock.style.display = 'block';
            bookBtn.disabled = false;
            bookBtn.className = 'book-now-button-widget is-ready';
            bookBtn.innerText = 'Confirm & Book Slots';
        }
    }

    function toggleAccordion(bodyId, chevronId) {
        document.getElementById(bodyId).classList.toggle('is-collapsed');
        document.getElementById(chevronId).classList.toggle('is-collapsed');
    }

    function setFormRating(rating) {
        currentFormRating = rating;
        const stars = document.querySelectorAll('#rating-selector .star-btn');
        stars.forEach((star, idx) => {
            if (idx < rating) {
                star.classList.add('is-active');
            } else {
                star.classList.remove('is-active');
            }
        });
    }

    function submitReview() {
        const nameInput = document.getElementById('review-user');
        const commentInput = document.getElementById('review-comment');
        
        const name = nameInput.value.trim();
        const comment = commentInput.value.trim();

        if (!name || !comment || currentFormRating === 0) {
            alert('Please select a rating, enter your name, and write a review.');
            return;
        }

        const listDiv = document.getElementById('reviews-list-container');
        const newCard = document.createElement('div');
        newCard.className = 'review-card is-new';

        let starsHtml = '';
        for (let i = 0; i < 5; i++) {
            starsHtml += `<span class="review-star ${i < currentFormRating ? 'is-active' : ''}">★</span>`;
        }

        newCard.innerHTML = `
            <div class="review-card__header">
                <div class="review-user-info">
                    <div class="review-user-avatar">
                        ${name.substring(0, 1).toUpperCase()}
                    </div>
                    <div>
                        <strong class="review-user-name">${name}</strong>
                        <span class="review-date">Just now</span>
                    </div>
                </div>
                <div class="review-star-rating">
                    ${starsHtml}
                </div>
            </div>
            <p class="review-comment">${comment}</p>
        `;

        listDiv.prepend(newCard);
        setTimeout(() => newCard.classList.remove('is-new'), 50);

        const badge = document.getElementById('reviews-count-badge');
        const countStr = badge.innerText;
        const currentCount = parseInt(countStr) || 0;
        badge.innerText = (currentCount + 1) + ' Reviews';

        nameInput.value = '';
        commentInput.value = '';
        setFormRating(0);
        
        alert('Thank you for your feedback! Your review has been added.');
    }

    function checkoutBooking() {
        const refId = 'BV-GH-' + Math.floor(10000 + Math.random() * 90000);
        const subtotal = selectedSlots.reduce((sum, s) => sum + s.price, 0);
        const gst = Math.round(subtotal * 0.18);
        const total = subtotal + gst + 50;

        document.getElementById('modal-ref-id').innerText = refId;
        
        const uniqueDates = selectedSlots.map(s => s.date).filter((value, index, self) => self.indexOf(value) === index);
        document.getElementById('modal-date').innerText = uniqueDates.join(', ');
        
        const slotsDesc = selectedSlots.map(s => `${s.sport} - ${s.court} (${s.time.split(' - ')[0]})`).join(', ');
        document.getElementById('modal-slots').innerText = slotsDesc;
        document.getElementById('modal-total').innerText = '₹' + total.toLocaleString();

        const modal = document.getElementById('success-modal');
        modal.style.display = 'flex';
    }

    function closeSuccessModal() {
        document.getElementById('success-modal').style.display = 'none';
        window.location.href = '/gamehub';
    }

    // Initialize Scheduler selectors on load
    window.addEventListener('DOMContentLoaded', () => {
        selectSport(selectedSport);
    });
</script>

{{-- ================================================================= --}}
{{-- Mobile venue detail (≤720px) behaviour                             --}}
{{--                                                                    --}}
{{-- Deliberately owns no booking logic. "Book Now" and "RATE VENUE"    --}}
{{-- MOVE the desktop widgets into a sheet — same nodes, same ids, same --}}
{{-- listeners — so the scheduler above stays the only booking code. A  --}}
{{-- mobile copy would be a second implementation of taking money.      --}}
{{-- ================================================================= --}}
<script>
(() => {
    const root = document.querySelector('.mven');
    if (!root) return;

    /* ---- Hero gallery dots: which shot is under the finger ---------- */
    const shots = root.querySelector('[data-mven-shots]');
    if (shots) {
        const dots = [...root.querySelectorAll('.mven__dot')];
        // Native scroll-snap owns the motion; this only reads where it settled.
        shots.addEventListener('scroll', () => {
            const i = Math.round(shots.scrollLeft / shots.clientWidth);
            dots.forEach((d, n) => d.classList.toggle('is-on', n === i));
        }, { passive: true });
    }

    /* ---- Save: the web twin of the app's device-local FavoritesStore -- */
    const favBtn = root.querySelector('[data-mven-fav]');
    const venueId = root.dataset.venueId;
    const KEY = 'haraan:favorites:venues';
    const read = () => {
        try { return JSON.parse(localStorage.getItem(KEY)) || []; } catch { return []; }
    };
    const paint = (on) => {
        favBtn.classList.toggle('is-on', on);
        favBtn.setAttribute('aria-pressed', String(on));
        favBtn.setAttribute('aria-label', on ? 'Remove from saved' : 'Save venue');
        favBtn.querySelector('svg').setAttribute('fill', on ? 'currentColor' : 'none');
    };
    paint(read().includes(venueId));
    favBtn?.addEventListener('click', () => {
        const list = read();
        const i = list.indexOf(venueId);
        if (i >= 0) list.splice(i, 1); else list.push(venueId);
        try { localStorage.setItem(KEY, JSON.stringify(list)); } catch { /* private mode */ }
        paint(i < 0);
    });

    /* ---- Share: the app fires an ACTION_SEND chooser; the web's twin is
           the native share sheet, falling back to copying the link. ------ */
    root.querySelector('[data-mven-share]')?.addEventListener('click', async () => {
        const share = { title: document.title, text: `Check out ${@json($venue->title)} on Haraan`, url: location.href };
        if (navigator.share) {
            try { await navigator.share(share); } catch { /* user dismissed */ }
        } else if (navigator.clipboard) {
            try { await navigator.clipboard.writeText(location.href); } catch { /* denied */ }
        }
    });

    /* ---- The sheet: hosts the real booking widget / review form ------ */
    const modal = document.querySelector('[data-mven-modal]');
    const body = modal.querySelector('[data-mven-modal-body]');
    const title = modal.querySelector('.mven__modal-title');
    // Where each widget came from, so it can go home when the sheet closes and
    // the desktop layout (and its JS) still finds it where it expects.
    const home = new Map();

    const open = (nodes, label) => {
        title.textContent = label;
        nodes.filter(Boolean).forEach((n) => {
            if (!home.has(n)) home.set(n, { parent: n.parentNode, next: n.nextSibling });
            body.appendChild(n);
        });
        modal.hidden = false;
        document.body.classList.add('mven-locked');
    };
    const close = () => {
        modal.hidden = true;
        document.body.classList.remove('mven-locked');
        // Put every moved node back exactly where it was.
        for (const [node, at] of home) at.parent.insertBefore(node, at.next);
        home.clear();
    };

    root.querySelectorAll('[data-mven-book]').forEach((b) => b.addEventListener('click', () => {
        open([document.getElementById('booking-widget'), document.querySelector('.sticky-booking-card')], 'Book a slot');
    }));
    root.querySelector('[data-mven-rate]')?.addEventListener('click', () => {
        open([document.querySelector('.review-form-card')], 'Rate this venue');
    });
    modal.querySelectorAll('[data-mven-close]').forEach((b) => b.addEventListener('click', close));
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !modal.hidden) close(); });
})();
</script>
@endsection
