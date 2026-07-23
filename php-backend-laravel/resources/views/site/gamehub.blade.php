@extends('site.layout')
@section('footer_icon_secondary', '#16a34a')
@section('content')

{{-- ================================================================= --}}
{{-- MOBILE APP-STYLE GAMEHUB (mirrors the Android app; ≤720px)         --}}
{{--                                                                    --}}
{{-- A port of MainScreen.kt GameHubTabScreen, in its order: green hero --}}
{{-- → ActionBoard (straddling the seam) → Top Player → sport chips →   --}}
{{-- Popular Venues reel → More venues. Values track the app's tokens    --}}
{{-- (GameHubDeep #1B5E20, GameHubGreen #00C853, 20px card radius);      --}}
{{-- change both together.                                              --}}
{{--                                                                    --}}
{{-- Two deliberate divergences from the app, both about not inventing   --}}
{{-- facts the web can't know:                                          --}}
{{--   · No distance chip. The app ranks by a real GPS fix; the site has --}}
{{--     only a chosen city, and the app's card already omits the chip   --}}
{{--     when distance is blank — so this is its own empty state, not a  --}}
{{--     missing feature.                                               --}}
{{--   · No "Available tonight" badge. The app hardcodes availableTonight--}}
{{--     = true on every venue, so the badge is decoration there; on the --}}
{{--     public site it would be a booking claim we haven't checked.     --}}
{{-- ================================================================= --}}
@php
    // The app's ActionBoard matches are cricket today (LiveMatchRow carries no sport),
    // so a non-cricket chip empties the board rather than showing football fixtures
    // that don't exist. Same rule here.
    $hubLive = ($selectedSport === 'All' || $selectedSport === 'Cricket') ? $liveMatches : [];
    $hubFeatured = $hubLive[0] ?? null;

    // Sport → inline glyph, mirroring the app's chip icons (venueSportIcon). Badminton
    // and Tennis share the racket glyph there, so they collapse to one here too.
    $hubGlyphKey = function (string $sport): string {
        $s = strtolower($sport);
        return match (true) {
            str_contains($s, 'cricket') => 'cricket',
            str_contains($s, 'football'), str_contains($s, 'soccer') => 'football',
            str_contains($s, 'badminton'), str_contains($s, 'tennis') => 'racket',
            str_contains($s, 'volley') => 'volleyball',
            default => 'basketball',
        };
    };
    $hubIcons = [
        'all'        => '<rect x="3" y="3" width="7" height="7" rx="1.5"></rect><rect x="14" y="3" width="7" height="7" rx="1.5"></rect><rect x="3" y="14" width="7" height="7" rx="1.5"></rect><rect x="14" y="14" width="7" height="7" rx="1.5"></rect>',
        'cricket'    => '<path d="M15.5 3.5a2.1 2.1 0 0 1 3 3L9 16l-3 1 1-3z"></path><circle cx="6.5" cy="17.5" r="3"></circle>',
        'football'   => '<circle cx="12" cy="12" r="9"></circle><path d="M12 7.5 8.5 10l1.3 4h4.4l1.3-4z"></path><path d="M12 3v4.5M4 9.5 8.5 10M20 9.5 15.5 10M7 20l2.8-6M17 20l-2.8-6"></path>',
        'racket'     => '<ellipse cx="9.5" cy="9.5" rx="6.5" ry="5.5" transform="rotate(-45 9.5 9.5)"></ellipse><path d="M13.5 13.5 20 20"></path><path d="M6.5 6.5 12.5 12.5M6.5 12.5 12.5 6.5"></path>',
        'volleyball' => '<circle cx="12" cy="12" r="9"></circle><path d="M12 3a15 15 0 0 0 0 18M3.5 8.5a15 15 0 0 1 17 7M3.5 15.5a15 15 0 0 0 17-7"></path>',
        'basketball' => '<circle cx="12" cy="12" r="9"></circle><path d="M12 3v18M3 12h18M5.6 5.6a12 12 0 0 0 12.8 12.8M18.4 5.6A12 12 0 0 1 5.6 18.4"></path>',
    ];
@endphp

<div class="mhub">
    {{-- 1. The app's green hero. This IS the page header, not a banner under one: on the
         GameHub tab the app renders no outer header at all (MainScreen.kt skips it when
         `isGameHubTab`) and stacks greeting → search → switch on the green band. So the
         site's white topbar is hidden here (see the CSS) and its three controls live on
         the green, in the app's order — otherwise the page carries two search bars and
         two tab controls, one white and one green. --}}
    <div class="mhub__hero">
        {{-- The "H" monogram (the app's haraan_copy drawable), not the wordmark: the
             band wants identity-as-texture, and a wordmark stretched to 210px is a
             squashed logo. --}}
        <img class="mhub__mark" src="{{ asset('images/haraan-mark.png') }}" alt="" aria-hidden="true">

        @include('site.partials.app-greet', ['onDark' => true])

        <form class="mhub__search" action="/search" method="GET" role="search">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><line x1="20" y1="20" x2="16.5" y2="16.5"></line></svg>
            <input type="text" name="q" placeholder="Search grounds, matches, players..." autocomplete="off">
        </form>

        {{-- The app's GameHubSegmentedSwitch: a translucent track with a white active
             pill. Real links here, since the web's two lanes are two pages. --}}
        <div class="mhub__switch" role="tablist" aria-label="Events or GameHub">
            <a class="mhub__switch-tab" href="/events" role="tab" aria-selected="false">Events</a>
            <a class="mhub__switch-tab is-on" href="/gamehub" role="tab" aria-selected="true">GameHub</a>
        </div>
    </div>

    {{-- 2. ActionBoard — the elevated hero card, straddling the green seam. --}}
    <a class="mhub__ab" href="/gamehub/actionboard">
        <div class="mhub__ab-head">
            <img src="{{ asset('images/haraan-mark.png') }}" alt="" aria-hidden="true">
            <strong>ActionBoard</strong>
            @if(count($hubLive))
                <span class="mhub__ab-live"><i></i>{{ count($hubLive) }} LIVE</span>
            @endif
        </div>

        @if($hubFeatured)
            @php
                // The batting side leads the card, as in the app's live list.
                $hubRows = collect([$hubFeatured['home'], $hubFeatured['away']])
                    ->sortByDesc(fn ($t) => (bool) $t['batting'])
                    ->values();
                $hubMeta = collect([
                    $hubFeatured['competition'] ?: null,
                    ($hubFeatured['home']['overs'] ?: $hubFeatured['away']['overs']) ? trim((string) ($hubFeatured['home']['overs'] ?: $hubFeatured['away']['overs'])) . ' ov' : null,
                ])->filter()->implode(' • ');
            @endphp
            <div class="mhub__ab-strip">
                @if($hubMeta)<p class="mhub__ab-meta">{{ $hubMeta }}</p>@endif
                @foreach($hubRows as $team)
                    <div class="mhub__ab-row {{ $team['batting'] ? 'is-batting' : '' }}">
                        <span class="mhub__ab-logo">{{ \Illuminate\Support\Str::of($team['abbr'])->substr(0, 2)->upper() }}</span>
                        <span class="mhub__ab-abbr">{{ $team['abbr'] }}</span>
                        <span class="mhub__ab-name">{{ $team['name'] }}</span>
                        <span class="mhub__ab-score">{{ $team['score'] }}</span>
                        @if($team['overs'])<span class="mhub__ab-ov">({{ $team['overs'] }})</span>@endif
                    </div>
                @endforeach
            </div>
        @else
            {{-- The app's honest empty state, sport-aware — never a scripted match. --}}
            <p class="mhub__ab-empty">
                {{ $selectedSport === 'All'
                    ? 'No live matches right now · tap to open the board'
                    : 'No live ' . $selectedSport . ' matches right now · tap to open the board' }}
            </p>
        @endif
    </a>

    {{-- 3. Top Player — the app's LeaderboardHomeWidget, on the same ranked-XP board. --}}
    <a class="mhub__top" href="/gamehub/leaderboard">
        <span class="mhub__top-face">
            @if(!empty($topPlayer['avatar']))
                <img src="{{ $topPlayer['avatar'] }}" alt="" loading="lazy">
            @else
                <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="5"></circle><path d="M8.2 12.5 7 22l5-2.6L17 22l-1.2-9.5"></path></svg>
            @endif
        </span>
        <span class="mhub__top-copy">
            <strong>Top Player</strong>
            @if($topPlayer)
                <span class="mhub__top-line">{{ $topPlayer['name'] }} &nbsp;•&nbsp; +{{ $topPlayer['xp'] }} XP</span>
                <span class="mhub__top-rank">#{{ $topPlayer['rank'] }}{{ $selectedCity ? ' in ' . $selectedCity : '' }}</span>
            @else
                {{-- Two lines, as the app allows this state: it's a sentence, not a name,
                     and one line ellipsises it to "Play a ranked matc…". --}}
                <span class="mhub__top-line mhub__top-line--empty">
                    {{ $selectedCity ? 'No ranked players in ' . $selectedCity . ' yet' : 'Play a ranked match to appear here' }}
                </span>
            @endif
        </span>
        <span class="mhub__top-cta">View Standings</span>
    </a>

    {{-- 4. Sport chips — a global filter over the venues below AND the ActionBoard. --}}
    <div class="mhub__chips">
        @foreach($sportChips as $chip)
            @php $key = $chip === 'All' ? 'all' : $hubGlyphKey($chip); @endphp
            <a class="mhub__chip {{ $chip === $selectedSport ? 'is-on' : '' }}"
               href="{{ $chip === 'All' ? '/gamehub' : '/gamehub?sport=' . urlencode($chip) }}">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $hubIcons[$key] !!}</svg>
                {{ $chip }}
            </a>
        @endforeach
    </div>

    {{-- 5. Popular Venues — the app's top-5-by-rating reel. --}}
    @php $hubTotal = $popularVenues->count() + $moreVenues->count(); @endphp
    <div class="mhub__head">
        <div>
            <h3>Popular Venues</h3>
            {{-- The app's contextual subtitle, minus its two GPS branches: without a fix
                 it says "N venues • top rated", which is exactly how these are ranked.
                 Pluralised, which the app's string isn't — that's its bug, not its design. --}}
            <p>{{ $hubTotal ? $hubTotal . ' ' . \Illuminate\Support\Str::plural('venue', $hubTotal) . ' • top rated' : 'No venues yet' }}</p>
        </div>
        {{-- Only when there's somewhere to go. The app's "View all" opens its search
             overlay, which the web answers with the header's search; with ≤5 venues the
             reel is already the whole catalogue, so a link here would jump nowhere. --}}
        @if($moreVenues->count())
            <a href="#mhub-more">View all</a>
        @endif
    </div>

    @if($popularVenues->count())
        <div class="mhub__reel">
            @foreach($popularVenues as $venue)
                <a class="mhub__arena" href="/gamehub/{{ $venue->id }}">
                    <span class="mhub__arena-img">
                        <img src="{{ $venue->image }}" alt="{{ $venue->title }}" loading="lazy" decoding="async">
                        <span class="mhub__arena-scrim"></span>
                        <span class="mhub__arena-cat">{{ $venue->category }}</span>
                        @if($venue->reviews > 0)
                            <span class="mhub__arena-rate"><i>★</i>{{ $venue->rating }}</span>
                        @endif
                    </span>
                    <span class="mhub__arena-body">
                        <strong class="mhub__arena-title">{{ $venue->title }}</strong>
                        <span class="mhub__arena-sub">{{ $venue->tagline ?: $venue->location }}</span>
                        <span class="mhub__arena-foot">
                            <span class="mhub__price">₹{{ number_format($venue->price) }}<i>/hr</i></span>
                            @include('site.partials.hub-sports', ['sports' => $venue->sports, 'glyphKey' => $hubGlyphKey, 'icons' => $hubIcons])
                        </span>
                    </span>
                </a>
            @endforeach
        </div>
    @else
        <p class="mhub__empty">No venues match this filter yet.</p>
    @endif

    {{-- 6. More venues — strictly what the reel didn't show, so nothing repeats. --}}
    @if($moreVenues->count())
        <div class="mhub__head mhub__head--tight" id="mhub-more">
            <div>
                <h3>More venues</h3>
                <p>{{ $moreVenues->count() }} more to explore</p>
            </div>
        </div>
        <div class="mhub__list">
            @foreach($moreVenues as $venue)
                <a class="mhub__vcard" href="/gamehub/{{ $venue->id }}">
                    <span class="mhub__vcard-img">
                        <img src="{{ $venue->image }}" alt="{{ $venue->title }}" loading="lazy" decoding="async">
                        <span class="mhub__vcard-scrim"></span>
                        <span class="mhub__arena-cat">{{ $venue->category }}</span>
                        @if($venue->tagline)<span class="mhub__vcard-tag">{{ $venue->tagline }}</span>@endif
                    </span>
                    <span class="mhub__vcard-body">
                        <span class="mhub__vcard-top">
                            <span class="mhub__vcard-id">
                                <strong>{{ $venue->title }}</strong>
                                <span class="mhub__vcard-loc">
                                    <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                                    {{ $venue->location }}
                                </span>
                            </span>
                            @if($venue->reviews > 0)
                                <span class="mhub__vcard-rate"><i>★</i>{{ $venue->rating }}</span>
                            @else
                                <span class="mhub__vcard-new">New</span>
                            @endif
                        </span>
                        <span class="mhub__vcard-foot">
                            <span class="mhub__price mhub__price--lg">₹{{ number_format($venue->price) }}<i>/hr</i></span>
                            <span class="mhub__book">Book Slot</span>
                        </span>
                    </span>
                </a>
            @endforeach
        </div>
    @endif
</div>

<section class="page-shell gamehub-page theme-gamehub">
    <div class="gamehub-actions">
        <a href="/gamehub/actionboard" class="gamehub-action-card active thanna-trigger">
            <div class="action-card__icon-wrapper">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="action-card__svg">
                    <path d="M21 7.5V16.5M21 7.5L12 3L3 7.5M21 7.5L12 12M3 7.5V16.5M3 7.5L12 12M12 12V21M12 21L21 16.5M12 21L3 16.5" />
                    <polygon points="12 4.5 8 9.5 11.5 9.5 10.5 14.5 15.5 9.5 12 9.5 13 4.5" fill="currentColor" stroke="none" />
                </svg>
            </div>
            <div class="action-card__content">
                <span class="action-card__title">ActionBoard</span>
                <span class="action-card__desc">Join active open sessions</span>
            </div>
            <span class="action-card__badge">Live Match</span>
        </a>
        <a href="#featured-venues" class="gamehub-action-card">
            <div class="action-card__icon-wrapper">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="action-card__svg">
                    <line x1="5" y1="18" x2="5" y2="7" />
                    <line x1="9" y1="18" x2="9" y2="7" />
                    <line x1="13" y1="18" x2="13" y2="7" />
                    <line x1="4" y1="7" x2="14" y2="7" />
                    <path d="M19.5 3.5l1 1-11.5 11.5-2.5-2.5 13-10z" />
                    <path d="M6.5 13.5l-3 3c-.7.7-.7 1.8 0 2.5s1.8.7 2.5 0l3-3" />
                    <circle cx="19" cy="18" r="3" />
                    <path d="M17 17a3 3 0 0 1 4 2" />
                </svg>
            </div>
            <div class="action-card__content">
                <span class="action-card__title">Reserve Turf</span>
                <span class="action-card__desc">Book secure hourly slots</span>
            </div>
        </a>
        <a href="#all-sports" class="gamehub-action-card">
            <div class="action-card__icon-wrapper">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="action-card__svg">
                    <ellipse cx="12" cy="10" rx="9" ry="5.5" />
                    <ellipse cx="12" cy="10" rx="6.2" ry="3.8" />
                    <line x1="12" y1="8" x2="12" y2="12" />
                    <circle cx="12" cy="10" r="1.2" />
                    <path d="M3 10v6c0 3 4 5.5 9 5.5s9-2.5 9-5.5v-6" />
                    <line x1="7.5" y1="14.2" x2="7.5" y2="19.5" />
                    <line x1="12" y1="15.5" x2="12" y2="21.5" />
                    <line x1="16.5" y1="14.2" x2="16.5" y2="19.5" />
                </svg>
            </div>
            <div class="action-card__content">
                <span class="action-card__title">Explore Arenas</span>
                <span class="action-card__desc">Find premier sports grounds</span>
            </div>
        </a>
    </div>

    {{-- Live now: real in-progress matches (mobile only; mirrors the app ActionboardLiveStrip) --}}
    @if(!empty($liveMatches) && count($liveMatches))
    <section class="gh-live" aria-label="Live matches">
        <div class="gh-live__head">
            <span class="gh-live__title"><span class="gh-live__dot"></span>Live now</span>
            <a href="/gamehub/actionboard" class="gh-live__all">See all</a>
        </div>
        <div class="gh-live__scroll">
            @foreach($liveMatches as $lm)
                <a class="gh-live__card" href="/gamehub/actionboard/match/{{ $lm['id'] }}">
                    <div class="gh-live__cardhead">
                        <span class="gh-live__comp">{{ $lm['competition'] ?: 'Live match' }}</span>
                        <span class="gh-live__badge">LIVE</span>
                    </div>
                    @foreach([$lm['home'], $lm['away']] as $team)
                        <div class="gh-live__team {{ $team['batting'] ? 'is-batting' : '' }}">
                            <span class="gh-live__logo">{{ \Illuminate\Support\Str::of($team['abbr'])->substr(0, 2)->upper() }}</span>
                            <span class="gh-live__abbr">{{ $team['abbr'] }}</span>
                            <span class="gh-live__name">{{ $team['name'] }}</span>
                            <span class="gh-live__score">{{ $team['score'] }}</span>
                            @if($team['overs'])<span class="gh-live__ov">({{ $team['overs'] }})</span>@endif
                        </div>
                    @endforeach
                </a>
            @endforeach
        </div>
    </section>
    @endif

    <section class="gamehub-hero">
        <div class="gamehub-hero__copy">
            <h1>Venues in {{ $selectedCity ?? 'All India' }}</h1>
            <p>Discover and book premium sports facilities curated for champions.</p>

            <div class="search-strip search-strip--gamehub">
                <label class="search-field">
                    <span>⌕</span>
                    <input type="text" id="venueSearchInput" value="" placeholder="Search by name or sport...">
                </label>
                <button class="filter-button filter-button--dropdown" type="button" id="venueClearFilter">All Sports</button>
            </div>
        </div>

        <div class="gamehub-hero__art">
            <img src="{{ asset('gamehub.png') }}" alt="GameHub artwork">
        </div>
    </section>

    <section class="events-section" id="all-sports">
        <div class="section-shell__header">
            <div>
                <p class="eyebrow eyebrow--soft">Explore by Sport</p>
                <h2>Find specialized facilities curated for your discipline.</h2>
            </div>
        </div>

        @php
            $sportTiles = [
                ['name' => 'Cricket', 'icon' => 'https://cdn-icons-png.flaticon.com/512/5140/5140374.png', 'class' => ''],
                ['name' => 'Football', 'icon' => 'https://cdn-icons-png.flaticon.com/512/7711/7711842.png', 'class' => 'sport-card--football'],
                ['name' => 'Badminton', 'icon' => 'https://cdn-icons-png.flaticon.com/512/10904/10904303.png', 'class' => ''],
                ['name' => 'Swimming', 'icon' => 'https://cdn-icons-png.flaticon.com/512/8317/8317259.png', 'class' => ''],
                ['name' => 'Tennis', 'icon' => 'https://cdn-icons-png.flaticon.com/512/8927/8927653.png', 'class' => ''],
                ['name' => 'Basketball', 'icon' => 'https://cdn-icons-png.flaticon.com/512/9128/9128501.png', 'class' => ''],
            ];
        @endphp
        <div class="sport-grid">
            @foreach($sportTiles as $tile)
                @php $count = (int) (($sportCounts ?? collect())[$tile['name']] ?? 0); @endphp
                <button class="sport-card {{ $tile['class'] }}" type="button" data-sport="{{ $tile['name'] }}">
                    <span class="sport-card__icon" aria-hidden="true">
                        <img src="{{ $tile['icon'] }}" alt="{{ $tile['name'] }} icon" />
                    </span>
                    <span>{{ $tile['name'] }}</span><small>{{ $count }} {{ $count === 1 ? 'venue' : 'venues' }}</small>
                </button>
            @endforeach
        </div>
    </section>

    <section class="events-section" id="featured-venues">
        <div class="section-shell__header">
            <div>
                <p class="eyebrow eyebrow--soft">Featured Venues</p>
                <h2>Top-rated facilities for your perfect game.</h2>
            </div>
            <a href="#all-venues" class="text-link">View all</a>
        </div>

        @if(isset($venues) && count($venues))
            @php
                $sportIcons = [
                    'Cricket' => 'https://cdn-icons-png.flaticon.com/512/5140/5140374.png',
                    'Football' => 'https://cdn-icons-png.flaticon.com/512/7711/7711842.png',
                    'Badminton' => 'https://cdn-icons-png.flaticon.com/512/10904/10904303.png',
                    'Swimming' => 'https://cdn-icons-png.flaticon.com/512/8317/8317259.png',
                    'Tennis' => 'https://cdn-icons-png.flaticon.com/512/8927/8927653.png',
                    'Basketball' => 'https://cdn-icons-png.flaticon.com/512/9128/9128501.png',
                ];
            @endphp
            <div class="events-grid gamehub-venues-grid">
                @foreach($venues as $venue)
                    <a class="event-card gamehub-venue-card" href="/gamehub/{{ $venue->id }}" data-venue-name="{{ \Illuminate\Support\Str::lower($venue->title) }}" data-venue-category="{{ \Illuminate\Support\Str::lower($venue->category) }}">
                        <div class="event-card__thumb">
                            <img src="{{ $venue->image }}" alt="{{ $venue->title }}" />
                            <div class="event-card__thumb-overlay">
                                <div class="event-card__thumb-text">
                                    <div class="event-card__thumb-venue">{{ $venue->location }}</div>
                                    <h3 class="event-card__thumb-title">{{ $venue->title }}</h3>
                                </div>
                            </div>
                            @if(isset($venue->badge))
                                <span class="venue-badge">{{ $venue->badge }}</span>
                            @endif
                        </div>
                        <div class="event-card__body gamehub-venue-card__body">
                            <!-- Category & Rating Row -->
                            <div class="event-card__meta gamehub-venue-card__meta">
                                <span class="event-card__category gamehub-venue-card__category" style="display: flex; align-items: center; gap: 6px;">
                                    @if(isset($sportIcons[$venue->category]))
                                        <img src="{{ $sportIcons[$venue->category] }}" alt="{{ $venue->category }} icon" style="width: 16px; height: 16px; object-fit: contain;" />
                                    @endif
                                    {{ $venue->category }}
                                </span>
                                @if($venue->reviews > 0)
                                    <span class="event-card__rating gamehub-venue-card__rating">
                                        <span class="gamehub-venue-card__star">★</span>
                                        <span class="gamehub-venue-card__rating-value">{{ $venue->rating }}</span>
                                        <span class="gamehub-venue-card__reviews-count">({{ $venue->reviews }})</span>
                                    </span>
                                @else
                                    <span class="event-card__rating gamehub-venue-card__rating gamehub-venue-card__rating--new">New</span>
                                @endif
                            </div>
                            
                            <!-- Price & Action Button Row -->
                            <div class="event-card__actions gamehub-venue-card__actions">
                                <span class="event-card__price gamehub-venue-card__price">
                                    ₹{{ number_format($venue->price) }}/hr
                                </span>
                                <span class="btn btn--solid event-card__btn gamehub-venue-card__btn">
                                    Book Slot
                                </span>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        @else
            <div class="empty-state">
                <p>No facilities found in this area</p>
                <button class="filter-button" type="button">Clear all filters</button>
            </div>
        @endif
    </section>

    <a class="promo-banner" href="#all-venues">
        <img src="{{ asset('gamehub.png') }}" alt="Promo banner">
        <div class="promo-banner__content">
            <span class="eyebrow eyebrow--soft">Limited Access</span>
            <h2>Unlock the Best Arenas in the City</h2>
            <p>Book elite facilities with professional equipment and instant digital scheduling.</p>
            <span class="btn btn--solid">Start Booking</span>
        </div>
    </a>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const cards = document.querySelectorAll('.gamehub-action-card');
    cards.forEach(card => {
        card.addEventListener('click', (e) => {
            cards.forEach(c => c.classList.remove('active'));
            card.classList.add('active');
        });
    });

    /* ---- Venue filtering: search box + sport tiles ---- */
    const searchInput = document.getElementById('venueSearchInput');
    const clearBtn    = document.getElementById('venueClearFilter');
    const sportTiles  = document.querySelectorAll('.sport-card[data-sport]');
    const venueCards  = Array.from(document.querySelectorAll('.gamehub-venue-card'));
    const venuesGrid  = document.querySelector('.gamehub-venues-grid');

    let activeSport = null;

    // Empty-message element shown when nothing matches.
    let emptyMsg = null;
    if (venuesGrid) {
        emptyMsg = document.createElement('div');
        emptyMsg.className = 'empty-state';
        emptyMsg.style.display = 'none';
        emptyMsg.innerHTML = '<p>No venues match your filters</p>';
        venuesGrid.parentNode.insertBefore(emptyMsg, venuesGrid.nextSibling);
    }

    function applyFilters() {
        const q = (searchInput?.value || '').trim().toLowerCase();
        let visible = 0;

        venueCards.forEach(card => {
            const name = card.getAttribute('data-venue-name') || '';
            const cat  = card.getAttribute('data-venue-category') || '';
            const matchesText  = !q || name.includes(q) || cat.includes(q);
            const matchesSport = !activeSport || cat === activeSport.toLowerCase();
            const show = matchesText && matchesSport;
            card.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        if (emptyMsg) emptyMsg.style.display = visible === 0 ? 'block' : 'none';
        if (venuesGrid) venuesGrid.style.display = visible === 0 ? 'none' : '';
    }

    searchInput?.addEventListener('input', applyFilters);

    sportTiles.forEach(tile => {
        tile.addEventListener('click', () => {
            const sport = tile.getAttribute('data-sport');
            if (activeSport === sport) {
                activeSport = null;
                tile.classList.remove('is-active');
            } else {
                activeSport = sport;
                sportTiles.forEach(t => t.classList.remove('is-active'));
                tile.classList.add('is-active');
            }
            applyFilters();
            document.getElementById('featured-venues')?.scrollIntoView({ behavior: 'smooth' });
        });
    });

    clearBtn?.addEventListener('click', () => {
        activeSport = null;
        if (searchInput) searchInput.value = '';
        sportTiles.forEach(t => t.classList.remove('is-active'));
        applyFilters();
    });
});
</script>
@endsection

@include('site.partials.thanna-actionboard')
