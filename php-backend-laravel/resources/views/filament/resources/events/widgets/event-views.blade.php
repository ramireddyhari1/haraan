{{-- Use the block php directive below, never the inline php() form with a nested
     call — it mis-parses in this Blade version and breaks the view's conditional
     compilation. Also: do not write a directive token with its at-sign inside a
     blade comment; Blade compiles it and breaks the view. --}}
@php
    $d = $this->getData();
@endphp
<x-filament-widgets::widget>
    <x-filament::section collapsible>
        <x-slot name="heading">Views &amp; traffic</x-slot>
        <x-slot name="description">Measured from every event-page open — real visitors, sources and devices</x-slot>

        <style>
            .evw2-kpis { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:22px; }
            @media(max-width:900px){ .evw2-kpis{ grid-template-columns:repeat(2,1fr); } }
            .evw2-kpi { border:1px solid var(--hrn-border,#e2e8f0); border-radius:12px; padding:12px 14px; background:rgb(120 120 120 / .04); }
            .evw2-kl { font-size:11px; font-weight:700; letter-spacing:.05em; text-transform:uppercase; color:var(--hrn-ink-2,#64748b); }
            .evw2-kv { font-size:22px; font-weight:800; color:var(--hrn-ink,#0f172a); margin-top:4px; letter-spacing:-.02em; font-variant-numeric:tabular-nums; }
            .evw2-kv.ok { color:var(--hrn-ok,#16a34a); }
            .evw2-ks { font-size:11.5px; color:var(--hrn-ink-2,#64748b); margin-top:3px; }
            .evw2-cols { display:grid; grid-template-columns:1.4fr 1fr 1fr; gap:26px; }
            @media(max-width:900px){ .evw2-cols{ grid-template-columns:1fr; gap:20px; } }
            .evw2-h { font-size:11px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:var(--hrn-ink-2,#64748b); margin:0 0 10px; }
            .evw2-spark { display:flex; align-items:flex-end; gap:4px; height:76px; }
            .evw2-b { flex:1; border-radius:3px 3px 0 0; min-height:2px; background:#60a5fa; }
            .evw2-b.peak { background:#2563eb; }
            .evw2-sparkx { display:flex; justify-content:space-between; font-size:10px; color:var(--hrn-ink-3,#94a3b8); margin-top:6px; }
            .evw2-seg { display:flex; height:8px; border-radius:99px; overflow:hidden; gap:2px; margin-bottom:10px; }
            .evw2-seg > span { display:block; height:100%; }
            .evw2-legend { display:flex; flex-direction:column; gap:6px; font-size:12.5px; }
            .evw2-lr { display:flex; justify-content:space-between; gap:10px; }
            .evw2-lr .nm { color:var(--hrn-ink,#0f172a); }
            .evw2-lr .vl { color:var(--hrn-ink-2,#64748b); font-variant-numeric:tabular-nums; white-space:nowrap; }
            .evw2-dot { display:inline-block; width:7px; height:7px; border-radius:2px; margin-right:6px; vertical-align:1px; }

            .evw2-empty { display:flex; gap:16px; align-items:flex-start; }
            .evw2-eic { flex:none; width:44px; height:44px; border-radius:12px; background:rgb(37 99 235 / .1); display:flex; align-items:center; justify-content:center; }
            .evw2-eic svg { width:22px; height:22px; color:#2563eb; }
            .evw2-et { font-size:15px; font-weight:700; color:var(--hrn-ink,#0f172a); }
            .evw2-ed { font-size:13px; color:var(--hrn-ink-2,#64748b); margin-top:3px; line-height:1.55; }
            .evw2-ed b { color:var(--hrn-ink,#0f172a); }
            .evw2-skel { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-top:20px; opacity:.55; }
            @media(max-width:900px){ .evw2-skel{ grid-template-columns:repeat(2,1fr); } }
            .evw2-sk { border:1px dashed rgb(120 120 120 / .35); border-radius:12px; padding:12px 13px; }
            .evw2-sk .a { width:50px; height:8px; border-radius:99px; background:rgb(120 120 120 / .18); }
            .evw2-sk .c { width:34px; height:16px; border-radius:5px; background:rgb(120 120 120 / .14); margin-top:9px; }
        </style>

        @if (($d['total'] ?? 0) === 0)
            <div class="evw2-empty">
                <span class="evw2-eic">{!! svg('heroicon-m-chart-bar')->toHtml() !!}</span>
                <div>
                    <div class="evw2-et">Detailed traffic is warming up</div>
                    <div class="evw2-ed">
                        @if (($d['counter'] ?? 0) > 0)
                            This event has <b>{{ number_format($d['counter']) }} page {{ \Illuminate\Support\Str::plural('open', $d['counter']) }}</b> on the counter. Visitor-level analytics — sources, devices and the 14-day trend — start filling in as new opens are tracked.
                        @else
                            Once people open this event's page (app or web), visitor-level analytics — sources, devices and the 14-day trend — appear here.
                        @endif
                    </div>
                </div>
            </div>
            <div class="evw2-skel">
                <div class="evw2-sk"><div class="a"></div><div class="c"></div></div>
                <div class="evw2-sk"><div class="a"></div><div class="c"></div></div>
                <div class="evw2-sk"><div class="a"></div><div class="c"></div></div>
                <div class="evw2-sk"><div class="a"></div><div class="c"></div></div>
            </div>
        @else
            @php
                $srcColors = ['#2563eb', '#12b76a', '#f59e0b', '#8b5cf6', '#ec4899', '#0ea5e9', '#94a3b8'];
                $devColors = ['#16a34a', '#0ea5e9', '#f59e0b', '#6366f1', '#94a3b8'];
                $peakIdx = collect($d['daily'])->search(fn ($p) => $p['pct'] >= 100);
            @endphp

            <div class="evw2-kpis">
                <div class="evw2-kpi"><div class="evw2-kl">Total views</div><div class="evw2-kv">{{ number_format($d['total']) }}</div><div class="evw2-ks">{{ number_format($d['today']) }} today · {{ number_format($d['week']) }} this week</div></div>
                <div class="evw2-kpi"><div class="evw2-kl">Unique</div><div class="evw2-kv">{{ number_format($d['unique']) }}</div><div class="evw2-ks">distinct people</div></div>
                <div class="evw2-kpi"><div class="evw2-kl">Returning</div><div class="evw2-kv ok">{{ $d['returningPct'] }}%</div><div class="evw2-ks">came back more than once</div></div>
                <div class="evw2-kpi"><div class="evw2-kl">Peak hour</div><div class="evw2-kv" style="font-size:17px;">{{ $d['peak'] }}</div><div class="evw2-ks">busiest viewing window</div></div>
            </div>

            <div class="evw2-cols">
                <div>
                    <p class="evw2-h">Daily views · 14 days</p>
                    <div class="evw2-spark">
                        @foreach ($d['daily'] as $i => $pt)
                            <div class="evw2-b {{ $i === $peakIdx ? 'peak' : '' }}" style="height:{{ max(2, $pt['pct']) }}%;" title="{{ $pt['day'] }}: {{ number_format($pt['count']) }}"></div>
                        @endforeach
                    </div>
                    <div class="evw2-sparkx"><span>{{ $d['daily'][0]['day'] ?? '' }}</span><span>{{ $d['daily'][count($d['daily']) - 1]['day'] ?? '' }}</span></div>
                </div>

                <div>
                    <p class="evw2-h">Traffic sources</p>
                    <div class="evw2-seg">
                        @foreach ($d['sources'] as $i => $row)
                            @if ($row['pct'] > 0)<span style="width:{{ max(2, $row['pct']) }}%;background:{{ $srcColors[$i % count($srcColors)] }};"></span>@endif
                        @endforeach
                    </div>
                    <div class="evw2-legend">
                        @foreach ($d['sources'] as $i => $row)
                            <div class="evw2-lr"><span class="nm"><span class="evw2-dot" style="background:{{ $srcColors[$i % count($srcColors)] }};"></span>{{ $row['label'] }}</span><span class="vl">{{ number_format($row['count']) }} · {{ $row['pct'] }}%</span></div>
                        @endforeach
                    </div>
                </div>

                <div>
                    <p class="evw2-h">Devices</p>
                    <div class="evw2-seg">
                        @foreach ($d['devices'] as $i => $row)
                            @if ($row['pct'] > 0)<span style="width:{{ max(2, $row['pct']) }}%;background:{{ $devColors[$i % count($devColors)] }};"></span>@endif
                        @endforeach
                    </div>
                    <div class="evw2-legend">
                        @foreach ($d['devices'] as $i => $row)
                            <div class="evw2-lr"><span class="nm"><span class="evw2-dot" style="background:{{ $devColors[$i % count($devColors)] }};"></span>{{ $row['label'] }}</span><span class="vl">{{ number_format($row['count']) }} · {{ $row['pct'] }}%</span></div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
