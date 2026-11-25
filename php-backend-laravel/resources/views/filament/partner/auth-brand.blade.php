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
    {{-- Phones: a badminton court in hairlines (true 6.1 x 13.4 m proportion),
         bleeding off the right edge: the same texture as the partner app's band. --}}
    <svg class="hrn-authbrand__court" viewBox="0 0 61 134" aria-hidden="true">
        <rect x="0" y="0" width="61" height="134"/>
        <path d="M4.6 0v134M56.4 0v134M0 47h61M0 87h61M30.5 0v47M30.5 87v47M0 7.4h61M0 126.6h61"/>
        <path class="net" d="M0 67h61"/>
    </svg>

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
                    <svg viewBox="0 0 16 16" aria-hidden="true"><rect x="1.9" y="3.2" width="12.2" height="9.6" rx="1.5"/><path d="M8 3.2v9.6"/><circle cx="8" cy="8" r="1.75"/></svg>
                    <span>Bookings</span>
                </li>
                <li>
                    <svg viewBox="0 0 16 16" aria-hidden="true"><path d="M1 3.5h14v3a1.5 1.5 0 0 0 0 3v3H1v-3a1.5 1.5 0 0 0 0-3z"/><path d="M5.8 8.1l1.6 1.6 3.1-3.2"/></svg>
                    <span>Gate check-in</span>
                </li>
                <li>
                    <svg viewBox="0 0 16 16" aria-hidden="true" class="is-bars"><path d="M3.5 13V9.2M8 13V6.4M12.5 13V3"/></svg>
                    <span>Payouts</span>
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
        background: var(--hrn-form-bg, #fff);
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

    .hrn-authbrand__court { display: none; }

    /* ---------------------------------------------------------------------
       PHONES & TABLETS: the partner app's sign-in (PartnerSignIn.kt). A navy
       band with the logo on top and the headline resting on a white sheet
       that sits on the bottom edge. No glow, no grid, no floating card.
    --------------------------------------------------------------------- */
    @media (max-width: 1023px) {
        .fi-simple-layout:has(.hrn-authbrand) {
            display: flex;
            flex-direction: column;
            min-height: 100dvh;
            background: #fff;
        }
        .hrn-authbrand {
            flex: 1 0 auto;
            min-height: 15.5rem;
            border-radius: 0;
            box-shadow: none;
            background:
                radial-gradient(90% 70% at 0% 100%, rgba(37, 99, 235, 0.2), transparent 70%),
                linear-gradient(180deg, #0B1A3F 0%, #081230 100%);
        }
        .hrn-authbrand__glow,
        .hrn-authbrand__grid,
        .hrn-authbrand__sub,
        .hrn-authbrand__mock,
        .hrn-authbrand__foot,
        .hrn-authbrand-rbrand { display: none; }

        .hrn-authbrand__court {
            display: block;
            position: absolute;
            z-index: 0;
            left: 66%;
            top: 10%;
            width: 34%;
            height: auto;
            transform: rotate(18deg);
            overflow: visible;
            fill: none;
            stroke: rgba(255, 255, 255, 0.09);
            stroke-width: 1.3px;
        }
        .hrn-authbrand__court * { vector-effect: non-scaling-stroke; }
        .hrn-authbrand__court .net { stroke-width: 2.3px; }

        /* Logo at the top; the headline rests just above the sheet's edge
           (the sheet rides 28px up over the band, hence the extra padding). */
        .hrn-authbrand__inner {
            padding: calc(env(safe-area-inset-top, 0px) + 18px) 24px 54px;
            gap: 0;
        }
        .hrn-authbrand__top { gap: 11px; margin: 0; }
        .hrn-authbrand__logo { height: 21px; }
        .hrn-authbrand__pill {
            position: relative;
            padding: 0 0 0 12px;
            border: 0;
            border-radius: 0;
            background: none;
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 2.6px;
            color: rgba(255, 255, 255, 0.72);
        }
        .hrn-authbrand__pill::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            width: 1px;
            height: 14px;
            margin-top: -7px;
            background: rgba(255, 255, 255, 0.28);
        }
        .hrn-authbrand__mid { margin-top: auto; padding-top: 2.5rem; }
        .hrn-authbrand__headline {
            font-size: 29px;
            line-height: 34px;
            font-weight: 700;
            letter-spacing: -0.6px;
            margin: 0;
        }
        /* What the app does, as a ruled row of facts: hairlines, not buttons. */
        .hrn-authbrand__feats {
            margin: 22px 0 0;
            padding: 14px 0 0;
            max-width: none;
            grid-template-columns: 1fr 1.25fr 1fr;
            border-top: 1px solid rgba(255, 255, 255, 0.16);
        }
        .hrn-authbrand__feats li {
            position: relative;
            justify-content: center;
            gap: 7px;
            padding: 0;
            font-size: 12px;
            font-weight: 500;
            color: rgba(255, 255, 255, 0.82);
        }
        .hrn-authbrand__feats li:first-child { padding-left: 0; }
        .hrn-authbrand__feats li + li { border-left: 0; }
        .hrn-authbrand__feats li + li::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            width: 1px;
            height: 18px;
            margin-top: -9px;
            background: rgba(255, 255, 255, 0.16);
        }
        .hrn-authbrand__feats svg { width: 16px; height: 16px; stroke: #BFD3FF; stroke-width: 1.4; }
        .hrn-authbrand__feats svg.is-bars { stroke-width: 2; }

        /* The white sheet: on the bottom edge, rounded top corners over the navy. */
        .fi-simple-layout:has(.hrn-authbrand) .fi-simple-main-ctn {
            flex: none;
            display: block;
            position: relative;
            z-index: 2;
            margin-top: -28px;
            padding: 28px 24px 8px;
            background: #fff;
            border-radius: 28px 28px 0 0;
            animation: hrn-sheet 0.6s cubic-bezier(0.22, 1, 0.36, 1) both;
        }
        .fi-simple-layout:has(.hrn-authbrand) main.fi-simple-main {
            width: 100%;
            max-width: 30rem;
            margin: 0 auto;
            padding: 0;
            background: none;
            border: 0;
            border-radius: 0;
            box-shadow: none;
            animation: none;
        }
        .fi-simple-layout:has(.hrn-authbrand) > .hga {
            position: relative;
            z-index: 2;
            background: #fff;
            padding-bottom: env(safe-area-inset-bottom, 0px);
            animation: hrn-sheet 0.6s cubic-bezier(0.22, 1, 0.36, 1) both;
        }
        @keyframes hrn-sheet {
            from { opacity: 0; transform: translate3d(0, 60px, 0); }
            to   { opacity: 1; transform: none; }
        }
    }

    /* Short phones (SE, mini): the fact row goes, the sheet stays whole. */
    @media (max-width: 1023px) and (max-height: 700px) {
        .hrn-authbrand { min-height: 12rem; }
        .hrn-authbrand__feats { display: none; }
        .hrn-authbrand__mid { padding-top: 1.75rem; }
    }

    @media (prefers-reduced-motion: reduce) {
        .hrn-authbrand__glow,
        .hrn-authbrand__top, .hrn-authbrand__headline, .hrn-authbrand__sub,
        .hrn-authbrand__feats li, .hrn-authbrand__mock,
        .fi-simple-layout:has(.hrn-authbrand) main.fi-simple-main,
        .fi-simple-layout:has(.hrn-authbrand) .fi-simple-main-ctn,
        .fi-simple-layout:has(.hrn-authbrand) > .hga { animation: none; }
    }

    /* Right column background follows the theme (light default / dark panel). */
    .fi-simple-layout:has(.hrn-authbrand) { --hrn-form-bg: #fff; }
    .dark .fi-simple-layout:has(.hrn-authbrand),
    :root.dark .fi-simple-layout:has(.hrn-authbrand) { --hrn-form-bg: #0f1420; }
</style>
