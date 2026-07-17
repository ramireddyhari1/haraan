@php($insights = $this->getInsights())
<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <span style="display:inline-flex;align-items:center;gap:7px;">
                <x-filament::icon icon="heroicon-m-sparkles" style="width:18px;height:18px;color:#7c3aed;" />
                Insights &amp; recommendations
            </span>
        </x-slot>
        <x-slot name="description">Computed from this event's real data — act on these to sell more</x-slot>

        <style>
            .evi{--evi-t:#0b1220;--evi-b:#4b5563;--evi-chip:#fff;}
            .dark .evi{--evi-t:#f3f5f9;--evi-b:#c3cbd8;--evi-chip:rgba(255,255,255,.10);}
            .evi-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px;}
            @media(max-width:720px){.evi-grid{grid-template-columns:1fr;}}
            .evi-card{display:flex;gap:12px;align-items:flex-start;padding:14px 15px;border-radius:13px;}
            .evi-chip{flex:none;width:34px;height:34px;border-radius:9px;background:var(--evi-chip);display:flex;align-items:center;justify-content:center;box-shadow:0 1px 2px rgba(0,0,0,.05);}
            .evi-title{font-size:13.5px;font-weight:700;color:var(--evi-t);}
            .evi-body{font-size:12.5px;line-height:1.55;color:var(--evi-b);margin-top:3px;}
        </style>

        @php
            $tones = [
                'good' => ['bg' => 'rgba(16,185,129,.10)', 'bd' => 'rgba(16,185,129,.35)', 'ic' => '#059669'],
                'tip'  => ['bg' => 'rgba(37,99,235,.09)',  'bd' => 'rgba(37,99,235,.32)',  'ic' => '#2563eb'],
                'warn' => ['bg' => 'rgba(245,158,11,.12)', 'bd' => 'rgba(245,158,11,.4)',   'ic' => '#d97706'],
                'info' => ['bg' => 'rgba(124,58,237,.10)', 'bd' => 'rgba(124,58,237,.3)',   'ic' => '#7c3aed'],
            ];
        @endphp

        <div class="evi">
            @if (empty($insights))
                <div style="text-align:center;padding:22px 12px;color:#8a94a6;font-size:13px;">
                    Not enough activity yet to generate insights. Once this event gathers views and bookings,
                    recommendations will appear here automatically.
                </div>
            @else
                <div class="evi-grid">
                    @foreach ($insights as $i)
                        @php $t = $tones[$i['tone']] ?? $tones['info']; @endphp
                        <div class="evi-card" style="background:{{ $t['bg'] }};border:1px solid {{ $t['bd'] }};">
                            <div class="evi-chip">
                                <x-filament::icon :icon="$i['icon']" style="width:18px;height:18px;color:{{ $t['ic'] }};" />
                            </div>
                            <div style="min-width:0;">
                                <div class="evi-title">{{ $i['title'] }}</div>
                                <div class="evi-body">{{ $i['body'] }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
