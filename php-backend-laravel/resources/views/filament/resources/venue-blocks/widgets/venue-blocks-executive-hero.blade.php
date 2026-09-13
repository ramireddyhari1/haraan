@php
    $t = $this->getTelemetry();
@endphp

<x-filament-widgets::widget>
    <style>
        .vbk-hero-root {
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
        .dark .vbk-hero-root {
            background: #0f172a;
            box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.08);
        }
        .vbk-card {
            background: #f8fafc;
            border: 1px solid #f1f5f9;
            border-radius: 12px;
            padding: 16px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .dark .vbk-card {
            background: #1e293b;
            border-color: #334155;
        }
    </style>

    <div class="vbk-hero-root relative overflow-hidden">
        {{-- Emerald / Indigo Ambient Glow --}}
        <div class="pointer-events-none absolute -right-20 -top-20 h-64 w-64 rounded-full bg-emerald-500/10 blur-3xl"></div>
        <div class="pointer-events-none absolute -left-16 -bottom-16 h-56 w-56 rounded-full bg-teal-500/5 blur-3xl"></div>

        {{-- Header --}}
        <div class="relative z-10 flex flex-col gap-4 border-b border-slate-100 pb-4 dark:border-slate-800 md:flex-row md:items-center md:justify-between">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 border border-emerald-100 dark:bg-emerald-950/50 dark:text-emerald-400 dark:border-emerald-800">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                    </svg>
                </div>
                <div>
                    <div class="flex items-center gap-2.5">
                        <h2 class="text-lg font-bold tracking-tight text-slate-900 dark:text-white">
                            Court Blackouts & Conflict Guard Command
                        </h2>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            Conflict Engine Guarded
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Zero-collision slot protection, maintenance blackouts, league reservation windows, and capacity impact analysis.
                    </p>
                </div>
            </div>

            {{-- Conflict Engine Badge --}}
            <div class="flex items-center gap-3">
                <div class="flex items-center gap-3 rounded-xl border border-emerald-200/80 bg-gradient-to-r from-emerald-50/90 to-teal-50/70 px-3.5 py-2 shadow-sm dark:border-emerald-800/60 dark:from-emerald-950/30 dark:to-slate-900">
                    <div class="text-left">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">Conflict Engine</div>
                        <div class="text-xs font-semibold text-slate-700 dark:text-slate-200">{{ $t['conflict_status'] }}</div>
                    </div>
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-600 text-sm font-black text-white font-mono shadow-sm">
                        0
                    </div>
                </div>
            </div>
        </div>

        {{-- 12-Column Grid --}}
        <div class="relative z-10 grid grid-cols-1 gap-4 lg:grid-cols-12">
            {{-- Col 1-4: Blocked Volume & Conflict Engine Status --}}
            <div class="vbk-card lg:col-span-4">
                <div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Active Blackout Holds</span>
                        <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-bold text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300">
                            {{ $t['active_blocks'] }} currently active
                        </span>
                    </div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-3xl font-black tracking-tight text-slate-900 dark:text-white font-mono">
                            {{ $t['blocked_hours_week'] }}
                        </span>
                        <span class="text-xs text-slate-500 dark:text-slate-400">hours blocked this week across all turfs</span>
                    </div>
                </div>

                <div class="mt-4 rounded-lg border border-emerald-200/80 bg-emerald-50/60 p-3 text-xs dark:border-emerald-900/60 dark:bg-emerald-950/40">
                    <div class="flex items-center gap-2 text-emerald-800 dark:text-emerald-300 font-bold">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        Collision Prevention Engine
                    </div>
                    <p class="mt-1 text-[11px] text-emerald-700 dark:text-emerald-400">
                        Double-booking safeguard running at microsecond resolution. Walk-ins and online checkouts are automatically locked out of blocked intervals.
                    </p>
                </div>
            </div>

            {{-- Col 5-8: Breakdown by Hold Reason --}}
            <div class="vbk-card lg:col-span-4">
                <div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Block Category Breakdown</span>
                        <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400 font-mono">Hours & Pct</span>
                    </div>

                    <div class="mt-3 space-y-2.5">
                        @foreach($t['reasons'] as $reason)
                            <div>
                                <div class="flex items-center justify-between text-[11px]">
                                    <span class="text-slate-600 dark:text-slate-300 font-medium">{{ $reason['name'] }}</span>
                                    <span class="font-mono font-bold text-slate-900 dark:text-white">{{ $reason['hours'] }}h ({{ $reason['pct'] }}%)</span>
                                </div>
                                <div class="mt-1 h-1.5 w-full rounded-full bg-slate-200 dark:bg-slate-700">
                                    <div class="h-1.5 rounded-full" style="width: {{ $reason['pct'] }}%; background-color: {{ $reason['color'] }};"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="mt-3 flex items-center justify-between border-t border-slate-200/60 pt-2 text-[10px] text-slate-500 dark:border-slate-700/60 dark:text-slate-400">
                    <span>Zero unallocated dead-time</span>
                    <span class="font-bold text-emerald-600 dark:text-emerald-400">Optimized Utilization</span>
                </div>
            </div>

            {{-- Col 9-12: Capacity Impact & Next Scheduled Window --}}
            <div class="vbk-card lg:col-span-4">
                <div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Fleet Capacity Impact</span>
                        <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400">-{{ $t['capacity']['capacity_impact'] }} Capacity</span>
                    </div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-2xl font-black tracking-tight text-slate-900 dark:text-white font-mono">
                            {{ $t['capacity']['retained_revenue'] }}
                        </span>
                        <span class="text-xs text-slate-500 dark:text-slate-400">retained buyout revenue</span>
                    </div>
                </div>

                {{-- Upcoming Window --}}
                <div class="mt-3 rounded-lg border border-slate-200/80 bg-white p-2.5 text-xs dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Next Scheduled Blackout</span>
                        <span class="rounded bg-amber-100 px-1.5 py-0.5 text-[9px] font-bold text-amber-800 dark:bg-amber-950/60 dark:text-amber-300">Upcoming</span>
                    </div>
                    <div class="mt-1 font-semibold text-slate-900 dark:text-white text-xs">
                        {{ $t['upcoming_window']['title'] }}
                    </div>
                    <div class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">
                        {{ $t['upcoming_window']['time'] }} · {{ $t['upcoming_window']['impact'] }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
