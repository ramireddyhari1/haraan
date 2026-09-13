@php
    $t = $this->getTelemetry();
@endphp

<x-filament-panels::page>
    <div class="space-y-6 -mt-2">
        {{-- Hero Header --}}
        <section class="relative overflow-hidden rounded-2xl border border-slate-200/90 bg-white p-6 md:p-8 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="pointer-events-none absolute -right-24 -top-24 h-80 w-80 rounded-full bg-emerald-500/10 blur-3xl"></div>

            <div class="relative z-10 flex flex-col gap-4 border-b border-slate-100 pb-6 dark:border-slate-800 md:flex-row md:items-center md:justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 border border-emerald-100 dark:bg-emerald-950/50 dark:text-emerald-400 dark:border-emerald-800">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2.5">
                            <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white md:text-2xl">
                                Operations Support & Dispute Center
                            </h1>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                SLA Guard Active
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Court dispute resolutions, automated weather reschedules, gateway refund approvals, and player satisfaction logs.
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2.5">
                    <x-filament::button wire:click="resolveAll" color="success" icon="heroicon-o-check-badge">
                        Auto-Resolve Pending Gateway Discrepancies
                    </x-filament::button>
                </div>
            </div>

            {{-- 4 Metric Cards --}}
            <div class="relative z-10 mt-6 grid grid-cols-2 gap-4 md:grid-cols-4">
                <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-4 dark:border-slate-800/80 dark:bg-slate-800/40">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Open Tickets</span>
                    <div class="mt-2 font-mono text-2xl font-black text-amber-600 dark:text-amber-400">{{ $t['open_tickets'] }} Pending</div>
                    <span class="mt-1 block text-[11px] text-slate-500 dark:text-slate-400">Active disputes</span>
                </div>
                <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-4 dark:border-slate-800/80 dark:bg-slate-800/40">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Resolved Today</span>
                    <div class="mt-2 font-mono text-2xl font-black text-emerald-600 dark:text-emerald-400">{{ $t['resolved_today'] }}</div>
                    <span class="mt-1 block text-[11px] text-emerald-700 dark:text-emerald-300 font-medium">100% same-day closure</span>
                </div>
                <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-4 dark:border-slate-800/80 dark:bg-slate-800/40">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">First Response SLA</span>
                    <div class="mt-2 font-mono text-2xl font-black text-slate-900 dark:text-white">{{ $t['avg_response_time'] }}</div>
                    <span class="mt-1 block text-[11px] text-slate-500 dark:text-slate-400">Target &lt; 10 mins</span>
                </div>
                <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-4 dark:border-slate-800/80 dark:bg-slate-800/40">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">CSAT Score</span>
                    <div class="mt-2 font-mono text-2xl font-black text-emerald-600 dark:text-emerald-400">{{ $t['csat_score'] }}</div>
                    <span class="mt-1 block text-[11px] text-slate-500 dark:text-slate-400">98% positive rating</span>
                </div>
            </div>
        </section>

        {{-- Disputes Queue & Categories --}}
        <section class="grid grid-cols-1 gap-6 lg:grid-cols-12">
            {{-- Col 1-5: Categories Breakdown --}}
            <div class="rounded-xl border border-slate-200/90 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-slate-900 lg:col-span-5">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Inquiry Categories</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">Resolution throughput across complaint types.</p>

                <div class="mt-4 space-y-3">
                    @foreach($t['categories'] as $cat)
                        <div class="rounded-lg border border-slate-100 bg-slate-50/70 p-3 dark:border-slate-800 dark:bg-slate-800/40">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-semibold text-slate-900 dark:text-white">{{ $cat['name'] }}</span>
                                <span class="font-mono text-emerald-600 dark:text-emerald-400 font-bold">{{ $cat['pct'] }}%</span>
                            </div>
                            <div class="mt-1 flex items-center justify-between text-[11px] text-slate-400">
                                <span>{{ $cat['resolved'] }} of {{ $cat['count'] }} resolved</span>
                                <span>SLA Met</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Col 6-12: Live Dispute Cases --}}
            <div class="rounded-xl border border-slate-200/90 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-slate-900 lg:col-span-7">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">Active Operational Cases</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Cases requiring front-desk or manager approval.</p>
                    </div>
                </div>

                <div class="mt-4 space-y-3">
                    @foreach($t['tickets'] as $ticket)
                        <div class="rounded-lg border border-slate-200/80 bg-white p-3 shadow-2xs dark:border-slate-800 dark:bg-slate-900">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="font-mono font-bold text-xs text-slate-900 dark:text-white">{{ $ticket['id'] }}</span>
                                    <span class="rounded bg-slate-100 px-1.5 py-0.2 text-[10px] font-bold text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                        {{ $ticket['category'] }}
                                    </span>
                                </div>
                                <span class="text-[10px] font-mono text-slate-400">{{ $ticket['elapsed'] }}</span>
                            </div>

                            <div class="mt-2 flex items-center justify-between text-xs">
                                <span class="text-slate-600 dark:text-slate-300">Player: <strong>{{ $ticket['user'] }}</strong> ({{ $ticket['court'] }})</span>
                                <span class="rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-bold text-amber-700 border border-amber-200 dark:bg-amber-950/40 dark:text-amber-300">
                                    {{ $ticket['status'] }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    </div>
</x-filament-panels::page>
