{{--
    BookMyShow / District-style split brand panel for the Haraan Partner auth
    screens (login + password reset). Injected at PanelsRenderHook::SIMPLE_LAYOUT_START
    from PartnerPanelProvider, so it renders as the FIRST child of .fi-simple-layout,
    ahead of the Filament form card (.fi-simple-main-ctn).

    Everything is self-contained (markup + inline <style>) so it deploys as a plain
    Blade file — no Vite/theme rebuild. The split layout is scoped with :has(.hrn-authbrand)
    so it only ever affects the partner auth pages, never the /control panel that
    shares the same compiled theme.
--}}
<div class="hrn-authbrand" aria-hidden="true">
    <div class="hrn-authbrand__glow hrn-authbrand__glow--a"></div>
    <div class="hrn-authbrand__glow hrn-authbrand__glow--b"></div>
    <div class="hrn-authbrand__grid"></div>

    <div class="hrn-authbrand__inner">
        <div class="hrn-authbrand__top">
            <img class="hrn-authbrand__logo"
                 src="{{ asset('images/haraan-logo-white.png') }}"
                 alt="haraan" width="1680" height="445">
            <span class="hrn-authbrand__pill">PARTNER</span>
        </div>

        <div class="hrn-authbrand__mid">
            <h1 class="hrn-authbrand__headline">
                Run your venue.<br>Fill every show.
            </h1>
            <p class="hrn-authbrand__sub">
                One console for hosts &amp; venue owners — publish events, take bookings,
                scan tickets at the gate and watch your earnings in real time.
            </p>

            {{-- What the console does — a spec row, not pills: these aren't
                 tappable, so they mustn't look like buttons. --}}
            <ul class="hrn-authbrand__feats">
                <li>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 17l5.5-5.5 4 4L21 7"/><path d="M15 7h6v6"/></svg>
                    <span>Live earnings</span>
                </li>
                <li>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 8V5.5A1.5 1.5 0 0 1 5.5 4H8M16 4h2.5A1.5 1.5 0 0 1 20 5.5V8M20 16v2.5a1.5 1.5 0 0 1-1.5 1.5H16M8 20H5.5A1.5 1.5 0 0 1 4 18.5V16"/><path d="M4 12h16"/></svg>
                    <span>Gate check-in</span>
                </li>
                <li>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="7" height="9" rx="1.5"/><rect x="13" y="4" width="7" height="5" rx="1.5"/><rect x="13" y="11" width="7" height="9" rx="1.5"/><rect x="4" y="15" width="7" height="5" rx="1.5"/></svg>
                    <span>One console</span>
                </li>
            </ul>
        </div>

        {{-- Faint floating "console" mock — credibility, desktop only. --}}
        <div class="hrn-authbrand__mock">
            <div class="hrn-authbrand__mock-row">
                <div class="hrn-authbrand__mock-kpi">
                    <span class="hrn-authbrand__mock-k">₹ 1,84,200</span>
                    <span class="hrn-authbrand__mock-l">This week</span>
                </div>
                <div class="hrn-authbrand__mock-kpi">
                    <span class="hrn-authbrand__mock-k">1,236</span>
                    <span class="hrn-authbrand__mock-l">Tickets sold</span>
                </div>
                <div class="hrn-authbrand__mock-kpi">
                    <span class="hrn-authbrand__mock-k">98%</span>
                    <span class="hrn-authbrand__mock-l">Checked in</span>
                </div>
            </div>
            <div class="hrn-authbrand__mock-bars">
                <i style="height:38%"></i><i style="height:62%"></i><i style="height:48%"></i>
                <i style="height:80%"></i><i style="height:56%"></i><i style="height:94%"></i>
                <i style="height:70%"></i>
            </div>
        </div>

        <div class="hrn-authbrand__foot">
            <span>Trusted by turf owners, clubs &amp; event organisers</span>
        </div>
    </div>
</div>

{{-- Blue Haraan logo + handwritten "Partner" tag, top-right of the form column. --}}
<div class="hrn-authbrand-rbrand">
    <img class="hrn-authbrand-rlogo"
         src="{{ asset('images/haraan-logo-blue.png') }}"
         alt="haraan" width="1680" height="445">
    <span class="hrn-authbrand-rtag">Partner</span>
</div>

<style>
    /* ---------------------------------------------------------------------
       SPLIT SHELL — turn Filament's centred .fi-simple-layout into a
       two-column brand|form grid, only on partner auth pages (:has scope).
    --------------------------------------------------------------------- */
    .fi-simple-layout:has(.hrn-authbrand) {
        position: relative;
        display: grid;
        grid-template-columns: 1.05fr 0.95fr;
        min-height: 100dvh;
        padding: 0;
        gap: 0;
        align-items: stretch;
        background: var(--hrn-form-bg, #f7f8fb);
    }

    /* Blue wordmark + handwritten tag, pinned top-right of the light form column. */
    .hrn-authbrand-rbrand {
        position: absolute;
        top: clamp(1.5rem, 3vw, 2.4rem);
        right: clamp(1.5rem, 3vw, 3rem);
        z-index: 5;
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 0.1rem;
    }
    .hrn-authbrand-rlogo {
        height: 2.3rem;
        width: auto;
        display: block;
    }
    .hrn-authbrand-rtag {
        font-size: 0.62rem;
        font-weight: 800;
        letter-spacing: 0.34em;
        margin-right: -0.34em;   /* the tracking after the last letter */
        line-height: 1;
        color: #2f6bff;
        text-transform: uppercase;
    }
    .dark .hrn-authbrand-rbrand,
    :root.dark .hrn-authbrand-rbrand { opacity: 0.95; }

    .fi-simple-layout:has(.hrn-authbrand) .fi-simple-main-ctn {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2.5rem 1.5rem;
    }

    .fi-simple-layout:has(.hrn-authbrand) main.fi-simple-main {
        width: 100%;
        max-width: 27rem;
    }

    /* Form card: flatten Filament's boxed card into a clean left-aligned panel. */
    .fi-simple-layout:has(.hrn-authbrand) .fi-simple-page {
        background: transparent;
        box-shadow: none;
        border: 0;
        padding: 0;
        gap: 1.75rem;
    }

    /* Hide the small header logo in the form column — the wordmark lives in the
       brand panel on the left (and the mobile band on top). */
    .fi-simple-layout:has(.hrn-authbrand) .fi-simple-page .fi-logo,
    .fi-simple-layout:has(.hrn-authbrand) .fi-simple-header .fi-logo {
        display: none;
    }

    .fi-simple-layout:has(.hrn-authbrand) .fi-simple-header {
        text-align: left;
        align-items: flex-start;
        gap: 0.4rem;
    }
    .fi-simple-layout:has(.hrn-authbrand) .fi-simple-header .fi-header-heading,
    .fi-simple-layout:has(.hrn-authbrand) .fi-simple-header h1 {
        font-size: 1.65rem;
        font-weight: 800;
        letter-spacing: -0.02em;
    }
    .fi-simple-layout:has(.hrn-authbrand) .fi-simple-header .fi-header-subheading {
        font-size: 0.9rem;
        color: color-mix(in srgb, currentColor 62%, transparent);
    }

    /* Inputs — taller, calmer, blue-lane focus ring. */
    .fi-simple-layout:has(.hrn-authbrand) .fi-input-wrp {
        border-radius: 0.75rem;
        min-height: 3rem;
    }
    .fi-simple-layout:has(.hrn-authbrand) .fi-fieldset,
    .fi-simple-layout:has(.hrn-authbrand) .fi-input {
        font-size: 0.95rem;
    }

    /* Submit — full-width gradient CTA with a confident press state. */
    .fi-simple-layout:has(.hrn-authbrand) .fi-sc-actions .fi-btn {
        min-height: 3rem;
        border-radius: 0.75rem;
        font-weight: 700;
        font-size: 0.95rem;
        background-image: linear-gradient(180deg, #2f6bff 0%, #1e50e6 100%);
        box-shadow: 0 8px 20px -8px rgba(37, 99, 235, 0.55);
        transition: transform 0.12s ease, box-shadow 0.12s ease;
    }
    .fi-simple-layout:has(.hrn-authbrand) .fi-sc-actions .fi-btn:hover {
        box-shadow: 0 12px 26px -8px rgba(37, 99, 235, 0.6);
    }
    .fi-simple-layout:has(.hrn-authbrand) .fi-sc-actions .fi-btn:active {
        transform: translateY(1px);
    }

    /* ---------------------------------------------------------------------
       BRAND PANEL — always-dark aurora, regardless of light/dark theme.
    --------------------------------------------------------------------- */
    .hrn-authbrand {
        position: relative;
        overflow: hidden;
        display: flex;
        color: #eaf0ff;
        background:
            radial-gradient(1100px 620px at 22% -12%, rgba(59, 130, 246, 0.55), transparent 62%),
            radial-gradient(900px 560px at 108% 8%, rgba(99, 102, 241, 0.45), transparent 60%),
            linear-gradient(155deg, #0a1738 0%, #0b1c46 46%, #0a1230 100%);
        isolation: isolate;
    }
    .hrn-authbrand__glow {
        position: absolute;
        border-radius: 50%;
        filter: blur(60px);
        opacity: 0.55;
        z-index: 0;
        animation: hrn-float 16s ease-in-out infinite alternate;
    }
    .hrn-authbrand__glow--a { width: 420px; height: 420px; top: -120px; left: -80px;
        background: radial-gradient(circle, rgba(56,132,255,0.7), transparent 70%); }
    .hrn-authbrand__glow--b { width: 360px; height: 360px; bottom: -140px; right: -60px;
        background: radial-gradient(circle, rgba(129,140,248,0.65), transparent 70%);
        animation-delay: -6s; }
    @keyframes hrn-float {
        from { transform: translate3d(0,0,0) scale(1); }
        to   { transform: translate3d(18px,-14px,0) scale(1.08); }
    }
    .hrn-authbrand__grid {
        position: absolute; inset: 0; z-index: 0; opacity: 0.18;
        background-image:
            linear-gradient(rgba(255,255,255,0.5) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255,255,255,0.5) 1px, transparent 1px);
        background-size: 44px 44px;
        mask-image: radial-gradient(120% 90% at 30% 10%, #000 30%, transparent 75%);
        -webkit-mask-image: radial-gradient(120% 90% at 30% 10%, #000 30%, transparent 75%);
    }

    .hrn-authbrand__inner {
        position: relative; z-index: 1;
        display: flex; flex-direction: column;
        width: 100%;
        padding: clamp(2rem, 4vw, 3.75rem);
        gap: 1.5rem;
    }

    .hrn-authbrand__top { display: flex; align-items: center; gap: 0.7rem; }
    .hrn-authbrand__logo {
        height: 1.9rem; width: auto; display: block;
    }
    .hrn-authbrand__pill {
        font-size: 0.62rem; font-weight: 800; letter-spacing: 0.16em;
        padding: 0.28rem 0.55rem; border-radius: 999px;
        color: #cfe0ff; background: rgba(255,255,255,0.1);
        border: 1px solid rgba(255,255,255,0.18);
    }

    .hrn-authbrand__mid { margin-top: auto; }
    .hrn-authbrand__headline {
        font-size: clamp(2rem, 3.4vw, 3rem);
        line-height: 1.05; font-weight: 800; letter-spacing: -0.03em;
        color: #fff; margin: 0 0 1rem;
    }
    .hrn-authbrand__sub {
        font-size: clamp(0.95rem, 1.2vw, 1.05rem);
        line-height: 1.55; color: rgba(224,232,255,0.78);
        max-width: 30rem; margin: 0;
    }
    /* Feature spec row — hairline-ruled columns with drawn line icons. */
    .hrn-authbrand__feats {
        list-style: none; margin: 1.75rem 0 0; padding: 1.1rem 0 0;
        display: grid; grid-template-columns: repeat(3, auto); justify-content: start;
        border-top: 1px solid rgba(255,255,255,0.12);
        max-width: 30rem;
    }
    .hrn-authbrand__feats li {
        display: flex; align-items: center; gap: 0.55rem;
        padding: 0 1.25rem;
        font-size: 0.84rem; font-weight: 600; letter-spacing: -0.005em;
        color: rgba(234,240,255,0.92);
        white-space: nowrap;
    }
    .hrn-authbrand__feats li:first-child { padding-left: 0; }
    .hrn-authbrand__feats li + li { border-left: 1px solid rgba(255,255,255,0.12); }
    .hrn-authbrand__feats svg {
        width: 1.15rem; height: 1.15rem; flex: none;
        fill: none; stroke: #8fb4ff; stroke-width: 1.75;
        stroke-linecap: round; stroke-linejoin: round;
    }

    /* Entrance — content settles in, top to bottom, once. */
    @keyframes hrn-rise {
        from { opacity: 0; transform: translate3d(0, 10px, 0); }
        to   { opacity: 1; transform: none; }
    }
    .hrn-authbrand__top,
    .hrn-authbrand__headline,
    .hrn-authbrand__sub,
    .hrn-authbrand__feats li,
    .hrn-authbrand__mock {
        animation: hrn-rise 0.6s cubic-bezier(0.2, 0.7, 0.2, 1) both;
    }
    .hrn-authbrand__headline { animation-delay: 0.06s; }
    .hrn-authbrand__sub { animation-delay: 0.12s; }
    .hrn-authbrand__feats li:nth-child(1) { animation-delay: 0.2s; }
    .hrn-authbrand__feats li:nth-child(2) { animation-delay: 0.26s; }
    .hrn-authbrand__feats li:nth-child(3) { animation-delay: 0.32s; }
    .hrn-authbrand__mock { animation-delay: 0.38s; }

    /* Floating console mock */
    .hrn-authbrand__mock {
        margin-top: 2rem;
        border-radius: 1rem;
        padding: 1rem 1.1rem 0.9rem;
        background: linear-gradient(180deg, rgba(255,255,255,0.1), rgba(255,255,255,0.04));
        border: 1px solid rgba(255,255,255,0.14);
        box-shadow: 0 24px 60px -30px rgba(0,0,0,0.7);
        backdrop-filter: blur(10px);
    }
    .hrn-authbrand__mock-row { display: flex; gap: 1.4rem; margin-bottom: 0.9rem; }
    .hrn-authbrand__mock-kpi { display: flex; flex-direction: column; gap: 0.15rem; }
    .hrn-authbrand__mock-k {
        font-size: 1.05rem; font-weight: 800; color: #fff;
        font-variant-numeric: tabular-nums; letter-spacing: -0.01em;
    }
    .hrn-authbrand__mock-l { font-size: 0.68rem; color: rgba(224,232,255,0.6); }
    .hrn-authbrand__mock-bars {
        display: flex; align-items: flex-end; gap: 0.4rem; height: 46px;
    }
    .hrn-authbrand__mock-bars i {
        flex: 1; border-radius: 3px 3px 0 0;
        background: linear-gradient(180deg, #6ea0ff, #2f6bff);
        opacity: 0.9;
    }

    .hrn-authbrand__foot {
        margin-top: 1.5rem;
        font-size: 0.75rem; color: rgba(224,232,255,0.55);
    }

    /* ---------------------------------------------------------------------
       MOBILE — stack: compact brand band on top, form below.
    --------------------------------------------------------------------- */
    @media (max-width: 1023px) {
        .fi-simple-layout:has(.hrn-authbrand) {
            display: flex;
            flex-direction: column;
            min-height: 100dvh;
        }
        /* Brand band: tight, rounded off at the bottom so the form tucks under it. */
        .hrn-authbrand {
            min-height: auto;
            border-radius: 0 0 1.75rem 1.75rem;
            box-shadow: 0 18px 40px -24px rgba(10, 23, 56, 0.9);
        }
        .hrn-authbrand-rbrand { display: none; }   /* band already shows the logo */
        .hrn-authbrand__inner {
            padding: 1.5rem 1.5rem 1.9rem;
            gap: 0.55rem;
        }
        .hrn-authbrand__top { margin-bottom: 0.35rem; }
        .hrn-authbrand__logo { height: 1.75rem; }
        .hrn-authbrand__mid { margin-top: 0; }     /* flow top-down, no auto push */
        .hrn-authbrand__headline {
            font-size: 1.75rem; line-height: 1.12;
            margin: 0.35rem 0 0.55rem;
        }
        .hrn-authbrand__sub {
            font-size: 0.9rem; line-height: 1.5;
            color: rgba(224, 232, 255, 0.82);
        }
        .hrn-authbrand__mock { display: none; }         /* keep the band tight */
        .hrn-authbrand__foot { display: none; }
        .hrn-authbrand__inner { padding-bottom: 3.4rem; } /* room for the card overlap */
        .hrn-authbrand__feats {
            margin-top: 1.1rem; padding-top: 0.95rem;
            grid-template-columns: repeat(3, 1fr); max-width: none;
        }
        .hrn-authbrand__feats li {
            flex-direction: column; align-items: flex-start; gap: 0.4rem;
            padding: 0 0.85rem; font-size: 0.78rem;
        }

        /* Form card rides up over the band's rounded edge — reads as a sheet
           laid on top, not a second page stacked below. */
        .fi-simple-layout:has(.hrn-authbrand) .fi-simple-main-ctn {
            flex: 1;
            align-items: flex-start;
            justify-content: flex-start;
            position: relative;
            z-index: 2;
            margin-top: -2.1rem;
            padding: 0 1rem 2.5rem;
        }
        .fi-simple-layout:has(.hrn-authbrand) main.fi-simple-main {
            animation: hrn-sheet 0.7s cubic-bezier(0.16, 1, 0.3, 1) 0.1s both;
        }
        @keyframes hrn-sheet {
            from { opacity: 0; transform: translate3d(0, 28px, 0); }
            to   { opacity: 1; transform: none; }
        }
        .fi-simple-layout:has(.hrn-authbrand) main.fi-simple-main {
            max-width: 26rem;
            margin: 0 auto;
        }
        /* Give the sign-in form a real card on mobile so it reads as premium.
           (Filament v4 paints the card on main.fi-simple-main.) */
        .fi-simple-layout:has(.hrn-authbrand) main.fi-simple-main {
            background: #fff;
            border: 1px solid rgba(15, 23, 42, 0.06);
            border-radius: 1.4rem;
            box-shadow:
                0 1px 2px rgba(15, 23, 42, 0.04),
                0 8px 20px -10px rgba(10, 23, 56, 0.18),
                0 30px 60px -32px rgba(10, 23, 56, 0.4);
            padding: 1.6rem 1.35rem 1.4rem;
            gap: 1.5rem;
        }
        .dark .fi-simple-layout:has(.hrn-authbrand) main.fi-simple-main,
        :root.dark .fi-simple-layout:has(.hrn-authbrand) main.fi-simple-main {
            background: #131a2a;
            border-color: rgba(255, 255, 255, 0.08);
        }
    }

    @media (max-width: 480px) {
        .hrn-authbrand__inner { padding: 1.35rem 1.35rem 3.25rem; }
        .hrn-authbrand__headline { font-size: 1.55rem; }
        .hrn-authbrand__sub { font-size: 0.86rem; }
        .hrn-authbrand__feats li { font-size: 0.74rem; padding: 0 0.7rem; }
    }

    @media (prefers-reduced-motion: reduce) {
        .hrn-authbrand__glow,
        .hrn-authbrand__top, .hrn-authbrand__headline, .hrn-authbrand__sub,
        .hrn-authbrand__feats li, .hrn-authbrand__mock,
        .fi-simple-layout:has(.hrn-authbrand) main.fi-simple-main { animation: none; }
    }

    /* Right column background follows the theme (light default / dark panel). */
    .fi-simple-layout:has(.hrn-authbrand) { --hrn-form-bg: #f7f8fb; }
    .dark .fi-simple-layout:has(.hrn-authbrand),
    :root.dark .fi-simple-layout:has(.hrn-authbrand) { --hrn-form-bg: #0f1420; }
</style>
