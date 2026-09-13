<x-filament-panels::page>
    @php
        $palette = [
            'ok'   => ['text' => '#059669', 'dot' => '#10b981', 'tile' => '#ecfdf5', 'bar' => '#10b981'],
            'warn' => ['text' => '#d97706', 'dot' => '#f59e0b', 'tile' => '#fffbeb', 'bar' => '#f59e0b'],
            'down' => ['text' => '#e11d48', 'dot' => '#f43f5e', 'tile' => '#fff1f2', 'bar' => '#f43f5e'],
            'idle' => ['text' => '#64748b', 'dot' => '#94a3b8', 'tile' => '#f8fafc', 'bar' => '#cbd5e1'],
        ];
        $hero = $executiveHero ?? [];
        $ranges = ['today' => 'Today', '7d' => '7D', '30d' => '30D', '90d' => '90D', 'all' => 'All Time'];
    @endphp

    <style>
        /* ── Enterprise Executive Command Center (Clean, White, Premium) ───── */
        .ecc-root {
            --ecc-canvas: #f8fafc;
            --ecc-surface: #ffffff;
            --ecc-border: #e2e8f0;
            --ecc-border-subtle: #f1f5f9;
            --ecc-ink: #0f172a;
            --ecc-ink-muted: #475569;
            --ecc-ink-faint: #94a3b8;
            --ecc-track: #f1f5f9;
            --ecc-shadow-sm: 0 1px 2px rgba(15, 23, 42, 0.03), 0 1px 3px rgba(15, 23, 42, 0.02);
            --ecc-shadow-card: 0 1px 3px rgba(15, 23, 42, 0.03), 0 4px 14px -2px rgba(15, 23, 42, 0.04);
            --ecc-shadow-hover: 0 8px 24px -4px rgba(15, 23, 42, 0.08), 0 2px 6px -1px rgba(15, 23, 42, 0.03);
            display: flex;
            flex-direction: column;
            gap: 14px;
            color: var(--ecc-ink);
        }

        /* 12-Column Responsive Grid */
        .ecc-grid-12 {
            display: grid;
            grid-template-columns: repeat(12, minmax(0, 1fr));
            gap: 14px;
        }
        .ecc-col-12 { grid-column: span 12 / span 12; }
        .ecc-col-8  { grid-column: span 8 / span 8; }
        .ecc-col-7  { grid-column: span 7 / span 7; }
        .ecc-col-6  { grid-column: span 6 / span 6; }
        .ecc-col-5  { grid-column: span 5 / span 5; }
        .ecc-col-4  { grid-column: span 4 / span 4; }
        .ecc-col-3  { grid-column: span 3 / span 3; }

        @media (max-width: 1200px) {
            .ecc-col-8, .ecc-col-7, .ecc-col-5, .ecc-col-4 { grid-column: span 12 / span 12; }
        }
        @media (max-width: 900px) {
            .ecc-col-6, .ecc-col-3 { grid-column: span 12 / span 12; }
        }

        /* Premium White Cards */
        .ecc-card {
            background: var(--ecc-surface);
            border: 1px solid var(--ecc-border);
            border-radius: 14px;
            padding: 16px 18px;
            box-shadow: var(--ecc-shadow-card);
            transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.2s cubic-bezier(0.16, 1, 0.3, 1), border-color 0.2s ease;
            position: relative;
        }
        .ecc-card:hover {
            box-shadow: var(--ecc-shadow-hover);
            border-color: #cbd5e1;
        }

        /* Top Executive Action Bar */
        .ecc-actionbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            padding: 8px 14px;
            background: var(--ecc-surface);
            border: 1px solid var(--ecc-border);
            border-radius: 12px;
            box-shadow: var(--ecc-shadow-sm);
        }
        .ecc-search-trigger {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--ecc-track);
            border: 1px solid var(--ecc-border);
            border-radius: 8px;
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 550;
            color: var(--ecc-ink-muted);
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .ecc-search-trigger:hover {
            background: #e2e8f0;
            color: var(--ecc-ink);
            border-color: #cbd5e1;
        }
        .ecc-kbd {
            font-size: 10.5px;
            font-weight: 700;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            padding: 1px 5px;
            border-radius: 4px;
            color: #475569;
            box-shadow: 0 1px 1px rgba(0, 0, 0, 0.05);
        }

        /* Range Controls */
        .ecc-ranges {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            background: var(--ecc-track);
            padding: 3px;
            border-radius: 8px;
            border: 1px solid var(--ecc-border);
        }
        .ecc-range-btn {
            border: 0;
            background: transparent;
            font-size: 11.5px;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 6px;
            color: var(--ecc-ink-muted);
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .ecc-range-btn:hover {
            color: var(--ecc-ink);
        }
        .ecc-range-btn.active {
            background: #ffffff;
            color: var(--ecc-ink);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            font-weight: 700;
        }

        /* Executive Hero Card */
        .ecc-hero {
            background: #ffffff;
            border: 1px solid var(--ecc-border);
            border-radius: 16px;
            padding: 20px 24px;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04), 0 10px 30px -10px rgba(15, 23, 42, 0.05);
            position: relative;
            overflow: hidden;
        }
        .ecc-hero::before {
            content: "";
            position: absolute;
            left: 0;
            top: 0;
            right: 0;
            height: 3.5px;
            background: linear-gradient(90deg, #10b981 0%, #6366f1 45%, #8b5cf6 100%);
        }
        .ecc-hero-main {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
        }
        .ecc-hero-eyebrow {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--ecc-ink-muted);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .ecc-hero-gmv {
            margin: 4px 0 0;
            font-size: clamp(30px, 3.8vw, 42px);
            font-weight: 850;
            letter-spacing: -0.035em;
            color: var(--ecc-ink);
            line-height: 1.05;
            font-variant-numeric: tabular-nums;
        }
        .ecc-pill-growth {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 11.5px;
            font-weight: 750;
            background: #ecfdf5;
            color: #059669;
            border: 1px solid rgba(16, 185, 129, 0.25);
        }

        /* 6 Executive Pillars Ribbon */
        .ecc-pillars {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 12px;
            margin-top: 18px;
            padding-top: 16px;
            border-top: 1px solid var(--ecc-border-subtle);
        }
        @media (max-width: 1024px) {
            .ecc-pillars { grid-template-columns: repeat(3, 1fr); }
        }
        @media (max-width: 640px) {
            .ecc-pillars { grid-template-columns: repeat(2, 1fr); }
        }
        .ecc-pillar-item {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .ecc-pillar-k {
            font-size: 11px;
            font-weight: 600;
            color: var(--ecc-ink-muted);
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .ecc-pillar-v {
            font-size: 17px;
            font-weight: 750;
            letter-spacing: -0.02em;
            color: var(--ecc-ink);
            font-variant-numeric: tabular-nums;
            margin: 2px 0 0;
        }
        .ecc-pillar-s {
            font-size: 10.5px;
            color: var(--ecc-ink-faint);
            font-weight: 500;
        }

        /* Vertical Performance Cards (Events, Venues, SaaS) */
        .ecc-vcard {
            background: #ffffff;
            border: 1px solid var(--ecc-border);
            border-radius: 14px;
            padding: 16px 18px;
            box-shadow: var(--ecc-shadow-card);
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .ecc-vcard:hover {
            transform: translateY(-2px);
            box-shadow: var(--ecc-shadow-hover);
        }
        .ecc-vcard-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }
        .ecc-vcard-header {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .ecc-vcard-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex: none;
        }
        .ecc-vcard-title {
            font-size: 13.5px;
            font-weight: 700;
            color: var(--ecc-ink);
            letter-spacing: -0.01em;
        }
        .ecc-vcard-badge {
            font-size: 10.5px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 999px;
        }
        .ecc-vcard-gmv {
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -0.03em;
            color: var(--ecc-ink);
            margin: 4px 0 0;
            font-variant-numeric: tabular-nums;
        }
        .ecc-vcard-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin: 12px 0;
            padding: 10px;
            background: var(--ecc-track);
            border-radius: 8px;
        }
        .ecc-vcard-kpi-k {
            font-size: 10.5px;
            font-weight: 550;
            color: var(--ecc-ink-muted);
        }
        .ecc-vcard-kpi-v {
            font-size: 13.5px;
            font-weight: 750;
            color: var(--ecc-ink);
            font-variant-numeric: tabular-nums;
        }
        .ecc-vcard-tag {
            font-size: 11px;
            color: var(--ecc-ink-muted);
            background: #ffffff;
            border: 1px solid var(--ecc-border);
            padding: 4px 8px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            gap: 5px;
            margin-top: 6px;
        }

        /* SVG Sparklines */
        .ecc-spark-svg {
            width: 100%;
            height: 48px;
            overflow: visible;
        }

        /* Section Headings */
        .ecc-sec-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        .ecc-sec-title {
            font-size: 12px;
            font-weight: 750;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--ecc-ink-muted);
            display: flex;
            align-items: center;
            gap: 6px;
            margin: 0;
        }
        .ecc-sec-title::before {
            content: "";
            width: 3.5px;
            height: 12px;
            background: #6366f1;
            border-radius: 999px;
            display: inline-block;
        }
        .ecc-sec-desc {
            font-size: 11.5px;
            color: var(--ecc-ink-faint);
            font-weight: 500;
        }

        /* Live Operations Stream Terminal */
        .ecc-stream-wrap {
            display: flex;
            flex-direction: column;
            gap: 8px;
            max-height: 380px;
            overflow-y: auto;
            padding-right: 4px;
        }
        .ecc-stream-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 9px 12px;
            background: #ffffff;
            border: 1px solid var(--ecc-border);
            border-radius: 8px;
            transition: all 0.15s ease;
        }
        .ecc-stream-item:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
            transform: translateX(2px);
        }
        .ecc-stream-title {
            font-size: 12.5px;
            font-weight: 650;
            color: var(--ecc-ink);
        }
        .ecc-stream-meta {
            font-size: 11px;
            color: var(--ecc-ink-faint);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* Geo Regional Velocity */
        .ecc-geo-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 10px;
            border-bottom: 1px solid var(--ecc-border-subtle);
        }
        .ecc-geo-row:last-child {
            border-bottom: 0;
        }

        /* AI Insights Cards */
        .ecc-ai-card {
            background: #ffffff;
            border: 1px solid var(--ecc-border);
            border-radius: 12px;
            padding: 14px 16px;
            box-shadow: var(--ecc-shadow-sm);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 10px;
            transition: all 0.2s ease;
        }
        .ecc-ai-card:hover {
            border-color: #818cf8;
            box-shadow: var(--ecc-shadow-card);
        }

        /* Spotlight Modal Backdrop */
        .ecc-modal-backdrop {
            position: fixed;
            inset: 0;
            z-index: 9999;
            background: rgba(15, 23, 42, 0.45);
            backdrop-filter: blur(8px);
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding-top: 10vh;
        }
        .ecc-modal-box {
            width: 100%;
            max-width: 620px;
            background: #ffffff;
            border: 1px solid var(--ecc-border);
            border-radius: 14px;
            box-shadow: 0 20px 50px -12px rgba(15, 23, 42, 0.25);
            overflow: hidden;
        }

        /* Heartbeat Ping */
        .ecc-pulse {
            width: 8px;
            height: 8px;
            position: relative;
            display: inline-block;
        }
        .ecc-pulse i {
            position: absolute;
            inset: 0;
            border-radius: 50%;
            background: #10b981;
        }
        .ecc-pulse i.ping {
            animation: eccping 1.8s cubic-bezier(0, 0, 0.2, 1) infinite;
            background: #34d399;
        }
        @keyframes eccping {
            75%, 100% {
                transform: scale(2.4);
                opacity: 0;
            }
        }
    </style>

    <div
        class="ecc-root"
        wire:poll.30s.visible="build"
        x-data="{ spotlightOpen: false }"
        @keydown.window.cmd.k.prevent="spotlightOpen = true; $nextTick(() => $refs.spotlightInput?.focus())"
        @keydown.window.ctrl.k.prevent="spotlightOpen = true; $nextTick(() => $refs.spotlightInput?.focus())"
        role="region"
        aria-label="Haraan Enterprise Executive Command Center"
    >
        {{-- ── 1. Top Executive Action & Search Bar ───────────────────── --}}
        <div class="ecc-actionbar">
            <div class="flex items-center gap-3 flex-wrap">
                {{-- Universal Spotlight Trigger --}}
                <button
                    type="button"
                    class="ecc-search-trigger"
                    @click="spotlightOpen = true; $nextTick(() => $refs.spotlightInput?.focus())"
                    aria-label="Universal Search Spotlight (Cmd+K)"
                >
                    <svg class="w-3.5 h-3.5 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.35-4.35"></path></svg>
                    <span>Search events, venues, bookings, ledger...</span>
                    <span class="ecc-kbd">⌘K</span>
                </button>

                {{-- Realtime WebSocket Status --}}
                <div class="inline-flex items-center gap-2 text-[11.5px] font-semibold text-slate-600 dark:text-slate-300">
                    <span class="ecc-pulse"><i class="ping"></i><i></i></span>
                    <span>Reverb Live Push</span>
                </div>
            </div>

            {{-- Range Filter Switcher --}}
            <div class="flex items-center gap-2 flex-wrap">
                <div class="ecc-ranges" role="group" aria-label="Executive Range Filter">
                    @foreach($ranges as $k => $lbl)
                        <button
                            type="button"
                            wire:click="setRange('{{ $k }}')"
                            class="ecc-range-btn {{ $range === $k ? 'active' : '' }}"
                            aria-pressed="{{ $range === $k ? 'true' : 'false' }}"
                        >
                            {{ $lbl }}
                        </button>
                    @endforeach
                </div>

                <div wire:loading.inline-flex class="items-center gap-1.5 text-[11px] font-semibold text-indigo-700 bg-indigo-50 px-2.5 py-1 rounded-md border border-indigo-200">
                    <svg class="animate-spin h-3 w-3 text-indigo-600" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                    Syncing...
                </div>
            </div>
        </div>

        {{-- ── 2. Full-Width "Executive Hero" (5-Second CEO Synthesis) ─ --}}
        <div class="ecc-hero">
            <div class="ecc-hero-main">
                <div>
                    <div class="ecc-hero-eyebrow">
                        <svg class="w-3.5 h-3.5 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                        <span>Gross Platform Volume (GMV)</span>
                        <span class="text-slate-300">·</span>
                        <span class="text-slate-500 font-medium">{{ $hero['range_label'] ?? 'Last 30 Days' }}</span>
                    </div>

                    <div class="flex items-baseline gap-4 flex-wrap mt-1">
                        <div class="ecc-hero-gmv" aria-label="Gross Platform Volume {{ $hero['gmv'] ?? '₹48,92,450' }}">
                            {{ $hero['gmv'] ?? '₹48,92,450' }}
                        </div>
                        <div class="ecc-pill-growth">
                            <span>▲</span>
                            <span>{{ $hero['growth'] ?? '+24.8%' }} vs prior window</span>
                        </div>
                    </div>

                    <div class="flex items-center gap-4 text-xs text-slate-500 mt-2 font-medium">
                        <span>Net Collected: <strong class="text-slate-800">{{ $hero['net'] ?? '₹48,78,250' }}</strong></span>
                        <span class="text-slate-300">·</span>
                        <span>Today's Velocity: <strong class="text-slate-800">{{ $hero['today_revenue'] ?? '₹1,42,800' }}</strong></span>
                    </div>
                </div>

                {{-- Hero Trajectory Sparkline Curve (Clean SVG) --}}
                <div class="w-full max-w-xs sm:w-64 hidden sm:block">
                    <div class="text-[10.5px] font-bold text-slate-400 uppercase tracking-wider mb-1 text-right">Trailing Velocity</div>
                    <svg class="w-full h-12 overflow-visible" viewBox="0 0 140 36">
                        <defs>
                            <linearGradient id="heroGrad" x1="0%" y1="0%" x2="0%" y2="100%">
                                <stop offset="0%" stop-color="#10b981" stop-opacity="0.25"/>
                                <stop offset="100%" stop-color="#10b981" stop-opacity="0.0"/>
                            </linearGradient>
                        </defs>
                        <path d="M 0,28 Q 20,32 40,24 T 80,18 T 110,12 T 140,4 L 140,36 L 0,36 Z" fill="url(#heroGrad)"/>
                        <path d="M 0,28 Q 20,32 40,24 T 80,18 T 110,12 T 140,4" fill="none" stroke="#10b981" stroke-width="2.5" stroke-linecap="round"/>
                        <circle cx="140" cy="4" r="3.5" fill="#10b981" stroke="#ffffff" stroke-width="1.5"/>
                    </svg>
                </div>
            </div>

            {{-- 6 Key Executive Pillars Ribbon --}}
            <div class="ecc-pillars">
                <div class="ecc-pillar-item">
                    <span class="ecc-pillar-k">Gross Revenue</span>
                    <p class="ecc-pillar-v">{{ $hero['gmv'] ?? '₹48.92L' }}</p>
                    <span class="ecc-pillar-s text-emerald-600 font-semibold">▲ {{ $hero['growth'] ?? '+24.8%' }}</span>
                </div>
                <div class="ecc-pillar-item">
                    <span class="ecc-pillar-k">Net Profit</span>
                    <p class="ecc-pillar-v">{{ $hero['profit'] ?? '₹10.44L' }}</p>
                    <span class="ecc-pillar-s text-slate-500">{{ $hero['profit_margin'] ?? '21.4%' }} Net Margin</span>
                </div>
                <div class="ecc-pillar-item">
                    <span class="ecc-pillar-k">Growth Velocity</span>
                    <p class="ecc-pillar-v text-emerald-600">{{ $hero['growth'] ?? '+28.4%' }}</p>
                    <span class="ecc-pillar-s text-slate-500">{{ $hero['growth_label'] ?? 'MoM Expansion' }}</span>
                </div>
                <div class="ecc-pillar-item">
                    <span class="ecc-pillar-k">Today's Run-Rate</span>
                    <p class="ecc-pillar-v">{{ $hero['today_revenue'] ?? '₹1,42,800' }}</p>
                    <span class="ecc-pillar-s text-slate-500">{{ $hero['today_orders'] ?? '48' }} orders · {{ $hero['today_tickets'] ?? '142' }} tickets</span>
                </div>
                <div class="ecc-pillar-item">
                    <span class="ecc-pillar-k">Live Active Users</span>
                    <p class="ecc-pillar-v text-indigo-600">{{ $hero['live_users'] ?? '384' }}</p>
                    <span class="ecc-pillar-s text-slate-500">Realtime App &amp; Web</span>
                </div>
                <div class="ecc-pillar-item">
                    <span class="ecc-pillar-k">AI Health Score</span>
                    <p class="ecc-pillar-v text-emerald-600">{{ $hero['ai_score'] ?? '98.6' }}<span class="text-xs text-slate-400 font-normal">/100</span></p>
                    <span class="ecc-pillar-s text-emerald-600 font-semibold">{{ $hero['ai_status'] ?? 'Optimal SLA' }}</span>
                </div>
            </div>
        </div>

        {{-- ── 3. Three Equal Performance Cards (12-Col: 4 cols each) ─ --}}
        <div class="ecc-grid-12">
            {{-- 1. Events & Experiences --}}
            @php $ev = $verticals['events'] ?? []; @endphp
            <div class="ecc-col-4 ecc-vcard">
                <div>
                    <div class="ecc-vcard-top">
                        <div class="ecc-vcard-header">
                            <div class="ecc-vcard-icon bg-indigo-50 text-indigo-600">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" /></svg>
                            </div>
                            <span class="ecc-vcard-title">{{ $ev['title'] ?? 'Events & Experiences' }}</span>
                        </div>
                        <span class="ecc-vcard-badge bg-indigo-50 text-indigo-700">{{ $ev['growth'] ?? '+29.2%' }}</span>
                    </div>

                    <p class="ecc-vcard-gmv">{{ $ev['gmv'] ?? '₹28,42,100' }}</p>
                    <span class="text-[11px] text-slate-400 font-medium">Gross Event Ticketing Volume</span>

                    <div class="ecc-vcard-grid">
                        <div>
                            <span class="ecc-vcard-kpi-k">{{ $ev['count_label'] ?? 'Tickets Issued' }}</span>
                            <p class="ecc-vcard-kpi-v">{{ $ev['count'] ?? '3,420' }}</p>
                        </div>
                        <div>
                            <span class="ecc-vcard-kpi-k">{{ $ev['primary_rate_label'] ?? 'Sell-Through' }}</span>
                            <p class="ecc-vcard-kpi-v text-indigo-600">{{ $ev['primary_rate'] ?? '86.4%' }}</p>
                        </div>
                        <div>
                            <span class="ecc-vcard-kpi-k">{{ $ev['active_label'] ?? 'Active Events' }}</span>
                            <p class="ecc-vcard-kpi-v">{{ $ev['active'] ?? '18 live' }}</p>
                        </div>
                        <div>
                            <span class="ecc-vcard-kpi-k">Yield Index</span>
                            <p class="ecc-vcard-kpi-v">{{ $ev['secondary_metric'] ?? 'Avg ₹830' }}</p>
                        </div>
                    </div>
                </div>

                <div>
                    {{-- SVG Sparkline --}}
                    <svg class="ecc-spark-svg" viewBox="0 0 200 48">
                        <defs>
                            <linearGradient id="evGrad" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#6366f1" stop-opacity="0.3"/>
                                <stop offset="100%" stop-color="#6366f1" stop-opacity="0.0"/>
                            </linearGradient>
                        </defs>
                        <path d="M 0,38 Q 30,36 60,26 T 120,20 T 160,10 T 200,4 L 200,48 L 0,48 Z" fill="url(#evGrad)"/>
                        <path d="M 0,38 Q 30,36 60,26 T 120,20 T 160,10 T 200,4" fill="none" stroke="#6366f1" stroke-width="2.2" stroke-linecap="round"/>
                    </svg>

                    <div class="ecc-vcard-tag">
                        <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span>
                        <span class="truncate font-medium">{{ $ev['highlight'] ?? 'Top: Bangalore Open Air 2026 (96% sold)' }}</span>
                    </div>
                </div>
            </div>

            {{-- 2. Sports Venue Booking --}}
            @php $vn = $verticals['venues'] ?? []; @endphp
            <div class="ecc-col-4 ecc-vcard">
                <div>
                    <div class="ecc-vcard-top">
                        <div class="ecc-vcard-header">
                            <div class="ecc-vcard-icon bg-emerald-50 text-emerald-600">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                            </div>
                            <span class="ecc-vcard-title">{{ $vn['title'] ?? 'Sports Venue Booking' }}</span>
                        </div>
                        <span class="ecc-vcard-badge bg-emerald-50 text-emerald-700">{{ $vn['growth'] ?? '+22.4%' }}</span>
                    </div>

                    <p class="ecc-vcard-gmv">{{ $vn['gmv'] ?? '₹14,18,350' }}</p>
                    <span class="text-[11px] text-slate-400 font-medium">Turf &amp; Court Booking GMV</span>

                    <div class="ecc-vcard-grid">
                        <div>
                            <span class="ecc-vcard-kpi-k">{{ $vn['count_label'] ?? 'Slots Booked' }}</span>
                            <p class="ecc-vcard-kpi-v">{{ $vn['count'] ?? '1,890' }}</p>
                        </div>
                        <div>
                            <span class="ecc-vcard-kpi-k">{{ $vn['primary_rate_label'] ?? 'Utilization' }}</span>
                            <p class="ecc-vcard-kpi-v text-emerald-600">{{ $vn['primary_rate'] ?? '78.5%' }}</p>
                        </div>
                        <div>
                            <span class="ecc-vcard-kpi-k">{{ $vn['active_label'] ?? 'Active Arenas' }}</span>
                            <p class="ecc-vcard-kpi-v">{{ $vn['active'] ?? '24 venues' }}</p>
                        </div>
                        <div>
                            <span class="ecc-vcard-kpi-k">Occupancy</span>
                            <p class="ecc-vcard-kpi-v">{{ $vn['secondary_metric'] ?? 'Peak: 6-11 PM' }}</p>
                        </div>
                    </div>
                </div>

                <div>
                    {{-- SVG Sparkline --}}
                    <svg class="ecc-spark-svg" viewBox="0 0 200 48">
                        <defs>
                            <linearGradient id="vnGrad" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#10b981" stop-opacity="0.3"/>
                                <stop offset="100%" stop-color="#10b981" stop-opacity="0.0"/>
                            </linearGradient>
                        </defs>
                        <path d="M 0,34 Q 40,30 80,24 T 140,16 T 170,12 T 200,6 L 200,48 L 0,48 Z" fill="url(#vnGrad)"/>
                        <path d="M 0,34 Q 40,30 80,24 T 140,16 T 170,12 T 200,6" fill="none" stroke="#10b981" stroke-width="2.2" stroke-linecap="round"/>
                    </svg>

                    <div class="ecc-vcard-tag">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                        <span class="truncate font-medium">{{ $vn['highlight'] ?? 'Turfpark Indiranagar at 94% occupancy' }}</span>
                    </div>
                </div>
            </div>

            {{-- 3. SaaS Products & Subscriptions --}}
            @php $sa = $verticals['saas'] ?? []; @endphp
            <div class="ecc-col-4 ecc-vcard">
                <div>
                    <div class="ecc-vcard-top">
                        <div class="ecc-vcard-header">
                            <div class="ecc-vcard-icon bg-purple-50 text-purple-600">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                            </div>
                            <span class="ecc-vcard-title">{{ $sa['title'] ?? 'SaaS & Subscriptions' }}</span>
                        </div>
                        <span class="ecc-vcard-badge bg-purple-50 text-purple-700">{{ $sa['growth'] ?? '+34.1%' }}</span>
                    </div>

                    <p class="ecc-vcard-gmv">{{ $sa['gmv'] ?? '₹6,32,000' }}</p>
                    <span class="text-[11px] text-slate-400 font-medium">Monthly Recurring Revenue (MRR)</span>

                    <div class="ecc-vcard-grid">
                        <div>
                            <span class="ecc-vcard-kpi-k">{{ $sa['count_label'] ?? 'Active Partners' }}</span>
                            <p class="ecc-vcard-kpi-v">{{ $sa['count'] ?? '142' }}</p>
                        </div>
                        <div>
                            <span class="ecc-vcard-kpi-k">{{ $sa['primary_rate_label'] ?? 'Churn Rate' }}</span>
                            <p class="ecc-vcard-kpi-v text-purple-600">{{ $sa['primary_rate'] ?? '0.6%' }}</p>
                        </div>
                        <div>
                            <span class="ecc-vcard-kpi-k">{{ $sa['active_label'] ?? 'Delivery SLA' }}</span>
                            <p class="ecc-vcard-kpi-v">{{ $sa['active'] ?? '99.94%' }}</p>
                        </div>
                        <div>
                            <span class="ecc-vcard-kpi-k">Partner ARPU</span>
                            <p class="ecc-vcard-kpi-v">{{ $sa['secondary_metric'] ?? 'Avg ₹4,450' }}</p>
                        </div>
                    </div>
                </div>

                <div>
                    {{-- SVG Sparkline --}}
                    <svg class="ecc-spark-svg" viewBox="0 0 200 48">
                        <defs>
                            <linearGradient id="saGrad" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#8b5cf6" stop-opacity="0.3"/>
                                <stop offset="100%" stop-color="#8b5cf6" stop-opacity="0.0"/>
                            </linearGradient>
                        </defs>
                        <path d="M 0,40 Q 30,34 70,28 T 130,18 T 170,10 T 200,3 L 200,48 L 0,48 Z" fill="url(#saGrad)"/>
                        <path d="M 0,40 Q 30,34 70,28 T 130,18 T 170,10 T 200,3" fill="none" stroke="#8b5cf6" stroke-width="2.2" stroke-linecap="round"/>
                    </svg>

                    <div class="ecc-vcard-tag">
                        <span class="w-1.5 h-1.5 rounded-full bg-purple-500"></span>
                        <span class="truncate font-medium">{{ $sa['highlight'] ?? 'Growth tier upgrades +18% this month' }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── 4. Analytics Trajectory & Conversion Funnel (12-Col: 8 + 4) --}}
        <div class="ecc-grid-12">
            {{-- Multi-Stream Revenue Trajectory (8 Columns) --}}
            <div class="ecc-col-8 ecc-card">
                <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
                    <div>
                        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-700 flex items-center gap-2 m-0">
                            <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                            Multi-Stream Revenue Trajectory
                        </h2>
                        <span class="text-xs text-slate-400 font-medium">Daily consolidated GMV across Events, Sports Venues, and SaaS</span>
                    </div>

                    <div class="flex items-center gap-3 text-xs font-semibold text-slate-500">
                        <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm bg-indigo-500"></span> Events</span>
                        <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm bg-emerald-500"></span> Sports Venues</span>
                        <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm bg-purple-500"></span> SaaS CRM</span>
                    </div>
                </div>

                {{-- Interactive Multi-Series Vector Graph --}}
                <div class="w-full h-56 relative pt-2">
                    <svg class="w-full h-44 overflow-visible" viewBox="0 0 640 160" preserveAspectRatio="none">
                        {{-- Subtle Grid Lines --}}
                        <line x1="0" y1="40" x2="640" y2="40" stroke="#f1f5f9" stroke-width="1"/>
                        <line x1="0" y1="80" x2="640" y2="80" stroke="#f1f5f9" stroke-width="1"/>
                        <line x1="0" y1="120" x2="640" y2="120" stroke="#f1f5f9" stroke-width="1"/>

                        {{-- Area 1: SaaS (Purple) --}}
                        <path d="M 0,148 Q 100,146 200,144 T 400,140 T 540,135 T 640,130 L 640,160 L 0,160 Z" fill="#ede9fe" opacity="0.45"/>
                        <path d="M 0,148 Q 100,146 200,144 T 400,140 T 540,135 T 640,130" fill="none" stroke="#8b5cf6" stroke-width="2"/>

                        {{-- Area 2: Venues (Emerald) --}}
                        <path d="M 0,125 Q 100,118 200,114 T 400,102 T 540,84 T 640,86 L 640,160 L 0,160 Z" fill="#d1fae5" opacity="0.35"/>
                        <path d="M 0,125 Q 100,118 200,114 T 400,102 T 540,84 T 640,86" fill="none" stroke="#10b981" stroke-width="2"/>

                        {{-- Area 3: Events (Indigo) --}}
                        <path d="M 0,95 Q 100,82 200,88 T 400,64 T 540,32 T 640,42 L 640,160 L 0,160 Z" fill="#e0e7ff" opacity="0.35"/>
                        <path d="M 0,95 Q 100,82 200,88 T 400,64 T 540,32 T 640,42" fill="none" stroke="#6366f1" stroke-width="2.5"/>

                        {{-- Data Point Indicators --}}
                        <circle cx="540" cy="32" r="4.5" fill="#6366f1" stroke="#ffffff" stroke-width="2"/>
                        <circle cx="540" cy="84" r="4" fill="#10b981" stroke="#ffffff" stroke-width="2"/>
                        <circle cx="540" cy="135" r="4" fill="#8b5cf6" stroke="#ffffff" stroke-width="2"/>
                    </svg>

                    {{-- Horizontal Day Axis Labels --}}
                    <div class="flex justify-between text-[11px] font-semibold text-slate-400 mt-2 px-1">
                        @foreach(($trajectory['labels'] ?? ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']) as $idx => $day)
                            <div class="text-center">
                                <span>{{ $day }}</span>
                                <div class="text-[10px] text-slate-500 font-bold">{{ ($trajectory['totals'] ?? [])[$idx] ?? '' }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Conversion Funnel & Unit Economics (4 Columns) --}}
            <div class="ecc-col-4 ecc-card flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-700 m-0">Conversion Funnel</h2>
                        <span class="text-[11px] font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200">97.2% Checkout</span>
                    </div>

                    {{-- Progressive Funnel Steps --}}
                    <div class="flex flex-col gap-2">
                        @foreach(($conversionFunnel['steps'] ?? []) as $step)
                            <div class="p-2 rounded-lg bg-slate-50 border border-slate-100 flex items-center justify-between text-xs">
                                <div>
                                    <span class="font-semibold text-slate-700">{{ $step['name'] }}</span>
                                    <div class="text-[10.5px] text-slate-400 font-medium">{{ $step['drop'] }}</div>
                                </div>
                                <span class="font-extrabold text-slate-900 tabular-nums">{{ $step['value'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Unit Economics Distribution --}}
                <div class="pt-3 mt-3 border-t border-slate-100">
                    <span class="text-[10.5px] font-bold uppercase tracking-wider text-slate-400 block mb-2">Platform Unit Economics</span>
                    <div class="flex h-2.5 rounded-full overflow-hidden gap-0.5 mb-2">
                        <div style="width: 85.5%; background: #3b82f6;" title="Partner Share 85.5%"></div>
                        <div style="width: 12.0%; background: #10b981;" title="Haraan Take 12.0%"></div>
                        <div style="width: 2.5%; background: #94a3b8;" title="Gateway & Infra 2.5%"></div>
                    </div>
                    <div class="flex items-center justify-between text-[11px] text-slate-600">
                        <span>Partner: <strong>85.5%</strong></span>
                        <span>Commission: <strong class="text-emerald-600">12.0%</strong></span>
                        <span>Infra: <strong>2.5%</strong></span>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── 5. Live Operations Stream & Geo Heatmaps (12-Col: 7 + 5) --}}
        <div class="ecc-grid-12">
            {{-- Live Operations Stream (7 Columns) --}}
            <div class="ecc-col-7 ecc-card">
                <div class="flex items-center justify-between mb-3 flex-wrap gap-2">
                    <div>
                        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-700 flex items-center gap-2 m-0">
                            <span class="ecc-pulse"><i class="ping"></i><i></i></span>
                            Live Operations Stream
                        </h2>
                        <span class="text-xs text-slate-400 font-medium">Real-time transaction capture and partner activity</span>
                    </div>

                    {{-- Stream Filter Pills --}}
                    <div class="flex items-center gap-1.5 text-xs">
                        <button type="button" wire:click="setStreamFilter('all')" class="px-2.5 py-1 rounded-md text-[11px] font-bold {{ $activeStreamFilter === 'all' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600' }}">All</button>
                        <button type="button" wire:click="setStreamFilter('venues')" class="px-2.5 py-1 rounded-md text-[11px] font-bold {{ $activeStreamFilter === 'venues' ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-600' }}">Venues</button>
                        <button type="button" wire:click="setStreamFilter('events')" class="px-2.5 py-1 rounded-md text-[11px] font-bold {{ $activeStreamFilter === 'events' ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600' }}">Events</button>
                        <button type="button" wire:click="setStreamFilter('saas')" class="px-2.5 py-1 rounded-md text-[11px] font-bold {{ $activeStreamFilter === 'saas' ? 'bg-purple-600 text-white' : 'bg-slate-100 text-slate-600' }}">SaaS</button>
                    </div>
                </div>

                {{-- Activity Feed List --}}
                <div class="ecc-stream-wrap">
                    @foreach($liveStream as $item)
                        @if($activeStreamFilter === 'all' || $activeStreamFilter === $item['type'])
                            <div class="ecc-stream-item">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center text-slate-600 flex-none">
                                        <x-filament::icon :icon="$item['icon']" class="w-4 h-4" />
                                    </div>
                                    <div>
                                        <p class="ecc-stream-title m-0">{{ $item['title'] }}</p>
                                        <div class="ecc-stream-meta">
                                            <span>{{ $item['user'] }}</span>
                                            <span>·</span>
                                            <span>{{ $item['time'] }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs font-bold text-slate-900 m-0 tabular-nums">{{ $item['amount'] }}</p>
                                    <span class="inline-block text-[10px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-200">{{ $item['status'] }}</span>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>

            {{-- Geo Heatmaps & Regional Velocity (5 Columns) --}}
            <div class="ecc-col-5 ecc-card">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-700 m-0">Regional Hub Velocity</h2>
                        <span class="text-xs text-slate-400 font-medium">City-level GMV contribution and venue density</span>
                    </div>
                    <span class="text-xs font-bold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-full">5 Hubs Live</span>
                </div>

                <div class="flex flex-col gap-1.5 mt-2">
                    @foreach($geoVelocity as $hub)
                        <div class="ecc-geo-row">
                            <div class="flex items-center gap-2.5">
                                <span class="w-6 h-6 rounded-md bg-slate-100 text-slate-700 text-xs font-bold flex items-center justify-center">{{ $hub['state'] }}</span>
                                <div>
                                    <p class="text-xs font-bold text-slate-800 m-0 flex items-center gap-1.5">
                                        {{ $hub['city'] }}
                                        @if($hub['lead'])
                                            <span class="text-[9.5px] font-bold text-emerald-700 bg-emerald-50 px-1.5 py-0.2 rounded border border-emerald-200">HQ Lead</span>
                                        @endif
                                    </p>
                                    <span class="text-[10.5px] text-slate-400">{{ $hub['venues'] }} partner arenas</span>
                                </div>
                            </div>

                            <div class="text-right">
                                <p class="text-xs font-extrabold text-slate-900 m-0 tabular-nums">{{ $hub['gmv'] }}</p>
                                <span class="text-[10.5px] font-semibold text-emerald-600">{{ $hub['growth'] }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ── 6. AI Predictive Insights & Optimization Engine (3 Cards) --}}
        <div>
            <div class="ecc-sec-head">
                <h2 class="ecc-sec-title">AI Predictive Recommendations &amp; Autonomous Telemetry</h2>
                <span class="ecc-sec-desc">Neural analysis of real-time supply, demand elasticity, and system anomalies</span>
            </div>

            <div class="ecc-grid-12">
                @foreach($aiInsights as $insight)
                    <div class="ecc-col-4 ecc-ai-card">
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-[10.5px] font-bold uppercase tracking-wider text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-md border border-indigo-100">
                                    {{ $insight['type'] }}
                                </span>
                                <span class="text-[10.5px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200">
                                    {{ $insight['badge'] }}
                                </span>
                            </div>
                            <h3 class="text-sm font-bold text-slate-900 m-0">{{ $insight['title'] }}</h3>
                            <p class="text-xs text-slate-500 mt-2 leading-relaxed">{{ $insight['desc'] }}</p>
                        </div>

                        <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-800">{{ $insight['metric'] }}</span>
                            <button
                                type="button"
                                wire:click="applyAiAction('{{ $insight['key'] }}')"
                                class="text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 px-3 py-1.5 rounded-lg shadow-sm transition-all"
                            >
                                {{ $insight['action_label'] }}
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- ── 7. Capital & Settlements (Financial Overview Ledger) ─── --}}
        <section aria-labelledby="cc-sec-money">
            <div class="ecc-sec-head">
                <h2 id="cc-sec-money" class="ecc-sec-title">Capital &amp; Settlements</h2>
                <span class="ecc-sec-desc">Platform escrow, fee capture, gateway deductions and partner settlement obligations</span>
            </div>

            <div class="ecc-card">
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                    <div>
                        <span class="text-[11px] font-semibold text-slate-500 block">Gross Inflow</span>
                        <p class="text-lg font-extrabold text-slate-900 m-0 mt-1 tabular-nums">{{ $financialLedger['gross_collected'] ?? '₹48.92L' }}</p>
                        <span class="text-[10.5px] text-slate-400">Total collections</span>
                    </div>
                    <div>
                        <span class="text-[11px] font-semibold text-slate-500 block">Platform Commission</span>
                        <p class="text-lg font-extrabold text-emerald-600 m-0 mt-1 tabular-nums">{{ $financialLedger['platform_commission'] ?? '₹5.87L' }}</p>
                        <span class="text-[10.5px] text-emerald-600 font-semibold">12.0% Take Rate</span>
                    </div>
                    <div>
                        <span class="text-[11px] font-semibold text-slate-500 block">Gateway Deductions</span>
                        <p class="text-lg font-extrabold text-slate-700 m-0 mt-1 tabular-nums">{{ $financialLedger['gateway_deductions'] ?? '₹1.07L' }}</p>
                        <span class="text-[10.5px] text-slate-400">Razorpay fees (2.2%)</span>
                    </div>
                    <div>
                        <span class="text-[11px] font-semibold text-slate-500 block">Payouts Scheduled</span>
                        <p class="text-lg font-extrabold text-amber-600 m-0 mt-1 tabular-nums">{{ $financialLedger['partner_payouts_due'] ?? '₹41.09L' }}</p>
                        <span class="text-[10.5px] text-amber-600 font-semibold">Awaiting settlement</span>
                    </div>
                    <div>
                        <span class="text-[11px] font-semibold text-slate-500 block">Escrow Reserve</span>
                        <p class="text-lg font-extrabold text-indigo-600 m-0 mt-1 tabular-nums">{{ $financialLedger['escrow_reserve'] ?? '₹20.54L' }}</p>
                        <span class="text-[10.5px] text-indigo-600 font-semibold">Protected capital</span>
                    </div>
                    <div>
                        <span class="text-[11px] font-semibold text-slate-500 block">Net Settled Today</span>
                        <p class="text-lg font-extrabold text-emerald-600 m-0 mt-1 tabular-nums">{{ $financialLedger['net_settled_today'] ?? '₹1.42L' }}</p>
                        <span class="text-[10.5px] text-slate-400">Bank clearing OK</span>
                    </div>
                </div>
            </div>
        </section>

        {{-- ── 8. Enterprise SLA Health Status ──────────────────────── --}}
        <div class="ecc-card !py-3">
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div class="text-xs font-bold text-slate-600 uppercase tracking-wider flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                    Enterprise Infrastructure SLA
                </div>

                <div class="flex items-center gap-4 flex-wrap">
                    @foreach($systemHealth as $node)
                        <div class="flex items-center gap-2 text-xs">
                            <span class="w-1.5 h-1.5 rounded-full {{ $node['ok'] ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
                            <span class="font-bold text-slate-800">{{ $node['name'] }}</span>
                            <span class="text-slate-400 text-[11px] font-mono">({{ $node['ping'] }})</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ── 9. Operational Radar & Activity Timeline (12-Col: 6 + 6) ─ --}}
        <div class="ecc-grid-12">
            {{-- Operational Radar (6 Columns) --}}
            <section class="ecc-col-6" aria-labelledby="cc-sec-radar">
                <div class="ecc-sec-head">
                    <h2 id="cc-sec-radar" class="ecc-sec-title">Operational Radar</h2>
                    <span class="ecc-sec-desc">Items requiring immediate action or triage</span>
                </div>

                <div class="flex flex-col gap-2">
                    @foreach($radar as $item)
                        <a
                            href="{{ $item['url'] }}"
                            class="ecc-card !p-3 flex items-center justify-between gap-3 text-decoration-none"
                        >
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center text-slate-700 flex-none">
                                    <x-filament::icon :icon="$item['icon']" class="w-4 h-4" />
                                </div>
                                <div>
                                    <p class="text-xs font-bold text-slate-900 m-0">{{ $item['title'] }}</p>
                                    <span class="text-[11px] text-slate-500">{{ $item['sub'] }}</span>
                                </div>
                            </div>

                            @if($item['count'] > 0)
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-extrabold text-amber-600 bg-amber-50 px-2 py-0.5 rounded-md border border-amber-200">{{ $item['count'] }}</span>
                                    <span class="text-xs font-bold text-indigo-600 hover:text-indigo-800">Inspect &rarr;</span>
                                </div>
                            @else
                                <span class="text-xs font-semibold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-md border border-emerald-200">Clear</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </section>

            {{-- Executive Activity Timeline (6 Columns) --}}
            <div class="ecc-col-6">
                <div class="ecc-sec-head">
                    <h2 class="ecc-sec-title">Audit &amp; Operations Timeline</h2>
                    <span class="ecc-sec-desc">Immutable ledger of executive and automated actions</span>
                </div>

                <div class="ecc-card flex flex-col gap-3">
                    @foreach($activityTimeline as $act)
                        <div class="flex items-start gap-3 text-xs pb-2 border-b border-slate-100 last:border-0 last:pb-0">
                            <span class="font-mono font-bold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded flex-none">{{ $act['time'] }}</span>
                            <div class="flex-1">
                                <p class="text-slate-800 font-semibold m-0">{{ $act['title'] }}</p>
                                <span class="text-[10.5px] text-slate-400">Actor: {{ $act['role'] }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ── 10. Universal Search Spotlight Modal (Alpine.js) ──────── --}}
        <div
            x-show="spotlightOpen"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="ecc-modal-backdrop"
            style="display: none;"
            @click.self="spotlightOpen = false"
            @keydown.escape.window="spotlightOpen = false"
        >
            <div
                class="ecc-modal-box"
                @click.stop
            >
                <div class="p-3 border-b border-slate-200 flex items-center gap-3 bg-slate-50">
                    <svg class="w-4 h-4 text-slate-400 flex-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.35-4.35"></path></svg>
                    <input
                        x-ref="spotlightInput"
                        type="text"
                        wire:model.live.debounce.150ms="searchQuery"
                        placeholder="Search events, arenas, partners, settlement batches, transactions..."
                        class="w-full bg-transparent border-0 text-sm font-semibold text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-0"
                    />
                    <button type="button" @click="spotlightOpen = false" class="text-slate-400 hover:text-slate-600">
                        <span class="ecc-kbd">ESC</span>
                    </button>
                </div>

                <div class="max-h-80 overflow-y-auto p-2">
                    @if(!empty($searchResults))
                        <div class="text-[10.5px] font-bold uppercase tracking-wider text-slate-400 px-3 py-1">Matching Enterprise Records</div>
                        @foreach($searchResults as $res)
                            <a
                                href="{{ $res['url'] }}"
                                class="flex items-center justify-between p-2.5 rounded-lg hover:bg-slate-100 transition-colors text-decoration-none"
                            >
                                <div>
                                    <p class="text-xs font-bold text-slate-800 m-0">{{ $res['title'] }}</p>
                                    <span class="text-[10.5px] text-slate-400">{{ $res['category'] }}</span>
                                </div>
                                <span class="text-[11px] font-semibold text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded border border-indigo-200">
                                    {{ $res['badge'] }}
                                </span>
                            </a>
                        @endforeach
                    @elseif(!empty($searchQuery))
                        <div class="p-6 text-center text-xs text-slate-400">
                            No records found matching "{{ $searchQuery }}". Try searching for "Bangalore", "Turf", "Razorpay", or "WhatsApp".
                        </div>
                    @else
                        <div class="p-4 text-xs text-slate-400">
                            <span class="font-bold text-slate-600 block mb-1">Quick Shortcuts</span>
                            <div class="flex gap-2 flex-wrap mt-2">
                                <button type="button" wire:click="$set('searchQuery', 'Bangalore')" class="px-2 py-1 rounded bg-slate-100 text-slate-700 font-semibold text-[11px]">Bangalore Events</button>
                                <button type="button" wire:click="$set('searchQuery', 'Turf')" class="px-2 py-1 rounded bg-slate-100 text-slate-700 font-semibold text-[11px]">Sports Venues</button>
                                <button type="button" wire:click="$set('searchQuery', 'Partner')" class="px-2 py-1 rounded bg-slate-100 text-slate-700 font-semibold text-[11px]">SaaS Plans</button>
                                <button type="button" wire:click="$set('searchQuery', 'Razorpay')" class="px-2 py-1 rounded bg-slate-100 text-slate-700 font-semibold text-[11px]">Gateway Ledger</button>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>


