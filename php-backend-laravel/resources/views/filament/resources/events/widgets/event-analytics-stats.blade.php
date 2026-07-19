{{-- Custom analytics grid. Each card is sorted into one of three treatments by
     what its number MEANS, so the decoration never lies:
       - meter : 0–100% with a real ceiling  → thin fill bar
       - split : two parts of a whole        → one stacked bar
       - plain : a count / fact, no max       → value + status chip only
     Colours resolve through per-card `eas-c-*` tokens (light + dark). --}}
<x-filament-widgets::widget
    :attributes="(new \Illuminate\View\ComponentAttributeBag)->class(['fi-wi-stats-overview'])"
>
    <style>
        .eas-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 0.75rem; }
        @media (max-width: 1024px) { .eas-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 640px) {
            .eas-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.55rem; }
            .eas-card { padding: 0.75rem 0.8rem; min-height: 102px; }
            .eas-value { font-size: 1.35rem; }
            .eas-label { font-size: 0.62rem; letter-spacing: .03em; }
            .eas-chip { font-size: 0.64rem; padding: 0.16rem 0.5rem; }
        }
        @media (max-width: 380px) { .eas-grid { grid-template-columns: 1fr; } }

        .eas-card {
            position: relative; overflow: hidden;
            background: #ffffff; border: 1px solid #e5e7eb;
            box-shadow: 0 1px 2px rgb(0 0 0 / 0.04);
            border-radius: 0.75rem; padding: 0.9rem 1rem; min-height: 118px;
            display: flex; flex-direction: column; gap: 0.5rem;
            transition: border-color .15s ease, transform .15s ease, box-shadow .15s ease;
        }
        .dark .eas-card { background: #1a1f2b; border-color: rgb(255 255 255 / 0.08); box-shadow: none; }
        .eas-card--clickable { cursor: pointer; }
        .eas-card--clickable:hover { border-color: var(--eas); transform: translateY(-1px); box-shadow: 0 4px 12px rgb(0 0 0 / 0.08); }

        .eas-label { font-size: 0.7rem; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: #6b7280; }
        .dark .eas-label { color: #9ca3af; }
        .eas-value { font-size: 1.65rem; font-weight: 700; line-height: 1.1; color: #111827; font-variant-numeric: tabular-nums; }
        .dark .eas-value { color: #f3f4f6; }

        .eas-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 0.5rem; }
        .eas-ic { color: var(--eas); flex: none; opacity: 0.9; }
        .eas-ic svg { width: 1.15rem; height: 1.15rem; }

        .eas-chip { align-self: flex-start; margin-top: auto; font-size: 0.72rem; font-weight: 500; line-height: 1.2;
            padding: 0.22rem 0.6rem; border-radius: 999px; background: var(--eas-bg); color: var(--eas-tx); }

        .eas-meter { height: 6px; border-radius: 999px; background: rgb(0 0 0 / 0.07); overflow: hidden; }
        .dark .eas-meter { background: rgb(255 255 255 / 0.09); }
        .eas-meter > span { display: block; height: 100%; border-radius: 999px; background: var(--eas); transition: width .4s ease; }

        .eas-split { display: flex; height: 8px; border-radius: 999px; overflow: hidden; background: rgb(0 0 0 / 0.07); gap: 2px; }
        .dark .eas-split { background: rgb(255 255 255 / 0.09); }
        .eas-split > span { display: block; height: 100%; }
        .eas-legend { display: flex; flex-wrap: wrap; gap: 0.15rem 0.75rem; font-size: 0.7rem; color: #6b7280; }
        .dark .eas-legend { color: #9ca3af; }
        .eas-legend b { font-weight: 600; color: #374151; }
        .dark .eas-legend b { color: #e5e7eb; }
        .eas-dot { display: inline-block; width: 7px; height: 7px; border-radius: 2px; margin-right: 5px; vertical-align: 1px; background: var(--eas); }

        /* Self-contained colour tokens — --eas: bar/icon/dot, --eas-bg/--eas-tx: chip. No Filament vars. */
        .eas-c-success { --eas: #16a34a; --eas-bg: rgb(22 163 74 / 0.12);  --eas-tx: #15803d; }
        .eas-c-danger  { --eas: #dc2626; --eas-bg: rgb(220 38 38 / 0.12);  --eas-tx: #b91c1c; }
        .eas-c-warning { --eas: #d97706; --eas-bg: rgb(217 119 6 / 0.14);  --eas-tx: #b45309; }
        .eas-c-info    { --eas: #2563eb; --eas-bg: rgb(37 99 235 / 0.12);  --eas-tx: #1d4ed8; }
        .eas-c-primary { --eas: #4f46e5; --eas-bg: rgb(79 70 229 / 0.12);  --eas-tx: #4338ca; }
        .eas-c-gray    { --eas: #9ca3af; --eas-bg: rgb(107 114 128 / 0.14); --eas-tx: #4b5563; }
        .dark .eas-c-success { --eas: #22c55e; --eas-bg: rgb(34 197 94 / 0.16);  --eas-tx: #4ade80; }
        .dark .eas-c-danger  { --eas: #ef4444; --eas-bg: rgb(239 68 68 / 0.16);  --eas-tx: #f87171; }
        .dark .eas-c-warning { --eas: #f59e0b; --eas-bg: rgb(245 158 11 / 0.16); --eas-tx: #fbbf24; }
        .dark .eas-c-info    { --eas: #3b82f6; --eas-bg: rgb(59 130 246 / 0.16); --eas-tx: #60a5fa; }
        .dark .eas-c-primary { --eas: #6366f1; --eas-bg: rgb(99 102 241 / 0.16); --eas-tx: #818cf8; }
        .dark .eas-c-gray    { --eas: #9ca3af; --eas-bg: rgb(156 163 175 / 0.16); --eas-tx: #d1d5db; }
    </style>

    @php
        // Label → drill-down metric key. Cards with their own `attributes`
        // (repeat-fan cards) keep their richer modal and skip this map.
        $metricMap = [
            'Event Views' => 'views', 'Conversion Rate' => 'conversion', 'Total Revenue' => 'revenue',
            'Avg per Attendee' => 'avg', 'Attendees' => 'attendees', 'Checked In' => 'checkedin',
            'No-shows' => 'noshows', 'Bookings' => 'bookings', 'Sell-through' => 'sellthrough',
            'Discounts Given' => 'discounts', 'Refunds / Cancelled' => 'refunds', 'Days to Event' => 'days',
        ];
    @endphp
    <div class="eas-grid">
        @foreach ($this->getCards() as $card)
            @php
                $attrs = $card['attributes'] ?? [];
                if (empty($attrs) && isset($metricMap[$card['label']])) {
                    $attrs = [
                        'wire:click' => "mountAction('drill', { metric: '" . $metricMap[$card['label']] . "' })",
                        'class'      => 'cursor-pointer',
                        'title'      => 'View the data behind this',
                    ];
                }
                $bag = (new \Illuminate\View\ComponentAttributeBag($attrs))
                    ->class(['eas-card', 'eas-c-' . ($card['color'] ?? 'gray'), 'eas-card--clickable' => ! empty($attrs)]);
            @endphp
            <div {{ $bag }}>
                <div class="eas-head">
                    <div>
                        <div class="eas-label">{{ $card['label'] }}</div>
                        <div class="eas-value">{{ $card['value'] }}</div>
                    </div>
                    @if (! empty($card['icon']))
                        <span class="eas-ic">{!! svg($card['icon'])->toHtml() !!}</span>
                    @endif
                </div>

                @if (($card['type'] ?? 'plain') === 'meter')
                    <div class="eas-meter"><span style="width: {{ max(0, min(100, (int) ($card['pct'] ?? 0))) }}%"></span></div>
                @elseif (($card['type'] ?? 'plain') === 'split')
                    <div class="eas-split">
                        @foreach ($card['segments'] as $seg)
                            @if ((int) $seg['pct'] > 0)
                                <span class="eas-c-{{ $seg['color'] }}" style="width: {{ min(100, (int) $seg['pct']) }}%; background: var(--eas);"></span>
                            @endif
                        @endforeach
                    </div>
                    <div class="eas-legend">
                        @foreach ($card['segments'] as $seg)
                            <span><span class="eas-dot eas-c-{{ $seg['color'] }}" style="background: var(--eas);"></span>{{ $seg['label'] }} <b>{{ $seg['value'] }}</b></span>
                        @endforeach
                    </div>
                @endif

                @if (! empty($card['chip']))
                    <span class="eas-chip">{{ $card['chip'] }}</span>
                @endif
            </div>
        @endforeach
    </div>

    <x-filament-actions::modals />
</x-filament-widgets::widget>
