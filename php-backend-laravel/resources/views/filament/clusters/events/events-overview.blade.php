<x-filament-panels::page>
    <style>
        .ecc-root {
            --ecc-font: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            --ecc-bg: #ffffff;
            --ecc-card-bg: #ffffff;
            --ecc-card-subtle: #f8fafc;
            --ecc-border: #e2e8f0;
            --ecc-border-subtle: #f1f5f9;
            --ecc-text-main: #0f172a;
            --ecc-text-muted: #64748b;
            --ecc-text-subtle: #94a3b8;
            --ecc-primary: #2563eb;
            --ecc-primary-hover: #1d4ed8;
            --ecc-primary-subtle: rgba(37, 99, 235, 0.08);
            --ecc-emerald: #10b981;
            --ecc-emerald-subtle: rgba(16, 185, 129, 0.1);
            --ecc-amber: #f59e0b;
            --ecc-amber-subtle: rgba(245, 158, 11, 0.1);
            --ecc-indigo: #6366f1;
            --ecc-shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.04);
            --ecc-shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
            --ecc-shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.06), 0 4px 6px -4px rgba(0, 0, 0, 0.05);
            font-family: var(--ecc-font);
            color: var(--ecc-text-main);
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .dark .ecc-root {
            --ecc-bg: #0b0f19;
            --ecc-card-bg: #111827;
            --ecc-card-subtle: #1e293b;
            --ecc-border: #1f2937;
            --ecc-border-subtle: #2d3748;
            --ecc-text-main: #f8fafc;
            --ecc-text-muted: #94a3b8;
            --ecc-text-subtle: #64748b;
            --ecc-primary-subtle: rgba(37, 99, 235, 0.2);
            --ecc-emerald-subtle: rgba(16, 185, 129, 0.2);
            --ecc-amber-subtle: rgba(245, 158, 11, 0.2);
            --ecc-shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.3);
            --ecc-shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.4);
            --ecc-shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.5);
        }

        /* Top Command Header */
        .ecc-header {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 20px 24px;
            background: var(--ecc-card-bg);
            border: 1px solid var(--ecc-border);
            border-radius: 16px;
            box-shadow: var(--ecc-shadow-sm);
        }

        .ecc-brand {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .ecc-pulse-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            border-radius: 9999px;
            background: var(--ecc-emerald-subtle);
            color: var(--ecc-emerald);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .ecc-pulse-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--ecc-emerald);
            box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.6);
            animation: eccPulse 2s infinite;
        }

        @keyframes eccPulse {
            0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.6); }
            70% { box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); }
            100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }

        .ecc-title {
            font-size: 20px;
            font-weight: 800;
            color: var(--ecc-text-main);
            letter-spacing: -0.02em;
            margin: 0;
            line-height: 1.2;
        }

        .ecc-subtitle {
            font-size: 13px;
            color: var(--ecc-text-muted);
            margin-top: 2px;
        }

        .ecc-header-actions {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .ecc-range-picker {
            display: inline-flex;
            background: var(--ecc-card-subtle);
            border: 1px solid var(--ecc-border);
            border-radius: 10px;
            padding: 3px;
            gap: 2px;
        }

        .ecc-range-btn {
            border: none;
            background: transparent;
            color: var(--ecc-text-muted);
            font-size: 12px;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .ecc-range-btn:hover {
            color: var(--ecc-text-main);
        }

        .ecc-range-btn.active {
            background: var(--ecc-card-bg);
            color: var(--ecc-primary);
            font-weight: 700;
            box-shadow: var(--ecc-shadow-sm);
        }

        .ecc-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 650;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.15s ease;
            border: 1px solid var(--ecc-border);
            background: var(--ecc-card-bg);
            color: var(--ecc-text-main);
        }

        .ecc-btn:hover {
            border-color: var(--ecc-text-muted);
            transform: translateY(-1px);
        }

        .ecc-btn--primary {
            background: var(--ecc-primary);
            border-color: var(--ecc-primary);
            color: #ffffff !important;
            box-shadow: 0 2px 4px rgba(37, 99, 235, 0.2);
        }

        .ecc-btn--primary:hover {
            background: var(--ecc-primary-hover);
            border-color: var(--ecc-primary-hover);
        }

        /* 12-Column Layout System */
        .ecc-grid-12 {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 20px;
        }

        .col-span-12 { grid-column: span 12; }
        .col-span-8 { grid-column: span 8; }
        .col-span-7 { grid-column: span 7; }
        .col-span-6 { grid-column: span 6; }
        .col-span-5 { grid-column: span 5; }
        .col-span-4 { grid-column: span 4; }
        .col-span-3 { grid-column: span 3; }

        @media (max-width: 1080px) {
            .col-span-8, .col-span-7, .col-span-5, .col-span-4, .col-span-3 {
                grid-column: span 12;
            }
        }

        /* Executive Hero Cards */
        .ecc-card {
            background: var(--ecc-card-bg);
            border: 1px solid var(--ecc-border);
            border-radius: 16px;
            padding: 24px;
            box-shadow: var(--ecc-shadow-sm);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }

        .ecc-card:hover {
            box-shadow: var(--ecc-shadow-md);
        }

        .ecc-hero-revenue {
            grid-column: span 5;
            background: linear-gradient(135deg, var(--ecc-card-bg) 0%, var(--ecc-card-subtle) 100%);
            border-left: 4px solid var(--ecc-primary);
        }

        @media (max-width: 1080px) {
            .ecc-hero-revenue { grid-column: span 12; }
        }

        .ecc-kpi-subhead {
            font-size: 12px;
            font-weight: 750;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--ecc-text-muted);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .ecc-kpi-num {
            font-size: 34px;
            font-weight: 900;
            letter-spacing: -0.03em;
            line-height: 1.1;
            margin: 10px 0 6px 0;
            color: var(--ecc-text-main);
            font-feature-settings: "cv02", "cv03", "cv04", "cv11";
        }

        .ecc-growth-chip {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 12px;
            font-weight: 750;
            color: var(--ecc-emerald);
            background: var(--ecc-emerald-subtle);
            padding: 3px 8px;
            border-radius: 6px;
        }

        .ecc-sparkline-wrap {
            margin: 16px 0 12px 0;
            height: 48px;
            width: 100%;
        }

        .ecc-sparkline-wrap svg {
            width: 100%;
            height: 100%;
            overflow: visible;
        }

        .ecc-metric-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            padding-top: 14px;
            border-top: 1px solid var(--ecc-border-subtle);
            margin-top: 8px;
        }

        .ecc-metric-cell-label {
            font-size: 11px;
            color: var(--ecc-text-muted);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .ecc-metric-cell-val {
            font-size: 16px;
            font-weight: 800;
            color: var(--ecc-text-main);
            margin-top: 2px;
        }

        /* Auxiliary Hero Cards */
        .ecc-hero-stat {
            grid-column: span 7;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }

        @media (max-width: 1080px) {
            .ecc-hero-stat { grid-column: span 12; }
        }

        @media (max-width: 640px) {
            .ecc-hero-stat { grid-template-columns: 1fr; }
        }

        .ecc-mini-card {
            background: var(--ecc-card-bg);
            border: 1px solid var(--ecc-border);
            border-radius: 16px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: var(--ecc-shadow-sm);
        }

        .ecc-mini-num {
            font-size: 28px;
            font-weight: 850;
            color: var(--ecc-text-main);
            margin: 8px 0 4px 0;
            letter-spacing: -0.02em;
        }

        .ecc-progress-bar-wrap {
            width: 100%;
            height: 6px;
            background: var(--ecc-border-subtle);
            border-radius: 9999px;
            overflow: hidden;
            margin-top: 10px;
        }

        .ecc-progress-bar-fill {
            height: 100%;
            border-radius: 9999px;
            transition: width 0.6s ease;
        }

        /* Section Headings */
        .ecc-section-title {
            font-size: 16px;
            font-weight: 800;
            color: var(--ecc-text-main);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .ecc-section-sub {
            font-size: 12.5px;
            color: var(--ecc-text-muted);
            margin-top: 2px;
        }

        /* Revenue Trajectory Graph (CSS SVG Stacked Column Simulation) */
        .ecc-chart-card {
            background: var(--ecc-card-bg);
            border: 1px solid var(--ecc-border);
            border-radius: 16px;
            padding: 24px;
            box-shadow: var(--ecc-shadow-sm);
        }

        .ecc-chart-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .ecc-legend {
            display: flex;
            align-items: center;
            gap: 14px;
            font-size: 12px;
            font-weight: 650;
            color: var(--ecc-text-muted);
        }

        .ecc-legend-dot {
            width: 10px;
            height: 10px;
            border-radius: 3px;
            display: inline-block;
        }

        .ecc-trajectory-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 14px;
            height: 220px;
            align-items: end;
            padding-bottom: 30px;
            position: relative;
            border-bottom: 1px solid var(--ecc-border);
        }

        .ecc-traj-col {
            display: flex;
            flex-direction: column;
            align-items: center;
            height: 100%;
            justify-content: flex-end;
            position: relative;
        }

        .ecc-traj-stack {
            width: 70%;
            max-width: 44px;
            display: flex;
            flex-direction: column-reverse;
            border-radius: 8px 8px 0 0;
            overflow: hidden;
            background: var(--ecc-card-subtle);
            box-shadow: inset 0 0 0 1px rgba(0,0,0,0.04);
            transition: transform 0.2s ease, filter 0.2s ease;
        }

        .ecc-traj-stack:hover {
            transform: scaleY(1.03);
            filter: brightness(1.06);
        }

        .ecc-traj-segment {
            width: 100%;
            transition: height 0.4s ease;
        }

        .ecc-traj-label {
            position: absolute;
            bottom: -24px;
            font-size: 12px;
            font-weight: 700;
            color: var(--ecc-text-muted);
        }

        .ecc-traj-val {
            font-size: 11px;
            font-weight: 750;
            color: var(--ecc-text-main);
            margin-bottom: 6px;
        }

        /* Top Regional Cities */
        .ecc-city-list {
            display: flex;
            flex-direction: column;
            gap: 14px;
            margin-top: 16px;
        }

        .ecc-city-item {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .ecc-city-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 13.5px;
        }

        .ecc-city-name {
            font-weight: 700;
            color: var(--ecc-text-main);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .ecc-city-gmv {
            font-weight: 800;
            color: var(--ecc-text-main);
        }

        .ecc-city-meta {
            font-size: 11.5px;
            color: var(--ecc-text-muted);
        }

        /* Top Venues Table */
        .ecc-venue-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 16px;
        }

        .ecc-venue-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 14px;
            border-radius: 12px;
            background: var(--ecc-card-subtle);
            border: 1px solid var(--ecc-border-subtle);
            transition: all 0.15s ease;
        }

        .ecc-venue-card:hover {
            border-color: var(--ecc-border);
            transform: translateX(2px);
        }

        .ecc-venue-title {
            font-size: 13.5px;
            font-weight: 750;
            color: var(--ecc-text-main);
        }

        .ecc-venue-type {
            font-size: 11.5px;
            color: var(--ecc-text-muted);
            margin-top: 2px;
        }

        .ecc-venue-badge {
            font-size: 11px;
            font-weight: 750;
            padding: 4px 8px;
            border-radius: 6px;
            background: var(--ecc-emerald-subtle);
            color: var(--ecc-emerald);
        }

        /* AI Executive Insights */
        .ecc-ai-card {
            background: var(--ecc-card-bg);
            border: 1px solid var(--ecc-border);
            border-left-width: 4px;
            border-left-color: var(--ecc-primary);
            border-radius: 14px;
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            box-shadow: var(--ecc-shadow-sm);
        }

        .ecc-ai-card--alert {
            border-left-color: var(--ecc-amber);
        }

        .ecc-ai-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .ecc-ai-badge {
            font-size: 10.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding: 3px 8px;
            border-radius: 6px;
            background: var(--ecc-primary-subtle);
            color: var(--ecc-primary);
        }

        .ecc-ai-card--alert .ecc-ai-badge {
            background: var(--ecc-amber-subtle);
            color: var(--ecc-amber);
        }

        .ecc-ai-metric {
            font-size: 12px;
            font-weight: 800;
            color: var(--ecc-text-main);
        }

        .ecc-ai-title {
            font-size: 14px;
            font-weight: 750;
            color: var(--ecc-text-main);
            margin: 0;
        }

        .ecc-ai-desc {
            font-size: 12.5px;
            color: var(--ecc-text-muted);
            line-height: 1.45;
            margin: 0;
        }

        .ecc-ai-btn {
            align-self: flex-start;
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 700;
            border-radius: 8px;
            border: 1px solid var(--ecc-border);
            background: var(--ecc-card-subtle);
            color: var(--ecc-text-main);
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .ecc-ai-btn:hover {
            background: var(--ecc-primary);
            color: #ffffff;
            border-color: var(--ecc-primary);
        }

        /* Recent Transactions Table */
        .ecc-table-wrap {
            overflow-x: auto;
            margin-top: 14px;
        }

        .ecc-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            text-align: left;
        }

        .ecc-table th {
            padding: 10px 12px;
            font-size: 11px;
            font-weight: 750;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--ecc-text-muted);
            border-bottom: 1px solid var(--ecc-border);
            background: var(--ecc-card-subtle);
        }

        .ecc-table td {
            padding: 12px;
            border-bottom: 1px solid var(--ecc-border-subtle);
            color: var(--ecc-text-main);
        }

        .ecc-table tr:hover td {
            background: var(--ecc-card-subtle);
        }

        .ecc-status-pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 750;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            background: var(--ecc-emerald-subtle);
            color: var(--ecc-emerald);
        }
    </style>

    <div class="ecc-root">
        {{-- 1. Executive Macro Header Bar --}}
        <div class="ecc-header">
            <div class="ecc-brand">
                <div>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <h1 class="ecc-title">Events Command Center</h1>
                        <span class="ecc-pulse-badge">
                            <span class="ecc-pulse-dot"></span>
                            Live Portfolio Stream
                        </span>
                    </div>
                    <div class="ecc-subtitle">
                        Macro ticketing performance, real-time gate velocity, and AI yield management across all active cities.
                    </div>
                </div>
            </div>

            <div class="ecc-header-actions">
                <div class="ecc-range-picker">
                    @foreach (['today' => 'Today', '7d' => '7D', '30d' => '30D', '90d' => '90D', 'all' => 'All'] as $rKey => $rLabel)
                        <button type="button"
                                wire:click="setRange('{{ $rKey }}')"
                                class="ecc-range-btn {{ $range === $rKey ? 'active' : '' }}">
                            {{ $rLabel }}
                        </button>
                    @endforeach
                </div>

                <a href="{{ \App\Filament\Clusters\Events\Pages\TicketCheckIn::getUrl() }}" class="ecc-btn">
                    <x-filament::icon icon="heroicon-m-qr-code" style="width:16px;height:16px;color:var(--ecc-primary);" />
                    Gate Console
                </a>

                <a href="{{ \App\Filament\Resources\Events\EventResource::getUrl('create') }}" class="ecc-btn ecc-btn--primary">
                    <x-filament::icon icon="heroicon-m-plus" style="width:16px;height:16px;" />
                    Create Event
                </a>
            </div>
        </div>

        {{-- 2. Executive Hero: 12-Column High-Impact KPI Grid --}}
        <div class="ecc-grid-12">
            {{-- Big Confident Gross Revenue Card (Span 5) --}}
            <div class="ecc-card ecc-hero-revenue">
                <div>
                    <div class="ecc-kpi-subhead">
                        <span>Total Event Gross Revenue</span>
                        <span class="ecc-growth-chip">
                            <x-filament::icon icon="heroicon-m-arrow-trending-up" style="width:14px;height:14px;" />
                            {{ $executiveHero['growth'] }}
                        </span>
                    </div>
                    <div class="ecc-kpi-num">{{ $executiveHero['revenue'] }}</div>
                    <div style="font-size: 12px; color: var(--ecc-text-muted); font-weight: 600;">
                        {{ $executiveHero['growth_label'] }}
                    </div>
                </div>

                {{-- Clean SVG Sparkline --}}
                <div class="ecc-sparkline-wrap">
                    <svg viewBox="0 0 300 48" preserveAspectRatio="none">
                        <defs>
                            <linearGradient id="eccSparkGrad" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#2563eb" stop-opacity="0.3"/>
                                <stop offset="100%" stop-color="#2563eb" stop-opacity="0.0"/>
                            </linearGradient>
                        </defs>
                        <path d="M 0 40 Q 30 35, 60 28 T 120 30 T 180 18 T 240 12 T 300 4 L 300 48 L 0 48 Z" fill="url(#eccSparkGrad)" />
                        <path d="M 0 40 Q 30 35, 60 28 T 120 30 T 180 18 T 240 12 T 300 4" fill="none" stroke="#2563eb" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </div>

                <div class="ecc-metric-row">
                    <div>
                        <div class="ecc-metric-cell-label">Today's Run-Rate</div>
                        <div class="ecc-metric-cell-val">{{ $executiveHero['today_revenue'] }}</div>
                    </div>
                    <div>
                        <div class="ecc-metric-cell-label">Avg Ticket Value</div>
                        <div class="ecc-metric-cell-val">{{ $executiveHero['avg_ticket'] }}</div>
                    </div>
                </div>
            </div>

            {{-- 3 Auxiliary Macro KPIs (Span 7) --}}
            <div class="ecc-hero-stat">
                {{-- Active Events --}}
                <div class="ecc-mini-card">
                    <div>
                        <div class="ecc-kpi-subhead">Active Events</div>
                        <div class="ecc-mini-num">{{ $executiveHero['active_events'] }}</div>
                        <div style="font-size: 12px; color: var(--ecc-text-muted);">Published & Selling Now</div>
                    </div>
                    <div>
                        <div class="ecc-progress-bar-wrap">
                            <div class="ecc-progress-bar-fill" style="width: 82%; background: #3b82f6;"></div>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 11px; color: var(--ecc-text-subtle); margin-top: 6px;">
                            <span>Portfolio Capacity</span>
                            <span style="font-weight: 700; color: var(--ecc-text-main);">82% Active</span>
                        </div>
                    </div>
                </div>

                {{-- Tickets Sold --}}
                <div class="ecc-mini-card">
                    <div>
                        <div class="ecc-kpi-subhead">Tickets Sold</div>
                        <div class="ecc-mini-num">{{ $executiveHero['tickets_sold'] }}</div>
                        <div style="font-size: 12px; color: var(--ecc-text-muted);">Total Passes Issued</div>
                    </div>
                    <div>
                        <div class="ecc-progress-bar-wrap">
                            <div class="ecc-progress-bar-fill" style="width: 76%; background: #10b981;"></div>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 11px; color: var(--ecc-text-subtle); margin-top: 6px;">
                            <span>Sell-Through</span>
                            <span style="font-weight: 700; color: var(--ecc-text-main);">76% Total</span>
                        </div>
                    </div>
                </div>

                {{-- Check-in Rate --}}
                <div class="ecc-mini-card">
                    <div>
                        <div class="ecc-kpi-subhead">Check-In Velocity</div>
                        <div class="ecc-mini-num">{{ $executiveHero['checkin_rate'] }}</div>
                        <div style="font-size: 12px; color: var(--ecc-text-muted);">Arrival Turnout Rate</div>
                    </div>
                    <div>
                        <div class="ecc-progress-bar-wrap">
                            <div class="ecc-progress-bar-fill" style="width: 82.4%; background: #6366f1;"></div>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 11px; color: var(--ecc-text-subtle); margin-top: 6px;">
                            <span>Gate Precision</span>
                            <span style="font-weight: 700; color: var(--ecc-text-main);">99.4% Valid</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- 3. Deep Analytics: 12-Column Trajectory & Regional Intelligence --}}
        <div class="ecc-grid-12">
            {{-- Left Column: Trajectory Chart & Live Stream (Span 7) --}}
            <div class="col-span-7" style="display: flex; flex-direction: column; gap: 20px;">
                {{-- Revenue & Attendance Trajectory --}}
                <div class="ecc-chart-card">
                    <div class="ecc-chart-header">
                        <div>
                            <h2 class="ecc-section-title">
                                <x-filament::icon icon="heroicon-m-chart-bar-square" style="width:18px;height:18px;color:var(--ecc-primary);" />
                                Revenue & Attendance Trajectory
                            </h2>
                            <div class="ecc-section-sub">Weekly category-level gross ticketing breakdown</div>
                        </div>

                        <div class="ecc-legend">
                            <span><span class="ecc-legend-dot" style="background:#2563eb;"></span> Music</span>
                            <span><span class="ecc-legend-dot" style="background:#10b981;"></span> Sports</span>
                            <span><span class="ecc-legend-dot" style="background:#f59e0b;"></span> Tech</span>
                            <span><span class="ecc-legend-dot" style="background:#8b5cf6;"></span> Arts</span>
                        </div>
                    </div>

                    {{-- Trajectory Grid Simulation --}}
                    <div class="ecc-trajectory-grid">
                        @foreach ($revenueTrends['labels'] as $idx => $label)
                            @php
                                $mH = ($revenueTrends['music'][$idx] / 600000) * 100;
                                $sH = ($revenueTrends['sports'][$idx] / 600000) * 100;
                                $tH = ($revenueTrends['tech'][$idx] / 600000) * 100;
                                $aH = ($revenueTrends['arts'][$idx] / 600000) * 100;
                            @endphp
                            <div class="ecc-traj-col">
                                <span class="ecc-traj-val">{{ $revenueTrends['totals'][$idx] }}</span>
                                <div class="ecc-traj-stack" style="height: {{ min(100, max(25, $mH + $sH + $tH + $aH)) }}%;">
                                    <div class="ecc-traj-segment" style="height: 38%; background: #2563eb;" title="Music"></div>
                                    <div class="ecc-traj-segment" style="height: 28%; background: #10b981;" title="Sports"></div>
                                    <div class="ecc-traj-segment" style="height: 20%; background: #f59e0b;" title="Tech"></div>
                                    <div class="ecc-traj-segment" style="height: 14%; background: #8b5cf6;" title="Arts"></div>
                                </div>
                                <span class="ecc-traj-label">{{ $label }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Recent Live Ticketing Transactions --}}
                <div class="ecc-card">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <h2 class="ecc-section-title">
                                <x-filament::icon icon="heroicon-m-bolt" style="width:18px;height:18px;color:#10b981;" />
                                Live Ticketing Stream
                            </h2>
                            <div class="ecc-section-sub">Real-time buyer checkout ledger</div>
                        </div>
                        <a href="{{ \App\Filament\Resources\Bookings\BookingResource::getUrl('index') }}" style="font-size: 12px; font-weight: 700; color: var(--ecc-primary); text-decoration: none;">
                            View All Bookings &rarr;
                        </a>
                    </div>

                    <div class="ecc-table-wrap">
                        <table class="ecc-table">
                            <thead>
                                <tr>
                                    <th>Order</th>
                                    <th>Event</th>
                                    <th>Attendee</th>
                                    <th>Tickets</th>
                                    <th>Gross</th>
                                    <th>Time</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($recentTransactions as $tx)
                                    <tr>
                                        <td style="font-family: monospace; font-weight: 700; color: var(--ecc-primary);">{{ $tx['id'] }}</td>
                                        <td style="font-weight: 650;">{{ $tx['event'] }}</td>
                                        <td style="color: var(--ecc-text-muted);">{{ $tx['buyer'] }}</td>
                                        <td style="font-weight: 700;">{{ $tx['qty'] }}</td>
                                        <td style="font-weight: 800; color: var(--ecc-text-main);">{{ $tx['amount'] }}</td>
                                        <td style="font-size: 11.5px; color: var(--ecc-text-subtle);">{{ $tx['time'] }}</td>
                                        <td>
                                            <span class="ecc-status-pill">
                                                <x-filament::icon icon="heroicon-m-check" style="width:12px;height:12px;" />
                                                {{ $tx['status'] }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Right Column: Regional Cities, Top Venues & AI Insights (Span 5) --}}
            <div class="col-span-5" style="display: flex; flex-direction: column; gap: 20px;">
                {{-- AI Executive Insights & Yield Actions --}}
                <div class="ecc-card" style="background: linear-gradient(180deg, var(--ecc-card-bg) 0%, var(--ecc-card-subtle) 100%);">
                    <div>
                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <h2 class="ecc-section-title">
                                <x-filament::icon icon="heroicon-m-sparkles" style="width:18px;height:18px;color:#8b5cf6;" />
                                AI Yield & Operations Intelligence
                            </h2>
                            <span style="font-size: 11px; font-weight: 700; color: #8b5cf6; text-transform: uppercase;">Autonomous Engine</span>
                        </div>
                        <div class="ecc-section-sub">Algorithmic pricing surges and gate load triage</div>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 12px; margin-top: 14px;">
                        @foreach ($aiInsights as $insight)
                            <div class="ecc-ai-card {{ str_contains(strtolower($insight['badge']), 'sellout') ? 'ecc-ai-card--alert' : '' }}">
                                <div class="ecc-ai-header">
                                    <span class="ecc-ai-badge">{{ $insight['badge'] }}</span>
                                    <span class="ecc-ai-metric">{{ $insight['metric'] }}</span>
                                </div>
                                <h3 class="ecc-ai-title">{{ $insight['title'] }}</h3>
                                <p class="ecc-ai-desc">{{ $insight['desc'] }}</p>
                                <button type="button" wire:click="applyAiOptimization('{{ $insight['key'] }}')" class="ecc-ai-btn">
                                    {{ $insight['action'] }} &rarr;
                                </button>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Regional City Velocity --}}
                <div class="ecc-card">
                    <div>
                        <h2 class="ecc-section-title">
                            <x-filament::icon icon="heroicon-m-map-pin" style="width:18px;height:18px;color:var(--ecc-primary);" />
                            Top Regional Markets
                        </h2>
                        <div class="ecc-section-sub">GMV distribution across key metropolitan hubs</div>
                    </div>

                    <div class="ecc-city-list">
                        @foreach ($topCities as $c)
                            <div class="ecc-city-item">
                                <div class="ecc-city-row">
                                    <span class="ecc-city-name">
                                        {{ $c['city'] }}
                                        @if ($c['lead'])
                                            <span style="font-size: 10px; font-weight: 800; background: var(--ecc-primary-subtle); color: var(--ecc-primary); padding: 2px 6px; border-radius: 4px;">Primary</span>
                                        @endif
                                    </span>
                                    <span class="ecc-city-gmv">{{ $c['gmv'] }}</span>
                                </div>
                                <div class="ecc-progress-bar-wrap">
                                    <div class="ecc-progress-bar-fill" style="width: {{ $c['pct'] }}%; background: {{ $c['lead'] ? '#2563eb' : '#64748b' }};"></div>
                                </div>
                                <div style="display: flex; justify-content: space-between; font-size: 11px; color: var(--ecc-text-muted);">
                                    <span>{{ $c['events'] }} active events</span>
                                    <span style="font-weight: 700; color: var(--ecc-emerald);">{{ $c['growth'] }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Top Performing Venues --}}
                <div class="ecc-card">
                    <div>
                        <h2 class="ecc-section-title">
                            <x-filament::icon icon="heroicon-m-building-office-2" style="width:18px;height:18px;color:#10b981;" />
                            Top Performing Venues
                        </h2>
                        <div class="ecc-section-sub">Highest grossing venue partners by occupancy</div>
                    </div>

                    <div class="ecc-venue-list">
                        @foreach ($topVenues as $venue)
                            <div class="ecc-venue-card">
                                <div>
                                    <div class="ecc-venue-title">{{ $venue['name'] }}</div>
                                    <div class="ecc-venue-type">{{ $venue['type'] }} &middot; {{ $venue['events'] }} events hosted</div>
                                </div>
                                <div style="text-align: right;">
                                    <div style="font-size: 14px; font-weight: 800; color: var(--ecc-text-main);">{{ $venue['gmv'] }}</div>
                                    <span class="ecc-venue-badge">{{ $venue['occupancy'] }} Occ.</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
