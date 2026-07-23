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
            <span class="hrn-authbrand__wordmark">haraan</span>
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

            <ul class="hrn-authbrand__chips">
                <li><span class="hrn-authbrand__chip-ic">₹</span> Live earnings</li>
                <li><span class="hrn-authbrand__chip-ic">⚡</span> Instant check-in</li>
                <li><span class="hrn-authbrand__chip-ic">◎</span> One dashboard</li>
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

<style>
    /* ---------------------------------------------------------------------
       SPLIT SHELL — turn Filament's centred .fi-simple-layout into a
       two-column brand|form grid, only on partner auth pages (:has scope).
    --------------------------------------------------------------------- */
    .fi-simple-layout:has(.hrn-authbrand) {
        display: grid;
        grid-template-columns: 1.05fr 0.95fr;
        min-height: 100dvh;
        padding: 0;
        gap: 0;
        align-items: stretch;
        background: var(--hrn-form-bg, #f7f8fb);
    }

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
    .hrn-authbrand__wordmark {
        font-size: 1.5rem; font-weight: 800; letter-spacing: -0.03em; color: #fff;
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
    .hrn-authbrand__chips {
        list-style: none; margin: 1.6rem 0 0; padding: 0;
        display: flex; flex-wrap: wrap; gap: 0.6rem;
    }
    .hrn-authbrand__chips li {
        display: inline-flex; align-items: center; gap: 0.5rem;
        font-size: 0.82rem; font-weight: 600; color: #e6edff;
        padding: 0.5rem 0.85rem; border-radius: 999px;
        background: rgba(255,255,255,0.08);
        border: 1px solid rgba(255,255,255,0.14);
        backdrop-filter: blur(6px);
    }
    .hrn-authbrand__chip-ic {
        display: inline-grid; place-items: center;
        width: 1.15rem; height: 1.15rem; border-radius: 50%;
        font-size: 0.72rem; font-weight: 800;
        background: rgba(56,132,255,0.35); color: #fff;
    }

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
        .hrn-authbrand { min-height: auto; }
        .hrn-authbrand__inner {
            padding: 1.6rem 1.4rem 1.9rem;
            gap: 0.9rem;
        }
        .hrn-authbrand__headline { font-size: 1.7rem; margin-bottom: 0.6rem; }
        .hrn-authbrand__sub { font-size: 0.9rem; }
        .hrn-authbrand__mock { display: none; }         /* keep the band tight */
        .hrn-authbrand__foot { display: none; }
        .hrn-authbrand__chips { margin-top: 1.1rem; }
        .fi-simple-layout:has(.hrn-authbrand) .fi-simple-main-ctn {
            flex: 1;
            padding: 2rem 1.25rem 2.5rem;
        }
    }

    @media (max-width: 480px) {
        .hrn-authbrand__headline { font-size: 1.45rem; }
        .hrn-authbrand__chips li { font-size: 0.76rem; padding: 0.42rem 0.7rem; }
    }

    @media (prefers-reduced-motion: reduce) {
        .hrn-authbrand__glow { animation: none; }
    }

    /* Right column background follows the theme (light default / dark panel). */
    .fi-simple-layout:has(.hrn-authbrand) { --hrn-form-bg: #f7f8fb; }
    .dark .fi-simple-layout:has(.hrn-authbrand),
    :root.dark .fi-simple-layout:has(.hrn-authbrand) { --hrn-form-bg: #0f1420; }
</style>
