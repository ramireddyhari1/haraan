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
            height: 340px !important; min-height: 0; max-height: none;
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

        /* Trust strip + metadata cards (mirror the app EventTrustIndicators + EventMetadataCards) */
        .dr-mmeta { padding: 0 20px; margin: 6px 0 20px; }
        .dr-trust { display: flex; gap: 8px; margin-bottom: 10px; }
        .dr-trust__chip {
            flex: 1; display: inline-flex; align-items: center; justify-content: center; gap: 6px;
            background: #F4F7FB; border-radius: 14px; padding: 9px 6px;
            font-size: 12px; font-weight: 700; color: #0F172A; white-space: nowrap;
        }
        .dr-trust__chip svg { width: 15px; height: 15px; color: #64748B; flex-shrink: 0; }
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
        .dr-mcard small { font-size: 11px; color: #94A3B8; }
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

        /* The desktop map card + organizer card are replaced by app-style row
           cards (EventOrganizerSection / EventVenueSection) in .dr-mobrows. */
        .district-event-page a[aria-label="Open venue location in Maps"] { display: none !important; }
        .district-event-page .dr-organizer-desk { display: none !important; }

        /* App-style compact row cards: Organizer + Venue */
        .dr-mobrows { display: flex; flex-direction: column; gap: 22px; margin-top: 24px; }
        .dr-mobrows h3 { margin: 0 0 10px; }
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
    .dr-gtk__card { background: #F4F7FB; border: 1px solid #E2E8F0; border-radius: 16px; padding: 18px; }
    .dr-gtk__grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px 16px; }
    .dr-gtk__cell { display: flex; gap: 12px; align-items: flex-start; min-width: 0; }
    .dr-gtk__ico { flex: 0 0 36px; width: 36px; height: 36px; border-radius: 10px; background: rgba(37, 99, 235, 0.10); display: grid; place-items: center; }
    .dr-gtk__ico svg { width: 18px; height: 18px; color: #2563EB; }
    .dr-gtk__txt { min-width: 0; display: flex; flex-direction: column; gap: 2px; }
    .dr-gtk__txt small { font-size: 10px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: #94A3B8; }
    .dr-gtk__txt strong { font-size: 14px; font-weight: 600; color: #0F172A; line-height: 1.3; overflow-wrap: anywhere; }
    /* Stay two-up on phones (app parity — items split left/right); just tighten
       the cells so the pairs fit at 360-430px. */
    @media (max-width: 430px) {
        .dr-gtk__grid { gap: 14px 12px; }
        .dr-gtk__card { padding: 14px; }
        .dr-gtk__ico { flex: 0 0 30px; width: 30px; height: 30px; border-radius: 9px; }
        .dr-gtk__ico svg { width: 15px; height: 15px; }
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
            <div class="dr-hero-banner" style="margin-top: 0; margin-bottom: 12px;">
                <img src="{{ $heroImg }}" alt="{{ $event->title }}" fetchpriority="high" decoding="async">
                @if($countdown)<div class="dr-hero-badge">{{ $countdown }}</div>@endif
            </div>

            {{-- Content sheet — overlaps the poster with a rounded top (mirrors the app) --}}
            <div class="dr-sheet">
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
                    <p class="dr-date-line">{{ optional($event->date)->format('D, d M') }} • {{ optional($event->date)->format('g:i A') }}@if($event->city) • {{ $event->city }}@endif</p>
                    <p class="dr-meta-text">{{ optional($event->date)->format('D, d M Y') }} • {{ optional($event->date)->format('g:i A') }}</p>
                    {{-- Mobile-only glass meta chips overlaid on the hero --}}
                    <div class="dr-meta-chips">
                        @if($event->date)
                        <span class="dr-chip">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            <span>{{ $event->date->format('D, d M') }} · {{ $event->date->format('g:i A') }}</span>
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
                $mTime = optional($event->date)->format('g:i A');
                $mVenueShort = $event->venue ? \Illuminate\Support\Str::before($event->venue, ',') : 'Venue';
                $mSchedule = $event->scheduleRows();
                $mMapsUrl = 'https://www.google.com/maps/search/?api=1&query='.urlencode(trim(($event->venue ?: '').' '.($event->city ?: 'India')));
            @endphp
            <div class="dr-mmeta">
                <div class="dr-trust">
                    <span class="dr-trust__chip">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>Verified
                    </span>
                    <span class="dr-trust__chip">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>Secure
                    </span>
                    <span class="dr-trust__chip">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>Instant
                    </span>
                </div>
                <div class="dr-mcards">
                    <div class="dr-mcard">
                        <span class="dr-mcard__ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span>
                        <strong>{{ $mDay ?: 'TBA' }}</strong>
                        <small>{{ $mMonth ?: 'Date' }}</small>
                    </div>
                    <a class="dr-mcard" href="{{ $mMapsUrl }}" target="_blank" rel="noopener">
                        <span class="dr-mcard__ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg></span>
                        <strong>{{ $mVenueShort }}</strong>
                        <small class="dr-mcard__link">Directions</small>
                    </a>
                    @if(count($mSchedule))
                    {{-- Tapping opens the run-of-show sheet, like the app's Doors Open card. --}}
                    <button type="button" class="dr-mcard" onclick="drSchedToggle(true)">
                        <span class="dr-mcard__ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/></svg></span>
                        <strong>{{ $mTime ?: 'On time' }}</strong>
                        <small class="dr-mcard__link">Schedule</small>
                    </button>
                    @else
                    <div class="dr-mcard">
                        <span class="dr-mcard__ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/></svg></span>
                        <strong>{{ $mTime ?: 'On time' }}</strong>
                        <small>Doors Open</small>
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
                            <div class="dr-description">
                                {!! $event->description !!}
                            </div>

                            {{-- Premium Horizontal Maps Card — links to a real Maps
                                 search for the venue. The preview is a generic map
                                 texture, not a hardcoded (and previously wrong-city)
                                 embed, so it never claims a false location. --}}
                            @php
                                $mapLoc = $event->location ?: '';
                                $mapVenue = $event->venue ?: '';
                                // Avoid repeating the venue when the location string already contains it.
                                $mapBase = ($mapVenue && stripos($mapLoc, $mapVenue) === false)
                                    ? trim($mapVenue.' '.$mapLoc)
                                    : ($mapLoc ?: $mapVenue);
                                $mapQuery = urlencode(trim(trim($mapBase).', '.($event->city ?: 'India'), ', '));
                            @endphp
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

                            {{-- Mobile-only app-parity rows: Organizer + Venue
                                 (EventOrganizerSection / EventVenueSection order). --}}
                            @php
                                $mOrganizer = $event->artist->name ?? 'Event Host';
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
                                    <div class="dr-mrow">
                                        <span class="dr-mrow__ava">
                                            @if($event->artist && $event->artist->image)
                                                <img src="{{ $event->artist->image }}" alt="{{ $mOrganizer }}">
                                            @else
                                                {{ strtoupper(mb_substr(trim($mOrganizer), 0, 1)) }}
                                            @endif
                                        </span>
                                        <span class="dr-mrow__txt">
                                            <strong>{{ $mOrganizer }}<svg viewBox="0 0 24 24" fill="currentColor"><path d="M23 12l-2.44-2.79.34-3.69-3.61-.82-1.89-3.2L12 2.96 8.6 1.5 6.71 4.69 3.1 5.5l.34 3.7L1 12l2.44 2.79-.34 3.7 3.61.82L8.6 22.5l3.4-1.47 3.4 1.46 1.89-3.19 3.61-.82-.34-3.69L23 12zm-12.91 4.72l-3.8-3.81 1.48-1.48 2.32 2.33 5.85-5.87 1.48 1.48-7.33 7.35z"/></svg></strong>
                                            @if($event->category)<small>{{ $event->category }} · Verified organizer</small>@endif
                                        </span>
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
                                @if($event->venue)
                                <section>
                                    <h3 class="dr-section-title">Venue</h3>
                                    <div class="dr-mrow">
                                        <span class="dr-mrow__ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg></span>
                                        <span class="dr-mrow__txt">
                                            <strong>{{ $mVenueName }}</strong>
                                            @if($mVenueAddr)<small>{{ $mVenueAddr }}</small>@endif
                                        </span>
                                        <a class="dr-mrow__cta" href="{{ $mMapsUrl }}" target="_blank" rel="noopener">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg>Directions
                                        </a>
                                    </div>
                                </section>
                                @endif
                            </div>
                        </section>
                    </div>

                    <div class="dr-organizer-desk">
                        <section>
                            <h3 class="dr-section-title" style="margin-bottom: 16px; font-size: 18px; font-weight: 800; letter-spacing: -0.02em; text-transform: none; color: #121620;">Organized by</h3>
                            <div class="dr-organizer-card" style="background: #ffffff; border: 1px solid var(--dr-border); border-radius: 24px; padding: 24px; display: flex; align-items: center; gap: 24px; box-shadow: 0 6px 24px rgba(0, 0, 0, 0.02); min-height: 180px;">
                                {{-- Left column: Avatar and Name --}}
                                    <div style="display: flex; flex-direction: column; align-items: center; text-align: center; gap: 12px; width: 45%;">
                                    <div style="width: 84px; height: 84px; border-radius: 50%; overflow: hidden; border: 2px solid #ffffff; box-shadow: 0 4px 12px rgba(0,0,0,0.08); background: #22252a; display: flex; align-items: center; justify-content: center;">
                                        @if($event->artist && $event->artist->image)
                                            <img src="{{ $event->artist->image }}" alt="{{ $event->artist->name }}" style="width: 100%; height: 100%; object-fit: cover;">
                                        @else
                                            <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="#a78bfa" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                                        @endif
                                    </div>
                                    <div class="dr-organizer-name" style="font-size: 12px; font-weight: 900; color: #121620; text-transform: uppercase; letter-spacing: 0.05em; line-height: 1.2;">
                                        {{ Str::limit($event->artist->name ?? 'Event Host', 14, '...') }}
                                    </div>
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
                @php $highlights = is_array($event->info_notes) ? array_values(array_filter($event->info_notes)) : []; @endphp
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

                {{-- Gallery: the event's own images (not stock arcade photos).
                     Shown only when there is more than the hero image, so we never
                     pad the page with unrelated placeholders. --}}
                @php $gallery = $event->imageUrls(); @endphp
                @if(count($gallery) > 1)
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
                $infoNotes = array_values(array_filter(
                    (array) ($event->info_notes ?? []),
                    fn ($n) => is_string($n) && trim($n) !== ''
                ));
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
            @if(count($gallery) > 1)
            <section class="dr-mgal">
                <h3 class="dr-section-title" style="margin-bottom: 12px;">Gallery <span class="dr-mgal__count">{{ count($gallery) }} photos</span></h3>
                <div class="dr-mgal__rail">
                    @foreach(array_slice($gallery, 0, 8) as $img)
                        <button type="button" class="dr-mgal__item" onclick="drLbx(this)" aria-label="View photo full screen">
                            <img src="{{ $img }}" alt="{{ $event->title }}" loading="lazy" decoding="async">
                        </button>
                    @endforeach
                </div>
            </section>
            @endif

            </div>{{-- /.dr-sheet --}}
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
        }
    </script>
    @endif

    {{-- Ticket selection sheet (both breakpoints) — GET to the auth-gated
         checkout review, so guests bounce through /login and resume. --}}
    @php
        $tixTiers = $event->ticketTypes->filter->isOnSale()->values();
        $tixSalesClosed = $event->ticketTypes->isNotEmpty() && $tixTiers->isEmpty();
        $tixSoldOut = (int) $event->available_slots <= 0;
    @endphp
    <div class="dr-tix__backdrop" onclick="drTixToggle(false)"></div>
    <form class="dr-tix" method="GET" action="/events/{{ $event->id }}/book" role="dialog" aria-modal="true" aria-label="Select tickets">
        <div class="dr-tix__grab"></div>
        <h3>Select tickets</h3>
        @if($tixSoldOut)
            <p class="dr-tix__closed">This event is sold out.</p>
        @elseif($tixSalesClosed)
            <p class="dr-tix__closed">Ticket sales are closed right now.</p>
        @elseif($tixTiers->count())
            @foreach($tixTiers as $tier)
                <div class="dr-tixrow" data-price="{{ $tier->effectivePrice() }}">
                    <div class="dr-tixrow__info">
                        <strong>{{ $tier->name }}</strong>
                        <small>₹{{ number_format($tier->effectivePrice()) }}</small>
                    </div>
                    <div class="dr-stepper">
                        <button type="button" onclick="drStep(this, -1)" aria-label="Fewer">−</button>
                        <input type="number" name="qty[{{ $tier->id }}]" value="0" min="0" max="10" readonly>
                        <button type="button" onclick="drStep(this, 1)" aria-label="More">+</button>
                    </div>
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
        @unless($tixSoldOut || $tixSalesClosed)
            <button type="submit" class="dr-tix__cta" disabled>Continue</button>
        @endunless
    </form>
    <script>
        function drTixToggle(open) {
            document.querySelector('.dr-tix').classList.toggle('is-open', open);
            document.querySelector('.dr-tix__backdrop').classList.toggle('is-open', open);
            document.body.classList.toggle('dr-lock', open);
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
    <div class="dr-book-bar">
        <div class="dr-book-bar__price">
            <span class="dr-book-bar__amount">{{ $event->price ? '₹'.number_format($event->price) : 'Free' }}</span>
            <span class="dr-book-bar__label">{{ $event->price ? 'onwards' : 'entry' }}</span>
        </div>
        <button class="dr-book-bar__btn" type="button" onclick="drTixToggle(true)" @if($tixSoldOut) disabled style="background:#CBD5E1;box-shadow:none;" @endif>{{ $tixSoldOut ? 'Sold out' : 'Book Tickets' }}</button>
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
    }
    function drLbxClose() {
        document.querySelector('.dr-lbx').classList.remove('is-open');
        document.body.classList.remove('dr-lock');
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
