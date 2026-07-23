{{-- Compact BMS-style summary strip for the Events list header: three columns
     divided by hairlines, big tabular numbers, and a capacity fill bar under
     tickets. Replaces three stacked stat cards so it stays dense on phones.
     Use the block php directive below, never the inline form with a nested call
     (it mis-parses); and never write a directive token inside a blade comment —
     Blade compiles it and breaks the view. --}}
@php
    $s = $this->getSummary();
@endphp

<x-filament-widgets::widget>
    <style>
        .els{display:flex;align-items:stretch;background:#fff;border-radius:16px;
            box-shadow:0 1px 3px rgba(11,18,32,.06),0 0 0 1px rgba(120,120,120,.11);
            padding:15px 4px;}
        .els-cell{flex:1 1 0;min-width:0;padding:1px 14px;
            display:flex;flex-direction:column;gap:6px;}
        .els-l{font-size:10.5px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;
            color:#8a94a6;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
        .els-v{font-size:27px;font-weight:800;letter-spacing:-.02em;line-height:1;
            color:#111827;font-variant-numeric:tabular-nums;}
        .els-s{font-size:11px;font-weight:500;color:#6b7280;
            white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
        .els-s b{font-weight:700;color:#0f766e;}
        .els-div{width:1px;background:rgba(120,120,120,.14);margin:5px 0;flex:0 0 auto;}
        .els-bar{height:5px;border-radius:999px;background:rgba(120,120,120,.15);
            overflow:hidden;margin:1px 0 1px;}
        .els-bar span{display:block;height:100%;border-radius:999px;
            background:linear-gradient(90deg,#3b82f6,#22d3ee);}

        .dark .els{background:#1a2130;box-shadow:0 0 0 1px rgba(255,255,255,.08);}
        .dark .els-v{color:#f3f4f6;}
        .dark .els-l{color:#7c8598;}
        .dark .els-s{color:#9aa4b5;}
        .dark .els-s b{color:#5eead4;}
        .dark .els-div{background:rgba(255,255,255,.10);}
        .dark .els-bar{background:rgba(255,255,255,.12);}

        @media (max-width:640px){
            .els{padding:13px 2px;border-radius:14px;}
            .els-cell{padding:1px 10px;gap:5px;}
            .els-v{font-size:22px;}
            .els-l{font-size:10px;letter-spacing:.04em;}
            .els-s{font-size:10.5px;}
        }
    </style>

    <div class="els">
        <div class="els-cell">
            <div class="els-l">Events</div>
            <div class="els-v">{{ number_format($s['total']) }}</div>
            <div class="els-s">
                @if ($s['total'] > 0)
                    <b>{{ $s['published'] }}</b> live · {{ $s['draft'] }} draft
                @else
                    None yet
                @endif
            </div>
        </div>

        <div class="els-div"></div>

        <div class="els-cell">
            <div class="els-l">Published</div>
            <div class="els-v">{{ number_format($s['published']) }}</div>
            <div class="els-s">
                @if ($s['upcoming'] > 0)<b>{{ $s['upcoming'] }}</b> upcoming @else none upcoming @endif
            </div>
        </div>

        <div class="els-div"></div>

        <div class="els-cell">
            <div class="els-l">Tickets sold</div>
            <div class="els-v">{{ number_format($s['sold']) }}</div>
            @if ($s['capacity'] > 0)
                <div class="els-bar"><span style="width:{{ min(100, max(2, $s['fillPct'])) }}%;"></span></div>
                <div class="els-s"><b>{{ $s['fillPct'] }}%</b> of {{ number_format($s['capacity']) }}</div>
            @else
                <div class="els-s">no capacity set</div>
            @endif
        </div>
    </div>
</x-filament-widgets::widget>
