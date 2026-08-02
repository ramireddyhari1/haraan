@extends('site.layout')

@section('body_class', 'theme-minimal event-detail-page')
@section('footer_icon_primary', '#000000')

@section('content')
<style>
    /* Remove top margin/padding from the main container on this page */
    main.container {
        padding-top: 0 !important;
    }
    /* Page-scoped accent (MI-blue) for this event detail page */
    .district-event-page {
        --mi-accent: #0d6efd; /* MI-like blue */
        --mi-accent-lite: rgba(13,110,253,0.08);
    }

    /* Apply MI blue to primary headings and accents on this page */
    .district-event-page .dr-main-title {
        color: var(--mi-accent) !important;
    }

    .district-event-page .dr-section-title {
        color: var(--mi-accent) !important;
    }

    /* Make all H4 headings on this page use the MI blue (overrides inline styles) */
    .district-event-page h4 {
        color: var(--mi-accent) !important;
    }

    /* Replace gold stroke/fill used in some inline SVGs with MI blue on this page */
    .district-event-page svg[stroke="#E2B13C"],
    .district-event-page svg[fill="#E2B13C"] {
        stroke: var(--mi-accent) !important;
        fill: var(--mi-accent) !important;
    }

    .district-event-page .dr-tabs .dr-tab.active,
    .district-event-page .dr-tabs .dr-tab:hover {
        color: var(--mi-accent) !important;
        border-bottom: 2px solid var(--mi-accent) !important;
    }

    .district-event-page .dr-hero-badge {
        background: var(--mi-accent) !important;
        color: #fff !important;
        display: inline-block !important;
        padding: 6px 10px !important;
        border-radius: 6px !important;
        font-weight: 800 !important;
    }

    /* Header adjustments (page-scoped) - change black header elements to MI blue on this page only */
    body.event-detail-page .topbar .brand__text strong {
        color: var(--mi-accent) !important;
    }

    body.event-detail-page .topbar .topnav__link {
        color: var(--mi-accent) !important;
    }

    body.event-detail-page .topbar .topnav__link.is-active,
    body.event-detail-page .topbar .topnav__link:hover {
        color: var(--mi-accent) !important;
    }

    body.event-detail-page .topbar .topnav__link.is-active::after,
    body.event-detail-page .topbar .topnav__link:hover::after {
        background: var(--mi-accent) !important;
    }

    body.event-detail-page .topbar__actions .btn--solid {
        background: var(--mi-accent) !important;
        border-color: var(--mi-accent) !important;
        box-shadow: 0 10px 20px rgba(13,110,253,0.12) !important;
    }

    body.event-detail-page .location-pill__label strong {
        color: var(--mi-accent) !important;
    }

    /* Organizer card: apply MI-blue to name and stat values */
    .district-event-page .dr-organizer-card .dr-organizer-name {
        color: var(--mi-accent) !important;
    }

    .district-event-page .dr-organizer-card .dr-stat-value {
        color: var(--mi-accent) !important;
        font-weight: 800 !important;
    }

    .district-event-page .dr-organizer-card .dr-stat-label {
        color: #6b7280 !important; /* keep labels muted */
        font-weight: 500 !important;
    }
    
    /* Floating Back Button styling */
    .floating-left-btn {
        width: 44px !important;
        height: 44px !important;
        border: 1px solid var(--dr-border) !important;
        background: #ffffff !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        color: var(--dr-text) !important;
        text-decoration: none !important;
        z-index: 50 !important;
        transition: all 0.2s ease !important;
    }

    /* Floating/back button placement: fixed on viewport to avoid affecting header flow.
       Desktop: place below header; Large screens: nudge outside centered container. */
        .floating-left-btn {
            display: none !important;
        }

    .floating-left-btn:hover {
        background: #000000 !important;
        color: #ffffff !important;
        border-color: #000000 !important;
    }
    @media (min-width: 1440px) {
        .floating-left-btn {
            /* 50% - 650px is the left edge of the centered 1300px container */
            left: calc(50% - 650px - 64px) !important;
            top: 24px !important;
        }
    }

    /* Mobile: float over the hero banner, not the sticky site header above it.
       Absolute (relative to .container, which starts right below the header)
       instead of fixed-to-viewport, which used to sit on top of the logo. */
        @media (max-width: 1024px) {
            .floating-left-btn {
                display: block !important;
                position: absolute !important;
                left: 12px !important;
                top: 12px !important;
                z-index: 20 !important;
            }
        }

    /* Small elegant gap between header and event poster */
    .dr-hero-banner {
        margin-top: 24px !important;
        margin-bottom: 12px !important;
        border-radius: 16px !important;
        overflow: hidden !important;
    }

    /* Restore full color to event poster image */
    .dr-hero-banner img {
        filter: none !important;
        border-radius: 16px !important;
    }

    /* Tighten the info row spacing below the hero banner */
    .dr-info-row {
        margin-bottom: 16px !important;
        padding-bottom: 12px !important;
    }

    /* Shrink CHECK IN button to reduce title block height */
    .dr-checkin-btn {
        padding: 12px 28px !important;
        font-size: 14px !important;
        font-weight: 600 !important;
        border-radius: 14px !important;
        background: var(--mi-accent, #121620) !important;
        color: #ffffff !important;
        border: none !important;
        text-transform: none !important;
        letter-spacing: -0.01em !important;
        cursor: pointer !important;
        transition: background-color 0.2s ease !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
    }

    /* Reduce tab bar spacing and item gaps */
    .dr-tabs {
        gap: 24px !important;
        margin-bottom: 16px !important;
    }

    .dr-tab {
        padding: 8px 0 !important;
    }

    /* Tighten the main content layout and columns gap */
    .dr-content-grid {
        gap: 28px !important;
    }

    .dr-section-title {
        margin-bottom: 12px !important;
    }

    .dr-artist-item {
        padding: 12px 0 !important;
    }

    /* Decrease font size of the main title */
    .dr-main-title {
        font-size: 32px !important;
        font-weight: 800 !important;
        letter-spacing: -0.03em !important;
        line-height: 1.25 !important;
        color: #121620 !important;
    }

    /* Ensure mobile header pieces are hidden on desktop widths and
       desktop nav is visible — page-scoped safety net for header issues. */
    @media (min-width: 1025px) {
        .mobile-action-buttons,
        .mobile-menu-toggle,
        .mobile-nav,
        .mobile-nav-backdrop {
            display: none !important;
        }

        .topnav {
            display: flex !important;
            position: absolute !important;
            left: 50% !important;
            transform: translateX(-50%) !important;
        }
    }

    /* =====================================================================
       MOBILE PREMIUM PASS (≤1024px): an immersive, app-style detail screen.
       Hide the home chrome, full-bleed hero with the title overlaid on a
       gradient, a clean meta row, and a sticky booking bar.
       ===================================================================== */
    @media (max-width: 1024px) {
        /* Reclaim the whole viewport: the home topbar is noise on a detail page. */
        body.event-detail-page .topbar { display: none !important; }
        /* Native feel: no accidental pull-to-refresh on the hero, no grey tap
           flash (we provide our own :active feedback below). */
        body.event-detail-page { overscroll-behavior-y: none; -webkit-tap-highlight-color: transparent; }
        /* Page scroll is locked while a bottom sheet is open (JS toggles this). */
        body.event-detail-page.dr-lock { overflow: hidden; }

        /* Press feedback: every tappable surface compresses slightly on touch. */
        .dr-mcard, .dr-mrow__cta, .dr-book-bar__btn, .dr-tix__cta, .dr-stepper button,
        .floating-left-btn, .floating-right-btn {
            transition: transform 0.12s ease !important;
        }
        .dr-mcard:active, .dr-mrow__cta:active,
        .dr-book-bar__btn:active:not(:disabled), .dr-tix__cta:active:not(:disabled),
        .dr-stepper button:active,
        .floating-left-btn:active, .floating-right-btn:active {
            transform: scale(0.96) !important;
        }

        /* Section reveals: JS tags sheet sections with .dr-reveal and a one-shot
           IntersectionObserver flips .is-in as they enter the viewport. */
        .dr-reveal { opacity: 0; transform: translateY(12px); transition: opacity 0.4s ease, transform 0.4s ease; }
        .dr-reveal.is-in { opacity: 1; transform: none; }

        /* Book bar starts off-screen and slides in once the title scrolls away
           (JS toggles .is-vis); it also ducks under any open bottom sheet. */
        .dr-book-bar { transform: translateY(110%); transition: transform 0.3s cubic-bezier(0.2, 0.8, 0.2, 1); }
        .dr-book-bar.is-vis { transform: none; }
        body.dr-lock .dr-book-bar { transform: translateY(110%); }

        /* Lineup rail: fade the edges so it visibly continues off-screen. */
        .dr-lineup__rail {
            -webkit-mask-image: linear-gradient(90deg, transparent 0, #000 28px, #000 calc(100% - 28px), transparent 100%);
            mask-image: linear-gradient(90deg, transparent 0, #000 28px, #000 calc(100% - 28px), transparent 100%);
        }

        /* Gallery: the stacked full-width grid is desktop-only; mobile gets a
           compact swipeable rail as the sheet's closing section. */
        .district-event-page .dr-gallery-desk { display: none !important; }
        .dr-mgal { padding: 0 20px; margin-top: 26px; }
        .dr-mgal__count { font-size: 12px; font-weight: 700; color: #94A3B8; letter-spacing: 0; margin-left: 6px; }
        .dr-mgal__rail {
            display: flex; gap: 10px; overflow-x: auto;
            scroll-snap-type: x mandatory; -webkit-overflow-scrolling: touch;
            margin: 0 -20px; padding: 2px 20px; scrollbar-width: none;
            -webkit-mask-image: linear-gradient(90deg, transparent 0, #000 20px, #000 calc(100% - 20px), transparent 100%);
            mask-image: linear-gradient(90deg, transparent 0, #000 20px, #000 calc(100% - 20px), transparent 100%);
        }
        .dr-mgal__rail::-webkit-scrollbar { display: none; }
        .dr-mgal__item {
            flex: 0 0 68vw; height: 190px; scroll-snap-align: start;
            border-radius: 18px; overflow: hidden; padding: 0; cursor: pointer;
            border: 1px solid #E2E8F0; background: #F4F7FB;
        }
        .dr-mgal__item img { width: 100%; height: 100%; object-fit: cover; display: block; }
        /* Venue ambiance — the venue's own photos off its Google listing, sitting
           just above the map. Same swipeable rail as the Gallery so the page has
           one scrolling idiom, but tiles are figures (not lightbox buttons): each
           carries the contributor credit Google requires to be shown with it. */
        .dr-amb { padding: 0 20px; margin-top: 26px; }
        .dr-amb__src { font-size: 12px; font-weight: 700; color: #94A3B8; letter-spacing: 0; margin-left: 6px; }
        .dr-amb__rail {
            display: flex; gap: 10px; overflow-x: auto;
            scroll-snap-type: x mandatory; -webkit-overflow-scrolling: touch;
            margin: 0 -20px; padding: 2px 20px; scrollbar-width: none;
            -webkit-mask-image: linear-gradient(90deg, transparent 0, #000 20px, #000 calc(100% - 20px), transparent 100%);
            mask-image: linear-gradient(90deg, transparent 0, #000 20px, #000 calc(100% - 20px), transparent 100%);
        }
        .dr-amb__rail::-webkit-scrollbar { display: none; }
        .dr-amb__item {
            position: relative; margin: 0; padding: 0; cursor: pointer;
            flex: 0 0 68vw; height: 190px; scroll-snap-align: start;
            border-radius: 18px; overflow: hidden;
            border: 1px solid #E2E8F0; background: #F4F7FB;
        }
        .dr-amb__item img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .dr-amb__cap {
            position: absolute; left: 0; right: 0; bottom: 0; padding: 18px 12px 8px;
            font-size: 10.5px; font-weight: 600; color: rgba(255,255,255,0.92);
            background: linear-gradient(transparent, rgba(0,0,0,0.72));
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        /* Full-bleed: both nested .containers carry 16px side MARGINS from the
           site stylesheet (not just padding) — zero them or the hero/sheet
           floats 32px off each edge. */
        body.event-detail-page main.container { padding: 0 !important; margin: 0 !important; max-width: 100% !important; width: 100% !important; }
        .district-event-page .container { padding: 0 !important; margin: 0 !important; max-width: 100% !important; width: 100% !important; }

        /* Poster hero — full colour; the title lives on the sheet, so no heavy scrim. */
        .district-event-page .dr-card-body { padding: 0 !important; background: #F4F7FB; }
        /* Sticky hero: the poster pins to the top and the white sheet scrolls
           OVER it (app-style). Compositor-driven — no JS scroll handlers.
           NOTE: ancestors use overflow-x: clip (not hidden) below; hidden
           would silently kill position: sticky. Dark background = the scrim
           and floating buttons sit on something while the image decodes. */
        .district-event-page .dr-hero-banner {
            position: sticky; top: 0; z-index: 0;
            margin: 0 !important;
            /* Same 3:4 shape as the event card + the recommended 1080×1440
               poster — a correctly sized poster shows WITHOUT any crop.
               Capped so short phones aren't all poster. */
            height: auto !important; aspect-ratio: 3 / 4;
            min-height: 0; max-height: 64vh !important;
            border: none !important; border-radius: 0 !important;
            background: #121620;
        }
        .district-event-page .dr-hero-banner img {
            border-radius: 0 !important; width: 100%; height: 100%; object-fit: cover;
        }
        /* Subtle TOP scrim only — enough for the floating buttons; poster stays bright. */
        .district-event-page .dr-hero-banner::after {
            content: ''; position: absolute; inset: 0; pointer-events: none;
            background: linear-gradient(180deg, rgba(4,8,15,0.32) 0%, rgba(4,8,15,0) 26%);
        }

        /* No countdown badge on the mobile hero — it covered the poster art and
           the date already sits under the title. Desktop keeps it. */
        .district-event-page .dr-hero-badge { display: none !important; }

        /* Hero pager: swipe through the event's photos. Native scroll-snap —
           compositor-driven, no JS in the scroll path (dots update via IO). */
        .district-event-page .dr-hero__rail {
            display: flex; height: 100%;
            overflow-x: auto; scroll-snap-type: x mandatory; -webkit-overflow-scrolling: touch;
            scrollbar-width: none; overscroll-behavior-x: contain;
        }
        .district-event-page .dr-hero__rail::-webkit-scrollbar { display: none; }
        .district-event-page .dr-hero__slide { flex: 0 0 100%; scroll-snap-align: start; }
        .dr-hero__dots {
            position: absolute; left: 0; right: 0; bottom: 40px; z-index: 5;
            display: flex; justify-content: center; gap: 6px; pointer-events: none;
        }
        .dr-hero__dot {
            width: 6px; height: 6px; border-radius: 999px;
            background: rgba(255,255,255,0.45); box-shadow: 0 1px 4px rgba(0,0,0,0.3);
            transition: width 0.25s ease, background 0.25s ease;
        }
        .dr-hero__dot.is-on { width: 18px; background: #ffffff; }

        /* White content sheet overlaps the poster with a big rounded top curve. */
        .district-event-page .dr-sheet {
            position: relative; z-index: 4;
            margin-top: -26px;
            background: #ffffff;
            border-radius: 28px 28px 0 0;
            padding: 22px 0 96px;
        }

        /* Floating circular buttons over the poster (back left, share right). */
        .floating-left-btn, .floating-right-btn {
            border-radius: 50% !important; width: 40px !important; height: 40px !important;
            top: 16px !important;
            background: rgba(255,255,255,0.92) !important; border: none !important;
            backdrop-filter: blur(6px);
            box-shadow: 0 4px 14px rgba(0,0,0,0.18) !important;
            display: flex !important; align-items: center; justify-content: center;
            color: #121620; z-index: 30;
        }
        .floating-left-btn { left: 16px !important; }
        .floating-right-btn { position: absolute !important; right: 16px !important; cursor: pointer; }

        /* Identity row + title + date now sit on the white sheet (dark text). */
        .district-event-page .dr-info-row {
            position: relative; z-index: 2;
            margin: 0 0 6px !important; padding: 0 20px !important;
            flex-direction: column; align-items: flex-start;
            border-bottom: none !important;
        }
        /* App parity (EventIdentityRow): category hugs the left, rating sits at
           the right edge. */
        .dr-idrow { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 12px; }
        .dr-cat-pill {
            font-size: 11px; font-weight: 800; letter-spacing: 0.04em; text-transform: uppercase;
            color: #2563EB; background: rgba(37,99,235,0.10); padding: 5px 11px; border-radius: 999px;
        }
        .dr-rate-pill {
            display: inline-flex; align-items: center; gap: 4px;
            font-size: 12px; font-weight: 800; color: #0F172A;
            background: #F4F7FB; border: 1px solid #E2E8F0; padding: 4px 9px; border-radius: 999px;
        }
        .dr-rate-pill i { color: #F5A623; font-style: normal; }
        body.event-detail-page .dr-main-title {
            color: #121620 !important;
            font-size: 24px !important; line-height: 1.24 !important;
            letter-spacing: -0.02em !important; margin-bottom: 8px !important;
            text-shadow: none;
        }
        .dr-date-line { display: block; margin: 0; font-size: 13px; font-weight: 600; color: #64748B; }
        /* Old overlay meta is replaced by the sheet identity + date line. */
        .district-event-page .dr-info-row .dr-meta-text,
        .district-event-page .dr-info-row .dr-meta-chips { display: none !important; }
        .district-event-page .dr-info-row .dr-checkin-btn { display: none !important; }

        /* Single continuous scroll — drop the tabs, show both panes stacked (app parity). */
        .district-event-page .dr-tabs { display: none !important; }
        .district-event-page #pane-know { display: block !important; margin-top: 8px !important; }
        .district-event-page .dr-tab-pane { padding: 0 20px !important; }

        /* Venue map card: stack (map on top, address below) instead of a cramped
           45/55 split that squeezes the address at 375px. */
        a[aria-label="Open venue location in Maps"] { flex-direction: column !important; height: auto !important; }
        a[aria-label="Open venue location in Maps"] > div { width: 100% !important; }
        a[aria-label="Open venue location in Maps"] > div:first-child { height: 132px !important; }

        /* Kill horizontal overflow: the desktop 2-col grid + a fixed-layout
           organizer card were forcing tracks wider than the viewport and
           clipping body text. minmax(0,1fr) + min-width:0 lets everything shrink. */
        .district-event-page .container,
        .district-event-page .dr-card-body { max-width: 100%; overflow-x: clip; }
        .district-event-page .dr-content-grid {
            grid-template-columns: minmax(0, 1fr) !important;
            gap: 22px !important;
        }
        .district-event-page .dr-content-grid > div,
        .district-event-page .dr-content-grid section { min-width: 0 !important; }
        .district-event-page .dr-description { overflow-wrap: anywhere; min-width: 0; }

        /* Overview: show ~6 lines, then a soft fade + "Read more" toggle.
           JS sets the exact max-height and only reveals the button when the
           copy actually overflows (short descriptions stay fully visible). */
        .district-event-page .dr-about-wrap.is-clamped .dr-description { position: relative; overflow: hidden; }
        .district-event-page .dr-about-wrap.is-clamped .dr-description::after {
            content: ''; position: absolute; left: 0; right: 0; bottom: 0; height: 62px;
            background: linear-gradient(rgba(255,255,255,0), #fff 92%); pointer-events: none;
        }
        .district-event-page .dr-readmore {
            display: inline-flex; align-items: center; gap: 6px; margin-top: 12px; padding: 0;
            background: none; border: 0; cursor: pointer; color: #2563EB;
            font: inherit; font-size: 14px; font-weight: 700; letter-spacing: -0.01em;
        }
        /* The `display` above outranks the UA's `[hidden]{display:none}`, so short
           descriptions were showing a "Read more" that toggled nothing. */
        .district-event-page .dr-readmore[hidden] { display: none; }
        .district-event-page .dr-readmore:active { opacity: 0.65; }
        .district-event-page .dr-readmore svg { width: 16px; height: 16px; transition: transform 0.2s ease; }
        .district-event-page .dr-readmore.is-open svg { transform: rotate(180deg); }

        /* Info row: stretch children to full width so the title wraps instead of
           shrink-wrapping to its longest line (flex-column + flex-start bug). */
        .district-event-page .dr-info-row { align-items: stretch !important; }
        .district-event-page .dr-info-row > div { width: 100%; min-width: 0; }
        body.event-detail-page .dr-main-title { width: 100%; overflow-wrap: anywhere; }

        /* Organizer card: stack on mobile (its desktop flex has fixed inner widths). */
        .district-event-page .dr-organizer-card {
            flex-direction: column !important; align-items: flex-start !important;
            gap: 16px !important; min-height: 0 !important; padding: 18px !important;
        }
        .district-event-page .dr-organizer-card > * { min-width: 0 !important; max-width: 100% !important; }

        /* App layout: date/venue live in the metadata cards below, not on the hero. */
        .district-event-page .dr-info-row .dr-meta-chips { display: none !important; }

        /* Metadata cards (mirror the app EventMetadataCards) */
        .dr-mmeta { padding: 0 20px; margin: 6px 0 20px; }
        .dr-mcards { display: flex; gap: 8px; }
        .dr-mcard {
            flex: 1; min-width: 0; display: flex; flex-direction: column; align-items: center; gap: 6px;
            background: #F4F7FB; border: 1px solid #E2E8F0; border-radius: 16px;
            padding: 12px 6px; text-decoration: none; text-align: center;
        }
        .dr-mcard__ico {
            width: 32px; height: 32px; border-radius: 9px; display: grid; place-items: center;
            background: rgba(37, 99, 235, 0.10);
        }
        .dr-mcard__ico svg { width: 18px; height: 18px; color: #2563EB; }
        .dr-mcard strong {
            font-size: 13.5px; font-weight: 700; color: #0F172A;
            max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        /* Same clamp as strong: a long locality must never wrap and make the
           three cards different heights. */
        .dr-mcard small {
            font-size: 11px; color: #94A3B8;
            max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        .dr-mcard__link { color: #2563EB !important; font-weight: 600; }

        /* App parity: section titles are dark and tight (HaraanTypography.SectionTitle),
           not the page-wide MI blue used on desktop. */
        .district-event-page .dr-section-title {
            color: #121620 !important;
            font-size: 17px !important; font-weight: 800 !important;
            letter-spacing: -0.02em !important; text-transform: none !important;
        }
        .district-event-page h4 { color: #121620 !important; }

        /* Date line under the title is accent-blue like the app EventHeader. */
        .dr-date-line { color: #2563EB !important; font-weight: 700 !important; }

        /* The desktop organizer card is replaced by an app-style row card in
           .dr-mobrows. The venue map card (live iframe or the fallback texture
           card) now stays visible on mobile too — it's the single venue block,
           so the old plain Venue row was removed. */
        .district-event-page .dr-organizer-desk { display: none !important; }

        /* App-style compact row cards: Organizer + Venue */
        .dr-mobrows { display: flex; flex-direction: column; gap: 22px; margin-top: 24px; }
        .dr-mobrows h3 { margin: 0 0 10px; }
        /* Organizer: ONE row — identity left, Follow right. Deliberately flat and
           tight. The previous version was a tall bordered card with a stacked
           stat strip and a full-width tinted CTA, which is a lot of chrome around
           a single link and reads as boilerplate. Meta collapses to one muted
           line so the row stays two lines tall whatever the host has filled in. */
        .dr-org { display: flex; align-items: center; gap: 12px; }
        .dr-org__who {
            display: flex; align-items: center; gap: 12px;
            flex: 1; min-width: 0; text-decoration: none; color: inherit;
        }
        .dr-org__ava {
            flex: 0 0 46px; width: 46px; height: 46px; border-radius: 50%;
            display: grid; place-items: center; overflow: hidden;
            font-size: 18px; font-weight: 800; color: #fff;
            background: linear-gradient(140deg, #2563EB, #1E3FA8);
        }
        .dr-org__ava img { width: 100%; height: 100%; object-fit: cover; }
        .dr-org__id { min-width: 0; flex: 1; display: flex; flex-direction: column; gap: 2px; }
        .dr-org__id strong {
            display: flex; align-items: center; gap: 5px;
            font-size: 15.5px; font-weight: 750; letter-spacing: -0.01em; color: #0F172A;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .dr-org__id strong svg { width: 15px; height: 15px; color: #2563EB; flex-shrink: 0; }
        .dr-org__id small {
            font-size: 12.5px; color: #6B7280;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        /* Outline until you're following, solid-tinted after — the state change is
           the whole point of the control, so it has to be visible at a glance. */
        .dr-org__follow {
            flex: none; display: inline-flex; align-items: center; justify-content: center;
            padding: 9px 18px; border-radius: 999px; cursor: pointer;
            border: 1.5px solid #2563EB; background: transparent; color: #2563EB;
            font-size: 13px; font-weight: 700; font-family: inherit; text-decoration: none;
            white-space: nowrap;
        }
        .dr-org__follow.is-on { background: rgba(37, 99, 235, 0.10); border-color: transparent; color: #1E40AF; }
        /* Shown instead of Follow when the host has no public page to follow —
           quieter than the blue outline, because reaching support is a fallback,
           not the thing we want people to do. */
        .dr-org__contact {
            flex: none; display: inline-flex; align-items: center; justify-content: center;
            padding: 9px 18px; border-radius: 999px;
            border: 1px solid #E2E8F0; background: #F4F7FB; color: #475569;
            font-size: 13px; font-weight: 700; text-decoration: none; white-space: nowrap;
        }
        .dr-mrow {
            display: flex; align-items: center; gap: 12px;
            background: #F4F7FB; border: 1px solid #E2E8F0; border-radius: 16px; padding: 14px;
        }
        .dr-mrow__ava, .dr-mrow__ico {
            flex: 0 0 44px; width: 44px; height: 44px; display: grid; place-items: center;
            background: rgba(37, 99, 235, 0.12); color: #2563EB;
        }
        .dr-mrow__ava {
            border-radius: 50%; font-size: 18px; font-weight: 800; overflow: hidden;
        }
        .dr-mrow__ava img { width: 100%; height: 100%; object-fit: cover; }
        .dr-mrow__ico { border-radius: 12px; }
        .dr-mrow__ico svg { width: 22px; height: 22px; }
        .dr-mrow__txt { min-width: 0; flex: 1; display: flex; flex-direction: column; gap: 2px; }
        .dr-mrow__txt strong {
            display: inline-flex; align-items: center; gap: 4px;
            font-size: 15px; font-weight: 700; color: #0F172A;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .dr-mrow__txt strong svg { width: 15px; height: 15px; color: #2563EB; flex-shrink: 0; }
        .dr-mrow__txt small {
            font-size: 13px; color: #64748B;
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
        }
        .dr-mrow__cta {
            flex-shrink: 0; display: inline-flex; align-items: center; gap: 6px;
            background: rgba(37, 99, 235, 0.10); color: #2563EB; text-decoration: none;
            font-size: 12.5px; font-weight: 700; padding: 10px 12px; border-radius: 12px;
        }
        .dr-mrow__cta svg { width: 15px; height: 15px; }

        /* Important Information — bordered card like the app (EventImportantInfoCard),
           not a bare bullet list. */
        .dr-impinfo { background: #F4F7FB; border: 1px solid #E2E8F0; border-radius: 16px; padding: 18px; }
        .dr-impinfo .dr-section-title { font-size: 15px !important; margin-bottom: 10px !important; }
        .dr-impinfo ul { font-size: 13.5px !important; line-height: 1.8 !important; color: #64748B !important; }

        /* App parity: no fabricated fallback policy cards — the app renders
           nothing when the host authored nothing. */
        .dr-know-fallback { display: none !important; }

        /* App parity: info notes appear once (Important Information card), so the
           Highlights section that repeats them is desktop-only. */
        .dr-highlights { display: none !important; }

        /* Lineup: centre-snapping rail with neighbours peeking in (the app's
           coverflow, minus scroll-linked transforms — see porting notes). */
        .dr-lineup-desk { display: none !important; }
        .dr-lineup__rail {
            display: flex; gap: 12px; overflow-x: auto;
            scroll-snap-type: x mandatory; -webkit-overflow-scrolling: touch;
            margin: 0 -20px; padding: 2px 44px; scrollbar-width: none;
        }
        .dr-lineup__rail::-webkit-scrollbar { display: none; }
        /* App card width: full width minus the pager's 64dp content padding
           per side (flex-basis % would resolve against the rail's content box,
           which under-sizes the card). */
        .dr-lineup__card { flex: 0 0 calc(100vw - 128px); height: 300px; scroll-snap-align: center; }

        /* Schedule bottom sheet (app EventScheduleSheet) — opened from the
           "Doors Open" metadata card when the admin authored a run-of-show. */
        button.dr-mcard { font-family: inherit; cursor: pointer; }
        .dr-sched__backdrop {
            position: fixed; inset: 0; background: rgba(4,8,15,0.45); z-index: 120;
            opacity: 0; pointer-events: none; transition: opacity 0.25s ease;
        }
        .dr-sched {
            position: fixed; left: 0; right: 0; bottom: 0; z-index: 121;
            background: #ffffff; border-radius: 24px 24px 0 0;
            padding: 14px 24px calc(28px + env(safe-area-inset-bottom, 0px));
            max-height: 70vh; overflow-y: auto; overscroll-behavior: contain;
            transform: translateY(105%); transition: transform 0.3s cubic-bezier(0.2, 0.8, 0.2, 1);
        }
        .dr-sched.is-open { transform: none; }
        .dr-sched__backdrop.is-open { opacity: 1; pointer-events: auto; }
        .dr-sched__grab { width: 40px; height: 4px; border-radius: 2px; background: #E2E8F0; margin: 0 auto 14px; }
        .dr-sched h3 { margin: 0 0 18px; font-size: 22px; font-weight: 800; color: #121620; letter-spacing: -0.02em; }
        .dr-schedrow { display: flex; gap: 16px; }
        .dr-schedrow__rail { display: flex; flex-direction: column; align-items: center; padding-top: 4px; }
        .dr-schedrow__dot { width: 12px; height: 12px; border-radius: 50%; background: #2563EB; flex-shrink: 0; }
        .dr-schedrow__line { width: 2px; flex: 1; min-height: 30px; background: #E2E8F0; margin-top: 2px; }
        .dr-schedrow__txt { min-width: 0; padding-bottom: 18px; display: flex; flex-direction: column; gap: 2px; }
        .dr-schedrow:last-child .dr-schedrow__txt { padding-bottom: 0; }
        .dr-schedrow__time { font-size: 13px; font-weight: 800; color: #2563EB; }
        .dr-schedrow__title { font-size: 15px; font-weight: 600; color: #0F172A; }
        .dr-schedrow__note { font-size: 13px; color: #64748B; line-height: 1.4; }

        /* Sticky booking bar */
        .dr-book-bar {
            display: flex !important; position: fixed; left: 0; right: 0; bottom: 0; z-index: 90;
            align-items: center; justify-content: space-between; gap: 16px;
            padding: 12px 18px calc(12px + env(safe-area-inset-bottom, 0px));
            background: #ffffff; border-top: 1px solid #eef1f5;
            box-shadow: 0 -8px 26px rgba(4,8,15,0.10);
        }
        .dr-book-bar__price { display: flex; flex-direction: column; line-height: 1.15; }
        .dr-book-bar__amount { font-size: 20px; font-weight: 800; color: #121620; letter-spacing: -0.01em; }
        .dr-book-bar__label { font-size: 11.5px; color: #8a8f98; font-weight: 600; }
        .dr-book-bar__btn {
            flex: 1; max-width: 62%;
            background: #2563EB; color: #fff; border: none;
            padding: 15px 24px; border-radius: 16px; font-size: 15.5px; font-weight: 700;
            letter-spacing: -0.01em; cursor: pointer;
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.28);
        }
    }
    /* "Who takes the stage" — performer cards (app EventLineupSection).
       Card look is shared; mobile lays them in a snap rail, desktop in a grid. */
    .dr-lineup__card {
        position: relative; margin: 0; border-radius: 20px; overflow: hidden; background: #121620;
    }
    .dr-lineup__card img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .dr-lineup__card::after {
        content: ''; position: absolute; inset: 0; pointer-events: none;
        background: linear-gradient(180deg, transparent 45%, rgba(0,0,0,0.78) 100%);
    }
    .dr-lineup__meta {
        position: absolute; left: 18px; right: 18px; bottom: 16px; z-index: 2;
        display: flex; flex-direction: column; gap: 2px;
    }
    .dr-lineup__meta strong {
        color: #ffffff; font-size: 18px; font-weight: 700;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .dr-lineup__meta span {
        color: rgba(255,255,255,0.82); font-size: 13px;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    /* Desktop grid variant */
    .dr-lineup-desk__row { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; }
    .dr-lineup-desk .dr-lineup__card { height: 260px; }

    /* Ticket selection sheet — opened by Book Tickets (both breakpoints).
       Bottom sheet on mobile, bottom-docked centred panel on desktop. */
    .dr-tix__backdrop {
        position: fixed; inset: 0; background: rgba(4,8,15,0.45); z-index: 120;
        opacity: 0; pointer-events: none; transition: opacity 0.25s ease;
    }
    .dr-tix {
        position: fixed; left: 0; right: 0; bottom: 0; z-index: 121;
        background: #ffffff; border-radius: 24px 24px 0 0;
        padding: 14px 24px calc(24px + env(safe-area-inset-bottom, 0px));
        max-height: 75vh; overflow-y: auto; overscroll-behavior: contain;
        transform: translateY(105%); transition: transform 0.3s cubic-bezier(0.2, 0.8, 0.2, 1);
    }
    .dr-tix.is-open { transform: none; }
    .dr-tix__backdrop.is-open { opacity: 1; pointer-events: auto; }
    @media (min-width: 1025px) { .dr-tix { left: 50%; right: auto; width: 430px; margin-left: -215px; } }
    .dr-tix__grab { width: 40px; height: 4px; border-radius: 2px; background: #E2E8F0; margin: 0 auto 14px; }
    .dr-tix h3 { margin: 0 0 6px; font-size: 20px; font-weight: 800; color: #121620 !important; letter-spacing: -0.02em; }
    .dr-tixrow { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 0; border-bottom: 1px solid #F1F5F9; }
    .dr-tixrow:last-of-type { border-bottom: none; }
    .dr-tixrow__info { min-width: 0; }
    .dr-tixrow__info strong { display: block; font-size: 14.5px; font-weight: 700; color: #0F172A; }
    .dr-tixrow__info small { font-size: 13px; color: #64748B; font-weight: 600; }
    /* Held back by a later release phase: shown so buyers know what's coming, greyed
       and stepper-less so it reads as "not yet" rather than broken. */
    .dr-tixrow--soon .dr-tixrow__info strong { color: #94A3B8; }
    .dr-tixrow--soon .dr-tixrow__info small { color: #94A3B8; }
    .dr-tixrow__phase { display: inline-block; margin-top: 5px; padding: 2px 8px; border-radius: 999px; background: #EEF2FF; color: #4F5BD5; font-size: 10.5px; font-weight: 700; letter-spacing: 0.02em; }
    .dr-tixlock { flex: none; max-width: 48%; text-align: right; font-size: 11.5px; font-weight: 700; color: #94A3B8; line-height: 1.35; }
    .dr-stepper { display: flex; align-items: center; gap: 4px; flex-shrink: 0; }
    .dr-stepper button {
        width: 32px; height: 32px; border-radius: 50%; border: 1px solid #E2E8F0;
        background: #F4F7FB; color: #0F172A; font-size: 17px; font-weight: 700;
        cursor: pointer; display: grid; place-items: center; line-height: 1; padding: 0;
    }
    .dr-stepper input {
        width: 34px; border: none; background: none; text-align: center;
        font: inherit; font-size: 15px; font-weight: 800; color: #121620;
        -moz-appearance: textfield; pointer-events: none;
    }
    .dr-stepper input::-webkit-outer-spin-button, .dr-stepper input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
    .dr-tix__cta {
        display: block; width: 100%; border: none; cursor: pointer; margin-top: 14px;
        background: #2563EB; color: #fff; font: inherit; font-size: 15px; font-weight: 700;
        padding: 14px 24px; border-radius: 16px; letter-spacing: -0.01em;
        box-shadow: 0 8px 20px rgba(37, 99, 235, 0.28);
    }
    .dr-tix__cta:disabled { background: #CBD5E1; box-shadow: none; cursor: default; }
    .dr-tix__closed { padding: 18px 0; text-align: center; font-size: 14px; color: #64748B; }

    /* Good to Know — app-style card of icon-chip cells (EventGoodToKnowCard) */
    .dr-gtk__card { background: #F8FAFC; border: 1px solid #EEF2F7; border-radius: 16px; padding: 16px 18px; }
    .dr-gtk__grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    .dr-gtk__cell { display: flex; gap: 10px; align-items: flex-start; min-width: 0; }
    /* De-chipped: a small muted glyph per cell instead of a blue-tinted icon box.
       Seven colored icon chips in a grid read as template "chip soup"; a quiet
       monochrome mark keeps the scanning cue without the visual noise. */
    .dr-gtk__ico { flex: 0 0 auto; width: 18px; height: 18px; display: grid; place-items: center; margin-top: 1px; color: #94A3B8; }
    .dr-gtk__ico svg { width: 17px; height: 17px; color: inherit; }
    .dr-gtk__txt { min-width: 0; display: flex; flex-direction: column; gap: 2px; }
    .dr-gtk__txt small { font-size: 10px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: #94A3B8; }
    .dr-gtk__txt strong { font-size: 14px; font-weight: 600; color: #0F172A; line-height: 1.3; overflow-wrap: anywhere; }
    /* Stay two-up on phones (app parity — items split left/right); just tighten
       the cells so the pairs fit at 360-430px. */
    @media (max-width: 430px) {
        .dr-gtk__grid { gap: 14px 12px; }
        .dr-gtk__card { padding: 14px; }
        .dr-gtk__ico { flex: 0 0 auto; width: 16px; height: 16px; }
        .dr-gtk__ico svg { width: 16px; height: 16px; }
        .dr-gtk__txt strong { font-size: 13px; }
    }

    /* Photo lightbox — opened from the mobile gallery rail. */
    .dr-lbx {
        position: fixed; inset: 0; z-index: 140;
        background: rgba(4, 8, 15, 0.93);
        display: flex; align-items: center; justify-content: center; padding: 20px;
        opacity: 0; pointer-events: none; transition: opacity 0.25s ease;
    }
    .dr-lbx.is-open { opacity: 1; pointer-events: auto; }
    .dr-lbx img { max-width: 100%; max-height: 86vh; border-radius: 12px; object-fit: contain; }
    .dr-lbx__close {
        position: absolute; top: calc(14px + env(safe-area-inset-top, 0px)); right: 14px;
        width: 38px; height: 38px; border-radius: 50%; border: none; cursor: pointer;
        background: rgba(255, 255, 255, 0.14); color: #ffffff; font-size: 17px;
        display: grid; place-items: center;
    }

    /* Mobile-only elements hidden on desktop */
    @media (min-width: 1025px) { .dr-book-bar, .dr-meta-chips, .dr-mmeta, .dr-idrow, .dr-date-line, .dr-mobrows, .dr-lineup, .dr-sched, .dr-sched__backdrop, .floating-right-btn, .dr-mgal, .dr-lbx { display: none !important; } }
    /* Desktop hero stays a single image — the swipe pager is mobile-only. */
    @media (min-width: 1025px) {
        .dr-hero__dots { display: none !important; }
        .dr-hero__slide:not(:first-child) { display: none !important; }
    }

    /* Toast — feedback pill for the clipboard share fallback (created by JS). */
    .dr-toast {
        position: fixed; left: 50%; bottom: calc(96px + env(safe-area-inset-bottom, 0px));
        transform: translate(-50%, 8px);
        background: rgba(15, 23, 42, 0.92); color: #ffffff;
        font-size: 13px; font-weight: 600; padding: 10px 16px; border-radius: 999px;
        z-index: 130; opacity: 0; pointer-events: none;
        transition: opacity 0.25s ease, transform 0.25s ease;
    }
    .dr-toast.is-on { opacity: 1; transform: translate(-50%, 0); }

    /* Accessibility: no entrance motion when the OS asks for reduced motion. */
    @media (prefers-reduced-motion: reduce) {
        .dr-reveal, .dr-reveal.is-in { opacity: 1 !important; transform: none !important; transition: none !important; }
        .dr-book-bar { transform: none !important; transition: none !important; }
    }

    /* =====================================================================
       DESKTOP REDESIGN (≥1025px) — an immersive, editorial event page
       rendered from the `.dr-desk` block. The mobile `.dr-sheet` above is
       left completely untouched; on desktop we simply hide it (and its
       poster hero) and show this handcrafted layout instead. All rules are
       scoped to ≥1025px, so nothing here can reach the phone experience.
       ===================================================================== */
    .dr-desk { display: none; }

    @media (min-width: 1025px) {
        .district-event-page .dr-hero-banner,
        .district-event-page .dr-sheet { display: none !important; }
        /* Light canvas, full-bleed. It used to be painted on .dr-card-body, which
           sits inside main.container + .container — 16px of margin each — so the
           tint stopped 32px short of both viewport edges and left white bands
           down the sides of the card. Paint it once on the body instead and let
           every wrapper in between go transparent. */
        /* !important is required: site.css has
           `.theme-minimal, .theme-minimal body … { background:#fff !important }`
           and body carries .theme-minimal, so a normal declaration always loses
           to it no matter how specific. */
        body.event-detail-page { background: #f8fafc !important; }
        body.event-detail-page main.container,
        .district-event-page,
        .district-event-page > .container,
        .district-event-page .dr-card-body { background: transparent !important; }
        .district-event-page .dr-card-body { padding: 0 !important; }

        .dr-desk {
            display: block;
            --dk-accent: #2563eb; --dk-ink: #0f1626; --dk-mut: #5b6472;
            --dk-line: #edf0f6; --dk-soft: #f6f8fc;
        }

        /* ---------- Hero card (white plate on the light canvas) ----------
           Replaced the full-bleed dark band whose backdrop was the poster blurred
           behind a near-black scrim: on dark posters it collapsed to a flat slab,
           and the white type over it was the loudest "generated page" read. Now a
           bordered white card, which also means the hero looks identical whatever
           the poster's colours are. */
        .dk-hero {
            position: relative; max-width: 1200px; margin: 22px auto 0;
            background: #ffffff; border: 1px solid var(--dk-line); border-radius: 24px;
            box-shadow: 0 24px 56px -36px rgba(15,22,38,0.3);
            overflow: hidden; isolation: isolate;
        }
        /* The blurred poster backdrop and its dark scrim are gone with the dark hero. */
        .dk-hero__bg { display: none; }
        .dk-hero__deco { position: absolute; inset: 0; z-index: 0; pointer-events: none; overflow: hidden; }
        .dk-hero__deco svg { width: 100%; height: 100%; display: block; }
        /* Content rides above the texture. */
        .dk-hero__inner { position: relative; z-index: 1; }
        .dk-hero__inner {
            width: 100%; padding: 44px 48px;
            display: grid; grid-template-columns: 300px 1fr;
            gap: 48px; align-items: center;
        }
        .dk-poster {
            aspect-ratio: 3 / 4; border-radius: 16px; overflow: hidden; background: #f1f4f9;
            box-shadow: 0 22px 48px -22px rgba(15,22,38,0.42);
        }
        .dk-poster img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .dk-info { color: var(--dk-ink); min-width: 0; }
        .dk-chips { display: flex; flex-wrap: wrap; gap: 9px; margin-bottom: 20px; }
        .dk-chip {
            display: inline-flex; align-items: center; gap: 6px; height: 29px; padding: 0 13px;
            border-radius: 999px; font-size: 12px; font-weight: 700; color: var(--dk-mut);
            background: var(--dk-soft); border: 1px solid var(--dk-line);
        }
        .dk-chip--cat { background: var(--dk-accent); border-color: transparent; color: #fff; text-transform: uppercase; letter-spacing: 0.06em; }
        .dk-chip--rate i { color: #ffca3a; font-style: normal; }
        .dk-title {
            margin: 0 0 22px; font-size: 46px; line-height: 1.05; font-weight: 800;
            letter-spacing: -0.028em; color: var(--dk-ink);
            /* Cap the measure so a long name breaks into even lines instead of
               running the full 776px column. */
            max-width: 26ch;
        }
        /* Long names step down rather than stacking three lines of 46px type. */
        .dk-title--long { font-size: 36px; letter-spacing: -0.022em; max-width: 28ch; }
        /* Last row in .dk-info now that the price/CTA are gone — no trailing gap,
           or the block sits optically high against the poster. */
        .dk-facts { display: flex; flex-wrap: wrap; gap: 12px 28px; }
        .dk-fact { display: inline-flex; align-items: center; gap: 9px; font-size: 14.5px; font-weight: 550; color: #3b4453; }
        .dk-fact svg { width: 17px; height: 17px; flex: none; color: var(--dk-mut); opacity: 0.9; }
        /* Scoped to .dk-hero so it outranks the later .dk-iconbtn sizing. */
        .dk-hero .dk-share { position: absolute; top: 20px; right: 20px; z-index: 2; width: 44px; height: 44px; border-radius: 12px; }
        .dk-hero .dk-share svg { width: 18px; height: 18px; }

        .dk-btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 9px;
            height: 52px; padding: 0 34px; border: none; cursor: pointer; border-radius: 14px;
            font: inherit; font-size: 15.5px; font-weight: 750; letter-spacing: -0.01em; color: #fff;
            background: linear-gradient(135deg, #2f6bff, #1e40af);
            box-shadow: 0 14px 30px -10px rgba(37,99,235,0.7);
            transition: transform 0.18s ease, box-shadow 0.18s ease, filter 0.18s ease;
        }
        .dk-btn:hover { transform: translateY(-2px); box-shadow: 0 20px 42px -12px rgba(37,99,235,0.8); filter: brightness(1.05); }
        .dk-btn:active { transform: translateY(0); }
        .dk-btn:disabled { background: #94a3b8; box-shadow: none; cursor: default; transform: none; filter: none; }
        .dk-iconbtn {
            width: 52px; height: 52px; border-radius: 14px; cursor: pointer;
            background: #fff; border: 1px solid var(--dk-line); color: var(--dk-ink);
            display: inline-flex; align-items: center; justify-content: center;
            transition: background 0.18s ease, border-color 0.18s ease;
        }
        .dk-iconbtn:hover { background: var(--dk-soft); border-color: #dfe5f0; }
        .dk-iconbtn svg { width: 20px; height: 20px; }
        /* ---------- Two-column body ---------- */
        .dk-body {
            /* 40px top now that the "All events" breadcrumb is gone — it used to
               contribute its own 18px + line box between the card and this. */
            max-width: 1200px; margin: 0 auto; padding: 40px 40px 96px;
            display: grid; grid-template-columns: minmax(0, 1fr) 358px; gap: 60px; align-items: start;
        }
        .dk-main { min-width: 0; }
        .dk-sec { padding-bottom: 40px; margin-bottom: 40px; border-bottom: 1px solid var(--dk-line); }
        .dk-sec:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
        .dk-h { display: flex; align-items: baseline; justify-content: space-between; gap: 16px; margin: 0 0 22px; }
        .dk-h h2 { margin: 0; font-size: 22px; font-weight: 800; letter-spacing: -0.02em; color: var(--dk-ink); }
        .dk-h span { font-size: 13px; font-weight: 600; color: var(--dk-mut); }

        .dk-about { font-size: 16.5px; line-height: 1.8; color: #3b4453; }
        .dk-about p { margin: 0 0 16px; } .dk-about p:last-child { margin-bottom: 0; }
        /* Collapsed to ~6 lines with a soft fade until "Read more" is clicked (JS sets max-height). */
        .dk-about-wrap.is-clamped .dk-about { position: relative; overflow: hidden; }
        .dk-about-wrap.is-clamped .dk-about::after {
            content: ''; position: absolute; left: 0; right: 0; bottom: 0; height: 74px;
            /* Fades to the light canvas, not white — the body sits on #f8fafc now. */
            background: linear-gradient(rgba(248,250,252,0), #f8fafc 92%); pointer-events: none;
        }
        .dk-readmore {
            display: inline-flex; align-items: center; gap: 6px; margin-top: 14px; padding: 0;
            background: none; border: none; cursor: pointer; color: var(--dk-accent);
            font: inherit; font-size: 14px; font-weight: 750;
        }
        .dk-readmore[hidden] { display: none; }
        .dk-readmore:hover { text-decoration: underline; text-underline-offset: 3px; }
        .dk-readmore svg { width: 16px; height: 16px; transition: transform 0.2s ease; }
        .dk-readmore.is-open svg { transform: rotate(180deg); }
        .dk-about ul, .dk-about ol { margin: 0 0 16px; padding-left: 22px; }
        .dk-about li { margin-bottom: 6px; }
        .dk-about a { color: var(--dk-accent); }
        .dk-about h1, .dk-about h2, .dk-about h3, .dk-about h4 { color: var(--dk-ink) !important; margin: 22px 0 10px; font-weight: 750; }

        /* Lineup */
        .dk-artists { display: grid; grid-template-columns: repeat(auto-fill, minmax(158px, 1fr)); gap: 22px; }
        .dk-artist { text-align: center; }
        .dk-artist__ph {
            aspect-ratio: 1 / 1; border-radius: 16px; overflow: hidden; background: #10141d;
            box-shadow: 0 12px 28px -16px rgba(15,22,38,0.45); margin-bottom: 12px;
        }
        .dk-artist__ph img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform 0.45s cubic-bezier(0.2,0.7,0.3,1); }
        .dk-artist:hover .dk-artist__ph img { transform: scale(1.06); }
        .dk-artist strong { display: block; font-size: 14.5px; font-weight: 700; color: var(--dk-ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .dk-artist span { display: block; font-size: 12.5px; color: var(--dk-mut); margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        /* Gallery mosaic — first photo anchors a 2×2 tile */
        .dk-gallery { display: grid; grid-template-columns: repeat(4, 1fr); grid-auto-rows: 152px; gap: 12px; }
        .dk-gallery button { border: none; padding: 0; cursor: pointer; overflow: hidden; border-radius: 16px; background: #10141d; }
        .dk-gallery img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform 0.5s cubic-bezier(0.2,0.7,0.3,1); }
        .dk-gallery button:hover img { transform: scale(1.07); }
        .dk-gallery button:first-child { grid-column: span 2; grid-row: span 2; }

        /* Venue / location */
        .dk-venue { background: #fff; border: 1px solid var(--dk-line); border-radius: 20px; overflow: hidden; box-shadow: 0 20px 46px -30px rgba(15,22,38,0.3); }
        .dk-venue iframe { display: block; width: 100%; height: 264px; border: 0; }
        /* Venue ambiance: a scrolling strip of the listing's own photos, BELOW the
           location card. First tile is wide — these sets almost always lead with
           the establishing shot. */
        .dk-amb__wrap { margin-top: 18px; }
        .dk-amb__hd { margin: 0 0 10px; font-size: 16px; font-weight: 750; color: var(--dk-ink); }
        .dk-amb__hd span { margin-left: 8px; font-size: 11.5px; font-weight: 600; color: var(--dk-mut); }
        .dk-amb__vp { position: relative; }
        .dk-amb {
            display: flex; gap: 10px; overflow-x: auto; scroll-snap-type: x mandatory;
            scroll-behavior: smooth;
            /* The native horizontal scrollbar sat under the strip looking like a
               stray OS widget. Hidden here; the arrows below drive the scroll. */
            scrollbar-width: none; -ms-overflow-style: none;
        }
        .dk-amb::-webkit-scrollbar { width: 0; height: 0; display: none; }
        .dk-amb > button { position: relative; margin: 0; padding: 0; cursor: pointer; flex: 0 0 auto; width: 210px; height: 190px; scroll-snap-align: start; border-radius: 16px; overflow: hidden; border: 1px solid var(--dk-line); background: var(--dk-soft); }
        .dk-amb > button:first-child { width: 320px; }
        .dk-amb img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform 0.45s cubic-bezier(0.2,0.7,0.3,1); }
        .dk-amb > button:hover img { transform: scale(1.05); }
        /* Arrows: hidden until the strip is hovered (or an arrow is focused), and
           removed entirely at whichever end you've reached. */
        .dk-amb__nav {
            position: absolute; top: 50%; transform: translateY(-50%); z-index: 2;
            width: 40px; height: 40px; padding: 0; border-radius: 50%; cursor: pointer;
            background: #fff; border: 1px solid var(--dk-line); color: var(--dk-ink);
            display: grid; place-items: center;
            box-shadow: 0 8px 22px -8px rgba(15,22,38,0.45);
            opacity: 0; transition: opacity 0.18s ease, background 0.18s ease;
        }
        .dk-amb__nav svg { width: 19px; height: 19px; }
        .dk-amb__nav:hover { background: var(--dk-soft); }
        .dk-amb__nav[hidden] { display: none; }
        .dk-amb__vp:hover .dk-amb__nav, .dk-amb__nav:focus-visible { opacity: 1; }
        .dk-amb__nav--prev { left: 10px; }
        .dk-amb__nav--next { right: 10px; }
        @media (prefers-reduced-motion: reduce) {
            .dk-amb { scroll-behavior: auto; }
            .dk-amb__nav { transition: none; }
        }
        .dk-venue__ft { display: flex; align-items: center; justify-content: space-between; gap: 18px; padding: 18px 22px; }
        .dk-venue__ft h4 { margin: 0 0 3px; font-size: 16px; font-weight: 750; color: var(--dk-ink); }
        .dk-venue__ft p { margin: 0; font-size: 13.5px; color: var(--dk-mut); }
        .dk-venue__map { height: 152px; position: relative; background: linear-gradient(135deg, #e6f0ff, #eef5ff); }
        .dk-venue__map::before { content: ''; position: absolute; inset: 0; background-image: linear-gradient(rgba(37,99,235,0.08) 1px, transparent 1px), linear-gradient(90deg, rgba(37,99,235,0.08) 1px, transparent 1px); background-size: 24px 24px; }
        .dk-venue__pin { position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%); width: 22px; height: 22px; border-radius: 50%; background: var(--dk-accent); border: 4px solid #fff; box-shadow: 0 4px 14px rgba(37,99,235,0.5); }
        .dk-dir { flex: none; display: inline-flex; align-items: center; gap: 8px; height: 44px; padding: 0 20px; border-radius: 13px; background: var(--dk-ink); color: #fff; font-size: 13.5px; font-weight: 700; text-decoration: none; transition: transform 0.18s ease, box-shadow 0.18s ease; }
        .dk-dir:hover { transform: translateY(-2px); box-shadow: 0 12px 24px -12px rgba(15,22,38,0.5); }
        .dk-dir svg { width: 15px; height: 15px; }

        /* Good to know — white, not --dk-soft: that tint is a hair off the new
           #f8fafc canvas, so the card vanished into the page. */
        .dk-gtk .dr-gtk__card { background: #fff; border-color: var(--dk-line); border-radius: 18px; }

        /* Important information */
        .dk-notes { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 14px; }
        .dk-notes li { display: flex; gap: 12px; font-size: 14.5px; line-height: 1.6; color: #3b4453; }
        .dk-notes li::before { content: ''; flex: none; width: 7px; height: 7px; margin-top: 8px; border-radius: 50%; background: var(--dk-accent); }

        /* ---------- Sticky booking rail ---------- */
        .dk-rail { align-self: start; position: sticky; top: 92px; display: flex; flex-direction: column; gap: 18px; }

        /* Organizer card — a real identity block (logo, verified name, bio, track
           record) instead of the old one-liner that printed a placeholder name. */
        .dk-org { background: #fff; border: 1px solid var(--dk-line); border-radius: 20px; padding: 18px 20px; box-shadow: 0 20px 48px -34px rgba(15,22,38,0.32); }
        .dk-org__hd { display: flex; align-items: center; gap: 15px; }
        .dk-org__ava { flex: none; width: 54px; height: 54px; border-radius: 50%; overflow: hidden; background: linear-gradient(140deg, #2563eb, #1e3fa8); display: grid; place-items: center; color: #fff; font-weight: 800; font-size: 19px; }
        .dk-org__ava img { width: 100%; height: 100%; object-fit: cover; }
        .dk-org__tx { min-width: 0; flex: 1; }
        .dk-org__tx small { font-size: 10.5px; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: #98a1b0; }
        .dk-org__tx strong { display: flex; align-items: center; gap: 5px; font-size: 15.5px; font-weight: 750; color: var(--dk-ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .dk-org__tx strong svg { width: 15px; height: 15px; color: var(--dk-accent); flex: none; }
        .dk-org__bio { margin: 12px 0 0; font-size: 13px; line-height: 1.5; color: var(--dk-mut); display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
        .dk-org__stats { display: flex; flex-wrap: wrap; gap: 6px 16px; margin-top: 13px; padding-top: 13px; border-top: 1px solid var(--dk-line); font-size: 12.5px; color: var(--dk-mut); }
        .dk-org__stats b { font-weight: 800; color: var(--dk-ink); }
        .dk-org__act { display: flex; align-items: center; gap: 14px; margin-top: 15px; }
        .dk-org__follow { display: inline-flex; align-items: center; justify-content: center; padding: 9px 20px; border-radius: 999px; cursor: pointer; border: 1.5px solid var(--dk-accent); background: transparent; color: var(--dk-accent); font-size: 13px; font-weight: 700; font-family: inherit; text-decoration: none; transition: background 0.2s; }
        .dk-org__follow:hover { background: rgba(37,99,235,0.08); }
        .dk-org__follow.is-on { background: rgba(37,99,235,0.10); border-color: transparent; color: #1e40af; }
        .dk-org__link { font-size: 13px; font-weight: 700; color: var(--dk-mut); text-decoration: none; }
        .dk-org__link:hover { color: var(--dk-ink); }

        /* The ambient blue glow that lifted the poster off the old dark hero would
           read as a smudge on the white card — a plain drop shadow does the job. */

        /* ---------- Buy card + info rows (sticky rail) ----------
           Replaced the inline stepper/total panel: it duplicated the .dr-tix modal
           the hero CTA already opens, so the page offered two different ways to
           pick the same tickets. Now one price, one CTA. */
        .dk-buy {
            background: #fff; border: 1px solid var(--dk-line); border-radius: 20px;
            padding: 20px 22px 18px; box-shadow: 0 18px 44px -30px rgba(15,22,38,0.36);
        }
        .dk-buy__urgent { display: block; margin-bottom: 9px; font-size: 12px; font-weight: 700; color: #c9720a; }
        .dk-buy__row { display: flex; align-items: center; justify-content: space-between; gap: 14px; }
        .dk-buy__price { display: flex; align-items: baseline; gap: 6px; min-width: 0; }
        .dk-buy__price b { font-size: 26px; font-weight: 800; letter-spacing: -0.025em; color: var(--dk-ink); }
        .dk-buy__price small { font-size: 12.5px; font-weight: 600; color: var(--dk-mut); }
        .dk-buy__cta { flex: none; height: 46px; padding: 0 24px; font-size: 14.5px; border-radius: 12px; }
        .dk-buy__off { flex: none; font-size: 14px; font-weight: 700; color: var(--dk-mut); }
        .dk-buy__sec { display: flex; align-items: center; gap: 7px; margin-top: 14px; padding-top: 13px; border-top: 1px solid var(--dk-line); font-size: 12px; font-weight: 600; color: var(--dk-mut); }
        .dk-buy__sec svg { width: 14px; height: 14px; flex: none; color: #22a565; }

        .dk-rows { background: #fff; border: 1px solid var(--dk-line); border-radius: 20px; box-shadow: 0 18px 44px -30px rgba(15,22,38,0.36); overflow: hidden; }
        .dk-row {
            display: flex; align-items: center; gap: 14px; padding: 16px 18px;
            border-bottom: 1px solid var(--dk-line); text-decoration: none; color: inherit;
            transition: background 0.15s ease;
        }
        .dk-row:last-child { border-bottom: none; }
        a.dk-row:hover { background: var(--dk-soft); }
        .dk-row__ic { flex: none; width: 38px; height: 38px; border-radius: 50%; border: 1px solid var(--dk-line); display: grid; place-items: center; color: var(--dk-ink); }
        .dk-row__ic svg { width: 18px; height: 18px; }
        .dk-row__tx { min-width: 0; flex: 1; }
        .dk-row__tx strong { display: block; font-size: 14.5px; font-weight: 700; line-height: 1.35; color: var(--dk-ink); }
        .dk-row__tx small { display: block; margin-top: 2px; font-size: 12.5px; color: var(--dk-mut); overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; }
        .dk-row__ch { flex: none; width: 18px; height: 18px; color: #b3bbc7; }

        /* ---------- Similar events (full-width discovery rail) ---------- */
        .dk-more { max-width: 1200px; margin: 0 auto; padding: 4px 40px 100px; }
        .dk-more__h { margin: 0 0 24px; font-size: 24px; font-weight: 800; letter-spacing: -0.025em; color: var(--dk-ink); }
        .dk-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(206px, 1fr)); gap: 22px; }
        .dk-card { display: block; text-decoration: none; color: inherit; border-radius: 18px; overflow: hidden; background: #fff; border: 1px solid var(--dk-line); box-shadow: 0 14px 32px -22px rgba(15,22,38,0.32); transition: transform 0.22s ease, box-shadow 0.22s ease; }
        .dk-card:hover { transform: translateY(-4px); box-shadow: 0 26px 48px -22px rgba(15,22,38,0.4); }
        .dk-card__img { aspect-ratio: 3 / 4; background: #10141d; overflow: hidden; }
        .dk-card__img img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform 0.5s cubic-bezier(0.2,0.7,0.3,1); }
        .dk-card:hover .dk-card__img img { transform: scale(1.05); }
        .dk-card__b { padding: 14px 15px 16px; }
        .dk-card__cat { font-size: 10.5px; font-weight: 800; letter-spacing: 0.05em; text-transform: uppercase; color: var(--dk-accent); }
        .dk-card__t { margin: 5px 0 7px; font-size: 15px; font-weight: 750; line-height: 1.3; color: var(--dk-ink); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .dk-card__m { display: flex; align-items: center; gap: 6px; font-size: 12.5px; color: var(--dk-mut); }
        .dk-card__m svg { width: 13px; height: 13px; flex: none; opacity: 0.7; }
        .dk-card__p { margin-top: 11px; font-size: 14px; font-weight: 800; color: var(--dk-ink); }

        /* Scroll-reveal — sections rise gently into view (never the sticky rail) */
        .dr-desk .dk-reveal { opacity: 0; transform: translateY(20px); transition: opacity 0.6s ease, transform 0.65s cubic-bezier(0.2,0.7,0.3,1); }
        .dr-desk .dk-reveal.is-in { opacity: 1; transform: none; }

        /* Let the shared photo lightbox open on desktop too (it's mobile-only above) */
        .district-event-page .dr-lbx { display: flex !important; }

        @media (prefers-reduced-motion: reduce) {
            .dk-btn, .dk-dir, .dk-artist__ph img, .dk-gallery img, .dk-card, .dk-card__img img { transition: none !important; }
            .dr-desk .dk-reveal { opacity: 1 !important; transform: none !important; transition: none !important; }
        }
    }

    /* Narrower desktops: let the rail drop under the content instead of cramping. */
    @media (min-width: 1025px) and (max-width: 1180px) {
        .dr-desk .dk-body { grid-template-columns: 1fr; gap: 8px; }
        .dr-desk .dk-rail { position: static; top: auto; flex-direction: row; flex-wrap: wrap; align-items: flex-start; }
        .dr-desk .dk-buy { flex: 2 1 340px; }
        .dr-desk .dk-org { flex: 1 1 260px; }
    }
</style>

<div class="district-event-page" style="position: relative;">
    <div class="container" style="max-width: 1300px; margin: 0 auto; padding: 0; position: relative;">
        
        {{-- Floating Back Button on the Left --}}
        {{-- Real back navigation preserves the events list's scroll/filters;
             /events stays as the no-JS and deep-link fallback. --}}
        <a href="/events" class="floating-left-btn" onclick="if(window.history.length>1){event.preventDefault();history.back();}">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>
        </a>

        {{-- Floating Share Button on the Right (mobile) --}}
        <button type="button" class="floating-right-btn" aria-label="Share" onclick="drShare()">
            <svg width="19" height="19" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.6" y1="13.5" x2="15.4" y2="17.5"/><line x1="15.4" y1="6.5" x2="8.6" y2="10.5"/></svg>
        </button>

        <main class="dr-card-body">
            {{-- Hero Banner --}}
            @php
                $heroImg = $event->heroImageUrl() ?? asset('events.png');
                // Real countdown from the event date; hidden for past events so we
                // never show a fabricated "Starts in 3H".
                $countdown = ($event->date && $event->date->isFuture())
                    ? 'Starts '.$event->date->diffForHumans()
                    : null;
            @endphp
            @php
                // Hero pager: swipe through the poster + gallery photos (mobile).
                $heroPhotos = array_values(array_merge($event->imageUrls(), $event->galleryUrls()));
                $heroImgs = count($heroPhotos) ? array_slice($heroPhotos, 0, 6) : [$heroImg];
            @endphp
            <div class="dr-hero-banner" style="margin-top: 0; margin-bottom: 12px;">
                <div class="dr-hero__rail">
                    @foreach($heroImgs as $i => $img)
                        <img class="dr-hero__slide" src="{{ $img }}" alt="{{ $event->title }}"
                             @if($i === 0) fetchpriority="high" @else loading="lazy" @endif decoding="async">
                    @endforeach
                </div>
                @if(count($heroImgs) > 1)
                <div class="dr-hero__dots" aria-hidden="true">
                    @foreach($heroImgs as $i => $img)
                        <span class="dr-hero__dot @if($i === 0) is-on @endif"></span>
                    @endforeach
                </div>
                @endif
                @if($countdown)<div class="dr-hero-badge">{{ $countdown }}</div>@endif
            </div>

            {{-- Content sheet — overlaps the poster with a rounded top (mirrors the app) --}}
            <div class="dr-sheet">
            @php
                // The event's real start time is the `time` string column ("7:00 PM").
                // `date` is date-only, so its clock reads 00:00 — formatting it gave
                // every event a bogus "12:00 AM". Use the real field, and simply omit
                // the time (→ "Time TBA" where a slot needs filling) when it's unset.
                $evTime  = trim((string) ($event->time ?? ''));
                $hasTime = $evTime !== '';
                // "5:00 PM – 8:00 PM" when the host set an end time. The date line
                // has room for the full range; the narrow meta chip splits it.
                $evEnd   = trim((string) ($event->end_time ?? ''));
                $evRange = $event->timeRangeLabel();
            @endphp
            {{-- Info Header --}}
            <div class="dr-info-row" style="margin-bottom: 16px; padding-bottom: 12px;">
                <div>
                    {{-- Mobile-only identity row: category pill + rating (on the white sheet) --}}
                    <div class="dr-idrow">
                        <span class="dr-cat-pill">{{ $event->category ?: 'Event' }}</span>
                        @if(!empty($event->rating) && $event->rating > 0)
                            <span class="dr-rate-pill"><i>★</i>{{ number_format($event->rating, 1) }}</span>
                        @endif
                    </div>
                    <h1 class="dr-main-title" style="margin-bottom: 8px;">{{ $event->title }}</h1>
                    <p class="dr-date-line">{{ optional($event->date)->format('D, d M') }}@if($evRange) • {{ $evRange }}@endif@if($event->city) • {{ $event->city }}@endif</p>
                    <p class="dr-meta-text">{{ optional($event->date)->format('D, d M Y') }}@if($evRange) • {{ $evRange }}@endif</p>
                    {{-- Mobile-only glass meta chips overlaid on the hero --}}
                    <div class="dr-meta-chips">
                        @if($event->date)
                        <span class="dr-chip">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            <span>{{ $event->date->format('D, d M') }}@if($hasTime) · {{ $evTime }}@endif</span>
                        </span>
                        @endif
                        @if($event->venue)
                        <span class="dr-chip">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                            <span>{{ $event->venue }}</span>
                        </span>
                        @endif
                    </div>
                </div>
                <button class="dr-checkin-btn" type="button" onclick="drTixToggle(true)">
                    Book Tickets
                </button>
            </div>

            {{-- Mobile-only: trust strip + metadata cards (mirrors the app detail) --}}
            @php
                $mDay = optional($event->date)->format('j');
                $mMonth = optional($event->date)->format('M');
                $mTime = $evTime;
                $mVenueShort = $event->venue ? \Illuminate\Support\Str::before($event->venue, ',') : 'Venue';
                // The chip used to caption the venue with the word "Directions",
                // which the pin icon already says. The locality ("Koramangala")
                // is the bit that tells someone whether they can get there.
                $mVenueArea = $event->venueArea();
                $mSchedule = $event->scheduleRows();
                // Precise "Directions" deep link — uses the exact lat/lng pin when the
                // event has one, else a venue+city text search (Event::directionsUrl()).
                $mMapsUrl = $event->directionsUrl();
            @endphp
            <div class="dr-mmeta">
                {{-- The old "Verified · Secure · Instant" chip strip was hardcoded
                     filler (shown on every event regardless of truth) — the classic
                     template tell. Real trust lives where it means something: the
                     "Verified organizer" line in the Organizer section, and the
                     secure-checkout note by the Book bar. --}}
                <div class="dr-mcards">
                    <div class="dr-mcard">
                        <span class="dr-mcard__ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span>
                        <strong>{{ $mDay ?: 'TBA' }}</strong>
                        <small>{{ $mMonth ?: 'Date' }}</small>
                    </div>
                    <a class="dr-mcard" href="{{ $mMapsUrl }}" target="_blank" rel="noopener">
                        <span class="dr-mcard__ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg></span>
                        <strong>{{ $mVenueShort }}</strong>
                        <small class="dr-mcard__link">{{ $mVenueArea ?: 'Directions' }}</small>
                    </a>
                    @if(count($mSchedule))
                    {{-- Tapping opens the run-of-show sheet, like the app's Doors Open card. --}}
                    <button type="button" class="dr-mcard" onclick="drSchedToggle(true)">
                        <span class="dr-mcard__ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/></svg></span>
                        <strong>{{ $hasTime ? $mTime : 'TBA' }}</strong>
                        {{-- The end time earns the caption slot when there is one:
                             "till 8:00 PM" answers a question, "Schedule" only
                             labels the tap the whole card already invites. --}}
                        <small class="dr-mcard__link">{{ $evEnd !== '' ? 'till ' . $evEnd : 'Schedule' }}</small>
                    </button>
                    @else
                    <div class="dr-mcard">
                        <span class="dr-mcard__ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/></svg></span>
                        <strong>{{ $hasTime ? $mTime : 'TBA' }}</strong>
                        <small>{{ $evEnd !== '' ? 'till ' . $evEnd : ($hasTime ? 'Doors Open' : 'Time') }}</small>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Tabs --}}
            <nav class="dr-tabs" style="gap: 24px; margin-bottom: 16px;">
                <a href="#" class="dr-tab active" data-target="pane-details" style="padding: 8px 0;">Details</a>
                <a href="#" class="dr-tab" data-target="pane-know" style="padding: 8px 0;">Know Before You Go</a>
            </nav>

            {{-- Content Pane: Details --}}
            <div id="pane-details" class="dr-tab-pane" style="display: block;">
                
                {{-- Content Grid --}}
                <div class="dr-content-grid" style="gap: 28px; display: grid;">
                    <div>
                        <section style="margin-bottom: 0px;">
                            <h3 class="dr-section-title" style="margin-bottom: 12px;">Overview</h3>
                            <div class="dr-about-wrap" id="drAboutWrap">
                                <div class="dr-description">
                                    {!! $event->description !!}
                                </div>
                                <button type="button" class="dr-readmore" id="drAboutToggle" onclick="drToggleAbout()" hidden>
                                    <span class="lbl">Read more</span>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
                                </button>
                            </div>

                            {{-- Venue map. When the event has real coordinates and a
                                 Google key is configured, show a live embedded map
                                 pinned to the exact spot. Otherwise fall back to the
                                 generic-texture card that links to a Maps search — it
                                 never claims a false location. --}}
                            @php
                                $mapLoc = $event->location ?: '';
                                $mapVenue = $event->venue ?: '';
                                // Avoid repeating the venue when the location string already contains it.
                                $mapBase = ($mapVenue && stripos($mapLoc, $mapVenue) === false)
                                    ? trim($mapVenue.' '.$mapLoc)
                                    : ($mapLoc ?: $mapVenue);
                                $mapQuery = urlencode(trim(trim($mapBase).', '.($event->city ?: 'India'), ', '));
                                $mapEmbed = $event->mapEmbedUrl();
                                $directions = $event->directionsUrl();
                                // The venue's own photos off its Google listing. Empty
                                // is normal — the section then just isn't drawn.
                                $ambiance = $event->venuePhotos();
                            @endphp
                            @if($mapEmbed && $event->hasCoordinates())
                            {{-- Live embedded Google map (exact pin) --}}
                            <div style="margin-top: 24px; border: 1px solid #f0f0f0; border-radius: 24px; background: #ffffff; overflow: hidden; box-shadow: 0 8px 30px rgba(0, 0, 0, 0.03);">
                                <iframe src="{{ $mapEmbed }}" width="100%" height="220" style="border:0; display:block;" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen title="Venue location map"></iframe>
                                <div style="padding: 16px 20px; display: flex; align-items: center; justify-content: space-between; gap: 12px;">
                                    <div style="min-width: 0;">
                                        <div style="font-size: 11px; font-weight: 800; color: #3b82f6; text-transform: uppercase; letter-spacing: 0.07em; margin-bottom: 4px;">Venue Location</div>
                                        <h4 style="margin: 0 0 4px 0; font-size: 16px; font-weight: 800; color: #121620; line-height: 1.3;">{{ $event->venue }}</h4>
                                        <div style="font-size: 13px; color: #71717a; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ $event->location ?: ($event->city ? $event->city.', India' : 'India') }}</div>
                                    </div>
                                    <a href="{{ $directions }}" target="_blank" rel="noopener" style="flex: none; display: inline-flex; align-items: center; gap: 6px; padding: 10px 16px; border-radius: 14px; background: #3b82f6; color: #ffffff; font-weight: 700; font-size: 13px; text-decoration: none; box-shadow: 0 4px 12px rgba(59,130,246,0.3);">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg>Directions
                                    </a>
                                </div>
                            </div>
                            @else
                            <a href="https://www.google.com/maps/search/?api=1&query={{ $mapQuery }}" target="_blank" rel="noopener" aria-label="Open venue location in Maps" style="margin-top: 24px; border: 1px solid #f0f0f0; border-radius: 24px; background: #ffffff; overflow: hidden; display: flex; height: 160px; box-shadow: 0 8px 30px rgba(0, 0, 0, 0.03); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); cursor: pointer; text-decoration: none; color: inherit;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 12px 35px rgba(0, 0, 0, 0.06)';" onmouseout="this.style.transform='none'; this.style.boxShadow='0 8px 30px rgba(0, 0, 0, 0.03)';">
                                {{-- Left Half: generic map texture with a pin affordance --}}
                                <div style="width: 45%; position: relative; height: 100%; background: linear-gradient(135deg, #e0f2fe 0%, #eff6ff 100%); overflow: hidden;">
                                    <div style="position:absolute; inset:0; background-image: linear-gradient(rgba(59,130,246,0.08) 1px, transparent 1px), linear-gradient(90deg, rgba(59,130,246,0.08) 1px, transparent 1px); background-size: 22px 22px;"></div>

                                    {{-- Soft blue translucent circular overlay like the mockup --}}
                                    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 70px; height: 70px; border-radius: 50%; background: rgba(59, 130, 246, 0.15); border: 1px solid rgba(59, 130, 246, 0.3); display: flex; align-items: center; justify-content: center; pointer-events: none; z-index: 10;">
                                        {{-- Glowing central marker --}}
                                        <div style="width: 24px; height: 24px; border-radius: 50%; background: #3b82f6; border: 3px solid #ffffff; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 10px rgba(59, 130, 246, 0.5);">
                                            <div style="width: 6px; height: 6px; border-radius: 50%; background: #ffffff;"></div>
                                        </div>
                                    </div>
                                </div>
                                
                                {{-- Right Half: Address details --}}
                                <div style="width: 55%; padding: 20px 24px; display: flex; flex-direction: column; justify-content: center; position: relative; height: 100%;">
                                    <div style="font-size: 11px; font-weight: 800; color: #3b82f6; text-transform: uppercase; letter-spacing: 0.07em; margin-bottom: 4px;">Venue Location</div>
                                    <h4 style="margin: 0 0 6px 0; font-size: 16px; font-weight: 800; color: #121620; letter-spacing: -0.01em; line-height: 1.3;">{{ $event->venue }}</h4>
                                    
                                    {{-- Address detail with tiny arrow icon --}}
                                    <div style="display: flex; align-items: center; gap: 6px; font-size: 13px; color: #71717a;">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" style="color: #71717a; transform: rotate(45deg);"><path d="M21 3L3 10.53v.98l6.84 2.65L12.48 21h.98L21 3z"/></svg>
                                        <span>{{ $event->location ?: ($event->city ? $event->city.', India' : 'India') }}</span>
                                    </div>
                                    
                                    {{-- Share / Open Map button on bottom right corner --}}
                                    <div style="position: absolute; right: 20px; bottom: 20px;">
                                        <div style="width: 44px; height: 44px; border-radius: 14px; background: #ffffff; border: 1px solid #e5e7eb; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(0,0,0,0.06); color: #3b82f6; transition: all 0.2s;" onmouseover="this.style.borderColor='#3b82f6'; this.style.background='#f0f9ff';" onmouseout="this.style.borderColor='#e5e7eb'; this.style.background='#ffffff';">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"></path>
                                                <polyline points="16 6 12 2 8 6"></polyline>
                                                <line x1="12" y1="2" x2="12" y2="15"></line>
                                            </svg>
                                        </div>
                                    </div>
                                </div>
                            </a>
                            @endif

                            @if(count($ambiance))
                            {{-- Venue ambiance — what the room actually looks like.
                                 Sits BELOW the map card: the map places the venue,
                                 then the photos show it. Google requires the
                                 contributor credit to travel with each photo, hence
                                 the caption on every tile. --}}
                            <section class="dr-amb">
                                <h3 class="dr-section-title" style="margin-bottom: 12px;">Venue ambiance <span class="dr-amb__src">Photos from Google</span></h3>
                                <div class="dr-amb__rail">
                                    @foreach($ambiance as $photo)
                                        {{-- A button, not a figure: these open the shared
                                             photo lightbox, so they must be reachable by
                                             keyboard and announced as controls. --}}
                                        <button type="button" class="dr-amb__item" onclick="drLbx(this)" aria-label="View venue photo full screen">
                                            <img src="{{ $photo['url'] }}" alt="Inside {{ $event->venue ?: 'the venue' }}" loading="lazy" decoding="async">
                                            @if($photo['credit'] !== '')
                                                <span class="dr-amb__cap">{{ $photo['credit'] }}</span>
                                            @endif
                                        </button>
                                    @endforeach
                                </div>
                            </section>
                            @endif

                            {{-- Mobile-only app-parity rows: Organizer + Venue
                                 (EventOrganizerSection / EventVenueSection order). --}}
                            @php
                                // The real organiser (host profile → partner account),
                                // NOT the lineup artist this used to read.
                                $org = $event->organiserCard();
                                $mVenueName = $event->venue ? trim(\Illuminate\Support\Str::before($event->venue, ',')) : '';
                                $mVenueAddr = $event->venue ? trim(\Illuminate\Support\Str::after($event->venue, ',')) : '';
                                if ($mVenueAddr === $event->venue) { $mVenueAddr = ''; }
                                if ($mVenueAddr === '' && $event->location) {
                                    // Don't repeat the venue name when the location string starts with it.
                                    $mVenueAddr = trim(ltrim(\Illuminate\Support\Str::after($event->location, $mVenueName), " |,-·"));
                                    if ($mVenueAddr === '') { $mVenueAddr = $event->location; }
                                } elseif ($mVenueAddr === '' && $event->city) { $mVenueAddr = $event->city; }
                            @endphp
                            <div class="dr-mobrows">
                                <section>
                                    <h3 class="dr-section-title">Organizer</h3>
                                    {{-- One row, the way a ticketing app does it: identity on
                                         the left (tap → organiser page), the action on the
                                         right. The previous version was a tall card whose
                                         only action was a full-width "View organiser page"
                                         button — a lot of furniture around one link. Follow
                                         is the affordance people actually want here, and it
                                         already existed on the organiser's own page. --}}
                                    @php $orgMeta = array_values(array_filter([
                                        $org['tagline'],
                                        $org['events'] > 0 ? $org['events'] . ' ' . \Illuminate\Support\Str::plural('event', $org['events']) : null,
                                        $org['followers'] > 0 ? $org['followers'] . ' ' . \Illuminate\Support\Str::plural('follower', $org['followers']) : null,
                                    ])); @endphp
                                    {{-- Hosts without a live public profile have no page to
                                         open and nothing to follow, so this row used to be an
                                         avatar, a name, and a dead javascript:void(0) anchor
                                         under an "Organizer" heading — it read as a section
                                         that failed to load. In that case the identity is
                                         plain text (no fake link) and the row carries the one
                                         action that does exist: reaching a human. --}}
                                    <div class="dr-org">
                                        <{{ $org['url'] ? 'a' : 'span' }} class="dr-org__who" @if($org['url']) href="{{ $org['url'] }}" @endif>
                                            <span class="dr-org__ava">
                                                @if($org['logo'])
                                                    <img src="{{ $org['logo'] }}" alt="{{ $org['name'] }}" loading="lazy" decoding="async">
                                                @else
                                                    {{ $org['initial'] }}
                                                @endif
                                            </span>
                                            <span class="dr-org__id">
                                                <strong>
                                                    {{ $org['name'] }}
                                                    {{-- The tick is the profile's real verification flag, not
                                                         decoration every host used to get for free. --}}
                                                    @if($org['verified'])
                                                        <svg viewBox="0 0 24 24" fill="currentColor" aria-label="Verified organiser"><path d="M23 12l-2.44-2.79.34-3.69-3.61-.82-1.89-3.2L12 2.96 8.6 1.5 6.71 4.69 3.1 5.5l.34 3.7L1 12l2.44 2.79-.34 3.7 3.61.82L8.6 22.5l3.4-1.47 3.4 1.46 1.89-3.19 3.61-.82-.34-3.69L23 12zm-12.91 4.72l-3.8-3.81 1.48-1.48 2.32 2.33 5.85-5.87 1.48 1.48-7.33 7.35z"/></svg>
                                                    @endif
                                                </strong>
                                                @if(count($orgMeta))
                                                    <small>{{ implode(' · ', $orgMeta) }}</small>
                                                @endif
                                            </span>
                                        </{{ $org['url'] ? 'a' : 'span' }}>

                                        @if(! $org['slug'])
                                            <a class="dr-org__contact" href="{{ url('/support') }}">Contact</a>
                                        @endif
                                        @if($org['slug'])
                                            @auth
                                                <form method="POST" action="{{ route('site.host.follow', ['slug' => $org['slug']]) }}">
                                                    @csrf
                                                    <button type="submit" class="dr-org__follow {{ $org['following'] ? 'is-on' : '' }}">
                                                        {{ $org['following'] ? 'Following' : 'Follow' }}
                                                    </button>
                                                </form>
                                            @else
                                                {{-- Guests get the same button; it just asks them in first. --}}
                                                <a class="dr-org__follow" href="{{ url('/login') }}">Follow</a>
                                            @endauth
                                        @endif
                                    </div>
                                </section>
                                @php $lineupRows = $event->lineupRows(); @endphp
                                @if(count($lineupRows))
                                <section class="dr-lineup">
                                    <h3 class="dr-section-title">Who takes the stage</h3>
                                    <div class="dr-lineup__rail">
                                        @foreach($lineupRows as $artist)
                                            <figure class="dr-lineup__card">
                                                @if($artist["image"])<img src="{{ $artist["image"] }}" alt="{{ $artist["name"] }}" loading="lazy" decoding="async">@endif
                                                <figcaption class="dr-lineup__meta">
                                                    <strong>{{ $artist['name'] }}</strong>
                                                    @if($artist['subtitle'])<span>{{ $artist['subtitle'] }}</span>@endif
                                                </figcaption>
                                            </figure>
                                        @endforeach
                                    </div>
                                </section>
                                @endif
                                {{-- The plain "Venue" row card was removed: it duplicated the
                                     Venue Location map card above (same name/address/Directions).
                                     The map card is the single source now, mirroring the app. --}}
                            </div>
                        </section>
                    </div>

                    <div class="dr-organizer-desk">
                        <section>
                            <h3 class="dr-section-title" style="margin-bottom: 16px; font-size: 18px; font-weight: 800; letter-spacing: -0.02em; text-transform: none; color: #121620;">Organized by</h3>
                            <div class="dr-organizer-card" style="background: #ffffff; border: 1px solid var(--dr-border); border-radius: 24px; padding: 24px; display: flex; align-items: center; gap: 24px; box-shadow: 0 6px 24px rgba(0, 0, 0, 0.02); min-height: 180px;">
                                {{-- Left column: Avatar and Name --}}
                                    <div style="display: flex; flex-direction: column; align-items: center; text-align: center; gap: 12px; width: 45%;">
                                    @php $orgDesk = $event->organiserCard(); @endphp
                                    <div style="width: 84px; height: 84px; border-radius: 50%; overflow: hidden; border: 2px solid #ffffff; box-shadow: 0 4px 12px rgba(0,0,0,0.08); background: linear-gradient(140deg,#2563eb,#1e3fa8); display: flex; align-items: center; justify-content: center; color:#fff; font-size:30px; font-weight:800;">
                                        @if($orgDesk['logo'])
                                            <img src="{{ $orgDesk['logo'] }}" alt="{{ $orgDesk['name'] }}" style="width: 100%; height: 100%; object-fit: cover;" loading="lazy" decoding="async">
                                        @else
                                            {{ $orgDesk['initial'] }}
                                        @endif
                                    </div>
                                    <div class="dr-organizer-name" style="font-size: 12px; font-weight: 900; color: #121620; text-transform: uppercase; letter-spacing: 0.05em; line-height: 1.2;">
                                        {{ Str::limit($orgDesk['name'], 14, '...') }}
                                    </div>
                                    @if($orgDesk['url'])<a href="{{ $orgDesk['url'] }}" style="font-size:11px;font-weight:700;color:#1e50e6;text-decoration:none;">View page →</a>@endif
                                </div>

                                {{-- Right column: real event facts (no fabricated stats) --}}
                                <div style="display: flex; flex-direction: column; flex-grow: 1; gap: 0; width: 55%;">
                                    <div style="padding: 10px 0; border-bottom: 1px solid #f3f4f6; font-size: 13.5px; font-family: 'Inter', sans-serif;">
                                        <span class="dr-stat-value">{{ $event->category ?: 'Event' }}</span> <span class="dr-stat-label">category</span>
                                    </div>
                                    <div style="padding: 10px 0; border-bottom: 1px solid #f3f4f6; font-size: 13.5px; font-family: 'Inter', sans-serif;">
                                        <span class="dr-stat-value">{{ optional($event->date)->format('D, d M') ?: 'TBA' }}</span> <span class="dr-stat-label">date</span>
                                    </div>
                                    <div style="padding: 10px 0; font-size: 13.5px; font-family: 'Inter', sans-serif;">
                                        <span class="dr-stat-value">{{ Str::limit($event->venue ?: 'Venue', 16) }}</span> <span class="dr-stat-label">venue</span>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>

                {{-- "Who takes the stage" — desktop grid variant (mobile uses the
                     snap rail inside .dr-mobrows, matching the app's coverflow). --}}
                @php $lineupRowsDesk = $event->lineupRows(); @endphp
                @if(count($lineupRowsDesk))
                <section class="dr-lineup-desk" style="margin-top: 8px; margin-bottom: 8px;">
                    <h3 class="dr-section-title" style="margin-bottom: 20px; font-size: 18px; font-weight: 800; letter-spacing: -0.02em; text-transform: none;">Who takes the stage</h3>
                    <div class="dr-lineup-desk__row">
                        @foreach($lineupRowsDesk as $artist)
                            <figure class="dr-lineup__card">
                                @if($artist["image"])<img src="{{ $artist["image"] }}" alt="{{ $artist["name"] }}" loading="lazy" decoding="async">@endif
                                <figcaption class="dr-lineup__meta">
                                    <strong>{{ $artist['name'] }}</strong>
                                    @if($artist['subtitle'])<span>{{ $artist['subtitle'] }}</span>@endif
                                </figcaption>
                            </figure>
                        @endforeach
                    </div>
                </section>
                @endif

                {{-- Highlights: only when the event carries real notes. Previously
                     this was hardcoded boilerplate ("world-class bowling…") shown on
                     every event plus a fake carousel — removed to stay honest.
                     Real highlights live in the "Know Before You Go" tab. --}}
                @php $highlights = $event->infoNoteRows(); @endphp
                @if(!empty($highlights))
                <section class="dr-highlights" style="margin-top: 8px; margin-bottom: 8px;">
                    <h3 class="dr-section-title" style="margin-bottom: 20px; font-size: 18px; font-weight: 800; letter-spacing: -0.02em; text-transform: none;">Highlights</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 24px;">
                        @foreach(array_slice($highlights, 0, 4) as $note)
                            <div style="position: relative; border: 1px solid var(--dr-border); padding: 24px; border-radius: 12px; background: #ffffff; min-height: 96px; display: flex; align-items: center; gap: 8px;">
                                <p style="margin: 0; font-size: 13.5px; line-height: 1.6; color: #555555;">{{ is_array($note) ? ($note['text'] ?? ($note['title'] ?? '')) : $note }}</p>
                            </div>
                        @endforeach
                    </div>
                </section>
                @endif

                {{-- Gallery: the host-curated showcase photos (the `gallery`
                     column, managed from the partner console's Gallery step),
                     separate from the poster. Hidden when the host added none. --}}
                @php $gallery = $event->galleryUrls(); @endphp
                @if(count($gallery) >= 1)
                <section class="dr-gallery-desk" style="margin-top: 12px; margin-bottom: 8px;">
                    <h3 class="dr-section-title" style="margin-bottom: 20px; font-size: 18px; font-weight: 800; letter-spacing: -0.02em; text-transform: none;">Gallery</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
                        @foreach(array_slice($gallery, 0, 6) as $img)
                            <div style="border-radius: 16px; overflow: hidden; height: 180px; border: 1px solid var(--dr-border);">
                                <img src="{{ $img }}" alt="{{ $event->title }}" loading="lazy" style="width: 100%; height: 100%; object-fit: cover;">
                            </div>
                        @endforeach
                    </div>
                </section>
                @endif
            </div>

            {{-- Content Pane: Know Before You Go --}}
            @php
                $gtkRows = $event->goodToKnowRows();
                $infoNotes = $event->infoNoteRows();
                $hasAdminKnow = count($gtkRows) > 0 || count($infoNotes) > 0;
                // Line icons keyed to the same taxonomy the app uses (EventGoodToKnowCard).
                $gtkIcon = function (string $key): string {
                    $a = 'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"';
                    $p = [
                        'language' => '<circle cx="12" cy="12" r="9"/><line x1="3" y1="12" x2="21" y2="12"/><path d="M12 3a15 15 0 0 1 0 18 15 15 0 0 1 0-18z"/>',
                        'duration' => '<circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/>',
                        'age'      => '<path d="M12 3l7 3v5c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6z"/><polyline points="9 12 11 14 15 10"/>',
                        'entry'    => '<path d="M4 9a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2 2 2 0 0 0 0 4 2 2 0 0 1-2 2H6a2 2 0 0 1-2-2 2 2 0 0 0 0-4z"/><line x1="14" y1="7" x2="14" y2="17" stroke-dasharray="1.5 2.5"/>',
                        'layout'   => '<polygon points="12 2 22 8.5 12 15 2 8.5 12 2"/><polyline points="2 15.5 12 22 22 15.5"/>',
                        'seating'  => '<path d="M6 10V7a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v3"/><path d="M4 10h16v5H4z"/><line x1="6" y1="15" x2="6" y2="20"/><line x1="18" y1="15" x2="18" y2="20"/>',
                        'kids'     => '<circle cx="12" cy="12" r="9"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/>',
                        'pets'     => '<circle cx="11" cy="4" r="1.6"/><circle cx="18" cy="8" r="1.6"/><circle cx="6" cy="8" r="1.6"/><path d="M12 10c2.5 0 5 2.5 5 5s-2.5 4-5 4-5-1-5-4 2.5-5 5-5z"/>',
                        'info'     => '<circle cx="12" cy="12" r="9"/><line x1="12" y1="11" x2="12" y2="16"/><line x1="12" y1="8" x2="12.01" y2="8"/>',
                    ];
                    $path = $p[$key] ?? $p['info'];
                    return '<svg viewBox="0 0 24 24" '.$a.'>'.$path.'</svg>';
                };
            @endphp
            <div id="pane-know" class="dr-tab-pane" style="display: none; margin-top: 24px;">

                {{-- Admin-authored "Good to Know" — attribute grid + T&C notes.
                     Falls back to the generic cards below when the host set nothing. --}}
                @if($hasAdminKnow)
                    @if(count($gtkRows) > 0)
                        <section style="margin-bottom: 28px;">
                            <h3 class="dr-section-title" style="margin-bottom: 16px; font-size: 18px; font-weight: 800; letter-spacing: -0.02em; text-transform: none; color: #121620;">Good to Know</h3>
                            <div class="dr-gtk__card">
                                <div class="dr-gtk__grid">
                                    @foreach($gtkRows as $row)
                                        <div class="dr-gtk__cell">
                                            <span class="dr-gtk__ico">{!! $gtkIcon($row['icon']) !!}</span>
                                            <span class="dr-gtk__txt">
                                                <small>{{ $row['label'] }}</small>
                                                <strong>{{ $row['value'] }}</strong>
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </section>
                    @endif

                    @if(count($infoNotes) > 0)
                        <section class="dr-impinfo" style="margin-bottom: 32px;">
                            <h3 class="dr-section-title" style="margin-bottom: 16px; font-size: 18px; font-weight: 800; letter-spacing: -0.02em; text-transform: none; color: #121620;">Important Information</h3>
                            <ul style="margin: 0; padding-left: 20px; color: var(--dr-text-mute); font-size: 14px; line-height: 1.9;">
                                @foreach($infoNotes as $note)
                                    <li>{{ $note }}</li>
                                @endforeach
                            </ul>
                        </section>
                    @endif
                @else
                <div class="dr-know-fallback" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px; margin-bottom: 32px;">

                    {{-- Entry & Timing --}}
                    <div style="border: 1px solid var(--dr-border); padding: 24px; border-radius: 0px; background: #ffffff;">
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 14px;">
                            <svg width="20" height="20" fill="none" stroke="#000000" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                            <h4 style="margin: 0; font-size: 14px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.05em; color: #000000;">Entry & Timings</h4>
                        </div>
                        <p style="margin: 0; font-size: 14px; line-height: 1.6; color: var(--dr-text-mute);">Gates open exactly 90 minutes before the scheduled start. We recommend early arrival to facilitate smooth entry. Latecomers may be restricted once the performance commences.</p>
                    </div>

                    {{-- Dynamic Ticket Policy --}}
                    <div style="border: 1px solid var(--dr-border); padding: 24px; border-radius: 0px; background: #ffffff;">
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 14px;">
                            <svg width="20" height="20" fill="none" stroke="#000000" stroke-width="2.5" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"></rect><line x1="16" y1="2" x2="16" y2="4"></line><line x1="8" y1="2" x2="8" y2="4"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                            <h4 style="margin: 0; font-size: 14px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.05em; color: #000000;">Digital Ticket Policy</h4>
                        </div>
                        <p style="margin: 0; font-size: 14px; line-height: 1.6; color: var(--dr-text-mute);">Keep your dynamic QR ticket ready on your mobile screen at entry gates. Printed PDFs or static screenshots are strictly invalid as secure QR codes refresh automatically.</p>
                    </div>

                    {{-- Prohibited Items --}}
                    <div style="border: 1px solid var(--dr-border); padding: 24px; border-radius: 0px; background: #ffffff;">
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 14px;">
                            <svg width="20" height="20" fill="none" stroke="#000000" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
                            <h4 style="margin: 0; font-size: 14px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.05em; color: #000000;">Prohibited Items</h4>
                        </div>
                        <p style="margin: 0; font-size: 14px; line-height: 1.6; color: var(--dr-text-mute);">Professional cameras, recorders, outside food or drinks, plastic bags, and large luggage are strictly banned inside the arena. Mandatory security check is active.</p>
                    </div>

                    {{-- Venue & Parking --}}
                    <div style="border: 1px solid var(--dr-border); padding: 24px; border-radius: 0px; background: #ffffff;">
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 14px;">
                            <svg width="20" height="20" fill="none" stroke="#000000" stroke-width="2.5" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                            <h4 style="margin: 0; font-size: 14px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.05em; color: #000000;">Venue & Parking</h4>
                        </div>
                        <p style="margin: 0; font-size: 14px; line-height: 1.6; color: var(--dr-text-mute);">On-site parking is limited and operates on a first-come, first-served basis. Cabs, local transport, or rideshares are strongly recommended for seamless travel.</p>
                    </div>

                </div>
                @endif
            </div>

            {{-- Mobile-only Gallery — swipeable rail, LAST section on the sheet
                 (the stacked full-width version read like three extra heroes
                 mid-page). Tap a card for the fullscreen lightbox. --}}
            @if(count($gallery) >= 1)
            <section class="dr-mgal">
                <h3 class="dr-section-title" style="margin-bottom: 12px;">Gallery <span class="dr-mgal__count">{{ count($gallery) }} {{ \Illuminate\Support\Str::plural('photo', count($gallery)) }}</span></h3>
                <div class="dr-mgal__rail">
                    @foreach(array_slice($gallery, 0, 8) as $img)
                        <button type="button" class="dr-mgal__item" onclick="drLbx(this)" aria-label="View photo full screen">
                            <img src="{{ $img }}" alt="{{ $event->title }}" loading="lazy" decoding="async">
                        </button>
                    @endforeach
                </div>
            </section>
            @endif

            {{-- FAQs — always the last section on the mobile sheet. --}}
            @php $faqs = collect($event->faqRows()); @endphp
            @if($faqs->isNotEmpty())
            <style>
                /* Mobile: this is the last block on the sheet, so it has to sit on
                   the same 20px gutter as every section above it — full-bleed rows
                   under padded copy is what made the page look like it fell off a
                   ledge at the bottom. */
                .dr-faq{padding:0 20px;}
                /* One hairline-divided stack, not a column of floating boxes — the
                   app's Important Information card reads the same way. */
                .dr-faq__list{border:1px solid #E2E8F0;border-radius:16px;background:#fff;overflow:hidden;}
                .dr-faq__item + .dr-faq__item{border-top:1px solid #EEF2F7;}
                .dr-faq__q{list-style:none;cursor:pointer;display:flex;align-items:flex-start;gap:12px;
                    padding:15px 16px;font-size:14.5px;font-weight:700;line-height:1.45;
                    letter-spacing:-.01em;color:#121620;-webkit-tap-highlight-color:transparent;}
                .dr-faq__q::-webkit-details-marker{display:none;}
                .dr-faq__item[open] .dr-faq__q{padding-bottom:9px;}
                .dr-faq__chev{margin-left:auto;flex:none;width:18px;height:18px;margin-top:1px;
                    color:#94A3B8;transition:transform .2s ease;}
                .dr-faq__chev svg{width:100%;height:100%;display:block;}
                .dr-faq__item[open] .dr-faq__chev{transform:rotate(180deg);color:#2563EB;}
                .dr-faq__a{padding:0 44px 16px 16px;font-size:13.5px;line-height:1.65;color:#64748B;}
                /* The sheet used to stop dead here and hand the reader ~96px of
                   blank white (the reserve for the book bar, which hides on
                   scroll-down). One quiet line closes the page and routes the
                   questions the host didn't answer. Flat link, not a boxed CTA. */
                .dr-faq__end{margin:14px 0 0;text-align:center;font-size:12.5px;color:#94A3B8;}
                .dr-faq__end a{color:#2563EB;font-weight:700;text-decoration:none;}
            </style>
            <section class="dr-faq" style="margin-top:26px;">
                <h3 class="dr-section-title" style="margin-bottom:12px;">Frequently asked questions</h3>
                <div class="dr-faq__list">
                    @foreach($faqs as $f)
                    <details class="dr-faq__item">
                        <summary class="dr-faq__q">{{ $f['question'] }}<span class="dr-faq__chev" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg></span></summary>
                        <div class="dr-faq__a">{!! nl2br(e($f['answer'])) !!}</div>
                    </details>
                    @endforeach
                </div>
                @php $faqOrg = $event->organiserCard(); @endphp
                <p class="dr-faq__end">Still have a question?
                    @if($faqOrg['url'])
                        <a href="{{ $faqOrg['url'] }}">Ask {{ $faqOrg['name'] }}</a>
                    @else
                        <a href="{{ url('/support') }}">Contact support</a>
                    @endif
                </p>
            </section>
            @endif

            </div>{{-- /.dr-sheet --}}

            {{-- =====================================================================
                 DESKTOP BUILD (≥1025px only; hidden on phones via CSS). A fully
                 separate, handcrafted layout so the app-parity mobile sheet above
                 is never touched. Re-renders the same $event data — the Book CTAs
                 open the shared .dr-tix ticket modal (drTixToggle).
                 ===================================================================== --}}
            @php
                // Buyable now (visible + in its sales window + release phase opened) and
                // the tiers a later phase still holds back — shown, but locked.
                $dkTiers        = $event->saleableTicketTypes();
                $dkLocked       = $event->lockedTicketTypes();
                $dkFrom         = $dkTiers->count() ? (float) $dkTiers->min(fn ($t) => $t->effectivePrice()) : (float) $event->price;
                $dkSoldOut      = $event->soldOut();
                $dkSalesClosed  = $event->ticketTypes->isNotEmpty() && $dkTiers->isEmpty() && $dkLocked->isEmpty();
                $dkPriceLabel   = $dkFrom > 0 ? '₹' . number_format($dkFrom) : 'Free';
                $dkPriceSuffix  = $dkTiers->count() > 1 ? 'onwards' : ($dkFrom > 0 ? 'per ticket' : 'entry');
                $dkLineup       = $event->lineupRows();
                $dkGallery      = $event->galleryUrls();
                $dkGtk          = $event->goodToKnowRows();
                $dkNotes        = $event->infoNoteRows();
                $dkMapEmbed     = $event->mapEmbedUrl();
                // Always show a real Google map on desktop: prefer the keyed Embed API
                // when configured, else the keyless embed (works with no API key and no
                // stored coordinates — searches the venue/address text).
                $dkMapSrc       = $dkMapEmbed ?: 'https://maps.google.com/maps?q=' . rawurlencode($event->mapsQuery()) . '&z=15&output=embed';
                $dkDirections   = $event->directionsUrl();
                $dkAmbiance     = $event->venuePhotos();
                $dkVenueName    = $event->venue ?: ($event->city ?: 'Venue');
                $dkVenueAddr    = $event->location ?: ($event->city ? $event->city . ', India' : 'India');
                // The real organiser (host profile → partner account), NOT the
                // lineup artist this used to read. See Event::organiserCard().
                $dkOrg          = $event->organiserCard();
                $dkHeroImg      = $event->heroImageUrl() ?? asset('events.png');
                // Hosts append the venue to the title for search ("… | Jollygunj,
                // Whitefield"), so the H1 printed the location the fact row prints
                // again 20px below it — and the extra words pushed the display type
                // to three lines. Drop a trailing pipe segment ONLY when every word
                // in it already appears in the venue/city line, so a title whose
                // tail carries real information is never touched.
                $dkTitle = trim((string) $event->title);
                if (str_contains($dkTitle, '|')) {
                    $dkHead = trim(\Illuminate\Support\Str::beforeLast($dkTitle, '|'));
                    $dkTail = trim(\Illuminate\Support\Str::afterLast($dkTitle, '|'));
                    $dkPlace = \Illuminate\Support\Str::lower(($event->venue ?: '') . ' ' . ($event->city ?: ''));
                    $dkTailWords = preg_split('/[^\p{L}\p{N}]+/u', \Illuminate\Support\Str::lower($dkTail), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                    $dkDupe = $dkTailWords !== [] && collect($dkTailWords)
                        ->every(fn ($w) => mb_strlen($w) < 3 || str_contains($dkPlace, $w));
                    if ($dkHead !== '' && $dkDupe) {
                        $dkTitle = $dkHead;
                    }
                }
                $dkDateLong     = optional($event->date)->format('l, d M Y');
                $dkDateShort    = optional($event->date)->format('D, d M');
                $dkTime         = trim((string) ($event->time ?? ''));
                // Desktop fact row / "Starts at" card show the full range too.
                $dkRange        = $event->timeRangeLabel();
                $dkDesc         = trim(strip_tags((string) $event->description));
                // Hosts paste plain text with bare line breaks, which rendered as one
                // unspaced run. Wrap those lines in real paragraphs; descriptions that
                // already carry block HTML are passed through untouched.
                $dkAboutHtml    = preg_match('/<(p|ul|ol|h[1-6]|div|br)\b/i', (string) $event->description)
                    ? (string) $event->description
                    : collect(preg_split('/\R+/u', (string) $event->description) ?: [])
                        ->map(fn ($line) => trim((string) $line))
                        ->filter()
                        ->map(fn ($line) => '<p>' . e($line) . '</p>')
                        ->implode('');
                // A finished event can't be sold (the booking route 404s it), so the
                // CTA must say so rather than sending the buyer into a dead end.
                $dkEnded        = $event->hasFinished();
                $dkBookDisabled = $dkEnded || $dkSoldOut || $dkSalesClosed;
                $dkBookLabel    = $dkEnded
                    ? 'Event ended'
                    : ($dkSoldOut ? 'Sold out' : ($dkSalesClosed ? 'Sales closed' : 'Book tickets'));
                // Honest urgency: only when the real remaining slot count is genuinely low.
                $dkUrgent       = ! $dkBookDisabled && (int) $event->available_slots > 0 && (int) $event->available_slots <= 20;
            @endphp
            <div class="dr-desk">
                <header class="dk-hero">
                    <div class="dk-hero__bg" style="background-image:url('{{ $dkHeroImg }}')"></div>
                    {{-- Background texture for the card's open right side: a soft brand
                         glow plus flowing contour lines. Anchored right (xMaxYMid slice)
                         so it fills the empty area and stays clear of the copy; every
                         stroke fades out at both ends so nothing reads as a hard shape.
                         Purely decorative — aria-hidden, pointer-events off, behind
                         .dk-hero__inner. --}}
                    <div class="dk-hero__deco" aria-hidden="true">
                        {{-- viewBox matches the card's own 1200×490 so the curves can be
                             placed against measured copy: the text block occupies
                             x 397–1151, y 153–337, so the lines run only in the empty
                             bands above (y<145) and below it (y>360). --}}
                        <svg viewBox="0 0 1200 490" preserveAspectRatio="xMaxYMid slice" fill="none">
                            <defs>
                                <radialGradient id="dkHeroGlow" cx="80%" cy="32%" r="58%">
                                    <stop offset="0" stop-color="#2563EB" stop-opacity="0.11"/>
                                    <stop offset="1" stop-color="#2563EB" stop-opacity="0"/>
                                </radialGradient>
                                <linearGradient id="dkHeroLine" x1="0" y1="0" x2="1" y2="0">
                                    <stop offset="0" stop-color="#2563EB" stop-opacity="0"/>
                                    <stop offset="0.40" stop-color="#2563EB" stop-opacity="0.8"/>
                                    <stop offset="0.76" stop-color="#60A5FA" stop-opacity="0.6"/>
                                    <stop offset="1" stop-color="#60A5FA" stop-opacity="0"/>
                                </linearGradient>
                                {{-- One curve each for the lower and upper band; the bands
                                     below are that same curve stepped down 13px at a time,
                                     so the lines stay exactly parallel. Even repetition is
                                     what makes it read as a pattern rather than as a few
                                     stray strokes. --}}
                                <path id="dkWaveLow" d="M260 452C500 452 590 392 810 392S1030 356 1260 352"/>
                                <path id="dkWaveTop" d="M310 -10C540 -10 630 52 850 52S1060 88 1260 92"/>
                            </defs>
                            <rect width="1200" height="490" fill="url(#dkHeroGlow)"/>
                            {{-- Opacity swells toward the middle of each bundle so it
                                 reads with depth instead of as a flat comb. --}}
                            <g stroke="url(#dkHeroLine)" stroke-width="1.1">
                                <use href="#dkWaveLow" y="0" opacity="0.30"/>
                                <use href="#dkWaveLow" y="13" opacity="0.55"/>
                                <use href="#dkWaveLow" y="26" opacity="0.85"/>
                                <use href="#dkWaveLow" y="39" opacity="1"/>
                                <use href="#dkWaveLow" y="52" opacity="0.85"/>
                                <use href="#dkWaveLow" y="65" opacity="0.55"/>
                                <use href="#dkWaveLow" y="78" opacity="0.30"/>
                                <use href="#dkWaveTop" y="0" opacity="0.28"/>
                                <use href="#dkWaveTop" y="13" opacity="0.5"/>
                                <use href="#dkWaveTop" y="26" opacity="0.8"/>
                                <use href="#dkWaveTop" y="39" opacity="0.6"/>
                                <use href="#dkWaveTop" y="52" opacity="0.32"/>
                            </g>
                        </svg>
                    </div>
                    {{-- Share sits in the card's top-right corner, out of the reading
                         order of chip → title → facts. --}}
                    <button type="button" class="dk-iconbtn dk-share" aria-label="Share" onclick="drShare()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.6" y1="13.5" x2="15.4" y2="17.5"/><line x1="15.4" y1="6.5" x2="8.6" y2="10.5"/></svg>
                    </button>
                    <div class="dk-hero__inner">
                        <div class="dk-poster">
                            <img src="{{ $dkHeroImg }}" alt="{{ $event->title }}" fetchpriority="high" decoding="async">
                        </div>
                        <div class="dk-info">
                            <div class="dk-chips">
                                <span class="dk-chip dk-chip--cat">{{ $event->category ?: 'Event' }}</span>
                                @if(!empty($event->rating) && $event->rating > 0)
                                    <span class="dk-chip dk-chip--rate"><i>★</i>{{ number_format($event->rating, 1) }}@if($event->ratings_count) · {{ $event->ratings_count }} rating{{ $event->ratings_count == 1 ? '' : 's' }}@endif</span>
                                @endif
                            </div>
                            <h1 class="dk-title @if(mb_strlen($dkTitle) > 52) dk-title--long @endif">{{ $dkTitle }}</h1>
                            <div class="dk-facts">
                                @if($dkDateLong)
                                <span class="dk-fact">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                    {{ $dkDateLong }}
                                </span>
                                @endif
                                @if($dkTime)
                                <span class="dk-fact">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/></svg>
                                    {{ $dkRange ?: $dkTime }}
                                </span>
                                @endif
                                @if($event->venue || $event->city)
                                <span class="dk-fact">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                    {{ trim(($event->venue ?: '') . ($event->venue && $event->city ? ', ' : '') . ($event->city ?: ''), ', ') }}
                                </span>
                                @endif
                            </div>
                        </div>
                    </div>
                </header>

                <div class="dk-body">
                    {{-- Left: editorial content column --}}
                    <div class="dk-main">
                        @if($dkDesc !== '')
                        <section class="dk-sec">
                            <div class="dk-h"><h2>About</h2></div>
                            <div class="dk-about-wrap" id="dkAboutWrap">
                                <div class="dk-about">{!! $dkAboutHtml !!}</div>
                                <button type="button" class="dk-readmore" id="dkAboutToggle" onclick="dkToggleAbout()" hidden>
                                    <span class="lbl">Read more</span>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
                                </button>
                            </div>
                        </section>
                        @endif

                        @if(count($dkLineup))
                        <section class="dk-sec">
                            {{-- No count beside the heading: with one artist it just
                                 advertised how thin the lineup is. --}}
                            <div class="dk-h"><h2>Lineup</h2></div>
                            <div class="dk-artists">
                                @foreach($dkLineup as $artist)
                                    <div class="dk-artist">
                                        <div class="dk-artist__ph">
                                            @if($artist['image'])
                                                <img src="{{ $artist['image'] }}" alt="{{ $artist['name'] }}" loading="lazy" decoding="async">
                                            @endif
                                        </div>
                                        <strong>{{ $artist['name'] }}</strong>
                                        @if($artist['subtitle'])<span>{{ $artist['subtitle'] }}</span>@endif
                                    </div>
                                @endforeach
                            </div>
                        </section>
                        @endif

                        @if(count($dkGallery) >= 1)
                        <section class="dk-sec">
                            <div class="dk-h"><h2>Gallery</h2></div>
                            <div class="dk-gallery">
                                @foreach(array_slice($dkGallery, 0, 5) as $img)
                                    <button type="button" onclick="drLbx(this)" aria-label="View photo full screen">
                                        <img src="{{ $img }}" alt="{{ $event->title }}" loading="lazy" decoding="async">
                                    </button>
                                @endforeach
                            </div>
                        </section>
                        @endif

                        @if($event->venue || $event->city)
                        <section class="dk-sec">
                            <div class="dk-h"><h2>Location</h2></div>
                            <div class="dk-venue">
                                <iframe src="{{ $dkMapSrc }}" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen title="Venue location map"></iframe>
                                <div class="dk-venue__ft">
                                    <div style="min-width:0;">
                                        <h4>{{ $dkVenueName }}</h4>
                                        <p>{{ $dkVenueAddr }}</p>
                                    </div>
                                    <a class="dk-dir" href="{{ $dkDirections }}" target="_blank" rel="noopener">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg>
                                        Directions
                                    </a>
                                </div>
                            </div>
                            @if(count($dkAmbiance))
                            {{-- Venue photos sit BELOW the location card: the map places
                                 the venue, then the photos show it. Contributor names
                                 are carried as tooltips, not printed over each tile —
                                 strangers' names captioning the photos read scraped. --}}
                            <div class="dk-amb__wrap">
                                <div class="dk-amb__hd">At the venue <span>Photos from Google</span></div>
                                <div class="dk-amb__vp">
                                    <div class="dk-amb">
                                        @foreach($dkAmbiance as $photo)
                                            <button type="button" onclick="drLbx(this)" aria-label="View venue photo full screen"
                                                @if($photo['credit'] !== '') title="Photo by {{ $photo['credit'] }}" @endif>
                                                <img src="{{ $photo['url'] }}" alt="Inside {{ $dkVenueName }}" loading="lazy" decoding="async">
                                            </button>
                                        @endforeach
                                    </div>
                                    <button type="button" class="dk-amb__nav dk-amb__nav--prev" onclick="dkAmbNudge(this,-1)" aria-label="Previous photos" hidden>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
                                    </button>
                                    <button type="button" class="dk-amb__nav dk-amb__nav--next" onclick="dkAmbNudge(this,1)" aria-label="Next photos" hidden>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
                                    </button>
                                </div>
                            </div>
                            @endif
                        </section>
                        @endif

                        @if(count($dkGtk))
                        <section class="dk-sec dk-gtk">
                            <div class="dk-h"><h2>Good to know</h2></div>
                            <div class="dr-gtk__card">
                                <div class="dr-gtk__grid">
                                    @foreach($dkGtk as $row)
                                        <div class="dr-gtk__cell">
                                            <span class="dr-gtk__ico">{!! $gtkIcon($row['icon']) !!}</span>
                                            <span class="dr-gtk__txt">
                                                <small>{{ $row['label'] }}</small>
                                                <strong>{{ $row['value'] }}</strong>
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </section>
                        @endif

                        @if(count($dkNotes))
                        <section class="dk-sec">
                            <div class="dk-h"><h2>Terms &amp; entry rules</h2></div>
                            <ul class="dk-notes">
                                @foreach($dkNotes as $note)<li>{{ $note }}</li>@endforeach
                            </ul>
                        </section>
                        @endif

                        {{-- No "Ratings" section: with no review text to show, it was a
                             whole band of page restating the ★4.8 · 173 ratings chip
                             already in the hero, wrapped in invented praise copy. The
                             chip carries the fact until real reviews land here. --}}
                        {{-- FAQs — last section in the desktop main column (reuses the
                             .dr-faq styles emitted with the mobile sheet). --}}
                        @if($faqs->isNotEmpty())
                        <section class="dk-sec">
                            <div class="dk-h"><h2>Frequently asked questions</h2></div>
                            <div class="dr-faq__list">
                                @foreach($faqs as $f)
                                <details class="dr-faq__item">
                                    <summary class="dr-faq__q">{{ $f['question'] }}<span class="dr-faq__chev" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg></span></summary>
                                    <div class="dr-faq__a">{!! nl2br(e($f['answer'])) !!}</div>
                                </details>
                                @endforeach
                            </div>
                        </section>
                        @endif
                    </div>

                    {{-- Right: sticky booking rail — an inline ticket selector that
                         posts to the same auth-gated checkout the mobile modal uses. --}}
                    <aside class="dk-rail">
                        {{-- Buy card: price + one CTA. The inline stepper/total that used
                             to live here is gone — tier selection happens in the shared
                             .dr-tix modal the hero CTA already opens, so there is one
                             place to pick tickets instead of two competing ones. --}}
                        <div class="dk-buy">
                            @if($dkUrgent)<span class="dk-buy__urgent">● Selling fast</span>@endif
                            <div class="dk-buy__row">
                                <span class="dk-buy__price">
                                    <b>{{ $dkPriceLabel }}</b>
                                    @if($dkFrom > 0)<small>{{ $dkPriceSuffix }}</small>@endif
                                </span>
                                @if($dkBookDisabled)
                                    <span class="dk-buy__off">{{ $dkBookLabel }}</span>
                                @else
                                    <button type="button" class="dk-btn dk-buy__cta" onclick="drTixToggle(true)">Book tickets</button>
                                @endif
                            </div>
                            @unless($dkBookDisabled)
                                <div class="dk-buy__sec">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                    Secure checkout · Instant confirmation
                                </div>
                            @endunless
                        </div>

                        {{-- Where and when, as rows. Only the venue row carries a chevron:
                             it opens directions. The time row has nowhere to go, and a
                             chevron that leads nowhere is worse than none. --}}
                        <div class="dk-rows">
                            <a class="dk-row" href="{{ $dkDirections }}" target="_blank" rel="noopener">
                                <span class="dk-row__ic">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                </span>
                                <span class="dk-row__tx">
                                    <strong>{{ trim($dkVenueName . ($event->city ? ', ' . $event->city : ''), ', ') }}</strong>
                                    <small>{{ $dkVenueAddr }}</small>
                                </span>
                                <svg class="dk-row__ch" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
                            </a>
                            @if($dkTime || $dkDateLong)
                            <div class="dk-row dk-row--static">
                                <span class="dk-row__ic">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                </span>
                                <span class="dk-row__tx">
                                    <strong>{{ $dkTime ? ($dkRange !== $dkTime ? $dkRange : 'Starts at ' . $dkTime) : $dkDateLong }}</strong>
                                    @if($dkTime && $dkDateLong)<small>{{ $dkDateLong }}</small>@endif
                                </span>
                            </div>
                            @endif
                        </div>


                        <div class="dk-org">
                            <div class="dk-org__hd">
                                <span class="dk-org__ava">
                                    @if($dkOrg['logo'])
                                        <img src="{{ $dkOrg['logo'] }}" alt="{{ $dkOrg['name'] }}" loading="lazy" decoding="async">
                                    @else
                                        {{ $dkOrg['initial'] }}
                                    @endif
                                </span>
                                <span class="dk-org__tx">
                                    <small>Organised by</small>
                                    <strong>
                                        {{ $dkOrg['name'] }}
                                        @if($dkOrg['verified'])
                                            <svg viewBox="0 0 24 24" fill="currentColor" aria-label="Verified organiser"><path d="M23 12l-2.44-2.79.34-3.69-3.61-.82-1.89-3.2L12 2.96 8.6 1.5 6.71 4.69 3.1 5.5l.34 3.7L1 12l2.44 2.79-.34 3.7 3.61.82L8.6 22.5l3.4-1.47 3.4 1.46 1.89-3.19 3.61-.82-.34-3.69L23 12zm-12.91 4.72l-3.8-3.81 1.48-1.48 2.32 2.33 5.85-5.87 1.48 1.48-7.33 7.35z"/></svg>
                                        @endif
                                    </strong>
                                </span>
                            </div>
                            @if($dkOrg['tagline'])
                                <p class="dk-org__bio">{{ $dkOrg['tagline'] }}</p>
                            @endif
                            @if($dkOrg['events'] > 0 || $dkOrg['followers'] > 0)
                            <div class="dk-org__stats">
                                @if($dkOrg['events'] > 0)
                                    <span><b>{{ $dkOrg['events'] }}</b> other {{ \Illuminate\Support\Str::plural('event', $dkOrg['events']) }}</span>
                                @endif
                                @if($dkOrg['followers'] > 0)
                                    <span><b>{{ $dkOrg['followers'] }}</b> {{ \Illuminate\Support\Str::plural('follower', $dkOrg['followers']) }}</span>
                                @endif
                            </div>
                            @endif
                            {{-- Follow is the primary action; the profile link rides
                                 beside it as plain text rather than a second button. --}}
                            @if($dkOrg['slug'])
                            <div class="dk-org__act">
                                @auth
                                    <form method="POST" action="{{ route('site.host.follow', ['slug' => $dkOrg['slug']]) }}">
                                        @csrf
                                        <button type="submit" class="dk-org__follow {{ $dkOrg['following'] ? 'is-on' : '' }}">
                                            {{ $dkOrg['following'] ? 'Following' : 'Follow' }}
                                        </button>
                                    </form>
                                @else
                                    <a class="dk-org__follow" href="{{ url('/login') }}">Follow</a>
                                @endauth
                                <a class="dk-org__link" href="{{ $dkOrg['url'] }}">View profile</a>
                            </div>
                            @endif
                        </div>
                    </aside>
                </div>

                {{-- Similar events — real published events from the controller ($similar). --}}
                @if(($similar ?? null) && count($similar))
                <section class="dk-more">
                    <h2 class="dk-more__h">Similar events</h2>
                    <div class="dk-cards">
                        @foreach($similar as $s)
                            @php
                                $sTiers = $s->relationLoaded('ticketTypes') ? $s->saleableTicketTypes() : collect();
                                $sFrom  = $sTiers->count() ? (float) $sTiers->min(fn ($t) => $t->effectivePrice()) : (float) $s->price;
                            @endphp
                            <a class="dk-card" href="/events/{{ $s->id }}">
                                <div class="dk-card__img">
                                    <img src="{{ $s->heroImageUrl() ?? asset('events.png') }}" alt="{{ $s->title }}" loading="lazy" decoding="async">
                                </div>
                                <div class="dk-card__b">
                                    <div class="dk-card__cat">{{ $s->category ?: 'Event' }}</div>
                                    <div class="dk-card__t">{{ $s->title }}</div>
                                    <div class="dk-card__m">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                        {{ optional($s->date)->format('D, d M') ?: 'TBA' }}@if($s->city) · {{ $s->city }}@endif
                                    </div>
                                    <div class="dk-card__p">{{ $sFrom > 0 ? 'From ₹'.number_format($sFrom) : 'Free' }}</div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </section>
                @endif
            </div>{{-- /.dr-desk --}}

            {{-- Desktop-only behaviour: the About clamp and gentle scroll-reveals.
                 Both Book CTAs open the shared .dr-tix modal. No effect on mobile. --}}
            <script>
                // Venue strip arrows. Scrolls by ~80% of the visible width so a tile
                // is never left half-cut, and hides whichever arrow points at an end
                // you've already reached.
                function dkAmbNudge(btn, dir) {
                    var strip = btn.parentElement.querySelector('.dk-amb');
                    if (!strip) return;
                    strip.scrollBy({ left: dir * Math.round(strip.clientWidth * 0.8), behavior: 'smooth' });
                }
                function dkAmbSync(strip) {
                    var vp = strip.parentElement;
                    var prev = vp.querySelector('.dk-amb__nav--prev');
                    var next = vp.querySelector('.dk-amb__nav--next');
                    // 2px of slack: fractional scroll widths never land exactly on the end.
                    var max = strip.scrollWidth - strip.clientWidth;
                    if (prev) prev.hidden = strip.scrollLeft <= 2;
                    if (next) next.hidden = max <= 2 || strip.scrollLeft >= max - 2;
                }
                function dkToggleAbout() {
                    var w = document.getElementById('dkAboutWrap');
                    var about = w.querySelector('.dk-about');
                    var b = document.getElementById('dkAboutToggle');
                    if (w.classList.contains('is-clamped')) {
                        w.classList.remove('is-clamped');
                        about.style.maxHeight = 'none';
                        b.querySelector('.lbl').textContent = 'Read less';
                        b.classList.add('is-open');
                    } else {
                        w.classList.add('is-clamped');
                        about.style.maxHeight = (w.dataset.max || '176') + 'px';
                        b.querySelector('.lbl').textContent = 'Read more';
                        b.classList.remove('is-open');
                        w.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }
                }
                // Mobile "Overview" — same clamp/reveal behaviour as the desktop "About".
                function drToggleAbout() {
                    var w = document.getElementById('drAboutWrap');
                    if (!w) return;
                    var about = w.querySelector('.dr-description');
                    var b = document.getElementById('drAboutToggle');
                    if (w.classList.contains('is-clamped')) {
                        w.classList.remove('is-clamped');
                        about.style.maxHeight = 'none';
                        b.querySelector('.lbl').textContent = 'Read less';
                        b.classList.add('is-open');
                    } else {
                        w.classList.add('is-clamped');
                        about.style.maxHeight = (w.dataset.max || '176') + 'px';
                        b.querySelector('.lbl').textContent = 'Read more';
                        b.classList.remove('is-open');
                        w.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }
                }
                document.addEventListener('DOMContentLoaded', function () {
                    // Clamp the mobile "Overview" to 6 lines when it overflows.
                    if (!window.matchMedia('(min-width: 1025px)').matches) {
                        var aw = document.getElementById('drAboutWrap');
                        if (aw) {
                            var dabout = aw.querySelector('.dr-description');
                            var dtog = document.getElementById('drAboutToggle');
                            var dlh = parseFloat(getComputedStyle(dabout).lineHeight) || 29;
                            var dmax = Math.round(dlh * 6);
                            aw.dataset.max = dmax;
                            if (dabout.scrollHeight > dmax + 8) {
                                aw.classList.add('is-clamped');
                                dabout.style.maxHeight = dmax + 'px';
                                if (dtog) dtog.hidden = false;
                            }
                        }
                    }
                    if (!window.matchMedia('(min-width: 1025px)').matches) return;
                    // "About" — clamp to 6 lines + reveal the Read more toggle only when
                    // the copy actually overflows (short descriptions stay fully shown).
                    var aw = document.getElementById('dkAboutWrap');
                    if (aw) {
                        var about = aw.querySelector('.dk-about');
                        var tog = document.getElementById('dkAboutToggle');
                        var lh = parseFloat(getComputedStyle(about).lineHeight) || 30;
                        var max = Math.round(lh * 6);
                        aw.dataset.max = max;
                        if (about.scrollHeight > max + 8) {
                            aw.classList.add('is-clamped');
                            about.style.maxHeight = max + 'px';
                            if (tog) tog.hidden = false;
                        }
                    }
                    // Venue strip: show the arrows only once we know it actually
                    // overflows, and keep them in sync as it scrolls or resizes.
                    document.querySelectorAll('.dr-desk .dk-amb').forEach(function (strip) {
                        dkAmbSync(strip);
                        strip.addEventListener('scroll', function () { dkAmbSync(strip); }, { passive: true });
                        window.addEventListener('resize', function () { dkAmbSync(strip); });
                        // Lazy tiles change scrollWidth as they decode.
                        strip.querySelectorAll('img').forEach(function (im) {
                            if (!im.complete) im.addEventListener('load', function () { dkAmbSync(strip); }, { once: true });
                        });
                    });

                    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                    if (!reduce && 'IntersectionObserver' in window) {
                        var io = new IntersectionObserver(function (entries) {
                            entries.forEach(function (e) {
                                if (e.isIntersecting) { e.target.classList.add('is-in'); io.unobserve(e.target); }
                            });
                        }, { rootMargin: '0px 0px -60px 0px' });
                        document.querySelectorAll('.dr-desk .dk-sec, .dr-desk .dk-more').forEach(function (s) {
                            s.classList.add('dk-reveal'); io.observe(s);
                        });
                    }
                });
            </script>
        </main>
    </div>

    {{-- Schedule bottom sheet (mobile only; app EventScheduleSheet) --}}
    @if(count($mSchedule))
    <div class="dr-sched__backdrop" onclick="drSchedToggle(false)"></div>
    <div class="dr-sched" role="dialog" aria-modal="true" aria-label="Event schedule">
        <div class="dr-sched__grab"></div>
        <h3>Schedule</h3>
        @foreach($mSchedule as $row)
            <div class="dr-schedrow">
                <span class="dr-schedrow__rail">
                    <span class="dr-schedrow__dot"></span>
                    @unless($loop->last)<span class="dr-schedrow__line"></span>@endunless
                </span>
                <span class="dr-schedrow__txt">
                    <span class="dr-schedrow__time">{{ $row['time'] }}</span>
                    @if($row['title'])<span class="dr-schedrow__title">{{ $row['title'] }}</span>@endif
                    @if($row['note'])<span class="dr-schedrow__note">{{ $row['note'] }}</span>@endif
                </span>
            </div>
        @endforeach
    </div>
    <script>
        function drSchedToggle(open) {
            document.querySelector('.dr-sched').classList.toggle('is-open', open);
            document.querySelector('.dr-sched__backdrop').classList.toggle('is-open', open);
            document.body.classList.toggle('dr-lock', open);
            // Give the sheet a history entry so Back closes it instead of leaving
            // the event page. See HaraanOverlay in site.js.
            if (open) window.HaraanOverlay.push('sched', () => drSchedToggle(false));
            else window.HaraanOverlay.pop('sched');
        }
    </script>
    @endif

    {{-- Ticket selection sheet (both breakpoints) — GET to the auth-gated
         checkout review, so guests bounce through /login and resume. --}}
    @php
        $tixTiers = $event->saleableTicketTypes();
        // Tiers a later release phase still holds back — listed, but not selectable.
        $tixLocked = $event->lockedTicketTypes();
        $tixSalesClosed = $event->ticketTypes->isNotEmpty() && $tixTiers->isEmpty() && $tixLocked->isEmpty();
        $tixSoldOut = $event->soldOut();
        // Already happened — nothing here is buyable, whatever the tiers say.
        $tixEnded = $event->hasFinished();
    @endphp
    <div class="dr-tix__backdrop" onclick="drTixToggle(false)"></div>
    <form class="dr-tix" method="GET" action="/events/{{ $event->id }}/book" role="dialog" aria-modal="true" aria-label="Select tickets">
        <div class="dr-tix__grab"></div>
        <h3>Select tickets</h3>
        @if($tixEnded)
            <p class="dr-tix__closed">This event has ended.</p>
        @elseif($tixSoldOut)
            <p class="dr-tix__closed">This event is sold out.</p>
        @elseif($tixSalesClosed)
            <p class="dr-tix__closed">Ticket sales are closed right now.</p>
        @elseif($tixTiers->count() || $tixLocked->count())
            @foreach($tixTiers as $tier)
                <div class="dr-tixrow" data-price="{{ $tier->effectivePrice() }}">
                    <div class="dr-tixrow__info">
                        <strong>{{ $tier->name }}</strong>
                        <small>{{ $tier->effectivePrice() > 0 ? '₹'.number_format($tier->effectivePrice()) : 'Free' }}</small>
                    </div>
                    <div class="dr-stepper">
                        <button type="button" onclick="drStep(this, -1)" aria-label="Fewer">−</button>
                        <input type="number" name="qty[{{ $tier->id }}]" value="0" min="0" max="10" readonly>
                        <button type="button" onclick="drStep(this, 1)" aria-label="More">+</button>
                    </div>
                </div>
            @endforeach
            @foreach($tixLocked as $tier)
                {{-- No qty input: a locked tier can't be added, so it can't reach checkout. --}}
                <div class="dr-tixrow dr-tixrow--soon">
                    <div class="dr-tixrow__info">
                        <strong>{{ $tier->name }}</strong>
                        <small>{{ $tier->effectivePrice() > 0 ? '₹'.number_format($tier->effectivePrice()) : 'Free' }}</small>
                        <span class="dr-tixrow__phase">{{ $event->phaseName((int) $tier->release_phase) ?? 'Next phase' }}</span>
                    </div>
                    <span class="dr-tixlock">🔒 {{ $event->phaseUnlockNote((int) $tier->release_phase) }}</span>
                </div>
            @endforeach
        @else
            <div class="dr-tixrow" data-price="{{ (float) $event->price }}">
                <div class="dr-tixrow__info">
                    <strong>Standard</strong>
                    <small>{{ $event->price ? '₹'.number_format($event->price) : 'Free' }}</small>
                </div>
                <div class="dr-stepper">
                    <button type="button" onclick="drStep(this, -1)" aria-label="Fewer">−</button>
                    <input type="number" name="qty[0]" value="0" min="0" max="10" readonly>
                    <button type="button" onclick="drStep(this, 1)" aria-label="More">+</button>
                </div>
            </div>
        @endif
        @unless($tixEnded || $tixSoldOut || $tixSalesClosed)
            <button type="submit" class="dr-tix__cta" disabled>Continue</button>
        @endunless
    </form>
    <script>
        function drTixToggle(open) {
            document.querySelector('.dr-tix').classList.toggle('is-open', open);
            document.querySelector('.dr-tix__backdrop').classList.toggle('is-open', open);
            document.body.classList.toggle('dr-lock', open);
            // The booking path: pressing Back here used to abandon the event page
            // entirely, mid-purchase. Now it just closes the ticket sheet.
            if (open) window.HaraanOverlay.push('tix', () => drTixToggle(false));
            else window.HaraanOverlay.pop('tix');
        }
        function drStep(btn, delta) {
            const input = btn.parentElement.querySelector('input');
            input.value = Math.min(10, Math.max(0, parseInt(input.value || '0', 10) + delta));
            drTixTotal();
        }
        function drTixTotal() {
            let total = 0, count = 0;
            document.querySelectorAll('.dr-tixrow[data-price]').forEach(function (row) {
                const qty = parseInt(row.querySelector('input').value || '0', 10);
                total += qty * parseFloat(row.dataset.price);
                count += qty;
            });
            const cta = document.querySelector('.dr-tix__cta');
            if (!cta) return;
            cta.disabled = count === 0;
            cta.textContent = count === 0 ? 'Continue'
                : (total > 0 ? 'Continue — ₹' + total.toLocaleString('en-IN') : 'Continue — Free');
            // Reflect the selection in the sticky bar so closing the sheet keeps context.
            const amt = document.querySelector('.dr-book-bar__amount');
            const lbl = document.querySelector('.dr-book-bar__label');
            if (amt && lbl) {
                if (!amt.dataset.base) { amt.dataset.base = amt.textContent; lbl.dataset.base = lbl.textContent; }
                if (count > 0) {
                    amt.textContent = total > 0 ? '₹' + total.toLocaleString('en-IN') : 'Free';
                    lbl.textContent = count + (count === 1 ? ' ticket' : ' tickets');
                } else {
                    amt.textContent = amt.dataset.base;
                    lbl.textContent = lbl.dataset.base;
                }
            }
        }
    </script>

    {{-- Photo lightbox (mobile gallery) --}}
    <div class="dr-lbx" onclick="drLbxClose()" role="dialog" aria-modal="true" aria-label="Photo viewer">
        <button type="button" class="dr-lbx__close" aria-label="Close">✕</button>
        <img src="" alt="{{ $event->title }}">
    </div>

    {{-- Sticky booking bar (mobile only) --}}
    @php
        // Price the bar off the tiers, never off events.price. An event authored in
        // Ticket Studio leaves that column at 0 and carries its real prices on the
        // ticket types, so reading it raw made every tiered event announce
        // "Free · entry" next to a live Book Tickets button while the sheet right
        // above it listed ₹ prices. Same rule $dkFrom already applies on desktop.
        //
        // Fall back to every tier (not just the saleable ones) so an event that is
        // sold out, sales-closed or still fully phase-locked shows what it costs
        // instead of reverting to "Free".
        $barTiers = $tixTiers->count() ? $tixTiers : $event->ticketTypes;
        $barFrom  = $barTiers->count()
            ? (float) $barTiers->min(fn ($t) => $t->effectivePrice())
            : (float) $event->price;
    @endphp
    <div class="dr-book-bar">
        <div class="dr-book-bar__price">
            <span class="dr-book-bar__amount">{{ $barFrom > 0 ? '₹'.number_format($barFrom) : 'Free' }}</span>
            <span class="dr-book-bar__label">{{ $barTiers->count() > 1 ? 'onwards' : ($barFrom > 0 ? 'per ticket' : 'entry') }}</span>
        </div>
        <button class="dr-book-bar__btn" type="button" onclick="drTixToggle(true)" @if($tixEnded || $tixSoldOut) disabled style="background:#CBD5E1;box-shadow:none;" @endif>{{ $tixEnded ? 'Event ended' : ($tixSoldOut ? 'Sold out' : 'Book Tickets') }}</button>
    </div>
</div>

<script>
    // Share: native sheet when available, clipboard + toast fallback.
    function drShare() {
        if (navigator.share) {
            navigator.share({ title: document.title, url: location.href }).catch(function () {});
        } else if (navigator.clipboard) {
            navigator.clipboard.writeText(location.href).then(function () { drToast('Link copied'); });
        }
    }
    // Gallery lightbox: reuses the body scroll lock (which also ducks the book bar).
    function drLbx(btn) {
        const box = document.querySelector('.dr-lbx');
        box.querySelector('img').src = btn.querySelector('img').src;
        box.classList.add('is-open');
        document.body.classList.add('dr-lock');
        window.HaraanOverlay.push('lbx', drLbxClose);
    }
    function drLbxClose() {
        document.querySelector('.dr-lbx').classList.remove('is-open');
        document.body.classList.remove('dr-lock');
        window.HaraanOverlay.pop('lbx');
    }
    function drToast(msg) {
        let t = document.querySelector('.dr-toast');
        if (!t) { t = document.createElement('div'); t.className = 'dr-toast'; document.body.appendChild(t); }
        t.textContent = msg;
        t.classList.add('is-on');
        clearTimeout(t._h);
        t._h = setTimeout(function () { t.classList.remove('is-on'); }, 1600);
    }

    document.addEventListener('DOMContentLoaded', function() {
        const tabs = document.querySelectorAll('.dr-tab');
        const panes = document.querySelectorAll('.dr-tab-pane');

        tabs.forEach(tab => {
            tab.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Reset active tab states
                tabs.forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                
                // Hide all panes
                panes.forEach(pane => pane.style.display = 'none');
                
                // Display target pane
                const targetId = this.getAttribute('data-target');
                const targetPane = document.getElementById(targetId);
                if (targetPane) {
                    if (targetId === 'pane-details') {
                        targetPane.style.display = 'grid';
                    } else {
                        targetPane.style.display = 'block';
                    }
                }
            });
        });

        // Mobile-only motion: section reveals + book-bar entrance. One-shot
        // IntersectionObservers — never per-frame scroll handlers (they jank;
        // see the For You rail postmortem).
        if (window.matchMedia('(max-width: 1024px)').matches) {
            const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            const hasIO = 'IntersectionObserver' in window;

            if (!reduce && hasIO) {
                const io = new IntersectionObserver(function (entries) {
                    entries.forEach(function (e) {
                        if (e.isIntersecting) { e.target.classList.add('is-in'); io.unobserve(e.target); }
                    });
                }, { rootMargin: '0px 0px -40px 0px' });
                document.querySelectorAll('.dr-mmeta, .dr-mobrows > section, #pane-details > section, #pane-know > section, .dr-mgal')
                    .forEach(function (s) { s.classList.add('dr-reveal'); io.observe(s); });
            }

            // Hero pager dots track the visible slide (IO, not scroll handlers).
            const heroRail = document.querySelector('.dr-hero__rail');
            const heroDots = document.querySelectorAll('.dr-hero__dot');
            if (heroRail && heroDots.length > 1 && hasIO) {
                const slides = Array.prototype.slice.call(heroRail.children);
                const heroIo = new IntersectionObserver(function (entries) {
                    entries.forEach(function (e) {
                        if (!e.isIntersecting) return;
                        const idx = slides.indexOf(e.target);
                        heroDots.forEach(function (d, i) { d.classList.toggle('is-on', i === idx); });
                    });
                }, { root: heroRail, threshold: 0.6 });
                slides.forEach(function (s) { heroIo.observe(s); });
            }

            const bar = document.querySelector('.dr-book-bar');
            const title = document.querySelector('.dr-main-title');
            if (bar && title && hasIO && !reduce) {
                // Bar shows at the top (while the title is on screen) and slides
                // away once the reader scrolls down into the content.
                bar.classList.add('is-vis');
                new IntersectionObserver(function (entries) {
                    bar.classList.toggle('is-vis', entries[0].isIntersecting);
                }).observe(title);
            } else if (bar) {
                bar.classList.add('is-vis');
            }
        }
    });
</script>
@endsection
