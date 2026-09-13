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
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/>
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2.5">
                            <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white md:text-2xl">
                                Staff & Field Operations Command
                            </h1>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                Roster Synchronized
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Court marshal deployment, certified referees, reception cashier shifts, and incident escalation rules.
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2.5">
                    <x-filament::button wire:click="pingStaff" color="success" icon="heroicon-o-signal">
                        Broadcast Shift Handover
                    </x-filament::button>
                </div>
            </div>

            {{-- 4 Metric Cards --}}
            <div class="relative z-10 mt-6 grid grid-cols-2 gap-4 md:grid-cols-4">
                <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-4 dark:border-slate-800/80 dark:bg-slate-800/40">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">On Duty Now</span>
                    <div class="mt-2 font-mono text-2xl font-black text-emerald-600 dark:text-emerald-400">{{ $t['on_duty_count'] }} Staff</div>
                    <span class="mt-1 block text-[11px] text-emerald-700 dark:text-emerald-300 font-medium">Across all turf arenas</span>
                </div>
                <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-4 dark:border-slate-800/80 dark:bg-slate-800/40">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Attendance Rate</span>
                    <div class="mt-2 font-mono text-2xl font-black text-slate-900 dark:text-white">{{ $t['attendance_rate'] }}</div>
                    <span class="mt-1 block text-[11px] text-slate-500 dark:text-slate-400">Zero unexcused absences</span>
                </div>
                <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-4 dark:border-slate-800/80 dark:bg-slate-800/40">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Shifts Logged</span>
                    <div class="mt-2 font-mono text-2xl font-black text-slate-900 dark:text-white">{{ $t['shifts_today'] }}</div>
                    <span class="mt-1 block text-[11px] text-slate-500 dark:text-slate-400">Completed or ongoing</span>
                </div>
                <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-4 dark:border-slate-800/80 dark:bg-slate-800/40">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Safety Index</span>
                    <div class="mt-2 font-mono text-2xl font-black text-emerald-600 dark:text-emerald-400">{{ $t['safety_score'] }}</div>
                    <span class="mt-1 block text-[11px] text-slate-500 dark:text-slate-400">Certified first-aiders on site</span>
                </div>
            </div>
        </section>

        {{-- Deployment Matrix & Roster Table --}}
        <section class="grid grid-cols-1 gap-6 lg:grid-cols-12">
            {{-- Col 1-4: Roles Breakdown --}}
            <div class="rounded-xl border border-slate-200/90 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-slate-900 lg:col-span-4">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Active Duty Deployment</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">Staff distribution by operational designation.</p>

                <div class="mt-4 space-y-3">
                    @foreach($t['roles'] as $role)
                        <div class="rounded-lg border border-slate-100 bg-slate-50/70 p-2.5 dark:border-slate-800 dark:bg-slate-800/40">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-semibold text-slate-900 dark:text-white">{{ $role['name'] }}</span>
                                <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400">{{ $role['duty'] }} on duty</span>
                            </div>
                            <div class="mt-1 text-[10px] text-slate-400">
                                {{ $role['count'] }} total staff rostered in department
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Col 5-12: Staff Shift Roster --}}
            <div class="rounded-xl border border-slate-200/90 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-slate-900 lg:col-span-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">Live Staff Assignment Roster</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Current shift allocations, venue location, and performance rating.</p>
                    </div>
                </div>

                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="border-b border-slate-200/80 bg-slate-50/80 font-bold text-slate-600 dark:border-slate-800 dark:bg-slate-800/60 dark:text-slate-300">
                            <tr>
                                <th class="p-2.5">Staff Member</th>
                                <th class="p-2.5">Designation</th>
                                <th class="p-2.5">Venue Location</th>
                                <th class="p-2.5">Shift Hours</th>
                                <th class="p-2.5">Status</th>
                                <th class="p-2.5 text-right">Score</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach($t['staff_roster'] as $s)
                                <tr>
                                    <td class="p-2.5 font-bold text-slate-900 dark:text-white">{{ $s['name'] }}</td>
                                    <td class="p-2.5 text-slate-600 dark:text-slate-300">{{ $s['role'] }}</td>
                                    <td class="p-2.5 font-medium text-slate-800 dark:text-slate-200">{{ $s['venue'] }}</td>
                                    <td class="p-2.5 font-mono text-slate-500 dark:text-slate-400">{{ $s['shift'] }}</td>
                                    <td class="p-2.5">
                                        @if($s['status'] === 'On Duty')
                                            <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300">
                                                ● On Duty
                                            </span>
                                        @elseif($s['status'] === 'Break')
                                            <span class="rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-bold text-amber-700 border border-amber-200 dark:bg-amber-950/40 dark:text-amber-300">
                                                On Break
                                            </span>
                                        @else
                                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                                                Scheduled
                                            </span>
                                        @endif
                                    </td>
                                    <td class="p-2.5 text-right font-mono font-black text-emerald-600 dark:text-emerald-400">{{ $s['score'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</x-filament-panels::page>
