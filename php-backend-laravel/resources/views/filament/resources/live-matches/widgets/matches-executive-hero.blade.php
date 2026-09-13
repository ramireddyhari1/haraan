@php
    $t = $this->getTelemetry();
@endphp

<x-filament-widgets::widget>
    <style>
        .match-hero-root {
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
        .dark .match-hero-root {
            background: #0f172a;
            box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.08);
        }
        .match-card {
            background: #f8fafc;
            border: 1px solid #f1f5f9;
            border-radius: 12px;
            padding: 16px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .dark .match-card {
            background: #1e293b;
            border-color: #334155;
        }
    </style>

    <div class="match-hero-root relative overflow-hidden">
        {{-- Emerald / Amber Glow --}}
        <div class="pointer-events-none absolute -right-20 -top-20 h-64 w-64 rounded-full bg-emerald-500/10 blur-3xl"></div>
        <div class="pointer-events-none absolute -left-16 -bottom-16 h-56 w-56 rounded-full bg-teal-500/5 blur-3xl"></div>

        {{-- Header --}}
        <div class="relative z-10 flex flex-col gap-4 border-b border-slate-100 pb-4 dark:border-slate-800 md:flex-row md:items-center md:justify-between">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 border border-emerald-100 dark:bg-emerald-950/50 dark:text-emerald-400 dark:border-emerald-800">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </div>
                <div>
                    <div class="flex items-center gap-2.5">
                        <h2 class="text-lg font-bold tracking-tight text-slate-900 dark:text-white">
                            Match Operations & Live Scorer Network
                        </h2>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            Live Scoring Sync
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Live match timelines, certified referee assignments, tournament brackets, and fair-play monitoring.
                    </p>
                </div>
            </div>

            {{-- Match Integrity & Actions --}}
            <div class="flex items-center gap-3">
                <div class="flex items-center gap-3 rounded-xl border border-emerald-200/80 bg-gradient-to-r from-emerald-50/90 to-teal-50/70 px-3.5 py-2 shadow-sm dark:border-emerald-800/60 dark:from-emerald-950/30 dark:to-slate-900">
                    <div class="text-left">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">Match Integrity</div>
                        <div class="text-xs font-semibold text-slate-700 dark:text-slate-200">{{ $t['fair_play_rating'] }} Fair Play</div>
                    </div>
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-600 text-sm font-black text-white font-mono shadow-sm">
                        100%
                    </div>
                </div>
            </div>
        </div>

        {{-- 12-Column Grid --}}
        <div class="relative z-10 grid grid-cols-1 gap-4 lg:grid-cols-12">
            {{-- Col 1-4: Match Volume & Today's Schedule --}}
            <div class="match-card lg:col-span-4">
                <div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Today's Matches</span>
                        <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-bold text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300">
                            {{ $t['completion_rate'] }} On-Time
                        </span>
                    </div>
                    <div class="mt-2 flex items-baseline gap-3">
                        <span class="text-3xl font-black tracking-tight text-slate-900 dark:text-white font-mono">
                            {{ $t['live_count'] + $t['upcoming_count'] + $t['completed_count'] }}
                        </span>
                        <span class="text-xs text-slate-500 dark:text-slate-400">total scheduled today</span>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-3 gap-2 border-t border-slate-200/60 pt-3 text-center dark:border-slate-700/60">
                    <div class="rounded-lg bg-white p-2 border border-slate-200/70 dark:bg-slate-900/50 dark:border-slate-800">
                        <div class="flex items-center justify-center gap-1 text-[10px] font-bold uppercase text-red-600 dark:text-red-400">
                            <span class="h-1.5 w-1.5 rounded-full bg-red-500 animate-ping"></span> Live Now
                        </div>
                        <div class="mt-1 text-lg font-black text-red-600 dark:text-red-400 font-mono">{{ $t['live_count'] }}</div>
                    </div>
                    <div class="rounded-lg bg-white p-2 border border-slate-200/70 dark:bg-slate-900/50 dark:border-slate-800">
                        <div class="text-[10px] font-bold uppercase text-amber-600 dark:text-amber-400">Upcoming</div>
                        <div class="mt-1 text-lg font-black text-slate-900 dark:text-white font-mono">{{ $t['upcoming_count'] }}</div>
                    </div>
                    <div class="rounded-lg bg-white p-2 border border-slate-200/70 dark:bg-slate-900/50 dark:border-slate-800">
                        <div class="text-[10px] font-bold uppercase text-emerald-600 dark:text-emerald-400">Completed</div>
                        <div class="mt-1 text-lg font-black text-slate-900 dark:text-white font-mono">{{ $t['completed_count'] }}</div>
                    </div>
                </div>
            </div>

            {{-- Col 5-8: Officials & Tournament Links --}}
            <div class="match-card lg:col-span-4">
                <div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Referees & Officials</span>
                        <span class="inline-flex items-center gap-1 text-xs font-bold text-emerald-600 dark:text-emerald-400">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            {{ $t['referees_coverage'] }}
                        </span>
                    </div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-3xl font-black tracking-tight text-emerald-600 dark:text-emerald-400 font-mono">
                            {{ $t['active_officials'] }}
                        </span>
                        <span class="text-xs text-slate-500 dark:text-slate-400">certified match marshals on pitch</span>
                    </div>
                </div>

                <div class="mt-4 rounded-lg border border-slate-200/80 bg-white p-3 dark:border-slate-700/80 dark:bg-slate-900/60">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-600 dark:text-slate-300">Active Tournaments</span>
                        <span class="rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-bold text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">
                            {{ $t['tournaments_active'] }} Leagues Live
                        </span>
                    </div>
                    <div class="mt-2 space-y-1 text-xs">
                        <div class="flex items-center justify-between text-slate-600 dark:text-slate-300">
                            <span>🏆 Hyderabad Premier Turf League</span>
                            <span class="font-mono text-[10px] text-emerald-600 font-bold">Matchday 4</span>
                        </div>
                        <div class="flex items-center justify-between text-slate-600 dark:text-slate-300">
                            <span>🏸 Monsoon Masters Open</span>
                            <span class="font-mono text-[10px] text-emerald-600 font-bold">Semi Finals</span>
                        </div>
                        <div class="flex items-center justify-between text-slate-600 dark:text-slate-300">
                            <span>🏏 Corporate Box Cup 2026</span>
                            <span class="font-mono text-[10px] text-emerald-600 font-bold">Group B</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Col 9-12: Live Scores Ticker --}}
            <div class="match-card lg:col-span-4">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Live Match Radar</span>
                    <span class="flex items-center gap-1 text-[11px] font-bold text-red-600 dark:text-red-400">
                        <span class="h-2 w-2 rounded-full bg-red-500 animate-ping"></span> Live Broadcast
                    </span>
                </div>

                <div class="mt-2 space-y-2">
                    @foreach($t['live_feed'] as $match)
                        <div class="rounded-lg border border-slate-200/80 bg-white p-2.5 shadow-2xs dark:border-slate-800 dark:bg-slate-900">
                            <div class="flex items-center justify-between">
                                <span class="truncate text-xs font-bold text-slate-900 dark:text-white max-w-[180px]">{{ $match['title'] }}</span>
                                <span class="font-mono text-xs font-black text-emerald-600 dark:text-emerald-400">{{ $match['score'] }}</span>
                            </div>
                            <div class="mt-1 flex items-center justify-between text-[10px] text-slate-500 dark:text-slate-400">
                                <span>{{ $match['court'] }} · {{ $match['clock'] }}</span>
                                <span class="truncate max-w-[100px]">{{ $match['referee'] }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
