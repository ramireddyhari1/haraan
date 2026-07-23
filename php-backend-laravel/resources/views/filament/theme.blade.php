{{-- Haraan Control — shared design system.

     Injected into every /control panel page via a HEAD_END render hook
     (App\Providers\Filament\AdminPanelProvider), mirroring the realtime-head
     pattern. This is the SINGLE SOURCE OF TRUTH for the panel's custom design
     tokens and reusable component classes, so custom pages/widgets stop
     redefining their own palettes inline.

     Namespace: canonical tokens are `--hrn-*`; reusable classes are `.hrn-*`.
     The `--cc-*` aliases keep the Command Center markup working unchanged. --}}
<style>
    :root {
        /* Surfaces & ink hierarchy */
        --hrn-surface:#fff; --hrn-border:#e8ecf3; --hrn-ink:#0b1220;
        --hrn-ink-2:#5a6579; --hrn-ink-3:#8a94a6; --hrn-track:#eef1f6;
        --hrn-radius:16px; --hrn-radius-lg:20px;
        --hrn-shadow:0 1px 2px rgba(11,18,32,.04);
        --hrn-shadow-hover:0 14px 26px -16px rgba(11,18,32,.26);

        /* Status palette — text / strong (dot,bar) / tinted background */
        --hrn-ok:#059669;   --hrn-ok-strong:#10b981; --hrn-ok-bg:#ecfdf5;
        --hrn-warn:#d97706; --hrn-warn-strong:#f59e0b; --hrn-warn-bg:#fffbeb;
        --hrn-down:#dc2626; --hrn-down-strong:#ef4444; --hrn-down-bg:#fef2f2;
        --hrn-idle:#9aa4b2; --hrn-idle-strong:#cbd2dd; --hrn-idle-bg:#f1f3f7;

        /* Back-compat aliases used by existing Command Center markup */
        --cc-ok:var(--hrn-ok);     --cc-ok-d:var(--hrn-ok-strong);     --cc-ok-bg:var(--hrn-ok-bg);
        --cc-warn:var(--hrn-warn); --cc-warn-d:var(--hrn-warn-strong); --cc-warn-bg:var(--hrn-warn-bg);
        --cc-down:var(--hrn-down); --cc-down-d:var(--hrn-down-strong); --cc-down-bg:var(--hrn-down-bg);
        --cc-idle:var(--hrn-idle); --cc-idle-d:var(--hrn-idle-strong); --cc-idle-bg:var(--hrn-idle-bg);
    }
    .dark {
        --hrn-surface:#111726; --hrn-border:rgba(255,255,255,.08); --hrn-ink:#f3f5f9;
        --hrn-ink-2:#aeb7c6; --hrn-ink-3:#7b8698; --hrn-track:rgba(255,255,255,.09);
        --hrn-shadow:0 1px 2px rgba(0,0,0,.3);
        --hrn-shadow-hover:0 16px 30px -18px rgba(0,0,0,.6);
        --hrn-ok-bg:rgba(16,185,129,.13); --hrn-warn-bg:rgba(245,158,11,.13);
        --hrn-down-bg:rgba(239,68,68,.13); --hrn-idle-bg:rgba(255,255,255,.05);
    }

    /* ── Section heading ─────────────────────────────────────────── */
    .hrn-sec-h{display:flex;align-items:center;gap:8px;margin:0 0 12px 2px;}
    .hrn-sec-h h2{margin:0;font-size:11px;font-weight:700;letter-spacing:.09em;
        text-transform:uppercase;color:var(--hrn-ink-2);}

    /* ── Responsive grid ─────────────────────────────────────────── */
    .hrn-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:15px;}
    @media(max-width:1100px){.hrn-grid{grid-template-columns:repeat(2,1fr);}}
    @media(max-width:600px){.hrn-grid{grid-template-columns:1fr;}}

    /* ── Card (status-accented) ──────────────────────────────────── */
    .hrn-card{position:relative;overflow:hidden;background:var(--hrn-surface);
        border:1px solid var(--hrn-border);border-radius:var(--hrn-radius);padding:17px;
        box-shadow:var(--hrn-shadow);transition:transform .18s,box-shadow .18s;}
    .hrn-card:hover{transform:translateY(-2px);box-shadow:var(--hrn-shadow-hover);}
    .hrn-card--accent::before{content:"";position:absolute;left:0;top:0;bottom:0;width:4px;
        background:var(--_bar,var(--hrn-idle-strong));}
    .hrn-card-top{display:flex;align-items:center;justify-content:space-between;gap:10px;}
    .hrn-card-tl{display:flex;align-items:center;gap:11px;}
    .hrn-tile{width:38px;height:38px;border-radius:11px;display:flex;align-items:center;
        justify-content:center;background:var(--_tile,var(--hrn-idle-bg));
        color:var(--_text,var(--hrn-ink-2));flex:none;}
    .hrn-tile svg{width:19px;height:19px;}
    .hrn-card-title{font-size:13.5px;font-weight:600;color:var(--hrn-ink-2);}
    .hrn-card-val{margin:14px 0 0;font-size:22px;font-weight:800;letter-spacing:-.02em;
        color:var(--hrn-ink);line-height:1.1;}
    .hrn-card-sub{margin:8px 0 0;font-size:12px;line-height:1.5;color:var(--hrn-ink-3);}

    /* ── Meter ───────────────────────────────────────────────────── */
    .hrn-meter{margin-top:10px;height:6px;border-radius:999px;background:var(--hrn-track);overflow:hidden;}
    .hrn-meter i{display:block;height:100%;border-radius:999px;
        background:var(--_bar,var(--hrn-ok-strong));transition:width .5s ease;}

    /* ── Radar / actionable row ──────────────────────────────────── */
    .hrn-radar{display:flex;flex-direction:column;gap:10px;}
    .hrn-row{display:flex;align-items:center;gap:14px;background:var(--hrn-surface);
        border:1px solid var(--hrn-border);border-radius:14px;padding:14px 16px;
        text-decoration:none;transition:transform .15s,box-shadow .15s,border-color .15s;}
    .hrn-row:hover{transform:translateX(2px);box-shadow:0 10px 22px -16px rgba(11,18,32,.3);
        border-color:var(--_dot,var(--hrn-idle-strong));}
    .hrn-row .hrn-tile{width:40px;height:40px;}
    .hrn-row-main{flex:1;min-width:0;}
    .hrn-row-t{font-size:14px;font-weight:700;color:var(--hrn-ink);}
    .hrn-row-s{font-size:12px;color:var(--hrn-ink-3);margin-top:2px;}

    /* ── Hero band ───────────────────────────────────────────────── */
    .hrn-hero{position:relative;overflow:hidden;border-radius:var(--hrn-radius-lg);
        padding:26px 28px;color:#fff;background:linear-gradient(130deg,#2563eb 0%,#12b76a 100%);
        box-shadow:0 16px 40px -18px rgba(37,99,235,.55);}
    .hrn-hero-wash{position:absolute;right:-40px;top:-60px;width:230px;height:230px;
        border-radius:50%;background:rgba(255,255,255,.14);filter:blur(40px);}
</style>
