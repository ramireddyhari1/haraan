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
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2.5">
                            <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white md:text-2xl">
                                Automated Operations Notifications
                            </h1>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                Multi-Channel Dispatch Active
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Instant WhatsApp QR tickets, SMS rain alerts, mobile match countdowns, and staff shift reconciliation pings.
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2.5">
                    <x-filament::button wire:click="testBroadcast" color="success" icon="heroicon-o-paper-airplane">
                        Test Multi-Channel Gateways
                    </x-filament::button>
                </div>
            </div>

            {{-- 4 Metric Cards --}}
            <div class="relative z-10 mt-6 grid grid-cols-2 gap-4 md:grid-cols-4">
                <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-4 dark:border-slate-800/80 dark:bg-slate-800/40">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Sent Today</span>
                    <div class="mt-2 font-mono text-2xl font-black text-slate-900 dark:text-white">{{ $t['total_sent_today'] }}</div>
                    <span class="mt-1 block text-[11px] text-slate-500 dark:text-slate-400">Operational alerts</span>
                </div>
                <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-4 dark:border-slate-800/80 dark:bg-slate-800/40">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Deliverability</span>
                    <div class="mt-2 font-mono text-2xl font-black text-emerald-600 dark:text-emerald-400">{{ $t['delivery_rate'] }}</div>
                    <span class="mt-1 block text-[11px] text-emerald-700 dark:text-emerald-300 font-medium">99.8% inbox delivery</span>
                </div>
                <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-4 dark:border-slate-800/80 dark:bg-slate-800/40">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Average Latency</span>
                    <div class="mt-2 font-mono text-2xl font-black text-slate-900 dark:text-white">{{ $t['avg_latency'] }}</div>
                    <span class="mt-1 block text-[11px] text-slate-500 dark:text-slate-400">Event-to-device delivery</span>
                </div>
                <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-4 dark:border-slate-800/80 dark:bg-slate-800/40">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Failures</span>
                    <div class="mt-2 font-mono text-2xl font-black text-emerald-600 dark:text-emerald-400">{{ $t['failed_count'] }}</div>
                    <span class="mt-1 block text-[11px] text-slate-500 dark:text-slate-400">Auto-retried over SMS</span>
                </div>
            </div>
        </section>

        {{-- Channel Breakdown & Templates Matrix --}}
        <section class="grid grid-cols-1 gap-6 lg:grid-cols-12">
            {{-- Col 1-4: Channel Rails --}}
            <div class="rounded-xl border border-slate-200/90 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-slate-900 lg:col-span-4">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Communication Rails</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">Traffic split across delivery providers.</p>

                <div class="mt-4 space-y-3">
                    @foreach($t['channels'] as $c)
                        <div>
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-semibold text-slate-900 dark:text-white">{{ $c['name'] }}</span>
                                <span class="font-mono text-slate-500 dark:text-slate-400">{{ $c['pct'] }}% ({{ $c['volume'] }})</span>
                            </div>
                            <div class="mt-1 h-2 w-full rounded-full bg-slate-100 dark:bg-slate-800">
                                <div class="h-2 rounded-full" style="width: {{ $c['pct'] }}%; background-color: {{ $c['color'] }};"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Col 5-12: Templates Matrix --}}
            <div class="rounded-xl border border-slate-200/90 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-slate-900 lg:col-span-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">Automated Trigger Templates</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Trigger conditions and delivery channels.</p>
                    </div>
                </div>

                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="border-b border-slate-200/80 bg-slate-50/80 font-bold text-slate-600 dark:border-slate-800 dark:bg-slate-800/60 dark:text-slate-300">
                            <tr>
                                <th class="p-2.5">Template</th>
                                <th class="p-2.5">Primary Rail</th>
                                <th class="p-2.5">Trigger Condition</th>
                                <th class="p-2.5">Deliverability</th>
                                <th class="p-2.5 text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach($t['templates'] as $tpl)
                                <tr>
                                    <td class="p-2.5 font-bold text-slate-900 dark:text-white">{{ $tpl['title'] }}</td>
                                    <td class="p-2.5">
                                        <span class="rounded bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                            {{ $tpl['channel'] }}
                                        </span>
                                    </td>
                                    <td class="p-2.5 text-slate-600 dark:text-slate-400">{{ $tpl['trigger'] }}</td>
                                    <td class="p-2.5 font-mono font-bold text-emerald-600 dark:text-emerald-400">{{ $tpl['deliverability'] }}</td>
                                    <td class="p-2.5 text-right">
                                        <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300">
                                            {{ $tpl['status'] }}
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
