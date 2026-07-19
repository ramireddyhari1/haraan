@php($cmp = $this->getComparison())
{{-- The widget root is UNCONDITIONAL: Livewire requires a single root tag on
     every render. Wrapping the whole widget in @if($cmp) made it render nothing
     when there's no earlier event to compare (e.g. an event with no paid
     bookings yet), which threw "missing root tag" on the next Livewire update.
     Emptiness is now handled INSIDE the widget, like the sibling widgets. --}}
<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Compared to your last event</x-slot>
        @if ($cmp)
            <x-slot name="description">This event vs. “{{ \Illuminate\Support\Str::limit($cmp['prevTitle'], 48) }}”</x-slot>
        @endif

        <style>
            /* Ink hierarchy + border from the panel-wide theme (--hrn-*). */
            .ecmp{--ecmp-t:var(--hrn-ink);--ecmp-b:var(--hrn-ink-2);--ecmp-bd:var(--hrn-border);}
            .ecmp-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;}
            @media(max-width:1000px){.ecmp-grid{grid-template-columns:repeat(2,1fr);}}
            @media(max-width:520px){.ecmp-grid{grid-template-columns:1fr;}}
            .ecmp-cell{border:1px solid var(--ecmp-bd);border-radius:12px;padding:12px 13px;}
            .ecmp-lbl{font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--ecmp-b);}
            .ecmp-cur{font-size:19px;font-weight:800;color:var(--ecmp-t);margin-top:6px;letter-spacing:-.01em;}
            .ecmp-prev{font-size:11.5px;color:var(--ecmp-b);margin-top:2px;}
            .ecmp-tag{display:inline-flex;align-items:center;gap:3px;font-size:11px;font-weight:700;padding:1px 7px;border-radius:999px;margin-left:6px;}
            .ecmp-good{color:#059669;background:rgba(16,185,129,.12);}
            .ecmp-bad{color:#dc2626;background:rgba(239,68,68,.12);}
            .ecmp-flat{color:#8a94a6;background:rgba(148,163,184,.14);}
        </style>

        @if ($cmp)
            <div class="ecmp">
                <div class="ecmp-grid">
                    @foreach ($cmp['rows'] as $r)
                        <div class="ecmp-cell">
                            <div class="ecmp-lbl">{{ $r['label'] }}</div>
                            <div class="ecmp-cur">
                                {{ $r['cur'] }}
                                <span class="ecmp-tag ecmp-{{ $r['dir'] }}">
                                    @if ($r['dir'] === 'good') ▲ @elseif ($r['dir'] === 'bad') ▼ @else — @endif
                                </span>
                            </div>
                            <div class="ecmp-prev">was {{ $r['prev'] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <div style="text-align:center;padding:22px 12px;color:var(--hrn-ink-3);font-size:13px;">
                No earlier event to compare against yet — this fills in once you’ve run another event.
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
