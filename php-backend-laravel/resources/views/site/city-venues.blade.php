@extends('site.layout')

@section('content')
<style>
    /* ==========================================================================
       HARAAN CITY VENUES & TURF HUB — PHYSICAL & ATHLETIC REDESIGN
       Designed to feel tactile, alive, and authentic to local sports culture.
       ========================================================================== */

    :root {
        --hrn-navy-950: #080D1A;
        --hrn-navy-900: #0F172A;
        --hrn-navy-800: #1E293B;
        --hrn-blue-600: #2563EB;
        --hrn-blue-700: #1D4ED8;
        --hrn-blue-50: #EFF6FF;
        --hrn-emerald-600: #059669;
        --hrn-emerald-500: #10B981;
        --hrn-emerald-50: #ECFDF5;
        --hrn-amber-500: #F59E0B;
        --hrn-slate-100: #F1F5F9;
        --hrn-slate-200: #E2E8F0;
        --hrn-slate-400: #94A3B8;
        --hrn-slate-600: #475569;
        --hrn-radius-lg: 20px;
        --hrn-radius-md: 14px;
        --hrn-radius-sm: 8px;
    }

    /* ── STADIUM CANOPY HERO ─────────────────────────────────────────────── */
    .stadium-hero {
        position: relative;
        background:
            radial-gradient(130% 90% at 50% -10%, rgba(37, 99, 235, 0.22) 0%, rgba(15, 23, 42, 0.98) 65%),
            radial-gradient(60% 50% at 85% 20%, rgba(16, 185, 129, 0.12) 0%, transparent 70%),
            #0B1220;
        color: #FFFFFF;
        padding: 44px 20px 36px;
        border-radius: 0 0 28px 28px;
        margin-bottom: 32px;
        overflow: hidden;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        box-shadow: 0 20px 40px -15px rgba(8, 13, 26, 0.5);
    }

    /* Subtle turf pitch markings pattern in hero background */
    .stadium-hero::before {
        content: "";
        position: absolute;
        inset: 0;
        background-image:
            linear-gradient(rgba(255, 255, 255, 0.03) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
        background-size: 32px 32px;
        pointer-events: none;
        opacity: 0.8;
    }

    /* Floodlight beam glow */
    .stadium-hero::after {
        content: "";
        position: absolute;
        top: -60px;
        left: 50%;
        transform: translateX(-50%);
        width: 600px;
        height: 200px;
        background: radial-gradient(ellipse at center, rgba(96, 165, 250, 0.25) 0%, transparent 70%);
        pointer-events: none;
        filter: blur(20px);
    }

    .stadium-hero__inner {
        position: relative;
        z-index: 2;
        max-width: 1140px;
        margin: 0 auto;
    }

    /* Breadcrumbs */
    .stadium-crumbs {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        color: #94A3B8;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }
    .stadium-crumbs a {
        color: #93C5FD;
        text-decoration: none;
        font-weight: 600;
        transition: color 0.15s ease;
    }
    .stadium-crumbs a:hover { color: #FFFFFF; text-decoration: underline; }
    .stadium-crumbs .crumb-sep { color: #475569; font-size: 11px; }

    /* Live Turf Network Pill */
    .stadium-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: rgba(16, 185, 129, 0.14);
        border: 1px solid rgba(16, 185, 129, 0.35);
        color: #34D399;
        font-size: 11.5px;
        font-weight: 800;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        padding: 6px 14px;
        border-radius: 999px;
        margin-bottom: 16px;
        backdrop-filter: blur(6px);
    }
    .stadium-badge__dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #10B981;
        box-shadow: 0 0 10px #10B981;
        animation: turfPulse 2s infinite ease-in-out;
    }
    @keyframes turfPulse {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.5; transform: scale(1.3); }
    }

    /* Hero Headline */
    .stadium-hero h1 {
        font-size: clamp(26px, 4vw, 38px);
        font-weight: 900;
        letter-spacing: -0.03em;
        color: #FFFFFF;
        margin: 0 0 14px;
        line-height: 1.18;
        font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
    }
    .stadium-hero h1 span.city-highlight {
        color: #60A5FA;
        text-shadow: 0 0 24px rgba(37, 99, 235, 0.4);
    }

    .stadium-hero p.lead {
        font-size: 15.5px;
        color: #CBD5E1;
        max-width: 780px;
        line-height: 1.65;
        margin: 0 0 28px;
    }

    /* ── TACTILE SPORT PILLS (Replacing generic links) ─────────────────── */
    .stadium-sports-nav {
        display: flex;
        gap: 10px;
        overflow-x: auto;
        padding-bottom: 8px;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
    }
    .stadium-sports-nav::-webkit-scrollbar { display: none; }

    .stadium-sport-pill {
        display: inline-flex;
        align-items: center;
        gap: 9px;
        padding: 10px 18px;
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.16);
        border-radius: 999px;
        font-size: 13.5px;
        font-weight: 700;
        color: #E2E8F0;
        text-decoration: none;
        white-space: nowrap;
        transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        backdrop-filter: blur(8px);
        cursor: pointer;
    }
    .stadium-sport-pill svg {
        flex-shrink: 0;
        color: #93C5FD;
        transition: transform 0.2s ease, color 0.2s ease;
    }
    .stadium-sport-pill:hover {
        background: rgba(255, 255, 255, 0.16);
        border-color: rgba(147, 197, 253, 0.5);
        color: #FFFFFF;
        transform: translateY(-1px);
    }
    .stadium-sport-pill:hover svg {
        color: #60A5FA;
        transform: scale(1.1);
    }
    .stadium-sport-pill.is-active {
        background: #2563EB;
        border-color: #3B82F6;
        color: #FFFFFF;
        box-shadow: 0 4px 18px rgba(37, 99, 235, 0.45);
    }
    .stadium-sport-pill.is-active svg {
        color: #FFFFFF;
    }

    /* ── CONTAINER ──────────────────────────────────────────────────────── */
    .turf-container {
        max-width: 1140px;
        margin: 0 auto;
        padding: 0 16px;
    }

    /* ── PHYSICAL TURF SPECIFICATION STRIP ──────────────────────────────── */
    /* Replaces the generic 4-card emoji pattern with an authentic athletic spec HUD */
    .turf-specs-strip {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 16px;
        margin-bottom: 40px;
    }
    .turf-spec-card {
        background: #FFFFFF;
        border: 1.5px solid var(--hrn-slate-200);
        border-radius: var(--hrn-radius-md);
        padding: 16px 18px;
        display: flex;
        align-items: flex-start;
        gap: 14px;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03);
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .turf-spec-card:hover {
        border-color: #93C5FD;
        box-shadow: 0 6px 16px rgba(37, 99, 235, 0.07);
    }
    .turf-spec-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        background: #F0FDF4;
        color: #059669;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        border: 1px solid rgba(16, 185, 129, 0.2);
    }
    .turf-spec-icon.icon-blue {
        background: #EFF6FF;
        color: #2563EB;
        border-color: rgba(37, 99, 235, 0.2);
    }
    .turf-spec-icon.icon-amber {
        background: #FFFBEB;
        color: #D97706;
        border-color: rgba(245, 158, 11, 0.2);
    }
    .turf-spec-icon.icon-indigo {
        background: #EEF2FF;
        color: #4F46E5;
        border-color: rgba(79, 70, 229, 0.2);
    }
    .turf-spec-content strong {
        display: block;
        font-size: 14.5px;
        font-weight: 800;
        color: var(--hrn-navy-900);
        margin-bottom: 2px;
    }
    .turf-spec-content span {
        display: block;
        font-size: 12.5px;
        color: var(--hrn-slate-600);
        line-height: 1.45;
    }

    /* ── SECTION HEADINGS ────────────────────────────────────────────────── */
    .turf-section-head {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        margin-bottom: 22px;
        gap: 16px;
        flex-wrap: wrap;
    }
    .turf-section-head__title {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .turf-section-head h2 {
        font-size: 23px;
        font-weight: 900;
        color: var(--hrn-navy-900);
        margin: 0;
        letter-spacing: -0.02em;
    }
    .turf-count-chip {
        font-size: 12px;
        font-weight: 800;
        background: #EFF6FF;
        color: #2563EB;
        padding: 4px 10px;
        border-radius: 999px;
        border: 1px solid rgba(37, 99, 235, 0.2);
    }

    /* ── VENUE CARDS (PHYSICAL ARENA PASS FEEL) ─────────────────────────── */
    .turf-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 24px;
        margin-bottom: 48px;
    }
    .turf-card {
        background: #FFFFFF;
        border: 1.5px solid var(--hrn-slate-200);
        border-radius: var(--hrn-radius-lg);
        overflow: hidden;
        text-decoration: none;
        color: inherit;
        display: flex;
        flex-direction: column;
        transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.25s ease, border-color 0.25s ease;
        position: relative;
    }
    .turf-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 18px 36px -10px rgba(15, 23, 42, 0.12);
        border-color: #2563EB;
    }
    .turf-card__media {
        position: relative;
        aspect-ratio: 16/9;
        background: #0F172A;
        overflow: hidden;
    }
    .turf-card__media img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.3s ease;
    }
    .turf-card:hover .turf-card__media img {
        transform: scale(1.04);
    }
    .turf-card__scrim {
        position: absolute;
        inset: 0;
        background: linear-gradient(180deg, rgba(0,0,0,0.1) 40%, rgba(0,0,0,0.6) 100%);
    }
    .turf-card__surface-tag {
        position: absolute;
        top: 12px;
        left: 12px;
        background: rgba(15, 23, 42, 0.85);
        backdrop-filter: blur(6px);
        color: #FFFFFF;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        padding: 4px 10px;
        border-radius: 6px;
        border: 1px solid rgba(255, 255, 255, 0.15);
    }
    .turf-card__rating {
        position: absolute;
        top: 12px;
        right: 12px;
        background: #FFFFFF;
        color: #D97706;
        font-size: 12px;
        font-weight: 800;
        padding: 4px 9px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        gap: 4px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.15);
    }
    .turf-card__live-status {
        position: absolute;
        bottom: 12px;
        left: 12px;
        background: rgba(16, 185, 129, 0.9);
        color: #FFFFFF;
        font-size: 11px;
        font-weight: 800;
        padding: 3px 8px;
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    .turf-card__live-status i {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: #FFFFFF;
    }

    .turf-card__body {
        padding: 20px;
        display: flex;
        flex-direction: column;
        flex-grow: 1;
    }
    .turf-card__title {
        font-size: 18px;
        font-weight: 900;
        color: var(--hrn-navy-900);
        margin: 0 0 6px;
        line-height: 1.3;
    }
    .turf-card__location {
        display: flex;
        align-items: center;
        gap: 5px;
        font-size: 13px;
        color: var(--hrn-slate-600);
        margin: 0 0 14px;
    }
    .turf-card__location svg {
        color: #64748B;
        flex-shrink: 0;
    }
    .turf-card__specs-row {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-bottom: 16px;
    }
    .turf-card__chip {
        font-size: 11.5px;
        font-weight: 700;
        padding: 4px 9px;
        border-radius: 6px;
        background: #F1F5F9;
        color: #334155;
    }
    .turf-card__foot {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: auto;
        padding-top: 16px;
        border-top: 1.5px solid #F1F5F9;
    }
    .turf-card__price strong {
        font-size: 20px;
        font-weight: 900;
        color: var(--hrn-navy-900);
        font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
    }
    .turf-card__price span {
        font-size: 12px;
        color: #64748B;
        font-weight: 600;
    }
    .turf-card__cta {
        background: #2563EB;
        color: #FFFFFF;
        font-size: 13.5px;
        font-weight: 800;
        padding: 9px 18px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: background 0.15s ease, transform 0.15s ease;
    }
    .turf-card:hover .turf-card__cta {
        background: #1D4ED8;
    }

    /* ── KADAPA TURF RADAR & SCOUT (TRANSFORMS THE EMPTY STATE) ──────────── */
    .radar-hub {
        background: #FFFFFF;
        border: 1.5px solid var(--hrn-slate-200);
        border-radius: var(--hrn-radius-lg);
        padding: 32px 24px;
        margin-bottom: 48px;
        box-shadow: 0 4px 20px -5px rgba(15, 23, 42, 0.05);
        position: relative;
        overflow: hidden;
    }
    .radar-hub::before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #2563EB 0%, #10B981 100%);
    }

    .radar-hub__top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 20px;
        margin-bottom: 28px;
        flex-wrap: wrap;
    }
    .radar-hub__lead h3 {
        font-size: 22px;
        font-weight: 900;
        color: var(--hrn-navy-900);
        margin: 0 0 8px;
        letter-spacing: -0.02em;
    }
    .radar-hub__lead p {
        font-size: 14.5px;
        color: var(--hrn-slate-600);
        margin: 0;
        max-width: 650px;
        line-height: 1.6;
    }
    .radar-hub__pulse-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: #ECFDF5;
        border: 1px solid rgba(16, 185, 129, 0.3);
        color: #065F46;
        font-size: 12px;
        font-weight: 800;
        padding: 6px 14px;
        border-radius: 999px;
        white-space: nowrap;
    }

    /* Local Kadapa Sporting Corridors */
    .radar-corridors {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 16px;
        margin-bottom: 30px;
    }
    .corridor-card {
        background: #F8FAFC;
        border: 1.5px solid #E2E8F0;
        border-radius: var(--hrn-radius-md);
        padding: 16px;
        transition: all 0.2s ease;
    }
    .corridor-card:hover {
        background: #FFFFFF;
        border-color: #2563EB;
        box-shadow: 0 6px 18px rgba(37, 99, 235, 0.08);
    }
    .corridor-card__zone {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 8px;
    }
    .corridor-card__zone strong {
        font-size: 14.5px;
        font-weight: 800;
        color: var(--hrn-navy-900);
    }
    .corridor-card__tag {
        font-size: 10.5px;
        font-weight: 800;
        text-transform: uppercase;
        padding: 2px 7px;
        border-radius: 4px;
        background: #EFF6FF;
        color: #2563EB;
    }
    .corridor-card p {
        font-size: 13px;
        color: var(--hrn-slate-600);
        line-height: 1.5;
        margin: 0;
    }

    /* Interactive "Request a Turf Slot in Kadapa" Physical Card */
    .radar-action-box {
        background: linear-gradient(135deg, #0F172A 0%, #1E293B 100%);
        border-radius: var(--hrn-radius-md);
        padding: 24px;
        color: #FFFFFF;
        display: flex;
        flex-direction: column;
        gap: 18px;
    }
    @media (min-width: 820px) {
        .radar-action-box {
            flex-direction: row;
            align-items: center;
            justify-content: space-between;
        }
    }
    .radar-action-box__info strong {
        display: block;
        font-size: 17px;
        font-weight: 800;
        margin-bottom: 4px;
        color: #FFFFFF;
    }
    .radar-action-box__info p {
        margin: 0;
        font-size: 13.5px;
        color: #94A3B8;
        line-height: 1.5;
    }
    .radar-action-box__cta-group {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }
    .btn-turf-request {
        background: #2563EB;
        color: #FFFFFF;
        font-size: 13.5px;
        font-weight: 800;
        padding: 11px 20px;
        border-radius: 10px;
        text-decoration: none;
        border: none;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: background 0.15s ease, transform 0.15s ease;
    }
    .btn-turf-request:hover {
        background: #1D4ED8;
        transform: translateY(-1px);
    }
    .btn-turf-partner-link {
        color: #93C5FD;
        font-size: 13px;
        font-weight: 700;
        text-decoration: none;
        padding: 10px 14px;
        border-radius: 10px;
        border: 1px solid rgba(255, 255, 255, 0.2);
        transition: all 0.15s ease;
    }
    .btn-turf-partner-link:hover {
        background: rgba(255, 255, 255, 0.1);
        color: #FFFFFF;
    }

    /* ── STADIUM DIGITAL SCOREBOARD (ACTIONBOARD TICKER) ─────────────────── */
    .scoreboard-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
        gap: 18px;
        margin-bottom: 48px;
    }
    .scoreboard-card {
        background: #0B132B;
        border: 1px solid rgba(255, 255, 255, 0.12);
        border-radius: var(--hrn-radius-md);
        padding: 18px;
        color: #FFFFFF;
        text-decoration: none;
        display: block;
        transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
        position: relative;
        overflow: hidden;
    }
    .scoreboard-card::before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 2px;
        background: linear-gradient(90deg, #2563EB, #60A5FA);
    }
    .scoreboard-card:hover {
        transform: translateY(-3px);
        border-color: #3B82F6;
        box-shadow: 0 12px 28px rgba(0, 0, 0, 0.35);
    }
    .scoreboard-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
    }
    .scoreboard-comp {
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: #94A3B8;
    }
    .scoreboard-badge {
        font-size: 10.5px;
        font-weight: 800;
        text-transform: uppercase;
        padding: 3px 8px;
        border-radius: 4px;
        letter-spacing: 0.05em;
    }
    .scoreboard-badge.is-live {
        background: rgba(16, 185, 129, 0.2);
        color: #34D399;
        border: 1px solid rgba(16, 185, 129, 0.4);
    }
    .scoreboard-badge.is-done {
        background: rgba(148, 163, 184, 0.15);
        color: #CBD5E1;
    }
    .scoreboard-teams {
        font-size: 16px;
        font-weight: 900;
        color: #FFFFFF;
        margin-bottom: 10px;
        line-height: 1.35;
        font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
    }
    .scoreboard-foot {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-top: 10px;
        border-top: 1px solid rgba(255, 255, 255, 0.08);
        font-size: 12px;
        font-weight: 700;
        color: #60A5FA;
    }

    /* ── TURF OWNER PARTNER BILLBOARD ───────────────────────────────────── */
    .partner-billboard {
        background:
            radial-gradient(120% 100% at 90% 10%, rgba(37, 99, 235, 0.28) 0%, transparent 60%),
            #0B1220;
        border: 1px solid rgba(255, 255, 255, 0.12);
        border-radius: 24px;
        padding: 36px 30px;
        color: #FFFFFF;
        margin-bottom: 48px;
        position: relative;
        overflow: hidden;
        box-shadow: 0 16px 36px -10px rgba(11, 18, 32, 0.5);
    }
    .partner-billboard__inner {
        position: relative;
        z-index: 2;
    }
    .partner-billboard h3 {
        font-size: 24px;
        font-weight: 900;
        color: #FFFFFF;
        margin: 0 0 10px;
        letter-spacing: -0.02em;
    }
    .partner-billboard p.lead {
        font-size: 15px;
        color: #CBD5E1;
        max-width: 680px;
        line-height: 1.6;
        margin: 0 0 24px;
    }
    .partner-perks {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
        gap: 16px;
        margin-bottom: 28px;
    }
    .partner-perk {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 12px;
        padding: 14px 16px;
    }
    .partner-perk strong {
        display: block;
        font-size: 13.5px;
        color: #FFFFFF;
        margin-bottom: 4px;
    }
    .partner-perk span {
        font-size: 12px;
        color: #94A3B8;
        line-height: 1.4;
        display: block;
    }
    .partner-billboard .btn-partner-cta {
        background: #2563EB;
        color: #FFFFFF;
        font-size: 14.5px;
        font-weight: 800;
        padding: 13px 26px;
        border-radius: 12px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        border: 1px solid rgba(255, 255, 255, 0.2);
        box-shadow: 0 6px 20px rgba(37, 99, 235, 0.4);
        transition: all 0.2s ease;
    }
    .partner-billboard .btn-partner-cta:hover {
        background: #1D4ED8;
        transform: translateY(-2px);
    }

    /* ── ATHLETIC PLAYBOOK & LOCAL GUIDE ─────────────────────────────────── */
    .turf-playbook {
        background: #FFFFFF;
        border: 1.5px solid var(--hrn-slate-200);
        border-radius: var(--hrn-radius-lg);
        padding: 32px 28px;
        margin-bottom: 48px;
    }
    .turf-playbook h3 {
        font-size: 21px;
        font-weight: 900;
        color: var(--hrn-navy-900);
        margin: 0 0 12px;
        letter-spacing: -0.02em;
    }
    .turf-playbook p.lead {
        font-size: 14.5px;
        color: var(--hrn-slate-600);
        line-height: 1.6;
        margin: 0 0 24px;
        max-width: 820px;
    }
    .playbook-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }
    .playbook-card {
        background: #F8FAFC;
        border: 1.5px solid #E2E8F0;
        border-radius: var(--hrn-radius-md);
        padding: 18px;
    }
    .playbook-card strong {
        display: block;
        font-size: 15px;
        font-weight: 800;
        color: var(--hrn-navy-900);
        margin-bottom: 8px;
    }
    .playbook-card ul {
        margin: 0;
        padding-left: 18px;
        font-size: 13px;
        color: var(--hrn-slate-600);
        line-height: 1.6;
    }

    /* ── TACTILE FAQ ACCORDION ───────────────────────────────────────────── */
    .turf-faq {
        margin-bottom: 60px;
    }
    .turf-faq h3 {
        font-size: 22px;
        font-weight: 900;
        color: var(--hrn-navy-900);
        margin: 0 0 20px;
        letter-spacing: -0.02em;
    }
    .faq-accordion-item {
        background: #FFFFFF;
        border: 1.5px solid var(--hrn-slate-200);
        border-radius: var(--hrn-radius-md);
        margin-bottom: 12px;
        overflow: hidden;
        transition: border-color 0.2s ease;
    }
    .faq-accordion-item[open] {
        border-color: #2563EB;
        box-shadow: 0 6px 16px rgba(37, 99, 235, 0.06);
    }
    .faq-accordion-summary {
        padding: 18px 22px;
        font-size: 15.5px;
        font-weight: 800;
        color: var(--hrn-navy-900);
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        list-style: none;
        user-select: none;
    }
    .faq-accordion-summary::-webkit-details-marker { display: none; }
    .faq-chevron {
        width: 18px;
        height: 18px;
        flex-shrink: 0;
        color: #64748B;
        transition: transform 0.2s ease;
    }
    .faq-accordion-item[open] .faq-chevron {
        transform: rotate(180deg);
        color: #2563EB;
    }
    .faq-accordion-content {
        padding: 0 22px 18px;
        font-size: 14px;
        color: var(--hrn-slate-600);
        line-height: 1.65;
        border-top: 1px solid #F1F5F9;
        margin-top: 4px;
        padding-top: 14px;
    }

    /* Interactive scout modal */
    .turf-toast {
        display: none;
        position: fixed;
        bottom: 24px;
        right: 24px;
        z-index: 9999;
        background: #0F172A;
        color: #FFFFFF;
        padding: 16px 20px;
        border-radius: 14px;
        border: 1px solid rgba(255,255,255,0.15);
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        font-size: 13.5px;
        font-weight: 700;
        align-items: center;
        gap: 12px;
        animation: toastIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    }
    @keyframes toastIn {
        from { transform: translateY(20px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }
</style>

{{-- ========================================================================= --}}
{{-- 1. STADIUM CANOPY HERO                                                    --}}
{{-- ========================================================================= --}}
<div class="stadium-hero">
    <div class="stadium-hero__inner">
        {{-- Breadcrumbs --}}
        <nav class="stadium-crumbs" aria-label="Breadcrumb">
            <a href="/">Home</a>
            <span class="crumb-sep">/</span>
            <a href="/gamehub">Pulse Arenas</a>
            <span class="crumb-sep">/</span>
            <span>{{ $cityName }}</span>
            @if($sportLabel)
                <span class="crumb-sep">/</span>
                <strong style="color: #FFFFFF;">{{ $sportLabel }}</strong>
            @endif
        </nav>

        {{-- Live Turf Network Pulse Badge --}}
        <div class="stadium-badge">
            <span class="stadium-badge__dot"></span>
            <span>Verified Grounds &amp; Floodlit Turfs</span>
        </div>

        {{-- Main Headline --}}
        <h1>
            @if($sportLabel)
                Best {{ $sportLabel }} Turfs in {{ $cityName }}
            @else
                Turfs in {{ $cityName }} — Cricket, Box Cricket &amp; Badminton Courts
            @endif
        </h1>

        <p class="lead">
            @if($sportLabel)
                Looking for turfs in {{ $cityName }}? Compare currently available {{ $sportLabel }} turfs, evening floodlit slots, and synthetic court pricing. Reserve direct hourly slots on Haraan.
            @else
                Looking for turfs in {{ $cityName }}? Compare currently available cricket turfs, box cricket venues, football grounds, and badminton courts. Check hourly slot rates, arena facilities, and book directly.
            @endif
        </p>

        {{-- Tactile Sports Navigation Bar (Real Vector Icons) --}}
        <div class="stadium-sports-nav">
            {{-- All Sports --}}
            <a class="stadium-sport-pill {{ $sportSlug === null ? 'is-active' : '' }}" href="{{ url('/venues/' . $citySlug) }}">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="3" width="7" height="7" rx="1.5"></rect>
                    <rect x="14" y="3" width="7" height="7" rx="1.5"></rect>
                    <rect x="3" y="14" width="7" height="7" rx="1.5"></rect>
                    <rect x="14" y="14" width="7" height="7" rx="1.5"></rect>
                </svg>
                <span>All Sports</span>
            </a>

            {{-- Box Cricket --}}
            <a class="stadium-sport-pill {{ $sportSlug === 'box-cricket' ? 'is-active' : '' }}" href="{{ url('/venues/' . $citySlug . '/box-cricket') }}">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="3" width="18" height="18" rx="3"></rect>
                    <path d="M3 9h18M3 15h18M9 3v18M15 3v18"></path>
                </svg>
                <span>Box Cricket</span>
            </a>

            {{-- Cricket Turfs --}}
            <a class="stadium-sport-pill {{ $sportSlug === 'cricket' ? 'is-active' : '' }}" href="{{ url('/venues/' . $citySlug . '/cricket') }}">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M15.5 3.5a2.1 2.1 0 0 1 3 3L9 16l-3 1 1-3z"></path>
                    <circle cx="6.5" cy="17.5" r="3"></circle>
                </svg>
                <span>Cricket Turfs</span>
            </a>

            {{-- Badminton --}}
            <a class="stadium-sport-pill {{ $sportSlug === 'badminton' ? 'is-active' : '' }}" href="{{ url('/venues/' . $citySlug . '/badminton') }}">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <ellipse cx="9.5" cy="9.5" rx="6.5" ry="5.5" transform="rotate(-45 9.5 9.5)"></ellipse>
                    <path d="M13.5 13.5 20 20M6.5 6.5 12.5 12.5M6.5 12.5 12.5 6.5"></path>
                </svg>
                <span>Badminton Courts</span>
            </a>

            {{-- Football --}}
            <a class="stadium-sport-pill {{ $sportSlug === 'football' ? 'is-active' : '' }}" href="{{ url('/venues/' . $citySlug . '/football') }}">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="9"></circle>
                    <path d="M12 7.5 8.5 10l1.3 4h4.4l1.3-4z"></path>
                    <path d="M12 3v4.5M4 9.5 8.5 10M20 9.5 15.5 10M7 20l2.8-6M17 20l-2.8-6"></path>
                </svg>
                <span>Football Grounds</span>
            </a>
        </div>
    </div>
</div>

<div class="turf-container">

    {{-- ========================================================================= --}}
    {{-- 2. PHYSICAL TURF SPECIFICATION BAR (NO GENERIC EMOJIS)                    --}}
    {{-- ========================================================================= --}}
    <div class="turf-specs-strip">
        <div class="turf-spec-card">
            <div class="turf-spec-icon">
                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 21h18M6 21l3-14 3 14M12 21l3-10 3 10M17 21l2-7 1 7"></path>
                </svg>
            </div>
            <div class="turf-spec-content">
                <strong>FIFA-Grade Astroturf</strong>
                <span>40–50mm synthetic grass with injury-reduction shock infill</span>
            </div>
        </div>

        <div class="turf-spec-card">
            <div class="turf-spec-icon icon-blue">
                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 18h6M10 21h4M12 3a6 6 0 0 1 4 10.5c-.6.6-1 1.4-1 2.2H9c0-.8-.4-1.6-1-2.2A6 6 0 0 1 12 3z"></path>
                </svg>
            </div>
            <div class="turf-spec-content">
                <strong>Night Floodlights</strong>
                <span>Anti-glare high-mast LED grids for evening &amp; midnight games</span>
            </div>
        </div>

        <div class="turf-spec-card">
            <div class="turf-spec-icon icon-amber">
                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="4" width="20" height="16" rx="2"></rect>
                    <path d="M6 4v16M10 4v16M14 4v16M18 4v16M2 8h20M2 12h20M2 16h20"></path>
                </svg>
            </div>
            <div class="turf-spec-content">
                <strong>Enclosed Power Netting</strong>
                <span>High-tension nylon containment for full 360° boundary play</span>
            </div>
        </div>

        <div class="turf-spec-card">
            <div class="turf-spec-icon icon-indigo">
                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m19 5-3-3L4 14l-1 5 5-1L19 5zM14 4l3 3M7 11l3 3"></path>
                    <circle cx="19" cy="19" r="2.5"></circle>
                </svg>
            </div>
            <div class="turf-spec-content">
                <strong>Match Kit &amp; Scoring</strong>
                <span>Turf willow bats, heavy tennis balls, and ActionBoard live stats</span>
            </div>
        </div>
    </div>

    {{-- ========================================================================= --}}
    {{-- 3. VENUES DIRECT LISTINGS (IF POPULATED)                                  --}}
    {{-- ========================================================================= --}}
    @if($venues->isNotEmpty())
        <div class="turf-section-head">
            <div class="turf-section-head__title">
                <h2>
                    @if($sportLabel)
                        {{ $sportLabel }} Arenas in {{ $cityName }}
                    @else
                        Top Rated Turfs in {{ $cityName }}
                    @endif
                </h2>
                <span class="turf-count-chip">{{ $venues->count() }} Ground{{ $venues->count() === 1 ? '' : 's' }} Available</span>
            </div>
        </div>

        <div class="turf-grid">
            @foreach($venues as $v)
                <a class="turf-card" href="{{ \App\Support\SeoUrl::venue($v) }}">
                    <div class="turf-card__media">
                        <img src="{{ $v->image ?: asset('images/haraan-social-card.png') }}" alt="{{ $v->title }} in {{ $cityName }}" loading="lazy">
                        <span class="turf-card__scrim"></span>
                        <span class="turf-card__surface-tag">{{ $v->category ?: 'Sports Turf' }}</span>
                        @if($v->rating)
                            <span class="turf-card__rating">★ {{ number_format((float)$v->rating, 1) }}</span>
                        @endif
                        <span class="turf-card__live-status">
                            <i></i> Instant Booking
                        </span>
                    </div>
                    <div class="turf-card__body">
                        <h3 class="turf-card__title">{{ $v->title }}</h3>
                        <div class="turf-card__location">
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z"></path>
                                <circle cx="12" cy="10" r="3"></circle>
                            </svg>
                            <span>{{ $v->tagline ?: ($v->location ?: $cityName) }}</span>
                        </div>

                        @if(!empty($v->sports))
                            <div class="turf-card__specs-row">
                                @foreach(array_slice($v->sports, 0, 3) as $sp)
                                    <span class="turf-card__chip">{{ ucfirst($sp) }}</span>
                                @endforeach
                            </div>
                        @endif

                        <div class="turf-card__foot">
                            <div class="turf-card__price">
                                <strong>₹{{ number_format($v->price) }}</strong>
                                <span>/ hour</span>
                            </div>
                            <span class="turf-card__cta">
                                Reserve Slot
                                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="5" y1="12" x2="19" y2="12"></line>
                                    <polyline points="12 5 19 12 12 19"></polyline>
                                </svg>
                            </span>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    @else
        {{-- ========================================================================= --}}
        {{-- 4. KADAPA TURF RADAR & SCOUT (REPLACES THE DEAD DASHED BOX)              --}}
        {{-- ========================================================================= --}}
        <div class="radar-hub">
            <div class="radar-hub__top">
                <div class="radar-hub__lead">
                    <h3>Kadapa Turf Radar &amp; Arena Network</h3>
                    <p>
                        We are currently onboarding premier box cricket turfs, indoor badminton academies, and football grounds across {{ $cityName }}. Select your preferred area below to check upcoming match slots or request a team booking.
                    </p>
                </div>
                <div class="radar-hub__pulse-badge">
                    <span class="stadium-badge__dot"></span>
                    <span>Slots Expanding Across Kadapa</span>
                </div>
            </div>

            {{-- Local Kadapa Corridors --}}
            <div class="radar-corridors">
                <div class="corridor-card">
                    <div class="corridor-card__zone">
                        <strong>Nagarajupeta &amp; Central</strong>
                        <span class="corridor-card__tag">Box Cricket</span>
                    </div>
                    <p>Central Kadapa evening box cricket hub. Quick access for corporate squads &amp; fast-paced 6v6 friendly games.</p>
                </div>

                <div class="corridor-card">
                    <div class="corridor-card__zone">
                        <strong>RIMS Road &amp; Bypass</strong>
                        <span class="corridor-card__tag">Floodlit Multi-Sport</span>
                    </div>
                    <p>Spacious arena layouts with wide run-ups, 600W LED floodlights, and dedicated team parking facilities.</p>
                </div>

                <div class="corridor-card">
                    <div class="corridor-card__zone">
                        <strong>Yerramukkapalli &amp; Arts College</strong>
                        <span class="corridor-card__tag">Badminton &amp; Nets</span>
                    </div>
                    <p>Grassroots cricket coaching nets and high-grade synthetic badminton courts with shock-absorbing underlays.</p>
                </div>

                <div class="corridor-card">
                    <div class="corridor-card__zone">
                        <strong>RTC Stand &amp; Railway Hub</strong>
                        <span class="corridor-card__tag">Late Night Slots</span>
                    </div>
                    <p>Ideal transit location for inter-city weekend matches and prime 10:00 PM – 1:00 AM midnight cricket tournaments.</p>
                </div>
            </div>

            {{-- Interactive Turf Scout Action Box --}}
            <div class="radar-action-box">
                <div class="radar-action-box__info">
                    <strong>Want to lock in an hourly slot in {{ $cityName }}?</strong>
                    <p>Join our Kadapa Turf Priority Alert to receive slot alerts on WhatsApp as soon as partner schedules open.</p>
                </div>
                <div class="radar-action-box__cta-group">
                    <button type="button" class="btn-turf-request" id="btn-request-slot" onclick="openScoutModal()">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                        </svg>
                        Get Kadapa Slot Alert
                    </button>
                    <a href="/partner/login" class="btn-turf-partner-link">
                        List Your {{ $cityName }} Turf &rarr;
                    </a>
                </div>
            </div>
        </div>

        {{-- ========================================================================= --}}
        {{-- 5. VERIFIED REGIONAL ARENAS (IF ANY IN DB)                               --}}
        {{-- ========================================================================= --}}
        @if($featuredVenues->isNotEmpty())
            <div class="turf-section-head">
                <div class="turf-section-head__title">
                    <h2>Featured Regional Arenas</h2>
                    <span class="turf-count-chip">Haraan Verified Grounds</span>
                </div>
            </div>
            <div class="turf-grid">
                @foreach($featuredVenues as $v)
                    <a class="turf-card" href="{{ \App\Support\SeoUrl::venue($v) }}">
                        <div class="turf-card__media">
                            <img src="{{ $v->image ?: asset('images/haraan-social-card.png') }}" alt="{{ $v->title }}" loading="lazy">
                            <span class="turf-card__scrim"></span>
                            <span class="turf-card__surface-tag">{{ $v->category ?: 'Sports Turf' }}</span>
                            @if($v->rating)
                                <span class="turf-card__rating">★ {{ number_format((float)$v->rating, 1) }}</span>
                            @endif
                        </div>
                        <div class="turf-card__body">
                            <h3 class="turf-card__title">{{ $v->title }}</h3>
                            <div class="turf-card__location">
                                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z"></path>
                                    <circle cx="12" cy="10" r="3"></circle>
                                </svg>
                                <span>{{ $v->tagline ?: ($v->location ?: $v->city) }}</span>
                            </div>
                            <div class="turf-card__foot">
                                <div class="turf-card__price">
                                    <strong>₹{{ number_format($v->price) }}</strong>
                                    <span>/ hour</span>
                                </div>
                                <span class="turf-card__cta">View Venue &rarr;</span>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    @endif

    {{-- ========================================================================= --}}
    {{-- 6. ACTIONBOARD STADIUM SCOREBOARD (LOCAL MATCHES TICKER)                  --}}
    {{-- ========================================================================= --}}
    @if($localMatches->isNotEmpty())
        <div class="turf-section-head">
            <div class="turf-section-head__title">
                <h2>Grassroots Matches in {{ $cityName }}</h2>
                <span class="turf-count-chip">ActionBoard Live Scoring</span>
            </div>
            <a href="/gamehub/actionboard" style="font-size: 13.5px; color: #2563EB; font-weight: 800; text-decoration: none;">Open ActionBoard &rarr;</a>
        </div>
        <div class="scoreboard-grid">
            @foreach($localMatches as $m)
                <a class="scoreboard-card" href="{{ route('site.gamehub.actionboard.match', $m->id) }}">
                    <div class="scoreboard-head">
                        <span class="scoreboard-comp">{{ $m->competition ?: 'Local Match' }}</span>
                        <span class="scoreboard-badge {{ $m->status === 'Live' ? 'is-live' : 'is-done' }}">
                            {{ $m->status === 'Live' ? '● LIVE' : $m->status }}
                        </span>
                    </div>
                    <div class="scoreboard-teams">
                        {{ $m->home_full ?: $m->home }} <span style="color: #60A5FA; font-weight: 400;">vs</span> {{ $m->away_full ?: $m->away }}
                    </div>
                    <div class="scoreboard-foot">
                        <span>Match Center &amp; Scores</span>
                        <span>View Scorecard &rarr;</span>
                    </div>
                </a>
            @endforeach
        </div>
    @endif

    {{-- ========================================================================= --}}
    {{-- 7. ARENA OWNER PARTNER BILLBOARD                                         --}}
    {{-- ========================================================================= --}}
    <div class="partner-billboard">
        <div class="partner-billboard__inner">
            <h3>Own a Sports Turf or Court in {{ $cityName }}?</h3>
            <p class="lead">
                Fill weekday morning and midnight slots, eliminate double bookings, and bring professional live LED scoreboard displays to your players with Haraan's venue platform.
            </p>

            <div class="partner-perks">
                <div class="partner-perk">
                    <strong>Direct Online Booking</strong>
                    <span>Instant slot confirmation with advance UPI deposit security.</span>
                </div>
                <div class="partner-perk">
                    <strong>ActionBoard Integration</strong>
                    <span>Broadcast live cricket scores on your turf's TV screen.</span>
                </div>
                <div class="partner-perk">
                    <strong>Automated WhatsApp Alerts</strong>
                    <span>Send booking confirmations &amp; reminders straight to team captains.</span>
                </div>
                <div class="partner-perk">
                    <strong>Zero Double-Bookings</strong>
                    <span>Unified calendar for online reservations and walk-in counter bookings.</span>
                </div>
            </div>

            <a href="/partner/login" class="btn-partner-cta">
                Partner Your {{ $cityName }} Ground with Haraan
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                    <polyline points="12 5 19 12 12 19"></polyline>
                </svg>
            </a>
        </div>
    </div>

    {{-- ========================================================================= --}}
    {{-- 8. KADAPA PLAYBOOK & TURF GUIDE (AUTHENTIC ATHLETIC ADVICE)              --}}
    {{-- ========================================================================= --}}
    @if(strcasecmp($cityName, 'Kadapa') === 0)
        <div class="turf-playbook">
            <h3>Local Sports &amp; Turf Guide for Kadapa</h3>
            <p class="lead">
                Looking for turfs in Kadapa? Whether you are planning a weekend tennis-ball cricket tournament, high-intensity 6v6 box cricket under the floodlights, or morning badminton practice, here is what to check before confirming your hourly slot.
            </p>

            <div class="playbook-cards">
                <div class="playbook-card">
                    <strong>Box Cricket Formats (6v6 to 8v8)</strong>
                    <ul>
                        <li>Dimensions: 90ft–110ft long with 25ft overhead nylon net cage</li>
                        <li>Ball: Heavy tennis ball (Vicky / Cosco) recommended</li>
                        <li>Footwear: Flat turf shoes or rubber-stud trainers only (no metal cleats)</li>
                        <li>Rules: Direct hit on roof net counted as single run or dot ball</li>
                    </ul>
                </div>

                <div class="playbook-card">
                    <strong>Indoor Badminton Courts</strong>
                    <ul>
                        <li>Surfaces: BWF-certified synthetic vinyl mat or teak wood flooring</li>
                        <li>Shoes: Strictly non-marking gum sole shoes required on court</li>
                        <li>Lighting: Anti-glare vertical LED high-bay luminaires (300+ Lux)</li>
                        <li>Booking: Reserve 60-min slots early for peak 6:00 PM – 9:00 PM</li>
                    </ul>
                </div>

                <div class="playbook-card">
                    <strong>Prime Booking Hours in Kadapa</strong>
                    <ul>
                        <li>Early Morning (6:00 AM – 8:30 AM): Fitness training and coaching</li>
                        <li>Prime Evening (6:00 PM – 10:00 PM): High demand for floodlit matches</li>
                        <li>Night Owl Slots (10:00 PM – 1:00 AM): Weekend knockout tournaments</li>
                        <li>Advise: Book 24–48 hours in advance for Friday &amp; Saturday nights</li>
                    </ul>
                </div>
            </div>
        </div>
    @endif

    {{-- ========================================================================= --}}
    {{-- 9. TACTILE FAQ ACCORDION                                                  --}}
    {{-- ========================================================================= --}}
    <div class="turf-faq">
        <h3>Frequently Asked Questions about Turfs in {{ $cityName }}</h3>

        @foreach($faqs as $f)
            <details class="faq-accordion-item" {{ $loop->first ? 'open' : '' }}>
                <summary class="faq-accordion-summary">
                    <span>{{ $f['question'] }}</span>
                    <svg class="faq-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </summary>
                <div class="faq-accordion-content">
                    {{ $f['answer'] }}
                </div>
            </details>
        @endforeach
    </div>

</div>

{{-- Interactive Scout Toast Notification --}}
<div id="turf-toast" class="turf-toast" role="status">
    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#34D399" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
        <polyline points="22 4 12 14.01 9 11.01"></polyline>
    </svg>
    <span id="turf-toast-msg">You are on the Kadapa Turf Priority list! We'll alert you via WhatsApp.</span>
</div>

<script>
    function openScoutModal() {
        const phone = prompt("Enter your WhatsApp number to get notified when Kadapa turf slots open:", "");
        if (phone && phone.trim().length >= 10) {
            const toast = document.getElementById('turf-toast');
            const msg = document.getElementById('turf-toast-msg');
            msg.innerText = "✓ Saved (" + phone.trim() + ")! We'll alert you on WhatsApp the moment new Kadapa slots open.";
            toast.style.display = 'flex';
            setTimeout(() => {
                toast.style.display = 'none';
            }, 6000);
        }
    }
</script>
@endsection
