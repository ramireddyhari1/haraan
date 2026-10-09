{{-- Stepped venue form: numbered steps, setup journey, week-hours drawing, bill preview, amenity tiles.
     Every selector is prefixed vf- or hangs off .vf-step, so nothing else on the panel changes. --}}
<style>
    :root {
        --vf-ink: #101828; --vf-ink-2: #475467; --vf-ink-3: #8a94a6;
        --vf-line: #e4e7ec; --vf-line-2: #eef0f3; --vf-sunk: #f6f7f9; --vf-surface: #fff;
        --vf-blue: #2563eb; --vf-blue-2: #1d4ed8; --vf-blue-soft: #e8efff; --vf-blue-mid: #b7cbfa;
        --vf-green: #16a34a; --vf-green-soft: #eaf7ee; --vf-amber: #d97706; --vf-amber-soft: #fdf3e2;
        --vf-lift: inset 0 1px 0 rgba(255,255,255,.9), 0 1px 1px rgba(16,24,40,.04), 0 2px 6px -2px rgba(16,24,40,.07);
    }
    .dark {
        --vf-ink: #eef2f8; --vf-ink-2: #a8b3c5; --vf-ink-3: #6b778b;
        --vf-line: #222b3b; --vf-line-2: #1a2231; --vf-sunk: #0e1420; --vf-surface: #121826;
        --vf-blue: #5b8cff; --vf-blue-2: #7aa2ff; --vf-blue-soft: #16213a; --vf-blue-mid: #2c4378;
        --vf-green: #3ccf7a; --vf-green-soft: #12261b; --vf-amber: #f0a43a; --vf-amber-soft: #2a1f0e;
        --vf-lift: inset 0 1px 0 rgba(255,255,255,.04), 0 1px 2px rgba(0,0,0,.4);
    }

    /* ── Steps ─────────────────────────────────────────────── */
    .vf-step { scroll-margin-top: 90px; border-radius: 16px !important; box-shadow: var(--vf-lift) !important; }
    .vf-step .fi-section-header-heading { display: flex; align-items: center; gap: 10px; font-size: 15px; letter-spacing: -0.01em; }
    .vf-num { display: inline-grid; place-items: center; width: 24px; height: 24px; border-radius: 8px; font-size: 12px; font-weight: 700;
        background: var(--vf-sunk); color: var(--vf-ink-2); border: 1px solid var(--vf-line); box-shadow: inset 0 -1px 0 var(--vf-line); flex: none; }
    .vf-step.is-done .vf-num { background: var(--vf-blue); color: #fff; border-color: var(--vf-blue-2); box-shadow: none; }
    .vf-step.is-missing .vf-num { background: var(--vf-amber-soft); color: var(--vf-amber); border-color: transparent; }
    .vf-step .fi-section-header-description { line-height: 1.55; }
    .vf-step:target { outline: 2px solid var(--vf-blue); outline-offset: 3px; }

    /* ── Setup journey ─────────────────────────────────────── */
    .vf-journey { background: var(--vf-surface); border: 1px solid var(--vf-line); border-radius: 16px; box-shadow: var(--vf-lift); padding: 16px 18px 14px; }
    .vf-j-top { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 14px; }
    .vf-j-title { font-size: 14.5px; font-weight: 650; color: var(--vf-ink); }
    .vf-j-sub { font-size: 12.5px; color: var(--vf-ink-3); margin-top: 2px; }
    .vf-pill { display: inline-flex; align-items: center; gap: 7px; font-size: 12.5px; font-weight: 650; padding: 5px 11px; border-radius: 999px; }
    .vf-pill i { width: 8px; height: 8px; border-radius: 50%; background: currentColor; }
    .vf-pill.live { background: var(--vf-green-soft); color: var(--vf-green); }
    .vf-pill.ready { background: var(--vf-blue-soft); color: var(--vf-blue); }
    .vf-pill.draft { background: var(--vf-amber-soft); color: var(--vf-amber); }
    .vf-track { display: grid; grid-template-columns: repeat(8, minmax(92px, 1fr)); position: relative; overflow-x: auto; padding-bottom: 2px; }
    .vf-node { position: relative; display: flex; flex-direction: column; align-items: center; text-align: center; gap: 6px; padding: 0 4px;
        text-decoration: none; color: inherit; cursor: pointer; background: none; border: 0; font: inherit; }
    .vf-node::before { content: ""; position: absolute; top: 20px; left: -50%; width: 100%; height: 2px; background: var(--vf-line); z-index: 0; }
    .vf-node:first-child::before { display: none; }
    .vf-node.done::before { background: var(--vf-blue); }
    .vf-dot { position: relative; z-index: 1; width: 40px; height: 40px; border-radius: 12px; display: grid; place-items: center;
        background: var(--vf-surface); color: var(--vf-ink-2); border: 1.5px solid var(--vf-line); transition: transform .12s, box-shadow .15s; }
    .vf-node:hover .vf-dot { transform: translateY(-2px); box-shadow: 0 6px 14px -6px rgba(16,24,40,.35); }
    .vf-node:active .vf-dot { transform: translateY(0); }
    .vf-node.done .vf-dot { background: var(--vf-blue); color: #fff; border-color: var(--vf-blue-2); }
    .vf-node.todo .vf-dot { border-color: var(--vf-amber); color: var(--vf-amber); background: var(--vf-amber-soft); }
    .vf-node.opt .vf-dot { border-style: dashed; }
    .vf-tick { position: absolute; right: -5px; bottom: -5px; width: 17px; height: 17px; border-radius: 50%; background: var(--vf-green); color: #fff;
        display: grid; place-items: center; border: 2px solid var(--vf-surface); }
    .vf-node .l { font-size: 12.5px; font-weight: 650; color: var(--vf-ink); }
    .vf-node .h { font-size: 11px; color: var(--vf-ink-3); line-height: 1.3; max-width: 110px; }
    .vf-node.todo .h { color: var(--vf-amber); }

    /* ── Week of hours ─────────────────────────────────────── */
    .vf-week { border: 1px solid var(--vf-line); border-radius: 12px; padding: 12px 14px 8px; background: var(--vf-sunk); }
    .vf-week svg { width: 100%; height: auto; display: block; }
    .vf-week text { font-family: inherit; }
    .vf-week-cap { display: flex; justify-content: space-between; gap: 8px; font-size: 12px; color: var(--vf-ink-3); margin-bottom: 6px; }
    .vf-week-cap b { color: var(--vf-ink); font-weight: 650; }

    /* ── Bill preview ──────────────────────────────────────── */
    .vf-bill { max-width: 320px; margin-left: auto; filter: drop-shadow(0 6px 10px rgba(16,24,40,.10)); }
    .vf-bill-paper { background: var(--vf-surface); border: 1px solid var(--vf-line); border-bottom: 0; border-radius: 12px 12px 0 0; padding: 14px 16px 10px;
        font-variant-numeric: tabular-nums; }
    .vf-bill-h { display: flex; justify-content: space-between; font-size: 11px; font-weight: 700; letter-spacing: .1em; color: var(--vf-ink-3); margin-bottom: 8px; }
    .vf-bill-row { display: flex; justify-content: space-between; gap: 12px; font-size: 13px; color: var(--vf-ink-2); padding: 3px 0; }
    .vf-bill-row b { color: var(--vf-ink); font-weight: 600; }
    .vf-bill-tot { border-top: 1.5px dashed var(--vf-line); margin-top: 6px; padding-top: 8px; font-size: 15px; }
    .vf-bill-tot b { font-size: 17px; font-weight: 750; }
    .vf-bill-edge { display: block; width: 100%; height: 10px; }
    .vf-bill-note { font-size: 11.5px; color: var(--vf-ink-3); margin-top: 6px; text-align: right; }

    /* ── Amenity & rule tiles: a checkbox you press, not a checkbox you tick ── */
    .vf-tiles .fi-fo-checkbox-list-option { border: 1px solid var(--vf-line); border-bottom-width: 2px; border-radius: 11px; padding: 9px 11px;
        background: var(--vf-surface); cursor: pointer; transition: border-color .15s, background .15s, transform .08s; align-items: center; }
    .vf-tiles .fi-fo-checkbox-list-option:hover { border-color: var(--vf-blue-mid); }
    .vf-tiles .fi-fo-checkbox-list-option:active { transform: translateY(1px); border-bottom-width: 1px; }
    .vf-tiles .fi-fo-checkbox-list-option:has(input:checked) { background: var(--vf-blue-soft); border-color: var(--vf-blue); }
    .vf-tiles .fi-fo-checkbox-list-option:has(input:checked) .fi-fo-checkbox-list-option-label { color: var(--vf-blue-2); font-weight: 650; }

    .vf-am { display: inline-flex; align-items: center; gap: 8px; }
    .vf-am svg { color: var(--vf-ink-3); flex: none; }
    .vf-tiles .fi-fo-checkbox-list-option:has(input:checked) .vf-am svg { color: var(--vf-blue); }

    /* ── Sport picker: drawn courts you press like keys ───────── */
    .vf-sp { --cc-ink: var(--vf-ink-2); --cc-blue-soft: var(--vf-sunk); --cc-surface: var(--vf-surface); }
    .vf-sp-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px; }
    .vf-sp-tile { position: relative; display: flex; flex-direction: column; gap: 8px; padding: 10px 10px 9px; border-radius: 14px; cursor: pointer;
        background: var(--vf-surface); border: 1px solid var(--vf-line); border-bottom-width: 3px; user-select: none; outline: none;
        transition: border-color .15s, background .15s, transform .08s ease-out, box-shadow .15s; }
    .vf-sp-tile:hover { border-color: var(--vf-blue-mid); }
    .vf-sp-tile:focus-visible { box-shadow: 0 0 0 3px var(--vf-blue-mid); }
    .vf-sp-tile:active { transform: translateY(2px); border-bottom-width: 1px; margin-bottom: 2px; }
    .vf-sp-art { position: relative; border-radius: 10px; padding: 6px; background: var(--vf-sunk); transition: background .15s; }
    .vf-sp-art .cb-pitch { width: 100%; height: auto; display: block; opacity: .55; filter: grayscale(.6);
        transition: opacity .18s, filter .18s, transform .18s; }
    .vf-sp-tile:hover .vf-sp-art .cb-pitch { opacity: .8; }
    .vf-sp-tick { position: absolute; top: 6px; right: 6px; width: 20px; height: 20px; border-radius: 50%; display: grid; place-items: center;
        background: var(--vf-blue); color: #fff; transform: scale(0); transition: transform .18s cubic-bezier(.3, 1.6, .5, 1); }
    .vf-sp-meta { display: flex; flex-direction: column; gap: 1px; padding: 0 2px; }
    .vf-sp-name { font-size: 13.5px; font-weight: 650; color: var(--vf-ink); letter-spacing: -0.01em; }
    .vf-sp-note { font-size: 11.5px; color: var(--vf-ink-3); }
    .vf-sp-foot { min-height: 22px; display: flex; align-items: center; padding: 0 2px; }
    .vf-sp-add { font-size: 12px; font-weight: 600; color: var(--vf-ink-3); }
    .vf-sp-badge { font-size: 11px; font-weight: 700; color: #fff; background: var(--vf-blue); padding: 3px 9px; border-radius: 999px; }
    .vf-sp-make { font-size: 11.5px; font-weight: 650; color: var(--vf-blue-2); background: var(--vf-surface); border: 1px solid var(--vf-blue-mid);
        padding: 2px 9px; border-radius: 999px; cursor: pointer; }
    .vf-sp-make:hover { background: var(--vf-blue); color: #fff; border-color: var(--vf-blue); }
    .vf-sp-tile.is-on { background: var(--vf-blue-soft); border-color: var(--vf-blue); }
    .vf-sp-tile.is-on .vf-sp-art { --cc-blue-soft: var(--vf-surface); --cc-ink: var(--vf-blue-2); background: color-mix(in srgb, var(--vf-blue) 10%, var(--vf-surface)); }
    .vf-sp-tile.is-on .vf-sp-art .cb-pitch { opacity: 1; transform: translateY(-1px); filter: drop-shadow(0 3px 3px rgba(16, 24, 40, .14)); }
    .vf-sp-tile.is-on .vf-sp-tick { transform: scale(1); }
    .vf-sp-tile.is-on .vf-sp-name { color: var(--vf-blue-2); }
    .vf-sp-tile.is-main { box-shadow: 0 6px 16px -8px color-mix(in srgb, var(--vf-blue) 55%, transparent); }
    .vf-sp-tile.is-nudge { animation: vf-sp-nudge .4s; }
    @keyframes vf-sp-nudge { 20%, 60% { transform: translateX(-4px); } 40%, 80% { transform: translateX(4px); } }

    @media (max-width: 760px) {
        .vf-sp-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .vf-track { grid-template-columns: repeat(8, 96px); }
        .vf-bill { margin: 0; }
    }
</style>
