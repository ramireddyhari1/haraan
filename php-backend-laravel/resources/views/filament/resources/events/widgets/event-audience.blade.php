{{-- Use the block php directive below, never the inline php() form with a nested
     call — it mis-parses in this Blade version and silently breaks the view's
     conditional compilation. (Also: do not write a directive token with its at-sign
     inside a blade comment — Blade compiles it and breaks the view.) --}}
@php
    $b = $this->getBreakdown();
@endphp
<x-filament-widgets::widget>
    <x-filament::section collapsible>
        <x-slot name="heading">Audience</x-slot>
        <x-slot name="description">Who bought — from the real accounts behind {{ number_format($b['buyers']) }} paid {{ \Illuminate\Support\Str::plural('buyer', $b['buyers']) }}</x-slot>

        {{-- Each dimension is a card: an icon header, a segmented distribution bar
             and a legend. Dimensions with no real data (only "Unknown") show a
             muted, honest state instead of a loud full bar. --}}
        <style>
            .eau2-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:14px; }
            @media(max-width:760px){ .eau2-grid{ grid-template-columns:1fr; } }
            .eau2-card { border:1px solid var(--hrn-border,#e2e8f0); border-radius:12px; padding:15px 16px; background:rgb(120 120 120 / .04); }
            .eau2-head { display:flex; align-items:center; gap:9px; margin-bottom:13px; }
            .eau2-ic { width:30px; height:30px; border-radius:8px; display:flex; align-items:center; justify-content:center; flex:none; }
            .eau2-ic svg { width:16px; height:16px; }
            .eau2-title { font-size:12px; font-weight:700; letter-spacing:.05em; text-transform:uppercase; color:var(--hrn-ink-2,#64748b); }
            .eau2-seg { display:flex; height:10px; border-radius:99px; overflow:hidden; gap:2px; margin-bottom:12px; }
            .eau2-seg > span { display:block; height:100%; }
            .eau2-legend { display:flex; flex-direction:column; gap:7px; }
            .eau2-lr { display:flex; align-items:center; justify-content:space-between; gap:10px; font-size:13px; }
            .eau2-lr .nm { color:var(--hrn-ink,#0f172a); }
            .eau2-lr .nm.mut { color:var(--hrn-ink-3,#94a3b8); }
            .eau2-lr .vl { color:var(--hrn-ink-2,#64748b); font-variant-numeric:tabular-nums; white-space:nowrap; }
            .eau2-dot { display:inline-block; width:8px; height:8px; border-radius:2px; margin-right:8px; vertical-align:1px; }
            .eau2-empty-bar { height:10px; border-radius:99px; margin-bottom:11px;
                background:repeating-linear-gradient(45deg, rgb(120 120 120 / .1), rgb(120 120 120 / .1) 6px, transparent 6px, transparent 12px);
                border:1px dashed rgb(120 120 120 / .3); }
            .eau2-empty-txt { display:flex; align-items:center; gap:7px; font-size:12.5px; color:var(--hrn-ink-3,#94a3b8); }
            .eau2-empty-txt svg { width:15px; height:15px; flex:none; }
        </style>

        @if ($b['buyers'] === 0)
            <div style="text-align:center;padding:22px 12px;color:var(--hrn-ink-3);font-size:13px;">
                No paid buyers yet — audience breakdown appears once tickets sell.
            </div>
        @else
            <div class="eau2-grid">
                @foreach ($b['dimensions'] as $dim)
                    <div class="eau2-card">
                        <div class="eau2-head">
                            <span class="eau2-ic" style="background:{{ $dim['hasReal'] ? 'rgb(37 99 235 / .10)' : 'rgb(120 120 120 / .10)' }};">
                                <span style="color:{{ $dim['hasReal'] ? '#2563eb' : '#94a3b8' }}; display:flex;">{!! svg($dim['icon'])->toHtml() !!}</span>
                            </span>
                            <span class="eau2-title">{{ $dim['title'] }}</span>
                        </div>

                        @if ($dim['hasReal'])
                            <div class="eau2-seg">
                                @foreach ($dim['rows'] as $r)
                                    @if ($r['pct'] > 0)
                                        <span style="width:{{ max(2, $r['pct']) }}%;background:{{ $r['color'] }};"></span>
                                    @endif
                                @endforeach
                            </div>
                            <div class="eau2-legend">
                                @foreach ($dim['rows'] as $r)
                                    <div class="eau2-lr">
                                        <span class="nm {{ $r['gray'] ? 'mut' : '' }}"><span class="eau2-dot" style="background:{{ $r['color'] }};"></span>{{ $r['label'] }}</span>
                                        <span class="vl">{{ number_format($r['count']) }} · {{ $r['pct'] }}%</span>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="eau2-empty-bar"></div>
                            <div class="eau2-empty-txt">
                                {!! svg('heroicon-m-information-circle')->toHtml() !!}
                                <span>{{ $dim['empty'] }}</span>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
