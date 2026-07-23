@php
    $f = $this->getFunnel();
    $hasData = collect($f['stages'] ?? [])->sum('count') > 0;
@endphp
<x-filament-widgets::widget>
    <x-filament::section collapsible>
        <x-slot name="heading">Conversion funnel</x-slot>
        <x-slot name="description">Viewed → checkout → paid, with drop-off at each step</x-slot>

        {{-- BMS-grade funnel: one continuous narrowing silhouette (SVG, geometry
             pre-computed in the widget) beside a numeric breakdown rail. Blue ramp
             is self-contained hex (light + dark); ink/green/red use the panel's
             --hrn-* theme tokens so both modes read correctly. --}}
        <style>
            .efn3-conv { display:flex; align-items:baseline; gap:9px; margin-bottom:18px; flex-wrap:wrap; }
            .efn3-conv b { font-size:30px; font-weight:800; color:var(--hrn-ok,#16a34a); letter-spacing:-.02em; line-height:1; }
            .efn3-conv span { font-size:12.5px; color:var(--hrn-ink-2,#64748b); }
            .efn3-caveat { font-size:12.5px; color:var(--hrn-ink-2,#64748b); margin-bottom:16px; line-height:1.5; }
            .efn3-wrap { display:flex; gap:34px; align-items:stretch; flex-wrap:wrap; }
            .efn3-viz { flex:1 1 300px; min-width:270px; }
            .efn3-viz svg { display:block; width:100%; height:auto; }
            .efn3-rail { flex:1 1 300px; min-width:260px; display:flex; flex-direction:column; }
            .efn3-card { display:flex; gap:13px; padding:13px 14px; border:1px solid rgb(120 120 120 / .2);
                border-radius:12px; background:rgb(120 120 120 / .05); }
            .efn3-card.paid { border-color:rgb(22 163 74 / .35); background:rgb(22 163 74 / .06); }
            .efn3-ic { flex:none; width:38px; height:38px; border-radius:10px; display:flex; align-items:center; justify-content:center; }
            .efn3-ic svg { width:19px; height:19px; }
            .efn3-main { flex:1; min-width:0; }
            .efn3-top { display:flex; align-items:baseline; justify-content:space-between; gap:10px; }
            .efn3-nm { font-size:14px; font-weight:600; color:var(--hrn-ink,#0f172a); }
            .efn3-ct { font-size:16px; font-weight:600; color:var(--hrn-ink,#0f172a); font-variant-numeric:tabular-nums; white-space:nowrap; }
            .efn3-card.paid .efn3-ct { color:var(--hrn-ok,#16a34a); }
            .efn3-ct u { color:var(--hrn-ink-3,#94a3b8); font-size:12px; font-weight:500; text-decoration:none; }
            .efn3-no { font-size:12px; color:var(--hrn-ink-3,#94a3b8); margin:1px 0 9px; }
            .efn3-no b { color:var(--hrn-ok,#16a34a); font-weight:600; }
            .efn3-track { height:6px; border-radius:99px; background:rgb(120 120 120 / .18); overflow:hidden; }
            .efn3-fill { height:100%; border-radius:99px; }
            .efn3-conn { display:flex; align-items:center; gap:9px; padding:7px 0 7px 19px; }
            .efn3-conn .ln { width:1.5px; height:20px; background:rgb(120 120 120 / .32); display:block; flex:none; }
            .efn3-conn .dc { display:flex; align-items:center; gap:6px; color:var(--hrn-down,#b91c1c); }
            .efn3-conn .dc svg { width:14px; height:14px; }
            .efn3-conn .dc span { font-size:11.5px; font-weight:600; }
        </style>

        @if (empty($f['stages']) || ! $hasData)
            <div style="text-align:center;padding:22px 12px;color:var(--hrn-ink-3);font-size:13px;">
                No funnel data yet — no views or bookings recorded for this event.
            </div>
        @else
            @if (! is_null($f['conversion']))
                <div class="efn3-conv">
                    <b>{{ $f['conversion'] }}%</b>
                    <span>overall conversion · viewed → paid</span>
                </div>
            @else
                <div class="efn3-caveat">
                    View tracking started recently — the overall conversion % becomes accurate once
                    tracked views catch up to order volume. Per-step drop-off below is already reliable.
                </div>
            @endif

            <div class="efn3-wrap">
                {{-- continuous funnel silhouette --}}
                <div class="efn3-viz">
                    <svg viewBox="0 0 {{ $f['svg']['width'] }} {{ $f['svg']['height'] }}" role="img"
                         aria-label="Conversion funnel narrowing across {{ count($f['stages']) }} steps"
                         style="font-family:ui-sans-serif,system-ui,-apple-system,'Segoe UI',sans-serif;">
                        <defs>
                            <linearGradient id="efn3grad" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0" stop-color="#9CC0EE"/>
                                <stop offset="0.5" stop-color="#4B8FE0"/>
                                <stop offset="1" stop-color="#1E5FA8"/>
                            </linearGradient>
                        </defs>
                        @foreach ($f['svg']['segments'] as $seg)
                            <polygon points="{{ $seg['points'] }}" fill="url(#efn3grad)"></polygon>
                            <text x="{{ $seg['tx'] }}" y="{{ $seg['ty'] }}" text-anchor="middle"
                                  fill="{{ $seg['dark'] ? '#08315e' : '#ffffff' }}"
                                  font-size="{{ $seg['fs'] }}" font-weight="700"
                                  style="font-variant-numeric:tabular-nums;">{{ number_format($seg['count']) }}</text>
                        @endforeach
                    </svg>
                </div>

                {{-- numeric breakdown rail --}}
                <div class="efn3-rail">
                    @foreach ($f['rail'] as $r)
                        @if (! is_null($r['drop']) && $r['drop'] > 0)
                            <div class="efn3-conn">
                                <span class="ln"></span>
                                <span class="dc">
                                    {!! svg('heroicon-m-arrow-trending-down')->toHtml() !!}
                                    <span>{{ $r['drop'] }}% drop · {{ number_format($r['lost']) }} {{ \Illuminate\Support\Str::plural('person', $r['lost']) }} lost</span>
                                </span>
                            </div>
                        @endif
                        <div class="efn3-card {{ $r['isLast'] ? 'paid' : '' }}">
                            <div class="efn3-ic" style="background:{{ $r['iconBg'] }};">
                                <span style="color:{{ $r['iconHex'] }}; display:flex;">{!! svg($r['icon'])->toHtml() !!}</span>
                            </div>
                            <div class="efn3-main">
                                <div class="efn3-top">
                                    <span class="efn3-nm">{{ $r['label'] }}</span>
                                    <span class="efn3-ct">{{ number_format($r['count']) }} <u>· {{ $r['pctTop'] }}%</u></span>
                                </div>
                                <div class="efn3-no">{{ $r['note'] }}@if (! is_null($r['retained'])) · <b>{{ $r['retained'] }}% retained</b>@endif</div>
                                <div class="efn3-track"><div class="efn3-fill" style="width:{{ max(3, $r['pctTop']) }}%; background:{{ $r['barHex'] }};"></div></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
