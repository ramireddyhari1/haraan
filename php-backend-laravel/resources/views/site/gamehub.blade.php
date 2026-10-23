@extends('site.layout')
@section('footer_icon_secondary', '#2563EB')
@section('content')
@include('site.partials.match-helpers')

{{-- ================================================================= --}}
{{-- MOBILE APP-STYLE GAMEHUB (mirrors the Android app; ≤720px)         --}}
{{--                                                                    --}}
{{-- A port of MainScreen.kt PulseTabScreen, in its order: green hero --}}
{{-- → ActionBoard (straddling the seam) → Top Player → sport chips →   --}}
{{-- Popular Venues reel → More venues. Values track the app's tokens    --}}
{{-- (PulseDeep #1E3A8A, PulseGreen #2563EB, 20px card radius);      --}}
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

    // Sport → inline glyph for the chips. Each racquet sport has its own drawing
    // (App\Support\SportGlyph); one shared racket read as a magnifying glass.
    $hubGlyphKey = function (string $sport): string {
        $s = strtolower($sport);
        return match (true) {
            str_contains($s, 'cricket') => 'cricket',
            str_contains($s, 'football'), str_contains($s, 'soccer') => 'football',
            str_contains($s, 'pickle') => 'pickleball',
            str_contains($s, 'tennis') => 'tennis',
            str_contains($s, 'badminton') => 'badminton',
            str_contains($s, 'volley') => 'volleyball',
            default => 'basketball',
        };
    };
    $hubIcons = [
        'all'        => '<rect x="3" y="3" width="7" height="7" rx="1.5"></rect><rect x="14" y="3" width="7" height="7" rx="1.5"></rect><rect x="3" y="14" width="7" height="7" rx="1.5"></rect><rect x="14" y="14" width="7" height="7" rx="1.5"></rect>',
        'cricket'    => '<path d="M15.5 3.5a2.1 2.1 0 0 1 3 3L9 16l-3 1 1-3z"></path><circle cx="6.5" cy="17.5" r="3"></circle>',
        'football'   => '<circle cx="12" cy="12" r="9"></circle><path d="M12 7.5 8.5 10l1.3 4h4.4l1.3-4z"></path><path d="M12 3v4.5M4 9.5 8.5 10M20 9.5 15.5 10M7 20l2.8-6M17 20l-2.8-6"></path>',
        'badminton'  => \App\Support\SportGlyph::BADMINTON,
        'tennis'     => \App\Support\SportGlyph::TENNIS,
        'pickleball' => \App\Support\SportGlyph::PICKLEBALL,
        'volleyball' => '<circle cx="12" cy="12" r="9"></circle><path d="M12 3a15 15 0 0 0 0 18M3.5 8.5a15 15 0 0 1 17 7M3.5 15.5a15 15 0 0 0 17-7"></path>',
        'basketball' => '<circle cx="12" cy="12" r="9"></circle><path d="M12 3v18M3 12h18M5.6 5.6a12 12 0 0 0 12.8 12.8M18.4 5.6A12 12 0 0 1 5.6 18.4"></path>',
    ];
@endphp

{{-- Finish pass: same layout, crafted surface. Scoped here so the shared
     stylesheets stay as they are. Physical cues: inset tracks, contact shadows,
     a 1px top highlight on raised surfaces, and press states that sink. --}}
<style>
    @media (max-width: 720px) {
        /* Band: a light panel, not a coloured slab. White, with one fine blue
           edge that follows its rounded bottom corners, and a soft drop so it sits
           above the grey page. The blue lives in the edge and the active ink. */
        .mhub .mhub__hero {
            background: #FFFFFF;
            border: 1.5px solid #2563EB;
            border-top: 0;
            border-radius: 0 0 28px 28px;
            padding-bottom: 18px;
            box-shadow: 0 10px 24px -14px rgba(37, 99, 235, .45), 0 2px 4px -2px rgba(15, 23, 42, .08);
        }
        .mhub .mhub__hero::before { display: none; }

        /* Greeting on white: the light variant's own ink; the three utility icons
           become outlined circles on a whisper of blue. */
        .mhub .mhub__hero .app-greet__icon {
            background: #F5F8FE; border: 1px solid #DCE6F8; color: #1E3A8A;
        }
        .mhub .mhub__hero .app-greet__icon:active { background: #EAF1FE; }
        .mhub .mhub__hero .app-greet__avatar { box-shadow: 0 0 0 2px #fff, 0 0 0 3.5px #2563EB; }

        /* Search: a recessed well on the white, edge turns blue on focus. */
        .mhub .mhub__search {
            background: #F4F7FC; border: 1px solid #E1E8F3;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .06) inset;
        }
        .mhub .mhub__search:focus-within { background: #fff; border-color: #2563EB; box-shadow: 0 0 0 3px rgba(37, 99, 235, .14); }
        .mhub .mhub__search input::placeholder { color: #8A97AB; }

        /* Switch: pale recessed track, white thumb with a crisp contact shadow. */
        .mhub .mhub__switch {
            background: #EEF2F8;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .08) inset;
        }
        .mhub .mhub__switch-thumb {
            box-shadow: 0 0 0 1px #DCE6F8, 0 1px 1px rgba(15, 23, 42, .08), 0 3px 8px -3px rgba(37, 99, 235, .35);
        }
        .mhub .mhub__switch-tab { color: #64748B; }
        .mhub .mhub__switch-tab.is-on { color: #1E3A8A; }

        /* The ActionBoard card sat 16px up into the old blue band; on a light
           panel that overlap just hides its title, so it sits below instead. */
        .mhub .mhub__ab { margin-top: 14px; }

        /* Chips: light, with a hairline and a contact shadow; selected = blue tint
           with a blue edge (a filter, not a second primary button). */
        /* Chips: each icon sits in its own round well; the picked chip's well turns
           solid blue (a filter state — tint + edge on the chip, never a solid pill).
           The row fades at the right edge so a cut chip reads as "scroll for more". */
        .mhub .mhub__chips {
            gap: 8px; padding-top: 2px; padding-bottom: 6px;
            -webkit-mask-image: linear-gradient(90deg, #000 calc(100% - 36px), transparent);
            mask-image: linear-gradient(90deg, #000 calc(100% - 36px), transparent);
        }
        .mhub .mhub__chip {
            height: 40px; gap: 8px; padding: 0 15px 0 4px; background: #fff; border: 1px solid #E2E8F0; color: #334155;
            font-size: 14px; font-weight: 700; letter-spacing: -.005em;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .06), 0 4px 10px -8px rgba(15, 23, 42, .35);
            transition: transform .09s ease, box-shadow .12s ease, border-color .15s, color .15s, background-color .15s;
            -webkit-tap-highlight-color: transparent;
        }
        .mhub .mhub__chip-well {
            display: inline-flex; align-items: center; justify-content: center; flex: none;
            width: 30px; height: 30px; border-radius: 50%; background: #F1F5F9; color: #475569;
            box-shadow: 0 1px 1px rgba(15, 23, 42, .06) inset;
            transition: background-color .15s, color .15s, transform .2s cubic-bezier(.2, .9, .3, 1.3);
        }
        .mhub .mhub__chip-well svg { color: inherit; }
        .mhub .mhub__chip:active { transform: translateY(1px) scale(.97); box-shadow: 0 1px 1px rgba(15, 23, 42, .08); }
        .mhub .mhub__chip.is-on {
            background: #EEF4FF; border-color: #2563EB; color: #1E3A8A;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .10), 0 6px 14px -10px rgba(37, 99, 235, .7);
        }
        .mhub .mhub__chip.is-on .mhub__chip-well {
            background: #2563EB; color: #fff; box-shadow: 0 1px 0 rgba(255, 255, 255, .25) inset, 0 2px 6px -2px rgba(37, 99, 235, .7);
        }

        .mhub .mhub__head { padding-top: 18px; }
        .mhub .mhub__head h3 { font-size: 18px; font-weight: 800; letter-spacing: -.02em; }
        .mhub .mhub__head p { margin-top: 3px; font-size: 12.5px; color: #64748B; }
        .mhub .mhub__head p .is-live { display: inline-flex; align-items: center; gap: 5px; color: #15803D; font-weight: 700; }
        .mhub .mhub__head p .is-live::before {
            content: ""; width: 6px; height: 6px; border-radius: 50%; background: #16A34A; box-shadow: 0 0 0 3px rgba(22, 163, 74, .16);
        }

        /* Venue reel: the photo speaks; no label pill or scrim painted on it. */
        .mhub .mhub__arena-scrim, .mhub .mhub__arena-cat { display: none; }
        .mhub .mhub__arena-rate {
            left: auto; right: 8px; top: 8px; bottom: auto; padding: 3px 8px; border-radius: 999px;
            background: rgba(255, 255, 255, .95); color: #0F172A; box-shadow: 0 1px 3px rgba(15, 23, 42, .2);
        }
        .mhub .mhub__arena:active, .mhub .mhub__vcard:active { transform: scale(.985); }

        /* Reel card v2: facts, not glyphs. Words for sports, today's hours on the
           photo, a price on its own baseline and a real Book key. Physical: the photo
           sits in an inset frame, the card has a contact shadow plus a 1px top light,
           and a press sinks it 2px while the key's lip closes. */
        /* Venue cards carry the same fine blue edge as the hero panel above them. */
        .mhub .mhub__arena--v2,
        .mhub .mhub__vcard {
            border: 1.5px solid #2563EB;
            box-shadow: 0 10px 24px -14px rgba(37, 99, 235, .45), 0 2px 4px -2px rgba(15, 23, 42, .08);
        }
        .mhub .mhub__arena--v2 {
            width: 244px; padding: 6px; border-radius: 22px; background: #fff;
            transition: transform .12s cubic-bezier(.2, .7, .3, 1), box-shadow .12s;
            -webkit-tap-highlight-color: transparent;
        }
        .mhub .mhub__arena--v2:active,
        .mhub .mhub__vcard:active {
            transform: translateY(2px) scale(.99);
            box-shadow: 0 3px 8px -6px rgba(37, 99, 235, .5);
        }
        .mhub .mhub__arena--v2 .mhub__arena-img {
            height: 138px; border-radius: 17px; overflow: hidden; background: #E7ECF3;
        }
        .mhub .mhub__arena--v2 .mhub__arena-img > img { transition: transform .5s cubic-bezier(.2, .7, .3, 1); }
        .mhub .mhub__arena--v2:active .mhub__arena-img > img { transform: scale(1.03); }
        /* A soft band of shade at the top so the white chips never float on a bright sky. */
        .mhub .mhub__arena--v2 .mhub__arena-img::after {
            content: ""; position: absolute; inset: 0; pointer-events: none; border-radius: inherit;
            background: linear-gradient(180deg, rgba(15, 23, 42, .22) 0%, transparent 34%);
            box-shadow: 0 0 0 1px rgba(15, 23, 42, .06) inset;
        }
        .mhub__arena-open {
            position: absolute; z-index: 1; top: 8px; left: 8px; display: inline-flex; align-items: center; gap: 6px;
            height: 24px; padding: 0 9px 0 8px; border-radius: 999px; background: rgba(255, 255, 255, .94);
            font-size: 11px; font-weight: 700; color: #475569; letter-spacing: -.005em;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .18);
        }
        .mhub__arena-open i { width: 6px; height: 6px; border-radius: 50%; background: #94A3B8; }
        .mhub__arena-open.is-open { color: #0F172A; }
        .mhub__arena-open.is-open i { background: #16A34A; box-shadow: 0 0 0 3px rgba(22, 163, 74, .18); }
        .mhub .mhub__arena--v2 .mhub__arena-rate { z-index: 1; }
        .mhub .mhub__arena--v2 .mhub__arena-body { padding: 11px 6px 4px; }
        .mhub .mhub__arena--v2 .mhub__arena-title { font-size: 15px; font-weight: 800; letter-spacing: -.015em; color: #0F172A; }
        .mhub__arena-meta { display: flex; align-items: center; gap: 4px; margin-top: 3px; min-width: 0; font-size: 12px; color: #64748B; }
        .mhub__arena-meta svg { flex: none; color: #94A3B8; }
        .mhub__arena-meta span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .mhub__arena-tags { display: flex; gap: 5px; margin-top: 9px; overflow: hidden; }
        .mhub__arena-tags em {
            flex: none; height: 22px; padding: 0 8px; display: inline-flex; align-items: center; border-radius: 7px;
            background: #F1F5F9; color: #334155; font-style: normal; font-size: 11px; font-weight: 600;
        }
        .mhub__arena-tags em.is-more { background: transparent; color: #64748B; padding: 0 2px; }
        .mhub .mhub__arena--v2 .mhub__arena-foot {
            justify-content: space-between; margin-top: 11px; padding-top: 10px; border-top: 1px dashed #E2E8F0;
        }
        .mhub__arena-price { display: inline-flex; align-items: baseline; gap: 3px; color: #0F172A; }
        .mhub__arena-price small { font-size: 11px; font-weight: 500; color: #64748B; }
        .mhub__arena-price b { font-size: 17px; font-weight: 800; letter-spacing: -.02em; font-variant-numeric: tabular-nums; }
        .mhub__arena-price i { font-size: 11px; font-style: normal; color: #64748B; }
        /* The key: blue like every action, with a lip under it that a press closes. */
        /* Same language as the sheet's Checkout key: the word, then a white round
           arrow seated in the blue. A press closes the lip and the arrow steps on. */
        .mhub__arena-go {
            display: inline-flex; align-items: center; gap: 8px; height: 36px; padding: 0 4px 0 14px; border-radius: 999px;
            background: #2563EB; color: #fff; font-size: 13.5px; font-weight: 800; letter-spacing: -.005em;
            box-shadow: 0 1px 0 rgba(255, 255, 255, .25) inset, 0 2px 0 #1D4ED8, 0 6px 12px -6px rgba(37, 99, 235, .75);
            transition: transform .08s, box-shadow .08s;
        }
        .mhub__arena-go i {
            display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 50%;
            background: #fff; color: #2563EB; box-shadow: 0 1px 2px rgba(15, 23, 42, .18);
            transition: transform .22s cubic-bezier(.2, .9, .3, 1.2);
        }
        .mhub .mhub__arena--v2:active .mhub__arena-go { transform: translateY(2px); box-shadow: 0 0 0 #1D4ED8, 0 1px 3px -1px rgba(37, 99, 235, .5); }
        .mhub .mhub__arena--v2:active .mhub__arena-go i { transform: translateX(3px); }
        @media (prefers-reduced-motion: reduce) {
            .mhub .mhub__arena--v2, .mhub .mhub__arena--v2 .mhub__arena-img > img { transition: none; }
        }

        /* Nothing listed near the viewer: a small map of their area — the pin
           carries the real place name, the dashed ring is the search radius, and
           the dashed plot inside it is where a ground would be. No button. */
        .mhub__none {
            margin: 12px 16px 4px; padding: 12px 12px 18px; background: #fff; border-radius: 20px; text-align: center;
            box-shadow: 0 1px 0 #fff inset, 0 6px 14px rgba(15, 23, 42, .06), 0 1px 2px rgba(15, 23, 42, .12);
        }
        .mhub__none-map { display: block; width: 100%; height: auto; border-radius: 14px; }
        .mhub__none strong { display: block; text-wrap: balance; margin-top: 14px; font-size: 15.5px; font-weight: 800; letter-spacing: -.01em; color: #0F172A; }
        .mhub__none p { text-wrap: balance; margin: 4px auto 0; max-width: 270px; font-size: 13px; line-height: 1.45; color: #64748B; }
        .mhub__none-ring { transform-origin: 160px 74px; }
        @media (prefers-reduced-motion: no-preference) {
            .mhub__none-ring { animation: mhub-sweep 2.8s cubic-bezier(.2, .6, .3, 1) infinite; }
            @keyframes mhub-sweep { 0% { transform: scale(.35); opacity: .55; } 80%, 100% { transform: scale(1); opacity: 0; } }
        }
    }
</style>

{{-- Desktop / tablet Pulse (.dhub). Hidden on phones, where .mhub renders. --}}
<style>
    .dhub { --ink: #0F172A; --muted: #64748B; --faint: #94A3B8; --line: #E6EAF0; --blue: #2563EB; --cta: #1E3A8A; --cta-down: #172554; color: var(--ink); }
    @media (max-width: 720px) { .dhub { display: none; } }

    /* Head: words and facts, no banner. */
    .dhub__head { padding: 10px 0 18px; }
    .dhub__crumbs { display: flex; gap: 8px; font-size: 13px; color: var(--muted); }
    .dhub__crumbs a { color: var(--muted); text-decoration: none; }
    .dhub__crumbs a:hover { color: var(--ink); }
    .dhub__crumbs span { color: #CBD5E1; }
    .dhub__head h1 { margin: 10px 0 0; font-size: clamp(28px, 2.6vw, 38px); line-height: 1.12; font-weight: 800; letter-spacing: -.03em; color: var(--ink); text-wrap: balance; }
    .dhub__facts { display: flex; flex-wrap: wrap; align-items: center; margin: 10px 0 0; font-size: 14.5px; color: var(--muted); }
    .dhub__facts span + span::before { content: ""; display: inline-block; width: 3px; height: 3px; margin: 0 10px 3px; border-radius: 50%; background: #CBD5E1; }
    .dhub__facts b { color: var(--ink); font-weight: 800; font-variant-numeric: tabular-nums; }
    .dhub__facts .is-live { color: #15803D; font-weight: 700; }
    .dhub__facts .is-live::after { content: ""; display: inline-block; width: 7px; height: 7px; margin-left: 7px; border-radius: 50%; background: #16A34A; box-shadow: 0 0 0 3px rgba(22, 163, 74, .18); vertical-align: 1px; }

    /* Sport bar: sticks under the site topbar; a hairline appears once it's stuck. */
    .dhub__bar {
        position: sticky; top: var(--dhub-top, 78px); z-index: 20;
        display: flex; align-items: center; gap: 16px;
        margin: 0 -2px; padding: 12px 2px;
        /* Solid white edge to edge: the shadow paints the gutters, clip-path keeps it
           from bleeding above or below the bar. */
        background: #fff; box-shadow: 0 0 0 100vmax #fff; clip-path: inset(0 -100vmax);
        border-bottom: 1px solid var(--line);
    }
    .dhub__chips { display: flex; gap: 8px; min-width: 0; flex: 1; overflow-x: auto; scrollbar-width: none; }
    .dhub__chips::-webkit-scrollbar { display: none; }
    .dhub__chip {
        flex: none; display: inline-flex; align-items: center; gap: 8px; height: 40px; padding: 0 15px;
        border-radius: 999px; border: 1px solid var(--line); background: #fff;
        font-size: 14px; font-weight: 700; color: #334155; text-decoration: none;
        transition: border-color .15s, background .15s, color .15s;
    }
    .dhub__chip svg { color: var(--faint); }
    .dhub__chip em { font-style: normal; font-size: 12px; font-weight: 700; color: var(--faint); font-variant-numeric: tabular-nums; }
    .dhub__chip:hover { border-color: #C7D2E3; color: var(--ink); }
    .dhub__chip.is-on { background: #EFF4FF; border-color: var(--blue); color: var(--cta); }
    .dhub__chip.is-on svg, .dhub__chip.is-on em { color: var(--blue); }
    .dhub__sort { flex: none; display: inline-flex; align-items: center; gap: 8px; font-size: 13px; color: var(--muted); }
    .dhub__sort select {
        height: 40px; padding: 0 34px 0 14px; border-radius: 12px; border: 1px solid var(--line);
        font: inherit; font-size: 14px; font-weight: 700; color: var(--ink); cursor: pointer;
        background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2364748B' stroke-width='2.5' stroke-linecap='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E") no-repeat right 12px center;
        appearance: none;
    }
    .dhub__sort select:focus-visible { outline: none; border-color: var(--blue); box-shadow: 0 0 0 3px rgba(37, 99, 235, .15); }

    /* Body: grid + rail. */
    .dhub__body { display: grid; grid-template-columns: minmax(0, 1fr) 340px; gap: 32px; align-items: start; padding: 24px 0 32px; }
    .dhub__grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 28px 22px; }

    /* Venue card: clean photo, then facts in order of decision. */
    .dvc { display: flex; flex-direction: column; min-width: 0; color: inherit; text-decoration: none; }
    .dvc__img { position: relative; display: block; aspect-ratio: 4 / 3; border-radius: 16px; overflow: hidden; background: #EEF2F7; }
    .dvc__img img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform .5s cubic-bezier(.2, .7, .3, 1); }
    .dvc:hover .dvc__img img { transform: scale(1.035); }
    .dvc__img::after { content: ""; position: absolute; inset: 0; border-radius: inherit; box-shadow: inset 0 0 0 1px rgba(15, 23, 42, .06); pointer-events: none; }
    .dvc__rate {
        position: absolute; top: 10px; right: 10px; display: inline-flex; align-items: center; gap: 4px; height: 26px; padding: 0 9px;
        border-radius: 8px; background: rgba(255, 255, 255, .95); font-size: 12.5px; font-weight: 800; color: var(--ink);
        box-shadow: 0 1px 3px rgba(15, 23, 42, .12);
    }
    .dvc__rate i { font-style: normal; color: #EAB308; }
    .dvc__rate small { font-size: 11px; font-weight: 600; color: var(--muted); }
    .dvc__body { display: flex; flex-direction: column; flex: 1; padding: 13px 2px 0; }
    .dvc__title { font-size: 16.5px; font-weight: 800; letter-spacing: -.015em; line-height: 1.3; color: var(--ink); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .dvc:hover .dvc__title { color: var(--cta); }
    .dvc__meta { display: flex; align-items: center; gap: 5px; margin-top: 5px; min-width: 0; font-size: 13px; color: var(--muted); }
    .dvc__meta svg { flex: none; color: var(--faint); }
    .dvc__meta span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .dvc__sports { margin-top: 3px; font-size: 13px; color: #475569; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .dvc__sports i { font-style: normal; color: var(--faint); }

    /* Today's hours on a 24h rail; the tick is the venue-clock now. */
    .dvc__hours { display: flex; align-items: center; gap: 10px; margin-top: 11px; }
    /* Notches at 6 AM, noon and 6 PM so the rail reads as a day. */
    .dvc__rail {
        position: relative; flex: 1; height: 6px; border-radius: 3px; background: #EEF1F5;
    }
    .dvc__rail::after {
        content: ""; position: absolute; inset: 0; border-radius: inherit; pointer-events: none;
        background: linear-gradient(90deg, transparent calc(25% - .5px), #fff calc(25% - .5px), #fff calc(25% + .5px), transparent calc(25% + .5px),
            transparent calc(50% - .5px), #fff calc(50% - .5px), #fff calc(50% + .5px), transparent calc(50% + .5px),
            transparent calc(75% - .5px), #fff calc(75% - .5px), #fff calc(75% + .5px), transparent calc(75% + .5px));
    }
    .dvc__rail b { z-index: 1; }
    .dvc__rail i { position: absolute; top: 0; bottom: 0; border-radius: 3px; background: #BFD3FB; }
    .dvc__rail b { position: absolute; top: -4px; width: 2px; height: 14px; margin-left: -1px; border-radius: 1px; background: var(--ink); }
    .dvc__hours em { flex: none; font-style: normal; font-size: 12px; font-weight: 700; color: var(--muted); }
    .dvc__hours em.is-open { color: #15803D; }

    .dvc__foot { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-top: auto; padding-top: 14px; }
    .dvc__price { display: inline-flex; align-items: baseline; gap: 3px; }
    .dvc__price b { font-size: 18px; font-weight: 800; letter-spacing: -.02em; font-variant-numeric: tabular-nums; }
    .dvc__price small { font-size: 12px; color: var(--muted); }
    .dvc__go {
        display: inline-flex; align-items: center; gap: 6px; height: 36px; padding: 0 14px; border-radius: 11px;
        background: var(--cta); color: #fff; font-size: 13.5px; font-weight: 800;
        box-shadow: 0 1px 0 rgba(255, 255, 255, .16) inset, 0 2px 0 var(--cta-down);
        transition: background .15s, transform .1s, box-shadow .1s;
    }
    .dvc__go svg { transition: transform .2s; }
    .dvc:hover .dvc__go { background: var(--cta-down); }
    .dvc:hover .dvc__go svg { transform: translateX(2px); }
    .dvc:active .dvc__go { transform: translateY(2px); box-shadow: none; }
    .dvc:focus-visible { outline: none; }
    .dvc:focus-visible .dvc__img { box-shadow: 0 0 0 3px #fff, 0 0 0 5px var(--blue); }

    /* No venues. */
    .dhub__none { display: flex; flex-direction: column; align-items: center; text-align: center; padding: 48px 24px 56px; border: 1px dashed #D5DEEA; border-radius: 20px; background: #FBFCFE; }
    .dhub__none strong { margin-top: 16px; font-size: 18px; font-weight: 800; letter-spacing: -.015em; }
    .dhub__none p { margin: 6px 0 0; font-size: 14px; color: var(--muted); }
    .dhub__none-links { display: flex; gap: 18px; margin-top: 16px; }
    .dhub__none-links a { font-size: 14px; font-weight: 700; color: var(--blue); text-decoration: none; }
    .dhub__none-links a:hover { text-decoration: underline; }

    /* Rail. */
    .dhub__rail { position: sticky; top: calc(var(--dhub-top, 78px) + 84px); display: flex; flex-direction: column; gap: 16px; }
    .dab, .dtp { border: 1px solid var(--line); border-radius: 18px; background: #fff; box-shadow: 0 1px 2px rgba(15, 23, 42, .04); }
    .dab__head, .dtp__head { display: flex; align-items: center; gap: 9px; padding: 15px 16px 0; }
    .dab__head img { width: 22px; height: 22px; object-fit: contain; }
    .dab__head strong, .dtp__head strong { font-size: 15px; font-weight: 800; letter-spacing: -.01em; }
    .dab__live { margin-left: auto; display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 800; color: #DC2626; text-transform: uppercase; letter-spacing: .04em; }
    .dab__live i { width: 7px; height: 7px; border-radius: 50%; background: #DC2626; animation: dab-pulse 1.6s ease-out infinite; }
    @keyframes dab-pulse { 0% { box-shadow: 0 0 0 0 rgba(220, 38, 38, .45); } 100% { box-shadow: 0 0 0 7px rgba(220, 38, 38, 0); } }
    @media (prefers-reduced-motion: reduce) { .dab__live i { animation: none; } }
    .dab__list { display: flex; flex-direction: column; padding: 8px 8px 0; }
    .dab__match { display: flex; flex-direction: column; gap: 6px; padding: 10px; border-radius: 12px; color: inherit; text-decoration: none; }
    .dab__match + .dab__match { border-top: 1px solid #F1F4F8; border-radius: 0 0 12px 12px; }
    .dab__match:hover { background: #F7F9FC; }
    .dab__comp { font-size: 11.5px; font-weight: 700; color: var(--faint); text-transform: uppercase; letter-spacing: .05em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .dab__row { display: flex; align-items: center; gap: 9px; font-size: 14px; color: var(--muted); }
    .dab__row.is-batting { color: var(--ink); font-weight: 700; }
    .dab__logo { flex: none; display: grid; place-items: center; width: 24px; height: 24px; border-radius: 7px; background: #EEF2F7; font-size: 10px; font-weight: 800; color: #475569; overflow: hidden; }
    .dab__logo img { width: 100%; height: 100%; object-fit: cover; }
    .dab__name { flex: 1; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .dab__score { flex: none; font-weight: 800; font-variant-numeric: tabular-nums; color: inherit; }
    .dab__score small { margin-left: 4px; font-size: 11.5px; font-weight: 600; color: var(--faint); }
    .dab__empty { margin: 0; padding: 10px 16px 2px; font-size: 13.5px; line-height: 1.5; color: var(--muted); }
    .dab__empty strong { display: block; margin-bottom: 2px; font-size: 14px; color: var(--ink); }
    .dab__foot { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 14px 16px 16px; }
    .dab__foot a { font-size: 13.5px; font-weight: 700; color: var(--blue); text-decoration: none; }
    .dab__foot a:hover { text-decoration: underline; }
    .dab__foot .dab__start {
        display: inline-flex; align-items: center; height: 36px; padding: 0 14px; border-radius: 11px;
        background: var(--cta); color: #fff; box-shadow: 0 1px 0 rgba(255, 255, 255, .16) inset, 0 2px 0 var(--cta-down);
    }
    .dab__foot .dab__start:hover { background: var(--cta-down); text-decoration: none; }
    .dab__foot .dab__start:active { transform: translateY(2px); box-shadow: none; }

    .dtp__head a { margin-left: auto; font-size: 13px; font-weight: 700; color: var(--blue); text-decoration: none; }
    .dtp__head a:hover { text-decoration: underline; }
    .dtp__row { display: flex; align-items: center; gap: 12px; margin: 8px 8px 8px; padding: 8px; border-radius: 12px; color: inherit; text-decoration: none; }
    .dtp__row:hover { background: #F7F9FC; }
    .dtp__avatar { position: relative; flex: none; display: grid; place-items: center; width: 46px; height: 46px; border-radius: 50%; background: #EFF4FF; color: var(--cta); font-weight: 800; font-size: 17px; }
    .dtp__avatar img { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; }
    .dtp__avatar i { position: absolute; right: -3px; bottom: -3px; display: grid; place-items: center; width: 20px; height: 20px; border-radius: 50%; background: #EAB308; border: 2px solid #fff; font-style: normal; font-size: 10.5px; font-weight: 800; color: #fff; }
    .dtp__id { display: flex; flex-direction: column; min-width: 0; }
    .dtp__id b { font-size: 15px; font-weight: 800; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .dtp__id small { margin-top: 2px; font-size: 12.5px; color: var(--muted); }
    .dtp__empty { margin: 0; padding: 8px 16px 16px; font-size: 13.5px; line-height: 1.5; color: var(--muted); }

    .down { display: flex; flex-direction: column; gap: 4px; padding: 16px; border-radius: 18px; background: #F5F7FB; color: inherit; text-decoration: none; }
    .down strong { font-size: 15px; font-weight: 800; letter-spacing: -.01em; }
    .down span { font-size: 13.5px; line-height: 1.45; color: var(--muted); }
    .down em { display: inline-flex; align-items: center; gap: 6px; margin-top: 8px; font-style: normal; font-size: 13.5px; font-weight: 700; color: var(--blue); }
    .down:hover em svg { transform: translateX(2px); }
    .down em svg { transition: transform .2s; }

    /* Tablet: the rail drops under the grid as a row of panels. */
    @media (max-width: 1100px) {
        .dhub__bar { position: static; }
        .dhub__body { grid-template-columns: minmax(0, 1fr); }
        .dhub__rail { position: static; display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); align-items: start; order: -1; }
        .dhub__grid { grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); }
    }
</style>

<div class="mhub">
    {{-- 1. The app's green hero. This IS the page header, not a banner under one: on the
         Pulse tab the app renders no outer header at all (MainScreen.kt skips it when
         `isPulseTab`) and stacks greeting → search → switch on the green band. So the
         site's white topbar is hidden here (see the CSS) and its three controls live on
         the green, in the app's order — otherwise the page carries two search bars and
         two tab controls, one white and one green. --}}
    <div class="mhub__hero">
        {{-- The "H" monogram (the app's haraan_copy drawable), not the wordmark: the
             band wants identity-as-texture, and a wordmark stretched to 210px is a
             squashed logo. --}}

        @include('site.partials.app-greet', ['onDark' => false])

        <form class="mhub__search" action="/search" method="GET" role="search">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><line x1="20" y1="20" x2="16.5" y2="16.5"></line></svg>
            <input type="text" name="q" placeholder="Search grounds, matches, players..." autocomplete="off">
        </form>

        {{-- The app's PulseSegmentedSwitch: a translucent track with a white active
             pill. Real links here, since the web's two lanes are two pages.
             The pill is one thumb that slides on tap (site.js), same as the Events header. --}}
        <div class="mhub__switch is-right" role="tablist" aria-label="Events or Pulse">
            <span class="mhub__switch-thumb" aria-hidden="true"></span>
            <a class="mhub__switch-tab" href="/events" role="tab" aria-selected="false">Events</a>
            <a class="mhub__switch-tab is-on" href="/gamehub" role="tab" aria-selected="true">Pulse</a>
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
            {{-- The app's honest empty state, sport-aware — never a scripted match. --}}
            <p class="mhub__ab-empty">
                {{ $selectedSport === 'All'
                    ? 'No live matches right now · tap to open the board'
                    : 'No live ' . $selectedSport . ' matches right now · tap to open the board' }}
            </p>
        @endif
    </a>

    {{-- 4. Sport chips — a global filter over the venues below AND the ActionBoard. --}}
    <div class="mhub__chips">
        @foreach($sportChips as $chip)
            @php $key = $chip === 'All' ? 'all' : $hubGlyphKey($chip); @endphp
            <a class="mhub__chip {{ $chip === $selectedSport ? 'is-on' : '' }}"
               href="{{ $chip === 'All' ? '/gamehub' : '/gamehub?sport=' . urlencode($chip) }}">
                <span class="mhub__chip-well"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $hubIcons[$key] !!}</svg></span>
                {{ $chip }}
            </a>
        @endforeach
    </div>

    {{-- 5. Popular Venues — the app's top-5-by-rating reel. --}}
    @php $hubTotal = $popularVenues->count() + $moreVenues->count(); @endphp
    <div class="mhub__head">
        <div>
            <h3>Popular venues</h3>
            {{-- The app's contextual subtitle, minus its two GPS branches: without a fix
                 it says "N venues • top rated", which is exactly how these are ranked.
                 Pluralised, which the app's string isn't — that's its bug, not its design. --}}
            {{-- "top rated" only once something here has ratings — with none it was a
                 claim nothing backed. --}}
            @php $hubRated = $popularVenues->contains(fn ($v) => $v->reviews > 0); @endphp
            {{-- The fact that matters next: how many are open right now, from each
                 venue's own hours on the venue clock. --}}
            @php
                $hubAll = $popularVenues->concat($moreVenues);
                $hubOpenNow = $hubAll->where('open_now', true)->count();
                $hubHasHours = $hubAll->contains(fn ($v) => !empty($v->open_state));
                // Nothing open yet: say when the first one opens today ("Opens 5 AM").
                $hubFirstOpen = $hubAll->pluck('open_state')
                    ->filter(fn ($s) => is_string($s) && str_starts_with($s, 'Opens '))
                    ->map(fn ($s) => substr($s, 6))
                    ->sortBy(fn ($t) => strtotime($t) ?: PHP_INT_MAX)
                    ->first();
                $hubNowLine = $hubOpenNow ? $hubOpenNow . ' open now' : ($hubFirstOpen ? 'first opens ' . $hubFirstOpen : 'closed for today');
            @endphp
            @if($hubTotal)<p>{{ $hubTotal . ' ' . \Illuminate\Support\Str::plural('venue', $hubTotal) }}{{ $selectedCity ? ' near ' . $selectedCity : '' }}@if($hubHasHours) · <span class="{{ $hubOpenNow ? 'is-live' : '' }}">{{ $hubNowLine }}</span>@elseif($hubRated) · top rated @endif</p>@endif
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
                @php
                    $arenaSports = collect($venue->sports ?? [])->filter()->values();
                    $arenaMeta = collect([
                        $venue->location ?: $venue->city,
                        ($venue->courts ?? 0) > 1 ? $venue->courts . ' courts' : null,
                        $venue->distance ?: null,
                    ])->filter()->implode(' · ');
                    $arenaFrom = ($venue->courts ?? 0) > 1;
                @endphp
                <a class="mhub__arena mhub__arena--v2" href="{{ \App\Support\SeoUrl::venue($venue) }}">
                    <span class="mhub__arena-img">
                        <img src="{{ $venue->image }}"{!! \App\Support\VenuePhoto::imgAttrs($venue->image, '232px') !!} alt="{{ $venue->title }}" loading="lazy" decoding="async">
                        {{-- Today's hours from the venue's own week, on the venue clock. --}}
                        @if(!empty($venue->open_state))
                            <span class="mhub__arena-open {{ $venue->open_now ? 'is-open' : '' }}"><i></i>{{ $venue->open_state }}</span>
                        @endif
                        @if($venue->reviews > 0)
                            <span class="mhub__arena-rate"><i>★</i>{{ $venue->rating }}</span>
                        @endif
                    </span>
                    <span class="mhub__arena-body">
                        <strong class="mhub__arena-title">{{ $venue->title }}</strong>
                        <span class="mhub__arena-meta">
                            <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                            <span>{{ $arenaMeta }}</span>
                        </span>
                        {{-- Sports as words: the racket glyph next to the price read as a magnifier. --}}
                        @if($arenaSports->count())
                            <span class="mhub__arena-tags">
                                @foreach($arenaSports->take(2) as $sport)<em>{{ $sport }}</em>@endforeach
                                @if($arenaSports->count() > 2)<em class="is-more">+{{ $arenaSports->count() - 2 }}</em>@endif
                            </span>
                        @endif
                        <span class="mhub__arena-foot">
                            <span class="mhub__arena-price">
                                @if($arenaFrom)<small>from</small>@endif
                                <b>₹{{ number_format($venue->from_price ?: $venue->price) }}</b><i>/hr</i>
                            </span>
                            <span class="mhub__arena-go">Book<i aria-hidden="true"><svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h13M13 6l6 6-6 6"></path></svg></i></span>
                        </span>
                    </span>
                </a>
            @endforeach
        </div>
    @else
        @php
            $noneWhere = $selectedCity ?: null;
            $noneLabel = $noneWhere ?: 'You';
            $noneChipW = min(150, (int) round(mb_strlen($noneLabel) * 6.7 + 22));
        @endphp
        <div class="mhub__none">
            <svg class="mhub__none-map" viewBox="0 0 320 150" role="img" aria-label="No venues found around {{ $noneWhere ?: 'your area' }}">
                {{-- the area: land, blocks, two roads --}}
                <rect width="320" height="150" rx="14" fill="#EEF3FA"/>
                <g fill="#E1E9F5">
                    <rect x="14" y="14" width="46" height="30" rx="5"/><rect x="68" y="14" width="34" height="30" rx="5"/>
                    <rect x="14" y="104" width="58" height="32" rx="5"/><rect x="242" y="12" width="64" height="26" rx="5"/>
                    <rect x="256" y="100" width="50" height="36" rx="5"/><rect x="226" y="108" width="22" height="28" rx="5"/>
                </g>
                <path d="M-6 66 C 70 58, 110 92, 170 86 S 270 52, 330 60" fill="none" stroke="#fff" stroke-width="13" stroke-linecap="round"/>
                <path d="M118 -6 C 126 40, 140 80, 132 156" fill="none" stroke="#fff" stroke-width="11" stroke-linecap="round"/>
                <path d="M-6 66 C 70 58, 110 92, 170 86 S 270 52, 330 60" fill="none" stroke="#DCE5F2" stroke-width="1.2" stroke-dasharray="6 6"/>
                {{-- the search radius, and its sweep --}}
                <circle cx="160" cy="74" r="58" fill="rgba(37,99,235,.06)" stroke="#2563EB" stroke-opacity=".45" stroke-width="1.4" stroke-dasharray="4 5"/>
                <circle class="mhub__none-ring" cx="160" cy="74" r="58" fill="none" stroke="#2563EB" stroke-width="1.6"/>
                {{-- where a ground would be: an empty plot --}}
                <g transform="translate(184 36)">
                    <rect width="46" height="30" rx="4" fill="#F7F9FC" stroke="#94A3B8" stroke-width="1.3" stroke-dasharray="3.5 3"/>
                    <line x1="23" y1="4" x2="23" y2="26" stroke="#CBD5E1" stroke-width="1.2" stroke-dasharray="2.5 2.5"/>
                    <circle cx="23" cy="15" r="5" fill="none" stroke="#CBD5E1" stroke-width="1.2" stroke-dasharray="2.5 2.5"/>
                </g>
                {{-- the viewer's place --}}
                <ellipse cx="160" cy="80" rx="9" ry="3" fill="rgba(15,23,42,.18)"/>
                <path d="M160 80c-8-9.5-12-15.6-12-21a12 12 0 0 1 24 0c0 5.4-4 11.5-12 21z" fill="#2563EB"/>
                <circle cx="160" cy="58.5" r="4.6" fill="#fff"/>
                <rect x="{{ 160 - $noneChipW / 2 }}" y="90" width="{{ $noneChipW }}" height="22" rx="11" fill="#fff" stroke="#DCE5F2"/>
                <text x="160" y="105" text-anchor="middle" font-family="Plus Jakarta Sans, system-ui, sans-serif" font-size="11.5" font-weight="700" fill="#0F172A">{{ $noneLabel }}</text>
            </svg>
            <strong>
                {{ $selectedSport === 'All' ? 'No venues' : 'No ' . strtolower($selectedSport) . ' venues' }}{{ $noneWhere ? ' near ' . $noneWhere : ' near you' }} yet
            </strong>
            <p>Grounds will show up here as soon as one opens nearby.</p>
        </div>
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
                <a class="mhub__vcard" href="{{ \App\Support\SeoUrl::venue($venue) }}">
                    <span class="mhub__vcard-img">
                        <img src="{{ $venue->image }}"{!! \App\Support\VenuePhoto::imgAttrs($venue->image, '(min-width: 768px) 420px, 100vw') !!} alt="{{ $venue->title }}" loading="lazy" decoding="async">
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
                                    {{ $venue->location }}@if(!empty($venue->distance)) • {{ $venue->distance }}@endif
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

{{-- ================================================================= --}}
{{-- DESKTOP / TABLET PULSE (>720px)                                     --}}
{{--                                                                    --}}
{{-- One catalogue page, Playo-shaped: a plain head that states real     --}}
{{-- facts, a sticky sport bar, the venue grid, and a sticky rail that   --}}
{{-- carries the live side of Pulse (ActionBoard, top player). Every     --}}
{{-- number on it is read from the venues or matches below — no counts   --}}
{{-- for sports with no venues, no hero art, no invented claims.         --}}
{{-- ================================================================= --}}
@php
    // Chips: every sport some venue here sells (a venue counts for each of its
    // sports), plus whatever is selected so the bar never loses the active chip.
    $dSportCounts = collect($venues ?? [])
        ->flatMap(fn ($v) => collect($v->sports ?: [$v->category])->filter()->unique())
        ->countBy()
        ->sortDesc();
    if ($selectedSport !== 'All' && !$dSportCounts->has($selectedSport)) {
        $dSportCounts->put($selectedSport, 0);
    }
    $dTotalAll = collect($venues ?? [])->count();

    $dFrom = $hubAll->pluck('from_price')->filter(fn ($p) => $p > 0)->min();
    $dHasDistance = $hubAll->contains(fn ($v) => !empty($v->distance_km));
    $dWhere = $selectedCity ?: null;
    $dSportWord = $selectedSport === 'All' ? null : strtolower($selectedSport);

    // Minutes → "6 AM" for the hours strip's tooltip.
    $dClock = static fn (int $m): string => $m >= 1440 ? 'midnight' : str_replace(':00 ', ' ', date('g:i A', mktime(0, $m)));
@endphp

<section class="dhub" aria-label="Pulse venues">
    <header class="dhub__head">
        <nav class="dhub__crumbs" aria-label="Breadcrumb"><a href="/">Haraan</a><span aria-hidden="true">/</span>Pulse</nav>
        <h1>
            {{ $dSportWord ? ucfirst($dSportWord) . ' venues' : 'Turfs & courts' }}
            {{ $dWhere ? 'in ' . $dWhere : 'near you' }}
        </h1>
        @if($hubTotal)
            <p class="dhub__facts">
                <span>{{ $hubTotal }} {{ \Illuminate\Support\Str::plural('venue', $hubTotal) }}</span>
                @if($hubHasHours)
                    <span class="{{ $hubOpenNow ? 'is-live' : '' }}">{{ ucfirst($hubNowLine) }}</span>
                @endif
                @if($dFrom)
                    <span>From <b>₹{{ number_format($dFrom) }}</b>/hr</span>
                @endif
            </p>
        @endif
    </header>

    {{-- Sport bar: ?sport= links (the split and the live board are recomputed
         server-side, same as the phone chips), plus a client-side sort. --}}
    <div class="dhub__bar">
        <nav class="dhub__chips" aria-label="Filter by sport">
            <a class="dhub__chip {{ $selectedSport === 'All' ? 'is-on' : '' }}" href="/gamehub">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $hubIcons['all'] !!}</svg>
                All<em>{{ $dTotalAll }}</em>
            </a>
            @foreach($dSportCounts as $sport => $count)
                <a class="dhub__chip {{ strcasecmp($sport, $selectedSport) === 0 ? 'is-on' : '' }}" href="/gamehub?sport={{ urlencode($sport) }}">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $hubIcons[$hubGlyphKey((string) $sport)] !!}</svg>
                    {{ $sport }}@if($count > 0)<em>{{ $count }}</em>@endif
                </a>
            @endforeach
        </nav>
        @if($hubTotal > 1)
            <label class="dhub__sort">
                <span>Sort</span>
                <select id="dhubSort">
                    <option value="order">Recommended</option>
                    @if($hubAll->contains(fn ($v) => $v->reviews > 0))<option value="rating">Top rated</option>@endif
                    <option value="price">Price: low to high</option>
                    @if($dHasDistance)<option value="dist">Nearest</option>@endif
                    @if($hubHasHours)<option value="open">Open now first</option>@endif
                </select>
            </label>
        @endif
    </div>

    <div class="dhub__body">
        <div class="dhub__main">
            @if($hubTotal)
                <div class="dhub__grid" id="dhubGrid">
                    @foreach($hubAll as $i => $venue)
                        @php
                            $dSports = collect($venue->sports ?: [$venue->category])->filter()->values();
                            $dMeta = collect([
                                $venue->location ?: $venue->city,
                                ($venue->courts ?? 0) > 1 ? $venue->courts . ' courts' : null,
                                $venue->distance ?: null,
                            ])->filter()->implode(' · ');
                            $dPrice = $venue->from_price ?: $venue->price;
                            $dWindows = collect($venue->today_windows ?? [])
                                ->map(fn ($w) => [max(0, (int) $w[0]), min(1440, (int) $w[1])])
                                ->filter(fn ($w) => $w[1] > $w[0]);
                        @endphp
                        <a class="dvc" href="{{ \App\Support\SeoUrl::venue($venue) }}"
                           data-order="{{ $i }}" data-rating="{{ $venue->reviews > 0 ? (float) $venue->rating : 0 }}"
                           data-price="{{ $dPrice ?: 999999 }}" data-open="{{ $venue->open_now ? 1 : 0 }}"
                           data-dist="{{ $venue->distance_km ?? 99999 }}">
                            <span class="dvc__img">
                                <img src="{{ $venue->image }}"{!! \App\Support\VenuePhoto::imgAttrs($venue->image, '(min-width: 1280px) 300px, (min-width: 1025px) 28vw, 45vw') !!} alt="{{ $venue->title }}" loading="{{ $i < 6 ? 'eager' : 'lazy' }}" decoding="async">
                                @if($venue->reviews > 0)
                                    <span class="dvc__rate"><i aria-hidden="true">★</i>{{ $venue->rating }}<small>({{ $venue->reviews }})</small></span>
                                @endif
                            </span>
                            <span class="dvc__body">
                                <strong class="dvc__title">{{ $venue->title }}</strong>
                                @if($dMeta)
                                    <span class="dvc__meta">
                                        <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z"></path><circle cx="12" cy="9.5" r="2.5"></circle></svg>
                                        <span>{{ $dMeta }}</span>
                                    </span>
                                @endif
                                @if($dSports->count())
                                    <span class="dvc__sports">{{ $dSports->take(3)->implode(' · ') }}@if($dSports->count() > 3) <i>+{{ $dSports->count() - 3 }}</i>@endif</span>
                                @endif

                                {{-- Today's hours drawn on a 24-hour rail, with a tick at the
                                     venue-clock now. Only for venues that set their hours. --}}
                                @if(!empty($venue->open_state))
                                    <span class="dvc__hours">
                                        <span class="dvc__rail" role="img" aria-label="Today: {{ $dWindows->map(fn ($w) => $dClock($w[0]) . ' to ' . $dClock($w[1]))->implode(', ') ?: 'closed' }}">
                                            @foreach($dWindows as $w)
                                                <i style="left: {{ round($w[0] / 14.4, 2) }}%; width: {{ round(($w[1] - $w[0]) / 14.4, 2) }}%"></i>
                                            @endforeach
                                            @if($venue->now_minute !== null)
                                                <b style="left: {{ round($venue->now_minute / 14.4, 2) }}%"></b>
                                            @endif
                                        </span>
                                        <em class="{{ $venue->open_now ? 'is-open' : '' }}">{{ $venue->open_state }}</em>
                                    </span>
                                @endif

                                <span class="dvc__foot">
                                    <span class="dvc__price">
                                        @if($dPrice)
                                            @if(($venue->courts ?? 0) > 1)<small>from</small>@endif
                                            <b>₹{{ number_format($dPrice) }}</b><small>/hr</small>
                                        @else
                                            <small>See prices</small>
                                        @endif
                                    </span>
                                    <span class="dvc__go">Book<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h13M13 6l6 6-6 6"></path></svg></span>
                                </span>
                            </span>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="dhub__none">
                    {{-- An unclaimed plot: a court outline drawn in dashes, waiting for a ground. --}}
                    <svg viewBox="0 0 220 120" width="220" height="120" aria-hidden="true">
                        <rect x="20" y="16" width="180" height="88" rx="8" fill="#F5F8FD" stroke="#94A3B8" stroke-width="1.6" stroke-dasharray="6 5"/>
                        <line x1="110" y1="22" x2="110" y2="98" stroke="#CBD5E1" stroke-width="1.4" stroke-dasharray="4 4"/>
                        <circle cx="110" cy="60" r="16" fill="none" stroke="#CBD5E1" stroke-width="1.4" stroke-dasharray="4 4"/>
                        <rect x="20" y="40" width="22" height="40" fill="none" stroke="#CBD5E1" stroke-width="1.4" stroke-dasharray="4 4"/>
                        <rect x="178" y="40" width="22" height="40" fill="none" stroke="#CBD5E1" stroke-width="1.4" stroke-dasharray="4 4"/>
                        <path d="M150 30v-22" stroke="#1E3A8A" stroke-width="2" stroke-linecap="round"/>
                        <path d="M150 8h22l-6 6 6 6h-22z" fill="#2563EB"/>
                    </svg>
                    <strong>{{ $dSportWord ? 'No ' . $dSportWord . ' venues' : 'No venues' }} {{ $dWhere ? 'in ' . $dWhere : 'near you' }} yet</strong>
                    <p>Grounds show up here as soon as an owner lists one.</p>
                    <div class="dhub__none-links">
                        @if($selectedSport !== 'All')<a href="/gamehub">See every sport</a>@endif
                        <a href="/become-a-partner">List your ground</a>
                    </div>
                </div>
            @endif
        </div>

        <aside class="dhub__rail">
            {{-- ActionBoard: the live side of Pulse. Real matches only. --}}
            <section class="dab" aria-label="ActionBoard">
                <header class="dab__head">
                    <img src="{{ asset('images/haraan-mark.png') }}" alt="" aria-hidden="true" width="22" height="22">
                    <strong>ActionBoard</strong>
                    @if(count($hubLive))<span class="dab__live"><i></i>{{ count($hubLive) }} live</span>@endif
                </header>
                @if(count($hubLive))
                    <div class="dab__list">
                        @foreach(array_slice($hubLive, 0, 3) as $lm)
                            @php
                                $dRows = collect([$lm['home'], $lm['away']])->sortByDesc(fn ($t) => (bool) $t['batting'])->values();
                            @endphp
                            <a class="dab__match" href="/gamehub/actionboard/match/{{ $lm['id'] }}">
                                @if($lm['competition'])<span class="dab__comp">{{ $lm['competition'] }}</span>@endif
                                @foreach($dRows as $team)
                                    @php $dIcon = hrn_team_icon($team['logo'] ?? '', $team['emblem'] ?? ''); @endphp
                                    <span class="dab__row {{ $team['batting'] ? 'is-batting' : '' }}">
                                        <span class="dab__logo {{ $dIcon !== '' ? 'has-img' : '' }}">@if($dIcon !== '')<img src="{{ $dIcon }}" alt="" loading="lazy">@else{{ \Illuminate\Support\Str::of($team['abbr'])->substr(0, 2)->upper() }}@endif</span>
                                        <span class="dab__name">{{ $team['name'] }}</span>
                                        <span class="dab__score">{{ $team['score'] }}@if($team['overs'])<small>({{ $team['overs'] }})</small>@endif</span>
                                    </span>
                                @endforeach
                            </a>
                        @endforeach
                    </div>
                @else
                    <p class="dab__empty">
                        <strong>{{ $selectedSport === 'All' || $selectedSport === 'Cricket' ? 'No live matches right now' : 'No live ' . strtolower($selectedSport) . ' matches right now' }}</strong>
                        {{ $selectedSport === 'All' || $selectedSport === 'Cricket' ? 'Scores land here ball by ball' : 'Scores land here' }} as soon as someone starts scoring.
                    </p>
                @endif
                <footer class="dab__foot">
                    <a href="/gamehub/actionboard">Open the board</a>
                    <a class="dab__start" href="/gamehub/actionboard/create">Start a match</a>
                </footer>
            </section>

            {{-- Top player: this month's #1 on the ranked board for the chosen city. --}}
            @if($dWhere)
                <section class="dtp" aria-label="Top player">
                    <header class="dtp__head">
                        <strong>Top player in {{ $dWhere }}</strong>
                        <a href="/gamehub/leaderboard">Leaderboard</a>
                    </header>
                    @if($topPlayer)
                        <a class="dtp__row" href="/player/{{ $topPlayer['player_id'] }}">
                            <span class="dtp__avatar">
                                @if(!empty($topPlayer['avatar']))<img src="{{ $topPlayer['avatar'] }}" alt="" loading="lazy">@else{{ \Illuminate\Support\Str::of($topPlayer['name'])->substr(0, 1)->upper() }}@endif
                                <i aria-hidden="true">1</i>
                            </span>
                            <span class="dtp__id">
                                <b>{{ $topPlayer['name'] }}</b>
                                <small>{{ number_format($topPlayer['xp']) }} XP this month @if($topPlayer['matches'])· {{ $topPlayer['matches'] }} {{ \Illuminate\Support\Str::plural('match', $topPlayer['matches']) }}@endif</small>
                            </span>
                        </a>
                    @else
                        <p class="dtp__empty">Nobody in {{ $dWhere }} has played a ranked match this month. The first one takes the spot.</p>
                    @endif
                </section>
            @endif

            <a class="down" href="/become-a-partner">
                <strong>Run a turf or court?</strong>
                <span>Take bookings online and keep walk-ins in the same calendar.</span>
                <em>List your ground<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h13M13 6l6 6-6 6"></path></svg></em>
            </a>
        </aside>
    </div>
</section>

<script>
/* Desktop sort: reorders the cards already on the page; filters stay server-side. */
document.addEventListener('DOMContentLoaded', () => {
    // The sport bar and rail stick under the site topbar, whose height changes with
    // the viewport (it wraps on narrower screens), so read it rather than guess.
    const dhub = document.querySelector('.dhub');
    const topbar = document.querySelector('.topbar');
    const setTop = () => {
        const cs = topbar && getComputedStyle(topbar);
        const stuck = cs && cs.display !== 'none' && (cs.position === 'sticky' || cs.position === 'fixed');
        dhub?.style.setProperty('--dhub-top', (stuck ? topbar.offsetHeight : 0) + 'px');
    };
    setTop();
    addEventListener('resize', setTop, { passive: true });

    const sort = document.getElementById('dhubSort');
    const grid = document.getElementById('dhubGrid');
    if (!sort || !grid) return;
    const cards = Array.from(grid.children);
    const num = (el, k) => parseFloat(el.dataset[k]) || 0;
    const by = {
        order:  (a, b) => num(a, 'order') - num(b, 'order'),
        rating: (a, b) => num(b, 'rating') - num(a, 'rating') || num(a, 'order') - num(b, 'order'),
        price:  (a, b) => num(a, 'price') - num(b, 'price') || num(a, 'order') - num(b, 'order'),
        dist:   (a, b) => num(a, 'dist') - num(b, 'dist'),
        open:   (a, b) => num(b, 'open') - num(a, 'open') || num(a, 'order') - num(b, 'order'),
    };
    sort.addEventListener('change', () => {
        cards.sort(by[sort.value] || by.order).forEach(c => grid.appendChild(c));
    });
});
</script>
@endsection
