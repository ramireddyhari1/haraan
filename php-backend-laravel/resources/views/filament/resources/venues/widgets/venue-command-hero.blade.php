@php
    $t = $this->getTelemetry();
    $money = static fn ($n): string => '₹' . number_format((float) $n);
@endphp

<x-filament-widgets::widget>
    @if (($t['ready'] ?? false) === true)
        <style>
            .vn360-root {
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
            .dark .vn360-root {
                background: #0f172a;
                box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.08);
            }
            .vn360-card {
                background: #f8fafc;
                border: 1px solid #f1f5f9;
                border-radius: 12px;
                padding: 16px;
                display: flex;
                flex-direction: column;
                gap: 4px;
            }
            .dark .vn360-card {
                background: #1e293b;
                border-color: #334155;
            }
            .vn360-figure {
                font-size: 24px;
                font-weight: 700;
                letter-spacing: -0.02em;
                line-height: 1.1;
            }
            .vn360-day {
                border: 1px solid #f1f5f9;
                border-radius: 10px;
                padding: 10px 8px;
                text-align: center;
                background: #ffffff;
            }
            .dark .vn360-day {
                background: #0f172a;
                border-color: #334155;
            }
            .vn360-track {
                height: 5px;
                border-radius: 999px;
                background: #e2e8f0;
                overflow: hidden;
                margin-top: 8px;
            }
            .dark .vn360-track { background: #334155; }
            .vn360-fill { height: 100%; border-radius: 999px; background: #2563eb; }
        </style>

        <div class="vn360-root relative overflow-hidden">
            {{-- Ambient wash, matching the venues fleet hero --}}
            <div class="pointer-events-none absolute -right-20 -top-20 h-64 w-64 rounded-full bg-blue-500/10 blur-3xl"></div>
            <div class="pointer-events-none absolute -left-16 -bottom-16 h-56 w-56 rounded-full bg-sky-500/5 blur-3xl"></div>

            {{-- Identity strip --}}
            <div class="relative z-10 flex flex-col gap-4 border-b border-slate-100 pb-4 dark:border-slate-800 md:flex-row md:items-start md:justify-between">
                <div class="flex items-start gap-3">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-blue-100 bg-blue-50 text-blue-600 dark:border-blue-800 dark:bg-blue-950/50 dark:text-blue-400">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                    </div>
                    <div>
                        <div class="flex flex-wrap items-center gap-2.5">
                            <h2 class="text-lg font-bold tracking-tight text-slate-900 dark:text-white">
                                {{ $t['branch'] }}
                            </h2>
                            <span @class([
                                'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold border',
                                'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800' => $t['onSale'],
                                'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800' => ! $t['onSale'],
                            ])>
                                <span @class([
                                    'h-1.5 w-1.5 rounded-full',
                                    'bg-emerald-500 animate-pulse' => $t['onSale'],
                                    'bg-rose-500' => ! $t['onSale'],
                                ])></span>
                                {{ $t['statusLabel'] }}
                            </span>
                        </div>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                            {{ $t['name'] }}@if ($t['city']) · {{ $t['city'] }}@endif ·
                            {{ $t['courtsActive'] }} of {{ $t['courtsTotal'] }} units active
                            @if ($t['hoursToday'])
                                · open {{ $t['hoursToday']['open'] }}–{{ $t['hoursToday']['close'] }} today
                            @else
                                · closed today
                            @endif
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-1.5">
                    @foreach ($t['sports'] as $sport)
                        <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-2.5 py-0.5 text-xs font-medium text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
                            {{ $sport }}
                        </span>
                    @endforeach
                </div>
            </div>

            {{-- Today's sheet + the money that moved --}}
            <div class="relative z-10 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div class="vn360-card">
                    <span class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Occupancy today</span>
                    <span class="vn360-figure text-slate-900 dark:text-white">{{ $t['occupancy'] }}%</span>
                    <span class="text-xs text-slate-500 dark:text-slate-400">
                        {{ $t['todayBookings'] }} booked of {{ $t['offeredToday'] }} court-hours offered
                    </span>
                    <div class="vn360-track"><div class="vn360-fill" style="width: {{ $t['occupancy'] }}%"></div></div>
                </div>

                <div class="vn360-card">
                    <span class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Collected today</span>
                    <span class="vn360-figure text-emerald-600 dark:text-emerald-400">{{ $money($t['collectedToday']) }}</span>
                    <span class="text-xs text-slate-500 dark:text-slate-400">From the payment ledger, not the order total</span>
                </div>

                <div class="vn360-card">
                    <span class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Collected · 30 days</span>
                    <span class="vn360-figure text-slate-900 dark:text-white">{{ $money($t['collected30d']) }}</span>
                    <span class="text-xs text-slate-500 dark:text-slate-400">Advances, desk payments and refunds netted</span>
                </div>

                <div class="vn360-card">
                    <span class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Balance due</span>
                    <span @class([
                        'vn360-figure',
                        'text-amber-600 dark:text-amber-400' => $t['balanceDue'] > 0,
                        'text-slate-900 dark:text-white' => $t['balanceDue'] <= 0,
                    ])>{{ $money($t['balanceDue']) }}</span>
                    <span class="text-xs text-slate-500 dark:text-slate-400">
                        @if ($t['owingCount'] > 0)
                            Across {{ $t['owingCount'] }} upcoming {{ \Illuminate\Support\Str::plural('booking', $t['owingCount']) }}
                        @else
                            Nothing outstanding ahead
                        @endif
                    </span>
                </div>
            </div>

            {{-- The week ahead --}}
            <div class="relative z-10">
                <div class="mb-2 flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Next seven days</span>
                    <span class="text-xs text-slate-400 dark:text-slate-500">booked / offered</span>
                </div>
                <div class="grid grid-cols-4 gap-2 sm:grid-cols-7">
                    @foreach ($t['days'] as $day)
                        <div class="vn360-day">
                            <div class="text-xs font-semibold text-slate-700 dark:text-slate-200">{{ $day['label'] }}</div>
                            <div class="text-[11px] text-slate-400 dark:text-slate-500">{{ $day['date'] }}</div>
                            @if ($day['closed'])
                                <div class="mt-1.5 text-[11px] font-medium text-rose-500 dark:text-rose-400">Closed</div>
                            @else
                                <div class="mt-1.5 text-sm font-bold text-slate-900 dark:text-white">
                                    {{ $day['booked'] }}<span class="text-[11px] font-normal text-slate-400">/{{ $day['offered'] }}</span>
                                </div>
                            @endif
                            <div class="vn360-track"><div class="vn360-fill" style="width: {{ $day['percent'] }}%"></div></div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</x-filament-widgets::widget>
