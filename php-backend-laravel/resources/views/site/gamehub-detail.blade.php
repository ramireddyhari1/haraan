@extends('site.layout')
@section('footer_icon_secondary', '#2563EB')

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

    // Live embedded map — only when we have a real pin AND a Google key. Otherwise
    // the "Show in Map" link alone stands (no fabricated location).
    $mvHasCoords = $venue->latitude !== null && $venue->longitude !== null;
    $mvMapsKey = (string) config('services.google_maps.key');
    $mvEmbed = ($mvMapsKey !== '' && $mvHasCoords)
        ? 'https://www.google.com/maps/embed/v1/place?' . http_build_query(['key' => $mvMapsKey, 'q' => $mvQuery, 'zoom' => '16'])
        : null;

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
        // Each racquet sport gets its own object — the old bare racket (oval + stick)
        // read as a magnifying glass.
        str_contains($mvCat, 'pickle') => \App\Support\SportGlyph::PICKLEBALL,
        str_contains($mvCat, 'tennis') => \App\Support\SportGlyph::TENNIS,
        default => \App\Support\SportGlyph::BADMINTON,
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

{{-- Slot board finish (2026-10-16): a booking sheet, not a dashboard. Keys have a
     darker bottom edge and sink when pressed; the price is stated once; booked
     keys are recessed. Scoped to the slot container so nothing else moves. --}}
