@extends('site.layout')
@section('footer_icon_secondary', '#2563EB')
@section('content')
@include('site.partials.match-helpers')

{{-- ================================================================= --}}
{{-- PULSE — one responsive page for phone and desktop.                 --}}
{{--                                                                    --}}
{{-- Order: heading + the true state of the lane → ActionBoard (kept as  --}}
{{-- the app's card) → Top Player on a drawn rostrum → sport filter →    --}}
{{-- grounds. Everything states only what we know: no distance chip (no --}}
{{-- GPS fix on the web), no "available tonight" claim, no venue counts  --}}
{{-- per sport, no stock artwork. With nothing listed the page says so   --}}
{{-- and draws the kind of court being filtered, to scale.               --}}
{{-- ================================================================= --}}
@php
    // ActionBoard matches are cricket today, so a non-cricket chip empties the board
    // rather than showing fixtures that don't exist (same rule as the app).
    $hubLive = ($selectedSport === 'All' || $selectedSport === 'Cricket') ? $liveMatches : [];
    $hubFeatured = $hubLive[0] ?? null;

    $hubGlyphKey = function (string $sport): string {
        $s = strtolower($sport);
        return match (true) {
            str_contains($s, 'cricket') => 'cricket',
            str_contains($s, 'football'), str_contains($s, 'soccer') => 'football',
            str_contains($s, 'badminton'), str_contains($s, 'tennis'), str_contains($s, 'pickle') => 'racket',
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

    // Every ground for the current filter, best-rated first, nothing twice.
    $grounds = $popularVenues->concat($moreVenues)->unique('id')->values();
    $groundCount = $grounds->count();
    $fromPrice = $grounds->pluck('price')->filter(fn ($p) => (int) $p > 0)->min();
    $where = $selectedCity ?: 'India';
    $sportWord = $selectedSport === 'All' ? null : strtolower($selectedSport);

    $plan = match ($selectedSport) {
        'Badminton' => 'badminton',
        'Football' => 'football',
        default => 'box',
    };

    // The rostrum's #1 block carries the real leader, or stays open.
    $leader = $topPlayer ?: null;
    $leaderInitial = $leader ? mb_strtoupper(mb_substr(trim((string) $leader['name']), 0, 1)) : null;
@endphp

<style>
    /* Pulse runs on the site's white header (as Events does). The old blue band with a
       watermark monogram, and the desktop action tiles / sport tiles / promo, are gone. */
    .pl {
        --ink: #0F172A; --ink-2: #334155; --ink-3: #64748B; --rule: #E3E8EF;
        --blue: #2563EB; --blue-ink: #1E40AF; --blue-tint: #EEF4FF; --deep: #1E3A8A;
        max-width: 1120px; margin: 0 auto; padding: 8px 0 64px;
        font-family: 'Plus Jakarta Sans', system-ui, sans-serif; color: var(--ink);
        font-variant-numeric: tabular-nums;
    }
    .pl a { color: inherit; }

    .pl-head { padding: 22px 0 22px; }
    .pl-head h1 { margin: 0; font-size: clamp(26px, 3.6vw, 40px); line-height: 1.08; font-weight: 800; letter-spacing: -0.025em; }
    .pl-head p { margin: 10px 0 0; max-width: 620px; font-size: 15.5px; line-height: 1.6; color: var(--ink-2); }
    .pl-head p strong { color: var(--ink); }

    .pl-top { display: grid; grid-template-columns: minmax(0, 1.35fr) minmax(0, 1fr); gap: 20px; align-items: stretch; }

    /* ActionBoard — the app's card, unchanged in look; only its phone-only "straddle
       the hero" margin is neutralised, since there is no band to straddle. */
    .pl .mhub__ab {
        position: relative; display: block; margin: 0; padding: 18px; background: #fff; border-radius: 20px;
        text-decoration: none; box-shadow: 0 8px 18px rgba(15, 23, 42, .10), 0 1px 3px rgba(15, 23, 42, .16);
    }
    .pl .mhub__ab-head { display: flex; align-items: center; gap: 6px; }
    .pl .mhub__ab-head img { width: 22px; height: 22px; object-fit: contain; }
    .pl .mhub__ab-head strong { font-size: 18px; font-weight: 800; letter-spacing: .3px; color: #0F172A; }
    .pl .mhub__ab-live { display: inline-flex; align-items: center; gap: 5px; margin-left: auto; font-size: 13px; font-weight: 800; letter-spacing: .3px; color: #D32F2F; }
    .pl .mhub__ab-live i { width: 6px; height: 6px; border-radius: 50%; background: #D32F2F; }
    .pl .mhub__ab-strip { margin-top: 10px; border-top: 1px solid #E2E8F0; padding-top: 10px; }
    .pl .mhub__ab-meta { margin: 0 0 8px; font-size: 11px; font-weight: 500; color: #64748B; }
    .pl .mhub__ab-empty { margin: 8px 0 0; font-size: 13px; color: #64748B; }
    .pl .mhub__ab-row { display: flex; align-items: center; gap: 6px; }
    .pl .mhub__ab-row + .mhub__ab-row { margin-top: 6px; }
    .pl .mhub__ab-logo { display: grid; place-items: center; width: 24px; height: 24px; flex-shrink: 0; margin-right: 2px; border-radius: 50%; background: #F1F5F9; font-size: 9px; font-weight: 800; color: #475569; overflow: hidden; }
    .pl .mhub__ab-logo.has-img { background: #fff; }
    .pl .mhub__ab-logo img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
    .pl .mhub__ab-abbr { font-size: 14px; font-weight: 700; color: #0F172A; }
    .pl .mhub__ab-name { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 12px; font-weight: 500; color: #64748B; }
    .pl .mhub__ab-score { margin-left: auto; font-size: 15px; font-weight: 700; color: #0F172A; font-feature-settings: 'tnum'; }
    .pl .mhub__ab-ov { min-width: 44px; text-align: right; font-size: 11px; font-weight: 500; color: #94A3B8; font-feature-settings: 'tnum'; }
    .pl .mhub__ab-row.is-batting .mhub__ab-abbr, .pl .mhub__ab-row.is-batting .mhub__ab-score { font-weight: 800; }

    /* Top player on a drawn rostrum: three plinths, #1 in the middle. */
    .pl-rank {
        display: grid; grid-template-columns: 132px minmax(0, 1fr); gap: 16px; align-items: center;
        padding: 16px 18px; background: #fff; border: 1px solid var(--rule); border-radius: 20px;
        text-decoration: none; box-shadow: 0 1px 1px rgba(15, 23, 42, .03);
        transition: box-shadow .18s, transform .18s;
    }
    .pl-rank:hover { box-shadow: 0 14px 30px -16px rgba(15, 23, 42, .3); transform: translateY(-1px); }
    .pl-rank:active { transform: translateY(0); }
    .pl-rank svg { display: block; width: 100%; height: auto; }
    .pl-rank .plinth { fill: #F1F5F9; stroke: #CBD5E1; stroke-width: 1.2; }
    .pl-rank .plinth--1 { fill: var(--blue-tint); stroke: #93B4F5; }
    .pl-rank .plinth-n { font: 800 13px 'Plus Jakarta Sans', system-ui, sans-serif; fill: #94A3B8; }
    .pl-rank .plinth--1 + .plinth-n { fill: var(--blue); }
    .pl-rank .seat { fill: none; stroke: #93B4F5; stroke-width: 1.6; stroke-dasharray: 4 3.5; }
    .pl-rank .face { fill: var(--deep); }
    .pl-rank .face-t { font: 800 15px 'Plus Jakarta Sans', system-ui, sans-serif; fill: #fff; }
    .pl-rank .ground { stroke: #CBD5E1; stroke-width: 1.2; }
    .pl-rank__eyebrow { font-size: 12.5px; font-weight: 700; color: var(--ink-3); }
    .pl-rank__line { display: block; margin-top: 3px; font-size: 16.5px; font-weight: 800; line-height: 1.3; }
    .pl-rank__sub { display: block; margin-top: 3px; font-size: 13.5px; line-height: 1.5; color: var(--ink-2); }
    .pl-rank__go { display: inline-block; margin-top: 8px; font-size: 14px; font-weight: 700; color: var(--blue); }

    /* Sport filter */
    .pl-chips { display: flex; gap: 8px; margin: 28px 0 0; overflow-x: auto; scrollbar-width: none; padding: 2px 0 4px; }
    .pl-chips::-webkit-scrollbar { display: none; }
    .pl-chip {
        flex: none; display: inline-flex; align-items: center; gap: 7px; height: 38px; padding: 0 15px 0 13px;
        border-radius: 999px; border: 1px solid var(--rule); background: #fff; color: var(--ink-2);
        font-size: 14px; font-weight: 600; text-decoration: none; box-shadow: 0 1px 0 rgba(15, 23, 42, .04);
        transition: border-color .15s, color .15s, transform .08s;
    }
    .pl-chip svg { color: var(--ink-3); }
    .pl-chip:hover { border-color: #C7D2FE; color: var(--blue-ink); }
    .pl-chip:active { transform: translateY(1px); box-shadow: none; }
    .pl-chip[aria-current="page"] { background: var(--blue-tint); border-color: var(--blue); color: var(--blue-ink); }
    .pl-chip[aria-current="page"] svg { color: var(--blue); }

    /* Grounds */
    .pl-sec { margin-top: 26px; }
    .pl-sec__head { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; margin-bottom: 14px; }
    .pl h2 { margin: 0; font-size: 21px; font-weight: 800; letter-spacing: -0.015em; }
    .pl-sec__head span { font-size: 13.5px; color: var(--ink-3); }
    .pl-grounds { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 18px; }
    .pl-ground {
        display: flex; flex-direction: column; background: #fff; border: 1px solid var(--rule); border-radius: 16px;
        overflow: hidden; text-decoration: none; box-shadow: 0 1px 1px rgba(15, 23, 42, .03);
        transition: transform .18s ease, box-shadow .18s ease;
    }
    .pl-ground:hover { transform: translateY(-2px); box-shadow: 0 14px 30px -14px rgba(15, 23, 42, .28); }
    .pl-ground:active { transform: translateY(0); }
    .pl-ground__img { aspect-ratio: 4 / 3; width: 100%; object-fit: cover; background: #E9EEF4; display: block; }
    .pl-ground__body { padding: 13px 15px 15px; display: flex; flex-direction: column; gap: 3px; flex: 1; }
    .pl-ground__top { display: flex; justify-content: space-between; gap: 10px; align-items: baseline; }
    .pl-ground__name { margin: 0; font-size: 16px; font-weight: 800; line-height: 1.3; }
    .pl-ground__rate { flex: none; font-size: 13px; font-weight: 700; color: var(--ink-2); }
    .pl-ground__rate i { font-style: normal; color: #EAB308; margin-right: 2px; }
    .pl-ground__where { font-size: 13.5px; color: var(--ink-3); }
    .pl-ground__foot { margin-top: auto; padding-top: 10px; display: flex; align-items: center; justify-content: space-between; }
    .pl-ground__price { font-size: 17px; font-weight: 800; }
    .pl-ground__price small { font-size: 12.5px; font-weight: 600; color: var(--ink-3); }
    .pl-ground .mhub__sports { display: inline-flex; gap: 6px; align-items: center; color: var(--ink-3); }
    .pl-ground .mhub__sports i { font-style: normal; font-size: 12px; font-weight: 700; }

    /* Nothing listed: say so, draw the court being filtered, offer the one real action. */
    .pl-none {
        display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 28px; align-items: center;
        background: #fff; border: 1px solid var(--rule); border-radius: 20px; padding: 22px;
    }
    .pl-none h3 { margin: 0; font-size: 19px; font-weight: 800; line-height: 1.3; }
    .pl-none p { margin: 8px 0 0; font-size: 15px; line-height: 1.6; color: var(--ink-2); }
    .pl-none .kv-plan__sheet { box-shadow: none; border-color: #EEF1F5; background: #FBFCFD; }
    .pl-actions { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; margin-top: 18px; }
    .pl-btn {
        display: inline-flex; align-items: center; justify-content: center; height: 46px; padding: 0 20px;
        border-radius: 12px; background: var(--blue); color: #fff !important; font-size: 15px; font-weight: 700;
        text-decoration: none; box-shadow: 0 1px 0 rgba(255,255,255,.18) inset, 0 2px 0 #1D4ED8, 0 6px 14px -6px rgba(37, 99, 235, .55);
        transition: transform .08s, box-shadow .08s, background .15s;
    }
    .pl-btn:hover { background: #1F5BE0; }
    .pl-btn:active { transform: translateY(2px); box-shadow: 0 1px 0 rgba(255,255,255,.18) inset, 0 0 0 #1D4ED8, 0 2px 6px -4px rgba(37, 99, 235, .5); }
    .pl-link { font-size: 15px; font-weight: 700; color: var(--blue) !important; text-decoration: none; }
    .pl-link:hover { text-decoration: underline; text-underline-offset: 3px; }

    @media (max-width: 900px) {
        .pl-top { grid-template-columns: minmax(0, 1fr); gap: 14px; }
        .pl-none { grid-template-columns: minmax(0, 1fr); }
    }
    @media (max-width: 720px) {
        /* Phone: the site header above already greets, searches and switches lanes. */
        .pl { padding: 0 16px 8px; }
        .pl-head { padding: 18px 0 16px; }
        .pl-head h1 { font-size: 26px; }
        .pl-head p { font-size: 14.5px; margin-top: 6px; }
        .pl .mhub__ab { padding: 16px; }
        .pl-rank { grid-template-columns: 96px minmax(0, 1fr); gap: 14px; padding: 14px 16px; }
        .pl-rank__line { font-size: 15.5px; }
        .pl-chips { margin: 22px -16px 0 0; padding-right: 16px; }
        .pl-sec { margin-top: 20px; }
        .pl-grounds { grid-template-columns: minmax(0, 1fr); gap: 14px; }
        .pl-none { padding: 18px; gap: 18px; }
        .pl-btn { flex: 1 1 100%; }
        .pl-actions { gap: 14px; }
    }
</style>

<div class="pl">
    {{-- 1. What this lane is, and what's true in it right now. --}}
    <header class="pl-head">
        <h1>Turfs &amp; courts in {{ $where }}</h1>
        <p>
            @if($groundCount > 0)
                <strong>{{ $groundCount }} {{ \Illuminate\Support\Str::plural('ground', $groundCount) }}</strong>{{ $sportWord ? ' for '.$sportWord : '' }} take bookings on Pulse{{ $fromPrice ? ', from ₹'.number_format((int) $fromPrice).' an hour' : '' }}.
            @else
                Book a court by the hour, score your match live on ActionBoard, and climb the rankings. <strong>No ground{{ $sportWord ? ' for '.$sportWord : '' }} takes online bookings here yet.</strong>
            @endif
        </p>
    </header>

    <div class="pl-top">
        {{-- 2. ActionBoard — kept exactly as the app's card. --}}
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
                        @php $abIcon = hrn_team_icon($team['logo'] ?? '', $team['emblem'] ?? ''); @endphp
                        <div class="mhub__ab-row {{ $team['batting'] ? 'is-batting' : '' }}">
                            <span class="mhub__ab-logo {{ $abIcon !== '' ? 'has-img' : '' }}">@if($abIcon !== '')<img src="{{ $abIcon }}" alt="{{ $team['abbr'] }}" loading="lazy">@else{{ \Illuminate\Support\Str::of($team['abbr'])->substr(0, 2)->upper() }}@endif</span>
                            <span class="mhub__ab-abbr">{{ $team['abbr'] }}</span>
                            <span class="mhub__ab-name">{{ $team['name'] }}</span>
                            <span class="mhub__ab-score">{{ $team['score'] }}</span>
                            @if($team['overs'])<span class="mhub__ab-ov">({{ $team['overs'] }})</span>@endif
                        </div>
                    @endforeach
                </div>
            @else
                <p class="mhub__ab-empty">
                    {{ $selectedSport === 'All'
                        ? 'No live matches right now · tap to open the board'
                        : 'No live ' . $selectedSport . ' matches right now · tap to open the board' }}
                </p>
            @endif
        </a>

        {{-- 3. Top player, standing on #1 — or #1 left open. --}}
        <a class="pl-rank" href="/gamehub/leaderboard">
            <svg viewBox="0 0 132 92" aria-hidden="true">
                <line class="ground" x1="2" y1="90" x2="130" y2="90"/>
                <rect class="plinth" x="6" y="58" width="38" height="32" rx="3"/>
                <text class="plinth-n" x="25" y="80" text-anchor="middle">2</text>
                <rect class="plinth plinth--1" x="47" y="44" width="38" height="46" rx="3"/>
                <text class="plinth-n" x="66" y="72" text-anchor="middle">1</text>
                <rect class="plinth" x="88" y="66" width="38" height="24" rx="3"/>
                <text class="plinth-n" x="107" y="83" text-anchor="middle">3</text>
                @if($leader)
                    <circle class="face" cx="66" cy="24" r="17"/>
                    <text class="face-t" x="66" y="29.5" text-anchor="middle">{{ $leaderInitial }}</text>
                @else
                    <circle class="seat" cx="66" cy="24" r="16"/>
                @endif
            </svg>
            <span>
                <span class="pl-rank__eyebrow">Top player{{ $selectedCity ? ' · '.$selectedCity : '' }}</span>
                @if($leader)
                    <span class="pl-rank__line">{{ $leader['name'] }}</span>
                    <span class="pl-rank__sub">#{{ $leader['rank'] }} this month · {{ number_format((int) $leader['xp']) }} XP</span>
                @else
                    <span class="pl-rank__line">#1 is open</span>
                    <span class="pl-rank__sub">
                        {{ $selectedCity ? 'Nobody in '.$selectedCity.' has a ranked match yet.' : 'Pick your city to see who leads near you.' }}
                        Win one on ActionBoard to take the top spot.
                    </span>
                @endif
                <span class="pl-rank__go">Standings →</span>
            </span>
        </a>
    </div>

    {{-- 4. Sport filter — over the grounds below and the ActionBoard card above. --}}
    <nav class="pl-chips" aria-label="Sport">
        @foreach($sportChips as $chip)
            @php $key = $chip === 'All' ? 'all' : $hubGlyphKey($chip); @endphp
            <a class="pl-chip" href="{{ $chip === 'All' ? '/gamehub' : '/gamehub?sport=' . urlencode($chip) }}" @if($chip === $selectedSport) aria-current="page" @endif>
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $hubIcons[$key] !!}</svg>
                {{ $chip === 'All' ? 'All sports' : $chip }}
            </a>
        @endforeach
    </nav>

    {{-- 5. Grounds. --}}
    <section class="pl-sec">
        @if($groundCount > 0)
            <div class="pl-sec__head">
                <h2>{{ $sportWord ? ucfirst($sportWord).' grounds' : 'Grounds' }}{{ $selectedCity ? ' near '.$selectedCity : '' }}</h2>
                <span>Best rated first</span>
            </div>
            <div class="pl-grounds">
                @foreach($grounds as $venue)
                    <a class="pl-ground" href="{{ \App\Support\SeoUrl::venue($venue) }}">
                        <img class="pl-ground__img" src="{{ $venue->image ?: asset('images/haraan-social-card.png') }}" alt="{{ $venue->title }}" loading="{{ $loop->index < 3 ? 'eager' : 'lazy' }}" decoding="async">
                        <span class="pl-ground__body">
                            <span class="pl-ground__top">
                                <strong class="pl-ground__name">{{ $venue->title }}</strong>
                                @if($venue->reviews > 0)
                                    <span class="pl-ground__rate"><i>★</i>{{ $venue->rating }}</span>
                                @endif
                            </span>
                            <span class="pl-ground__where">{{ trim(($venue->location ?: '').($venue->city && $venue->city !== $venue->location ? ', '.$venue->city : ''), ', ') }}</span>
                            <span class="pl-ground__foot">
                                @if($venue->price > 0)
                                    <span class="pl-ground__price">₹{{ number_format($venue->price) }} <small>/ hour</small></span>
                                @else
                                    <span></span>
                                @endif
                                @include('site.partials.hub-sports', ['sports' => $venue->sports, 'glyphKey' => $hubGlyphKey, 'icons' => $hubIcons])
                            </span>
                        </span>
                    </a>
                @endforeach
            </div>
        @else
            <div class="pl-none">
                <div>
                    <h3>No {{ $sportWord ? $sportWord.' ' : '' }}ground{{ $selectedCity ? ' near '.$selectedCity : '' }} is on Pulse yet</h3>
                    <p>When an owner lists one, it shows here with its courts, open slots and hourly price — and you book it in a minute.</p>
                    <div class="pl-actions">
                        <a class="pl-btn" href="/partner/login">List your ground</a>
                        <a class="pl-link" href="/gamehub/actionboard">Score a match instead →</a>
                    </div>
                </div>
                @include('site.partials.court-plan', ['plan' => $plan])
            </div>
        @endif
    </section>
</div>
@endsection
