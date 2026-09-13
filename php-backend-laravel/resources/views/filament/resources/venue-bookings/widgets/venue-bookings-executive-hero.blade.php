@php
    $t = $this->getTelemetry();
@endphp

<x-filament-widgets::widget>
    <style>
        .vb-hero-root {
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
        .dark .vb-hero-root {
            background: #0f172a;
            box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.08);
        }
        .vb-card {
            background: #f8fafc;
            border: 1px solid #f1f5f9;
            border-radius: 12px;
            padding: 16px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .dark .vb-card {
            background: #1e293b;
            border-color: #334155;
        }
    </style>

    <div class="vb-hero-root relative overflow-hidden">
        {{-- Emerald Ambient Glow --}}
        <div class="pointer-events-none absolute -right-20 -top-20 h-64 w-64 rounded-full bg-emerald-500/10 blur-3xl"></div>
        <div class="pointer-events-none absolute -left-16 -bottom-16 h-56 w-56 rounded-full bg-teal-500/5 blur-3xl"></div>

        {{-- Header --}}
        <div class="relative z-10 flex flex-col gap-4 border-b border-slate-100 pb-4 dark:border-slate-800 md:flex-row md:items-center md:justify-between">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 border border-emerald-100 dark:bg-emerald-950/50 dark:text-emerald-400 dark:border-emerald-800">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <div class="flex items-center gap-2.5">
                        <h2 class="text-lg font-bold tracking-tight text-slate-900 dark:text-white">
                            Bookings & Payment Settlement Command
                        </h2>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            Live Payment Gateway
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Funnel conversion tracking, cancellation ratios, coupon redemption analytics, payment splits, and 7-day revenue forecasts.
                    </p>
                </div>
            </div>

            {{-- Conversion Health Score --}}
            <div class="flex items-center gap-3">
                <div class="flex items-center gap-3 rounded-xl border border-emerald-200/80 bg-gradient-to-r from-emerald-50/90 to-teal-50/70 px-3.5 py-2 shadow-sm dark:border-emerald-800/60 dark:from-emerald-950/30 dark:to-slate-900">
                    <div class="text-left">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">Checkout Health</div>
                        <div class="text-xs font-semibold text-slate-700 dark:text-slate-200">Optimal Velocity</div>
                    </div>
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-600 text-sm font-black text-white font-mono shadow-sm">
                        97
                    </div>
                </div>
            </div>
        </div>

        {{-- 12-Column Grid --}}
        <div class="relative z-10 grid grid-cols-1 gap-4 lg:grid-cols-12">
            {{-- Col 1-4: GMV & Conversion Metrics --}}
            <div class="vb-card lg:col-span-4">
                <div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Gross Booking Value</span>
                        <span class="inline-flex items-center gap-1 text-xs font-bold text-emerald-600 dark:text-emerald-400">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                            {{ $t['conversion_sub'] }}
                        </span>
                    </div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-3xl font-black tracking-tight text-slate-900 dark:text-white font-mono">
                            {{ $t['gmv'] }}
                        </span>
                        <span class="text-xs text-slate-500 dark:text-slate-400">settled volume</span>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-3 border-t border-slate-200/60 pt-3 dark:border-slate-700/60">
                    <div class="rounded-lg bg-white p-2.5 border border-slate-200/70 dark:bg-slate-900/50 dark:border-slate-800">
                        <div class="text-[10px] font-bold uppercase text-slate-500 dark:text-slate-400">Conversion Rate</div>
                        <div class="mt-1 font-mono text-xl font-black text-emerald-600 dark:text-emerald-400">{{ $t['conversion_rate'] }}</div>
                        <div class="text-[10px] text-emerald-700 dark:text-emerald-300">Checkout &rarr; Booked</div>
                    </div>
                    <div class="rounded-lg bg-white p-2.5 border border-slate-200/70 dark:bg-slate-900/50 dark:border-slate-800">
                        <div class="text-[10px] font-bold uppercase text-slate-500 dark:text-slate-400">Cancellation Rate</div>
                        <div class="mt-1 font-mono text-xl font-black text-slate-900 dark:text-white">{{ $t['cancellation_rate'] }}</div>
                        <div class="text-[10px] text-slate-500 dark:text-slate-400">{{ $t['refunded_amount'] }}</div>
                    </div>
                </div>
            </div>

            {{-- Col 5-8: Booking Funnel & Coupons --}}
            <div class="vb-card lg:col-span-4">
                <div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Checkout Funnel Analytics</span>
                        <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400 font-mono">30-Day Stage Drop</span>
                    </div>

                    {{-- Horizontal Funnel Bars --}}
                    <div class="mt-3 space-y-2 text-xs">
                        <div>
                            <div class="flex items-center justify-between text-[11px]">
                                <span class="text-slate-600 dark:text-slate-300">1. Slot Views</span>
                                <span class="font-mono font-bold text-slate-900 dark:text-white">{{ number_format($t['funnel']['views']) }}</span>
                            </div>
                            <div class="mt-1 h-1.5 w-full rounded-full bg-slate-200 dark:bg-slate-700">
                                <div class="h-1.5 rounded-full bg-slate-400" style="width: 100%;"></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex items-center justify-between text-[11px]">
                                <span class="text-slate-600 dark:text-slate-300">2. Cart Lock</span>
                                <span class="font-mono font-bold text-slate-900 dark:text-white">{{ number_format($t['funnel']['cart']) }} (26.8%)</span>
                            </div>
                            <div class="mt-1 h-1.5 w-full rounded-full bg-slate-200 dark:bg-slate-700">
                                <div class="h-1.5 rounded-full bg-teal-500" style="width: 55%;"></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex items-center justify-between text-[11px]">
                                <span class="text-slate-600 dark:text-slate-300">3. Confirmed & Paid</span>
                                <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400">{{ number_format($t['funnel']['confirmed']) }} (91.5%)</span>
                            </div>
                            <div class="mt-1 h-1.5 w-full rounded-full bg-slate-200 dark:bg-slate-700">
                                <div class="h-1.5 rounded-full bg-emerald-500" style="width: 91%;"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-3 flex items-center justify-between rounded-lg border border-slate-200/80 bg-white p-2 text-xs dark:border-slate-800 dark:bg-slate-900">
                    <span class="flex items-center gap-1 text-slate-600 dark:text-slate-300">
                        🏷️ Active Coupon: <strong class="font-mono text-emerald-700 dark:text-emerald-300">{{ $t['coupons']['top_code'] }}</strong>
                    </span>
                    <span class="font-mono font-bold text-slate-900 dark:text-white">{{ $t['coupons']['total_discount'] }} saved</span>
                </div>
            </div>

            {{-- Col 9-12: Payment Split & Forecast --}}
            <div class="vb-card lg:col-span-4">
                <div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Payment Rails Split</span>
                        <span class="rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-bold text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">UPI Dominant</span>
                    </div>

                    <div class="mt-3 space-y-1.5">
                        @foreach($t['payment_split'] as $rail)
                            <div>
                                <div class="flex items-center justify-between text-[11px]">
                                    <span class="text-slate-600 dark:text-slate-300">{{ $rail['name'] }}</span>
                                    <span class="font-mono font-bold text-slate-900 dark:text-white">{{ $rail['pct'] }}% · {{ $rail['amount'] }}</span>
                                </div>
                                <div class="mt-1 h-1.5 w-full rounded-full bg-slate-200 dark:bg-slate-700">
                                    <div class="h-1.5 rounded-full" style="width: {{ $rail['pct'] }}%; background-color: {{ $rail['color'] }};"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="mt-3 rounded-lg border border-emerald-200/70 bg-emerald-50/60 p-2 text-xs dark:border-emerald-900/50 dark:bg-emerald-950/30">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold uppercase text-emerald-800 dark:text-emerald-300">7-Day Revenue Forecast</span>
                        <span class="font-mono font-black text-emerald-700 dark:text-emerald-400">{{ $t['forecast']['projected_7d'] }}</span>
                    </div>
                    <div class="mt-0.5 text-[10px] text-emerald-700 dark:text-emerald-300">
                        {{ $t['forecast']['weekend_occupancy'] }} weekend occupancy projected · {{ $t['forecast']['confidence'] }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
