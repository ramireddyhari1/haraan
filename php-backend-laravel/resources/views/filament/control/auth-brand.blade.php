{{--
    /control sign-in — left brand panel. Injected at SIMPLE_LAYOUT_START (scoped to
    ControlLogin) from AdminPanelProvider.

    Deliberately plain: a light panel, the wordmark, what this console is for, and one
    thin-stroke court drawing in the same line-art style as the rest of /control.
    It used to carry a "PLATFORM TELEMETRY · LIVE" mock with invented figures
    (₹12.4L GMV, 8,940 tickets, 99.98% uptime), glow blobs and buzzword chips —
    a public page must never show numbers the business didn't produce.
--}}
<div class="hrn-ctlbrand">
    <div class="hrn-ctlbrand__inner">
        <div class="hrn-ctlbrand__top">
            <img class="hrn-ctlbrand__logo"
                 src="{{ asset('images/haraan-logo-blue.png') }}"
                 alt="Haraan" width="1680" height="445">
            <span class="hrn-ctlbrand__tag">Control</span>
        </div>

        <div class="hrn-ctlbrand__mid">
            <h1 class="hrn-ctlbrand__headline">The staff console<br>behind haraan.app</h1>

            <ul class="hrn-ctlbrand__list">
                <li><span>Events &amp; tickets</span><em>listings, check-in, host review</em></li>
                <li><span>GameHub</span><em>venues, courts, slots, live matches</em></li>
                <li><span>Money</span><em>bookings, partner payouts, memberships</em></li>
                <li><span>People</span><em>players, partners, staff &amp; HR</em></li>
            </ul>
        </div>

        {{-- One drawn object: a court in plan view, thin blue strokes on the panel tint. --}}
        <svg class="hrn-ctlbrand__court" viewBox="0 0 520 220" fill="none" aria-hidden="true">
            <rect x="10" y="10" width="500" height="200" rx="6" stroke="currentColor" stroke-width="1.5"/>
            <line x1="260" y1="10" x2="260" y2="210" stroke="currentColor" stroke-width="1.5"/>
            <circle cx="260" cy="110" r="34" stroke="currentColor" stroke-width="1.5"/>
            <circle cx="260" cy="110" r="2.5" fill="currentColor"/>
            <rect x="10" y="55" width="70" height="110" stroke="currentColor" stroke-width="1.5"/>
            <rect x="440" y="55" width="70" height="110" stroke="currentColor" stroke-width="1.5"/>
            <rect x="10" y="85" width="26" height="50" stroke="currentColor" stroke-width="1.5"/>
            <rect x="484" y="85" width="26" height="50" stroke="currentColor" stroke-width="1.5"/>
            <path d="M80 82a30 30 0 0 1 0 56M440 82a30 30 0 0 0 0 56" stroke="currentColor" stroke-width="1.5"/>
        </svg>

        <p class="hrn-ctlbrand__foot">Staff accounts only. Ask a super admin if you need access.</p>
    </div>
</div>

