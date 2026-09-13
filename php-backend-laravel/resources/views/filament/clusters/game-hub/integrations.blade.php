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
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2.5">
                            <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white md:text-2xl">
                                Hardware & API Integrations Command
                            </h1>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                IoT Telemetry Live
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Court floodlight relays, optical turnstiles, digital scoreboards, and payment gateway health webhooks.
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2.5">
                    <x-filament::button wire:click="syncAll" color="success" icon="heroicon-o-arrow-path">
                        Poll Hardware Fleet Heartbeat
                    </x-filament::button>
                </div>
            </div>

            {{-- 4 Metric Cards --}}
            <div class="relative z-10 mt-6 grid grid-cols-2 gap-4 md:grid-cols-4">
                <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-4 dark:border-slate-800/80 dark:bg-slate-800/40">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Connected Units</span>
                    <div class="mt-2 font-mono text-2xl font-black text-slate-900 dark:text-white">{{ $t['connected_devices'] }} Units</div>
                    <span class="mt-1 block text-[11px] text-slate-500 dark:text-slate-400">Floodlights, gates & boards</span>
                </div>
                <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-4 dark:border-slate-800/80 dark:bg-slate-800/40">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Online Rate</span>
                    <div class="mt-2 font-mono text-2xl font-black text-emerald-600 dark:text-emerald-400">{{ $t['controllers_online'] }}</div>
                    <span class="mt-1 block text-[11px] text-emerald-700 dark:text-emerald-300 font-medium">100% controllers responsive</span>
                </div>
                <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-4 dark:border-slate-800/80 dark:bg-slate-800/40">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Fleet Uptime</span>
                    <div class="mt-2 font-mono text-2xl font-black text-emerald-600 dark:text-emerald-400">{{ $t['uptime'] }}</div>
                    <span class="mt-1 block text-[11px] text-slate-500 dark:text-slate-400">30-day rolling reliability</span>
                </div>
                <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-4 dark:border-slate-800/80 dark:bg-slate-800/40">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Telemetry Stream</span>
                    <div class="mt-2 font-mono text-2xl font-black text-slate-900 dark:text-white">{{ $t['events_streamed'] }}</div>
                    <span class="mt-1 block text-[11px] text-slate-500 dark:text-slate-400">MQTT message throughput</span>
                </div>
            </div>
        </section>

        {{-- Connected Hardware & Webhook APIs --}}
        <section class="grid grid-cols-1 gap-6 lg:grid-cols-12">
            {{-- Col 1-5: Payment & Cloud Webhooks --}}
            <div class="rounded-xl border border-slate-200/90 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-slate-900 lg:col-span-5">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Cloud API Gateways</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">Third-party service connectivity and webhook latency.</p>

                <div class="mt-4 space-y-3">
                    @foreach($t['gateways'] as $gw)
                        <div class="rounded-lg border border-slate-100 bg-slate-50/70 p-3 dark:border-slate-800 dark:bg-slate-800/40">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-bold text-slate-900 dark:text-white">{{ $gw['name'] }}</span>
                                <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300">
                                    {{ $gw['status'] }}
                                </span>
                            </div>
                            <div class="mt-1 flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400">
                                <span>{{ $gw['role'] }}</span>
                                <span class="font-mono text-emerald-600 dark:text-emerald-400">{{ $gw['latency'] }} latency</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Col 6-12: Physical Device Fleet --}}
            <div class="rounded-xl border border-slate-200/90 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-slate-900 lg:col-span-7">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">Physical IoT Hardware Fleet</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Real-time status of on-premise relays and turnstiles.</p>
                    </div>
                </div>

                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="border-b border-slate-200/80 bg-slate-50/80 font-bold text-slate-600 dark:border-slate-800 dark:bg-slate-800/60 dark:text-slate-300">
                            <tr>
                                <th class="p-2.5">Device Name</th>
                                <th class="p-2.5">Court Location</th>
                                <th class="p-2.5">Protocol</th>
                                <th class="p-2.5">Ping</th>
                                <th class="p-2.5 text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach($t['devices'] as $d)
                                <tr>
                                    <td class="p-2.5">
                                        <div class="font-bold text-slate-900 dark:text-white">{{ $d['name'] }}</div>
                                        <div class="text-[10px] text-slate-400">{{ $d['type'] }}</div>
                                    </td>
                                    <td class="p-2.5 text-slate-600 dark:text-slate-300">{{ $d['court'] }}</td>
                                    <td class="p-2.5 font-mono text-[11px] text-slate-500 dark:text-slate-400">{{ $d['protocol'] }}</td>
                                    <td class="p-2.5 font-mono text-emerald-600 dark:text-emerald-400">{{ $d['ping'] }}</td>
                                    <td class="p-2.5 text-right">
                                        <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300">
                                            ● {{ $d['status'] }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</x-filament-panels::page>
