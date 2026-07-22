{{-- Partner dashboard "Peak booking hours" heatmap + the insight it yields.
     Self-contained (markup + inline CSS, theme-aware). --}}
@php
    $h = $this->getHeatmap();
    $max = max(1, (int) $h['max']);
    // Map a count to a green-intensity step (0 = empty).
    $shade = function (int $v) use ($max): string {
        if ($v <= 0) return 'l0';
        $ratio = $v / $max;
        return match (true) {
            $ratio >= 0.80 => 'l5',
            $ratio >= 0.60 => 'l4',
            $ratio >= 0.40 => 'l3',
            $ratio >= 0.20 => 'l2',
            default        => 'l1',
        };
    };
@endphp

<x-filament-widgets::widget>
    <div class="pph">
        <div class="pph-head">
            <div>
                <div class="pph-title">Peak booking hours</div>
                <div class="pph-sub">When your audience buys · last 90 days</div>
            </div>
        </div>

        @if ($h['total'] > 0)
            <div class="pph-map">
                <div class="pph-corner"></div>
                @foreach ($h['hours'] as $hl)
                    <div class="pph-hlab">{{ $hl }}</div>
                @endforeach

                @foreach ($h['days'] as $di => $day)
                    <div class="pph-dlab">{{ $day }}</div>
                    @foreach ($h['grid'][$di] as $v)
                        <div class="pph-cell {{ $shade($v) }}" title="{{ $day }} · {{ $v }} booking{{ $v === 1 ? '' : 's' }}"></div>
                    @endforeach
                @endforeach
            </div>

            <div class="pph-legend">
                <span>Less</span>
                <i class="l1"></i><i class="l2"></i><i class="l3"></i><i class="l4"></i><i class="l5"></i>
                <span>More</span>
            </div>

            @if ($h['insight'])
                <div class="pph-insight">
                    <span class="pph-bulb">💡</span> {{ $h['insight'] }}
                </div>
            @endif
        @else
            <div class="pph-empty">
                No booking activity yet. Once tickets start selling, your busiest days and hours show up here — so you can time drops and ads to the peak.
            </div>
        @endif
    </div>

    <style>
        .pph{background:#fff;border:1px solid #e7e9ee;border-radius:16px;padding:20px 22px;
            box-shadow:0 1px 2px rgba(11,18,32,.06);}
        .pph-head{margin-bottom:16px;}
        .pph-title{font-size:16px;font-weight:800;color:#0b1220;letter-spacing:-.02em;}
        .pph-sub{font-size:12.5px;color:#9aa2b1;margin-top:2px;}

        .pph-map{display:grid;grid-template-columns:auto repeat(9,1fr);gap:5px;align-items:center;}
        .pph-corner{}
        .pph-hlab{font-size:10.5px;color:#9aa2b1;text-align:center;font-variant-numeric:tabular-nums;}
        .pph-dlab{font-size:11.5px;color:#6b7382;font-weight:600;padding-right:8px;}
        .pph-cell{aspect-ratio:1/1;border-radius:5px;background:#eef1f5;min-height:20px;transition:transform .1s;}
        .pph-cell:hover{transform:scale(1.12);}
        .pph-cell.l0{background:#eef1f5;}
        .pph-cell.l1{background:#d6efe1;}
        .pph-cell.l2{background:#a7ddc2;}
        .pph-cell.l3{background:#63c79b;}
        .pph-cell.l4{background:#25a874;}
        .pph-cell.l5{background:#0f8a5a;}

        .pph-legend{display:flex;align-items:center;gap:5px;justify-content:flex-end;margin-top:12px;
            font-size:11px;color:#9aa2b1;}
        .pph-legend i{width:14px;height:14px;border-radius:4px;display:inline-block;}
        .pph-legend i.l1{background:#d6efe1;} .pph-legend i.l2{background:#a7ddc2;}
        .pph-legend i.l3{background:#63c79b;} .pph-legend i.l4{background:#25a874;} .pph-legend i.l5{background:#0f8a5a;}

        .pph-insight{margin-top:16px;padding:12px 14px;border-radius:12px;
            background:#e9f6ef;border:1px solid #cbe9d9;color:#0a5f42;font-size:13.5px;line-height:1.5;
            display:flex;gap:8px;align-items:flex-start;}
        .pph-bulb{flex:0 0 auto;}
        .pph-empty{font-size:13.5px;color:#6b7382;line-height:1.55;padding:8px 0;}

        .dark .pph{background:#111722;border-color:#1e2633;box-shadow:0 1px 2px rgba(0,0,0,.4);}
        .dark .pph-title{color:#eef1f6;} .dark .pph-sub{color:#5e6675;}
        .dark .pph-dlab{color:#8b94a5;} .dark .pph-hlab{color:#5e6675;}
        .dark .pph-cell.l0,.dark .pph-cell{background:#1a222f;}
        .dark .pph-cell.l1{background:#123528;} .dark .pph-cell.l2{background:#16543a;}
        .dark .pph-cell.l3{background:#1c8057;} .dark .pph-cell.l4{background:#24a870;} .dark .pph-cell.l5{background:#33c583;}
        .dark .pph-insight{background:#0f2a20;border-color:#1e4535;color:#8fe4bd;}
        .dark .pph-empty{color:#8b94a5;}

        @media (max-width:640px){
            .pph-cell{min-height:15px;border-radius:4px;}
            .pph-hlab{font-size:9px;}
        }
    </style>
</x-filament-widgets::widget>
