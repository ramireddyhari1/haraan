@php
    $t = $this->getTelemetry();
@endphp

<x-filament-widgets::widget>
    <style>
        .ven-hero-root {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 1px 3px rgba(11, 18, 32, 0.05), 0 0 0 1px rgba(226, 232, 240, 0.8);
            padding: 22px;
            display: flex;
            flex-direction: column;
            gap: 18px;
            margin-bottom: 12px;
            font-family: inherit;
        }
        .dark .ven-hero-root {
            background: #0f172a;
            box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.08);
        }
        .ven-stat-card {
            background: #f8fafc;
            border: 1px solid #f1f5f9;
            border-radius: 12px;
            padding: 16px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .dark .ven-stat-card {
            background: #1e293b;
            border-color: #334155;
        }
    </style>

    <div class="ven-hero-root relative overflow-hidden">
        {{-- Emerald Ambient Glow --}}
        <div class="pointer-events-none absolute -right-20 -top-20 h-64 w-64 rounded-full bg-emerald-500/10 blur-3xl"></div>
        <div class="pointer-events-none absolute -left-16 -bottom-16 h-56 w-56 rounded-full bg-teal-500/5 blur-3xl"></div>

        {{-- Glassy Header --}}
        <div class="relative z-10 flex flex-col gap-4 border-b border-slate-100 pb-4 dark:border-slate-800 md:flex-row md:items-center md:justify-between">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 border border-emerald-100 dark:bg-emerald-950/50 dark:text-emerald-400 dark:border-emerald-800">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                </div>
                <div>
                    <div class="flex items-center gap-2.5">
                        <h2 class="text-lg font-bold tracking-tight text-slate-900 dark:text-white">
                            Venues Fleet & Court Utilization
                        </h2>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            Live Fleet Telemetry
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Multi-location turf health scores, court availability matrix, maintenance windows, and dynamic surge pricing.
                    </p>
                </div>
            </div>

            {{-- Fleet Health Score & Actions --}}
            <div class="flex items-center gap-3">
                <div class="flex items-center gap-3 rounded-xl border border-emerald-200/80 bg-gradient-to-r from-emerald-50/90 to-teal-50/70 px-3.5 py-2 shadow-sm dark:border-emerald-800/60 dark:from-emerald-950/30 dark:to-slate-900">
                    <div class="text-left">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">Fleet Health</div>
                        <div class="text-xs font-semibold text-slate-700 dark:text-slate-200">{{ $t['health_grade'] }}</div>
                    </div>
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-600 text-sm font-black text-white font-mono shadow-sm">
                        {{ $t['health_score'] }}
                    </div>
                </div>
            </div>
        </div>

        {{-- 12-Column Executive Hero Grid --}}
        <div class="relative z-10 grid grid-cols-1 gap-4 lg:grid-cols-12">
            {{-- Col 1-4: Fleet Inventory & Court Status --}}
            <div class="ven-stat-card lg:col-span-4">
                <div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Venues & Arenas</span>
                        <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400">100% Operational</span>
                    </div>
                    <div class="mt-2 flex items-baseline gap-3">
                        <span class="text-3xl font-black tracking-tight text-slate-900 dark:text-white font-mono">
                            {{ $t['active_venues'] }}
                        </span>
                        <span class="text-sm font-medium text-slate-500 dark:text-slate-400">
                            locations · <span class="font-bold text-slate-900 dark:text-white font-mono">{{ $t['total_courts'] }}</span> total courts
                        </span>
                    </div>
                </div>

                <div class="mt-4 space-y-2 border-t border-slate-200/60 pt-3 dark:border-slate-700/60">
                    <div class="flex items-center justify-between text-xs">
                        <span class="flex items-center gap-1.5 text-slate-600 dark:text-slate-300">
                            <span class="h-2 w-2 rounded-full bg-emerald-500"></span> Fully Operational
                        </span>
                        <span class="font-mono font-bold text-slate-900 dark:text-white">{{ $t['maintenance']['operational'] }} Courts</span>
                    </div>
                    <div class="flex items-center justify-between text-xs">
                        <span class="flex items-center gap-1.5 text-slate-600 dark:text-slate-300">
                            <span class="h-2 w-2 rounded-full bg-amber-500"></span> Scheduled Maintenance
                        </span>
                        <span class="font-mono font-bold text-amber-600 dark:text-amber-400">{{ $t['maintenance']['in_maintenance'] }} Courts</span>
                    </div>
                    <div class="flex items-center justify-between text-xs">
                        <span class="flex items-center gap-1.5 text-slate-600 dark:text-slate-300">
                            <span class="h-2 w-2 rounded-full bg-slate-400"></span> Off-Sale
                        </span>
                        <span class="font-mono font-bold text-slate-600 dark:text-slate-400">{{ $t['maintenance']['off_sale'] }} Courts</span>
                    </div>
                </div>
            </div>

            {{-- Col 5-8: Court Utilization & Dynamic Pricing --}}
            <div class="ven-stat-card lg:col-span-4">
                <div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Court Utilization Matrix</span>
                        <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-bold text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800">
                            +4.2% vs last wk
                        </span>
                    </div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-3xl font-black tracking-tight text-emerald-600 dark:text-emerald-400 font-mono">
                            {{ $t['court_utilization'] }}
                        </span>
                        <span class="text-xs text-slate-500 dark:text-slate-400">7-day rolling avg</span>
                    </div>
                </div>

                {{-- Dynamic Surge Rules --}}
                <div class="mt-4 rounded-lg border border-slate-200/80 bg-white p-3 dark:border-slate-700/80 dark:bg-slate-900/60">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-600 dark:text-slate-300">Dynamic Pricing Engine</span>
                        <span class="rounded bg-emerald-600 px-1.5 py-0.5 text-[10px] font-black text-white font-mono">ACTIVE</span>
                    </div>
                    <div class="mt-2 grid grid-cols-2 gap-2 text-xs">
                        <div>
                            <div class="text-[10px] text-slate-500 dark:text-slate-400">Base Hourly Rate</div>
                            <div class="font-mono font-bold text-slate-900 dark:text-white">{{ $t['pricing']['avg_rate'] }}</div>
                        </div>
                        <div>
                            <div class="text-[10px] text-slate-500 dark:text-slate-400">Prime Surge Window</div>
                            <div class="font-mono font-bold text-emerald-600 dark:text-emerald-400">{{ $t['pricing']['peak_multiplier'] }}</div>
                        </div>
                    </div>
                    <div class="mt-1 text-[10px] text-slate-500 dark:text-slate-400">
                        Window: {{ $t['pricing']['surge_window'] }} · {{ $t['pricing']['weekend_multiplier'] }}
                    </div>
                </div>
            </div>

            {{-- Col 9-12: 7-Day Slot Availability Calendar --}}
            <div class="ven-stat-card lg:col-span-4">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">7-Day Slot Availability</span>
                    <span class="text-[11px] font-bold text-slate-600 dark:text-slate-400 font-mono">Booked / Total</span>
                </div>

                <div class="mt-3 grid grid-cols-7 gap-1.5 text-center">
                    @foreach($t['availability_days'] as $day)
                        <div class="flex flex-col items-center">
                            <div class="text-[10px] font-bold text-slate-500 dark:text-slate-400">{{ $day['name'] }}</div>
                            <div class="text-[9px] text-slate-400 dark:text-slate-500">{{ $day['date'] }}</div>
                            
                            {{-- Mini Bar Graph --}}
                            <div class="mt-2 h-14 w-full rounded-md bg-slate-200 dark:bg-slate-700 flex flex-col justify-end p-0.5">
                                <div class="w-full rounded-sm bg-gradient-to-t from-emerald-600 to-teal-500 transition-all" style="height: {{ $day['pct'] }}%;"></div>
                            </div>
                            
                            <div class="mt-1 font-mono text-[10px] font-black text-slate-900 dark:text-white">
                                {{ $day['pct'] }}%
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-2 flex items-center justify-between border-t border-slate-200/60 pt-2 text-[10px] text-slate-500 dark:border-slate-700/60 dark:text-slate-400">
                    <span>🟢 High Demand (>75%)</span>
                    <span>Weekend Surge Applied</span>
                </div>
            </div>
        </div>

        {{-- Maintenance Advisory Alert --}}
        <div class="relative z-10 flex items-center gap-3 rounded-xl border border-amber-200/80 bg-amber-50/70 px-4 py-2.5 dark:border-amber-900/50 dark:bg-amber-950/30">
            <svg class="h-4 w-4 shrink-0 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            <div class="text-xs text-amber-800 dark:text-amber-300">
                <span class="font-bold">Maintenance Status:</span> {{ $t['maintenance']['status_note'] }}
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
