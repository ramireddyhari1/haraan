@extends('site.layout')
@section('body_class', 'kv-page')
@section('content')
@php
    $venueCount = $venues->count();
    $prices = $venues->pluck('price')->filter(fn ($p) => (int) $p > 0);
    $fromPrice = $prices->isNotEmpty() ? (int) $prices->min() : null;
    $isKadapa = strcasecmp($cityName, 'Kadapa') === 0;

    // What the page is about, in the words people search with.
    $subject = match ($sportSlug) {
        'box-cricket' => 'Box cricket',
        'cricket' => 'Cricket turfs',
        'badminton' => 'Badminton courts',
        'football' => 'Football turfs',
        null => 'Turfs & courts',
        default => ($sportLabel ?: 'Turfs').' venues',
    };

    // The court plan drawn beside the heading follows the sport being browsed.
    $plan = match ($sportSlug) {
        'badminton' => 'badminton',
        'football' => 'football',
        default => 'box',
    };

    $chips = [
        [null, 'All sports'],
        ['box-cricket', 'Box cricket'],
        ['cricket', 'Cricket'],
        ['badminton', 'Badminton'],
        ['football', 'Football'],
    ];

    // Matches: anything being played or finished first; an abandoned fixture is
    // still real, but it shouldn't lead the list.
    $statusRank = static fn ($m) => match (strtolower((string) $m->status)) {
        'live' => 0,
        'finished', 'completed' => 1,
        'abandoned' => 3,
        default => 2,
    };
    $matches = $localMatches->sortBy($statusRank)->values();
@endphp

