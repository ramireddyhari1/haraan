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
        <div class="mbrandmark__dots"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div>
        <img src="{{ asset('images/haraan-logo.png') }}" alt="Haraan">
        <span>special</span>
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
@php
    // Desktop home is built from the same live feed the mobile page uses — no
    // placeholder copy, no invented sections. Real event front and centre.
    $featured   = $mEvents->first();
    $deskCats   = collect($mEvents)
        ->map(fn ($e) => trim((string) ($e->category ?: 'Event')))
        ->filter()
        ->countBy()
        ->sortDesc();

    // Line icons keyed by category (never emoji — they read as a template and
    // re-render per OS). Matches the mobile Categories row's icon language.
    $deskCatIcons = [
        'concerts'  => '<path d="M9 18V5l12-2v13"></path><circle cx="6" cy="18" r="3"></circle><circle cx="18" cy="16" r="3"></circle>',
        'music'     => '<path d="M9 18V5l12-2v13"></path><circle cx="6" cy="18" r="3"></circle><circle cx="18" cy="16" r="3"></circle>',
        'comedy'    => '<path d="M12 1a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"></path><path d="M19 10v1a7 7 0 0 1-14 0v-1"></path><line x1="12" y1="18" x2="12" y2="22"></line>',
        'nightlife' => '<path d="M5 4h14l-7 8z"></path><line x1="12" y1="12" x2="12" y2="20"></line><line x1="8" y1="20" x2="16" y2="20"></line>',
        'workshops' => '<path d="M14.7 6.3a4 4 0 0 1-5.4 5.4L4 17v3h3l5.3-5.3a4 4 0 0 1 5.4-5.4l-2.6 2.6 2 2 2.6-2.6z"></path>',
        'festivals' => '<path d="M12 2v20"></path><path d="M12 4l8 4-8 4"></path>',
        'sports'    => '<circle cx="12" cy="12" r="9"></circle><path d="M12 3a15 15 0 0 1 0 18M3 12h18"></path>',
        'default'   => '<path d="M3 9a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2 2 2 0 0 0 0 4 2 2 0 0 1-2 2H5a2 2 0 0 1-2-2 2 2 0 0 0 0-4z"></path><line x1="14" y1="7" x2="14" y2="17" stroke-dasharray="1.5 2"></line>',
    ];
@endphp

{{-- Discover hero: real value prop on one side, a live featured event on the
     other — rendered in the app's dark-poster card language. --}}
<section class="dhero">
    <div class="dhero__copy">
        <span class="dhero__eyebrow">Discover in {{ $selectedCity ?? 'All India' }}</span>
        <h1 class="dhero__title">Live nights and real games, in one app.</h1>
        <p class="dhero__sub">
            Book concerts and experiences, reserve turf and courts, and follow live
            scores — all from a single account.
        </p>

        <div class="dhero__actions">
            <a href="/events" class="dbtn dbtn--events">Browse events</a>
            <a href="/gamehub" class="dbtn dbtn--gamehub">Open GameHub</a>
        </div>

        <div class="dhero__stats">
            <div>
                <strong>{{ ($listingCount ?? 0) > 0 ? number_format($listingCount) : '—' }}</strong>
                <span>Live listings{{ $selectedCity ? ' in '.$selectedCity : '' }}</span>
            </div>
            <div>
                <strong>2</strong>
                <span>Lanes — Events &amp; Play</span>
            </div>
            <div>
                <strong>24/7</strong>
                <span>Book anytime</span>
            </div>
        </div>
    </div>

    @if($featured)
        @php
            $fimg   = $featured->heroImageUrl() ?? '/bv-white.png';
            $fwhen  = optional($featured->date)->format('D, M j • g:i A');
            $fprice = $featured->price ? '₹'.number_format($featured->price).' onwards' : 'Free';
            $fvenue = trim((string) $featured->venue) ?: trim((string) $featured->location);
        @endphp
        <a class="dfeat" href="/events/{{ $featured->id }}" style="background-image:url('{{ $fimg }}')">
            <span class="dfeat__grad"></span>
            <span class="dfeat__cat">{{ $featured->category ?? 'Featured' }}</span>
            @if(!empty($featured->rating) && $featured->rating > 0)
                <span class="dfeat__rating"><i>★</i>{{ number_format($featured->rating, 1) }}</span>
            @else
                <span class="dfeat__rating dfeat__rating--new">NEW</span>
            @endif
            <span class="dfeat__body">
                <span class="dfeat__date">{{ $fwhen }}</span>
                <span class="dfeat__name">{{ $featured->title }}</span>
                <span class="dfeat__meta">📍 {{ $fvenue }} · {{ $fprice }}</span>
                <span class="dfeat__cta">
                    Book tickets
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                </span>
            </span>
        </a>
    @endif
</section>

{{-- Categories — desktop-scaled twin of the mobile .mcat row, built only from
     categories that actually have events. --}}
@if($deskCats->count())
<div class="dsec__head">
    <div>
        <span class="dsec__eyebrow">Find your night</span>
        <h2>Browse by category</h2>
    </div>
    <a href="/events" class="dsec__link">See all events</a>
</div>
<div class="dcats">
    @foreach($deskCats->take(5) as $cat => $count)
        @php $ico = $deskCatIcons[strtolower($cat)] ?? $deskCatIcons['default']; @endphp
        <a class="dcat" href="/events?category={{ urlencode($cat) }}">
            <span class="dcat__ico">
                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $ico !!}</svg>
            </span>
            <span class="dcat__txt">
                <strong>{{ $cat }}</strong>
                <small>{{ $count }} {{ \Illuminate\Support\Str::plural('event', $count) }}</small>
            </span>
        </a>
    @endforeach
    <a class="dcat dcat--hub" href="/gamehub">
        <span class="dcat__ico">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 12h16M12 4v16"></path><circle cx="12" cy="12" r="9"></circle></svg>
        </span>
        <span class="dcat__txt">
            <strong>GameHub</strong>
            <small>Turf &amp; courts</small>
        </span>
    </a>
</div>
@endif

{{-- Popular experiences — the live feed in the restyled event card. --}}
@if($mEvents->count())
<div class="dsec__head">
    <div>
        <span class="dsec__eyebrow">Handpicked experiences</span>
        <h2>Popular near {{ $selectedCity ?? 'you' }}</h2>
    </div>
    <a href="/events" class="dsec__link">View all</a>
</div>
<div class="events-grid">
    @foreach($mEvents as $event)
        @include('components.event-card', ['event' => $event])
    @endforeach
</div>
@endif
</div>{{-- /.home-desktop-only --}}
@endsection
