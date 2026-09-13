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
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2.5">
                            <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white md:text-2xl">
                                Tournaments & Championship League Hub
                            </h1>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                Live Brackets Active
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Tournament fixture scheduling, team registrations, prize disbursement pools, and court reservation synchronization.
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2.5">
                    <x-filament::button wire:click="generateBrackets" color="success" icon="heroicon-o-arrow-path">
                        Sync Fixture Brackets
                    </x-filament::button>
                </div>
            </div>

            {{-- 4 Metric Cards --}}
            <div class="relative z-10 mt-6 grid grid-cols-2 gap-4 md:grid-cols-4">
                <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-4 dark:border-slate-800/80 dark:bg-slate-800/40">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Live Tournaments</span>
                    <div class="mt-2 font-mono text-2xl font-black text-emerald-600 dark:text-emerald-400">{{ $t['active_tournaments'] }} Cups</div>
                    <span class="mt-1 block text-[11px] text-emerald-700 dark:text-emerald-300 font-medium">Currently underway</span>
                </div>
                <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-4 dark:border-slate-800/80 dark:bg-slate-800/40">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Registered Teams</span>
                    <div class="mt-2 font-mono text-2xl font-black text-slate-900 dark:text-white">{{ $t['registered_teams'] }}</div>
                    <span class="mt-1 block text-[11px] text-slate-500 dark:text-slate-400">Across 3 sporting codes</span>
                </div>
                <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-4 dark:border-slate-800/80 dark:bg-slate-800/40">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Total Prize Pool</span>
                    <div class="mt-2 font-mono text-2xl font-black text-slate-900 dark:text-white">{{ $t['total_prize_pool'] }}</div>
                    <span class="mt-1 block text-[11px] text-slate-500 dark:text-slate-400">Escrow guaranteed payout</span>
                </div>
                <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-4 dark:border-slate-800/80 dark:bg-slate-800/40">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Matches Played</span>
                    <div class="mt-2 font-mono text-2xl font-black text-emerald-600 dark:text-emerald-400">{{ $t['matches_played'] }}</div>
                    <span class="mt-1 block text-[11px] text-slate-500 dark:text-slate-400">Completed tournament games</span>
                </div>
            </div>
        </section>

        {{-- Tournaments Roster Cards --}}
        <section class="space-y-4">
            <h3 class="text-sm font-bold text-slate-900 dark:text-white">Active Tournaments & League Brackets</h3>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                @foreach($t['tournaments'] as $tourn)
                    <div class="rounded-xl border border-slate-200/90 bg-white p-5 shadow-xs transition hover:border-emerald-300 dark:border-slate-800 dark:bg-slate-900 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between">
                                <span class="rounded bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">
                                    {{ $tourn['sport'] }}
                                </span>
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                    {{ $tourn['status'] }}
                                </span>
                            </div>

                            <h4 class="mt-3 font-bold text-slate-900 dark:text-white text-sm leading-snug">{{ $tourn['name'] }}</h4>
                            <div class="mt-1 text-xs text-emerald-600 dark:text-emerald-400 font-bold">{{ $tourn['stage'] }}</div>

                            <div class="mt-4 space-y-1.5 text-xs border-t border-slate-100 pt-3 dark:border-slate-800">
                                <div class="flex items-center justify-between text-slate-600 dark:text-slate-300">
                                    <span>Squads</span>
                                    <span class="font-bold text-slate-900 dark:text-white">{{ $tourn['teams'] }}</span>
                                </div>
                                <div class="flex items-center justify-between text-slate-600 dark:text-slate-300">
                                    <span>Prize Purse</span>
                                    <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400">{{ $tourn['prize'] }}</span>
                                </div>
                                <div class="flex items-center justify-between text-slate-600 dark:text-slate-300">
                                    <span>Venues</span>
                                    <span class="font-medium text-slate-700 dark:text-slate-200">{{ $tourn['courts'] }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 pt-2 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-[11px] text-slate-400">
                            <span>{{ $tourn['dates'] }}</span>
                            <span class="font-bold text-emerald-600 dark:text-emerald-400 cursor-pointer">Live Brackets &rarr;</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    </div>
</x-filament-panels::page>