<style>
    #slots-grid-container { font-variant-numeric: tabular-nums; }
    #slots-grid-container .sb-status {
        display: flex; align-items: baseline; justify-content: space-between; gap: 12px;
        padding: 2px 2px 12px; margin-bottom: 4px; border-bottom: 1px solid #E8EDF4;
        font-size: 13px; color: #64748B;
    }
    #slots-grid-container .sb-status b { color: #0F172A; font-weight: 800; }
    #slots-grid-container .sb-status__rate { color: #334155; font-weight: 600; white-space: nowrap; }
    #slots-grid-container .sb-status.is-offline .sb-status__count { color: #B45309; }
    #slots-grid-container .sb-status.is-checking { border-bottom-color: transparent; }

    #slots-grid-container .sb-group { margin-top: 16px; }
    #slots-grid-container .sb-group__head {
        display: flex; align-items: baseline; gap: 8px; margin: 0 2px 10px;
    }
    #slots-grid-container .sb-group__head h4 { margin: 0; font-size: 14px; font-weight: 800; color: #0F172A; letter-spacing: -.005em; }
    #slots-grid-container .sb-group__hours { font-size: 12.5px; color: #94A3B8; font-weight: 500; }
    #slots-grid-container .sb-group__count { margin-left: auto; font-size: 12.5px; font-weight: 700; color: #475569; }
    #slots-grid-container .sb-group__count.is-none { color: #94A3B8; }

    /* Slot cards (2026-10-21): a time card, not a keyboard key. Time on the left at
       full weight, the length or the reason underneath, a status dot in the corner.
       Physical: a hairline + contact shadow at rest, a press sinks 1px and the
       shadow closes; a pick fills blue and a tick draws itself. */
    #slots-grid-container .sb-keys { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; }
    #slots-grid-container .sb-key {
        position: relative; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 1px;
        min-height: 52px; padding: 8px 6px; margin: 0;
        background: #FFFFFF; border: 1px solid #DFE5EE; border-radius: 12px;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .05);
        color: #0F172A; cursor: pointer; text-align: center; overflow: hidden;
        transform: none; transition: transform .09s ease, box-shadow .12s ease, border-color .15s ease, background-color .18s ease;
        -webkit-tap-highlight-color: transparent; user-select: none;
    }
    #slots-grid-container .sb-key::before, #slots-grid-container .sb-key::after { content: none; }
    #slots-grid-container .sb-key:hover:not(.is-booked):not(.is-selected) { border-color: #C9D4E3; }
    #slots-grid-container .sb-key:active:not(.is-booked) {
        transform: translateY(1px) scale(.985);
        box-shadow: 0 0 0 rgba(15, 23, 42, 0), 0 1px 1px rgba(15, 23, 42, .08);
    }
    #slots-grid-container .sb-key__time { display: flex; align-items: baseline; font-size: 17px; font-weight: 800; letter-spacing: -.02em; line-height: 1.1; }
    #slots-grid-container .sb-key__time small { margin-left: 3px; font-size: 10.5px; font-weight: 700; color: #94A3B8; letter-spacing: .03em; }
    #slots-grid-container .sb-key__sub,
    #slots-grid-container .sb-key__len { font-size: 11.5px; font-weight: 600; color: #94A3B8; line-height: 1.2; }
    #slots-grid-container .sb-key__soon { font-size: 11px; font-weight: 700; color: #2563EB; line-height: 1.2; }

    /* Peak: costs more, says how much, in a warm ink. */
    #slots-grid-container .sb-key.is-peak .sb-key__sub { color: #B45309; font-weight: 700; }

    /* Picked: blue card, tick in the corner drawn on the pick. */
    /* !important only here: site-theme-overrides.css forces a pale selected tile with
       !important on desktop, which turned white text invisible. */
    #slots-grid-container .sb-key.is-selected {
        background: #2563EB !important; border-color: #2563EB !important; color: #fff;
        box-shadow: 0 1px 0 rgba(255, 255, 255, .25) inset, 0 8px 16px -8px rgba(37, 99, 235, .75), 0 0 0 3px rgba(37, 99, 235, .14) !important;
    }
    #slots-grid-container .sb-key.is-selected .sb-key__time { color: #fff !important; }
    #slots-grid-container .sb-key.is-selected .sb-key__time small,
    #slots-grid-container .sb-key.is-selected .sb-key__sub,
    #slots-grid-container .sb-key.is-selected .sb-key__len,
    #slots-grid-container .sb-key.is-selected .sb-key__soon { color: rgba(255, 255, 255, .8); }
    #slots-grid-container .sb-key__tick { display: none; position: absolute; top: 7px; right: 7px; width: 16px; height: 16px; border-radius: 50%; background: #fff; color: #2563EB; }
    #slots-grid-container .sb-key.is-selected .sb-key__tick { display: block; }
    #slots-grid-container .sb-key__tick path { stroke-dasharray: 12; stroke-dashoffset: 0; }
    #slots-grid-container .sb-key.is-pop .sb-key__tick { animation: sb-tick-in .32s cubic-bezier(.2, .9, .3, 1.3) both; }
    #slots-grid-container .sb-key.is-pop .sb-key__tick path { animation: sb-tick-draw .3s .08s ease-out both; }
    #slots-grid-container .sb-key.is-pop { animation: sb-pick .28s cubic-bezier(.2, .9, .3, 1.2); }
    @keyframes sb-tick-in { from { transform: scale(0); } to { transform: scale(1); } }
    @keyframes sb-tick-draw { from { stroke-dashoffset: 12; } to { stroke-dashoffset: 0; } }
    @keyframes sb-pick { 0% { transform: scale(.96); } 60% { transform: scale(1.02); } 100% { transform: scale(1); } }

    /* Taken: a dashed outline on the sheet, time struck through - nothing to press. */
    #slots-grid-container .sb-key.is-booked {
        background: #F6F8FB; border: 1px dashed #D5DDE8; box-shadow: none; color: #A3AFC0; cursor: not-allowed;
    }
    #slots-grid-container .sb-key.is-booked .sb-key__clock { text-decoration: line-through; text-decoration-thickness: 1.5px; text-decoration-color: #B8C3D2; }
    #slots-grid-container .sb-key.is-booked .sb-key__sub { color: #94A3B8; }

    /* A slot that just changed under the viewer: one brief cue, then calm. */
    #slots-grid-container .sb-key.is-just-taken { animation: sb-gone .9s ease; }
    #slots-grid-container .sb-key.is-just-freed { animation: sb-back .9s ease; }
    @keyframes sb-gone { 0%, 40% { background: #FEE2E2; border-color: #FCA5A5; } }
    @keyframes sb-back { 0%, 40% { background: #DCFCE7; border-color: #86EFAC; } }
    @media (prefers-reduced-motion: reduce) {
        #slots-grid-container .sb-key { transition: none; }
        #slots-grid-container .sb-key.is-just-taken, #slots-grid-container .sb-key.is-just-freed { animation: none; }
    }

    #slots-grid-container .sb-key--skel { cursor: default; border-color: #E8EDF4; border-bottom-color: #E1E7F0; background: linear-gradient(90deg, #F4F7FB 25%, #EAF0F7 50%, #F4F7FB 75%) 0 0 / 200% 100%; animation: sb-shimmer 1.2s linear infinite; }
    @keyframes sb-shimmer { to { background-position: -200% 0; } }

    /* ── Phone dock: ticket stub + Checkout key (2026-10-21) ───────────────
       Replaces the pill / subtotal / "Continue · ₹" stack in the sheet foot. The
       stub is a real pass shape: a date tear-off, a perforation with two punched
       notches, and the picked times. No money - checkout shows the bill. */
    .co-dock { display: none; }
    .mven__modal-foot .sticky-booking-card > .mb-24,
    .mven__modal-foot .price-calculator-panel,
    .mven__modal-foot #book-now-button,
    .mven__modal-foot .booking-notice-text { display: none !important; }
    .mven__modal-foot .co-dock { display: block; }

    .co-stub {
        position: relative; display: flex; align-items: stretch; margin-bottom: 12px;
        background: #F7F9FD; border: 1px solid #E1E8F4; border-radius: 16px;
        box-shadow: 0 1px 0 #fff inset;
        transition: background-color .2s ease, border-color .2s ease;
    }
    .co-dock:not(.is-empty) .co-stub { background: #fff; border-color: #CFDCF3; box-shadow: 0 1px 0 #fff inset, 0 6px 14px -10px rgba(37, 99, 235, .45); }
    /* Punched notches top and bottom of the perforation, cut from the sheet colour. */
    .co-stub::before, .co-stub::after {
        content: ""; position: absolute; left: 63px; width: 14px; height: 14px; border-radius: 50%;
        background: #fff; border: 1px solid #E1E8F4;
    }
    .co-stub::before { top: -8px; clip-path: inset(50% 0 0 0); }
    .co-stub::after { bottom: -8px; clip-path: inset(0 0 50% 0); }
    .co-dock:not(.is-empty) .co-stub::before, .co-dock:not(.is-empty) .co-stub::after { border-color: #CFDCF3; }
    .co-stub__date {
        flex: none; width: 70px; display: flex; flex-direction: column; align-items: center; justify-content: center;
        padding: 10px 0; border-right: 1.5px dashed #D5DEEC; color: #64748B;
        font-size: 10px; font-weight: 800; letter-spacing: .12em; line-height: 1.25;
    }
    .co-stub__date b { font-size: 24px; font-weight: 800; letter-spacing: -.03em; color: #0F172A; line-height: 1.05; font-variant-numeric: tabular-nums; }
    .co-dock:not(.is-empty) .co-stub__date span:first-child { color: #2563EB; }
    .co-stub__body { flex: 1; min-width: 0; display: flex; flex-direction: column; justify-content: center; gap: 3px; padding: 11px 6px 11px 14px; }
    .co-stub__what {
        overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        font-size: 10.5px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; color: #2563EB;
    }
    .co-dock.is-empty .co-stub__what { color: #94A3B8; }
    .co-stub__times { display: flex; flex-direction: column; gap: 2px; min-height: 26px; justify-content: center; }
    .co-range {
        display: flex; align-items: baseline; gap: 4px; white-space: nowrap;
        font-size: 21px; font-weight: 800; letter-spacing: -.03em; line-height: 1.15; color: #0F172A; font-variant-numeric: tabular-nums;
        animation: co-range-in .26s cubic-bezier(.2, .9, .3, 1.15) both;
    }
    .co-range small { font-size: 11.5px; font-weight: 800; letter-spacing: .02em; color: #64748B; }
    .co-range i { margin-left: 4px; font-style: normal; font-size: 11.5px; font-weight: 700; letter-spacing: 0; color: #64748B; }
    .co-dock.is-multi .co-range { font-size: 16px; }
    .co-stub__hint { font-size: 15px; font-weight: 700; color: #94A3B8; letter-spacing: -.01em; }
    .co-stub__facts { font-size: 12px; font-weight: 600; color: #64748B; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .co-stub__facts b { font-weight: 700; color: #0F172A; }
    @keyframes co-range-in { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: none; } }

    /* The court itself, drawn: lines in blue, its name stamped under it. */
    .co-stub__court {
        flex: none; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 2px;
        width: 58px; padding-right: 8px; color: #93B4F5;
    }
    .co-dock:not(.is-empty) .co-stub__court { color: #2563EB; }
    .co-dock.is-empty .co-stub__court { color: #CBD5E1; }
    .co-stub__court svg { display: block; transform: rotate(-4deg); }
    .co-dock:not(.is-empty) .co-stub__court svg { animation: co-court-in .4s cubic-bezier(.2, .9, .3, 1.3) both; }
    .co-stub__court span {
        max-width: 54px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        font-size: 10px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; color: inherit;
    }
    @keyframes co-court-in { from { transform: rotate(-14deg) scale(.85); opacity: .4; } to { transform: rotate(-4deg); opacity: 1; } }
    .co-stub__clear {
        position: absolute; top: -10px; right: 6px; width: 24px; height: 24px; display: none; align-items: center; justify-content: center;
        padding: 0; border: 1px solid #CFDCF3; border-radius: 50%; background: #fff; color: #64748B; cursor: pointer;
        box-shadow: 0 2px 6px -2px rgba(15, 23, 42, .25); -webkit-tap-highlight-color: transparent;
    }
    .co-dock:not(.is-empty) .co-stub__clear { display: inline-flex; }
    .co-stub__clear:active { transform: scale(.88); background: #F1F5F9; }

    /* The key: blue, a pass sitting in a slot on the left, an arrow on the right,
       and a 2px lip underneath that a press closes. */
    .co-go {
        position: relative; display: flex; align-items: center; gap: 12px; width: 100%; height: 56px; padding: 0 8px 0 8px;
        border: 0; border-radius: 16px; overflow: hidden; cursor: pointer;
        background: #2563EB; color: #fff; font: inherit;
        box-shadow: 0 1px 0 rgba(255, 255, 255, .22) inset, 0 3px 0 #1D4ED8, 0 10px 20px -10px rgba(37, 99, 235, .8);
        transition: transform .08s ease, box-shadow .08s ease, background-color .2s ease;
        -webkit-tap-highlight-color: transparent;
    }
    .co-go:active:not(:disabled), .co-go.is-going { transform: translateY(3px); box-shadow: 0 0 0 #1D4ED8, 0 3px 8px -4px rgba(37, 99, 235, .7); }
    .co-go:disabled { cursor: default; background: #E8EDF5; color: #94A3B8; box-shadow: 0 1px 0 #fff inset, 0 2px 0 #D5DDEA; }
    .co-go__pass {
        flex: none; display: flex; align-items: center; justify-content: center; width: 44px; height: 40px; border-radius: 11px;
        background: rgba(255, 255, 255, .16); box-shadow: 0 1px 2px rgba(0, 0, 0, .12) inset;
    }
    .co-go__pass svg { transform: rotate(-8deg); transition: transform .3s cubic-bezier(.2, .8, .3, 1), opacity .25s ease; }
    .co-go:disabled .co-go__pass { background: #DDE4EF; box-shadow: none; }
    .co-go:disabled .co-go__pass svg { opacity: .55; filter: grayscale(1); }
    .co-go__label { flex: 1; text-align: left; font-size: 16.5px; font-weight: 800; letter-spacing: -.01em; }
    .co-go__arrow {
        flex: none; display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 12px;
        background: #fff; color: #2563EB; transition: transform .3s cubic-bezier(.2, .8, .3, 1);
    }
    .co-go:disabled .co-go__arrow { background: #DDE4EF; color: #A3AFC0; }
    .co-dock:not(.is-empty) .co-go__pass svg { animation: co-pass-ready .5s cubic-bezier(.2, .9, .3, 1.3) both; }
    /* Going: the pass slides out to the right through the key, the arrow follows. */
    .co-go.is-going .co-go__pass svg { transform: translateX(260px) rotate(0deg); opacity: 0; }
    .co-go.is-going .co-go__arrow { transform: translateX(6px); }
    @keyframes co-pass-ready { 0% { transform: rotate(-8deg) translateY(10px); opacity: 0; } 100% { transform: rotate(-8deg); opacity: 1; } }
    @media (prefers-reduced-motion: reduce) {
        .co-range, .co-dock:not(.is-empty) .co-go__pass svg, .co-dock:not(.is-empty) .co-stub__court svg { animation: none; }
        .crt { transition: none !important; }
    }

    /* ── RATE VENUE form (real, 2026-10-21) — big tappable stars that name themselves ── */
    .rvf { text-align: center; }
    .rvf__title { margin: 0; font-size: 18px; font-weight: 800; letter-spacing: -.02em; color: #0F172A; }
    .rvf__lede { margin: 4px 0 0; font-size: 13px; color: #64748B; }
    .rvf__note { margin: 0; font-size: 13.5px; color: #475569; }
    .rvf__stars { display: flex; justify-content: center; gap: 6px; margin-top: 18px; }
    .rvf .rvf__star {
        width: 48px; height: 48px; display: inline-flex; align-items: center; justify-content: center; padding: 0;
        border: 0; border-radius: 14px; background: #F1F5F9; color: #CBD5E1; cursor: pointer;
        transition: background-color .15s ease, color .15s ease, transform .1s ease;
        -webkit-tap-highlight-color: transparent;
    }
    .rvf .rvf__star svg { width: 28px; height: 28px; fill: currentColor; }
    .rvf .rvf__star:active { transform: scale(.9); }
    .rvf .rvf__star.is-active { background: #FFF7E0; color: #F59E0B; }
    .rvf .rvf__star.is-pop { animation: rvf-pop .32s cubic-bezier(.2, .9, .3, 1.4); }
    @keyframes rvf-pop { 0% { transform: scale(.8); } 60% { transform: scale(1.12); } 100% { transform: scale(1); } }
    .rvf__word { display: block; height: 18px; margin-top: 8px; font-size: 13px; font-weight: 700; color: #B45309; }
    .rvf .rvf__text { width: 100%; margin-top: 14px; padding: 12px 14px; border: 1px solid #E2E8F0; border-radius: 14px; font: inherit; font-size: 14px; resize: vertical; background: #F8FAFC; }
    .rvf .rvf__text:focus { outline: none; border-color: #2563EB; background: #fff; box-shadow: 0 0 0 3px rgba(37, 99, 235, .14); }
    .rvf__err { margin: 10px 0 0; font-size: 13px; font-weight: 600; color: #DC2626; }
    .rvf .rvf__go {
        width: 100%; height: 50px; margin-top: 14px; border: 0; border-radius: 14px; background: #2563EB; color: #fff;
        font-size: 15.5px; font-weight: 800; cursor: pointer;
        box-shadow: 0 1px 0 rgba(255, 255, 255, .22) inset, 0 3px 0 #1D4ED8, 0 10px 20px -10px rgba(37, 99, 235, .8);
        transition: transform .08s ease, box-shadow .08s ease, background-color .15s ease;
    }
    .rvf .rvf__go:active:not(:disabled) { transform: translateY(3px); box-shadow: 0 0 0 #1D4ED8, 0 3px 8px -4px rgba(37, 99, 235, .7); }
    .rvf .rvf__go:disabled { background: #E8EDF5; color: #94A3B8; box-shadow: none; cursor: default; }
    .rvf__as { margin: 10px 0 0; font-size: 12px; color: #94A3B8; }
    .rvf__as b { color: #475569; }
    .mven__review-played {
        display: inline-flex; align-items: center; height: 18px; margin-left: 4px; padding: 0 6px; border-radius: 5px; vertical-align: 1px;
        background: #ECFDF3; color: #15803D; font-size: 10px; font-weight: 800; letter-spacing: .02em;
    }
    .mven__review { transition: opacity .3s ease, transform .3s ease; }
    .mven__review.is-new { opacity: 0; transform: translateY(-6px); }
    .rvf-toast {
        position: fixed; left: 50%; bottom: calc(96px + env(safe-area-inset-bottom, 0px)); z-index: 80; transform: translate(-50%, 12px);
        display: inline-flex; align-items: center; gap: 8px; padding: 11px 16px; border-radius: 999px;
        background: #0F172A; color: #fff; font-size: 13.5px; font-weight: 700; opacity: 0; pointer-events: none;
        box-shadow: 0 10px 24px -10px rgba(15, 23, 42, .6); transition: opacity .2s ease, transform .25s cubic-bezier(.2, .8, .3, 1);
    }
    .rvf-toast svg { color: #4ADE80; }
    .rvf-toast.is-on { opacity: 1; transform: translate(-50%, 0); }

    /* ── Sheet head: tight, aligned to the content's 16px edge, no rule under it ── */
    .mven__modal-head .mven__modal-id { flex: 1; min-width: 0; }
    .mven__modal-head .mven__modal-title { font-size: 18px; line-height: 1.2; letter-spacing: -.02em; }
    .mven__modal-sub {
        margin: 1px 0 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        font-size: 12.5px; font-weight: 500; color: #64748B;
    }
    @media (max-width: 720px) {
        .mven__modal-head {
            justify-content: space-between !important; align-items: center !important;
            padding: 4px 12px 6px 16px !important; border-bottom: 0 !important;
        }
        .mven__modal-head .mven__modal-x { width: 32px; height: 32px; }
        .mven__modal-head .mven__modal-x svg { width: 16px; height: 16px; }
        .mven__modal-body { padding-top: 10px !important; }
        /* The instruction line said what the screen already shows. */
        .mven__modal-body #booking-widget > .detail-card-panel__subtitle { display: none; }
    }

    /* ── Courts: a card per court with its drawing and its own rate ──────── */
    .crt-head { display: flex; align-items: baseline; justify-content: space-between; gap: 8px; }
    .crt-head__count { font-size: 12px; font-weight: 600; color: #94A3B8; }
    .court-selector-container .court-pill.crt,
    .mven__modal-body .court-selector-container .court-pill.crt {
        position: relative; flex: none; display: flex; align-items: center; gap: 10px;
        height: auto; min-width: 128px; padding: 9px 14px 9px 9px; margin: 0;
        border: 1px solid #E1E7EF; border-radius: 16px; background: #fff; color: #0F172A; text-align: left;
        box-shadow: 0 1px 0 #fff inset, 0 1px 2px rgba(15, 23, 42, .06), 0 6px 12px -10px rgba(15, 23, 42, .35);
        transition: transform .09s ease, box-shadow .12s ease, border-color .15s ease, background-color .15s ease;
        -webkit-tap-highlight-color: transparent;
    }
    .mven__modal-body .court-selector-container .court-pill.crt:hover { background: #fff; color: #0F172A; border-color: #C9D4E3; }
    .court-selector-container .court-pill.crt:active { transform: translateY(1px) scale(.98); box-shadow: 0 1px 1px rgba(15, 23, 42, .08); }
    .crt__art {
        flex: none; display: flex; align-items: center; justify-content: center; width: 36px; height: 46px; border-radius: 10px;
        background: #F1F5FA; color: #94A3B8; transition: background-color .15s ease, color .15s ease;
    }
    .crt__art svg { width: 26px; height: 36px; }
    .crt__txt { display: flex; flex-direction: column; min-width: 0; line-height: 1.2; }
    .crt__txt b { font-size: 14px; font-weight: 800; letter-spacing: -.01em; white-space: nowrap; }
    .crt__txt small { margin-top: 2px; font-size: 12.5px; font-weight: 700; color: #334155; font-variant-numeric: tabular-nums; }
    .crt__txt small i { font-style: normal; font-weight: 500; color: #94A3B8; }
    .crt__txt em { margin-top: 1px; font-style: normal; font-size: 10.5px; font-weight: 700; color: #B45309; white-space: nowrap; }
    .crt__tick { position: absolute; top: -5px; right: -5px; width: 18px; height: 18px; display: none; filter: drop-shadow(0 2px 3px rgba(37, 99, 235, .35)); }
    .court-selector-container .court-pill.crt.is-active,
    .mven__modal-body .court-selector-container .court-pill.crt.is-active {
        background: #F5F8FF; border-color: #2563EB; color: #0F172A;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, .12), 0 8px 16px -10px rgba(37, 99, 235, .55);
    }
    .court-pill.crt.is-active .crt__art { background: #2563EB; color: #fff; }
    .court-pill.crt.is-active .crt__tick { display: block; animation: sb-tick-in .3s cubic-bezier(.2, .9, .3, 1.3) both; }
    /* Room for the tick that sits on the card's corner. */
    .mven__modal-body .court-selector-container { padding-top: 6px !important; padding-bottom: 8px !important; }

    /* ── Slot board: one segmented control, one rate line, one grid ──────────
       The control filters the grid to a part of the day. Its white thumb slides
       between tabs (--i = active index); counts are the live open figures. */
    #slots-grid-container .sb-seg {
        position: relative; display: grid; grid-template-columns: repeat(3, minmax(0, 1fr));
        padding: 3px; margin-bottom: 12px; border-radius: 12px; background: #EEF1F5;
    }
    #slots-grid-container .sb-seg__thumb {
        position: absolute; top: 3px; bottom: 3px; left: 3px; width: calc((100% - 6px) / 3); border-radius: 9px; background: #fff;
        box-shadow: 0 1px 1px rgba(15, 23, 42, .06), 0 2px 6px -1px rgba(15, 23, 42, .14);
        transform: translateX(calc(var(--i, 0) * 100%));
        transition: transform .28s cubic-bezier(.3, .7, .2, 1);
    }
    #slots-grid-container .sb-seg__tab {
        position: relative; z-index: 1; display: flex; flex-direction: column; align-items: center; gap: 1px;
        padding: 7px 4px 6px; border: 0; background: none; cursor: pointer; font: inherit; color: #64748B;
        -webkit-tap-highlight-color: transparent;
    }
    #slots-grid-container .sb-seg__tab b { position: relative; font-size: 13px; font-weight: 700; letter-spacing: -.01em; }
    #slots-grid-container .sb-seg__tab small { font-size: 11px; font-weight: 600; color: #94A3B8; font-variant-numeric: tabular-nums; }
    #slots-grid-container .sb-seg__tab.is-on b { color: #0F172A; font-weight: 800; }
    #slots-grid-container .sb-seg__tab.is-on small { color: #16A34A; }
    #slots-grid-container .sb-seg__tab:active:not(:disabled) b { opacity: .6; }
    #slots-grid-container .sb-seg__tab.is-none { cursor: default; color: #B6C0CE; }
    /* You have picks in this part of the day. */
    #slots-grid-container .sb-seg__dot { position: absolute; top: 1px; right: -8px; width: 6px; height: 6px; border-radius: 50%; background: #2563EB; }

    #slots-grid-container .sb-rateline {
        display: flex; align-items: baseline; justify-content: space-between; gap: 10px; padding: 0 2px 10px;
        font-size: 12.5px; color: #64748B; font-variant-numeric: tabular-nums;
    }
    #slots-grid-container .sb-rateline b { font-size: 14px; font-weight: 800; color: #0F172A; }
    #slots-grid-container .sb-rateline__peak { font-weight: 600; color: #B45309; white-space: nowrap; }
    #slots-grid-container .sb-rateline.is-offline { color: #B45309; font-weight: 600; }

    /* The segment already names the part of the day — no second heading. */
    #slots-grid-container .sb-seg ~ .sb-group { margin-top: 0; }
    #slots-grid-container .sb-seg ~ .sb-group .sb-group__head { display: none; }
    @media (prefers-reduced-motion: reduce) {
        #slots-grid-container .sb-seg__thumb { transition: none; }
    }
    @media (prefers-reduced-motion: reduce) {
        .co-go__pass svg, .co-go__arrow { transition: none; }
    }
</style>

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

        @if($mvEmbed)
            {{-- Live embedded Google map pinned to the venue's exact coordinates. --}}
            <div class="mven__map" style="margin-top: 12px; border-radius: 16px; overflow: hidden; border: 1px solid #ececf1;">
                <iframe src="{{ $mvEmbed }}" width="100%" height="200" style="border:0; display:block;" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen title="{{ $venue->title }} location map"></iframe>
            </div>
        @endif

        {{-- ── 3. Rating summary + RATE VENUE ────────────────────────────── --}}
        <div class="mven__rule"></div>
        <div class="mven__rating">
            <div class="mven__rating-copy" id="mven-rating-copy">
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

        @if($hostProfile ?? null)
            <div class="mven__rule"></div>
            <a href="{{ route('site.host', ['slug' => $hostProfile->slug]) }}" style="display:inline-flex;align-items:center;gap:6px;font-size:14px;font-weight:700;color:#1e50e6;text-decoration:none;">
                Managed by {{ $hostProfile->display_name }} →
            </a>
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
        {{-- Always rendered (hidden while empty) so a first review posted from RATE VENUE
             can appear at once, without a reload. --}}
        <div id="mven-reviews-wrap" @if(!count($venue->reviews_list)) hidden @endif>
            <div class="mven__rule"></div>
            <h2 class="mven__h2">Reviews (<span data-reviews-count>{{ $venue->reviews }}</span>)</h2>
            <div class="mven__reviews" id="mven-reviews-list">
                @foreach($venue->reviews_list as $review)
                    <div class="mven__review">
                        <div class="mven__review-head">
                            <span class="mven__review-face">{{ mb_strtoupper(mb_substr($review->user, 0, 1)) }}</span>
                            <span class="mven__review-who">
                                <strong>{{ $review->user }} @if(!empty($review->played))<span class="mven__review-played">Played here</span>@endif</strong>
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
        </div>

        {{-- ── 9b. FAQ — answered from this venue's own data (PublicWebController::
             venueFaqs); the same list is the page's FAQPage markup, which search
             engines only honour when the questions are visible on the page. ── --}}
        @if(!empty($venueFaqs))
            <div class="mven__rule"></div>
            <h2 class="mven__h2">Questions about {{ $venue->title }}</h2>
            <div class="mven__faq">
                @foreach($venueFaqs as $faq)
                    <details @if($loop->first) open @endif>
                        <summary>{{ $faq['question'] }}</summary>
                        <p>{{ $faq['answer'] }}</p>
                    </details>
                @endforeach
            </div>
            <style>
                .mven__faq details { border-bottom: 1px solid #E2E8F0; padding: 12px 0; }
                .mven__faq summary { cursor: pointer; list-style: none; display: flex; justify-content: space-between; gap: 12px; font-size: 14px; font-weight: 700; color: #0F172A; }
                .mven__faq summary::-webkit-details-marker { display: none; }
                .mven__faq summary::after { content: '+'; color: #2563EB; font-weight: 800; }
                .mven__faq details[open] summary::after { content: '–'; }
                .mven__faq p { margin: 8px 0 0; font-size: 14px; line-height: 1.55; color: #475569; }
            </style>
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
        <span class="mven__modal-grip" aria-hidden="true"></span>
        <div class="mven__modal-head">
            <div class="mven__modal-id">
                <h2 id="mvenModalTitle" class="mven__modal-title">Book a slot</h2>
                <p class="mven__modal-sub">{{ $venue->title }}@if($venue->location) · {{ $venue->location }}@endif</p>
            </div>
            <button type="button" class="mven__modal-x" data-mven-close aria-label="Close">
                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
        </div>
        <div class="mven__modal-body" data-mven-modal-body></div>
        {{-- The booking summary docks here, outside the scroll, so the pay button
             stays under the thumb while the player scrolls the slot grid. --}}
        <div class="mven__modal-foot" data-mven-modal-foot></div>
    </div>
</div>

{{-- ================================================================= --}}
{{-- Review booking — between the slot picker and Razorpay, phone + PC. --}}
{{-- Every number on it comes from POST /gamehub/{id}/book/quote (the  --}}
{{-- same pricing reserve() charges). Policies are only the venue's    --}}
{{-- own: its cancellation window and its rules — nothing invented.    --}}
{{-- ================================================================= --}}
@php
    $vcoRules = array_values(array_filter($venue->rules ?? []));
@endphp
<div class="vco" id="vco" hidden>
    <div class="vco__backdrop" data-vco-close></div>
    <div class="vco__panel" role="dialog" aria-modal="true" aria-labelledby="vcoTitle">
        <header class="vco__bar">
            <button type="button" class="vco__back" data-vco-close aria-label="Back to slots">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            </button>
            <h2 id="vcoTitle" class="vco__title">Review booking</h2>
        </header>

        <div class="vco__scroll">
            <div class="vco__grid">
                {{-- What you're booking --}}
                <section class="vco__card vco__summary" aria-label="Booking summary">
                    <div class="vco__summary-head">
                        <div class="vco__venue">
                            <strong>{{ $venue->title }}</strong>
                            @if($venue->location)<small>{{ $venue->location }}</small>@endif
                        </div>
                        <span class="vco__sport" id="vco-sport"></span>
                    </div>
                    <div class="vco__meta">
                        <span class="vco__meta-row">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"></rect><line x1="12" y1="5" x2="12" y2="19"></line><circle cx="12" cy="12" r="2.5"></circle></svg>
                            <span id="vco-court"></span>
                        </span>
                        <span class="vco__meta-row">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4.5" width="18" height="16.5" rx="3"></rect><path d="M3 9.5h18M8 2.5v4M16 2.5v4"></path></svg>
                            <span id="vco-date"></span>
                        </span>
                    </div>
                    <ul class="vco__slots" id="vco-slots"></ul>
                </section>

                {{-- Price details --}}
                <section class="vco__card vco__price" id="vco-price" aria-label="Price details">
                    <h3 class="vco__h3">Price details</h3>
                    <p class="vco__error" id="vco-price-error" hidden></p>

                    <div class="vco__row">
                        <span id="vco-court-price-label">Court price</span>
                        <span id="vco-court-price">—</span>
                    </div>

                    <button type="button" class="vco__coupon-open" id="vco-coupon-open" aria-expanded="false" aria-controls="vco-coupon-form">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z"></path><circle cx="7.5" cy="7.5" r="1.5"></circle></svg>
                        <span>Apply coupon</span>
                        <svg class="vco__chev" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"></polyline></svg>
                    </button>
                    <form class="vco__coupon-form" id="vco-coupon-form" hidden autocomplete="off">
                        <input type="text" id="vco-coupon-input" class="vco__coupon-input" placeholder="Enter coupon code" maxlength="40" autocapitalize="characters" spellcheck="false" aria-label="Coupon code">
                        <button type="submit" class="vco__coupon-apply">Apply</button>
                    </form>
                    <div class="vco__coupon-applied" id="vco-coupon-applied" hidden>
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        <span><b id="vco-coupon-applied-code"></b> applied<small id="vco-coupon-applied-save"></small></span>
                        <button type="button" id="vco-coupon-remove" class="vco__link">Remove</button>
                    </div>
                    <p class="vco__coupon-msg" id="vco-coupon-msg" role="status" hidden></p>

                    <div class="vco__row" id="vco-fees-row" hidden>
                        <span>
                            <span id="vco-fees-title">Fees &amp; taxes</span>
                            <button type="button" class="vco__link vco__link--block" id="vco-breakup-toggle" aria-expanded="false" aria-controls="vco-breakup">See breakup</button>
                        </span>
                        <span id="vco-fees"></span>
                    </div>
                    <div class="vco__breakup" id="vco-breakup" hidden>
                        <div id="vco-fee-lines"></div>
                        <div class="vco__row vco__row--sub" id="vco-tax-row"><span id="vco-tax-label">Tax</span><span id="vco-tax"></span></div>
                    </div>

                    <div class="vco__row vco__row--save" id="vco-discount-row" hidden>
                        <span>Coupon <b id="vco-discount-code"></b></span>
                        <span id="vco-discount"></span>
                    </div>

                    <div class="vco__rule"></div>
                    <div class="vco__row vco__row--total"><span>Total amount</span><span id="vco-total">—</span></div>
                    <div class="vco__row vco__row--pay"><span>Payable now</span><span id="vco-paynow">—</span></div>

                    <div class="vco__pay">
                        <span class="vco__pay-total" id="vco-foot-total" aria-hidden="true"></span>
                        <button type="button" class="vco__proceed" id="vco-proceed" disabled>Updating price…</button>
                    </div>
                    <p class="vco__secure">
                        <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"></rect><path d="M8 11V7a4 4 0 0 1 8 0v4"></path></svg>
                        Secure payment via Razorpay
                        <span class="vco__hold" id="vco-hold"></span>
                    </p>
                </section>

                {{-- The venue's own policies --}}
                @if($venue->cancellation || count($vcoRules))
                    <section class="vco__card vco__policies" aria-label="Policies">
                        @if($venue->cancellation)
                            <h4 class="vco__h4">Cancellation policy</h4>
                            <p class="vco__policy">{{ $venue->cancellation }}</p>
                        @endif
                        @if(count($vcoRules))
                            <h4 class="vco__h4">Venue rules</h4>
                            <ul class="vco__rules">
                                @foreach($vcoRules as $rule)
                                    <li>{{ $rule }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </section>
                @endif
            </div>
        </div>
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
                <p class="detail-card-panel__subtitle">Tap one or more open slots to add them to your booking.</p>

                <!-- Date Picker Strip -->
                @php
                    $datePills = [];
                    for ($i = 0; $i < 7; $i++) {
                        // The venue's calendar day, not the server's UTC one: before 5:30 AM IST
                        // UTC is still on yesterday, and "Today" pointed at the wrong date.
                        $dt = \App\Support\BusinessClock::todayDate()->addDays($i);
                        $datePills[] = [
                            'ymd' => $dt->toDateString(),
                            'dayName' => $i === 0 ? 'Today' : ($i === 1 ? 'Tomorrow' : $dt->format('D')),
                            'dateStr' => $dt->format('d M'),
                            'display' => $i === 0 ? 'Today' : ($i === 1 ? 'Tomorrow' : $dt->format('D, d M')),
                        ];
                    }
                @endphp
                {{-- Two weeks of days to tap, plus a calendar for any other date. The
                     server-rendered pills are the no-JS fallback; renderDateStrip() redraws
                     the strip and greys out days past the venue's booking window. --}}
                <div class="dpick">
                    <div class="dpick__head">
                        <span class="dpick__label" id="dpick-label">{{ \App\Support\BusinessClock::todayDate()->format('l, j M') }}</span>
                        <button type="button" class="dpick__cal-btn" id="dpick-cal-btn" aria-expanded="false" aria-controls="dpick-cal">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4.5" width="18" height="16.5" rx="3"></rect><path d="M3 9.5h18M8 2.5v4M16 2.5v4"></path></svg>
                            Pick a date
                        </button>
                    </div>
                    <div class="date-picker-strip" id="date-strip">
                        @foreach($datePills as $index => $dp)
                            <button type="button" data-ymd="{{ $dp['ymd'] }}" class="date-pill {{ $index === 0 ? 'is-active' : '' }}">
                                <span class="date-pill__day">{{ $index === 0 ? 'Today' : \Illuminate\Support\Carbon::parse($dp['ymd'])->format('D') }}</span>
                                <span class="date-pill__date">{{ \Illuminate\Support\Carbon::parse($dp['ymd'])->format('j') }}</span>
                            </button>
                        @endforeach
                    </div>
                    <div class="dpick__cal" id="dpick-cal" hidden></div>
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
                    <div class="crt-head">
                        <label class="sidebar-label">Choose a court</label>
                        <span id="crt-count" class="crt-head__count"></span>
                    </div>
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

                {{-- RATE VENUE — saved for real (POST /gamehub/{id}/review → VenueReviewService,
                     the same path as the app). Was a mock that drew a card and saved nothing.
                     Name comes from the account; one review per person, re-rating edits it. --}}
                <div class="review-form-card rvf" data-rvf>
                    @if(!empty($venue->is_owner))
                        <p class="rvf__note">This is your venue — players' ratings show here and in your partner app.</p>
                    @else
                        <h4 class="rvf__title">{{ !empty($venue->my_review) ? 'Your rating' : 'How was it here?' }}</h4>
                        <p class="rvf__lede">{{ !empty($venue->my_review) ? 'Change it any time — your earlier rating is replaced.' : 'Your rating helps other players pick a court.' }}</p>
                        <div class="rvf__stars" id="rating-selector" role="radiogroup" aria-label="Your rating">
                            @foreach(['Poor', 'Okay', 'Good', 'Very good', 'Excellent'] as $i => $word)
                                <button type="button" class="star-btn rvf__star" role="radio" aria-checked="false" aria-label="{{ $i + 1 }} star{{ $i ? 's' : '' }} — {{ $word }}" data-word="{{ $word }}" onclick="setFormRating({{ $i + 1 }})">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 17.3 6.2 3.7-1.6-7 5.4-4.7-7.1-.6L12 2 9.1 8.7 2 9.3l5.4 4.7-1.6 7z"/></svg>
                                </button>
                            @endforeach
                        </div>
                        <span class="rvf__word" id="rvf-word">Tap a star</span>
                        <textarea id="review-comment" rows="3" maxlength="1000" class="review-input-field review-comment-textarea rvf__text"
                                  placeholder="What stood out — the surface, lights, staff? (optional)">{{ $venue->my_review['text'] ?? '' }}</textarea>
                        <p class="rvf__err" id="rvf-err" hidden></p>
                        @auth
                            <button type="button" onclick="submitReview(this)" class="review-submit-btn rvf__go" id="rvf-go" disabled>
                                {{ !empty($venue->my_review) ? 'Update rating' : 'Post rating' }}
                            </button>
                            <p class="rvf__as">Posting as <b>{{ \Illuminate\Support\Str::of(auth()->user()->name)->explode(' ')->first() }}</b></p>
                        @else
                            <button type="button" class="review-submit-btn rvf__go" onclick="(window.openLoginModal ? window.openLoginModal() : (document.getElementById('loginBtn') || {click(){ location.href='/login'; }}).click())">
                                Sign in to rate
                            </button>
                        @endauth
                    @endif
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
                    <div id="selected-date-text" class="selected-date-preview">{{ $datePills[0]['display'] }}</div>
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
                    <div class="calc-row" id="calc-fee-row" style="display:none">
                        <span>{{ count($venue->fee_rules ?? []) > 1 ? 'Fees' : (($venue->fee_rules[0]['label'] ?? null) ?: 'Convenience fee') }}</span>
                        <span id="calc-fee">₹0</span>
                    </div>
                    <div class="calc-row" id="calc-tax-row" style="display:none">
                        <span id="calc-tax-label">{{ \App\Models\Venue::taxLabel() }}</span>
                        <span id="calc-tax">₹0</span>
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

                @guest
                    <p class="booking-notice-text" style="color: #64748B;">
                        🔒 Sign in required to complete reservation.
                    </p>
                @else
                    <p class="booking-notice-text">Instant digital confirmation backed by Razorpay secure payment.</p>
                @endguest

                {{-- Phone sheet dock (shown only inside .mven__modal-foot): what you picked,
                     drawn as a ticket stub, and the Checkout key. No money here — the bill
                     with fees is the checkout page's job. Filled by renderDock(). --}}
                <div id="co-dock" class="co-dock is-empty" aria-live="polite">
                    <div class="co-stub">
                        <div class="co-stub__date" aria-hidden="true">
                            <span id="co-dow">—</span><b id="co-day">—</b><span id="co-mon"></span>
                        </div>
                        <div class="co-stub__body">
                            <span id="co-what" class="co-stub__what">Your slot</span>
                            <div id="co-times" class="co-stub__times"><span class="co-stub__hint">Tap an open time above</span></div>
                            <span id="co-facts" class="co-stub__facts"></span>
                        </div>
                        <div id="co-court" class="co-stub__court" aria-hidden="true"></div>
                        <button type="button" id="co-clear" class="co-stub__clear" aria-label="Clear picked slots" onclick="clearPicks()">
                            <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                        </button>
                    </div>
                    <button type="button" id="co-go" class="co-go" disabled onclick="goCheckout(this)">
                        <span class="co-go__pass" aria-hidden="true">
                            <svg viewBox="0 0 32 22" width="30" height="21"><path d="M3 2h26a1 1 0 0 1 1 1v4.5a3.5 3.5 0 0 0 0 7V19a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1v-4.5a3.5 3.5 0 0 0 0-7V3a1 1 0 0 1 1-1z" fill="#fff"/><path d="M21 3.5v15" stroke="#93B4F5" stroke-width="1.4" stroke-dasharray="1.6 1.8"/><path d="M7 8h9M7 11h6M7 14h8" stroke="#2563EB" stroke-width="1.6" stroke-linecap="round"/><circle cx="25.5" cy="11" r="2" fill="#2563EB"/></svg>
                        </span>
                        <span class="co-go__label" id="co-go-label">{{ auth()->check() ? 'Checkout' : 'Sign in to checkout' }}</span>
                        <span class="co-go__arrow" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h13M13 6l6 6-6 6"/></svg>
                        </span>
                    </button>
                </div>
            </div>
        </div>

    </div>

</section>

<style>
/* ==========================================================================
   MINI THERMAL PRINTER MODAL & PHYSICAL RECEIPT STYLING
   ========================================================================== */
.printer-overlay-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(10, 15, 29, 0.78);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    z-index: 999999;
    align-items: center;
    justify-content: center;
    padding: 16px;
    box-sizing: border-box;
    overflow-y: auto;
}

.printer-modal-wrapper {
    width: 100%;
    max-width: 360px;
    margin: auto;
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    padding: 12px 0 24px;
}

/* Virtual Mini Printer Bezel / Casing */
.virtual-printer-housing {
    width: 100%;
    background: linear-gradient(180deg, #1E293B 0%, #0F172A 100%);
    border-radius: 18px 18px 0 0;
    padding: 14px 18px 12px;
    border: 1px solid #334155;
    border-bottom: none;
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.4);
    box-sizing: border-box;
    position: relative;
    z-index: 2;
}

.printer-housing-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.printer-brand {
    display: flex;
    align-items: center;
    gap: 8px;
}

.printer-brand__icon {
    font-size: 16px;
}

.printer-brand__name {
    font-size: 11.5px;
    font-weight: 800;
    letter-spacing: 0.08em;
    color: #94A3B8;
    text-transform: uppercase;
}

.printer-status-led {
    display: flex;
    align-items: center;
    gap: 6px;
}

.printer-led-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #22C55E;
    box-shadow: 0 0 8px #22C55E;
    animation: printerLedPulse 2s infinite ease-in-out;
}

@keyframes printerLedPulse {
    0%, 100% { opacity: 1; transform: scale(1); box-shadow: 0 0 8px #22C55E; }
    50% { opacity: 0.6; transform: scale(0.92); box-shadow: 0 0 3px #22C55E; }
}

.printer-led-text {
    font-size: 10.5px;
    font-weight: 700;
    letter-spacing: 0.05em;
    color: #22C55E;
}

/* Paper Output Slit */
.printer-slot-mouth {
    height: 8px;
    background: #030712;
    border-radius: 4px;
    box-shadow: inset 0 3px 6px rgba(0,0,0,0.9);
    margin-top: 10px;
    border-bottom: 1.5px solid #475569;
}

/* Paper Ejection Container */
.thermal-receipt-scroll-container {
    width: 100%;
    overflow: hidden;
    position: relative;
    z-index: 1;
    margin-top: -3px;
    display: flex;
    justify-content: center;
}

/* The Thermal Paper Receipt */
.thermal-receipt-paper {
    width: 320px;
    background: #FFFFFF;
    color: #111827;
    font-family: 'Courier New', Courier, 'Space Mono', Consolas, monospace;
    font-size: 12.5px;
    line-height: 1.4;
    box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.45);
    box-sizing: border-box;
    position: relative;
}

/* Animated sliding out of printer */
.thermal-receipt-paper.is-ejecting {
    animation: receiptEjectAnim 1.1s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

@keyframes receiptEjectAnim {
    0% {
        transform: translateY(-80%);
        opacity: 0;
    }
    40% {
        opacity: 1;
    }
    100% {
        transform: translateY(0);
        opacity: 1;
    }
}

/* Sawtooth Serrated Tear Cut (Top & Bottom) */
.receipt-sawtooth {
    width: 100%;
    height: 8px;
    background-repeat: repeat-x;
    background-size: 14px 8px;
}

.receipt-sawtooth--top {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 14 8'%3E%3Cpolygon points='0,0 7,8 14,0' fill='%230F172A'/%3E%3C/svg%3E");
}

.receipt-sawtooth--bottom {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 14 8'%3E%3Cpolygon points='0,0 7,8 14,0' fill='%23FFFFFF'/%3E%3C/svg%3E");
    margin-top: -1px;
}

.receipt-inner-content {
    padding: 16px 20px 18px;
}

/* Receipt Header */
.receipt-header {
    text-align: center;
    margin-bottom: 12px;
}

.receipt-haraan-logo {
    height: 34px;
    width: auto;
    margin: 0 auto 6px auto;
    display: block;
    filter: grayscale(100%) contrast(250%);
}

.receipt-brand-text {
    font-size: 15px;
    font-weight: 900;
    letter-spacing: 0.1em;
    color: #000;
    margin: 0;
    text-transform: uppercase;
}

.receipt-venue-title {
    font-size: 14px;
    font-weight: 800;
    color: #111;
    margin: 4px 0 2px 0;
    text-transform: uppercase;
}

.receipt-venue-sub {
    font-size: 11px;
    color: #4B5563;
    margin: 0 0 8px 0;
}

/* Dividers */
.receipt-divider-stars,
.receipt-divider-dash,
.receipt-divider-double,
.receipt-divider-dots {
    text-align: center;
    letter-spacing: 0.05em;
    color: #6B7280;
    font-size: 11px;
    user-select: none;
    margin: 8px 0;
    white-space: nowrap;
    overflow: hidden;
}

.receipt-divider-double {
    color: #111827;
    font-weight: bold;
}

/* Key-Value Tables */
.receipt-table {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.receipt-row {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    gap: 8px;
}

.receipt-label {
    font-size: 11.5px;
    color: #4B5563;
    font-weight: 600;
}

.receipt-value {
    font-size: 12px;
    color: #000;
    font-weight: 700;
    text-align: right;
    word-break: break-word;
}

.receipt-bold {
    font-weight: 900;
    color: #000;
}

/* Slots Section */
.receipt-slots-block {
    margin: 6px 0;
}

.receipt-slots-title {
    font-size: 11.5px;
    font-weight: 800;
    color: #111;
    margin-bottom: 4px;
}

.receipt-slots-lines {
    font-size: 11.5px;
    color: #1F2937;
    line-height: 1.5;
}

.receipt-slot-row {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    gap: 8px;
    font-size: 11.5px;
    margin-bottom: 3px;
}

.receipt-total-row {
    margin: 8px 0 4px;
    align-items: center;
}

.receipt-total-label {
    font-size: 14px;
    font-weight: 900;
    letter-spacing: 0.05em;
    color: #000;
}

.receipt-total-value {
    font-size: 18px;
    font-weight: 900;
    color: #000;
}

.receipt-payment-status {
    text-align: right;
    font-size: 10.5px;
    font-weight: 700;
    color: #16A34A;
    margin-bottom: 6px;
    letter-spacing: 0.04em;
}

/* QR Code Section */
.receipt-qr-center {
    display: flex;
    flex-direction: column;
    align-items: center;
    margin: 12px 0 10px;
}

.receipt-qr-box {
    padding: 8px;
    background: #fff;
    border: 1px dashed #9CA3AF;
    display: inline-block;
}

.receipt-qr-box canvas,
.receipt-qr-box img {
    display: block;
    margin: 0 auto;
}

.receipt-qr-caption {
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.08em;
    color: #4B5563;
    margin-top: 6px;
    text-transform: uppercase;
}

/* Simulated Barcode */
.receipt-barcode-box {
    text-align: center;
    margin: 10px 0 8px;
}

.receipt-barcode-lines {
    font-size: 14px;
    font-weight: 900;
    letter-spacing: 0.14em;
    color: #111;
    line-height: 1;
}

.receipt-barcode-code {
    font-size: 10.5px;
    font-weight: 700;
    letter-spacing: 0.16em;
    color: #4B5563;
    margin-top: 3px;
}

/* Receipt Footer */
.receipt-footer {
    text-align: center;
    margin-top: 8px;
}

.receipt-tear-notice {
    font-size: 10px;
    color: #9CA3AF;
    margin-bottom: 6px;
}

.receipt-footer-tag {
    font-size: 11px;
    font-weight: 700;
    color: #111;
    margin: 0 0 2px 0;
}

.receipt-footer-url {
    font-size: 10px;
    color: #6B7280;
    margin: 0;
}

/* Action Control Buttons Below Receipt */
.printer-actions-panel {
    width: 320px;
    margin-top: 16px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.printer-btn {
    width: 100%;
    padding: 13px 16px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    text-decoration: none;
    border: none;
    box-sizing: border-box;
    transition: transform 0.12s ease, opacity 0.15s ease, background 0.15s ease;
}

.printer-btn:active {
    transform: scale(0.98);
}

.printer-btn--print {
    background: #2563EB;
    color: #FFFFFF;
    box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35);
}

.printer-btn--print:hover {
    background: #1D4ED8;
}

.printer-btn--bt {
    background: #1E293B;
    color: #38BDF8;
    border: 1px solid #334155;
}

.printer-btn--bt:hover {
    background: #0F172A;
    color: #7DD3FC;
}

.printer-actions-subrow {
    display: flex;
    gap: 8px;
}

.printer-btn--link {
    flex: 1;
    background: #FFFFFF;
    color: #0F172A;
    border: 1.5px solid #CBD5E1;
    font-size: 13px;
}

.printer-btn--link:hover {
    background: #F8FAFC;
    border-color: #94A3B8;
}

.printer-btn--close {
    flex: 1;
    background: rgba(255, 255, 255, 0.1);
    color: #E2E8F0;
    border: 1px solid rgba(255, 255, 255, 0.2);
    font-size: 13px;
}

.printer-btn--close:hover {
    background: rgba(255, 255, 255, 0.18);
}

/* Spinner */
.printer-spinner {
    display: inline-block;
    width: 14px;
    height: 14px;
    border: 2px solid rgba(255,255,255,0.3);
    border-radius: 50%;
    border-top-color: #fff;
    animation: pSpin 0.7s linear infinite;
}
@keyframes pSpin { to { transform: rotate(360deg); } }

/* ==========================================================================
   PHYSICAL THERMAL PRINT MEDIA QUERY (58mm & 80mm Roll Support)
   ========================================================================== */
@media print {
    body * {
        visibility: hidden !important;
    }
    
    html, body {
        background: #fff !important;
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
        height: auto !important;
    }

    #success-modal {
        display: block !important;
        position: static !important;
        background: transparent !important;
        backdrop-filter: none !important;
        padding: 0 !important;
        margin: 0 !important;
        overflow: visible !important;
        visibility: visible !important;
    }

    .printer-modal-wrapper,
    .thermal-receipt-scroll-container {
        display: block !important;
        width: 100% !important;
        max-height: none !important;
        overflow: visible !important;
        margin: 0 !important;
        padding: 0 !important;
        background: transparent !important;
        box-shadow: none !important;
        visibility: visible !important;
    }

    #thermal-printable-receipt,
    #thermal-printable-receipt * {
        visibility: visible !important;
    }

    #thermal-printable-receipt {
        display: block !important;
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
        width: 58mm !important; /* Perfect fit for 58mm & 80mm thermal rolls */
        max-width: 58mm !important;
        margin: 0 !important;
        padding: 2mm 1.5mm !important;
        background: #fff !important;
        color: #000 !important;
        box-shadow: none !important;
        border: none !important;
        transform: none !important;
        animation: none !important;
        font-family: 'Courier New', Courier, monospace !important;
        font-size: 10.5px !important;
        line-height: 1.3 !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .receipt-sawtooth,
    .virtual-printer-housing,
    .printer-actions-panel {
        display: none !important;
        visibility: hidden !important;
    }

    @page {
        size: 58mm auto;
        margin: 0mm;
    }
}
</style>

<!-- Mini Thermal Printer Interactive Ticket Modal -->
<div id="success-modal" class="printer-overlay-modal" style="display:none;" role="dialog" aria-modal="true" aria-label="Official Booking Pass">
    <div class="printer-modal-wrapper">
        
        <!-- Virtual POS Mini Printer Bezel Slot -->
        <div class="virtual-printer-housing" aria-hidden="true">
            <div class="printer-housing-header">
                <div class="printer-brand">
                    <span class="printer-brand__icon">🖨️</span>
                    <span class="printer-brand__name">HARAAN POS-58 MINI PRINTER</span>
                </div>
                <div class="printer-status-led">
                    <span class="printer-led-dot"></span>
                    <span class="printer-led-text">PRINTING READY</span>
                </div>
            </div>
            <div class="printer-slot-mouth"></div>
        </div>

        <!-- Thermal Paper Slip (Ejects from printer) -->
        <div class="thermal-receipt-scroll-container">
            <div id="thermal-printable-receipt" class="thermal-receipt-paper is-ejecting">
                
                <!-- Serrated Top Tear Edge -->
                <div class="receipt-sawtooth receipt-sawtooth--top" aria-hidden="true"></div>

                <div class="receipt-inner-content">
                    <!-- Receipt Header -->
                    <div class="receipt-header">
                        <img src="{{ asset('images/haraan-logo.png') }}" class="receipt-haraan-logo" alt="HARAAN">
                        <p class="receipt-brand-text">HARAAN SPORTS</p>
                        <h2 class="receipt-venue-title">{{ $venue->title }}</h2>
                        <p class="receipt-venue-sub">
                            {{ $venue->location ? $venue->location . ' · ' : '' }}Official Entry Pass
                        </p>
                        <div class="receipt-divider-stars">* * * * * * * * * * * * * * * * * * * *</div>
                    </div>

                    <!-- Meta Information -->
                    <div class="receipt-table">
                        <div class="receipt-row">
                            <span class="receipt-label">PASS REF #:</span>
                            <span class="receipt-value receipt-bold" id="tp-ref">HT-0000000000</span>
                        </div>
                        <div class="receipt-row">
                            <span class="receipt-label">BOOKED ON:</span>
                            <span class="receipt-value" id="tp-time">--</span>
                        </div>
                        <div class="receipt-row">
                            <span class="receipt-label">CUSTOMER:</span>
                            <span class="receipt-value" id="tp-user">{{ auth()->user()?->name ?? 'Guest User' }}</span>
                        </div>
                    </div>

                    <div class="receipt-divider-dash">----------------------------------------</div>

                    <!-- Court & Sport Details -->
                    <div class="receipt-table">
                        <div class="receipt-row">
                            <span class="receipt-label">SPORT:</span>
                            <span class="receipt-value receipt-bold" id="tp-sport">--</span>
                        </div>
                        <div class="receipt-row">
                            <span class="receipt-label">COURT/TURF:</span>
                            <span class="receipt-value receipt-bold" id="tp-court">--</span>
                        </div>
                        <div class="receipt-row">
                            <span class="receipt-label">PLAY DATE:</span>
                            <span class="receipt-value" id="tp-date">--</span>
                        </div>
                    </div>

                    <div class="receipt-divider-dots">. . . . . . . . . . . . . . . . . . . .</div>

                    <!-- Booked Slots List -->
                    <div class="receipt-slots-block">
                        <div class="receipt-slots-title">RESERVED SLOTS:</div>
                        <div id="tp-slots" class="receipt-slots-lines">
                            <!-- Injected dynamically -->
                        </div>
                    </div>

                    <div class="receipt-divider-dash">----------------------------------------</div>

                    <!-- Pricing Breakdown -->
                    <div class="receipt-table">
                        <div class="receipt-row">
                            <span class="receipt-label">SLOTS SUBTOTAL</span>
                            <span class="receipt-value" id="tp-subtotal">₹0</span>
                        </div>
                        <div class="receipt-row" id="tp-fee-row">
                            <span class="receipt-label" id="tp-fee-label">CONVENIENCE FEE</span>
                            <span class="receipt-value" id="tp-fee">₹0</span>
                        </div>
                        <div class="receipt-row" id="tp-tax-row">
                            <span class="receipt-label" id="tp-tax-label">{{ strtoupper(\App\Models\Venue::taxLabel()) }}</span>
                            <span class="receipt-value" id="tp-tax">₹0</span>
                        </div>
                        <div class="receipt-row" id="tp-discount-row">
                            <span class="receipt-label">DISCOUNT</span>
                            <span class="receipt-value" id="tp-discount">₹0</span>
                        </div>
                    </div>

                    <div class="receipt-divider-double">========================================</div>

                    <div class="receipt-row receipt-total-row">
                        <span class="receipt-total-label">TOTAL PAID:</span>
                        <span class="receipt-total-value" id="tp-total">₹0</span>
                    </div>
                    <div class="receipt-payment-status">
                        STATUS: PAID ONLINE (RAZORPAY) ✓
                    </div>

                    <div class="receipt-divider-dash">----------------------------------------</div>

                    <!-- Centered QR Code -->
                    <div class="receipt-qr-center">
                        <div class="receipt-qr-box" id="tp-qr"></div>
                        <div class="receipt-qr-caption">SCAN AT ENTRY GATE FOR VERIFICATION</div>
                    </div>

                    <!-- Simulated Barcode -->
                    <div class="receipt-barcode-box">
                        <div class="receipt-barcode-lines">|||||||| | ||| |||||| | ||||| ||| ||||| |||||||</div>
                        <div class="receipt-barcode-code" id="tp-barcode-code">HT-0000000000</div>
                    </div>

                    <!-- Receipt Footer -->
                    <div class="receipt-footer">
                        <div class="receipt-tear-notice">- - - - - - - - - ✂ CUT HERE ✂ - - - - - - - - -</div>
                        <p class="receipt-footer-tag">Thank you for playing with Haraan!</p>
                        <p class="receipt-footer-url">haraan.app • Support: support@haraan.app</p>
                    </div>

                </div>

                <!-- Serrated Bottom Tear Edge -->
                <div class="receipt-sawtooth receipt-sawtooth--bottom" aria-hidden="true"></div>
            </div>
        </div>

        <!-- Action Control Buttons Below Receipt -->
        <div class="printer-actions-panel">
            <button type="button" onclick="printThermalReceipt()" class="printer-btn printer-btn--print">
                <span class="printer-btn__icon">🖨️</span>
                <span>Print Ticket Slip (58mm / 80mm)</span>
            </button>

            <!-- Bluetooth ESC/POS Direct Print Button for Turf Desks / Partners -->
            <button type="button" id="btn-bt-print" onclick="printViaBluetooth()" class="printer-btn printer-btn--bt">
                <span class="printer-btn__icon">📶</span>
                <span>Bluetooth Print (Partner Desk)</span>
            </button>

            <div class="printer-actions-subrow">
                <a href="{{ route('site.bookings') }}" class="printer-btn printer-btn--link">
                    View My Bookings
                </a>
                <button type="button" onclick="closeSuccessModal()" class="printer-btn printer-btn--close">
                    Done
                </button>
            </div>
        </div>

    </div>
</div>

<script src="{{ asset('js/qrcode.min.js') }}"></script>
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>

<script>
    const venueCourts = @json($venue->courts);
    const venueSports = @json($venue->sports);
    const courtPrices = @json($venue->court_prices ?? new \stdClass);
    const courtPeak = @json($venue->court_peak ?? new \stdClass);
    const venueBasePrice = {{ (int) $venue->price }};
    // Mirrors Venue::convenienceFeeFor() — the server recomputes it; this is only the estimate.
    // Mirrors Venue::feeLinesFor(): the convenience fee + the admin's named fees, each
    // rounded to paise. Only an estimate for the sheet; the review page asks the server.
    const venueFeeRules = @json($venue->fee_rules ?? []);
    function venueFeeFor(subtotal) {
        if (subtotal <= 0) return 0;
        const sum = venueFeeRules.reduce((t, r) => t + (r.type === 'flat'
            ? Math.round(r.value * 100) / 100
            : Math.round(subtotal * r.value) / 100), 0);
        return Math.round(sum * 100) / 100;
    }
    // Mirrors Venue::taxFor() (/control → Platform rules → Fees → Pulse tax), on subtotal − discount.
    const venueTaxType = @json(\App\Support\PlatformRules::string('fees.venue_tax_type'));
    const venueTaxValue = {{ \App\Support\PlatformRules::float('fees.venue_tax_value') }};
    function venueTaxFor(subtotal, discount = 0) {
        const base = Math.max(0, subtotal - discount);
        if (subtotal <= 0 || base <= 0) return 0;
        if (venueTaxType === 'flat') return Math.round(venueTaxValue * 100) / 100;
        if (venueTaxType === 'percent') return Math.round(base * venueTaxValue) / 100;
        return 0;
    }

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
    const isAuthenticated = {{ auth()->check() ? 'true' : 'false' }};
    const venueId = {{ (int) $venue->id }};
    const csrfToken = '{{ csrf_token() }}';
    let selectedDate = '{{ $datePills[0]['ymd'] }}';
    let selectedDateDisplay = '{{ $datePills[0]['display'] }}';
    let selectedSport = venueSports[0];
    let selectedCourt = (venueCourts[selectedSport] || [])[0] ?? null;
    // The admin's slot rows per date (see PublicWebController::decorateVenueDetail) and the
    // court ids the availability check is keyed by.
    const slotsByDate = @json($venue->slots_by_date ?? new \stdClass);
    const courtIds = @json($venue->court_ids ?? new \stdClass);
    const todayYmd = '{{ $datePills[0]['ymd'] }}';
    // Live state per slot id from /api/venues/{id}/availability ('open' | 'booked' | 'closed').
    // null = not answered yet (or unreachable): chips then show the admin's own open/closed
    // switch and claim nothing about bookings, the same fallback as the app.
    let liveStates = null;
    // Set when the whole day can't be booked (outside the booking window).
    let dayRefusal = '';
    let availabilitySeq = 0;
    let selectedSlots = [];
    let currentFormRating = 0;

    function toggleFavorite(btn) {
        const svg = document.getElementById('fav-icon');
        if (svg.getAttribute('fill') === 'none') {
            svg.setAttribute('fill', '#2563EB');
            svg.setAttribute('stroke', '#2563EB');
            btn.style.borderColor = '#DBEAFE';
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

    /* ---- Dates: a two-week strip plus a calendar for any other day ------ */
    const WEEKDAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    const WEEKDAYS_LONG = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const MONTHS_LONG = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    const STRIP_DAYS = 14;
    // Before the first availability answer says otherwise, offer this far ahead; the
    // server refuses anything past the venue's real window with its own message.
    const FALLBACK_WINDOW_DAYS = 60;

    // Local-part parsing throughout, so a date never slides a day through UTC.
    const parseYmd = (s) => { const [y, m, d] = String(s).split('-').map(Number); return new Date(y, m - 1, d); };
    const toYmd = (d) => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    const addDays = (d, n) => new Date(d.getFullYear(), d.getMonth(), d.getDate() + n);

    // The last day this viewer may book (from /availability's booking_window).
    let lastBookableYmd = toYmd(addDays(parseYmd(todayYmd), FALLBACK_WINDOW_DAYS));
    let calMonth = null; // first of the month the calendar shows

    function dayDisplay(ymd) {
        const d = parseYmd(ymd);
        const diff = Math.round((d - parseYmd(todayYmd)) / 86400000);
        if (diff === 0) return 'Today';
        if (diff === 1) return 'Tomorrow';
        return WEEKDAYS[d.getDay()] + ', ' + d.getDate() + ' ' + MONTHS[d.getMonth()];
    }

    // Slot rows repeat by weekday (VenueSlot::forDate), so a date past the pre-built
    // week borrows the rows of the same weekday inside it.
    function daySlots(ymd) {
        if (slotsByDate[ymd]) return slotsByDate[ymd];
        const wd = parseYmd(ymd).getDay();
        const twin = Object.keys(slotsByDate).find(k => parseYmd(k).getDay() === wd);
        return twin ? slotsByDate[twin] : [];
    }

    function renderDateStrip() {
        const strip = document.getElementById('date-strip');
        if (!strip) return;
        const today = parseYmd(todayYmd);
        const days = [];
        for (let i = 0; i < STRIP_DAYS; i++) days.push(toYmd(addDays(today, i)));
        // A calendar pick past the strip joins it at the end, so the choice stays visible.
        const custom = !days.includes(selectedDate);
        if (custom) days.push(selectedDate);

        strip.innerHTML = days.map((ymd, i) => {
            const d = parseYmd(ymd);
            const locked = ymd > lastBookableYmd;
            const monthStart = d.getDate() === 1 || (custom && i === days.length - 1);
            const cls = ['date-pill',
                ymd === selectedDate ? 'is-active' : '',
                ymd === todayYmd ? 'is-today' : '',
                monthStart && ymd !== todayYmd ? 'is-month-start' : '',
                custom && i === days.length - 1 ? 'is-custom' : '',
                locked ? 'is-locked' : ''].join(' ');
            const top = ymd === todayYmd ? 'Today' : (monthStart ? MONTHS[d.getMonth()] : WEEKDAYS[d.getDay()]);
            return `<button type="button" class="${cls}" data-ymd="${ymd}" ${locked ? 'disabled' : ''}
                        aria-pressed="${ymd === selectedDate}" aria-label="${WEEKDAYS_LONG[d.getDay()]} ${d.getDate()} ${MONTHS_LONG[d.getMonth()]}">
                        <span class="date-pill__day">${top}</span>
                        <span class="date-pill__date">${d.getDate()}</span>
                    </button>`;
        }).join('');

        const label = document.getElementById('dpick-label');
        if (label) {
            const d = parseYmd(selectedDate);
            label.textContent = WEEKDAYS_LONG[d.getDay()] + ', ' + d.getDate() + ' ' + MONTHS[d.getMonth()];
        }
        centerActiveDate();
    }

    // Scroll only the strip. scrollIntoView() also scrolls every ancestor - inside the
    // mobile sheet that shoved the whole (overflow: hidden) card sideways.
    function centerActiveDate() {
        const strip = document.getElementById('date-strip');
        const pill = strip && strip.querySelector('.is-active');
        if (!pill) return;
        strip.scrollLeft = pill.offsetLeft - (strip.clientWidth - pill.offsetWidth) / 2; // strip is the offsetParent
    }

    function renderCalendar() {
        const cal = document.getElementById('dpick-cal');
        if (!cal || cal.hidden) return;
        const today = parseYmd(todayYmd);
        const last = parseYmd(lastBookableYmd);
        const y = calMonth.getFullYear(), m = calMonth.getMonth();
        const canPrev = y > today.getFullYear() || (y === today.getFullYear() && m > today.getMonth());
        const canNext = y < last.getFullYear() || (y === last.getFullYear() && m < last.getMonth());
        const lead = new Date(y, m, 1).getDay();
        const count = new Date(y, m + 1, 0).getDate();

        let cells = '';
        for (let i = 0; i < lead; i++) cells += '<span class="dpick__cell is-blank"></span>';
        for (let n = 1; n <= count; n++) {
            const ymd = toYmd(new Date(y, m, n));
            const off = ymd < todayYmd || ymd > lastBookableYmd;
            const cls = ['dpick__cell',
                ymd === selectedDate ? 'is-active' : '',
                ymd === todayYmd ? 'is-today' : ''].join(' ');
            cells += `<button type="button" class="${cls}" data-ymd="${ymd}" ${off ? 'disabled' : ''}>${n}</button>`;
        }

        const chev = (d) => `<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="${d}"></polyline></svg>`;
        cal.innerHTML = `
            <div class="dpick__cal-head">
                <button type="button" class="dpick__nav" data-cal-step="-1" ${canPrev ? '' : 'disabled'} aria-label="Previous month">${chev('15 18 9 12 15 6')}</button>
                <strong>${MONTHS_LONG[m]} ${y}</strong>
                <button type="button" class="dpick__nav" data-cal-step="1" ${canNext ? '' : 'disabled'} aria-label="Next month">${chev('9 18 15 12 9 6')}</button>
            </div>
            <div class="dpick__grid">
                ${WEEKDAYS.map(w => `<span class="dpick__wd">${w.charAt(0)}</span>`).join('')}
                ${cells}
            </div>
            <p class="dpick__note">Bookable up to ${dayDisplay(lastBookableYmd)}</p>`;
    }

    function toggleCalendar(open) {
        const cal = document.getElementById('dpick-cal');
        const btn = document.getElementById('dpick-cal-btn');
        if (!cal || !btn) return;
        const show = open ?? cal.hidden;
        cal.hidden = !show;
        btn.setAttribute('aria-expanded', String(show));
        if (show) {
            const d = parseYmd(selectedDate);
            calMonth = new Date(d.getFullYear(), d.getMonth(), 1);
            renderCalendar();
        }
    }

    // Learn the viewer's real window from an availability answer.
    function applyBookingWindow(win) {
        if (!win || !/^\d{4}-\d{2}-\d{2}$/.test(win.last_date || '') || win.last_date === lastBookableYmd) return;
        lastBookableYmd = win.last_date;
        renderDateStrip();
        renderCalendar();
    }

    function selectDate(ymd) {
        if (!ymd || ymd === selectedDate) return;
        selectedDate = ymd;
        selectedDateDisplay = dayDisplay(ymd);
        const dtEl = document.getElementById('selected-date-text');
        if (dtEl) dtEl.innerText = selectedDateDisplay;
        renderDateStrip();

        // A new day is a new set of slots and bookings.
        selectedSlots = [];
        updatePriceBreakdown();
        loadAvailability();
    }

    document.addEventListener('click', (e) => {
        const pill = e.target.closest('#date-strip .date-pill');
        if (pill && !pill.disabled) { selectDate(pill.dataset.ymd); return; }
        if (e.target.closest('#dpick-cal-btn')) { toggleCalendar(); return; }
        const step = e.target.closest('#dpick-cal [data-cal-step]');
        if (step && !step.disabled) {
            calMonth = new Date(calMonth.getFullYear(), calMonth.getMonth() + Number(step.dataset.calStep), 1);
            renderCalendar();
            return;
        }
        const cell = e.target.closest('#dpick-cal .dpick__cell[data-ymd]');
        if (cell && !cell.disabled) { toggleCalendar(false); selectDate(cell.dataset.ymd); }
    });

    function selectSport(sport) {
        selectedSport = sport;
        selectedCourt = (venueCourts[sport] || [])[0] ?? null;

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
        loadAvailability();
    }

    function selectCourt(court) {
        selectedCourt = court;
        renderCourtSelector();
        // Checkout books one court for every picked slot, so a court switch starts over.
        selectedSlots = [];
        updatePriceBreakdown();
        loadAvailability();
    }

    function renderCourtSelector() {
        const container = document.getElementById('court-selector-container');
        if (!container) return;

        const courts = venueCourts[selectedSport] || [];
        const countEl = document.getElementById('crt-count');
        if (countEl) countEl.innerText = courts.length > 1 ? courts.length + ' courts' : '';
        // A card per court: its drawing, its name, its own rate (and peak rate when set).
        const art = courtDrawing(selectedSport, null);
        container.innerHTML = courts.map(court => {
            const isActive = court === selectedCourt;
            const rate = courtPrices[court] ?? venueBasePrice;
            const peak = courtPeak[court] && courtPeak[court].price > rate ? courtPeak[court].price : 0;
            return `
                <button type="button" onclick="selectCourt('${court}')" class="court-pill crt ${isActive ? 'is-active' : ''}" aria-pressed="${isActive}">
                    <span class="crt__art">${art}</span>
                    <span class="crt__txt">
                        <b>${court}</b>
                        <small>₹${Number(rate).toLocaleString('en-IN')}<i>/hr</i></small>
                        ${peak ? `<em>peak ₹${Number(peak).toLocaleString('en-IN')}</em>` : ''}
                    </span>
                    <svg class="crt__tick" viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="8" fill="#2563EB"/><path d="M4.5 8.2l2.3 2.3 4.7-4.9" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
            `;
        }).join('');
    }

    // Bookability per slot for the picked day + court: real bookings, live payment holds,
    // court blocks and closed days, the same answer checkout gives (VenueSlotAvailability).
    // `quiet` (the background re-check) keeps the current chips on screen while it asks.
    async function loadAvailability(quiet = false) {
        const seq = ++availabilitySeq;
        if (!quiet) {
            liveStates = null;
            dayRefusal = '';
            renderSlots();
        }

        const params = new URLSearchParams({ date: selectedDate });
        const courtId = courtIds[selectedCourt];
        if (courtId) params.set('court_id', courtId);

        try {
            const res = await fetch('/api/venues/' + venueId + '/availability?' + params.toString(), {
                headers: { 'Accept': 'application/json' },
            });
            const body = await res.json().catch(() => ({}));
            if (seq !== availabilitySeq) return; // a newer date/court pick owns the grid

            dayRefusal = '';
            applyBookingWindow(body.booking_window || (body.data && body.data.booking_window));
            if (res.status === 422) {
                dayRefusal = body.message || 'This day is not open for booking yet.';
                liveStates = {};
            } else if (res.ok && body.data && Array.isArray(body.data.slots)) {
                const previous = liveStates;
                liveStates = {};
                body.data.slots.forEach(r => { liveStates[r.id] = r.state; });
                // Only a background re-check of the same grid can "change" under the player.
                slotFlips = quiet && previous ? diffStates(previous, liveStates) : {};
            } else {
                if (!quiet && liveStates === null) { liveStates = {}; liveFailed = true; renderSlots(); }
                return; // unreachable: keep the template fallback
            }
        } catch (e) {
            if (!quiet && liveStates === null) { liveStates = {}; liveFailed = true; renderSlots(); }
            return;
        }
        liveFailed = false;
        liveCheckedAt = Date.now();

        // Drop picks that were taken while the player was deciding.
        const before = selectedSlots.length;
        const lost = selectedSlots.filter(s => slotState(s.slotId) !== 'open');
        selectedSlots = selectedSlots.filter(s => slotState(s.slotId) === 'open');
        if (selectedSlots.length !== before) updatePriceBreakdown();
        const flips = slotFlips; // renderSlots spends them on the flash
        renderSlots(!quiet);
        announceFlips(lost, flips);
    }

    // What moved between two live reads: { slotId: 'taken' | 'freed' }.
    let slotFlips = {};
    // Which part of the day the slot grid shows ('Morning' | 'Afternoon' | 'Evening').
    let activePart = null;
    function selectPart(name) {
        if (name === activePart) return;
        activePart = name;
        renderSlots(true);
    }
    let liveCheckedAt = 0;
    let liveFailed = false;
    function diffStates(prev, next) {
        const out = {};
        Object.keys(next).forEach(id => {
            if (!(id in prev) || prev[id] === next[id]) return;
            if (prev[id] === 'open' && next[id] === 'booked') out[id] = 'taken';
            else if (prev[id] !== 'open' && next[id] === 'open') out[id] = 'freed';
        });
        return out;
    }

    // Tell the player when the grid changed under them — the "someone else is booking" moment.
    function announceFlips(lostPicks, flips) {
        const label = (id) => {
            const s = daySlots(selectedDate).find(x => String(x.id) === String(id));
            return s ? s.time.split(' - ')[0] : 'A slot';
        };
        if (lostPicks.length) {
            slotToast(`${lostPicks[0].time.split(' - ')[0]} was just booked by another player — removed from your picks.`, 'taken');
            return;
        }
        const ids = Object.keys(flips);
        if (!ids.length) return;
        const taken = ids.filter(id => flips[id] === 'taken');
        const freed = ids.filter(id => flips[id] === 'freed');
        if (taken.length) {
            slotToast(taken.length === 1 ? `${label(taken[0])} just got booked` : `${taken.length} slots just got booked`, 'taken');
        } else if (freed.length) {
            slotToast(freed.length === 1 ? `${label(freed[0])} just opened up` : `${freed.length} slots just opened up`, 'freed');
        }
    }

    let slotToastTimer = 0;
    function slotToast(text, kind) {
        let el = document.getElementById('slot-live-toast');
        if (!el) {
            el = document.createElement('div');
            el.id = 'slot-live-toast';
            el.className = 'slot-toast';
            el.setAttribute('role', 'status');
            el.setAttribute('aria-live', 'polite');
            document.body.appendChild(el);
        }
        el.className = 'slot-toast is-' + kind;
        el.innerHTML = `<span class="slot-toast__dot"></span><span>${text}</span>`;
        // Restart the slide-in even when a toast is already up.
        void el.offsetWidth;
        el.classList.add('is-on');
        clearTimeout(slotToastTimer);
        slotToastTimer = setTimeout(() => el.classList.remove('is-on'), 3600);
    }

    // "Updated 12s ago" — keeps ticking between checks so the grid reads as watched, not frozen.
    function liveAgoText() {
        if (liveFailed) return 'Offline · showing last known';
        if (!liveCheckedAt) return 'Checking now…';
        const s = Math.round((Date.now() - liveCheckedAt) / 1000);
        if (s < 5) return 'Updated just now';
        if (s < 60) return `Updated ${s}s ago`;
        return `Updated ${Math.floor(s / 60)}m ago`;
    }
    setInterval(() => {
        const el = document.getElementById('slots-live-ago');
        if (el) el.textContent = liveAgoText();
    }, 1000);

    function slotState(slotId) {
        const slot = daySlots(selectedDate).find(s => s.id === slotId);
        if (!slot || dayRefusal || !slot.open) return 'closed';
        if (liveStates && liveStates[slot.id]) return liveStates[slot.id];
        return 'open';
    }

    // The slots a player can see for the picked day and sport: the admin's rows, minus the
    // ones that run for other sports and, today, the hours already gone (checkout refuses them).
    function visibleSlots() {
        const now = new Date();
        const nowMin = now.getHours() * 60 + now.getMinutes();
        return daySlots(selectedDate).filter(s =>
            (!s.sports.length || s.sports.includes(selectedSport)) &&
            (selectedDate !== todayYmd || s.start > nowMin)
        );
    }

    // Drawn marks for the slot grid — hand-built strokes, not an icon font.
    const SLOT_ART = {
        Morning: '<svg viewBox="0 0 28 28" fill="none" aria-hidden="true"><path d="M4 19h20" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M8.5 19a5.5 5.5 0 0 1 11 0" fill="#FDE68A" stroke="#F59E0B" stroke-width="1.8"/><path d="M14 7.5v2.6M7.2 10.3l1.8 1.8M20.8 10.3 19 12.1M3.8 15.2h2.4M21.8 15.2h2.4" stroke="#F59E0B" stroke-width="1.8" stroke-linecap="round"/><path d="M9 23h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" opacity=".35"/></svg>',
        Afternoon: '<svg viewBox="0 0 28 28" fill="none" aria-hidden="true"><circle cx="14" cy="14" r="5" fill="#FCD34D" stroke="#F59E0B" stroke-width="1.8"/><path d="M14 3.5v2.4M14 22.1v2.4M3.5 14h2.4M22.1 14h2.4M6.6 6.6l1.7 1.7M19.7 19.7l1.7 1.7M21.4 6.6l-1.7 1.7M8.3 19.7l-1.7 1.7" stroke="#F59E0B" stroke-width="1.8" stroke-linecap="round"/></svg>',
        Evening: '<svg viewBox="0 0 28 28" fill="none" aria-hidden="true"><path d="M8 25V9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><rect x="4.5" y="4" width="7" height="5" rx="1.2" fill="#E0E7FF" stroke="#4F46E5" stroke-width="1.6"/><path d="M12.5 8.5 22 17M12 6.5l11 4" stroke="#A5B4FC" stroke-width="1.5" stroke-linecap="round" stroke-dasharray="1 2.6"/><path d="M19.5 4.2a3.2 3.2 0 1 0 3.6 4.2 2.6 2.6 0 0 1-3.6-4.2Z" fill="#C7D2FE" stroke="#4F46E5" stroke-width="1.4" stroke-linejoin="round"/><path d="M4 25h8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
        lock: '<svg viewBox="0 0 16 16" fill="none" aria-hidden="true"><rect x="3.5" y="7" width="9" height="6.5" rx="1.6" stroke="currentColor" stroke-width="1.5"/><path d="M5.5 7V5.2a2.5 2.5 0 0 1 5 0V7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
        flame: '<svg viewBox="0 0 16 16" aria-hidden="true"><path d="M8.3 1.5c.4 2.2 3.7 3.6 3.7 7.3A4 4 0 0 1 4 9c0-1.6.8-2.7 1.7-3.4-.1 1.2.4 2 1.2 2.3C6.6 5.6 7.2 3.4 8.3 1.5Z" fill="currentColor"/></svg>',
    };

    function renderSlots(animate = false) {
        const container = document.getElementById('slots-grid-container');
        if (!container) return;
        if (!selectedSlots.length) renderDock(); // the stub follows the picked day

        const rate = currentRate();

        // Reflect the selected court's rate in the sticky booking card header.
        const rateEl = document.getElementById('rate-per-hour');
        if (rateEl) rateEl.innerText = '₹' + rate.toLocaleString();

        const note = (text) => `<p class="detail-card-panel__subtitle">${text}</p>`;

        // A venue that models no courts books the venue itself (checkout allows it); one that
        // has courts but none for this sport has nothing to sell.
        if (!selectedCourt && Object.keys(courtIds).length) {
            container.innerHTML = note('No court is open for this sport yet.');
            return;
        }
        if (dayRefusal) {
            container.innerHTML = note(dayRefusal);
            return;
        }

        const slots = visibleSlots();
        if (!slots.length) {
            container.innerHTML = note(selectedDate === todayYmd
                ? 'No more slots today. Pick another day.'
                : 'No slots on this day. Pick another day.');
            return;
        }

        // First read of this day/court still in flight: a shimmer, not yesterday's guesses.
        if (liveStates === null) {
            const n = Math.min(slots.length, 9);
            container.innerHTML = `
                <div class="sb-status is-checking"><span class="sb-status__count">Checking what’s open…</span></div>
                <div class="sb-keys sb-keys--skeleton">
                    ${'<div class="sb-key sb-key--skel"></div>'.repeat(n)}
                </div>`;
            return;
        }

        // Today only: how soon the first open slot starts — a real clock, not a sales line.
        const now = new Date();
        const nowMin = now.getHours() * 60 + now.getMinutes();
        const nextUp = selectedDate === todayYmd ? slots.find(s => slotState(s.id) === 'open') : null;
        const startsIn = (m) => m < 60 ? `in ${m} min` : `in ${Math.floor(m / 60)}h${m % 60 ? ' ' + (m % 60) + 'm' : ''}`;

        let idx = 0;
        // "6:00 AM" → "6 AM", "12:30 PM" → "12:30 PM": hours as people say them.
        const spoken = (t) => (t || '').replace(/^0/, '').replace(':00', '');
        const renderGroup = (title, group) => {
            if (!group.length) return '';
            const gOpen = group.filter(s => slotState(s.id) === 'open').length;
            const gShut = group.every(s => slotState(s.id) === 'closed');
            const from = spoken(group[0].time.split(' - ')[0]);
            const to = spoken((group[group.length - 1].time.split(' - ')[1] || ''));

            let groupHtml = `
                <section class="sb-group" id="sb-g-${title.toLowerCase()}">
                    <header class="sb-group__head">
                        <h4>${title}</h4>
                        <span class="sb-group__hours">${from}${to ? ' – ' + to : ''}</span>
                        <span class="sb-group__count${gOpen ? '' : ' is-none'}">${gOpen ? gOpen + ' open' : (gShut ? 'Closed' : 'Full')}</span>
                    </header>
                    <div class="sb-keys">
            `;

            group.forEach((slot) => {
                const state = slotState(slot.id);
                const isTaken = state !== 'open';
                const slotKey = `${selectedDate}_${selectedSport}_${selectedCourt}_${slot.id}`;
                const isSelected = selectedSlots.some(s => s.key === slotKey);
                const r = slotRate(slot.time);
                const isPeak = r > rate;
                const flip = slotFlips[slot.id];

                const [clock, ampm] = slot.time.split(' - ')[0].split(' ');
                const classes = ['slot-item', 'sb-key',
                    isTaken ? (state === 'closed' ? 'is-booked is-closed' : 'is-booked') : (isSelected ? 'is-selected' : 'is-open'),
                    isPeak && !isTaken ? 'is-peak' : '',
                    flip ? 'is-just-' + flip : '',
                ].filter(Boolean).join(' ');
                const onclickAttr = isTaken ? '' : `onclick="toggleSlot(this, ${slot.id}, ${r})"`;
                // The price is said once, in the line above. A key only carries one when
                // it differs (peak), or the word for why it can't be taken.
                const sub = state === 'booked' ? 'Booked'
                    : state === 'closed' ? 'Closed'
                    : isPeak ? '₹' + r.toLocaleString() : '';
                const tag = nextUp && nextUp.id === slot.id && slot.start - nowMin <= 240
                    ? `<span class="sb-key__soon">${startsIn(slot.start - nowMin)}</span>` : '';
                const style = animate ? ` style="--i:${idx++}"` : '';

                groupHtml += `
                    <div ${onclickAttr} class="${classes}${animate ? ' is-enter' : ''}" data-key="${slotKey}"${style}
                         role="button" aria-pressed="${isSelected}" aria-disabled="${isTaken}"
                         aria-label="${slot.time}, ${state === 'open' ? '₹' + r + (isPeak ? ' peak' : '') : state}">
                        <span class="sb-key__time"><span class="sb-key__clock">${clock.replace(/^0/, '')}</span><small>${ampm || ''}</small></span>
                        ${sub ? `<span class="sb-key__sub">${sub}</span>` : tag}
                        <svg class="sb-key__tick" viewBox="0 0 16 16" aria-hidden="true"><path d="M4.5 8.2l2.3 2.3 4.7-4.9" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </div>
                `;
            });

            groupHtml += `
                    </div>
                </section>
            `;
            return groupHtml;
        };

        // The price once, and the peak rate only where this court has one.
        const peakRates = slots.map(s => slotRate(s.time)).filter(v => v > rate);

        // Parts of the day, each a jump to its group with its real open count.
        const parts = [
            ['Morning', slots.filter(s => s.start < 12 * 60)],
            ['Afternoon', slots.filter(s => s.start >= 12 * 60 && s.start < 17 * 60)],
            ['Evening', slots.filter(s => s.start >= 17 * 60)],
        ];
        const peakWin = courtPeak[selectedCourt] && courtPeak[selectedCourt].start && courtPeak[selectedCourt].end
            ? ' · ' + spoken(courtPeak[selectedCourt].start).replace(/ (AM|PM)$/, (m, ap) => spoken(courtPeak[selectedCourt].end).endsWith(ap) ? '' : m) + '–' + spoken(courtPeak[selectedCourt].end)
            : '';
        // One part of the day on screen at a time. Keep the player's choice while it still
        // has slots; otherwise land on the first part with something open.
        const openIn = (g) => g.filter(s => slotState(s.id) === 'open').length;
        const current = parts.find(([n, g]) => n === activePart && g.length);
        if (!current) activePart = (parts.find(([, g]) => openIn(g)) || parts.find(([, g]) => g.length) || parts[0])[0];
        const activeIdx = parts.findIndex(([n]) => n === activePart);
        const pickedIn = (g) => g.some(s => selectedSlots.some(p => p.key === `${selectedDate}_${selectedSport}_${selectedCourt}_${s.id}`));

        const bar = `
            <div class="sb-seg" role="tablist" aria-label="Part of day" style="--i:${activeIdx}">
                <span class="sb-seg__thumb" aria-hidden="true"></span>
                ${parts.map(([name, g]) => {
                    const n = openIn(g);
                    const on = name === activePart;
                    return `<button type="button" role="tab" aria-selected="${on}" class="sb-seg__tab${on ? ' is-on' : ''}${g.length ? '' : ' is-none'}" ${g.length ? `onclick="selectPart('${name}')"` : 'disabled'}>
                        <b>${name}${pickedIn(g) ? '<i class="sb-seg__dot"></i>' : ''}</b>
                        <small>${!g.length ? 'Closed' : (n ? n + ' open' : 'Full')}</small>
                    </button>`;
                }).join('')}
            </div>
            <div class="sb-rateline${liveFailed ? ' is-offline' : ''}">
                ${liveFailed ? 'Couldn’t refresh · showing last known' : `<span><b>₹${rate.toLocaleString('en-IN')}</b> / hour</span>${peakRates.length ? `<span class="sb-rateline__peak">Peak ₹${Math.max(...peakRates).toLocaleString('en-IN')}${peakWin}</span>` : ''}`}
            </div>`;

        container.innerHTML = bar + renderGroup(activePart, parts[activeIdx][1]);
        // The flip flash plays once; the next render is calm again.
        slotFlips = {};
    }

    function toggleSlot(element, slotId, rate) {
        const slot = daySlots(selectedDate).find(s => s.id === slotId);
        if (!slot) return;
        const slotKey = `${selectedDate}_${selectedSport}_${selectedCourt}_${slot.id}`;
        const seg = document.querySelector(`[data-seg="${slotKey}"]`);
        element.classList.remove('is-enter', 'is-pop');
        if (element.classList.contains('is-selected')) {
            element.classList.remove('is-selected');
            element.classList.add('is-open');
            element.setAttribute('aria-pressed', 'false');
            if (seg) seg.classList.remove('is-picked');
            selectedSlots = selectedSlots.filter(s => s.key !== slotKey);
        } else {
            element.classList.remove('is-open');
            element.classList.add('is-selected');
            void element.offsetWidth;
            element.classList.add('is-pop');
            element.setAttribute('aria-pressed', 'true');
            if (seg) seg.classList.add('is-picked');
            selectedSlots.push({
                key: slotKey,
                date: selectedDate,
                sport: selectedSport,
                court: selectedCourt,
                slotId: slot.id,
                time: slot.time,
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

    // "2026-09-27" -> "Sun, 27 Sep" for the picked-slot pills, matching the date
    // strip. Built by hand: Intl now spells September "Sept" in en-GB/en-IN.
    // Parsed as local parts so the day never slides through a UTC conversion.
    function pillDay(ymd) {
        const [y, m, d] = String(ymd).split('-').map(Number);
        if (!y || !m || !d) return ymd;
        const dt = new Date(y, m - 1, d);
        return ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'][dt.getDay()] + ', ' + d + ' '
            + ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'][m - 1];
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
                        ${s.sport}${s.court ? ' • ' + s.court : ''}
                    </div>
                    <div class="selected-slot-item-pill__details">
                        <span class="selected-slot-item-pill__time">${s.time} <span class="selected-slot-item-pill__day">${pillDay(s.date)}</span></span>
                        <span class="selected-slot-item-pill__price">₹${s.price.toLocaleString()}</span>
                    </div>
                    <button onclick="removeSelectedSlot('${s.key}')" class="selected-slot-item-pill__remove">×</button>
                </div>
            `).join('');

            const subtotal = selectedSlots.reduce((sum, s) => sum + s.price, 0);
            const fee = venueFeeFor(subtotal);
            const tax = venueTaxFor(subtotal);
            const total = Math.round((subtotal + fee + tax) * 100) / 100;

            document.getElementById('calc-hours').innerText = slotsCount;
            document.getElementById('calc-subtotal').innerText = '₹' + subtotal.toLocaleString();
            document.getElementById('calc-fee-row').style.display = fee > 0 ? '' : 'none';
            document.getElementById('calc-fee').innerText = '₹' + fee.toLocaleString('en-IN');
            document.getElementById('calc-tax-row').style.display = tax > 0 ? '' : 'none';
            document.getElementById('calc-tax').innerText = '₹' + tax.toLocaleString('en-IN');
            document.getElementById('calc-total').innerText = '₹' + total.toLocaleString();

            calcBlock.style.display = 'block';
            bookBtn.disabled = false;
            bookBtn.className = 'book-now-button-widget is-ready';
            if (!isAuthenticated) {
                bookBtn.innerText = 'Sign in to Book (' + slotsCount + ' slot' + (slotsCount > 1 ? 's' : '') + ')';
            } else {
                bookBtn.innerText = 'Continue · ₹' + total.toLocaleString('en-IN');
            }
        }
        renderDock();
    }

    // Phone dock: the picks as a ticket stub. Times only — the bill lives on checkout.
    // Back-to-back slots on one court read as one range ("6:00 – 8:00 AM").
    function renderDock() {
        const dock = document.getElementById('co-dock');
        if (!dock) return;
        const go = document.getElementById('co-go');
        const mins = (t) => timeMin((t || '').trim());
        const clock = (m) => {
            const h24 = Math.floor((m % 1440) / 60), mm = m % 60;
            return { t: ((h24 % 12) || 12) + ':' + String(mm).padStart(2, '0'), ap: h24 < 12 ? 'AM' : 'PM' };
        };
        const picks = selectedSlots.map(p => {
            const [a, b] = p.time.split(' - ');
            const s = mins(a), e0 = mins(b);
            return { ...p, s, e: e0 != null && e0 <= s ? e0 + 1440 : e0 };
        }).sort((x, y) => (x.date + x.court).localeCompare(y.date + y.court) || x.s - y.s);

        // Merge touching slots on the same day + court.
        const ranges = [];
        picks.forEach(p => {
            const last = ranges[ranges.length - 1];
            if (last && last.date === p.date && last.court === p.court && last.e === p.s) { last.e = p.e; last.keys.push(p.key); }
            else ranges.push({ date: p.date, court: p.court, s: p.s, e: p.e, keys: [p.key] });
        });

        dock.classList.toggle('is-empty', !picks.length);
        dock.classList.toggle('is-multi', ranges.length > 1);
        go.disabled = !picks.length;

        const ymd = picks.length ? picks[0].date : selectedDate;
        const [y, m, d] = String(ymd).split('-').map(Number);
        const dt = new Date(y, (m || 1) - 1, d || 1);
        document.getElementById('co-dow').innerText = ['SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT'][dt.getDay()];
        document.getElementById('co-day').innerText = d || '—';
        document.getElementById('co-mon').innerText = ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'][(m || 1) - 1];

        const sport = picks.length ? picks[0].sport : selectedSport;
        const courts = [...new Set(picks.map(p => p.court).filter(Boolean))];
        const court = courts.length === 1 ? courts[0] : (picks.length ? null : selectedCourt);
        document.getElementById('co-what').innerText = [sport, court || (courts.length > 1 ? courts.length + ' courts' : '')].filter(Boolean).join(' · ');
        document.getElementById('co-court').innerHTML = courtDrawing(sport, court);

        const times = document.getElementById('co-times');
        const facts = document.getElementById('co-facts');
        if (!picks.length) {
            times.innerHTML = '<span class="co-stub__hint">Tap an open time above</span>';
            facts.innerText = '';
            return;
        }
        const days = new Set(ranges.map(r => r.date)).size;
        times.innerHTML = ranges.map(r => {
            const a = clock(r.s), b = clock(r.e);
            const day = days > 1 ? `<i>${pillDay(r.date).split(', ')[1]}</i>` : '';
            const crt = courts.length > 1 ? `<i>${r.court}</i>` : '';
            return `<span class="co-range">${a.t}${a.ap === b.ap ? '' : ' <small>' + a.ap + '</small>'} – ${b.t} <small>${b.ap}</small>${day}${crt}</span>`;
        }).join('');

        // Facts: total play time, and — today only — how soon it starts, from the real clock.
        const total = picks.reduce((n, p) => n + ((p.e ?? p.s) - p.s), 0);
        const len = total % 60 ? (total >= 60 ? Math.floor(total / 60) + ' hr ' + (total % 60) + ' min' : total + ' min') : (total / 60) + ' hr';
        let soon = '';
        if (ymd === todayYmd) {
            const now = new Date();
            const left = Math.min(...picks.filter(p => p.date === todayYmd).map(p => p.s)) - (now.getHours() * 60 + now.getMinutes());
            if (left > 0) soon = 'starts in ' + (left < 60 ? left + ' min' : Math.floor(left / 60) + 'h' + (left % 60 ? ' ' + (left % 60) + 'm' : ''));
        }
        facts.innerHTML = [len + ' of play', picks.length > 1 ? picks.length + ' slots' : '', soon ? `<b>${soon}</b>` : ''].filter(Boolean).join(' · ');
    }

    function clearPicks() {
        selectedSlots = [];
        updatePriceBreakdown();
        renderSlots();
    }

    // A small line drawing of the court being booked, with its name on it.
    function courtDrawing(sport, court) {
        const s = String(sport || '').toLowerCase();
        const label = court ? String(court).replace(/^(court|pitch|net|lane)\s*/i, '').slice(0, 6) || court : '';
        const line = 'fill="none" stroke="currentColor" stroke-width="1.3"';
        let marks;
        if (s.includes('football') || s.includes('soccer')) {
            marks = `<rect x="4" y="4" width="40" height="60" rx="3" ${line}/><path d="M4 34h40" ${line}/><circle cx="24" cy="34" r="7" ${line}/><path d="M15 4v9h18V4M15 64v-9h18v9" ${line}/>`;
        } else if (s.includes('cricket')) {
            marks = `<ellipse cx="24" cy="34" rx="20" ry="30" ${line}/><rect x="19" y="16" width="10" height="36" rx="1.5" ${line}/><path d="M17 21h14M17 47h14" ${line}/>`;
        } else if (s.includes('badminton')) {
            marks = `<rect x="6" y="4" width="36" height="60" rx="2" ${line}/><path d="M9 4v60M39 4v60M6 34h36M6 25h36M6 43h36M24 4v21M24 43v21M6 7h36M6 61h36" ${line} stroke-width="1"/>`;
        } else {
            // tennis / pickleball / anything racket-shaped
            marks = `<rect x="6" y="4" width="36" height="60" rx="2" ${line}/><path d="M6 34h36M6 22h36M6 46h36M24 4v18M24 46v18" ${line} stroke-width="1"/>`;
        }
        return `<svg viewBox="0 0 48 68" width="40" height="56">${marks}<path d="M4 34h40" stroke="currentColor" stroke-width="2" stroke-dasharray="2 1.6"/></svg>`
            + (label ? `<span>${label}</span>` : '');
    }

    // Checkout key: the pass slides out of the key, then the review page opens.
    function goCheckout(btn) {
        if (btn.disabled || btn.classList.contains('is-going')) return;
        try { navigator.vibrate && navigator.vibrate(12); } catch (_) { /* blocked */ }
        btn.classList.add('is-going');
        const reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        setTimeout(() => {
            btn.classList.remove('is-going');
            checkoutBooking();
        }, reduce ? 0 : 340);
    }
    renderDock();

    function toggleAccordion(bodyId, chevronId) {
        document.getElementById(bodyId).classList.toggle('is-collapsed');
        document.getElementById(chevronId).classList.toggle('is-collapsed');
    }

    // RATE VENUE: stars name themselves ("Very good"), and Post wakes up once one is picked.
    function setFormRating(rating) {
        currentFormRating = rating;
        document.querySelectorAll('#rating-selector .star-btn').forEach((star, idx) => {
            const on = idx < rating;
            star.classList.toggle('is-active', on);
            star.setAttribute('aria-checked', idx === rating - 1 ? 'true' : 'false');
            if (on && idx === rating - 1) {
                star.classList.remove('is-pop'); void star.offsetWidth; star.classList.add('is-pop');
            }
        });
        const word = document.getElementById('rvf-word');
        const picked = document.querySelectorAll('#rating-selector .star-btn')[rating - 1];
        if (word) word.textContent = picked ? picked.dataset.word : 'Tap a star';
        const go = document.getElementById('rvf-go');
        if (go) go.disabled = rating < 1;
        try { rating && navigator.vibrate && navigator.vibrate(8); } catch (_) { /* blocked */ }
    }

    // Saved on the server (VenueReviewService), then drawn into both review lists from
    // what the server returned — never from what was typed.
    async function submitReview(btn) {
        const err = document.getElementById('rvf-err');
        const text = (document.getElementById('review-comment')?.value || '').trim();
        if (currentFormRating < 1) return;
        const label = btn.innerText;
        btn.disabled = true;
        btn.innerText = 'Posting…';
        if (err) err.hidden = true;
        try {
            const res = await fetch('/gamehub/' + venueId + '/review', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ rating: currentFormRating, text }),
            });
            const j = await res.json().catch(() => ({}));
            if (!res.ok) {
                const msg = (j.errors && Object.values(j.errors)[0] && Object.values(j.errors)[0][0]) || j.message || 'Couldn’t post your rating. Try again.';
                throw new Error(res.status === 401 || res.status === 419 ? 'Sign in again to post your rating.' : msg);
            }
            drawMyReview(j.review, j.venue);
            btn.innerText = 'Update rating';
            const title = document.querySelector('.rvf__title');
            if (title) title.textContent = 'Your rating';
            document.dispatchEvent(new CustomEvent('mven:close'));
            reviewToast(j.review.updated ? 'Your rating is updated' : 'Thanks — your rating is live');
        } catch (e) {
            if (err) { err.textContent = e.message; err.hidden = false; }
            btn.innerText = label;
        } finally {
            btn.disabled = currentFormRating < 1;
        }
    }

    const escHtml = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    // Put the viewer's review at the top of both lists (replacing their earlier one) and
    // refresh the counts the page shows.
    function drawMyReview(r, venue) {
        if (!r.visible) return; // hidden by Haraan: saved, but it isn't shown to others
        const face = escHtml((r.name || '?').slice(0, 1).toUpperCase());
        const star = '<svg viewBox="0 0 24 24" width="13" height="13" fill="currentColor" aria-hidden="true"><path d="m12 17.3 6.2 3.7-1.6-7 5.4-4.7-7.1-.6L12 2 9.1 8.7 2 9.3l5.4 4.7-1.6 7z"></path></svg>';
        const played = r.played ? '<span class="mven__review-played">Played here</span>' : '';

        const wrap = document.getElementById('mven-reviews-wrap');
        const list = document.getElementById('mven-reviews-list');
        if (wrap && list) {
            list.querySelector('[data-mine]')?.remove();
            const el = document.createElement('div');
            el.className = 'mven__review is-new';
            el.dataset.mine = '1';
            el.innerHTML = `<div class="mven__review-head"><span class="mven__review-face">${face}</span>
                <span class="mven__review-who"><strong>${escHtml(r.name)} ${played}</strong><small>${escHtml(r.ago)}</small></span>
                <span class="mven__review-score">${star}<b>${r.rating}</b></span></div>
                ${r.text ? `<p class="mven__review-text">${escHtml(r.text)}</p>` : ''}`;
            list.prepend(el);
            wrap.hidden = false;
            requestAnimationFrame(() => el.classList.remove('is-new'));
        }
        const dlist = document.getElementById('reviews-list-container');
        if (dlist) {
            dlist.querySelector('[data-mine]')?.remove();
            const card = document.createElement('div');
            card.className = 'review-card';
            card.dataset.mine = '1';
            card.innerHTML = `<div class="review-card__header"><div class="review-user-info"><div class="review-user-avatar">${face}</div>
                <div><strong class="review-user-name">${escHtml(r.name)}</strong><span class="review-date">${escHtml(r.ago)}</span></div></div>
                <div class="review-star-rating">${[1, 2, 3, 4, 5].map((i) => `<span class="review-star ${i <= r.rating ? 'is-active' : ''}">★</span>`).join('')}</div></div>
                ${r.text ? `<p class="review-comment">${escHtml(r.text)}</p>` : ''}`;
            dlist.prepend(card);
        }
        if (venue) {
            const n = venue.reviews;
            document.querySelectorAll('[data-reviews-count]').forEach((e) => { e.textContent = n; });
            const badge = document.getElementById('reviews-count-badge');
            if (badge) badge.textContent = n + (n === 1 ? ' Review' : ' Reviews');
            const sum = document.getElementById('mven-rating-copy');
            if (sum && n > 0) {
                sum.innerHTML = `<div class="mven__rating-top"><strong>${Number(venue.rating).toFixed(1)}</strong></div>
                    <span class="mven__rating-sub">${n} ${n === 1 ? 'rating' : 'ratings'}</span>`;
            }
        }
    }

    function reviewToast(text) {
        const t = document.createElement('div');
        t.className = 'rvf-toast';
        t.innerHTML = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.2 4.2L19 7"/></svg>' + escHtml(text);
        document.body.appendChild(t);
        requestAnimationFrame(() => t.classList.add('is-on'));
        setTimeout(() => { t.classList.remove('is-on'); setTimeout(() => t.remove(), 300); }, 2600);
    }
    @if(!empty($venue->my_review))
        setFormRating({{ (int) $venue->my_review['rating'] }});
    @endif

    async function checkoutBooking() {
        if (selectedSlots.length === 0) {
            alert('Please select at least one slot first.');
            return;
        }

        // 1. Strict Authentication Check
        if (!isAuthenticated) {
            if (typeof window.openLoginModal === 'function') {
                window.openLoginModal();
            } else {
                const btn = document.getElementById('loginBtn') || document.querySelector('[data-login-open]');
                if (btn) btn.click();
                else window.location.href = '/login';
            }
            return;
        }

        openReview();
    }

    // Proceed on the review page: hold the slots, then Razorpay. The server re-prices
    // everything (rates, fee, coupon, tax) - the review's quote is only what it showed.
    async function startPayment(couponCode, bookBtn) {
        const originalText = bookBtn.innerText;
        bookBtn.disabled = true;
        bookBtn.innerText = 'Securing slots...';

        try {
            // 2. Reserve slots on backend and obtain Razorpay order parameters
            const res = await fetch('/gamehub/' + venueId + '/book', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    date: selectedDate,
                    sport: selectedSport,
                    court: selectedCourt,
                    court_id: courtIds[selectedCourt] ?? null,
                    slots: selectedSlots.map(s => ({ time: s.time, price: s.price })),
                    couponCode: couponCode || null,
                })
            });

            const data = await res.json();

            if (!res.ok || !data.ok) {
                alert(data.error || 'Could not reserve slots. Please try another slot.');
                bookBtn.disabled = false;
                bookBtn.innerText = originalText;
                // Most refusals mean a slot went meanwhile: back to the grid to re-pick.
                closeReview();
                loadAvailability();
                return;
            }

            // If 0-amount or free
            if (!data.requires_payment) {
                closeReview();
                showSuccessModal(data.reference, data.date, data.slots, data.total, data.breakdown);
                bookBtn.disabled = false;
                bookBtn.innerText = originalText;
                return;
            }

            // 3. Initiate Razorpay Gateway Checkout
            bookBtn.innerText = 'Opening payment...';

            const options = {
                key: data.payment.key,
                order_id: data.payment.orderId,
                amount: data.payment.amount,
                currency: data.payment.currency,
                name: data.payment.name || 'Haraan Sports Venue',
                description: data.payment.description || 'Court Slot Booking',
                prefill: data.payment.prefill || {},
                theme: { color: '#2563EB' },
                handler: async function (response) {
                    bookBtn.innerText = 'Verifying payment...';

                    try {
                        const verifyRes = await fetch('/gamehub/' + venueId + '/confirm', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            body: JSON.stringify({
                                razorpay_order_id: response.razorpay_order_id,
                                razorpay_payment_id: response.razorpay_payment_id,
                                razorpay_signature: response.razorpay_signature,
                            })
                        });

                        const verifyData = await verifyRes.json();

                        if (verifyRes.ok && verifyData.ok) {
                            closeReview();
                            showSuccessModal(
                                verifyData.reference || data.bookingRef,
                                data.date,
                                data.slots,
                                data.total,
                                data.breakdown
                            );
                        } else {
                            alert(verifyData.error || 'Payment verification failed. Please check your bookings page.');
                        }
                    } catch (vErr) {
                        console.error('Verification error:', vErr);
                        alert('Payment was received, but verification encountered a network delay. Please check your bookings page.');
                        window.location.href = '/bookings';
                    } finally {
                        bookBtn.disabled = false;
                        bookBtn.innerText = originalText;
                    }
                },
                modal: {
                    ondismiss: function () {
                        // Release hold
                        fetch('/gamehub/' + venueId + '/release', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            body: JSON.stringify({ razorpay_order_id: data.payment.orderId })
                        }).catch(() => {});
                        bookBtn.disabled = false;
                        bookBtn.innerText = originalText;
                        alert('Payment was cancelled. Your slot hold has been released.');
                        loadAvailability();
                    }
                }
            };

            const rzp = new Razorpay(options);
            rzp.on('payment.failed', function (resp) {
                bookBtn.disabled = false;
                bookBtn.innerText = originalText;
                const msg = resp && resp.error ? resp.error.description : 'Payment failed. Please try again.';
                alert(msg);
            });
            rzp.open();

        } catch (err) {
            console.error('Booking checkout error:', err);
            alert('A network error occurred while preparing your booking. Please try again.');
            bookBtn.disabled = false;
            bookBtn.innerText = originalText;
        }
    }

    /* ---- Review page: what you're booking, priced by the server, then Proceed ---- */
    let reviewCoupon = '';   // the code the last quote accepted
    let reviewSeq = 0;
    const inr = (n) => '₹' + Number(n || 0).toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
    const esc = (t) => String(t ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    function openReview() {
        const el = document.getElementById('vco');
        if (!el || !selectedSlots.length) return;
        reviewCoupon = '';
        const form = document.getElementById('vco-coupon-form');
        if (form) form.hidden = true;
        const input = document.getElementById('vco-coupon-input');
        if (input) input.value = '';
        setCouponMsg('');
        document.getElementById('vco-breakup').hidden = true;
        document.getElementById('vco-breakup-toggle').setAttribute('aria-expanded', 'false');
        renderReviewSummary();
        el.hidden = false;
        el.classList.remove('is-closing');
        el.querySelector('.vco__scroll').scrollTop = 0;
        document.body.classList.add('vco-locked');
        window.HaraanOverlay?.push('vco', closeReview);
        requote('');
    }

    function closeReview() {
        const el = document.getElementById('vco');
        if (!el || el.hidden || el.classList.contains('is-closing')) return;
        window.HaraanOverlay?.pop('vco');
        const done = () => {
            el.hidden = true;
            el.classList.remove('is-closing');
            document.body.classList.remove('vco-locked');
        };
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) { done(); return; }
        el.classList.add('is-closing');
        setTimeout(done, 200);
    }

    function renderReviewSummary() {
        const n = selectedSlots.length;
        document.getElementById('vco-sport').textContent = `${selectedSport} (${n})`;
        document.getElementById('vco-court').textContent = selectedCourt || 'Any court';
        const d = parseYmd(selectedDate);
        document.getElementById('vco-date').textContent =
            `${d.getDate()} ${MONTHS_LONG[d.getMonth()]} ${d.getFullYear()}, ${WEEKDAYS_LONG[d.getDay()]}`;
        document.getElementById('vco-slots').innerHTML = selectedSlots.map(s => `
            <li class="vco__slot">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><polyline points="12 7 12 12 15.5 14"></polyline></svg>
                <span class="vco__slot-time">${esc(s.time.replace(' - ', ' – '))}</span>
                <span class="vco__slot-price" data-slot-price="${esc(s.time)}">${inr(s.price)}</span>
                <button type="button" class="vco__slot-x" data-vco-remove="${esc(s.key)}" aria-label="Remove ${esc(s.time)}">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" aria-hidden="true"><line x1="17" y1="7" x2="7" y2="17"></line><line x1="7" y1="7" x2="17" y2="17"></line></svg>
                </button>
            </li>`).join('');
    }

    function setCouponMsg(text, ok = false) {
        const m = document.getElementById('vco-coupon-msg');
        if (!m) return;
        m.textContent = text;
        m.hidden = !text;
        m.classList.toggle('is-ok', ok);
    }

    function setProceed(label, enabled) {
        const btn = document.getElementById('vco-proceed');
        btn.disabled = !enabled;
        btn.textContent = label;
        document.getElementById('vco-foot-total').textContent = label.includes('₹') ? label.slice(label.indexOf('₹')) : '';
    }

    // Ask the server what this selection costs (with [code] when given).
    async function requote(code) {
        const seq = ++reviewSeq;
        const priceCard = document.getElementById('vco-price');
        priceCard.classList.add('is-loading');
        setProceed('Updating price…', false);
        try {
            const res = await fetch('/gamehub/' + venueId + '/book/quote', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({
                    date: selectedDate,
                    court_id: courtIds[selectedCourt] ?? null,
                    slots: selectedSlots.map(s => s.time),
                    couponCode: code || null,
                }),
            });
            const q = await res.json().catch(() => ({}));
            if (seq !== reviewSeq) return;
            if (!res.ok || !q.ok) {
                const msg = q.error || (res.status === 429 ? 'Too many tries. Wait a minute and try again.' : 'Could not price this booking. Please try again.');
                if (code) { setCouponMsg(msg); return requote(''); }
                document.getElementById('vco-price-error').textContent = msg;
                document.getElementById('vco-price-error').hidden = false;
                setProceed('Unavailable', false);
                return;
            }
            document.getElementById('vco-price-error').hidden = true;
            reviewCoupon = q.coupon && q.coupon.applied ? q.coupon.code : '';
            if (code && !reviewCoupon) setCouponMsg(q.coupon.message || 'This code isn’t valid.');
            else if (code) setCouponMsg('');
            renderPrice(q);
        } catch (e) {
            if (seq !== reviewSeq) return;
            document.getElementById('vco-price-error').textContent = 'You seem to be offline. Check your connection and try again.';
            document.getElementById('vco-price-error').hidden = false;
            setProceed('Unavailable', false);
        } finally {
            if (seq === reviewSeq) priceCard.classList.remove('is-loading');
        }
    }

    function renderPrice(q) {
        // Per-slot prices as the server rates them (peak hours included).
        (q.lines || []).forEach(l => {
            const cell = document.querySelector(`#vco-slots [data-slot-price="${CSS.escape(l.time)}"]`);
            if (cell) cell.textContent = inr(l.rate);
        });
        const n = (q.lines || []).length;
        document.getElementById('vco-court-price-label').textContent = `Court price (${n} slot${n === 1 ? '' : 's'})`;
        document.getElementById('vco-court-price').textContent = inr(q.subtotal);

        const charges = Number(q.fee || 0) + Number(q.tax || 0);
        document.getElementById('vco-fees-row').hidden = charges <= 0;
        document.getElementById('vco-fees').textContent = inr(charges);
        const feeLines = (q.fee_lines && q.fee_lines.length) ? q.fee_lines
            : (q.fee > 0 ? [{ label: 'Convenience fee', amount: q.fee }] : []);
        document.getElementById('vco-fee-lines').innerHTML = feeLines.map(l =>
            `<div class="vco__row vco__row--sub"><span>${esc(l.label)}</span><span>${inr(l.amount)}</span></div>`).join('');
        document.getElementById('vco-fees-title').textContent =
            (q.tax > 0 ? (feeLines.length ? 'Fees & taxes' : 'Taxes') : (feeLines.length === 1 ? feeLines[0].label : 'Fees'));
        document.getElementById('vco-tax-row').hidden = !(q.tax > 0);
        document.getElementById('vco-tax-label').textContent = q.tax_label || 'Tax';
        document.getElementById('vco-tax').textContent = inr(q.tax);

        const applied = !!reviewCoupon;
        document.getElementById('vco-discount-row').hidden = !applied;
        document.getElementById('vco-discount-code').textContent = reviewCoupon;
        document.getElementById('vco-discount').textContent = '−' + inr(q.discount);
        document.getElementById('vco-coupon-open').hidden = applied;
        document.getElementById('vco-coupon-applied').hidden = !applied;
        document.getElementById('vco-coupon-applied-code').textContent = reviewCoupon;
        document.getElementById('vco-coupon-applied-save').textContent = applied ? 'You save ' + inr(q.discount) : '';
        if (applied) document.getElementById('vco-coupon-form').hidden = true;

        document.getElementById('vco-total').textContent = inr(q.total);
        document.getElementById('vco-paynow').textContent = inr(q.total);
        const hold = document.getElementById('vco-hold');
        if (hold && q.hold_minutes) hold.textContent = `Your slot${n === 1 ? ' is' : 's are'} held for ${q.hold_minutes} minutes once you proceed.`;
        setProceed(q.total > 0 ? 'Proceed ' + inr(q.total) : 'Confirm booking', true);
    }

    document.addEventListener('click', (e) => {
        if (!e.target.closest('#vco')) return;
        // Touch feel, as in the slot sheet: a short tick on a real tap (Android only).
        const tap = e.target.closest('button');
        if (tap && !tap.disabled && navigator.vibrate && (!navigator.userActivation || navigator.userActivation.isActive)) {
            try { navigator.vibrate(tap.id === 'vco-proceed' ? 14 : 6); } catch (_) { /* blocked */ }
        }
        if (e.target.closest('[data-vco-close]')) { closeReview(); return; }

        const rm = e.target.closest('[data-vco-remove]');
        if (rm) {
            removeSelectedSlot(rm.dataset.vcoRemove);
            if (!selectedSlots.length) { closeReview(); return; }
            renderReviewSummary();
            requote(reviewCoupon);
            return;
        }
        if (e.target.closest('#vco-coupon-open')) {
            const form = document.getElementById('vco-coupon-form');
            form.hidden = !form.hidden;
            e.target.closest('#vco-coupon-open').setAttribute('aria-expanded', String(!form.hidden));
            if (!form.hidden) document.getElementById('vco-coupon-input').focus();
            return;
        }
        if (e.target.closest('#vco-coupon-remove')) { setCouponMsg(''); requote(''); return; }
        if (e.target.closest('#vco-breakup-toggle')) {
            const b = document.getElementById('vco-breakup');
            b.hidden = !b.hidden;
            e.target.closest('#vco-breakup-toggle').setAttribute('aria-expanded', String(!b.hidden));
            return;
        }
        if (e.target.closest('#vco-proceed')) {
            startPayment(reviewCoupon, document.getElementById('vco-proceed'));
        }
    });

    document.addEventListener('submit', (e) => {
        if (e.target.id !== 'vco-coupon-form') return;
        e.preventDefault();
        const code = document.getElementById('vco-coupon-input').value.trim();
        if (!code) { setCouponMsg('Enter a coupon code.'); return; }
        setCouponMsg('');
        requote(code);
    });

    let currentReceiptData = null;

    function showSuccessModal(refId, date, slots, total, breakdown) {
        const now = new Date();
        const bookedTimeStr = now.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) + ' ' + 
                              now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
        
        const finalRef = refId || ('HT-' + Math.floor(1000000000 + Math.random() * 9000000000));
        const finalDate = date || selectedDateDisplay || selectedDate;
        const sportName = (selectedSlots.length > 0 ? selectedSlots[0].sport : selectedSport).toUpperCase();
        const courtName = (selectedSlots.length > 0 ? selectedSlots[0].court : selectedCourt).toUpperCase();

        // The server's breakdown is what Razorpay charged; the local sum is only a fallback.
        const b = breakdown || {};
        const subtotal = b.subtotal != null ? Number(b.subtotal) : selectedSlots.reduce((sum, s) => sum + s.price, 0);
        const fee = b.convenienceFee != null ? Number(b.convenienceFee) : venueFeeFor(subtotal);
        const discount = b.discount != null ? Number(b.discount) : 0;
        const tax = b.tax != null ? Number(b.tax) : venueTaxFor(subtotal, discount);
        const taxLabel = b.taxLabel || document.getElementById('calc-tax-label')?.innerText || 'GST';
        const finalTotal = total != null ? Number(total) : (subtotal + fee - discount + tax);
        const savedSlots = [...selectedSlots];

        // Cache for Bluetooth / ESC-POS direct printing
        currentReceiptData = {
            refId: finalRef,
            bookedOn: bookedTimeStr,
            user: "{{ auth()->user()?->name ?? 'Guest User' }}",
            sport: sportName,
            court: courtName,
            date: finalDate,
            slots: savedSlots,
            rawSlots: slots,
            subtotalNum: subtotal,
            feeNum: fee,
            discountNum: discount,
            taxNum: tax,
            taxLabel: taxLabel,
            totalNum: finalTotal
        };

        // Populate receipt text fields
        const refEl = document.getElementById('tp-ref');
        if (refEl) refEl.innerText = finalRef;

        const barcodeEl = document.getElementById('tp-barcode-code');
        if (barcodeEl) barcodeEl.innerText = finalRef;

        const timeEl = document.getElementById('tp-time');
        if (timeEl) timeEl.innerText = bookedTimeStr;

        const sportEl = document.getElementById('tp-sport');
        if (sportEl) sportEl.innerText = sportName;

        const courtEl = document.getElementById('tp-court');
        if (courtEl) courtEl.innerText = courtName;

        const dateEl = document.getElementById('tp-date');
        if (dateEl) dateEl.innerText = finalDate;

        const subtotalEl = document.getElementById('tp-subtotal');
        if (subtotalEl) subtotalEl.innerText = '₹' + subtotal.toLocaleString('en-IN');

        const feeEl = document.getElementById('tp-fee');
        if (feeEl) feeEl.innerText = '₹' + fee.toLocaleString('en-IN');
        const feeRow = document.getElementById('tp-fee-row');
        if (feeRow) feeRow.style.display = fee > 0 ? '' : 'none';
        // Named after the fee when there is one ("FLOODLIGHT CHARGE"), else "FEES".
        const feeLabelEl = document.getElementById('tp-fee-label');
        const feeLines = Array.isArray(b.feeLines) ? b.feeLines : [];
        if (feeLabelEl) feeLabelEl.innerText = (feeLines.length === 1 ? feeLines[0].label : (feeLines.length > 1 ? 'Fees' : 'Convenience fee')).toUpperCase();

        const taxEl = document.getElementById('tp-tax');
        if (taxEl) taxEl.innerText = '₹' + tax.toLocaleString('en-IN');
        const taxLabelEl = document.getElementById('tp-tax-label');
        if (taxLabelEl) taxLabelEl.innerText = taxLabel.toUpperCase();
        const taxRow = document.getElementById('tp-tax-row');
        if (taxRow) taxRow.style.display = tax > 0 ? '' : 'none';

        const discountEl = document.getElementById('tp-discount');
        if (discountEl) discountEl.innerText = '−₹' + discount.toLocaleString('en-IN');
        const discountRow = document.getElementById('tp-discount-row');
        if (discountRow) discountRow.style.display = discount > 0 ? '' : 'none';

        const totalEl = document.getElementById('tp-total');
        if (totalEl) totalEl.innerText = '₹' + finalTotal.toLocaleString('en-IN');

        // Populate slot item rows
        const slotsContainer = document.getElementById('tp-slots');
        if (slotsContainer) {
            if (savedSlots.length > 0) {
                slotsContainer.innerHTML = savedSlots.map(s => `
                    <div class="receipt-slot-row">
                        <span>• ${s.time}</span>
                        <span class="receipt-bold">₹${Number(s.price).toLocaleString('en-IN')}</span>
                    </div>
                `).join('');
            } else if (slots) {
                slotsContainer.innerHTML = `
                    <div class="receipt-slot-row">
                        <span>• ${slots}</span>
                        <span class="receipt-bold">₹${subtotal.toLocaleString('en-IN')}</span>
                    </div>
                `;
            } else {
                slotsContainer.innerHTML = `
                    <div class="receipt-slot-row">
                        <span>• Reserved Slot</span>
                        <span class="receipt-bold">₹${subtotal.toLocaleString('en-IN')}</span>
                    </div>
                `;
            }
        }

        // Generate Scannable QR Code
        const qrBox = document.getElementById('tp-qr');
        if (qrBox) {
            qrBox.innerHTML = '';
            if (typeof QRCode !== 'undefined') {
                try {
                    new QRCode(qrBox, {
                        text: 'haraan:pass:' + finalRef + ':' + encodeURIComponent(finalDate),
                        width: 125,
                        height: 125,
                        colorDark: '#000000',
                        colorLight: '#ffffff',
                        correctLevel: QRCode.CorrectLevel.M
                    });
                } catch (qrErr) {
                    console.warn('QR code generation error:', qrErr);
                }
            }
        }

        // Show Modal and trigger physical paper ejection animation
        const modal = document.getElementById('success-modal');
        if (modal) modal.style.display = 'flex';

        const receiptPaper = document.getElementById('thermal-printable-receipt');
        if (receiptPaper) {
            receiptPaper.classList.remove('is-ejecting');
            void receiptPaper.offsetWidth; // Trigger DOM reflow for CSS keyframe animation restart
            receiptPaper.classList.add('is-ejecting');
        }

        // Reset scheduler state for future selections
        selectedSlots = [];
        updatePriceBreakdown();
        renderSlots();
    }

    function closeSuccessModal() {
        const modal = document.getElementById('success-modal');
        if (modal) modal.style.display = 'none';
        window.location.href = '/bookings';
    }

    // Standard Browser Print (Formatted via @media print for 58mm/80mm roll)
    function printThermalReceipt() {
        window.print();
    }

    // Bluetooth ESC/POS Direct Print (for turf partners / venue reception desk)
    async function printViaBluetooth() {
        const btBtn = document.getElementById('btn-bt-print');
        const originalContent = btBtn ? btBtn.innerHTML : '';

        if (!navigator.bluetooth) {
            alert('Web Bluetooth is supported on Google Chrome and Microsoft Edge (Android & Desktop).\n\nSwitching to standard print preview for you now.');
            window.print();
            return;
        }

        try {
            if (btBtn) {
                btBtn.disabled = true;
                btBtn.innerHTML = '<span class="printer-spinner"></span> Connecting...';
            }

            // Request Bluetooth device with typical POS thermal printer service UUIDs
            const device = await navigator.bluetooth.requestDevice({
                acceptAllDevices: true,
                optionalServices: [
                    '000018f0-0000-1000-8000-00805f9b34fb', // Standard POS Printer service
                    '49535343-fe7d-4ae5-8fa9-9fafd205e455', // ISSC Transparent Serial
                    'e7810a71-73ae-499d-8c15-faa9aef0c3f2',
                    '0000e0ff-3c55-4cc0-a4da-1600f6bd0c5a',
                    '0000ff00-0000-1000-8000-00805f9b34fb',
                    '0000fee7-0000-1000-8000-00805f9b34fb'
                ]
            });

            if (btBtn) btBtn.innerHTML = '<span class="printer-spinner"></span> Sending Ticket...';

            const server = await device.gatt.connect();

            // Locate writable characteristic
            let writeChar = null;
            const services = await server.getPrimaryServices();
            for (const s of services) {
                try {
                    const chars = await s.getCharacteristics();
                    for (const c of chars) {
                        if (c.properties.write || c.properties.writeWithoutResponse) {
                            writeChar = c;
                            break;
                        }
                    }
                } catch (ce) {
                    console.warn('Could not inspect service characteristics:', ce);
                }
                if (writeChar) break;
            }

            if (!writeChar) {
                throw new Error('No writable ESC/POS channel found on selected Bluetooth printer.');
            }

            // Build ESC/POS Byte Array
            const bytes = buildEscPosPayload();

            // Send in 64-byte chunks to avoid BLE MTU overflow
            const chunkSize = 64;
            for (let i = 0; i < bytes.length; i += chunkSize) {
                const chunk = bytes.slice(i, i + chunkSize);
                if (writeChar.writeValueWithoutResponse) {
                    await writeChar.writeValueWithoutResponse(chunk);
                } else {
                    await writeChar.writeValue(chunk);
                }
                await new Promise(r => setTimeout(r, 25));
            }

            if (btBtn) {
                btBtn.innerHTML = '✓ Printed Successfully!';
                setTimeout(() => {
                    btBtn.disabled = false;
                    btBtn.innerHTML = originalContent;
                }, 2500);
            }

        } catch (err) {
            console.error('Bluetooth ESC/POS Error:', err);
            if (btBtn) {
                btBtn.disabled = false;
                btBtn.innerHTML = originalContent;
            }
            if (err.name !== 'NotFoundError') {
                const fallback = confirm('Bluetooth printing note: ' + (err.message || 'Printer disconnected') + '.\n\nOpen standard print dialog instead?');
                if (fallback) {
                    window.print();
                }
            }
        }
    }

    // Generate Raw ESC/POS Command Byte Sequence
    function buildEscPosPayload() {
        const encoder = new TextEncoder();
        const parts = [];

        function pushBytes(arr) {
            parts.push(new Uint8Array(arr));
        }
        function pushText(str) {
            parts.push(encoder.encode(str));
        }

        // 1. Initialize printer: ESC @
        pushBytes([0x1B, 0x40]);

        // 2. Center align: ESC a 1
        pushBytes([0x1B, 0x61, 0x01]);

        // 3. Double-height & bold header: ESC ! 0x18
        pushBytes([0x1B, 0x21, 0x18]);
        pushText("HARAAN SPORTS\n");

        // Normal font: ESC ! 0x00
        pushBytes([0x1B, 0x21, 0x00]);
        pushText("--------------------------------\n");

        const venueTitle = @json($venue->title);
        pushText(venueTitle.toUpperCase() + "\n");
        pushText("Official Entry Pass\n");
        pushText("* * * * * * * * * * * * * * * *\n\n");

        // Left align: ESC a 0
        pushBytes([0x1B, 0x61, 0x00]);

        const r = currentReceiptData || {};
        pushText("PASS REF : " + (r.refId || 'N/A') + "\n");
        pushText("BOOKED ON: " + (r.bookedOn || '') + "\n");
        pushText("CUSTOMER : " + (r.user || 'Guest') + "\n");
        pushText("SPORT    : " + (r.sport || '') + "\n");
        pushText("COURT    : " + (r.court || '') + "\n");
        pushText("DATE     : " + (r.date || '') + "\n");
        pushText("--------------------------------\n");
        pushText("RESERVED SLOTS:\n");

        if (r.slots && r.slots.length > 0) {
            r.slots.forEach(s => {
                pushText(" - " + s.time + "  Rs." + s.price + "\n");
            });
        } else {
            pushText(" - " + (r.rawSlots || 'Reserved Slot') + "\n");
        }

        pushText("--------------------------------\n");
        pushText("SUBTOTAL    : Rs. " + (r.subtotalNum ? r.subtotalNum.toLocaleString('en-IN') : '0') + "\n");
        if (r.feeNum > 0) {
            pushText("CONV. FEE   : Rs. " + r.feeNum.toLocaleString('en-IN') + "\n");
        }
        if (r.discountNum > 0) {
            pushText("DISCOUNT    : -Rs. " + r.discountNum.toLocaleString('en-IN') + "\n");
        }
        if (r.taxNum > 0) {
            pushText((r.taxLabel || 'GST').toUpperCase().padEnd(12).slice(0, 12) + ": Rs. " + r.taxNum.toLocaleString('en-IN') + "\n");
        }
        pushText("================================\n");

        // Bold total: ESC E 1
        pushBytes([0x1B, 0x45, 0x01]);
        pushText("TOTAL PAID  : Rs. " + (r.totalNum ? r.totalNum.toLocaleString('en-IN') : '0') + "\n");
        pushBytes([0x1B, 0x45, 0x00]);

        pushText("STATUS      : PAID ONLINE (RAZORPAY)\n");
        pushText("--------------------------------\n\n");

        // Center align for footer
        pushBytes([0x1B, 0x61, 0x01]);
        pushText("TICKET: " + (r.refId || '') + "\n");
        pushText("Show this slip at entry gate\n\n");
        pushText("Thank you for playing with HARAAN!\n");
        pushText("haraan.app\n");
        pushText("--------------------------------\n\n\n\n");

        // Paper Cut: GS V 0
        pushBytes([0x1D, 0x56, 0x00]);

        let totalLength = 0;
        for (const p of parts) totalLength += p.length;
        const combined = new Uint8Array(totalLength);
        let offset = 0;
        for (const p of parts) {
            combined.set(p, offset);
            offset += p.length;
        }
        return combined;
    }

    // Initialize Scheduler selectors on load
    window.addEventListener('DOMContentLoaded', () => {
        renderDateStrip();
        selectSport(selectedSport);
        // Other players book too: re-check every 15s while the page is on screen, and the
        // moment the player comes back to the tab (a held phone wakes to a fresh grid).
        setInterval(() => { if (!document.hidden) loadAvailability(true); }, 15000);
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && Date.now() - liveCheckedAt > 5000) loadAvailability(true);
        });
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
    const foot = modal.querySelector('[data-mven-modal-foot]');
    const title = modal.querySelector('.mven__modal-title');
    // Where each widget came from, so it can go home when the sheet closes and
    // the desktop layout (and its JS) still finds it where it expects.
    const home = new Map();
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    let closeTimer = 0;

    const move = (n, into) => {
        if (!n) return;
        if (!home.has(n)) home.set(n, { parent: n.parentNode, next: n.nextSibling });
        into.appendChild(n);
    };
    const restore = () => {
        // Put every moved node back exactly where it was.
        for (const [node, at] of home) at.parent.insertBefore(node, at.next);
        home.clear();
    };

    const open = (nodes, label, footNodes = []) => {
        // Reopened mid slide-down: finish that close first so nothing is moved twice.
        if (closeTimer) { clearTimeout(closeTimer); closeTimer = 0; restore(); }
        title.textContent = label;
        nodes.forEach((n) => move(n, body));
        footNodes.forEach((n) => move(n, foot));
        modal.classList.remove('is-closing');
        modal.hidden = false;
        body.scrollTop = 0;
        document.body.classList.add('mven-locked');
        // Booking path: without a history entry, Back abandoned the venue page
        // mid-booking instead of closing this sheet. See HaraanOverlay in site.js.
        window.HaraanOverlay.push('mven', close);
    };
    const close = () => {
        if (modal.hidden || closeTimer) return;
        window.HaraanOverlay.pop('mven');
        const finish = () => {
            closeTimer = 0;
            modal.hidden = true;
            modal.classList.remove('is-closing');
            document.body.classList.remove('mven-locked');
            restore();
        };
        if (reduceMotion) { finish(); return; }
        // Let the sheet slide down before the widgets go home (matches the CSS).
        modal.classList.add('is-closing');
        closeTimer = setTimeout(finish, 200);
    };

    /* ---- Touch feel: a short tick on every pick inside the sheet. Android
           Chrome honours it; iOS has no web vibration and simply ignores it. -- */
    // Only after a real tap: Chrome refuses (and logs) vibrate without user activation.
    const tick = (ms) => {
        if (navigator.userActivation && !navigator.userActivation.isActive) return;
        try { navigator.vibrate && navigator.vibrate(ms); } catch { /* blocked */ }
    };
    modal.addEventListener('click', (e) => {
        const hit = e.target.closest('.slot-item, .date-pill, .court-pill, .sport-tab, .selected-slot-item-pill__remove, .dpick__cal-btn, .dpick__cell, .dpick__nav');
        if (!hit || hit.disabled || hit.classList.contains('is-booked')) return;
        tick(hit.classList.contains('slot-item') ? 12 : 6);
    });

    root.querySelectorAll('[data-mven-book]').forEach((b) => b.addEventListener('click', () => {
        open([document.getElementById('booking-widget')], 'Book a slot', [document.querySelector('.sticky-booking-card')]);
        // Bring the picked day into view — the strip can be scrolled past it.
        if (typeof centerActiveDate === 'function') centerActiveDate();
        // Opening the sheet is the moment to be sure: re-check unless we just did.
        if (typeof loadAvailability === 'function' && Date.now() - liveCheckedAt > 5000) loadAvailability(true);
    }));
    root.querySelector('[data-mven-rate]')?.addEventListener('click', () => {
        open([document.querySelector('.review-form-card')], 'Rate this venue');
    });
    modal.querySelectorAll('[data-mven-close]').forEach((b) => b.addEventListener('click', close));
    // A posted rating closes its sheet (submitReview dispatches this).
    document.addEventListener('mven:close', close);
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !modal.hidden) close(); });
})();
</script>
@endsection
