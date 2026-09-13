@php
    $s = $this->getSummary();
@endphp

<x-filament-widgets::widget>
    <style>
        .ech-root {
            --ech-bg: #ffffff;
            --ech-card-subtle: #f8fafc;
            --ech-border: #e2e8f0;
            --ech-text-main: #0f172a;
            --ech-text-muted: #64748b;
            --ech-text-subtle: #94a3b8;
            --ech-primary: #2563eb;
            --ech-emerald: #10b981;
            --ech-emerald-subtle: rgba(16, 185, 129, 0.1);
            --ech-shadow: 0 1px 3px rgba(11, 18, 32, 0.05), 0 0 0 1px rgba(226, 232, 240, 0.8);
            background: var(--ech-bg);
            border-radius: 16px;
            box-shadow: var(--ech-shadow);
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 16px;
            margin-bottom: 8px;
        }

        .dark .ech-root {
            --ech-bg: #111827;
            --ech-card-subtle: #1e293b;
            --ech-border: #1f2937;
            --ech-text-main: #f8fafc;
            --ech-text-muted: #94a3b8;
            --ech-text-subtle: #64748b;
            --ech-shadow: 0 0 0 1px rgba(255, 255, 255, 0.08);
        }

        .ech-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            padding-bottom: 14px;
            border-bottom: 1px solid var(--ech-border);
        }

        .ech-title-group {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .ech-title {
            font-size: 15px;
            font-weight: 800;
            color: var(--ech-text-main);
            letter-spacing: -0.01em;
            margin: 0;
        }

        .ech-badge {
            font-size: 11px;
            font-weight: 750;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 3px 8px;
            border-radius: 6px;
            background: var(--ech-emerald-subtle);
            color: var(--ech-emerald);
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .ech-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .ech-action-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 650;
            color: var(--ech-text-muted);
            background: var(--ech-card-subtle);
            border: 1px solid var(--ech-border);
            text-decoration: none;
            transition: all 0.15s ease;
        }

        .ech-action-chip:hover {
            color: var(--ech-text-main);
            border-color: var(--ech-primary);
        }

        /* 5-Pillar Executive Grid */
        .ech-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 16px;
        }

        @media (max-width: 1024px) {
            .ech-grid { grid-template-columns: repeat(3, 1fr); }
        }

        @media (max-width: 640px) {
            .ech-grid { grid-template-columns: 1fr; }
        }

        .ech-cell {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 12px 14px;
            border-radius: 12px;
            background: var(--ech-card-subtle);
            border: 1px solid var(--ech-border);
        }

        .ech-cell--hero {
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.05) 0%, var(--ech-card-subtle) 100%);
            border-color: rgba(37, 99, 235, 0.2);
        }

        .ech-label {
            font-size: 11px;
            font-weight: 750;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--ech-text-muted);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .ech-val {
            font-size: 26px;
            font-weight: 850;
            letter-spacing: -0.02em;
            line-height: 1.1;
            margin: 8px 0 4px 0;
            color: var(--ech-text-main);
            font-variant-numeric: tabular-nums;
        }

        .ech-sub {
            font-size: 11.5px;
            color: var(--ech-text-muted);
            font-weight: 550;
        }

        .ech-sub b {
            font-weight: 750;
            color: var(--ech-text-main);
        }

        .ech-meter {
            height: 5px;
            border-radius: 999px;
            background: var(--ech-border);
            overflow: hidden;
            margin-top: 8px;
        }

        .ech-meter span {
            display: block;
            height: 100%;
            border-radius: 999px;
        }
    </style>

    <div class="ech-root">
        {{-- Catalog Command Strip Header --}}
        <div class="ech-top">
            <div class="ech-title-group">
                <h3 class="ech-title">Events Catalog Intelligence</h3>
                <span class="ech-badge">
                    <x-filament::icon icon="heroicon-m-check-badge" style="width:14px;height:14px;" />
                    Portfolio Health: {{ $s['healthScore'] }}/100
                </span>
            </div>

            <div class="ech-actions">
                <a href="{{ \App\Filament\Clusters\Events\Pages\EventsOverview::getUrl() }}" class="ech-action-chip">
                    <x-filament::icon icon="heroicon-m-chart-bar" style="width:14px;height:14px;color:var(--ech-primary);" />
                    Executive Overview
                </a>
                <a href="{{ \App\Filament\Clusters\Events\Pages\TicketCheckIn::getUrl() }}" class="ech-action-chip">
                    <x-filament::icon icon="heroicon-m-qr-code" style="width:14px;height:14px;color:var(--ech-emerald);" />
                    Gate Scanner
                </a>
                <a href="{{ \App\Filament\Resources\Events\EventResource::getUrl('create') }}" class="ech-action-chip" style="background:var(--ech-primary);color:#fff;border-color:var(--ech-primary);">
                    <x-filament::icon icon="heroicon-m-plus" style="width:14px;height:14px;" />
                    New Event
                </a>
            </div>
        </div>

        {{-- 5-Pillar Executive Macro Metrics --}}
        <div class="ech-grid">
            {{-- 1. Catalog Gross Revenue --}}
            <div class="ech-cell ech-cell--hero">
                <div class="ech-label">
                    <span>Catalog Revenue</span>
                    <x-filament::icon icon="heroicon-m-currency-rupee" style="width:14px;height:14px;color:var(--ech-primary);" />
                </div>
                <div class="ech-val" style="color:var(--ech-primary);">{{ $s['revenue'] }}</div>
                <div class="ech-sub">Gross settled ticketing</div>
            </div>

            {{-- 2. Status & Inventory --}}
            <div class="ech-cell">
                <div class="ech-label">
                    <span>Active Status</span>
                    <x-filament::icon icon="heroicon-m-signal" style="width:14px;height:14px;color:var(--ech-emerald);" />
                </div>
                <div class="ech-val">{{ number_format($s['published']) }} <span style="font-size:14px;color:var(--ech-text-muted);font-weight:600;">/ {{ number_format($s['total']) }}</span></div>
                <div class="ech-sub"><b>{{ $s['upcoming'] }}</b> upcoming &middot; {{ $s['draft'] }} drafts</div>
            </div>

            {{-- 3. Capacity & Sell-Through --}}
            <div class="ech-cell">
                <div class="ech-label">
                    <span>Capacity Utilization</span>
                    <x-filament::icon icon="heroicon-m-ticket" style="width:14px;height:14px;color:#f59e0b;" />
                </div>
                <div class="ech-val">{{ $s['fillPct'] }}%</div>
                <div class="ech-meter">
                    <span style="width:{{ min(100, max(4, $s['fillPct'])) }}%; background:linear-gradient(90deg,#f59e0b,#10b981);"></span>
                </div>
                <div class="ech-sub" style="margin-top:6px;"><b>{{ number_format($s['sold']) }}</b> of {{ number_format($s['capacity']) }} slots</div>
            </div>

            {{-- 4. Active Organizers --}}
            <div class="ech-cell">
                <div class="ech-label">
                    <span>Organizers</span>
                    <x-filament::icon icon="heroicon-m-user-group" style="width:14px;height:14px;color:#6366f1;" />
                </div>
                <div class="ech-val">{{ number_format($s['organizers']) }}</div>
                <div class="ech-sub">Hosting partners active</div>
            </div>

            {{-- 5. AI Health Score --}}
            <div class="ech-cell">
                <div class="ech-label">
                    <span>Health Score</span>
                    <x-filament::icon icon="heroicon-m-sparkles" style="width:14px;height:14px;color:var(--ech-emerald);" />
                </div>
                <div class="ech-val" style="color:var(--ech-emerald);">{{ $s['healthScore'] }}</div>
                <div class="ech-sub">Optimal booking pace</div>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
