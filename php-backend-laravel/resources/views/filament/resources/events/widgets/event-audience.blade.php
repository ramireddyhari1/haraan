@php($b = $this->getBreakdown())
<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Audience</x-slot>
        <x-slot name="description">Who bought — from the real accounts behind {{ number_format($b['buyers']) }} paid {{ \Illuminate\Support\Str::plural('buyer', $b['buyers']) }}</x-slot>

        <style>
            /* Ink hierarchy + track from the panel-wide theme (--hrn-*). */
            .eau{--eau-t:var(--hrn-ink);--eau-b:var(--hrn-ink-2);--eau-track:var(--hrn-track);}
            .eau-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:20px 28px;}
            @media(max-width:760px){.eau-grid{grid-template-columns:1fr;}}
            .eau-h{font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--eau-b);margin:0 0 10px;}
            .eau-row{margin-bottom:9px;}
            .eau-top{display:flex;justify-content:space-between;font-size:12.5px;margin-bottom:4px;}
            .eau-lbl{color:var(--eau-t);font-weight:600;}
            .eau-val{color:var(--eau-b);}
            .eau-bar{height:7px;border-radius:999px;background:var(--eau-track);overflow:hidden;}
            .eau-fill{height:100%;border-radius:999px;}
        </style>

        @php
            $palettes = [
                'gender'   => ['#2563eb', '#ec4899', '#8b5cf6', '#94a3b8'],
                'age'      => ['#12b76a', '#10b981', '#059669', '#047857', '#065f46', '#94a3b8'],
                'location' => ['#2563eb', '#3b82f6', '#60a5fa', '#93c5fd', '#bfdbfe'],
                'device'   => ['#0ea5e9', '#22c55e', '#f59e0b', '#94a3b8'],
            ];
        @endphp

        @if ($b['buyers'] === 0)
            <div style="text-align:center;padding:22px 12px;color:var(--hrn-ink-3);font-size:13px;">
                No paid buyers yet — audience breakdown appears once tickets sell.
            </div>
        @else
            <div class="eau">
                <div class="eau-grid">
                    @foreach (['gender' => 'Gender', 'age' => 'Age', 'location' => 'Top locations', 'device' => 'Device'] as $key => $title)
                        <div>
                            <p class="eau-h">{{ $title }}</p>
                            @forelse ($b[$key] as $idx => $row)
                                <div class="eau-row">
                                    <div class="eau-top">
                                        <span class="eau-lbl">{{ $row['label'] }}</span>
                                        <span class="eau-val">{{ number_format($row['count']) }} · {{ $row['pct'] }}%</span>
                                    </div>
                                    <div class="eau-bar">
                                        <div class="eau-fill" style="width:{{ max(2, $row['pct']) }}%;background:{{ $palettes[$key][$idx % count($palettes[$key])] }};"></div>
                                    </div>
                                </div>
                            @empty
                                <div style="font-size:12px;color:var(--hrn-ink-3);">No data</div>
                            @endforelse
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
