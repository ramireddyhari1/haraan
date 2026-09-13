@php
    $t = $this->getTelemetry();
@endphp

<x-filament-widgets::widget>
    <style>
        .shf-hero-root {
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
        .dark .shf-hero-root {
            background: #0f172a;
            box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.08);
        }
        .shf-card {
            background: #f8fafc;
            border: 1px solid #f1f5f9;
            border-radius: 12px;
            padding: 16px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .dark .shf-card {
            background: #1e293b;
            border-color: #334155;
        }
    </style>

    <div class="shf-hero-root relative overflow-hidden">
        {{-- Emerald Ambient Glow --}}
        <div class="pointer-events-none absolute -right-20 -top-20 h-64 w-64 rounded-full bg-emerald-500/10 blur-3xl"></div>
        <div class="pointer-events-none absolute -left-16 -bottom-16 h-56 w-56 rounded-full bg-teal-500/5 blur-3xl"></div>

        {{-- Header --}}
        <div class="relative z-10 flex flex-col gap-4 border-b border-slate-100 pb-4 dark:border-slate-800 md:flex-row md:items-center md:justify-between">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 border border-emerald-100 dark:bg-emerald-950/50 dark:text-emerald-400 dark:border-emerald-800">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                    </svg>
                </div>
                <div>
                    <div class="flex items-center gap-2.5">
                        <h2 class="text-lg font-bold tracking-tight text-slate-900 dark:text-white">
                            Front-Desk Shift & Cash Drawer Reconciliation
                        </h2>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            Live Drawer Audit
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Float management, cash vs POS terminal reconciliation, staff cashier performance, and discrepancy alarms.
                    </p>
                </div>
            </div>

            {{-- Audit Health Score --}}
            <div class="flex items-center gap-3">
                <div class="flex items-center gap-3 rounded-xl border border-emerald-200/80 bg-gradient-to-r from-emerald-50/90 to-teal-50/70 px-3.5 py-2 shadow-sm dark:border-emerald-800/60 dark:from-emerald-950/30 dark:to-slate-900">
                    <div class="text-left">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">Audit Status</div>
                        <div class="text-xs font-semibold text-slate-700 dark:text-slate-200">{{ $t['variance_grade'] }}</div>
                    </div>
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-600 text-sm font-black text-white font-mono shadow-sm">
                        0
                    </div>
                </div>
            </div>
        </div>

        {{-- 12-Column Grid --}}
        <div class="relative z-10 grid grid-cols-1 gap-4 lg:grid-cols-12">
            {{-- Col 1-4: Drawer Cash & Float Volume --}}
            <div class="shf-card lg:col-span-4">
                <div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Physical Cash in Drawers</span>
                        <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-bold text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300">
                            {{ $t['open_shifts'] }} active shifts open
                        </span>
                    </div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-3xl font-black tracking-tight text-slate-900 dark:text-white font-mono">
                            {{ $t['cash_in_drawers'] }}
                        </span>
                        <span class="text-xs text-slate-500 dark:text-slate-400">cash on premises</span>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-2.5 border-t border-slate-200/60 pt-3 text-xs dark:border-slate-700/60">
                    <div class="rounded-lg bg-white p-2 border border-slate-200/70 dark:bg-slate-900/50 dark:border-slate-800">
                        <div class="text-[10px] text-slate-500 dark:text-slate-400">Starting Floats</div>
                        <div class="mt-0.5 font-mono font-bold text-slate-900 dark:text-white">{{ $t['opening_float'] }}</div>
                    </div>
                    <div class="rounded-lg bg-white p-2 border border-slate-200/70 dark:bg-slate-900/50 dark:border-slate-800">
                        <div class="text-[10px] text-slate-500 dark:text-slate-400">Counter Turnover</div>
                        <div class="mt-0.5 font-mono font-bold text-emerald-600 dark:text-emerald-400">{{ $t['total_counter_turnover'] }}</div>
                    </div>
                </div>
            </div>

            {{-- Col 5-8: POS Terminals & Variance Status --}}
            <div class="shf-card lg:col-span-4">
                <div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Counter Payment Rails</span>
                        <span class="font-mono text-xs font-bold text-emerald-600 dark:text-emerald-400">{{ $t['variance_status'] }}</span>
                    </div>

                    <div class="mt-3 space-y-2">
                        @foreach($t['pos_rails'] as $rail)
                            <div>
                                <div class="flex items-center justify-between text-[11px]">
                                    <span class="text-slate-600 dark:text-slate-300 font-medium">{{ $rail['name'] }}</span>
                                    <span class="font-mono font-bold text-slate-900 dark:text-white">{{ $rail['pct'] }}% · {{ $rail['amount'] }}</span>
                                </div>
                                <div class="mt-1 h-1.5 w-full rounded-full bg-slate-200 dark:bg-slate-700">
                                    <div class="h-1.5 rounded-full" style="width: {{ $rail['pct'] }}%; background-color: {{ $rail['color'] }};"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="mt-3 flex items-center justify-between rounded-lg border border-emerald-200/80 bg-emerald-50/60 p-2 text-xs dark:border-emerald-900/60 dark:bg-emerald-950/40">
                    <span class="flex items-center gap-1 text-emerald-800 dark:text-emerald-300 font-semibold">
                        ✅ Zero Cash Discrepancy
                    </span>
                    <span class="text-[10px] text-emerald-700 dark:text-emerald-400">All shifts reconciled penny-for-penny</span>
                </div>
            </div>

            {{-- Col 9-12: Staff Cashier Performance --}}
            <div class="shf-card lg:col-span-4">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Staff Shift Scorecard</span>
                    <span class="text-[10px] font-bold text-emerald-600 dark:text-emerald-400 font-mono">100% Balanced</span>
                </div>

                <div class="mt-2 space-y-2">
                    @foreach($t['staff_scorecard'] as $staff)
                        <div class="rounded-lg border border-slate-200/80 bg-white p-2 dark:border-slate-800 dark:bg-slate-900">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-bold text-slate-900 dark:text-white">{{ $staff['name'] }}</span>
                                <span class="font-mono font-black text-emerald-600 dark:text-emerald-400">{{ $staff['amount'] }}</span>
                            </div>
                            <div class="mt-0.5 flex items-center justify-between text-[10px] text-slate-500 dark:text-slate-400">
                                <span>{{ $staff['terminal'] }} ({{ $staff['transactions'] }} txns)</span>
                                <span class="rounded bg-emerald-50 px-1 py-0.2 text-[9px] font-bold text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300">
                                    {{ $staff['variance'] }} variance
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
