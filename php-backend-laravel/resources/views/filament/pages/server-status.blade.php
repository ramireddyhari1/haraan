<x-filament-panels::page>
    {{-- Re-probes every 10s so the ops team watches health live without refreshing. --}}
    @php
        // status → visual mapping. Kept in the view so the PHP page stays presentation-free.
        $styles = [
            'ok'   => ['dot' => 'bg-success-500', 'text' => 'text-success-600', 'ring' => 'ring-success-500/20', 'label' => 'Healthy'],
            'warn' => ['dot' => 'bg-warning-500', 'text' => 'text-warning-600', 'ring' => 'ring-warning-500/20', 'label' => 'Watch'],
            'down' => ['dot' => 'bg-danger-500',  'text' => 'text-danger-600',  'ring' => 'ring-danger-500/20',  'label' => 'Down'],
            'idle' => ['dot' => 'bg-gray-300',    'text' => 'text-gray-500',    'ring' => 'ring-gray-400/10',   'label' => 'n/a'],
        ];
        $counts = collect($cards)->countBy('status');
        $anyDown = ($counts['down'] ?? 0) > 0;
        $anyWarn = ($counts['warn'] ?? 0) > 0;
    @endphp

    <div wire:poll.10s="refresh" class="space-y-6">

        {{-- Overall banner --}}
        <x-filament::section>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    @if ($anyDown)
                        <x-heroicon-o-exclamation-triangle class="h-7 w-7 text-danger-500" />
                        <div>
                            <p class="text-base font-semibold text-danger-600">Attention needed</p>
                            <p class="text-sm text-gray-500">{{ $counts['down'] }} system(s) down{{ $anyWarn ? ', '.$counts['warn'].' to watch' : '' }}.</p>
                        </div>
                    @elseif ($anyWarn)
                        <x-heroicon-o-exclamation-circle class="h-7 w-7 text-warning-500" />
                        <div>
                            <p class="text-base font-semibold text-warning-600">All up — {{ $counts['warn'] }} to watch</p>
                            <p class="text-sm text-gray-500">No outages. Some metrics are elevated.</p>
                        </div>
                    @else
                        <x-heroicon-o-check-circle class="h-7 w-7 text-success-500" />
                        <div>
                            <p class="text-base font-semibold text-success-600">All systems operational</p>
                            <p class="text-sm text-gray-500">Every probe is green.</p>
                        </div>
                    @endif
                </div>
                <p class="text-xs text-gray-400">Last checked {{ $checkedAt }} · auto-refresh 10s</p>
            </div>
        </x-filament::section>

        {{-- Health grid --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($cards as $c)
                @php $s = $styles[$c['status']] ?? $styles['idle']; @endphp
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm ring-1 {{ $s['ring'] }} dark:border-white/10 dark:bg-gray-900">
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-2 text-gray-500">
                            <x-filament::icon :icon="$c['icon']" class="h-5 w-5" />
                            <span class="text-sm font-medium">{{ $c['title'] }}</span>
                        </div>
                        <span class="flex items-center gap-1.5 text-xs font-semibold {{ $s['text'] }}">
                            <span class="h-2 w-2 rounded-full {{ $s['dot'] }}"></span>
                            {{ $s['label'] }}
                        </span>
                    </div>
                    <p class="mt-3 text-xl font-bold text-gray-950 dark:text-white">{{ $c['value'] }}</p>
                    <p class="mt-1 text-xs text-gray-500">{{ $c['sub'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
