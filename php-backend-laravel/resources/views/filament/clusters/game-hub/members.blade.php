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
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2.5">
                            <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white md:text-2xl">
                                Members & Player Community Command
                            </h1>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                Live Community Sync
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Member tier classification, season passholder perks, loyalty token balances, and player attendance habits.
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2.5">
                    <x-filament::button wire:click="creditPoints" color="success" icon="heroicon-o-sparkles">
                        Reward Active Cohort (+500 XP)
                    </x-filament::button>
                </div>
            </div>

            {{-- 4 Metric Cards --}}
            <div class="relative z-10 mt-6 grid grid-cols-2 gap-4 md:grid-cols-4">
                <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-4 dark:border-slate-800/80 dark:bg-slate-800/40">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Total Registered</span>
                    <div class="mt-2 font-mono text-2xl font-black text-slate-900 dark:text-white">{{ number_format($t['total_players']) }}</div>
                    <span class="mt-1 block text-[11px] text-slate-500 dark:text-slate-400">Verified player profiles</span>
                </div>
                <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-4 dark:border-slate-800/80 dark:bg-slate-800/40">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Active Monthly</span>
                    <div class="mt-2 font-mono text-2xl font-black text-emerald-600 dark:text-emerald-400">{{ number_format($t['active_members']) }}</div>
                    <span class="mt-1 block text-[11px] text-emerald-700 dark:text-emerald-300 font-medium">&gt; 1 booking in 30 days</span>
                </div>
                <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-4 dark:border-slate-800/80 dark:bg-slate-800/40">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Season Passholders</span>
                    <div class="mt-2 font-mono text-2xl font-black text-slate-900 dark:text-white">{{ $t['passholders'] }}</div>
                    <span class="mt-1 block text-[11px] text-slate-500 dark:text-slate-400">VIP priority auto-access</span>
                </div>
                <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-4 dark:border-slate-800/80 dark:bg-slate-800/40">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Loyalty Pool</span>
                    <div class="mt-2 font-mono text-2xl font-black text-emerald-600 dark:text-emerald-400">{{ $t['loyalty_points'] }}</div>
                    <span class="mt-1 block text-[11px] text-slate-500 dark:text-slate-400">Redeemable on slots</span>
                </div>
            </div>
        </section>

        {{-- Tier Distribution & Active Roster --}}
        <section class="grid grid-cols-1 gap-6 lg:grid-cols-12">
            {{-- Col 1-4: Tier Breakdown --}}
            <div class="rounded-xl border border-slate-200/90 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-slate-900 lg:col-span-4">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Player Tier Segmentation</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">Categorized by 90-day booking volume and tournament participation.</p>

                <div class="mt-4 space-y-3">
                    @foreach($t['tiers'] as $tier)
                        <div>
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-semibold text-slate-900 dark:text-white">{{ $tier['name'] }}</span>
                                <span class="font-mono text-slate-500 dark:text-slate-400">{{ number_format($tier['count']) }} ({{ $tier['pct'] }}%)</span>
                            </div>
                            <div class="mt-1 h-2 w-full rounded-full bg-slate-100 dark:bg-slate-800">
                                <div class="h-2 rounded-full" style="width: {{ $tier['pct'] }}%; background-color: {{ $tier['color'] }};"></div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-5 rounded-lg border border-slate-200/80 bg-slate-50/70 p-3 text-xs dark:border-slate-800 dark:bg-slate-800/40">
                    <div class="font-bold text-slate-900 dark:text-white">Tier Retention Engine</div>
                    <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">Players with 4+ bookings in 30 days are automatically bumped to Pro Squad with 5% slot cashback.</p>
                </div>
            </div>

            {{-- Col 5-12: Top Active Players Leaderboard --}}
            <div class="rounded-xl border border-slate-200/90 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-slate-900 lg:col-span-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">Top Active Fleet Members</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Highest court-hour engagement and loyalty point accumulation.</p>
                    </div>
                </div>

                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="border-b border-slate-200/80 bg-slate-50/80 font-bold text-slate-600 dark:border-slate-800 dark:bg-slate-800/60 dark:text-slate-300">
                            <tr>
                                <th class="p-2.5">Player</th>
                                <th class="p-2.5">Tier</th>
                                <th class="p-2.5">Matches</th>
                                <th class="p-2.5">Court Hours</th>
                                <th class="p-2.5">Spend</th>
                                <th class="p-2.5 text-right">Points</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach($t['top_players'] as $p)
                                <tr>
                                    <td class="p-2.5">
                                        <div class="font-bold text-slate-900 dark:text-white">{{ $p['name'] }}</div>
                                        <div class="font-mono text-[10px] text-slate-400">{{ $p['phone'] }}</div>
                                    </td>
                                    <td class="p-2.5">
                                        <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300">
                                            {{ $p['tier'] }}
                                        </span>
                                    </td>
                                    <td class="p-2.5 font-mono font-bold text-slate-900 dark:text-white">{{ $p['matches'] }}</td>
                                    <td class="p-2.5 font-mono text-slate-600 dark:text-slate-300">{{ $p['hours'] }}</td>
                                    <td class="p-2.5 font-mono font-bold text-slate-900 dark:text-white">{{ $p['spent'] }}</td>
                                    <td class="p-2.5 text-right font-mono font-black text-emerald-600 dark:text-emerald-400">{{ $p['points'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</x-filament-panels::page>