<style>
    .kv-page main.container, .kv-page .container { max-width: none; }
    .kv {
        --ink: #0F172A;
        --ink-2: #334155;
        --ink-3: #64748B;
        --rule: #E3E8EF;
        --paper: #FFFFFF;
        --wash: #F6F8FB;
        --blue: #2563EB;
        --blue-ink: #1E40AF;
        --blue-tint: #EEF4FF;
        --turf: #3F7D4E;
        --turf-2: #4A8B5A;
        --court: #2F6E5A;
        font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
        color: var(--ink);
        max-width: 1120px;
        margin: 0 auto;
        padding: 0 20px 72px;
        font-variant-numeric: tabular-nums;
    }
    .kv a { color: inherit; }

    /* ── Top: heading + the court plan ────────────────────────────────── */
    .kv-top {
        display: grid;
        grid-template-columns: minmax(0, 1.05fr) minmax(0, 0.95fr);
        gap: 48px;
        align-items: center;
        padding: 36px 0 40px;
        border-bottom: 1px solid var(--rule);
    }
    .kv-crumbs { display: flex; flex-wrap: wrap; gap: 6px; font-size: 13px; color: var(--ink-3); margin-bottom: 18px; }
    .kv-crumbs a { text-decoration: none; }
    .kv-crumbs a:hover { color: var(--blue); }
    .kv-crumbs span[aria-hidden] { color: #CBD5E1; }
    .kv-place { font-size: 13px; font-weight: 700; color: var(--ink-3); letter-spacing: .02em; margin: 0 0 8px; }
    .kv h1 { margin: 0; font-size: clamp(32px, 4.6vw, 48px); line-height: 1.04; font-weight: 800; letter-spacing: -0.025em; }
    .kv h1 em { font-style: normal; color: var(--ink-3); font-weight: 700; }
    .kv-status { margin: 18px 0 0; max-width: 520px; font-size: 16px; line-height: 1.6; color: var(--ink-2); }
    .kv-status strong { color: var(--ink); }
    .kv-chips { display: flex; gap: 8px; margin-top: 26px; overflow-x: auto; scrollbar-width: none; padding: 2px 0 4px; }
    .kv-chips::-webkit-scrollbar { display: none; }
    .kv-chip {
        flex: none; display: inline-flex; align-items: center; height: 38px; padding: 0 16px;
        border-radius: 999px; border: 1px solid var(--rule); background: var(--paper);
        font-size: 14px; font-weight: 600; color: var(--ink-2); text-decoration: none;
        box-shadow: 0 1px 0 rgba(15, 23, 42, .04);
        transition: border-color .15s, color .15s, transform .08s;
    }
    .kv-chip:hover { border-color: #C7D2FE; color: var(--blue-ink); }
    .kv-chip:active { transform: translateY(1px); box-shadow: none; }
    .kv-chip[aria-current="page"] { background: var(--blue-tint); border-color: var(--blue); color: var(--blue-ink); }

    /* The plan: a scaled, top-down drawing of the court, white lines on the
       playing surface, dimensions in ink outside it — like a groundsman's sheet. */
    .kv-plan { margin: 0; }
    .kv-plan__sheet {
        position: relative; background: var(--paper); border: 1px solid var(--rule); border-radius: 14px;
        padding: 18px 18px 14px;
        box-shadow: 0 1px 1px rgba(15, 23, 42, .04), 0 10px 28px -12px rgba(15, 23, 42, .18);
    }
    .kv-plan svg { display: block; width: 100%; height: auto; }
    .kv-plan .surface { fill: var(--turf); }
    .kv-plan .surface--court { fill: var(--court); }
    .kv-plan .run { fill: var(--turf-2); }
    .kv-plan .mow { fill: #fff; opacity: .055; }
    .kv-plan .line { fill: none; stroke: #fff; stroke-width: 2; stroke-linecap: square; }
    .kv-plan .net { fill: none; stroke: rgba(15, 23, 42, .6); stroke-width: 2; stroke-dasharray: 4 3; }
    .kv-plan .dim { fill: none; stroke: var(--ink-3); stroke-width: 1; }
    .kv-plan .dim-txt { fill: var(--ink-2); font: 600 11px 'Plus Jakarta Sans', system-ui, sans-serif; }
    .kv-plan .note { fill: var(--ink-3); font: 500 10.5px 'Plus Jakarta Sans', system-ui, sans-serif; }
    .kv-plan .stump { fill: #fff; }
    .kv-plan figcaption { display: flex; justify-content: space-between; gap: 12px; margin-top: 10px; font-size: 12.5px; color: var(--ink-3); }
    .kv-plan figcaption b { color: var(--ink); font-weight: 700; }
    @media (prefers-reduced-motion: no-preference) {
        .kv-plan .line { stroke-dasharray: 1200; stroke-dashoffset: 1200; animation: kv-draw 1.1s cubic-bezier(.3,.7,.2,1) .15s forwards; }

        @keyframes kv-draw { to { stroke-dashoffset: 0; } }
        .kv-plan .net { stroke-dasharray: 4 3; stroke-dashoffset: 0; animation: kv-fade .4s ease .9s both; }
        @keyframes kv-fade { from { opacity: 0; } }
    }

    /* ── Sections ─────────────────────────────────────────────────────── */
    .kv-sec { padding: 40px 0 0; }
    .kv-sec__head { display: flex; align-items: baseline; justify-content: space-between; gap: 16px; margin-bottom: 18px; }
    .kv h2 { margin: 0; font-size: 22px; line-height: 1.25; font-weight: 800; letter-spacing: -0.015em; }
    .kv-sec__head a { font-size: 14px; font-weight: 700; color: var(--blue); text-decoration: none; white-space: nowrap; }
    .kv-sec__lead { margin: -8px 0 18px; color: var(--ink-3); font-size: 14.5px; line-height: 1.55; }

    /* Ground cards */
    .kv-grounds { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 18px; }
    .kv-ground {
        display: flex; flex-direction: column; background: var(--paper); border: 1px solid var(--rule); border-radius: 14px;
        overflow: hidden; text-decoration: none;
        box-shadow: 0 1px 1px rgba(15, 23, 42, .03);
        transition: transform .18s ease, box-shadow .18s ease;
    }
    .kv-ground:hover { transform: translateY(-2px); box-shadow: 0 14px 30px -14px rgba(15, 23, 42, .28); }
    .kv-ground:active { transform: translateY(0); }
    .kv-ground__img { aspect-ratio: 4 / 3; width: 100%; object-fit: cover; background: #E9EEF4; display: block; }
    .kv-ground__body { padding: 14px 16px 16px; display: flex; flex-direction: column; gap: 4px; flex: 1; }
    .kv-ground__name { margin: 0; font-size: 16.5px; font-weight: 800; line-height: 1.3; }
    .kv-ground__where { font-size: 13.5px; color: var(--ink-3); }
    .kv-ground__sports { font-size: 13px; color: var(--ink-2); margin-top: 4px; }
    .kv-ground__foot { margin-top: auto; padding-top: 12px; display: flex; align-items: baseline; justify-content: space-between; }
    .kv-ground__price { font-size: 17px; font-weight: 800; }
    .kv-ground__price small { font-size: 12.5px; font-weight: 600; color: var(--ink-3); }
    .kv-ground__go { font-size: 14px; font-weight: 700; color: var(--blue); }
    .kv-ground__rating { font-size: 13px; font-weight: 700; color: var(--ink-2); }

    /* Honest empty state */
    .kv-none {
        display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 24px; align-items: center;
        background: var(--paper); border: 1px solid var(--rule); border-radius: 14px; padding: 24px 26px;
    }
    .kv-none h2 { font-size: 19px; }
    .kv-none p { margin: 6px 0 0; color: var(--ink-2); font-size: 15px; line-height: 1.6; max-width: 600px; }
    .kv-actions { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
    .kv-btn {
        display: inline-flex; align-items: center; justify-content: center; height: 46px; padding: 0 20px;
        border-radius: 12px; background: var(--blue); color: #fff !important; font-size: 15px; font-weight: 700;
        text-decoration: none; box-shadow: 0 1px 0 rgba(255,255,255,.18) inset, 0 2px 0 #1D4ED8, 0 6px 14px -6px rgba(37, 99, 235, .55);
        transition: transform .08s, box-shadow .08s, background .15s;
    }
    .kv-btn:hover { background: #1F5BE0; }
    .kv-btn:active { transform: translateY(2px); box-shadow: 0 1px 0 rgba(255,255,255,.18) inset, 0 0 0 #1D4ED8, 0 2px 6px -4px rgba(37, 99, 235, .5); }
    .kv-link { font-size: 15px; font-weight: 700; color: var(--blue) !important; text-decoration: none; padding: 0 6px; }
    .kv-link:hover { text-decoration: underline; text-underline-offset: 3px; }

    /* Matches: a results sheet, not a wall of cards */
    .kv-sheet { background: var(--paper); border: 1px solid var(--rule); border-radius: 14px; overflow: hidden; }
    .kv-row {
        display: grid; grid-template-columns: 52px minmax(0, 1fr) auto; gap: 16px; align-items: center;
        padding: 14px 18px; text-decoration: none; border-top: 1px solid var(--rule);
        transition: background .12s;
    }
    .kv-row:first-child { border-top: 0; }
    .kv-row:hover { background: var(--wash); }
    .kv-row__date { text-align: center; line-height: 1.1; }
    .kv-row__date b { display: block; font-size: 19px; font-weight: 800; }
    .kv-row__date span { font-size: 11.5px; font-weight: 700; color: var(--ink-3); text-transform: uppercase; letter-spacing: .06em; }
    .kv-row__teams { font-size: 15.5px; font-weight: 700; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .kv-row__teams i { font-style: normal; font-weight: 500; color: var(--ink-3); padding: 0 4px; }
    .kv-row__meta { font-size: 13px; color: var(--ink-3); margin-top: 2px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .kv-state { font-size: 12.5px; font-weight: 700; color: var(--ink-3); white-space: nowrap; }
    .kv-state--live { color: #DC2626; }
    .kv-state--live::before { content: ''; display: inline-block; width: 7px; height: 7px; border-radius: 50%; background: #DC2626; margin-right: 6px; vertical-align: 1px; }
    .kv-state--done { color: var(--ink-2); }

    /* Guide: typeset like an article, columns divided by rules */
    .kv-guide { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); border-top: 2px solid var(--ink); }
    .kv-guide section { padding: 18px 24px 4px 0; }
    .kv-guide section + section { padding-left: 24px; border-left: 1px solid var(--rule); }
    .kv-guide h3 { margin: 0 0 10px; font-size: 16px; font-weight: 800; }
    .kv-guide p, .kv-guide li { font-size: 14.5px; line-height: 1.65; color: var(--ink-2); }
    .kv-guide p { margin: 0 0 10px; }
    .kv-guide ul { list-style: none; margin: 0; padding: 0; }
    .kv-guide li { display: grid; grid-template-columns: 112px 1fr; gap: 10px; padding: 7px 0; border-top: 1px dashed var(--rule); }
    .kv-guide li:first-child { border-top: 0; }
    .kv-guide li b { color: var(--ink); font-weight: 700; }

    /* Owners */
    .kv-owner {
        display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 24px; align-items: center;
        margin-top: 44px; padding: 26px 0; border-top: 1px solid var(--rule); border-bottom: 1px solid var(--rule);
    }
    .kv-owner h2 { font-size: 20px; }
    .kv-owner p { margin: 6px 0 0; font-size: 15px; line-height: 1.6; color: var(--ink-2); max-width: 640px; }

    /* FAQ */
    .kv-faq details { border-top: 1px solid var(--rule); }
    .kv-faq details:last-child { border-bottom: 1px solid var(--rule); }
    .kv-faq summary {
        list-style: none; cursor: pointer; display: flex; justify-content: space-between; align-items: center; gap: 18px;
        padding: 16px 2px; font-size: 16px; font-weight: 700;
    }
    .kv-faq summary::-webkit-details-marker { display: none; }
    .kv-faq summary svg { flex: none; color: var(--ink-3); transition: transform .2s; }
    .kv-faq details[open] summary svg { transform: rotate(45deg); color: var(--blue); }
    .kv-faq p { margin: -4px 0 18px; padding-right: 40px; font-size: 15px; line-height: 1.65; color: var(--ink-2); }

    @media (max-width: 900px) {
        .kv-top { grid-template-columns: minmax(0, 1fr); gap: 28px; padding-top: 24px; }
        .kv-guide { grid-template-columns: minmax(0, 1fr); }
        .kv-guide section, .kv-guide section + section { padding: 18px 0 6px; border-left: 0; }
        .kv-guide section + section { border-top: 1px solid var(--rule); }
    }
    @media (max-width: 600px) {
        .kv { padding: 0 16px 56px; }
        .kv h1 { font-size: 34px; }
        .kv-status { font-size: 15.5px; }
        .kv-chips { margin-right: -16px; padding-right: 16px; }
        .kv-plan__sheet { padding: 12px 10px 10px; }
        .kv-plan figcaption { flex-direction: column; gap: 2px; padding: 0 4px; }
        .kv-none, .kv-owner { grid-template-columns: 1fr; gap: 16px; }
        .kv-none { padding: 20px; }
        .kv-btn { width: 100%; }
        .kv-row { grid-template-columns: 44px minmax(0, 1fr); padding: 12px 14px; }
        .kv-row .kv-state { grid-column: 2; margin-top: -6px; }
        .kv-guide li { grid-template-columns: 96px 1fr; }
        .kv-faq p { padding-right: 0; }
    }
</style>

<div class="kv">
    {{-- ── 1. Heading + the court ─────────────────────────────────────── --}}
    <header class="kv-top">
        <div>
            <nav class="kv-crumbs" aria-label="Breadcrumb">
                <a href="{{ url('/') }}">Home</a><span aria-hidden="true">/</span>
                <a href="{{ route('site.gamehub') }}">Pulse</a><span aria-hidden="true">/</span>
                @if($sportLabel)
                    <a href="{{ url('/venues/'.$citySlug) }}">{{ $cityName }}</a><span aria-hidden="true">/</span>
                    <span>{{ $sportLabel }}</span>
                @else
                    <span>{{ $cityName }}</span>
                @endif
            </nav>

            @if($isKadapa)
                <p class="kv-place">Kadapa · YSR district, Andhra Pradesh</p>
            @endif
            <h1>{{ $subject }} in {{ $cityName }}</h1>

            <p class="kv-status">
                @if($venueCount > 0)
                    <strong>{{ $venueCount }} {{ \Illuminate\Support\Str::plural('ground', $venueCount) }}</strong> in {{ $cityName }} {{ $venueCount === 1 ? 'takes' : 'take' }} bookings on Haraan{{ $fromPrice ? ', from ₹'.number_format($fromPrice).' an hour' : '' }}.
                    Pick a ground, choose a court and an open slot, and pay online.
                @else
                    <strong>No ground in {{ $cityName }} takes online bookings on Haraan yet.</strong>
                    Grounds are being added. Until then, this page covers what to check before you book, and owners can list their ground below.
                @endif
            </p>

            <nav class="kv-chips" aria-label="Sport">
                @foreach($chips as [$slug, $label])
                    <a class="kv-chip" href="{{ url('/venues/'.$citySlug.($slug ? '/'.$slug : '')) }}" @if($sportSlug === $slug) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
            </nav>
        </div>

        <figure class="kv-plan" aria-label="Plan of a {{ $plan === 'box' ? 'box cricket arena' : ($plan === 'badminton' ? 'badminton court' : 'five-a-side football pitch') }}">
            <div class="kv-plan__sheet">
                @if($plan === 'badminton')
                    {{-- BWF doubles court, to scale: 13.40 × 6.10 m (26.87 px/m). --}}
                    <svg viewBox="0 0 440 262" role="img" aria-hidden="true">
                        <rect class="surface surface--court" x="28" y="42" width="384" height="196" rx="2"/>
                        <g class="line">
                            <rect x="40" y="56" width="360" height="164"/>
                            <line x1="40" y1="68.4" x2="400" y2="68.4"/>
                            <line x1="40" y1="207.6" x2="400" y2="207.6"/>
                            <line x1="60.4" y1="56" x2="60.4" y2="220"/>
                            <line x1="379.6" y1="56" x2="379.6" y2="220"/>
                            <line x1="166.8" y1="56" x2="166.8" y2="220"/>
                            <line x1="273.2" y1="56" x2="273.2" y2="220"/>
                            <line x1="40" y1="138" x2="166.8" y2="138"/>
                            <line x1="273.2" y1="138" x2="400" y2="138"/>
                        </g>
                        <line class="net" x1="220" y1="48" x2="220" y2="228"/>
                        <g class="dim">
                            <path d="M40 24v10M400 24v10M40 29h360"/>
                            <path d="M418 56h10M418 220h10M423 56v164"/>
                            <path d="M166.8 250v-8M220 250v-8M168 247h50"/>
                        </g>
                        <text class="dim-txt" x="220" y="20" text-anchor="middle">13.40 m</text>
                        <text class="dim-txt" x="432" y="142" text-anchor="middle" transform="rotate(90 432 142)">6.10 m doubles</text>
                        <text class="note" x="193" y="259" text-anchor="middle">1.98 m</text>
                        <text class="note" x="232" y="251">net 1.55 m</text>
                    </svg>
                    <figcaption><span><b>Badminton court</b> · doubles lines shown</span><span>BWF size, to scale</span></figcaption>
                @elseif($plan === 'football')
                    {{-- Five-a-side: sizes vary by ground; drawn at 36 × 20 m. --}}
                    <svg viewBox="0 0 452 262" role="img" aria-hidden="true">
                        <rect class="surface" x="22" y="40" width="396" height="198" rx="3"/>
                        <rect class="mow" x="22" y="40" width="36" height="198"/><rect class="mow" x="94" y="40" width="36" height="198"/><rect class="mow" x="166" y="40" width="36" height="198"/><rect class="mow" x="238" y="40" width="36" height="198"/><rect class="mow" x="310" y="40" width="36" height="198"/><rect class="mow" x="382" y="40" width="36" height="198"/>
                        <g class="line">
                            <rect x="40" y="52" width="360" height="174" rx="1"/>
                            <line x1="220" y1="52" x2="220" y2="226"/>
                            <circle cx="220" cy="139" r="27"/>
                            <path d="M40 94a48 48 0 0 1 0 90"/>
                            <path d="M400 94a48 48 0 0 0 0 90"/>
                            <rect x="31" y="121" width="9" height="36"/>
                            <rect x="400" y="121" width="9" height="36"/>
                        </g>
                        <circle cx="220" cy="139" r="2.5" fill="#fff"/>
                        <g class="dim">
                            <path d="M40 22v10M400 22v10M40 27h360"/>
                            <path d="M424 52h8M424 226h8M428 52v174"/>
                        </g>
                        <text class="dim-txt" x="220" y="18" text-anchor="middle">25–40 m</text>
                        <text class="dim-txt" x="443" y="139" text-anchor="middle" transform="rotate(90 443 139)">16–25 m</text>
                        <text class="note" x="220" y="256" text-anchor="middle">Rebound boards or nets on all sides</text>
                    </svg>
                    <figcaption><span><b>Five-a-side pitch</b> · size varies by ground</span><span>Check before booking</span></figcaption>
                @else
                    {{-- Box cricket: a netted cage, usually 90–110 ft long, with a short pitch. --}}
                    <svg viewBox="0 0 440 262" role="img" aria-hidden="true">
                        <rect class="surface" x="40" y="48" width="360" height="176" rx="3"/>
                        <rect class="mow" x="40" y="48" width="40" height="176"/><rect class="mow" x="120" y="48" width="40" height="176"/><rect class="mow" x="200" y="48" width="40" height="176"/><rect class="mow" x="280" y="48" width="40" height="176"/><rect class="mow" x="360" y="48" width="40" height="176"/>
                        <rect class="run" x="140" y="121" width="160" height="30" rx="1"/>
                        <g class="line">
                            <line x1="154" y1="121" x2="154" y2="151"/>
                            <line x1="286" y1="121" x2="286" y2="151"/>
                            <line x1="146" y1="126" x2="162" y2="126"/>
                            <line x1="146" y1="146" x2="162" y2="146"/>
                            <line x1="278" y1="126" x2="294" y2="126"/>
                            <line x1="278" y1="146" x2="294" y2="146"/>
                        </g>
                        <rect class="stump" x="146" y="132.5" width="3" height="7" rx="1"/>
                        <rect class="stump" x="291" y="132.5" width="3" height="7" rx="1"/>
                        <rect class="net" x="40" y="48" width="360" height="176" rx="3"/>
                        <g class="dim">
                            <path d="M40 24v10M400 24v10M40 29h360"/>
                            <path d="M418 48h10M418 224h10M423 48v176"/>
                        </g>
                        <text class="dim-txt" x="220" y="20" text-anchor="middle">90–110 ft</text>
                        <text class="dim-txt" x="433" y="136" text-anchor="middle" transform="rotate(90 433 136)">50–70 ft</text>
                        <text class="note" x="220" y="113" text-anchor="middle" fill="#fff" style="fill:#fff;opacity:.85">pitch</text>
                        <text class="note" x="40" y="246">- - -  netted on all sides and overhead</text>
                    </svg>
                    <figcaption><span><b>Box cricket arena</b> · 6 to 8 a side</span><span>Typical size; grounds vary</span></figcaption>
                @endif
            </div>
        </figure>
    </header>

    {{-- ── 2. Grounds ───────────────────────────────────────────────────── --}}
    <div class="kv-sec">
        @if($venueCount > 0)
            <div class="kv-sec__head">
                <h2>{{ $sportLabel ? $sportLabel.' grounds' : 'Grounds' }} in {{ $cityName }}</h2>
            </div>
            <div class="kv-grounds">
                @foreach($venues as $v)
                    <a class="kv-ground" href="{{ \App\Support\SeoUrl::venue($v) }}">
                        <img class="kv-ground__img" src="{{ $v->image ?: asset('images/haraan-social-card.png') }}" alt="{{ $v->title }}, {{ $cityName }}" loading="{{ $loop->index < 3 ? 'eager' : 'lazy' }}">
                        <span class="kv-ground__body">
                            <h3 class="kv-ground__name">{{ $v->title }}</h3>
                            <span class="kv-ground__where">{{ $v->location ?: $cityName }}</span>
                            @if(!empty($v->sports))
                                <span class="kv-ground__sports">{{ implode(' · ', array_map('ucfirst', array_slice($v->sports, 0, 3))) }}</span>
                            @endif
                            <span class="kv-ground__foot">
                                @if($v->price > 0)
                                    <span class="kv-ground__price">₹{{ number_format($v->price) }} <small>/ hour</small></span>
                                @elseif($v->rating)
                                    <span class="kv-ground__rating">★ {{ number_format((float) $v->rating, 1) }}</span>
                                @else
                                    <span></span>
                                @endif
                                <span class="kv-ground__go">See slots →</span>
                            </span>
                        </span>
                    </a>
                @endforeach
            </div>
        @else
            <div class="kv-none">
                <div>
                    <h2>{{ $sportLabel ? 'No '.strtolower($sportLabel).' ground' : 'No ground' }} in {{ $cityName }} on Haraan yet</h2>
                    <p>When one is listed it will show here with its open slots and hourly price. If you run a turf or court in {{ $cityName }}, you can be the first.</p>
                </div>
                <div class="kv-actions">
                    <a class="kv-btn" href="{{ url('/partner/login') }}">List your ground</a>
                    <a class="kv-link" href="{{ route('site.gamehub') }}">Browse all grounds</a>
                </div>
            </div>

            @if($featuredVenues->isNotEmpty())
                <div class="kv-sec">
                    <div class="kv-sec__head"><h2>Bookable in other cities</h2></div>
                    <div class="kv-grounds">
                        @foreach($featuredVenues as $v)
                            <a class="kv-ground" href="{{ \App\Support\SeoUrl::venue($v) }}">
                                <img class="kv-ground__img" src="{{ $v->image ?: asset('images/haraan-social-card.png') }}" alt="{{ $v->title }}" loading="lazy">
                                <span class="kv-ground__body">
                                    <h3 class="kv-ground__name">{{ $v->title }}</h3>
                                    <span class="kv-ground__where">{{ trim(($v->location ? $v->location.', ' : '').($v->city ?? ''), ', ') }}</span>
                                    <span class="kv-ground__foot">
                                        @if($v->price > 0)
                                            <span class="kv-ground__price">₹{{ number_format($v->price) }} <small>/ hour</small></span>
                                        @else
                                            <span></span>
                                        @endif
                                        <span class="kv-ground__go">See slots →</span>
                                    </span>
                                </span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        @endif
    </div>

    {{-- ── 3. Matches scored in the city ─────────────────────────────────── --}}
    @if($matches->isNotEmpty())
        <div class="kv-sec">
            <div class="kv-sec__head">
                <h2>Matches scored in {{ $cityName }}</h2>
                <a href="{{ route('site.gamehub.actionboard') }}">ActionBoard →</a>
            </div>
            <div class="kv-sheet">
                @foreach($matches as $m)
                    @php
                        $st = strtolower((string) $m->status);
                        $stClass = $st === 'live' ? 'kv-state--live' : (in_array($st, ['finished', 'completed'], true) ? 'kv-state--done' : '');
                        $meta = implode(' · ', array_filter([ucfirst((string) ($m->sport ?: 'cricket')), trim((string) $m->competition)]));
                    @endphp
                    <a class="kv-row" href="{{ route('site.gamehub.actionboard.match', $m->id) }}">
                        <span class="kv-row__date">
                            <b>{{ $m->created_at?->format('j') }}</b>
                            <span>{{ $m->created_at?->format('M') }}</span>
                        </span>
                        <span style="min-width:0">
                            <span class="kv-row__teams" style="display:block">{{ $m->home_full ?: $m->home }}<i>vs</i>{{ $m->away_full ?: $m->away }}</span>
                            <span class="kv-row__meta" style="display:block">{{ $meta }}</span>
                        </span>
                        <span class="kv-state {{ $stClass }}">{{ $st === 'live' ? 'Live' : ucfirst($st ?: 'Scheduled') }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- ── 4. Before you book (Kadapa's own guide) ───────────────────────── --}}
    @if($isKadapa)
        <div class="kv-sec">
            <div class="kv-sec__head"><h2>Before you book a ground in Kadapa</h2></div>
            <div class="kv-guide">
                <section>
                    <h3>Box cricket</h3>
                    <p>Matches are usually 6 to 8 a side in a netted cage, played with a heavy tennis ball. House rules differ — ask whether a hit into the roof net is out or a dot ball, and how many overs fit your slot.</p>
                    <p>Wear flat turf shoes or rubber studs. Metal spikes aren't allowed on synthetic turf.</p>
                </section>
                <section>
                    <h3>Badminton</h3>
                    <p>Check the floor before you book: a synthetic mat plays very differently from bare wood or cement. Most indoor courts insist on non-marking shoes.</p>
                    <p>Ask whether shuttles and racquets are included or charged by the hour.</p>
                </section>
                <section>
                    <h3>When to play</h3>
                    <ul>
                        <li><b>6–8:30 am</b><span>Cooler. Coaching batches often hold courts.</span></li>
                        <li><b>6–10 pm</b><span>Busiest. Floodlit slots go first.</span></li>
                        <li><b>Fri &amp; Sat night</b><span>Book a day or two ahead for teams.</span></li>
                    </ul>
                </section>
            </div>
        </div>
    @endif

    {{-- ── 5. For owners ───────────────────────────────────────────────── --}}
    <div class="kv-owner">
        <div>
            <h2>Run a turf or court in {{ $cityName }}?</h2>
            <p>List it on Haraan to take bookings online, keep walk-ins and online slots in one calendar, and send customers their booking on WhatsApp.</p>
        </div>
        <div class="kv-actions"><a class="kv-btn" href="{{ url('/partner/login') }}">List your ground</a></div>
    </div>

    {{-- ── 6. FAQ ──────────────────────────────────────────────────────── --}}
    <div class="kv-sec kv-faq">
        <div class="kv-sec__head"><h2>Questions</h2></div>
        @foreach($faqs as $f)
            <details @if($loop->first) open @endif>
                <summary>
                    <span>{{ $f['question'] }}</span>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                </summary>
                <p>{{ $f['answer'] }}</p>
            </details>
        @endforeach
    </div>
</div>
<script>
    (function () {
        var row = document.querySelector('.kv-chips');
        var on = row && row.querySelector('[aria-current="page"]');
        if (on && on.offsetLeft + on.offsetWidth > row.clientWidth) {
            row.scrollLeft = on.offsetLeft - 16;
        }
    })();
</script>
@endsection
