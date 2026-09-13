@php
    $summary = $this->getAnalyticsSummary();
    $categories = $this->getReportCategories();
@endphp

<x-filament-panels::page>
    <div class="space-y-6 -mt-2">
        {{-- ========================================================================= --}}
        {{-- 1. EXECUTIVE REPORTING HERO                                               --}}
        {{-- ========================================================================= --}}
        <section class="relative overflow-hidden rounded-2xl border border-slate-200/90 bg-white p-6 md:p-8 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            {{-- Emerald Gradient Ambience Glow --}}
            <div class="pointer-events-none absolute -right-24 -top-24 h-80 w-80 rounded-full bg-emerald-500/10 blur-3xl"></div>
            <div class="pointer-events-none absolute -left-20 -bottom-20 h-72 w-72 rounded-full bg-teal-500/5 blur-3xl"></div>

            {{-- Glassy Header --}}
            <div class="relative z-10 flex flex-col gap-4 border-b border-slate-100 pb-6 dark:border-slate-800 md:flex-row md:items-center md:justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 border border-emerald-100 dark:bg-emerald-950/50 dark:text-emerald-400 dark:border-emerald-800">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2.5">
                            <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white md:text-2xl">
                                Executive Analytics & Export Center
                            </h1>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                Live Data Warehouse
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Enterprise financial audit, court occupancy yield, cohort retention reports, and automated scheduled dispatches.
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <div class="rounded-xl border border-emerald-200/80 bg-gradient-to-r from-emerald-50/90 to-teal-50/70 px-4 py-2 shadow-sm dark:border-emerald-800/60 dark:from-emerald-950/30 dark:to-slate-900">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">Automated Dispatch</div>
                        <div class="text-xs font-semibold text-slate-700 dark:text-slate-200">{{ $summary['scheduled_jobs'] }} Active Schedules</div>
                    </div>
                </div>
            </div>

            {{-- Telemetry KPI Metrics (4 Pillars) --}}
            <div class="relative z-10 mt-6 grid grid-cols-2 gap-4 md:grid-cols-4">
                <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-4 dark:border-slate-800/80 dark:bg-slate-800/40">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Audited Gross Revenue</span>
                    <div class="mt-2 font-mono text-2xl font-black text-slate-900 dark:text-white">{{ $summary['gross_revenue'] }}</div>
                    <span class="mt-1 block text-[11px] text-emerald-600 dark:text-emerald-400 font-medium">Filtered period turnover</span>
                </div>

                <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-4 dark:border-slate-800/80 dark:bg-slate-800/40">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Court Bookings</span>
                    <div class="mt-2 font-mono text-2xl font-black text-slate-900 dark:text-white">{{ $summary['total_bookings'] }}</div>
                    <span class="mt-1 block text-[11px] text-slate-500 dark:text-slate-400">Across {{ $summary['courts_monitored'] }} courts</span>
                </div>

                <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-4 dark:border-slate-800/80 dark:bg-slate-800/40">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Fleet Occupancy Yield</span>
                    <div class="mt-2 font-mono text-2xl font-black text-emerald-600 dark:text-emerald-400">{{ $summary['avg_utilization'] }}</div>
                    <span class="mt-1 block text-[11px] text-emerald-700 dark:text-emerald-300 font-medium">+5.1% vs prev month</span>
                </div>

                <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-4 dark:border-slate-800/80 dark:bg-slate-800/40">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Player Retention</span>
                    <div class="mt-2 font-mono text-2xl font-black text-slate-900 dark:text-white">{{ $summary['player_retention'] }}</div>
                    <span class="mt-1 block text-[11px] text-slate-500 dark:text-slate-400">Repeat booking ratio</span>
                </div>
            </div>

            {{-- Filter & Dispatch Toolbar --}}
            <div class="relative z-10 mt-6 rounded-xl border border-slate-200/80 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/60">
                <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div class="flex flex-wrap items-end gap-3">
                        <div>
                            <label class="text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-300">From Date</label>
                            <input type="date" wire:model.live="from"
                                   class="mt-1 block rounded-lg border-slate-300 px-3 py-2 text-xs shadow-xs focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                        </div>

                        <div>
                            <label class="text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-300">To Date</label>
                            <input type="date" wire:model.live="to"
                                   class="mt-1 block rounded-lg border-slate-300 px-3 py-2 text-xs shadow-xs focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                        </div>

                        <div>
                            <label class="text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-300">Export Format</label>
                            <select wire:model.live="exportFormat"
                                    class="mt-1 block rounded-lg border-slate-300 px-3 py-2 text-xs shadow-xs focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                                <option value="csv">CSV (Raw Data Sheet)</option>
                                <option value="xlsx">Excel (.xlsx formatted)</option>
                                <option value="pdf">PDF Executive Summary</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex items-center gap-2.5">
                        <x-filament::button wire:click="scheduleReport" color="gray" icon="heroicon-o-clock">
                            Schedule Automation
                        </x-filament::button>

                        <x-filament::button wire:click="download" color="success" icon="heroicon-o-arrow-down-tray">
                            Export Selected Dataset
                        </x-filament::button>
                    </div>
                </div>

                <div class="mt-3 flex items-center justify-between border-t border-slate-100 pt-2 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400">
                    <span>Selected Window: <strong>{{ $from }}</strong> &rarr; <strong>{{ $to }}</strong></span>
                    <span>Ready for instant stream download ({{ $this->rowCount() }} matched rows)</span>
                </div>
            </div>
        </section>

        {{-- ========================================================================= --}}
        {{-- 2. REPORT CATALOG (4 CORE ENTERPRISE LEDGERS)                             --}}
        {{-- ========================================================================= --}}
        <section class="space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-base font-bold text-slate-900 dark:text-white">Standardized Reporting Ledgers</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Pre-built executive formats formatted for finance, operations, and leadership.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                @foreach($categories as $cat)
                    <div class="group relative rounded-xl border border-slate-200/90 bg-white p-5 shadow-xs transition hover:border-emerald-300 hover:shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 border border-emerald-100 dark:bg-emerald-950/60 dark:text-emerald-400 dark:border-emerald-800">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ $cat['title'] }}</h3>
                                    <span class="rounded bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                        {{ $cat['frequency'] }} · {{ $cat['records'] }}
                                    </span>
                                </div>
                            </div>

                            <button wire:click="download" type="button" class="rounded-lg bg-emerald-50 px-2.5 py-1.5 text-xs font-bold text-emerald-700 hover:bg-emerald-100 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800">
                                Download
                            </button>
                        </div>

                        <p class="mt-3 text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                            {{ $cat['description'] }}
                        </p>

                        <div class="mt-3 flex items-center justify-between border-t border-slate-100 pt-2 text-[11px] text-slate-400 dark:border-slate-800 dark:text-slate-500">
                            <span>Last Generated: {{ $cat['last_generated'] }}</span>
                            <span class="text-emerald-600 dark:text-emerald-400 font-semibold">CSV / XLSX / PDF Ready</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- ========================================================================= --}}
        {{-- 3. AUTOMATED SCHEDULES AUDIT TRAY                                         --}}
        {{-- ========================================================================= --}}
        <section class="rounded-xl border border-slate-200/90 bg-slate-50/60 p-5 dark:border-slate-800 dark:bg-slate-900/60">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">Active Automated Dispatch Rules</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Scheduled report generators pushing encrypted summaries to stakeholders.</p>
                </div>
                <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-bold text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300">
                    All Automations Healthy
                </span>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-3">
                <div class="rounded-lg border border-slate-200/70 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-bold text-slate-900 dark:text-white">Daily Midnight Ledger</span>
                        <span class="text-[10px] font-mono text-emerald-600 font-bold">00:05 IST</span>
                    </div>
                    <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">Recaps today's gross court collections, UPI vs Cash splits, and open shift balances.</p>
                    <div class="mt-2 text-[10px] text-slate-400">Dispatched to: finance@haraan.app, GM Telegram</div>
                </div>

                <div class="rounded-lg border border-slate-200/70 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-bold text-slate-900 dark:text-white">Weekly Occupancy Audit</span>
                        <span class="text-[10px] font-mono text-emerald-600 font-bold">Mondays 06:00 IST</span>
                    </div>
                    <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">Analyzes peak hour yield, dead turf hours, and recommends surge pricing adjustments.</p>
                    <div class="mt-2 text-[10px] text-slate-400">Dispatched to: ops@haraan.app</div>
                </div>

                <div class="rounded-lg border border-slate-200/70 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-bold text-slate-900 dark:text-white">Monthly Tax & Payout Pack</span>
                        <span class="text-[10px] font-mono text-emerald-600 font-bold">1st of Month 08:00 IST</span>
                    </div>
                    <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">CA-ready GST 18% liability, platform commission reconciliation, and partner bank credits.</p>
                    <div class="mt-2 text-[10px] text-slate-400">Dispatched to: accounts@haraan.app, CA Portal</div>
                </div>
            </div>
        </section>
    </div>
</x-filament-panels::page>
