@php
    $t = $this->getTelemetry();
@endphp

<x-filament-widgets::widget>
    <style>
        .bkh-root {
            --bkh-bg: #ffffff;
            --bkh-card-subtle: #f8fafc;
            --bkh-border: #e2e8f0;
            --bkh-text-main: #0f172a;
            --bkh-text-muted: #64748b;
            --bkh-primary: #2563eb;
            --bkh-emerald: #10b981;
            --bkh-emerald-subtle: rgba(16, 185, 129, 0.1);
            --bkh-shadow: 0 1px 3px rgba(11, 18, 32, 0.05), 0 0 0 1px rgba(226, 232, 240, 0.8);
            background: var(--bkh-bg);
            border-radius: 16px;
            box-shadow: var(--bkh-shadow);
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 16px;
            margin-bottom: 8px;
            font-family: inherit;
        }

        .dark .bkh-root {
            --bkh-bg: #111827;
            --bkh-card-subtle: #1e293b;
            --bkh-border: #1f2937;
            --bkh-text-main: #f8fafc;
            --bkh-text-muted: #94a3b8;
            --bkh-shadow: 0 0 0 1px rgba(255, 255, 255, 0.08);
        }

        .bkh-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            padding-bottom: 14px;
            border-bottom: 1px solid var(--bkh-border);
        }

        .bkh-title-group {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .bkh-title {
            font-size: 15px;
            font-weight: 800;
            color: var(--bkh-text-main);
            letter-spacing: -0.01em;
            margin: 0;
        }

        .bkh-badge {
            font-size: 11px;
            font-weight: 750;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 3px 8px;
            border-radius: 6px;
            background: var(--bkh-emerald-subtle);
            color: var(--bkh-emerald);
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .bkh-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .bkh-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 650;
            color: var(--bkh-text-muted);
            background: var(--bkh-card-subtle);
            border: 1px solid var(--bkh-border);
            text-decoration: none;
            transition: all 0.15s ease;
        }

        .bkh-chip:hover {
            color: var(--bkh-text-main);
            border-color: var(--bkh-primary);
        }

        /* 4-Pillar Grid */
        .bkh-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
        }

        @media (max-width: 1024px) {
            .bkh-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 640px) {
            .bkh-grid { grid-template-columns: 1fr; }
        }

        .bkh-cell {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 14px 16px;
            border-radius: 12px;
            background: var(--bkh-card-subtle);
            border: 1px solid var(--bkh-border);
        }

        .bkh-cell--hero {
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.05) 0%, var(--bkh-card-subtle) 100%);
            border-color: rgba(37, 99, 235, 0.2);
        }

        .bkh-label {
            font-size: 11px;
            font-weight: 750;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--bkh-text-muted);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .bkh-val {
            font-size: 26px;
            font-weight: 850;
            letter-spacing: -0.02em;
            line-height: 1.1;
            margin: 8px 0 4px 0;
            color: var(--bkh-text-main);
            font-variant-numeric: tabular-nums;
        }

        .bkh-sub {
            font-size: 11.5px;
            color: var(--bkh-text-muted);
            font-weight: 550;
        }

        .bkh-sub b {
            font-weight: 750;
            color: var(--bkh-text-main);
        }

        /* Acquisition Multi-Segment Bar */
        .bkh-stacked-bar {
            display: flex;
            height: 6px;
            border-radius: 999px;
            overflow: hidden;
            margin: 10px 0 8px 0;
            background: var(--bkh-border);
        }

        .bkh-legend-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 10.5px;
            font-weight: 650;
            color: var(--bkh-text-muted);
        }
    </style>

    <div class="bkh-root">
        {{-- Header Bar --}}
        <div class="bkh-top">
            <div class="bkh-title-group">
                <h3 class="bkh-title">Bookings Executive Intelligence</h3>
                <span class="bkh-badge">
                    <x-filament::icon icon="heroicon-m-check-badge" style="width:14px;height:14px;" />
                    Funnel Health: High Velocity
                </span>
            </div>

            <div class="bkh-actions">
                <a href="{{ \App\Filament\Clusters\Events\Pages\EventsOverview::getUrl() }}" class="bkh-chip">
                    <x-filament::icon icon="heroicon-m-chart-bar" style="width:14px;height:14px;color:var(--bkh-primary);" />
                    Events Overview
                </a>
                <a href="{{ \App\Filament\Clusters\Events\Pages\TicketCheckIn::getUrl() }}" class="bkh-chip">
                    <x-filament::icon icon="heroicon-m-qr-code" style="width:14px;height:14px;color:var(--bkh-emerald);" />
                    Check-in Console
                </a>
            </div>
        </div>

        {{-- 4 Executive Telemetry Pillars --}}
        <div class="bkh-grid">
            {{-- Pillar 1: Conversion Rate --}}
            <div class="bkh-cell bkh-cell--hero">
                <div class="bkh-label">
                    <span>Checkout Conversion</span>
                    <x-filament::icon icon="heroicon-m-bolt" style="width:14px;height:14px;color:var(--bkh-primary);" />
                </div>
                <div class="bkh-val" style="color:var(--bkh-primary);">{{ $t['conversion_rate'] }}</div>
                <div class="bkh-sub"><b style="color:var(--bkh-emerald);">{{ $t['conversion_growth'] }}</b> &middot; Cart completion</div>
            </div>

            {{-- Pillar 2: Cancellations & Churn --}}
            <div class="bkh-cell">
                <div class="bkh-label">
                    <span>Cancellations & Refunds</span>
                    <x-filament::icon icon="heroicon-m-shield-check" style="width:14px;height:14px;color:#10b981;" />
                </div>
                <div class="bkh-val">{{ $t['cancellation_rate'] }}</div>
                <div class="bkh-sub"><b>{{ $t['cancellation_count'] }}</b> orders &middot; {{ $t['cancellation_sub'] }}</div>
            </div>

            {{-- Pillar 3: Average Booking Value (AOV) --}}
            <div class="bkh-cell">
                <div class="bkh-label">
                    <span>Average Order Value (AOV)</span>
                    <x-filament::icon icon="heroicon-m-currency-rupee" style="width:14px;height:14px;color:#f59e0b;" />
                </div>
                <div class="bkh-val">{{ $t['aov'] }}</div>
                <div class="bkh-sub"><b>{{ $t['avg_tickets'] }}</b> tickets/order &middot; {{ $t['gmv'] }} GMV</div>
            </div>

            {{-- Pillar 4: Acquisition Sources --}}
            <div class="bkh-cell">
                <div class="bkh-label">
                    <span>Acquisition Channels</span>
                    <x-filament::icon icon="heroicon-m-device-phone-mobile" style="width:14px;height:14px;color:#6366f1;" />
                </div>
                <div class="bkh-stacked-bar">
                    @foreach ($t['sources'] as $src)
                        <div style="width: {{ $src['pct'] }}%; background: {{ $src['color'] }};" title="{{ $src['name'] }}: {{ $src['pct'] }}%"></div>
                    @endforeach
                </div>
                <div class="bkh-legend-row">
                    <span>App <b>58%</b></span>
                    <span>Web <b>32%</b></span>
                    <span>Direct <b>10%</b></span>
                </div>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
