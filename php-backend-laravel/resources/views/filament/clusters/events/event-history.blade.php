<x-filament-panels::page>
    @php
        $histSum = $this->getHistoricalExecutiveSummary();
        $qTrends = $this->getQuarterlyRevenueTrends();
        $cities  = $this->getCityComparisons();
    @endphp

    <style>
        .eh-root {
            --eh-bg: #ffffff;
            --eh-card-subtle: #f8fafc;
            --eh-border: #e2e8f0;
            --eh-border-subtle: #f1f5f9;
            --eh-text-main: #0f172a;
            --eh-text-muted: #64748b;
            --eh-primary: #2563eb;
            --eh-emerald: #10b981;
            --eh-emerald-subtle: rgba(16, 185, 129, 0.1);
            --eh-shadow: 0 1px 3px rgba(11, 18, 32, 0.05), 0 0 0 1px rgba(226, 232, 240, 0.8);
            display: flex;
            flex-direction: column;
            gap: 20px;
            margin-bottom: 24px;
            font-family: inherit;
        }

        .dark .eh-root {
            --eh-bg: #111827;
            --eh-card-subtle: #1e293b;
            --eh-border: #1f2937;
            --eh-border-subtle: #2d3748;
            --eh-text-main: #f8fafc;
            --eh-text-muted: #94a3b8;
            --eh-shadow: 0 0 0 1px rgba(255, 255, 255, 0.08);
        }

        /* 4-Pillar Executive Hero */
        .eh-hero-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
        }

        @media (max-width: 1024px) {
            .eh-hero-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 640px) {
            .eh-hero-grid { grid-template-columns: 1fr; }
        }

        .eh-card {
            background: var(--eh-bg);
            border: 1px solid var(--eh-border);
            border-radius: 16px;
            padding: 20px;
            box-shadow: var(--eh-shadow);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .eh-card--hero {
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.05) 0%, var(--eh-card-subtle) 100%);
            border-color: rgba(37, 99, 235, 0.25);
            border-left: 4px solid var(--eh-primary);
        }

        .eh-kpi-subhead {
            font-size: 11.5px;
            font-weight: 750;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--eh-text-muted);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .eh-kpi-num {
            font-size: 28px;
            font-weight: 850;
            letter-spacing: -0.02em;
            line-height: 1.1;
            margin: 8px 0 4px;
            color: var(--eh-text-main);
            font-variant-numeric: tabular-nums;
        }

        .eh-kpi-sub {
            font-size: 11.5px;
            color: var(--eh-text-muted);
            font-weight: 550;
        }

        .eh-kpi-sub b {
            font-weight: 750;
            color: var(--eh-text-main);
        }

        /* 2-Column Analytics */
        .eh-analytics-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        @media (max-width: 960px) {
            .eh-analytics-grid { grid-template-columns: 1fr; }
        }

        .eh-chart-card {
            background: var(--eh-bg);
            border: 1px solid var(--eh-border);
            border-radius: 16px;
            padding: 20px;
            box-shadow: var(--eh-shadow);
        }

        .eh-sec-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }

        .eh-sec-title {
            font-size: 14px;
            font-weight: 800;
            color: var(--eh-text-main);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .eh-sec-sub {
            font-size: 11.5px;
            color: var(--eh-text-muted);
            margin-top: 2px;
        }

        /* Quarterly Trajectory Bars */
        .eh-q-bars {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 12px;
            height: 160px;
            align-items: end;
            padding-bottom: 24px;
            border-bottom: 1px solid var(--eh-border-subtle);
            position: relative;
        }

        .eh-q-col {
            display: flex;
            flex-direction: column;
            align-items: center;
            height: 100%;
            justify-content: flex-end;
            position: relative;
        }

        .eh-q-bar {
            width: 70%;
            max-width: 36px;
            border-radius: 6px 6px 0 0;
            background: linear-gradient(180deg, #3b82f6 0%, #2563eb 100%);
            transition: transform 0.2s ease, filter 0.2s ease;
        }

        .eh-q-bar:hover {
            transform: scaleY(1.04);
            filter: brightness(1.1);
        }

        .eh-q-val {
            font-size: 11px;
            font-weight: 750;
            color: var(--eh-text-main);
            margin-bottom: 6px;
        }

        .eh-q-lbl {
            position: absolute;
            bottom: -20px;
            font-size: 11px;
            font-weight: 650;
            color: var(--eh-text-muted);
            white-space: nowrap;
        }

        /* City Performance Rows */
        .eh-city-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 12px;
            border-radius: 10px;
            background: var(--eh-card-subtle);
            border: 1px solid var(--eh-border-subtle);
            margin-bottom: 8px;
            transition: transform 0.15s ease;
        }

        .eh-city-row:hover {
            transform: translateX(2px);
        }

        .eh-city-name {
            font-size: 13.5px;
            font-weight: 750;
            color: var(--eh-text-main);
        }

        .eh-city-meta {
            font-size: 11.5px;
            color: var(--eh-text-muted);
            margin-top: 1px;
        }

        .eh-growth-badge {
            font-size: 11px;
            font-weight: 750;
            color: var(--eh-emerald);
            background: var(--eh-emerald-subtle);
            padding: 2px 7px;
            border-radius: 5px;
        }
    </style>

    <div class="eh-root">
        {{-- Historical Executive Hero Strip --}}
        <div class="eh-hero-grid">
            {{-- Lifetime Gross Revenue --}}
            <div class="eh-card eh-card--hero">
                <div>
                    <div class="eh-kpi-subhead">
                        <span>Lifetime Event GMV</span>
                        <x-filament::icon icon="heroicon-m-currency-rupee" style="width:15px;height:15px;color:var(--eh-primary);" />
                    </div>
                    <div class="eh-kpi-num" style="color:var(--eh-primary);">{{ $histSum['lifetime_gmv'] }}</div>
                </div>
                <div class="eh-kpi-sub"><b style="color:var(--eh-emerald);">{{ $histSum['lifetime_gmv_sub'] }}</b></div>
            </div>

            {{-- Lifetime Attendees --}}
            <div class="eh-card">
                <div>
                    <div class="eh-kpi-subhead">
                        <span>Lifetime Turnout</span>
                        <x-filament::icon icon="heroicon-m-user-group" style="width:15px;height:15px;color:#10b981;" />
                    </div>
                    <div class="eh-kpi-num">{{ $histSum['past_attendees'] }}</div>
                </div>
                <div class="eh-kpi-sub">Verified check-in arrivals</div>
            </div>

            {{-- Historical Capacity Realized --}}
            <div class="eh-card">
                <div>
                    <div class="eh-kpi-subhead">
                        <span>Capacity Realized</span>
                        <x-filament::icon icon="heroicon-m-chart-pie" style="width:15px;height:15px;color:#f59e0b;" />
                    </div>
                    <div class="eh-kpi-num">{{ $histSum['capacity_achieved'] }}</div>
                </div>
                <div class="eh-kpi-sub">Average arena utilization</div>
            </div>

            {{-- Total Conducted Events --}}
            <div class="eh-card">
                <div>
                    <div class="eh-kpi-subhead">
                        <span>Conducted Events</span>
                        <x-filament::icon icon="heroicon-m-check-badge" style="width:15px;height:15px;color:#6366f1;" />
                    </div>
                    <div class="eh-kpi-num">{{ $histSum['past_events'] }}</div>
                </div>
                <div class="eh-kpi-sub">Across 4 major metro hubs</div>
            </div>
        </div>

        {{-- Deep Historical Trajectory & Regional Comparison Grid --}}
        <div class="eh-analytics-grid">
            {{-- Quarterly Revenue Trends --}}
            <div class="eh-chart-card">
                <div class="eh-sec-header">
                    <div>
                        <h3 class="eh-sec-title">
                            <x-filament::icon icon="heroicon-m-arrow-trending-up" style="width:16px;height:16px;color:var(--eh-primary);" />
                            Quarterly Revenue Trajectory
                        </h3>
                        <div class="eh-sec-sub">Trailing 5-quarter portfolio GMV expansion</div>
                    </div>
                </div>

                <div class="eh-q-bars">
                    @foreach ($qTrends as $qt)
                        <div class="eh-q-col">
                            <span class="eh-q-val">{{ $qt['revenue'] }}</span>
                            <div class="eh-q-bar" style="height: {{ $qt['pct'] }}%;"></div>
                            <span class="eh-q-lbl">{{ $qt['quarter'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- City-Wise Comparisons --}}
            <div class="eh-chart-card">
                <div class="eh-sec-header">
                    <div>
                        <h3 class="eh-sec-title">
                            <x-filament::icon icon="heroicon-m-map-pin" style="width:16px;height:16px;color:var(--eh-emerald);" />
                            City-Wise Historical Performance
                        </h3>
                        <div class="eh-sec-sub">Regional breakdown of historical events and yield</div>
                    </div>
                </div>

                <div>
                    @foreach ($cities as $cty)
                        <div class="eh-city-row">
                            <div>
                                <div class="eh-city-name">{{ $cty['city'] }}</div>
                                <div class="eh-city-meta">{{ $cty['events'] }} events &middot; {{ $cty['fill'] }} avg fill</div>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-size: 13.5px; font-weight: 800; color: var(--eh-text-main);">{{ $cty['gmv'] }}</div>
                                <span class="eh-growth-badge">{{ $cty['yoy'] }} YoY</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- Interactive Historical Ledger --}}
    {{ $this->table }}
</x-filament-panels::page>
