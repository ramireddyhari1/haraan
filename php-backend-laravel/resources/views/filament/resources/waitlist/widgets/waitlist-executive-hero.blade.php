@php
    $t = $this->getTelemetry();
@endphp

<x-filament-widgets::widget>
    <style>
        .wtl-hero-root {
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
        .dark .wtl-hero-root {
            background: #0f172a;
            box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.08);
        }
        .wtl-card {
            background: #f8fafc;
            border: 1px solid #f1f5f9;
            border-radius: 12px;
            padding: 16px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .dark .wtl-card {
            background: #1e293b;
            border-color: #334155;
        }
    </style>

    <div class="wtl-hero-root relative overflow-hidden">
        {{-- Emerald Ambient Glow --}}
        <div class="pointer-events-none absolute -right-20 -top-20 h-64 w-64 rounded-full bg-emerald-500/10 blur-3xl"></div>
        <div class="pointer-events-none absolute -left-16 -bottom-16 h-56 w-56 rounded-full bg-teal-500/5 blur-3xl"></div>

        {{-- Header --}}
        <div class="relative z-10 flex flex-col gap-4 border-b border-slate-100 pb-4 dark:border-slate-800 md:flex-row md:items-center md:justify-between">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 border border-emerald-100 dark:bg-emerald-950/50 dark:text-emerald-400 dark:border-emerald-800">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <div class="flex items-center gap-2.5">
                        <h2 class="text-lg font-bold tracking-tight text-slate-900 dark:text-white">
                            Waitlist Queue & Auto-Allocation Engine
                        </h2>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            Auto-Dispatch Active
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Zero dead-turf salvage, priority queuing rules, automated slot reassignment upon cancellation, and wait-time telemetry.
                    </p>
                </div>
            </div>

            {{-- Allocation Rate --}}
            <div class="flex items-center gap-3">
                <div class="flex items-center gap-3 rounded-xl border border-emerald-200/80 bg-gradient-to-r from-emerald-50/90 to-teal-50/70 px-3.5 py-2 shadow-sm dark:border-emerald-800/60 dark:from-emerald-950/30 dark:to-slate-900">
                    <div class="text-left">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">Recovery Rate</div>
                        <div class="text-xs font-semibold text-slate-700 dark:text-slate-200">Optimal Slot Salvage</div>
                    </div>
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-600 text-sm font-black text-white font-mono shadow-sm">
                        {{ $t['auto_allocation_rate'] }}
                    </div>
                </div>
            </div>
        </div>

        {{-- 12-Column Grid --}}
        <div class="relative z-10 grid grid-cols-1 gap-4 lg:grid-cols-12">
            {{-- Col 1-4: Queue Depth & Recovered Revenue --}}
            <div class="wtl-card lg:col-span-4">
                <div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Active Queue Depth</span>
                        <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-bold text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300">
                            {{ $t['allocated_count'] }} slots filled
                        </span>
                    </div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-3xl font-black tracking-tight text-slate-900 dark:text-white font-mono">
                            {{ $t['waiting_count'] }}
                        </span>
                        <span class="text-xs text-slate-500 dark:text-slate-400">players waiting for next open turf</span>
                    </div>
                </div>

                <div class="mt-4 rounded-lg border border-emerald-200/80 bg-emerald-50/60 p-3 text-xs dark:border-emerald-900/60 dark:bg-emerald-950/40">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-800 dark:text-emerald-300">Cancellation Salvage Value</span>
                        <span class="font-mono font-black text-emerald-700 dark:text-emerald-400">{{ $t['recovered_revenue'] }}</span>
                    </div>
                    <p class="mt-1 text-[11px] text-emerald-700 dark:text-emerald-300">
                        {{ $t['recovered_sub'] }}. Instantly matched to waitlist within seconds.
                    </p>
                </div>
            </div>

            {{-- Col 5-8: Priority Hierarchy Engine --}}
            <div class="wtl-card lg:col-span-4">
                <div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Priority Dispatch Rules</span>
                        <span class="rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-bold text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">Automated</span>
                    </div>

                    <div class="mt-3 space-y-2">
                        @foreach($t['priority_tiers'] as $tier)
                            <div class="rounded-lg border border-slate-200/70 bg-white p-2 text-xs dark:border-slate-800 dark:bg-slate-900">
                                <div class="flex items-center justify-between">
                                    <span class="font-bold text-slate-900 dark:text-white text-[11px]">{{ $tier['name'] }}</span>
                                    <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400 text-[10px]">{{ $tier['active'] }} in line</span>
                                </div>
                                <div class="mt-0.5 text-[10px] text-slate-500 dark:text-slate-400">
                                    Rule: {{ $tier['rule'] }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="mt-2 text-[10px] text-slate-400 dark:text-slate-500 text-center">
                    Automated WhatsApp push + SMS fallback on slot opening
                </div>
            </div>

            {{-- Col 9-12: Wait Time & Engine Speed --}}
            <div class="wtl-card lg:col-span-4">
                <div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Expected Wait Time</span>
                        <span class="font-mono text-xs font-bold text-emerald-600 dark:text-emerald-400">&plusmn; 4 mins</span>
                    </div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-3xl font-black tracking-tight text-emerald-600 dark:text-emerald-400 font-mono">
                            {{ $t['avg_wait_time'] }}
                        </span>
                        <span class="text-xs text-slate-500 dark:text-slate-400">avg time to confirmed slot</span>
                    </div>
                </div>

                <div class="mt-4 rounded-lg border border-slate-200/80 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-bold text-slate-900 dark:text-white">{{ $t['auto_engine']['status'] }}</span>
                        <span class="h-2 w-2 rounded-full bg-emerald-500 animate-ping"></span>
                    </div>
                    <div class="mt-1 space-y-1 text-[11px] text-slate-500 dark:text-slate-400">
                        <div>⚡ {{ $t['auto_engine']['latency'] }}</div>
                        <div>🛡️ {{ $t['auto_engine']['retention'] }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
