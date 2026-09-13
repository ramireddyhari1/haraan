<x-filament-panels::page>
    @php
        $metrics = $this->getMetrics();
        $pillars = $this->getPerformancePillars();
        $heatmap = $this->getHourlyHeatmap();
        $cities = $this->getCityBreakdown();
        $aiInsights = $this->getAiOperationalInsights();
        $liveStream = $this->getLiveOperationsStream();
    @endphp

    <div class="space-y-6 -mt-2">
        {{-- ========================================================================= --}}
        {{-- 1. EXECUTIVE HERO COMMAND CENTER                                          --}}
        {{-- ========================================================================= --}}
        <section class="relative overflow-hidden rounded-2xl border border-slate-200/90 bg-white p-6 md:p-8 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            {{-- Emerald Gradient Ambience Glow --}}
            <div class="pointer-events-none absolute -right-24 -top-24 h-96 w-96 rounded-full bg-emerald-500/10 blur-3xl"></div>
            <div class="pointer-events-none absolute -left-20 -bottom-20 h-72 w-72 rounded-full bg-teal-500/5 blur-3xl"></div>

            {{-- Top Glassy Header Bar --}}
            <div class="relative z-10 flex flex-col gap-4 border-b border-slate-100 pb-6 dark:border-slate-800 md:flex-row md:items-center md:justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 shadow-sm border border-emerald-100 dark:bg-emerald-950/50 dark:text-emerald-400 dark:border-emerald-800">
                        <svg class="h-6 w-6 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2.5">
                            <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white md:text-2xl">
                                Game Hub Operations Command
                            </h1>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-ping"></span>
                                Live Telemetry
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Real-time turf occupancy, live match scoring, POS drawer reconciliation & fleet health.
                        </p>
                    </div>
                </div>

                {{-- AI Business Health Score --}}
                <div class="flex items-center gap-3">
                    <div class="rounded-xl border border-emerald-200/80 bg-gradient-to-r from-emerald-50/90 to-teal-50/70 px-4 py-2 text-right shadow-sm dark:border-emerald-800/60 dark:from-emerald-950/30 dark:to-slate-900">
                        <div class="flex items-center gap-2">
                            <div class="text-left">
                                <div class="text-[10px] font-bold tracking-wider uppercase text-emerald-700 dark:text-emerald-400">AI Pulse Health</div>
                                <div class="text-xs font-semibold text-slate-700 dark:text-slate-200">{{ $metrics['health_grade'] }}</div>
                            </div>
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-600 text-base font-black text-white shadow-sm font-mono">
                                {{ $metrics['health_score'] }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Core 12-Column Executive Hero Metrics Grid --}}
            <div class="relative z-10 mt-6 grid grid-cols-1 gap-6 lg:grid-cols-12">
                {{-- Primary Revenue Anchor (Col 1-5) --}}
                <div class="rounded-xl border border-slate-100 bg-slate-50/60 p-5 dark:border-slate-800/80 dark:bg-slate-800/40 lg:col-span-5 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Turf & Court Gross Revenue</span>
                            <span class="inline-flex items-center gap-1 text-xs font-bold text-emerald-600 dark:text-emerald-400">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                                +{{ $metrics['revenue_growth'] }}% MTD
                            </span>
                        </div>
                        <div class="mt-2 flex items-baseline gap-3">
                            <span class="text-3xl md:text-4xl font-black tracking-tight text-slate-900 dark:text-white font-mono">
                                ₹{{ number_format($metrics['today_revenue'], 0) }}
                            </span>
                            <span class="text-xs text-slate-500 font-medium">today</span>
                        </div>
                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            Month-to-date collected: <strong class="text-slate-700 dark:text-slate-200 font-mono">₹{{ number_format($metrics['mtd_revenue'], 0) }}</strong> across all sports venues.
                        </div>
                    </div>

                    {{-- Mini Sparkline Tray --}}
                    <div class="mt-4 pt-3 border-t border-slate-200/60 dark:border-slate-700/60">
                        <div class="flex items-center justify-between text-[11px] text-slate-500 mb-1.5">
                            <span>7-Day Velocity</span>
                            <span class="font-mono font-semibold text-emerald-600">Peak Sat-Sun</span>
                        </div>
                        <div class="h-8 w-full">
                            <svg class="h-full w-full overflow-visible" preserveAspectRatio="none" viewBox="0 0 100 24">
                                <path d="M0,18 L16,15 L32,17 L48,11 L64,13 L80,6 L100,4 L100,24 L0,24 Z" fill="rgba(16,185,129,0.12)"/>
                                <path d="M0,18 L16,15 L32,17 L48,11 L64,13 L80,6 L100,4" fill="none" stroke="#10b981" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                        </div>
                    </div>
                </div>

                {{-- 4 Equal Telemetry Cards (Col 6-12) --}}
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-2 lg:col-span-7">
                    {{-- Occupancy % --}}
                    <div class="rounded-xl border border-slate-100 bg-slate-50/60 p-4 dark:border-slate-800/80 dark:bg-slate-800/40">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Fleet Occupancy</span>
                            <span class="inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
                        </div>
                        <div class="mt-2 text-2xl font-black text-slate-900 dark:text-white font-mono">
                            {{ $metrics['occupancy_rate'] }}%
                        </div>
                        <div class="mt-1 text-[11px] text-slate-500">
                            Peak evening: <span class="font-semibold text-emerald-600">96%</span>
                        </div>
                    </div>

                    {{-- Venues & Courts --}}
                    <div class="rounded-xl border border-slate-100 bg-slate-50/60 p-4 dark:border-slate-800/80 dark:bg-slate-800/40">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Active Venues</span>
                            <span class="text-[10px] font-bold text-emerald-600 font-mono">ONLINE</span>
                        </div>
                        <div class="mt-2 text-2xl font-black text-slate-900 dark:text-white font-mono">
                            {{ $metrics['active_venues'] }} <span class="text-sm font-medium text-slate-400">/ {{ $metrics['total_venues'] }}</span>
                        </div>
                        <div class="mt-1 text-[11px] text-slate-500 font-mono">
                            {{ $metrics['total_courts'] }} total courts & pitches
                        </div>
                    </div>

                    {{-- Live & Scheduled Matches --}}
                    <div class="rounded-xl border border-slate-100 bg-slate-50/60 p-4 dark:border-slate-800/80 dark:bg-slate-800/40">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Today's Matches</span>
                            <span class="inline-flex items-center gap-1 text-[10px] font-bold text-rose-600 bg-rose-50 px-1.5 py-0.5 rounded-full dark:bg-rose-950 dark:text-rose-300">
                                <span class="h-1.5 w-1.5 rounded-full bg-rose-500 animate-ping"></span>
                                {{ $metrics['live_matches'] }} LIVE
                            </span>
                        </div>
                        <div class="mt-2 text-2xl font-black text-slate-900 dark:text-white font-mono">
                            {{ $metrics['scheduled_matches'] }}
                        </div>
                        <div class="mt-1 text-[11px] text-slate-500">
                            Cricket, Football, Badminton
                        </div>
                    </div>

                    {{-- Players & Check-in Rate --}}
                    <div class="rounded-xl border border-slate-100 bg-slate-50/60 p-4 dark:border-slate-800/80 dark:bg-slate-800/40">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Active Players</span>
                            <span class="text-[10px] font-bold text-emerald-600 font-mono">{{ $metrics['checkin_rate'] }}% QR</span>
                        </div>
                        <div class="mt-2 text-2xl font-black text-slate-900 dark:text-white font-mono">
                            {{ number_format($metrics['active_players']) }}
                        </div>
                        <div class="mt-1 text-[11px] text-slate-500">
                            Live on-premise check-ins
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- ========================================================================= --}}
        {{-- 2. THREE EQUAL PERFORMANCE PILLARS                                        --}}
        {{-- ========================================================================= --}}
        <section class="grid grid-cols-1 gap-6 md:grid-cols-3">
            @foreach ($pillars as $pillar)
                <div class="flex flex-col justify-between rounded-2xl border border-slate-200/90 bg-white p-6 shadow-sm transition-all hover:border-emerald-300/80 hover:shadow-md dark:border-slate-800 dark:bg-slate-900">
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 border border-emerald-100 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800">
                                {{ $pillar['badge'] }}
                            </span>
                            <div class="h-2 w-2 rounded-full bg-emerald-500"></div>
                        </div>

                        <h2 class="mt-3 text-base font-bold text-slate-900 dark:text-white">
                            {{ $pillar['title'] }}
                        </h2>

                        <div class="mt-3 flex items-baseline gap-2">
                            <span class="text-3xl font-black tracking-tight text-slate-900 dark:text-white font-mono">
                                {{ $pillar['primary_kpi'] }}
                            </span>
                            <span class="text-xs text-slate-500 font-medium">
                                {{ $pillar['primary_label'] }}
                            </span>
                        </div>

                        {{-- Sparkline Curve --}}
                        <div class="mt-3 h-8 w-full">
                            <svg class="h-full w-full" preserveAspectRatio="none" viewBox="0 0 70 20">
                                @php
                                    $points = $pillar['sparkline'];
                                    $min = min($points);
                                    $max = max($points);
                                    $range = $max - $min > 0 ? $max - $min : 1;
                                    $coords = [];
                                    foreach ($points as $idx => $val) {
                                        $x = ($idx / (count($points) - 1)) * 70;
                                        $y = 18 - (($val - $min) / $range) * 16;
                                        $coords[] = "$x,$y";
                                    }
                                    $polyline = implode(' ', $coords);
                                @endphp
                                <polyline fill="none" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" points="{{ $polyline }}"/>
                            </svg>
                        </div>

                        {{-- Operational Specs --}}
                        <dl class="mt-4 space-y-2 border-t border-slate-100 pt-4 dark:border-slate-800 text-xs">
                            @foreach ($pillar['stats'] as $st)
                                <div class="flex items-center justify-between">
                                    <dt class="text-slate-500 dark:text-slate-400">{{ $st['label'] }}</dt>
                                    <dd class="font-semibold text-slate-800 dark:text-slate-200 font-mono">{{ $st['value'] }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>

                    <div class="mt-5 rounded-lg bg-slate-50 p-2.5 text-[11px] text-slate-600 dark:bg-slate-800/60 dark:text-slate-400 flex items-center gap-2">
                        <svg class="h-4 w-4 text-emerald-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>{{ $pillar['status_text'] }}</span>
                    </div>
                </div>
            @endforeach
        </section>

        {{-- ========================================================================= --}}
        {{-- 3. HOURLY DEMAND HEATMAP & METRO COURT TRAJECTORY                         --}}
        {{-- ========================================================================= --}}
        <section class="grid grid-cols-1 gap-6 lg:grid-cols-12">
            {{-- Hourly Occupancy Heatmap (Col 1-7) --}}
            <div class="rounded-2xl border border-slate-200/90 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900 lg:col-span-7">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4 dark:border-slate-800">
                    <div>
                        <h2 class="text-base font-bold text-slate-900 dark:text-white">Hourly Court Demand Heatmap</h2>
                        <p class="text-xs text-slate-500">Utilization distribution across 24-hour booking cycle</p>
                    </div>
                    <div class="flex items-center gap-2 text-xs">
                        <span class="inline-flex items-center gap-1 text-[11px] text-slate-500"><span class="h-2.5 w-2.5 rounded-sm bg-slate-200 dark:bg-slate-700"></span> Off-Peak</span>
                        <span class="inline-flex items-center gap-1 text-[11px] text-slate-500"><span class="h-2.5 w-2.5 rounded-sm bg-emerald-300"></span> Steady</span>
                        <span class="inline-flex items-center gap-1 text-[11px] text-slate-500"><span class="h-2.5 w-2.5 rounded-sm bg-emerald-600"></span> Prime Floodlight</span>
                    </div>
                </div>

                {{-- Visual Heatmap Grid --}}
                <div class="mt-5 grid grid-cols-6 sm:grid-cols-9 gap-2">
                    @foreach ($heatmap as $h)
                        @php
                            $bgClass = match($h['level']) {
                                'peak' => 'bg-emerald-600 text-white shadow-sm ring-1 ring-emerald-700',
                                'high' => 'bg-emerald-400 text-slate-900',
                                'medium' => 'bg-emerald-200/90 text-slate-800 dark:bg-emerald-800/60 dark:text-slate-100',
                                default => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400',
                            };
                        @endphp
                        <div class="flex flex-col items-center justify-center rounded-xl p-2.5 transition-transform hover:scale-105 {{ $bgClass }}">
                            <span class="text-[10px] font-bold uppercase tracking-wider">{{ $h['label'] }}</span>
                            <span class="text-sm font-black font-mono mt-0.5">{{ $h['utilization'] }}%</span>
                        </div>
                    @endforeach
                </div>

                <div class="mt-4 rounded-xl border border-emerald-100 bg-emerald-50/50 p-3 text-xs text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-950/20 dark:text-emerald-300 flex items-center justify-between">
                    <span class="flex items-center gap-2">
                        <svg class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Prime Floodlight Surge: 6:00 PM – 10:00 PM averaging 95.2% capacity.
                    </span>
                    <a href="{{ url('control/game-hub/game-hub-pricing-rules') }}" class="font-bold underline hover:text-emerald-900">Configure Rules &rarr;</a>
                </div>
            </div>

            {{-- City & Metro Breakdown Table (Col 8-12) --}}
            <div class="rounded-2xl border border-slate-200/90 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900 lg:col-span-5">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4 dark:border-slate-800">
                    <div>
                        <h2 class="text-base font-bold text-slate-900 dark:text-white">Metro Operations Distribution</h2>
                        <p class="text-xs text-slate-500">Fleet capacity & revenue across territories</p>
                    </div>
                    <span class="text-xs font-semibold text-emerald-600">4 Active Metros</span>
                </div>

                <div class="mt-4 divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($cities as $c)
                        <div class="flex items-center justify-between py-3">
                            <div>
                                <div class="text-sm font-bold text-slate-900 dark:text-white">{{ $c['city'] }}</div>
                                <div class="text-[11px] text-slate-500 font-mono">{{ $c['venues'] }} venues · {{ $c['courts'] }} courts</div>
                            </div>
                            <div class="text-right">
                                <div class="text-sm font-black font-mono text-slate-900 dark:text-white">{{ $c['revenue'] }}</div>
                                <div class="flex items-center justify-end gap-1.5 text-[11px]">
                                    <span class="font-mono text-slate-500">{{ $c['occupancy'] }}</span>
                                    <span class="font-semibold text-emerald-600 font-mono">{{ $c['trend'] }}</span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- ========================================================================= --}}
        {{-- 4. AI INSIGHTS & REAL-TIME OPERATIONS STREAM                              --}}
        {{-- ========================================================================= --}}
        <section class="grid grid-cols-1 gap-6 lg:grid-cols-12">
            {{-- AI Operations Intelligence (Col 1-7) --}}
            <div class="rounded-2xl border border-slate-200/90 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900 lg:col-span-7">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4 dark:border-slate-800">
                    <div class="flex items-center gap-2">
                        <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                            </svg>
                        </div>
                        <h2 class="text-base font-bold text-slate-900 dark:text-white">AI Operational Intelligence</h2>
                    </div>
                    <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700 border border-emerald-200">ACTIVE</span>
                </div>

                <div class="mt-4 space-y-3">
                    @foreach ($aiInsights as $ai)
                        <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-4 transition-all hover:border-emerald-200 dark:border-slate-800 dark:bg-slate-800/40">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-xs font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                        <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                                        {{ $ai['title'] }}
                                    </h3>
                                    <p class="mt-1 text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                                        {{ $ai['description'] }}
                                    </p>
                                </div>
                                <a href="{{ $ai['action_url'] }}" class="shrink-0 rounded-lg bg-white px-3 py-1.5 text-xs font-bold text-emerald-600 border border-slate-200 shadow-sm hover:bg-emerald-50 hover:border-emerald-300 dark:bg-slate-800 dark:border-slate-700 dark:text-emerald-400">
                                    {{ $ai['action_label'] }} &rarr;
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Live Operations Stream (Col 8-12) --}}
            <div class="rounded-2xl border border-slate-200/90 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900 lg:col-span-5">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4 dark:border-slate-800">
                    <div class="flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full bg-emerald-500 animate-ping"></span>
                        <h2 class="text-base font-bold text-slate-900 dark:text-white">Live Operations Feed</h2>
                    </div>
                    <span class="text-xs text-slate-400 font-mono">Stream synced</span>
                </div>

                <div class="mt-4 space-y-3">
                    @foreach ($liveStream as $st)
                        <div class="flex items-start gap-3 rounded-lg border border-slate-100 p-3 text-xs dark:border-slate-800 bg-white dark:bg-slate-850">
                            <div class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-emerald-50 text-emerald-600 border border-emerald-100 dark:bg-emerald-950/40 dark:text-emerald-400 dark:border-emerald-800">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center justify-between">
                                    <span class="font-bold text-slate-800 dark:text-slate-200">{{ $st['event'] }}</span>
                                    <span class="text-[10px] text-slate-400 font-mono">{{ $st['time'] }}</span>
                                </div>
                                <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">{{ $st['meta'] }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- ========================================================================= --}}
        {{-- 5. FLOATING QUICK ACTION COMMAND TRAY                                     --}}
        {{-- ========================================================================= --}}
        <section class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-900 text-white dark:bg-white dark:text-slate-900 font-bold text-sm">
                    ⌘
                </div>
                <div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">Fast Operations Toolbar</h3>
                    <p class="text-xs text-slate-500">Quick dispatch for fleet managers, desk supervisors, and tournament coordinators</p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2.5">
                <a href="{{ \App\Filament\Resources\Venues\VenueResource::getUrl('create') }}" class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-3.5 py-2 text-xs font-bold text-white shadow-sm hover:bg-emerald-700 transition">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Add Venue
                </a>
                <a href="{{ \App\Filament\Resources\VenueBlocks\VenueBlockResource::getUrl('create') }}" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 transition">
                    <svg class="h-4 w-4 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                    Block Court-Hour
                </a>
                <a href="{{ url('control/game-hub/game-hub-tournaments') }}" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 transition">
                    <svg class="h-4 w-4 text-purple-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    Launch Tournament
                </a>
                <a href="{{ \App\Filament\Resources\Shifts\ShiftSessionResource::getUrl('index') }}" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 transition">
                    <svg class="h-4 w-4 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    Reconcile Shifts
                </a>
                <a href="{{ \App\Filament\Clusters\GameHub\Pages\Reports::getUrl() }}" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 transition">
                    <svg class="h-4 w-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Export Center
                </a>
            </div>
        </section>
    </div>
</x-filament-panels::page>
