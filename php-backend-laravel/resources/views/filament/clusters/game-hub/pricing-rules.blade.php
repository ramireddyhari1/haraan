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
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2.5">
                            <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white md:text-2xl">
                                Dynamic Pricing & Surge Yield Engine
                            </h1>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                Yield Optimizer Active
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Automated prime-time surge rates, weekend yield multipliers, weather guarantees, and corporate package tiers.
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2.5">
                    <x-filament::button wire:click="toggleSurge" color="success" icon="heroicon-o-bolt">
                        Recalculate Yield Curves
                    </x-filament::button>
                </div>
            </div>

            {{-- 4 Metric Cards --}}
            <div class="relative z-10 mt-6 grid grid-cols-2 gap-4 md:grid-cols-4">
                <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-4 dark:border-slate-800/80 dark:bg-slate-800/40">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Standard Base Rate</span>
                    <div class="mt-2 font-mono text-2xl font-black text-slate-900 dark:text-white">{{ $t['base_rate'] }}</div>
                    <span class="mt-1 block text-[11px] text-slate-500 dark:text-slate-400">Off-peak baseline slot fee</span>
                </div>
                <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-4 dark:border-slate-800/80 dark:bg-slate-800/40">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Evening Prime Surge</span>
                    <div class="mt-2 font-mono text-2xl font-black text-emerald-600 dark:text-emerald-400">{{ $t['peak_multiplier'] }}</div>
                    <span class="mt-1 block text-[11px] text-emerald-700 dark:text-emerald-300 font-medium">18:00 - 23:00 Daily</span>
                </div>
                <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-4 dark:border-slate-800/80 dark:bg-slate-800/40">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Weekend Premium</span>
                    <div class="mt-2 font-mono text-2xl font-black text-slate-900 dark:text-white">{{ $t['weekend_surge'] }}</div>
                    <span class="mt-1 block text-[11px] text-slate-500 dark:text-slate-400">Sat-Sun automated surge</span>
                </div>
                <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-4 dark:border-slate-800/80 dark:bg-slate-800/40">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Yield Optimization</span>
                    <div class="mt-2 font-mono text-2xl font-black text-emerald-600 dark:text-emerald-400">{{ $t['ai_yield_score'] }}</div>
                    <span class="mt-1 block text-[11px] text-slate-500 dark:text-slate-400">Dynamic engine uplift</span>
                </div>
            </div>
        </section>

        {{-- Active Dynamic Pricing Rules Matrix --}}
        <section class="rounded-xl border border-slate-200/90 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">Active Pricing & Surge Rules Matrix</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Rules executed by the booking checkout engine before cart lock.</p>
                </div>
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="border-b border-slate-200/80 bg-slate-50/80 font-bold text-slate-600 dark:border-slate-800 dark:bg-slate-800/60 dark:text-slate-300">
                        <tr>
                            <th class="p-2.5">Rule Name</th>
                            <th class="p-2.5">Category</th>
                            <th class="p-2.5">Trigger Window</th>
                            <th class="p-2.5">Multiplier / Action</th>
                            <th class="p-2.5">Applied Venue Scope</th>
                            <th class="p-2.5 text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach($t['rules'] as $rule)
                            <tr>
                                <td class="p-2.5 font-bold text-slate-900 dark:text-white">{{ $rule['name'] }}</td>
                                <td class="p-2.5">
                                    <span class="rounded bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                        {{ $rule['category'] }}
                                    </span>
                                </td>
                                <td class="p-2.5 font-mono text-slate-600 dark:text-slate-300">{{ $rule['window'] }}</td>
                                <td class="p-2.5 font-mono font-black text-emerald-600 dark:text-emerald-400">{{ $rule['multiplier'] }}</td>
                                <td class="p-2.5 text-slate-600 dark:text-slate-400">{{ $rule['scope'] }}</td>
                                <td class="p-2.5 text-right">
                                    <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300">
                                        {{ $rule['status'] }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-filament-panels::page>
