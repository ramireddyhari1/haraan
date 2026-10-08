{{-- Shared look for the console's overview pages (Command Center, Events overview, history, check-in).
     Scoped to .cc so it never touches Filament's own components. --}}
    <style>
        .cc {
            --cc-bg: #f4f5f7;
            --cc-surface: #ffffff;
            --cc-sunk: #f6f7f9;
            --cc-line: #e4e7ec;
            --cc-line-2: #eef0f3;
            --cc-ink: #101828;
            --cc-ink-2: #475467;
            --cc-ink-3: #8a94a6;
            --cc-blue: #2563eb;
            --cc-blue-2: #1d4ed8;
            --cc-blue-soft: #e8efff;
            --cc-blue-mid: #b7cbfa;
            --cc-green: #16a34a;
            --cc-green-soft: #eaf7ee;
            --cc-amber: #d97706;
            --cc-amber-soft: #fdf3e2;
            --cc-red: #dc2626;
            --cc-red-soft: #fdecec;
            --cc-gold: #c99a2e;
            --cc-lift: inset 0 1px 0 rgba(255,255,255,.9), 0 1px 1px rgba(16,24,40,.04), 0 2px 6px -2px rgba(16,24,40,.07);
            --cc-lift-hi: inset 0 1px 0 rgba(255,255,255,.9), 0 2px 4px rgba(16,24,40,.05), 0 10px 22px -10px rgba(16,24,40,.16);
            display: flex; flex-direction: column; gap: 16px;
            color: var(--cc-ink);
            font-feature-settings: "tnum" 1, "cv11" 1;
        }
        .dark .cc {
            --cc-bg: #0b0f17;
            --cc-surface: #121826;
            --cc-sunk: #0e1420;
            --cc-line: #222b3b;
            --cc-line-2: #1a2231;
            --cc-ink: #eef2f8;
            --cc-ink-2: #a8b3c5;
            --cc-ink-3: #6b778b;
            --cc-blue: #5b8cff;
            --cc-blue-2: #7aa2ff;
            --cc-blue-soft: #16213a;
            --cc-blue-mid: #2c4378;
            --cc-green: #3ccf7a;
            --cc-green-soft: #12261b;
            --cc-amber: #f0a43a;
            --cc-amber-soft: #2a1f0e;
            --cc-red: #f87171;
            --cc-red-soft: #2b1414;
            --cc-lift: inset 0 1px 0 rgba(255,255,255,.04), 0 1px 2px rgba(0,0,0,.4);
            --cc-lift-hi: inset 0 1px 0 rgba(255,255,255,.05), 0 12px 24px -12px rgba(0,0,0,.7);
        }

        .cc a { color: inherit; text-decoration: none; }
        .cc :focus-visible { outline: 2px solid var(--cc-blue); outline-offset: 2px; border-radius: 8px; }
        .cc-num { font-variant-numeric: tabular-nums; letter-spacing: -0.02em; }

        /* Layout */
        .cc-grid { display: grid; grid-template-columns: repeat(12, minmax(0, 1fr)); gap: 16px; }
        .cc-s8 { grid-column: span 8; } .cc-s7 { grid-column: span 7; } .cc-s5 { grid-column: span 5; }
        .cc-s4 { grid-column: span 4; }
        .cc-stack { display: flex; flex-direction: column; gap: 16px; min-width: 0; }
        @media (max-width: 1180px) { .cc-s8, .cc-s4.cc-side, .cc-s7, .cc-s5 { grid-column: span 12; } }
        @media (max-width: 1120px) { .cc-s4 { grid-column: span 12; } }

        /* Card: paper on a desk — a crisp edge, a lit top lip, a soft contact shadow */
        .cc-card {
            background: var(--cc-surface);
            border: 1px solid var(--cc-line);
            border-radius: 16px;
            box-shadow: var(--cc-lift);
            padding: 18px 20px;
            min-width: 0;
        }
        .cc-card-h { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 14px; }
        .cc-card-t { font-size: 14.5px; font-weight: 650; letter-spacing: -0.01em; display: flex; align-items: center; gap: 10px; margin: 0; }
        .cc-card-sub { font-size: 12.5px; color: var(--cc-ink-3); margin-top: 2px; }
        .cc-more { font-size: 12.5px; font-weight: 600; color: var(--cc-blue); display: inline-flex; align-items: center; gap: 4px; padding: 4px 6px; margin: -4px -6px; border-radius: 8px; white-space: nowrap; }
        .cc-more:hover { background: var(--cc-blue-soft); }
        .cc-more svg { transition: transform .18s ease; }
        .cc-more:hover svg { transform: translateX(2px); }

        /* Glyph tile — the drawn mark in each card header */
        .cc-glyph { width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center; flex: none;
            background: var(--cc-sunk); border: 1px solid var(--cc-line); box-shadow: inset 0 -1px 0 var(--cc-line); color: var(--cc-ink); }
        .cc-glyph svg { width: 20px; height: 20px; }

        /* Toolbar */
        .cc-bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
        .cc-today { font-size: 13.5px; color: var(--cc-ink-2); display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .cc-today b { color: var(--cc-ink); font-weight: 650; }
        .cc-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--cc-green); box-shadow: 0 0 0 3px var(--cc-green-soft); flex: none; }
        .cc-tools { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }

        /* Physical controls: a recessed track with a raised thumb, keys that press down */
        .cc-seg { display: inline-flex; padding: 3px; gap: 2px; border-radius: 11px; background: var(--cc-sunk);
            border: 1px solid var(--cc-line); box-shadow: inset 0 1px 2px rgba(16,24,40,.06); }
        .cc-seg button { border: 0; background: transparent; color: var(--cc-ink-2); font-size: 12.5px; font-weight: 600;
            padding: 6px 11px; border-radius: 8px; cursor: pointer; transition: background .15s, color .15s, transform .08s, box-shadow .15s; }
        .cc-seg button:hover { color: var(--cc-ink); }
        .cc-seg button:active { transform: scale(.96); }
        .cc-seg button[aria-pressed="true"] { background: var(--cc-surface); color: var(--cc-ink);
            box-shadow: 0 1px 2px rgba(16,24,40,.12), 0 0 0 1px var(--cc-line); }
        .cc-search { display: inline-flex; align-items: center; gap: 10px; min-width: 250px; padding: 7px 8px 7px 11px;
            border-radius: 11px; border: 1px solid var(--cc-line); background: var(--cc-surface); color: var(--cc-ink-3);
            font-size: 13px; cursor: text; box-shadow: var(--cc-lift); transition: border-color .15s; }
        .cc-search:hover { border-color: var(--cc-blue-mid); }
        .cc-search span.l { flex: 1; text-align: left; }
        .cc-key { font: 600 11px/1 ui-sans-serif, system-ui; color: var(--cc-ink-2); padding: 4px 6px 3px; border-radius: 6px;
            background: var(--cc-surface); border: 1px solid var(--cc-line); border-bottom-width: 2px; }
        .cc-search:active .cc-key { border-bottom-width: 1px; transform: translateY(1px); }

        /* Money */
        .cc-eyebrow { font-size: 12.5px; color: var(--cc-ink-3); font-weight: 550; }
        .cc-gmv { font-size: clamp(30px, 3.3vw, 40px); font-weight: 700; letter-spacing: -0.035em; line-height: 1.05; margin: 6px 0 6px; }
        .cc-delta { font-size: 12.5px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; }
        .cc-delta.up { color: var(--cc-green); } .cc-delta.down { color: var(--cc-red); } .cc-delta.flat { color: var(--cc-ink-3); }
        .cc-facts { display: flex; gap: 22px; flex-wrap: wrap; margin-top: 2px; }
        .cc-fact { display: flex; flex-direction: column; }
        .cc-fact span { font-size: 12px; color: var(--cc-ink-3); }
        .cc-fact b { font-size: 15px; font-weight: 650; }
        .cc-money-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 20px; flex-wrap: wrap; }
        .cc-chart { width: 100%; height: auto; display: block; margin-top: 14px; overflow: visible; }
        .cc-chart .bar { fill: var(--cc-blue-mid); transition: fill .15s; }
        .cc-chart .bar.now { fill: var(--cc-blue); }
        .cc-chart g.col:hover .bar { fill: var(--cc-blue-2); }
        .cc-chart g.col .hit { fill: transparent; }
        .cc-chart .grid { stroke: var(--cc-line-2); stroke-width: 1; }
        .cc-chart .base { stroke: var(--cc-line); stroke-width: 1; }
        .cc-chart text { fill: var(--cc-ink-3); font-size: 10.5px; font-family: inherit; }
        .cc-chart .callout rect { fill: var(--cc-ink); }
        .cc-chart .callout text { fill: var(--cc-surface); font-weight: 650; font-size: 10.5px; }
        .cc-chart .tip { opacity: 0; transition: opacity .12s; pointer-events: none; }
        .cc-chart g.col:hover .tip { opacity: 1; }
        .cc-chart g.col:hover ~ .callout, .cc-chart:hover .callout { opacity: .0; }
        .cc-chart .callout { transition: opacity .12s; }
        .cc-empty-chart { display: grid; place-items: center; text-align: center; gap: 6px; padding: 26px 0 8px; color: var(--cc-ink-3); font-size: 13px; }

        /* Where it went — one bar, three honest slices */
        .cc-split { margin-top: 16px; padding-top: 14px; border-top: 1px dashed var(--cc-line); }
        .cc-split-bar { display: flex; height: 12px; border-radius: 6px; overflow: hidden; gap: 2px; background: var(--cc-sunk); }
        .cc-split-bar i { display: block; height: 100%; min-width: 3px; }
        .cc-split-legend { display: flex; flex-wrap: wrap; gap: 6px 22px; margin-top: 10px; font-size: 12.5px; color: var(--cc-ink-2); }
        .cc-split-legend span { display: inline-flex; align-items: center; gap: 7px; }
        .cc-split-legend i { width: 9px; height: 9px; border-radius: 3px; display: inline-block; }
        .cc-split-legend b { color: var(--cc-ink); font-weight: 650; }

        /* Attention list */
        .cc-rows { display: flex; flex-direction: column; margin: 0 -8px; }
        .cc-row { display: flex; align-items: center; gap: 12px; padding: 10px 8px; border-radius: 10px; transition: background .12s, transform .08s; }
        .cc-row + .cc-row { border-top: 1px solid var(--cc-line-2); }
        a.cc-row:hover { background: var(--cc-sunk); }
        a.cc-row:active { transform: translateY(1px); }
        .cc-row-ic { width: 30px; height: 30px; border-radius: 9px; display: grid; place-items: center; flex: none;
            background: var(--cc-sunk); color: var(--cc-ink-2); border: 1px solid var(--cc-line); }
        .cc-row-ic svg { width: 16px; height: 16px; }
        .cc-row.hot .cc-row-ic { background: var(--cc-amber-soft); color: var(--cc-amber); border-color: transparent; }
        .cc-row-main { flex: 1; min-width: 0; }
        .cc-row-t { font-size: 13.5px; font-weight: 600; }
        .cc-row-s { font-size: 12px; color: var(--cc-ink-3); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .cc-count { min-width: 28px; height: 24px; padding: 0 8px; border-radius: 8px; display: grid; place-items: center; font-size: 12.5px; font-weight: 700;
            background: var(--cc-amber-soft); color: var(--cc-amber); }
        .cc-count.zero { background: transparent; color: var(--cc-ink-3); font-weight: 500; }
        .cc-clear { display: flex; align-items: center; gap: 14px; padding: 6px 0 14px; }
        .cc-clear p { margin: 0; font-size: 13.5px; font-weight: 600; }
        .cc-clear span { font-size: 12.5px; color: var(--cc-ink-3); }

        /* Lines of business */
        .cc-line-card { display: flex; flex-direction: column; gap: 14px; transition: box-shadow .2s, transform .2s; }
        .cc-line-card:hover { box-shadow: var(--cc-lift-hi); }
        .cc-figure { display: block; width: 100%; max-width: 380px; height: auto; overflow: visible; }
        .cc-figure.wide { max-width: none; }
        .cc-figure text { font-family: inherit; }
        .cc-kv { display: grid; grid-template-columns: repeat(auto-fit, minmax(92px, 1fr)); gap: 10px 14px; border-top: 1px solid var(--cc-line-2); padding-top: 12px; }
        .cc-kv div span { display: block; font-size: 11.5px; color: var(--cc-ink-3); }
        .cc-kv div b { font-size: 14.5px; font-weight: 650; }
        .cc-note { font-size: 12.5px; color: var(--cc-ink-2); display: flex; align-items: center; gap: 8px; padding: 9px 11px; border-radius: 10px; background: var(--cc-sunk); }
        .cc-note b { color: var(--cc-ink); font-weight: 600; }
        .cc-meter { height: 5px; border-radius: 3px; background: var(--cc-line-2); overflow: hidden; flex: 0 0 64px; }
        .cc-meter i { display: block; height: 100%; background: var(--cc-blue); border-radius: 3px; }

        /* Latest bookings */
        .cc-feed { display: flex; flex-direction: column; margin: 0 -8px; }
        .cc-feed a { display: grid; grid-template-columns: 30px minmax(0,1fr) auto; gap: 12px; align-items: center; padding: 9px 8px; border-radius: 10px; transition: background .12s, transform .08s; }
        .cc-feed a + a { border-top: 1px solid var(--cc-line-2); }
        .cc-feed a:hover { background: var(--cc-sunk); }
        .cc-feed a:active { transform: translateY(1px); }
        .cc-feed .k { width: 30px; height: 30px; border-radius: 9px; display: grid; place-items: center; background: var(--cc-sunk); border: 1px solid var(--cc-line); color: var(--cc-ink-2); }
        .cc-feed .k svg { width: 17px; height: 17px; }
        .cc-feed .t { font-size: 13.5px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .cc-feed .s { font-size: 12px; color: var(--cc-ink-3); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .cc-feed .r { text-align: right; }
        .cc-feed .amt { font-size: 13.5px; font-weight: 650; }
        .cc-pill { display: inline-flex; align-items: center; gap: 5px; font-size: 11.5px; font-weight: 600; color: var(--cc-ink-3); }
        .cc-pill i { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
        .cc-pill.ok { color: var(--cc-green); } .cc-pill.warn { color: var(--cc-amber); } .cc-pill.down { color: var(--cc-red); }

        /* Cities */
        .cc-city { display: grid; grid-template-columns: 110px minmax(0,1fr) auto; gap: 12px; align-items: center; padding: 7px 0; font-size: 13px; }
        .cc-city .name { font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .cc-city .track { height: 8px; border-radius: 4px; background: var(--cc-sunk); overflow: hidden; }
        .cc-city .track i { display: block; height: 100%; border-radius: 4px; background: var(--cc-blue-mid); }
        .cc-city:first-child .track i { background: var(--cc-blue); }
        .cc-city .v { font-weight: 650; text-align: right; min-width: 72px; }

        /* Owed */
        .cc-owed { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 14px 16px; margin-top: 14px;
            border-radius: 12px; background: var(--cc-sunk); border: 1px solid var(--cc-line); }
        .cc-owed b { font-size: 20px; font-weight: 700; }
        .cc-owed span { font-size: 12px; color: var(--cc-ink-3); display: block; }

        /* Audit timeline */
        .cc-tl { position: relative; padding-left: 18px; }
        .cc-tl::before { content: ""; position: absolute; left: 4px; top: 6px; bottom: 6px; width: 1px; background: var(--cc-line); }
        .cc-tl-i { position: relative; padding: 0 0 13px; }
        .cc-tl-i:last-child { padding-bottom: 0; }
        .cc-tl-i::before { content: ""; position: absolute; left: -18px; top: 5px; width: 9px; height: 9px; border-radius: 50%;
            background: var(--cc-surface); border: 2px solid var(--cc-blue-mid); }
        .cc-tl-i:first-child::before { border-color: var(--cc-blue); }
        .cc-tl-t { font-size: 13px; font-weight: 600; }
        .cc-tl-s { font-size: 12px; color: var(--cc-ink-3); }

        /* Systems */
        .cc-sys { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 9px 0; font-size: 13px; }
        .cc-sys + .cc-sys { border-top: 1px solid var(--cc-line-2); }
        .cc-sys .n { display: flex; align-items: center; gap: 9px; font-weight: 600; }
        .cc-sys .d { color: var(--cc-ink-3); font-size: 12.5px; text-align: right; }
        .cc-led { width: 8px; height: 8px; border-radius: 50%; background: var(--cc-green); box-shadow: 0 0 0 3px var(--cc-green-soft); }
        .cc-led.off { background: var(--cc-amber); box-shadow: 0 0 0 3px var(--cc-amber-soft); }

        .cc-muted-empty { display: flex; align-items: center; gap: 14px; padding: 8px 0; color: var(--cc-ink-3); font-size: 13px; }

        /* ⌘K */
        .cc-modal { position: fixed; inset: 0; z-index: 60; background: rgba(16,24,40,.38); backdrop-filter: blur(3px);
            display: flex; align-items: flex-start; justify-content: center; padding: 12vh 16px 16px; }
        .cc-modal-box { width: 100%; max-width: 600px; background: var(--cc-surface); border: 1px solid var(--cc-line); border-radius: 16px;
            box-shadow: 0 24px 60px -18px rgba(16,24,40,.45); overflow: hidden; }
        .cc-modal-in { display: flex; align-items: center; gap: 10px; padding: 14px 16px; border-bottom: 1px solid var(--cc-line); }
        .cc-modal-in input { flex: 1; border: 0; outline: 0; background: transparent; font-size: 15px; color: var(--cc-ink); box-shadow: none; padding: 0; }
        .cc-modal-in input:focus { box-shadow: none; }
        .cc-res { max-height: 56vh; overflow-y: auto; padding: 6px; }
        .cc-res-g { font-size: 11.5px; font-weight: 600; color: var(--cc-ink-3); padding: 10px 10px 4px; }
        .cc-res a { display: flex; justify-content: space-between; gap: 12px; padding: 9px 10px; border-radius: 10px; }
        .cc-res a:hover, .cc-res a:focus { background: var(--cc-blue-soft); outline: none; }
        .cc-res .t { font-size: 13.5px; font-weight: 600; }
        .cc-res .m { font-size: 12px; color: var(--cc-ink-3); }
        .cc-res-empty { padding: 26px 16px; text-align: center; font-size: 13px; color: var(--cc-ink-3); }

        @media (max-width: 640px) {
            .cc-card { padding: 16px; border-radius: 14px; }
            .cc-search { min-width: 0; flex: 1; }
            .cc-tools { width: 100%; }
            .cc-seg { width: 100%; overflow-x: auto; }
            .cc-seg button { flex: 1; padding: 6px 8px; }
            .cc-city { grid-template-columns: 86px minmax(0,1fr) auto; }
        }
        @media (max-width: 640px) {
            /* The chart scales down with its viewBox; keep its labels readable on a phone. */
            .cc-chart text { font-size: 19px; }
            .cc-chart .callout, .cc-chart .tip { display: none; }
        }
        @media (prefers-reduced-motion: reduce) { .cc * { transition: none !important; } }
    </style>
