@php
    $cmp = $this->getComparison();
@endphp
{{-- The widget root is UNCONDITIONAL: Livewire requires a single root tag on
     every render. Wrapping the whole widget in @if($cmp) made it render nothing
     when there's no earlier event to compare (e.g. an event with no paid
     bookings yet), which threw "missing root tag" on the next Livewire update.
     Emptiness is handled INSIDE the widget, like the sibling widgets. Uses block
     @php (never inline @php with a nested call — it corrupts blade compilation). --}}
<x-filament-widgets::widget>
    <x-filament::section collapsible>
        <x-slot name="heading">Compared to your last event</x-slot>
        @if ($cmp)
            <x-slot name="description">This event vs. “{{ \Illuminate\Support\Str::limit($cmp['prevTitle'], 48) }}”</x-slot>
        @endif

        {{-- BMS-style comparison tiles: a delta pill (magnitude + direction,
             coloured good/bad by the metric's polarity) sits with each value. --}}
        <style>
            .ecmp2-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; }
            @media(max-width:1000px){ .ecmp2-grid{ grid-template-columns:repeat(2,1fr); } }
            @media(max-width:520px){ .ecmp2-grid{ grid-template-columns:1fr; } }
            .ecmp2-cell { border:1px solid var(--hrn-border,#e2e8f0); border-radius:12px; padding:13px 14px; background:rgb(120 120 120 / .04); }
            .ecmp2-head { display:flex; align-items:center; justify-content:space-between; gap:8px; margin-bottom:8px; }
            .ecmp2-lbl { font-size:11px; font-weight:700; letter-spacing:.05em; text-transform:uppercase; color:var(--hrn-ink-2,#64748b); }
            .ecmp2-pill { display:inline-flex; align-items:center; gap:3px; font-size:11px; font-weight:600; padding:2px 8px; border-radius:999px; white-space:nowrap; }
            .ecmp2-pill .ar { font-size:9px; line-height:1; }
            .ecmp2-good { color:#15803d; background:rgb(22 163 74 / .12); }
            .ecmp2-bad  { color:#b91c1c; background:rgb(220 38 38 / .10); }
            .ecmp2-flat { color:#6b7280; background:rgb(120 120 120 / .14); }
            .dark .ecmp2-good { color:#4ade80; }
            .dark .ecmp2-bad  { color:#f87171; }
            .dark .ecmp2-flat { color:#9ca3af; }
            .ecmp2-val { font-size:23px; font-weight:800; color:var(--hrn-ink,#0f172a); letter-spacing:-.01em; line-height:1.1; display:flex; align-items:baseline; gap:4px; font-variant-numeric:tabular-nums; }
            .ecmp2-star svg { width:15px; height:15px; color:#eab308; transform:translateY(2px); }
            .ecmp2-prev { font-size:11.5px; color:var(--hrn-ink-3,#94a3b8); margin-top:6px; }
        </style>

        @if ($cmp)
            <div class="ecmp2-grid">
                @foreach ($cmp['rows'] as $r)
                    <div class="ecmp2-cell">
                        <div class="ecmp2-head">
                            <span class="ecmp2-lbl">{{ $r['label'] }}</span>
                            <span class="ecmp2-pill ecmp2-{{ $r['dir'] }}">
                                <span class="ar">@if ($r['arrow'] === 'up')▲@elseif ($r['arrow'] === 'down')▼@else—@endif</span>{{ $r['delta'] }}
                            </span>
                        </div>
                        <div class="ecmp2-val">
                            {{ $r['cur'] }}
                            @if ($r['star'])<span class="ecmp2-star">{!! svg('heroicon-m-star')->toHtml() !!}</span>@endif
                        </div>
                        <div class="ecmp2-prev">was {{ $r['prev'] }}</div>
                    </div>
                @endforeach
            </div>
        @else
            <div style="text-align:center;padding:22px 12px;color:var(--hrn-ink-3);font-size:13px;">
                No earlier event to compare against yet — this fills in once you’ve run another event.
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