<style>
    .fi-simple-layout:has(.hrn-ctlbrand) {
        position: relative;
        display: grid;
        grid-template-columns: 1fr 1fr;
        min-height: 100dvh;
        padding: 0;
        gap: 0;
        align-items: stretch;
        background: var(--hrn-form-bg, #ffffff);
    }

    .fi-simple-layout:has(.hrn-ctlbrand) .fi-simple-main-ctn {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2.5rem 2rem;
    }
    .fi-simple-layout:has(.hrn-ctlbrand) main.fi-simple-main { width: 100%; max-width: 25rem; }

    .fi-simple-layout:has(.hrn-ctlbrand) .fi-simple-page {
        background: transparent;
        box-shadow: none;
        border: 0;
        padding: 0;
        gap: 1.5rem;
    }
    .fi-simple-layout:has(.hrn-ctlbrand) .fi-simple-page .fi-logo,
    .fi-simple-layout:has(.hrn-ctlbrand) .fi-simple-header .fi-logo { display: none; }

    .fi-simple-layout:has(.hrn-ctlbrand) .fi-simple-header {
        text-align: left;
        align-items: flex-start;
        gap: 0.35rem;
    }
    .fi-simple-layout:has(.hrn-ctlbrand) .fi-simple-header .fi-header-heading,
    .fi-simple-layout:has(.hrn-ctlbrand) .fi-simple-header h1 {
        font-size: 1.5rem;
        font-weight: 700;
        letter-spacing: -0.02em;
        color: #0f172a;
    }
    .dark .fi-simple-layout:has(.hrn-ctlbrand) .fi-simple-header .fi-header-heading,
    .dark .fi-simple-layout:has(.hrn-ctlbrand) .fi-simple-header h1 { color: #f8fafc; }
    .fi-simple-layout:has(.hrn-ctlbrand) .fi-simple-header .fi-simple-header-subheading,
    .fi-simple-layout:has(.hrn-ctlbrand) .fi-simple-header .fi-header-subheading {
        font-size: 0.9rem;
        color: #64748b;
        text-align: left;
    }
    /* The form sits straight on the page; no floating card. */
    .fi-simple-layout:has(.hrn-ctlbrand) main.fi-simple-main {
        background: transparent;
        box-shadow: none !important;
        border: 0;
        padding: 0;
    }

    .fi-simple-layout:has(.hrn-ctlbrand) .fi-input-wrp { border-radius: 0.6rem; min-height: 2.75rem; }
    .fi-simple-layout:has(.hrn-ctlbrand) .fi-sc-actions .fi-btn {
        min-height: 2.75rem;
        border-radius: 0.6rem;
        font-weight: 600;
    }

    /* ── Brand panel: light, flat, one drawing ─────────────────────────────── */
    .hrn-ctlbrand {
        display: flex;
        background: #f3f6fc;
        border-right: 1px solid #e5eaf3;
        color: #0f172a;
    }
    .dark .hrn-ctlbrand { background: #0d1320; border-right-color: #1c2433; color: #e2e8f0; }

    .hrn-ctlbrand__inner {
        display: flex;
        flex-direction: column;
        width: 100%;
        padding: clamp(2rem, 4.5vw, 3.75rem);
        gap: 2rem;
    }
    .hrn-ctlbrand__top { display: flex; align-items: center; gap: 0.75rem; }
    .hrn-ctlbrand__logo { height: 1.9rem; width: auto; display: block; }
    .hrn-ctlbrand__tag {
        font-size: 0.68rem;
        font-weight: 600;
        letter-spacing: 0.18em;
        text-transform: uppercase;
        color: #64748b;
        padding-left: 0.75rem;
        border-left: 1px solid #cbd5e1;
    }

    .hrn-ctlbrand__mid { margin-top: auto; }
    .hrn-ctlbrand__headline {
        font-size: clamp(1.9rem, 3vw, 2.6rem);
        line-height: 1.12;
        font-weight: 700;
        letter-spacing: -0.03em;
        margin: 0 0 1.75rem;
    }
    .hrn-ctlbrand__list { list-style: none; margin: 0; padding: 0; max-width: 30rem; }
    .hrn-ctlbrand__list li {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        padding: 0.7rem 0;
        border-top: 1px solid #e2e8f0;
        font-size: 0.9rem;
    }
    .hrn-ctlbrand__list li:last-child { border-bottom: 1px solid #e2e8f0; }
    .hrn-ctlbrand__list span { font-weight: 600; }
    .hrn-ctlbrand__list em { font-style: normal; color: #64748b; text-align: right; }
    .dark .hrn-ctlbrand__list li, .dark .hrn-ctlbrand__list li:last-child { border-color: #1f2937; }
    .dark .hrn-ctlbrand__list em, .dark .hrn-ctlbrand__tag { color: #94a3b8; }

    .hrn-ctlbrand__court { width: 100%; max-width: 30rem; height: auto; color: #2563eb; opacity: 0.35; }
    .dark .hrn-ctlbrand__court { color: #60a5fa; opacity: 0.3; }

    .hrn-ctlbrand__foot { margin: 0; font-size: 0.8rem; color: #64748b; }

    @media (max-width: 1023px) {
        .fi-simple-layout:has(.hrn-ctlbrand) { display: flex; flex-direction: column; }
        .hrn-ctlbrand { border-right: 0; border-bottom: 1px solid #e5eaf3; }
        .hrn-ctlbrand__inner { padding: 1.5rem 1.25rem; gap: 1rem; }
        .hrn-ctlbrand__mid { margin-top: 0.5rem; }
        .hrn-ctlbrand__headline { font-size: 1.5rem; margin: 0; }
        .hrn-ctlbrand__list, .hrn-ctlbrand__court, .hrn-ctlbrand__foot { display: none; }
        .fi-simple-layout:has(.hrn-ctlbrand) .fi-simple-main-ctn { flex: 1; padding: 2rem 1.25rem 2.5rem; }
    }

    .dark .fi-simple-layout:has(.hrn-ctlbrand) { --hrn-form-bg: #070c18; }
</style>
