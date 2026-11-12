{{--
    Premium visual theme for the Event Create/Edit wizard. Injected into <head>
    ONLY on those pages (see AdminPanelProvider/PartnerPanelProvider render hooks
    scoped to CreateEvent/EditEvent), so nothing else in the panel is affected.
    Targets Filament v4's real DOM classes (fi-sc-wizard*, fi-input-wrp, fi-section*).
--}}
<style>
    :root{
        --hb-ink:#0f172a; --hb-ink2:#1e293b; --hb-mut:#64748b; --hb-line:#e6e8ee;
        --hb-blue:#2563eb; --hb-blue-d:#1d4ed8; --hb-soft:#eef4ff; --hb-soft2:#f5f8ff;
    }

    /* ── Page rhythm: rein in the empty canvas ────────────────────────────── */
    .fi-page .fi-main{ max-width:960px; }

    /* ═══ Unified hero: header panel + stepper as ONE elevated surface ═══ */
    .fi-resource-create-record-page .fi-page-header-main-ctn,
    .fi-resource-edit-record-page .fi-page-header-main-ctn{ padding-bottom:0 !important; margin-bottom:0 !important; }
    .fi-resource-create-record-page .fi-page-content,
    .fi-resource-edit-record-page .fi-page-content{ margin-top:0 !important; padding-top:0 !important; }

    .fi-resource-create-record-page .fi-header,
    .fi-resource-edit-record-page .fi-header{
        position:relative; align-items:flex-start !important; overflow:hidden;
        background:#fff;
        border:1px solid var(--hb-line); border-bottom:none;
        border-radius:24px 24px 0 0;
        padding:24px 28px 30px !important;
    }
    .fi-resource-create-record-page .fi-header > div,
    .fi-resource-edit-record-page .fi-header > div{ position:relative; z-index:1; }

    .fi-resource-create-record-page .fi-breadcrumbs-item-label,
    .fi-resource-edit-record-page .fi-breadcrumbs-item-label{
        font-size:11px !important; font-weight:650 !important; letter-spacing:.07em;
        text-transform:uppercase; color:#9aa4b2 !important;
    }
    .fi-resource-create-record-page .fi-header-heading,
    .fi-resource-edit-record-page .fi-header-heading{
        display:flex !important; align-items:center; gap:16px;
        font-size:1.6rem !important; font-weight:700 !important; letter-spacing:-.035em; color:var(--hb-ink);
        margin-top:10px;
    }
    .fi-resource-create-record-page .fi-header-subheading,
    .fi-resource-edit-record-page .fi-header-subheading{
        margin-top:6px !important; color:var(--hb-mut) !important;
        font-size:.95rem !important; font-weight:450; line-height:1.5; max-width:60ch;
    }
    /* actions top-right, premium primary */
    .fi-resource-create-record-page .fi-header-actions-ctn,
    .fi-resource-edit-record-page .fi-header-actions-ctn{ position:relative; z-index:1; padding-top:4px; }
    .fi-resource-create-record-page .fi-header .fi-btn.fi-color-primary,
    .fi-resource-edit-record-page .fi-header .fi-btn.fi-color-primary{
        background:#2563eb !important; border-color:transparent !important; font-weight:600 !important;
    }
    /* the wizard card fuses flush onto the hero */
    .fi-resource-create-record-page .fi-sc-wizard,
    .fi-resource-edit-record-page .fi-sc-wizard{
        border-radius:0 0 24px 24px !important; border-top:none !important; margin-top:0 !important;
    }

    /* ── The wizard as one elevated card ──────────────────────────────────── */
    .fi-sc-wizard{
        background:#fff; border:1px solid var(--hb-line); border-radius:22px;
        box-shadow:0 1px 2px rgba(15,23,42,.04);
        overflow:hidden;
    }

    /* ── Progress stepper — bespoke connected node row (no default chevrons/scrollbar) ── */
    .fi-sc-wizard-header{
        display:flex !important; align-items:flex-start !important; gap:0 !important;
        background:#fafbfd;
        border-bottom:1px solid var(--hb-line);
        padding:26px 26px 20px !important; overflow:visible !important;
        list-style:none; margin:0;
    }
    .fi-sc-wizard-header-step{
        flex:1 1 0 !important; min-width:0 !important; position:relative;
        display:flex !important; justify-content:center;
    }
    /* connecting track between this node and the next; fills blue once completed */
    .fi-sc-wizard-header-step:not(:last-child)::after{
        content:''; position:absolute; top:23px; z-index:0;
        left:calc(50% + 26px); width:calc(100% - 52px);
        height:2px; border-radius:2px; background:var(--hb-line);
    }
    .fi-sc-wizard-header-step.fi-completed::after{ background:#2563eb; }

    .fi-sc-wizard-header-step-btn{
        flex-direction:column !important; align-items:center !important; gap:10px !important;
        text-align:center; position:relative; z-index:1; width:100%; padding:0 4px !important;
    }
    .fi-sc-wizard-header-step-icon-ctn{
        width:46px !important; height:46px !important; border-radius:50% !important; flex:none;
        background:#fff; border:2px solid var(--hb-line); color:#94a3b8;
        box-shadow:0 1px 2px rgba(15,23,42,.06); transition:all .22s ease;
    }
    .fi-sc-wizard-header-step.fi-active .fi-sc-wizard-header-step-icon-ctn{
        background:#2563eb !important;
        border-color:var(--hb-blue) !important; color:#fff !important;
        box-shadow:0 0 0 4px rgba(37,99,235,.12);
    }
    .fi-sc-wizard-header-step.fi-completed .fi-sc-wizard-header-step-icon-ctn{
        background:var(--hb-blue) !important; border-color:var(--hb-blue) !important; color:#fff !important;
    }
    /* Force the icon SVG to follow the node colour — Filament ships the icon with its
       own grey text class, which read as grey-on-blue (invisible) on the active node. */
    .fi-sc-wizard-header-step-icon-ctn svg{ width:22px !important; height:22px !important; color:inherit !important; }
    .fi-sc-wizard-header-step:not(.fi-active):not(.fi-completed) .fi-sc-wizard-header-step-icon-ctn svg{ color:#94a3b8 !important; stroke:#94a3b8 !important; }
    .fi-sc-wizard-header-step.fi-active .fi-sc-wizard-header-step-icon-ctn svg,
    .fi-sc-wizard-header-step.fi-completed .fi-sc-wizard-header-step-icon-ctn svg{ color:#fff !important; stroke:#fff !important; }
    .fi-sc-wizard-header-step-number{ font-weight:700 !important; font-size:.9rem !important; color:inherit !important; }
    .fi-sc-wizard-header-step-text{ display:flex !important; flex-direction:column; align-items:center !important; gap:2px; max-width:100%; }
    .fi-sc-wizard-header-step-label{
        font-weight:700 !important; font-size:.86rem !important; color:#0f172a;
        white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:100%;
    }
    .fi-sc-wizard-header-step.fi-active .fi-sc-wizard-header-step-label{ color:var(--hb-blue-d); }
    .fi-sc-wizard-header-step:not(.fi-active):not(.fi-completed) .fi-sc-wizard-header-step-label{ color:#94a3b8; }
    .fi-sc-wizard-header-step-description{
        font-size:.75rem !important; color:var(--hb-mut) !important; line-height:1.35;
        white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:100%;
    }
    /* retire the default zig-zag chevron separators — the track above replaces them */
    .fi-sc-wizard-header-step-separator{ display:none !important; }
    @media (max-width:700px){
        .fi-sc-wizard-header-step-description{ display:none !important; }
        .fi-sc-wizard-header-step-label{ font-size:.72rem !important; }
    }

    /* ── Step body: clear vertical separation without touching Filament's own
       layout engine (forcing display/grid-cols shifted the label into a corner). */
    .fi-sc-wizard-step{ padding:32px 30px !important; }
    .fi-sc-wizard-step > .fi-sc{ row-gap:24px !important; }

    /* ── Labels & helper text: clear type scale (real Filament v4 classes) ── */
    .fi-fo-field-label, .fi-fo-field-label-content{
        font-weight:650 !important; font-size:.86rem !important; color:var(--hb-ink2) !important;
        letter-spacing:-.005em; line-height:1.3;
    }
    .fi-fo-field-label{ margin-bottom:8px !important; }
    .fi-fo-field-label-required-mark{ color:#ef4444 !important; margin-left:1px; }
    /* helper text renders as a below-content Text component */
    .fi-fo-field-content-col .fi-sc-text{ color:var(--hb-mut) !important; font-size:.8rem !important; line-height:1.45; margin-top:6px; }

    /* ── Inputs: consistent height, refined border, premium focus ─────────── */
    .fi-input-wrp, .fi-fo-select-wrp, .fi-fo-textarea-wrp{
        border-radius:12px !important; border:1.5px solid var(--hb-line) !important;
        background:#fff; box-shadow:0 1px 2px rgba(15,23,42,.04) !important;
        transition:border-color .15s, box-shadow .15s;
    }
    .fi-input-wrp:hover, .fi-fo-select-wrp:hover, .fi-fo-textarea-wrp:hover{ border-color:#cfd6e4 !important; }
    .fi-input-wrp:focus-within, .fi-fo-select-wrp:focus-within, .fi-fo-textarea-wrp:focus-within{
        border-color:var(--hb-blue) !important;
        box-shadow:0 0 0 4px rgba(37,99,235,.13) !important;
    }
    .fi-input-wrp .fi-input{ min-height:44px; padding-top:10px !important; padding-bottom:10px !important; font-size:.92rem; }
    .fi-select-input-btn{ min-height:44px; font-size:.92rem; }

    /* ── Sections as elegant cards ────────────────────────────────────────── */
    .fi-section{
        border-radius:16px !important; border:1px solid var(--hb-line) !important;
        box-shadow:0 1px 2px rgba(15,23,42,.04), 0 16px 34px -28px rgba(15,23,42,.28) !important;
    }
    .fi-section-header{ padding:18px 20px !important; }
    .fi-section-header-heading{ font-weight:750 !important; font-size:1rem !important; letter-spacing:-.01em; color:var(--hb-ink); }
    .fi-section-header-description{ color:var(--hb-mut) !important; font-size:.83rem !important; }

    /* ── Repeater cards (Tickets, Lineup, Coupons) ─────────────────────────── */
    .fi-fo-repeater-item{
        border-radius:16px !important; border:1.5px solid var(--hb-line) !important;
        box-shadow:0 2px 8px rgba(15,23,42,.04) !important;
        transition:border-color .18s, box-shadow .18s;
        margin-bottom:14px !important;
        overflow:hidden;
    }
    .fi-fo-repeater-item:hover{
        border-color:color-mix(in srgb,var(--hb-blue) 40%,var(--hb-line)) !important;
        box-shadow:0 6px 16px rgba(37,99,235,.08) !important;
    }

    /* ── Hint actions (e.g. "Draft with AI"): a quiet text button, not a glowing pill ── */
    .fi-fo-field-wrp-hint-action,
    button[wire\:click*="draftDescription"]{
        background:transparent !important;
        border:0 !important;
        color:#2563eb !important;
        border-radius:6px !important;
        padding:2px 6px !important;
        font-weight:600 !important;
        font-size:.78rem !important;
        display:inline-flex !important;
        align-items:center !important;
        gap:5px !important;
        transition:all .18s ease !important;
    }
    .fi-fo-field-wrp-hint-action:hover,
    button[wire\:click*="draftDescription"]:hover{
        background:#eef4ff !important;
    }
    .fi-fo-field-wrp-hint-action svg,
    button[wire\:click*="draftDescription"] svg{
        width:14px !important; height:14px !important;
        color:inherit !important;
    }

    /* ── Mobile Stepper Scroll Snap (No squishing on phones) ────────────────── */
    @media (max-width:768px){
        .fi-sc-wizard-header{
            overflow-x:auto !important;
            scroll-snap-type:x mandatory;
            -webkit-overflow-scrolling:touch;
            justify-content:flex-start !important;
            padding:16px 14px !important;
            scrollbar-width:none;
        }
        .fi-sc-wizard-header::-webkit-scrollbar{ display:none; }
        .fi-sc-wizard-header-step{
            flex:0 0 120px !important;
            min-width:120px !important;
            scroll-snap-align:center;
        }
        .fi-sc-wizard-header-step:not(:last-child)::after{
            display:none !important;
        }
        .fi-resource-create-record-page .fi-header-heading,
        .fi-resource-edit-record-page .fi-header-heading{
            font-size:1.5rem !important;
        }
        .fi-resource-create-record-page .fi-header-subheading,
        .fi-resource-edit-record-page .fi-header-subheading{
            margin-left:0 !important;
        }
        .fi-sc-wizard-step{
            padding:20px 16px !important;
        }
    }

    /* ── Footer + prominent primary CTA ───────────────────────────────────── */
    .fi-sc-wizard-footer{
        padding:18px 26px !important; border-top:1px solid var(--hb-line);
        background:#fff;
    }
    .fi-sc-wizard-footer .fi-btn{ border-radius:12px !important; min-height:44px; font-weight:700 !important; padding-inline:22px !important; }
    .fi-sc-wizard-footer .fi-btn.fi-color-primary{
        background:#2563eb !important;
    }
    .fi-sc-wizard-footer .fi-btn.fi-color-primary:hover{ filter:brightness(1.05); }

    /* ═══ Dark Mode Theme Overrides ═════════════════════════════════════════ */
    .dark{
        --hb-ink:#f8fafc; --hb-ink2:#e2e8f0; --hb-mut:#94a3b8; --hb-line:#1e293b;
        --hb-blue:#3b82f6; --hb-blue-d:#60a5fa; --hb-soft:#172554; --hb-soft2:#0f172a;
    }
    .dark .fi-resource-create-record-page .fi-header,
    .dark .fi-resource-edit-record-page .fi-header{
        background:#0f172a !important;
        border-color:var(--hb-line) !important;
    }
    .dark .fi-sc-wizard{
        background:#0f172a !important;
        border-color:var(--hb-line) !important;
    }
    .dark .fi-sc-wizard-header{
        background:#0f172a !important;
        border-color:var(--hb-line) !important;
    }
    .dark .fi-sc-wizard-header-step-icon-ctn{
        background:#1e293b !important;
        border-color:#334155 !important;
        color:#94a3b8 !important;
    }
    .dark .fi-sc-wizard-header-step-label{
        color:#cbd5e1 !important;
    }
    .dark .fi-sc-wizard-header-step.fi-active .fi-sc-wizard-header-step-label{
        color:#60a5fa !important;
    }
    .dark .fi-input-wrp,
    .dark .fi-fo-select-wrp,
    .dark .fi-fo-textarea-wrp{
        background:#0b1120 !important;
        border-color:#1e293b !important;
        color:#f8fafc !important;
    }
    .dark .fi-section{
        background:#0f172a !important;
        border-color:var(--hb-line) !important;
    }
    .dark .fi-section-header-heading{
        color:#f8fafc !important;
    }
    .dark .fi-fo-repeater-item{
        background:#0b1120 !important;
        border-color:#1e293b !important;
    }
    .dark .fi-sc-wizard-footer{
        background:#0f172a !important;
        border-color:var(--hb-line) !important;
    }
    .dark .fi-fo-field-wrp-hint-action,
    .dark button[wire\:click*="draftDescription"]{
        background:transparent !important;
        color:#60a5fa !important;
    }

    @media (prefers-reduced-motion: reduce) {
        .fi-fo-field-wrp-hint-action,
        button[wire\:click*="draftDescription"] {
            animation: none !important;
            transition: none !important;
        }
        .fi-sc-wizard-header-step-icon-ctn,
        .fi-sc-wizard-step {
            transition: none !important;
        }
    }
</style>
