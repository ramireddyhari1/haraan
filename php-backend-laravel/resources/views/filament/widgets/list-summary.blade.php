{{-- Shared summary strip for list pages (see App\Filament\Widgets\ListSummaryWidget).
     Title, a row of figures split by hairlines, an optional split bar with its
     legend, an optional short list. Every value arrives from the database. --}}
@php
    $s = $this->getSummary();
    $split = $s['split'] ?? null;
    $list = $s['list'] ?? null;
    $palette = ['#2563eb', '#0f9d63', '#e8a33d', '#7c3aed', '#64748b', '#0ea5e9'];
@endphp

<x-filament-widgets::widget>
    <section class="hls">
        <header class="hls-head">
            <h3>{{ $s['title'] }}</h3>
            @isset($s['window'])<span>{{ $s['window'] }}</span>@endisset
        </header>

        <dl class="hls-stats" style="--n: {{ max(1, count($s['stats'])) }}">
            @foreach ($s['stats'] as $stat)
                <div class="hls-stat">
                    <dt>{{ $stat['label'] }}</dt>
                    <dd @class(['is-good' => ($stat['tone'] ?? null) === 'good', 'is-warn' => ($stat['tone'] ?? null) === 'warn'])>{{ $stat['value'] }}</dd>
                    @if (! empty($stat['sub']))<p>{{ $stat['sub'] }}</p>@endif
                </div>
            @endforeach
        </dl>

        @if ($split || $list)
            <div class="hls-lower">
                @if ($split)
                    <div class="hls-split">
                        <div class="hls-sub-title">{{ $split['label'] }}</div>
                        @if ($split['parts'] === [])
                            <p class="hls-empty">Nothing recorded yet.</p>
                        @else
                            <div class="hls-bar">
                                @foreach ($split['parts'] as $i => $part)
                                    <i style="width: {{ $part['pct'] }}%; background: {{ $palette[$i % count($palette)] }}" title="{{ $part['name'] }} · {{ $part['pct'] }}%"></i>
                                @endforeach
                            </div>
                            <ul class="hls-legend">
                                @foreach ($split['parts'] as $i => $part)
                                    <li>
                                        <b style="background: {{ $palette[$i % count($palette)] }}"></b>
                                        <span>{{ $part['name'] }}</span>
                                        <em>{{ $part['value'] }} · {{ $part['pct'] }}%</em>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endif

                @if ($list)
                    <div class="hls-list">
                        <div class="hls-sub-title">{{ $list['title'] }}</div>
                        @if ($list['rows'] === [])
                            <p class="hls-empty">{{ $list['empty'] }}</p>
                        @else
                            <ul>
                                @foreach ($list['rows'] as $row)
                                    <li>
                                        <div>
                                            <span class="hls-row-main">{{ $row['primary'] }}</span>
                                            @if (! empty($row['secondary']))<span class="hls-row-sub">{{ $row['secondary'] }}</span>@endif
                                        </div>
                                        @if (! empty($row['trailing']))<span class="hls-row-end">{{ $row['trailing'] }}</span>@endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endif
            </div>
        @endif
    </section>

    <style>
        .hls{background:#fff;border:1px solid #e6e9f0;border-radius:16px;box-shadow:0 1px 2px rgba(15,23,42,.04);overflow:hidden;}
        .hls-head{display:flex;align-items:baseline;justify-content:space-between;gap:12px;padding:14px 18px 0;}
        .hls-head h3{font-size:15px;font-weight:680;letter-spacing:-.012em;color:#0f172a;margin:0;}
        .hls-head span{font-size:12px;color:#94a3b8;}
        .hls-stats{display:grid;grid-template-columns:repeat(var(--n),minmax(0,1fr));margin:10px 0 0;border-top:1px solid #eef1f6;}
        .hls-stat{padding:13px 18px 14px;min-width:0;}
        .hls-stat + .hls-stat{border-left:1px solid #eef1f6;}
        .hls-stat dt{font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#64748b;}
        .hls-stat dd{margin:6px 0 0;font-size:22px;font-weight:740;letter-spacing:-.03em;color:#0f172a;font-variant-numeric:tabular-nums;}
        .hls-stat dd.is-good{color:#0f9d63;}
        .hls-stat dd.is-warn{color:#b45309;}
        .hls-stat p{margin:3px 0 0;font-size:12px;color:#94a3b8;}
        .hls-lower{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));border-top:1px solid #eef1f6;}
        .hls-lower > div{padding:13px 18px 16px;min-width:0;}
        .hls-lower > div + div{border-left:1px solid #eef1f6;}
        .hls-sub-title{font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#64748b;margin-bottom:10px;}
        .hls-empty{font-size:13px;color:#94a3b8;margin:0;}
        .hls-bar{display:flex;height:8px;border-radius:99px;overflow:hidden;background:#f1f4f9;gap:2px;}
        .hls-bar i{display:block;height:100%;}
        .hls-legend{list-style:none;margin:10px 0 0;padding:0;display:flex;flex-direction:column;gap:6px;}
        .hls-legend li{display:flex;align-items:center;gap:8px;font-size:12.5px;color:#334155;}
        .hls-legend b{width:8px;height:8px;border-radius:2px;flex:none;}
        .hls-legend em{margin-left:auto;font-style:normal;color:#64748b;font-variant-numeric:tabular-nums;}
        .hls-list ul{list-style:none;margin:0;padding:0;}
        .hls-list li{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:7px 0;}
        .hls-list li + li{border-top:1px solid #f1f4f9;}
        .hls-list li > div{min-width:0;}
        .hls-row-main{display:block;font-size:13px;font-weight:600;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
        .hls-row-sub{display:block;font-size:12px;color:#64748b;margin-top:1px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
        .hls-row-end{font-size:12.5px;font-weight:650;color:#334155;white-space:nowrap;font-variant-numeric:tabular-nums;}
        @media (max-width:900px){
            .hls-stats{grid-template-columns:repeat(2,minmax(0,1fr));}
            .hls-stat + .hls-stat{border-left:0;}
            .hls-stat:nth-child(even){border-left:1px solid #eef1f6;}
            .hls-stat:nth-child(n+3){border-top:1px solid #eef1f6;}
            .hls-lower > div + div{border-left:0;border-top:1px solid #eef1f6;}
        }
        .dark .hls{background:#111827;border-color:#1f2937;}
        .dark .hls-head h3,.dark .hls-stat dd,.dark .hls-row-main{color:#f8fafc;}
        .dark .hls-stats,.dark .hls-lower,.dark .hls-stat + .hls-stat,.dark .hls-lower > div + div,.dark .hls-list li + li{border-color:#1f2937;}
        .dark .hls-legend li,.dark .hls-row-end{color:#cbd5e1;}
        .dark .hls-bar{background:#1f2937;}
    </style>
</x-filament-widgets::widget>
