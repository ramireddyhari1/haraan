@php($d = $this->getData())
<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Views &amp; traffic</x-slot>
        <x-slot name="description">Measured from every event-page open — real visitors, sources and devices</x-slot>

        <style>
            /* Ink hierarchy + track + border from the panel-wide theme (--hrn-*). */
            .evw{--evw-t:var(--hrn-ink);--evw-b:var(--hrn-ink-2);--evw-track:var(--hrn-track);--evw-bd:var(--hrn-border);}
            .evw-kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px;}
            @media(max-width:900px){.evw-kpis{grid-template-columns:repeat(2,1fr);}}
            .evw-kpi{border:1px solid var(--evw-bd);border-radius:12px;padding:12px 13px;}
            .evw-kl{font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--evw-b);}
            .evw-kv{font-size:21px;font-weight:800;color:var(--evw-t);margin-top:5px;letter-spacing:-.02em;}
            .evw-ks{font-size:11.5px;color:var(--evw-b);margin-top:2px;}
            .evw-cols{display:grid;grid-template-columns:1.3fr 1fr 1fr;gap:24px;}
            @media(max-width:900px){.evw-cols{grid-template-columns:1fr;}}
            .evw-h{font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--evw-b);margin:0 0 10px;}
            .evw-spark{display:flex;align-items:flex-end;gap:3px;height:70px;}
            .evw-bar{flex:1;background:linear-gradient(180deg,#3b82f6,#2563eb);border-radius:3px 3px 0 0;min-height:2px;}
            .evw-sparkx{display:flex;justify-content:space-between;font-size:10px;color:var(--evw-b);margin-top:5px;}
            .evw-row{margin-bottom:9px;}
            .evw-rt{display:flex;justify-content:space-between;font-size:12.5px;margin-bottom:4px;}
            .evw-rl{color:var(--evw-t);font-weight:600;} .evw-rv{color:var(--evw-b);}
            .evw-track2{height:7px;border-radius:999px;background:var(--evw-track);overflow:hidden;}
            .evw-fill{height:100%;border-radius:999px;}
        </style>

        @if (($d['total'] ?? 0) === 0)
            <div style="text-align:center;padding:22px 12px;color:var(--hrn-ink-3);font-size:13px;">
                No views recorded yet. Once people open this event's page (app or web), traffic analytics appear here.
            </div>
        @else
            @php
                $srcColors = ['#2563eb','#12b76a','#f59e0b','#8b5cf6','#ec4899','#0ea5e9','#94a3b8'];
                $devColors = ['#0ea5e9','#22c55e','#f59e0b','#6366f1','#94a3b8'];
            @endphp
            <div class="evw">
                <div class="evw-kpis">
                    <div class="evw-kpi"><div class="evw-kl">Total views</div><div class="evw-kv">{{ number_format($d['total']) }}</div><div class="evw-ks">{{ number_format($d['today']) }} today · {{ number_format($d['week']) }} this week</div></div>
                    <div class="evw-kpi"><div class="evw-kl">Unique visitors</div><div class="evw-kv">{{ number_format($d['unique']) }}</div><div class="evw-ks">distinct people</div></div>
                    <div class="evw-kpi"><div class="evw-kl">Returning</div><div class="evw-kv">{{ $d['returningPct'] }}%</div><div class="evw-ks">came back more than once</div></div>
                    <div class="evw-kpi"><div class="evw-kl">Peak hour</div><div class="evw-kv" style="font-size:17px;">{{ $d['peak'] }}</div><div class="evw-ks">busiest viewing window</div></div>
                </div>

                <div class="evw-cols">
                    <div>
                        <p class="evw-h">Daily views · 14 days</p>
                        <div class="evw-spark">
                            @foreach ($d['daily'] as $pt)
                                <div class="evw-bar" style="height:{{ max(2, $pt['pct']) }}%;" title="{{ $pt['day'] }}: {{ $pt['count'] }}"></div>
                            @endforeach
                        </div>
                        <div class="evw-sparkx"><span>{{ $d['daily'][0]['day'] ?? '' }}</span><span>{{ $d['daily'][count($d['daily'])-1]['day'] ?? '' }}</span></div>
                    </div>

                    <div>
                        <p class="evw-h">Traffic sources</p>
                        @foreach ($d['sources'] as $i => $row)
                            <div class="evw-row">
                                <div class="evw-rt"><span class="evw-rl">{{ $row['label'] }}</span><span class="evw-rv">{{ number_format($row['count']) }} · {{ $row['pct'] }}%</span></div>
                                <div class="evw-track2"><div class="evw-fill" style="width:{{ max(2,$row['pct']) }}%;background:{{ $srcColors[$i % count($srcColors)] }};"></div></div>
                            </div>
                        @endforeach
                    </div>

                    <div>
                        <p class="evw-h">Devices</p>
                        @foreach ($d['devices'] as $i => $row)
                            <div class="evw-row">
                                <div class="evw-rt"><span class="evw-rl">{{ $row['label'] }}</span><span class="evw-rv">{{ number_format($row['count']) }} · {{ $row['pct'] }}%</span></div>
                                <div class="evw-track2"><div class="evw-fill" style="width:{{ max(2,$row['pct']) }}%;background:{{ $devColors[$i % count($devColors)] }};"></div></div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
