{{--
    Haraan Control (/control) — World-Class SaaS Split Authentication Screen.
    Engineered to the benchmark quality of Stripe, Linear, Vercel, and Clerk.
    Injected at PanelsRenderHook::SIMPLE_LAYOUT_START from AdminPanelProvider.
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
            <span class="hrn-ctlbrand__pill">
                <span class="hrn-live-dot"></span>
                CONTROL · ENTERPRISE
            </span>
        </div>

        <div class="hrn-ctlbrand__mid">
            <h1 class="hrn-ctlbrand__headline">
                One console.<br>The whole platform.
            </h1>
            <p class="hrn-ctlbrand__sub">
                The Haraan command plane — orchestrate events, monitor venues &amp; users, review
                bookings and settlements, and keep consumer channels running with zero downtime.
            </p>

            <ul class="hrn-ctlbrand__chips">
                <li><span class="hrn-ctlbrand__chip-ic">◎</span> Command Center</li>
                <li><span class="hrn-ctlbrand__chip-ic">₹</span> Payments Telemetry</li>
                <li><span class="hrn-ctlbrand__chip-ic">⚡</span> Real-time Platform</li>
            </ul>
        </div>

        {{-- Floating Telemetry Console Mock --}}
        <div class="hrn-ctlbrand__mock">
            <div class="hrn-ctlbrand__mock-header">
                <span class="hrn-ctlbrand__mock-title">PLATFORM TELEMETRY</span>
                <span class="hrn-ctlbrand__mock-live">
                    <span class="hrn-ctlbrand__mock-dot"></span> LIVE
                </span>
            </div>
            <div class="hrn-ctlbrand__mock-row">
                <div class="hrn-ctlbrand__mock-kpi">
                    <span class="hrn-ctlbrand__mock-k">₹ 12.4L</span>
                    <span class="hrn-ctlbrand__mock-l">GMV this week</span>
                </div>
                <div class="hrn-ctlbrand__mock-kpi">
                    <span class="hrn-ctlbrand__mock-k">8,940</span>
                    <span class="hrn-ctlbrand__mock-l">Tickets verified</span>
                </div>
                <div class="hrn-ctlbrand__mock-kpi">
                    <span class="hrn-ctlbrand__mock-k">99.98%</span>
                    <span class="hrn-ctlbrand__mock-l">System uptime</span>
                </div>
            </div>
            <div class="hrn-ctlbrand__mock-bars">
                <i style="height:44%"></i><i style="height:66%"></i><i style="height:52%"></i>
                <i style="height:84%"></i><i style="height:60%"></i><i style="height:96%"></i>
                <i style="height:74%"></i><i style="height:88%"></i><i style="height:92%"></i>
            </div>
        </div>

        <div class="hrn-ctlbrand__foot">
            <svg class="w-3.5 h-3.5 inline-block text-emerald-400 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            <span>Staff authorization required — audit telemetry active</span>
        </div>
    </div>
</div>

{{-- Right column brand insignia --}}
<div class="hrn-ctlbrand-rbrand">
    <img class="hrn-ctlbrand-rlogo"
         src="{{ asset('images/haraan-logo-blue.png') }}"
         alt="haraan" width="1680" height="445">
    <span class="hrn-ctlbrand-rtag">Control</span>
</div>

<style>
    /* ─────────────────────────────────────────────────────────────────────────
       SPLIT SHELL — Grid layout for Control Auth (Stripe/Linear model)
       ───────────────────────────────────────────────────────────────────────── */
    .fi-simple-layout:has(.hrn-ctlbrand) {
        position: relative;
        display: grid;
        grid-template-columns: 1.08fr 0.92fr;
        min-height: 100dvh;
        padding: 0;
        gap: 0;
        align-items: stretch;
        background: var(--hrn-form-bg, #f6f8fb);
    }

    /* Right column brand insignia */
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
        height: 2.1rem;
        width: auto;
        display: block;
    }
    .hrn-ctlbrand-rtag {
        font-family: 'Inter', -apple-system, sans-serif;
        font-size: 0.75rem;
        font-weight: 800;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        color: var(--primary-600, #059669);
        padding-right: 0.2rem;
    }
    .dark .hrn-ctlbrand-rbrand { opacity: 0.95; }

    .fi-simple-layout:has(.hrn-ctlbrand) .fi-simple-main-ctn {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2.5rem 2rem;
    }

    .fi-simple-layout:has(.hrn-ctlbrand) main.fi-simple-main {
        width: 100%;
        max-width: 27rem;
    }

    /* Form card flattening */
    .fi-simple-layout:has(.hrn-ctlbrand) .fi-simple-page {
        background: transparent;
        box-shadow: none;
        border: 0;
        padding: 0;
        gap: 1.75rem;
    }

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
        font-size: 1.75rem;
        font-weight: 800;
        letter-spacing: -0.025em;
        color: var(--hrn-ink, #070c18);
    }
    .dark .fi-simple-layout:has(.hrn-ctlbrand) .fi-simple-header .fi-header-heading,
    .dark .fi-simple-layout:has(.hrn-ctlbrand) .fi-simple-header h1 {
        color: #f8fafc;
    }
    .fi-simple-layout:has(.hrn-ctlbrand) .fi-simple-header .fi-header-subheading {
        font-size: 0.92rem;
        color: var(--hrn-ink-3, #64748b);
    }

    /* Inputs — Geometric radius & Emerald focus ring */
    .fi-simple-layout:has(.hrn-ctlbrand) .fi-input-wrp {
        border-radius: 0.75rem;
        min-height: 3rem;
        border: 1px solid var(--hrn-border, #e2e8f0);
        background: var(--hrn-surface, #ffffff);
        box-shadow: 0 1px 2px rgba(9, 15, 29, 0.03);
        transition: all 0.18s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .fi-simple-layout:has(.hrn-ctlbrand) .fi-input-wrp:focus-within {
        border-color: var(--primary-500, #10b981) !important;
        box-shadow: 0 0 0 1px var(--primary-500, #10b981), 0 0 0 4px rgba(16, 185, 129, 0.18) !important;
    }
    .fi-simple-layout:has(.hrn-ctlbrand) .fi-fieldset,
    .fi-simple-layout:has(.hrn-ctlbrand) .fi-input {
        font-size: 0.95rem;
    }

    /* Submit Button — Tactile Emerald Specular CTA */
    .fi-simple-layout:has(.hrn-ctlbrand) .fi-sc-actions .fi-btn {
        min-height: 3rem;
        border-radius: 0.75rem;
        font-weight: 700;
        font-size: 0.95rem;
        letter-spacing: -0.01em;
        color: #ffffff;
        background: linear-gradient(180deg, #059669 0%, #047857 100%);
        border: 1px solid rgba(255, 255, 255, 0.12);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.28),
                    0 2px 4px rgba(4, 120, 87, 0.15),
                    0 8px 24px -6px rgba(5, 150, 105, 0.5);
        transition: transform 0.08s cubic-bezier(0.4, 0, 0.2, 1),
                    box-shadow 0.15s ease,
                    filter 0.15s ease;
    }
    .fi-simple-layout:has(.hrn-ctlbrand) .fi-sc-actions .fi-btn:hover {
        filter: brightness(1.04);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.35),
                    0 12px 28px -6px rgba(5, 150, 105, 0.65);
    }
    .fi-simple-layout:has(.hrn-ctlbrand) .fi-sc-actions .fi-btn:active {
        transform: scale(0.98);
    }

    /* ─────────────────────────────────────────────────────────────────────────
       BRAND PANEL — Emerald Aurora Canvas
       ───────────────────────────────────────────────────────────────────────── */
    .hrn-ctlbrand {
        position: relative;
        overflow: hidden;
        display: flex;
        color: #eafbee;
        background:
            radial-gradient(1100px 620px at 20% -10%, rgba(16, 185, 129, 0.45), transparent 65%),
            radial-gradient(900px 560px at 105% 12%, rgba(5, 150, 105, 0.4), transparent 60%),
            radial-gradient(800px 500px at 50% 110%, rgba(4, 120, 87, 0.35), transparent 60%),
            linear-gradient(155deg, #041f16 0%, #063826 48%, #031710 100%);
        isolation: isolate;
    }
    .hrn-ctlbrand__glow {
        position: absolute;
        border-radius: 50%;
        filter: blur(65px);
        opacity: 0.55;
        z-index: 0;
        animation: hrn-ctl-float 16s ease-in-out infinite alternate;
    }
    .hrn-ctlbrand__glow--a {
        width: 440px; height: 440px; top: -120px; left: -80px;
        background: radial-gradient(circle, rgba(16, 185, 129, 0.65), transparent 70%);
    }
    .hrn-ctlbrand__glow--b {
        width: 380px; height: 380px; bottom: -140px; right: -60px;
        background: radial-gradient(circle, rgba(5, 150, 105, 0.6), transparent 70%);
        animation-delay: -6s;
    }
    @keyframes hrn-ctl-float {
        from { transform: translate3d(0, 0, 0) scale(1); }
        to   { transform: translate3d(20px, -16px, 0) scale(1.08); }
    }
    .hrn-ctlbrand__grid {
        position: absolute; inset: 0; z-index: 0; opacity: 0.16;
        background-image:
            linear-gradient(rgba(255, 255, 255, 0.45) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255, 255, 255, 0.45) 1px, transparent 1px);
        background-size: 40px 40px;
        mask-image: radial-gradient(120% 90% at 30% 10%, #000 30%, transparent 75%);
        -webkit-mask-image: radial-gradient(120% 90% at 30% 10%, #000 30%, transparent 75%);
    }

    .hrn-ctlbrand__inner {
        position: relative; z-index: 1;
        display: flex; flex-direction: column;
        width: 100%;
        padding: clamp(2.2rem, 4.5vw, 4rem);
        gap: 1.5rem;
    }

    .hrn-ctlbrand__top { display: flex; align-items: center; gap: 0.85rem; }
    .hrn-ctlbrand__logo { height: 2rem; width: auto; display: block; }
    .hrn-ctlbrand__pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 0.65rem; font-weight: 800; letter-spacing: 0.14em;
        padding: 0.3rem 0.65rem; border-radius: 999px;
        color: #d1fae5; background: rgba(16, 185, 129, 0.18);
        border: 1px solid rgba(16, 185, 129, 0.35);
        backdrop-filter: blur(8px);
    }

    .hrn-ctlbrand__mid { margin-top: auto; }
    .hrn-ctlbrand__headline {
        font-size: clamp(2.2rem, 3.6vw, 3.2rem);
        line-height: 1.05; font-weight: 800; letter-spacing: -0.035em;
        color: #ffffff; margin: 0 0 1rem;
    }
    .hrn-ctlbrand__sub {
        font-size: clamp(0.95rem, 1.2vw, 1.05rem);
        line-height: 1.6; color: rgba(220, 252, 231, 0.85);
        max-width: 31rem; margin: 0;
    }
    .hrn-ctlbrand__chips {
        list-style: none; margin: 1.75rem 0 0; padding: 0;
        display: flex; flex-wrap: wrap; gap: 0.65rem;
    }
    .hrn-ctlbrand__chips li {
        display: inline-flex; align-items: center; gap: 0.5rem;
        font-size: 0.82rem; font-weight: 600; color: #ecfdf5;
        padding: 0.5rem 0.9rem; border-radius: 999px;
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.16);
        backdrop-filter: blur(8px);
    }
    .hrn-ctlbrand__chip-ic {
        display: inline-grid; place-items: center;
        width: 1.2rem; height: 1.2rem; border-radius: 50%;
        font-size: 0.72rem; font-weight: 800;
        background: rgba(16, 185, 129, 0.35); color: #ffffff;
    }

    /* Floating Telemetry Console Mock */
    .hrn-ctlbrand__mock {
        margin-top: 1.75rem;
        border-radius: 1.1rem;
        padding: 1.2rem 1.3rem 1rem;
        background: linear-gradient(180deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.03));
        border: 1px solid rgba(255, 255, 255, 0.15);
        box-shadow: 0 24px 60px -28px rgba(0, 0, 0, 0.65);
        backdrop-filter: blur(12px);
    }
    .hrn-ctlbrand__mock-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 0.85rem;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }
    .hrn-ctlbrand__mock-title {
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.1em;
        color: rgba(209, 250, 229, 0.75);
    }
    .hrn-ctlbrand__mock-live {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 0.65rem;
        font-weight: 800;
        color: #34d399;
    }
    .hrn-ctlbrand__mock-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background-color: #34d399;
        box-shadow: 0 0 8px #34d399;
    }
    .hrn-ctlbrand__mock-row { display: flex; gap: 1.5rem; margin-bottom: 0.85rem; }
    .hrn-ctlbrand__mock-kpi { display: flex; flex-direction: column; gap: 0.15rem; }
    .hrn-ctlbrand__mock-k {
        font-size: 1.15rem; font-weight: 800; color: #ffffff;
        font-variant-numeric: tabular-nums; letter-spacing: -0.02em;
    }
    .hrn-ctlbrand__mock-l { font-size: 0.68rem; color: rgba(209, 250, 229, 0.7); }
    .hrn-ctlbrand__mock-bars {
        display: flex; align-items: flex-end; gap: 0.4rem; height: 44px;
    }
    .hrn-ctlbrand__mock-bars i {
        flex: 1; border-radius: 3px 3px 0 0;
        background: linear-gradient(180deg, #34d399, #059669);
        opacity: 0.85;
    }

    .hrn-ctlbrand__foot {
        margin-top: 1.25rem;
        font-size: 0.75rem; color: rgba(209, 250, 229, 0.65);
        display: flex;
        align-items: center;
    }

    /* Mobile stack */
    @media (max-width: 1023px) {
        .fi-simple-layout:has(.hrn-ctlbrand) {
            display: flex;
            flex-direction: column;
            min-height: 100dvh;
        }
        .hrn-ctlbrand {
            min-height: auto;
            border-radius: 0 0 1.75rem 1.75rem;
            box-shadow: 0 18px 40px -24px rgba(3, 31, 22, 0.9);
        }
        .hrn-ctlbrand-rbrand { display: none; }
        .hrn-ctlbrand__inner {
            padding: 1.75rem 1.5rem 2rem;
            gap: 0.65rem;
        }
        .hrn-ctlbrand__mock { display: none; }
        .hrn-ctlbrand__headline { font-size: 1.85rem; line-height: 1.15; }
        .fi-simple-layout:has(.hrn-ctlbrand) .fi-simple-main-ctn {
            flex: 1;
            padding: 2rem 1.5rem 2.5rem;
        }
    }

    /* Right column background follows theme */
    .fi-simple-layout:has(.hrn-ctlbrand) { --hrn-form-bg: #f6f8fb; }
    .dark .fi-simple-layout:has(.hrn-ctlbrand),
    :root.dark .fi-simple-layout:has(.hrn-ctlbrand) { --hrn-form-bg: #070c18; }
</style>
