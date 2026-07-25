{{--
    BookMyShow / District-style split brand panel for the Haraan Control (/control)
    sign-in screen. Injected at PanelsRenderHook::SIMPLE_LAYOUT_START from
    AdminPanelProvider (scoped to ControlLogin), so it renders as the FIRST child
    of .fi-simple-layout, ahead of the Filament form card (.fi-simple-main-ctn).

    Twin of resources/views/filament/partner/auth-brand.blade.php — same blue
    aurora shell, admin-flavoured copy and a "CONTROL" pill. Everything is
    self-contained (markup + inline <style>) so it deploys as a plain Blade file
    with no Vite/theme rebuild. The split layout is scoped with :has(.hrn-ctlbrand)
    so it only ever affects this login page, never the /partner auth pages that
    share the same compiled theme.
--}}
<div class="hrn-ctlbrand" aria-hidden="true">
    <div class="hrn-ctlbrand__glow hrn-ctlbrand__glow--a"></div>
    <div class="hrn-ctlbrand__glow hrn-ctlbrand__glow--b"></div>
    <div class="hrn-ctlbrand__grid"></div>

    <div class="hrn-ctlbrand__inner">
        <div class="hrn-ctlbrand__top">
            <img class="hrn-ctlbrand__logo"
                 src="{{ asset('images/haraan-logo-white.png') }}"
                 alt="haraan" width="1680" height="445">
            <span class="hrn-ctlbrand__pill">CONTROL</span>
        </div>

        <div class="hrn-ctlbrand__mid">
            <h1 class="hrn-ctlbrand__headline">
                One console.<br>The whole platform.
            </h1>
            <p class="hrn-ctlbrand__sub">
                The Haraan command plane — manage events, venues &amp; users, review
                bookings and payments, and keep the app and website running in real time.
            </p>

            <ul class="hrn-ctlbrand__chips">
                <li><span class="hrn-ctlbrand__chip-ic">◎</span> Command Center</li>
                <li><span class="hrn-ctlbrand__chip-ic">₹</span> Payments health</li>
                <li><span class="hrn-ctlbrand__chip-ic">⚡</span> Live platform</li>
            </ul>
        </div>

        {{-- Faint floating "console" mock — credibility, desktop only. --}}
        <div class="hrn-ctlbrand__mock">
            <div class="hrn-ctlbrand__mock-row">
                <div class="hrn-ctlbrand__mock-kpi">
                    <span class="hrn-ctlbrand__mock-k">₹ 12.4L</span>
                    <span class="hrn-ctlbrand__mock-l">GMV this week</span>
                </div>
                <div class="hrn-ctlbrand__mock-kpi">
                    <span class="hrn-ctlbrand__mock-k">8,940</span>
                    <span class="hrn-ctlbrand__mock-l">Tickets sold</span>
                </div>
                <div class="hrn-ctlbrand__mock-kpi">
                    <span class="hrn-ctlbrand__mock-k">99.9%</span>
                    <span class="hrn-ctlbrand__mock-l">Uptime</span>
                </div>
            </div>
            <div class="hrn-ctlbrand__mock-bars">
                <i style="height:44%"></i><i style="height:66%"></i><i style="height:52%"></i>
                <i style="height:84%"></i><i style="height:60%"></i><i style="height:96%"></i>
                <i style="height:74%"></i>
            </div>
        </div>

        <div class="hrn-ctlbrand__foot">
            <span>Staff access only — every action is logged</span>
        </div>
    </div>
</div>

{{-- Blue Haraan logo + handwritten "Control" tag, top-right of the form column. --}}
<div class="hrn-ctlbrand-rbrand">
    <img class="hrn-ctlbrand-rlogo"
         src="{{ asset('images/haraan-logo-blue.png') }}"
         alt="haraan" width="1680" height="445">
    <span class="hrn-ctlbrand-rtag">Control</span>
</div>

