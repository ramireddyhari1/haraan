<x-filament-panels::page>
    @php
        // status → palette. Kept in the view so the PHP page stays presentation-free.
        $palette = [
            'ok'   => ['label' => 'Healthy', 'text' => 'text-emerald-600 dark:text-emerald-400', 'dot' => 'bg-emerald-500', 'tile' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-400/10 dark:text-emerald-400', 'bar' => 'bg-emerald-500', 'edge' => 'before:bg-emerald-500'],
            'warn' => ['label' => 'Watch',   'text' => 'text-amber-600 dark:text-amber-400',     'dot' => 'bg-amber-500',   'tile' => 'bg-amber-50 text-amber-600 dark:bg-amber-400/10 dark:text-amber-400',       'bar' => 'bg-amber-500',   'edge' => 'before:bg-amber-500'],
            'down' => ['label' => 'Down',     'text' => 'text-red-600 dark:text-red-400',         'dot' => 'bg-red-500',     'tile' => 'bg-red-50 text-red-600 dark:bg-red-400/10 dark:text-red-400',               'bar' => 'bg-red-500',     'edge' => 'before:bg-red-500'],
            'idle' => ['label' => 'Inactive', 'text' => 'text-gray-400 dark:text-gray-500',       'dot' => 'bg-gray-300 dark:bg-gray-600', 'tile' => 'bg-gray-100 text-gray-400 dark:bg-white/5 dark:text-gray-500', 'bar' => 'bg-gray-300', 'edge' => 'before:bg-gray-300 dark:before:bg-gray-600'],
        ];

        $counts  = collect($cards)->countBy('status');
        $down    = $counts['down'] ?? 0;
        $warn    = $counts['warn'] ?? 0;
        $ok      = $counts['ok'] ?? 0;
        $anyDown = $down > 0;
        $anyWarn = $warn > 0;

        $sections = [
            'data'     => ['label' => 'Data & cache',   'icon' => 'heroicon-m-circle-stack'],
            'host'     => ['label' => 'Host resources', 'icon' => 'heroicon-m-cpu-chip'],
            'services' => ['label' => 'Services',       'icon' => 'heroicon-m-squares-2x2'],
        ];
        $grouped = collect($cards)->groupBy('group');

        $hero = $anyDown
            ? ['grad' => 'from-red-500 to-rose-600',        'icon' => 'heroicon-o-exclamation-triangle', 'head' => 'Attention needed',        'sub' => $down . ' system' . ($down === 1 ? '' : 's') . ' down' . ($anyWarn ? ' · ' . $warn . ' to watch' : '')]
            : ($anyWarn
                ? ['grad' => 'from-amber-500 to-orange-500', 'icon' => 'heroicon-o-exclamation-circle',    'head' => 'All up — ' . $warn . ' to watch', 'sub' => 'No outages. Some metrics are elevated.']
                : ['grad' => 'from-emerald-500 to-teal-600', 'icon' => 'heroicon-o-check-circle',          'head' => 'All systems operational',  'sub' => 'Every probe is green.']);
    @endphp

    <div wire:poll.10s="refresh" class="space-y-8">

        {{-- ── Hero status banner ───────────────────────────────────────────── --}}
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br {{ $hero['grad'] }} px-6 py-5 text-white shadow-lg">
            <div class="relative z-10 flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-white/20 ring-1 ring-white/30">
                        <x-filament::icon :icon="$hero['icon']" class="h-7 w-7" />
                    </div>
                    <div>
                        <p class="text-lg font-bold leading-tight">{{ $hero['head'] }}</p>
                        <p class="text-sm text-white/80">{{ $hero['sub'] }}</p>
                    </div>
                </div>

                {{-- Health tally --}}
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-sm font-semibold ring-1 ring-white/20">
                        <span class="h-2 w-2 rounded-full bg-white"></span>{{ $ok }} healthy
                    </span>
                    @if ($anyWarn)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-sm font-semibold ring-1 ring-white/20">
                            <span class="h-2 w-2 rounded-full bg-white/70"></span>{{ $warn }} watch
                        </span>
                    @endif
                    @if ($anyDown)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-white/25 px-3 py-1 text-sm font-semibold ring-1 ring-white/30">
                            <span class="h-2 w-2 rounded-full bg-white"></span>{{ $down }} down
                        </span>
                    @endif
                </div>
            </div>

            {{-- Auto-refresh footer --}}
            <div class="relative z-10 mt-4 flex items-center gap-2 text-xs text-white/70">
                <span class="relative flex h-2 w-2">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-white opacity-75"></span>
                    <span class="relative inline-flex h-2 w-2 rounded-full bg-white"></span>
                </span>
                Live · auto-refresh every 10s · last checked {{ $checkedAt }}
            </div>

            {{-- Decorative wash --}}
            <div class="pointer-events-none absolute -right-8 -top-10 h-40 w-40 rounded-full bg-white/10 blur-2xl"></div>
        </div>

        {{-- ── Grouped health cards ─────────────────────────────────────────── --}}
        @foreach ($sections as $key => $section)
            @if ($grouped->has($key))
                <section class="space-y-3">
                    <div class="flex items-center gap-2 px-1">
                        <x-filament::icon :icon="$section['icon']" class="h-4 w-4 text-gray-400" />
                        <h2 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $section['label'] }}</h2>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($grouped[$key] as $c)
                            @php $s = $palette[$c['status']] ?? $palette['idle']; @endphp
                            <div class="group relative overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-md dark:border-white/10 dark:bg-gray-900
                                        before:absolute before:inset-y-0 before:left-0 before:w-1 {{ $s['edge'] }}">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $s['tile'] }}">
                                            <x-filament::icon :icon="$c['icon']" class="h-5 w-5" />
                                        </div>
                                        <span class="text-sm font-medium text-gray-600 dark:text-gray-300">{{ $c['title'] }}</span>
                                    </div>
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-50 px-2.5 py-1 text-xs font-semibold {{ $s['text'] }} ring-1 ring-inset ring-gray-200 dark:bg-white/5 dark:ring-white/10">
                                        <span class="h-1.5 w-1.5 rounded-full {{ $s['dot'] }}"></span>{{ $s['label'] }}
                                    </span>
                                </div>

                                <p class="mt-4 text-2xl font-bold tracking-tight text-gray-950 dark:text-white">{{ $c['value'] }}</p>

                                @if (! is_null($c['meter'] ?? null))
                                    <div class="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                                        <div class="h-full rounded-full {{ $s['bar'] }} transition-all duration-500" style="width: {{ max(2, min(100, (int) $c['meter'])) }}%"></div>
                                    </div>
                                @endif

                                <p class="mt-2 text-xs leading-relaxed text-gray-500 dark:text-gray-400">{{ $c['sub'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
        @endforeach
    </div>
</x-filament-panels::page>