<style>
    /* ---------------------------------------------------------------------
       SPLIT SHELL — turn Filament's centred .fi-simple-layout into a
       two-column brand|form grid, only on the control login (:has scope).
    --------------------------------------------------------------------- */
    .fi-simple-layout:has(.hrn-ctlbrand) {
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
    .hrn-ctlbrand-rbrand {
        position: absolute;
        top: clamp(1.5rem, 3vw, 2.4rem);
        right: clamp(1.5rem, 3vw, 3rem);
        z-index: 5;
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 0.1rem;
    }
    .hrn-ctlbrand-rlogo {
        height: 2.3rem;
        width: auto;
        display: block;
    }
    .hrn-ctlbrand-rtag {
        font-family: "Segoe Script", "Bradley Hand", "Snell Roundhand",
                     "Brush Script MT", cursive;
        font-size: 1.2rem;
        font-weight: 400;
        line-height: 1;
        color: #2f6bff;
        padding-right: 0.2rem;
        transform: rotate(-4deg);
    }
    .dark .hrn-ctlbrand-rbrand,
    :root.dark .hrn-ctlbrand-rbrand { opacity: 0.95; }

    .fi-simple-layout:has(.hrn-ctlbrand) .fi-simple-main-ctn {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2.5rem 1.5rem;
    }

    .fi-simple-layout:has(.hrn-ctlbrand) main.fi-simple-main {
        width: 100%;
        max-width: 27rem;
    }

    /* Form card: flatten Filament's boxed card into a clean left-aligned panel. */
    .fi-simple-layout:has(.hrn-ctlbrand) .fi-simple-page {
        background: transparent;
        box-shadow: none;
        border: 0;
        padding: 0;
        gap: 1.75rem;
    }

    /* Hide the small header logo in the form column — the wordmark lives in the
       brand panel on the left (and the mobile band on top). */
    .fi-simple-layout:has(.hrn-ctlbrand) .fi-simple-page .fi-logo,
    .fi-simple-layout:has(.hrn-ctlbrand) .fi-simple-header .fi-logo {
        display: none;
    }

    .fi-simple-layout:has(.hrn-ctlbrand) .fi-simple-header {
        text-align: left;
        align-items: flex-start;
        gap: 0.4rem;
    }
    .fi-simple-layout:has(.hrn-ctlbrand) .fi-simple-header .fi-header-heading,
    .fi-simple-layout:has(.hrn-ctlbrand) .fi-simple-header h1 {
        font-size: 1.65rem;
        font-weight: 800;
        letter-spacing: -0.02em;
    }
    .fi-simple-layout:has(.hrn-ctlbrand) .fi-simple-header .fi-header-subheading {
        font-size: 0.9rem;
        color: color-mix(in srgb, currentColor 62%, transparent);
    }

    /* Inputs — taller, calmer, blue-lane focus ring. */
    .fi-simple-layout:has(.hrn-ctlbrand) .fi-input-wrp {
        border-radius: 0.75rem;
        min-height: 3rem;
    }
    .fi-simple-layout:has(.hrn-ctlbrand) .fi-input-wrp:focus-within {
        box-shadow: 0 0 0 1px #2f6bff, 0 0 0 4px rgba(47, 107, 255, 0.18);
    }
    .fi-simple-layout:has(.hrn-ctlbrand) .fi-fieldset,
    .fi-simple-layout:has(.hrn-ctlbrand) .fi-input {
        font-size: 0.95rem;
    }

    /* Submit — full-width blue gradient CTA with a confident press state.
       (The /control panel's primary colour is green; the login deliberately
       overrides to the blue brand lane so it reads BookMyShow-premium.) */
    .fi-simple-layout:has(.hrn-ctlbrand) .fi-sc-actions .fi-btn {
        min-height: 3rem;
        border-radius: 0.75rem;
        font-weight: 700;
        font-size: 0.95rem;
        --c-400: #6ea0ff;
        --c-500: #2f6bff;
        --c-600: #1e50e6;
        color: #fff;
        background-image: linear-gradient(180deg, #2f6bff 0%, #1e50e6 100%);
        box-shadow: 0 8px 20px -8px rgba(37, 99, 235, 0.55);
        transition: transform 0.12s ease, box-shadow 0.12s ease;
    }
    .fi-simple-layout:has(.hrn-ctlbrand) .fi-sc-actions .fi-btn:hover {
        box-shadow: 0 12px 26px -8px rgba(37, 99, 235, 0.6);
    }
    .fi-simple-layout:has(.hrn-ctlbrand) .fi-sc-actions .fi-btn:active {
        transform: translateY(1px);
    }

    /* ---------------------------------------------------------------------
       BRAND PANEL — always-dark blue aurora, regardless of light/dark theme.
    --------------------------------------------------------------------- */
    .hrn-ctlbrand {
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
    .hrn-ctlbrand__glow {
        position: absolute;
        border-radius: 50%;
        filter: blur(60px);
        opacity: 0.55;
        z-index: 0;
        animation: hrn-ctl-float 16s ease-in-out infinite alternate;
    }
    .hrn-ctlbrand__glow--a { width: 420px; height: 420px; top: -120px; left: -80px;
        background: radial-gradient(circle, rgba(56,132,255,0.7), transparent 70%); }
    .hrn-ctlbrand__glow--b { width: 360px; height: 360px; bottom: -140px; right: -60px;
        background: radial-gradient(circle, rgba(129,140,248,0.65), transparent 70%);
        animation-delay: -6s; }
    @keyframes hrn-ctl-float {
        from { transform: translate3d(0,0,0) scale(1); }
        to   { transform: translate3d(18px,-14px,0) scale(1.08); }
    }
    .hrn-ctlbrand__grid {
        position: absolute; inset: 0; z-index: 0; opacity: 0.18;
        background-image:
            linear-gradient(rgba(255,255,255,0.5) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255,255,255,0.5) 1px, transparent 1px);
        background-size: 44px 44px;
        mask-image: radial-gradient(120% 90% at 30% 10%, #000 30%, transparent 75%);
        -webkit-mask-image: radial-gradient(120% 90% at 30% 10%, #000 30%, transparent 75%);
    }

    .hrn-ctlbrand__inner {
        position: relative; z-index: 1;
        display: flex; flex-direction: column;
        width: 100%;
        padding: clamp(2rem, 4vw, 3.75rem);
        gap: 1.5rem;
    }

    .hrn-ctlbrand__top { display: flex; align-items: center; gap: 0.7rem; }
    .hrn-ctlbrand__logo {
        height: 1.9rem; width: auto; display: block;
    }
    .hrn-ctlbrand__pill {
        font-size: 0.62rem; font-weight: 800; letter-spacing: 0.16em;
        padding: 0.28rem 0.55rem; border-radius: 999px;
        color: #cfe0ff; background: rgba(255,255,255,0.1);
        border: 1px solid rgba(255,255,255,0.18);
    }

    .hrn-ctlbrand__mid { margin-top: auto; }
    .hrn-ctlbrand__headline {
        font-size: clamp(2rem, 3.4vw, 3rem);
        line-height: 1.05; font-weight: 800; letter-spacing: -0.03em;
        color: #fff; margin: 0 0 1rem;
    }
    .hrn-ctlbrand__sub {
        font-size: clamp(0.95rem, 1.2vw, 1.05rem);
        line-height: 1.55; color: rgba(224,232,255,0.78);
        max-width: 30rem; margin: 0;
    }
    .hrn-ctlbrand__chips {
        list-style: none; margin: 1.6rem 0 0; padding: 0;
        display: flex; flex-wrap: wrap; gap: 0.6rem;
    }
    .hrn-ctlbrand__chips li {
        display: inline-flex; align-items: center; gap: 0.5rem;
        font-size: 0.82rem; font-weight: 600; color: #e6edff;
        padding: 0.5rem 0.85rem; border-radius: 999px;
        background: rgba(255,255,255,0.08);
        border: 1px solid rgba(255,255,255,0.14);
        backdrop-filter: blur(6px);
    }
    .hrn-ctlbrand__chip-ic {
        display: inline-grid; place-items: center;
        width: 1.15rem; height: 1.15rem; border-radius: 50%;
        font-size: 0.72rem; font-weight: 800;
        background: rgba(56,132,255,0.35); color: #fff;
    }

    /* Floating console mock */
    .hrn-ctlbrand__mock {
        margin-top: 2rem;
        border-radius: 1rem;
        padding: 1rem 1.1rem 0.9rem;
        background: linear-gradient(180deg, rgba(255,255,255,0.1), rgba(255,255,255,0.04));
        border: 1px solid rgba(255,255,255,0.14);
        box-shadow: 0 24px 60px -30px rgba(0,0,0,0.7);
        backdrop-filter: blur(10px);
    }
    .hrn-ctlbrand__mock-row { display: flex; gap: 1.4rem; margin-bottom: 0.9rem; }
    .hrn-ctlbrand__mock-kpi { display: flex; flex-direction: column; gap: 0.15rem; }
    .hrn-ctlbrand__mock-k {
        font-size: 1.05rem; font-weight: 800; color: #fff;
        font-variant-numeric: tabular-nums; letter-spacing: -0.01em;
    }
    .hrn-ctlbrand__mock-l { font-size: 0.68rem; color: rgba(224,232,255,0.6); }
    .hrn-ctlbrand__mock-bars {
        display: flex; align-items: flex-end; gap: 0.4rem; height: 46px;
    }
    .hrn-ctlbrand__mock-bars i {
        flex: 1; border-radius: 3px 3px 0 0;
        background: linear-gradient(180deg, #6ea0ff, #2f6bff);
        opacity: 0.9;
    }

    .hrn-ctlbrand__foot {
        margin-top: 1.5rem;
        font-size: 0.75rem; color: rgba(224,232,255,0.55);
    }

    /* ---------------------------------------------------------------------
       MOBILE — stack: compact brand band on top, form below.
    --------------------------------------------------------------------- */
    @media (max-width: 1023px) {
        .fi-simple-layout:has(.hrn-ctlbrand) {
            display: flex;
            flex-direction: column;
            min-height: 100dvh;
        }
        .hrn-ctlbrand {
            min-height: auto;
            border-radius: 0 0 1.75rem 1.75rem;
            box-shadow: 0 18px 40px -24px rgba(10, 23, 56, 0.9);
        }
        .hrn-ctlbrand-rbrand { display: none; }
        .hrn-ctlbrand__inner {
            padding: 1.5rem 1.5rem 1.9rem;
            gap: 0.55rem;
        }
        .hrn-ctlbrand__top { margin-bottom: 0.35rem; }
        .hrn-ctlbrand__logo { height: 1.75rem; }
        .hrn-ctlbrand__mid { margin-top: 0; }
        .hrn-ctlbrand__headline {
            font-size: 1.75rem; line-height: 1.12;
            margin: 0.35rem 0 0.55rem;
        }
        .hrn-ctlbrand__sub {
            font-size: 0.9rem; line-height: 1.5;
            color: rgba(224, 232, 255, 0.82);
        }
        .hrn-ctlbrand__mock { display: none; }
        .hrn-ctlbrand__foot { display: none; }
        .hrn-ctlbrand__chips { margin-top: 1.05rem; gap: 0.5rem; }

        .fi-simple-layout:has(.hrn-ctlbrand) .fi-simple-main-ctn {
            flex: 1;
            align-items: flex-start;
            justify-content: flex-start;
            padding: 2rem 1.35rem 2.5rem;
        }
        .fi-simple-layout:has(.hrn-ctlbrand) main.fi-simple-main {
            max-width: 26rem;
            margin: 0 auto;
        }
        .fi-simple-layout:has(.hrn-ctlbrand) .fi-simple-page {
            background: #fff;
            border: 1px solid rgba(15, 23, 42, 0.07);
            border-radius: 1.25rem;
            box-shadow: 0 24px 50px -30px rgba(15, 23, 42, 0.35);
            padding: 1.75rem 1.5rem;
            gap: 1.5rem;
        }
        .dark .fi-simple-layout:has(.hrn-ctlbrand) .fi-simple-page,
        :root.dark .fi-simple-layout:has(.hrn-ctlbrand) .fi-simple-page {
            background: #131a2a;
            border-color: rgba(255, 255, 255, 0.08);
        }
    }

    @media (max-width: 480px) {
        .hrn-ctlbrand__inner { padding: 1.35rem 1.35rem 1.75rem; }
        .hrn-ctlbrand__headline { font-size: 1.55rem; }
        .hrn-ctlbrand__sub { font-size: 0.86rem; }
        .hrn-ctlbrand__chips li { font-size: 0.76rem; padding: 0.42rem 0.7rem; }
    }

    @media (prefers-reduced-motion: reduce) {
        .hrn-ctlbrand__glow { animation: none; }
    }

    /* Right column background follows the theme (light default / dark panel). */
    .fi-simple-layout:has(.hrn-ctlbrand) { --hrn-form-bg: #f7f8fb; }
    .dark .fi-simple-layout:has(.hrn-ctlbrand),
    :root.dark .fi-simple-layout:has(.hrn-ctlbrand) { --hrn-form-bg: #0f1420; }
</style>
