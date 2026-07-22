{{-- "Money by day": last 7 days of collected revenue as a compact bar strip.
     Best day highlighted, today pulses. Self-contained (markup + inline CSS,
     theme-aware). Data from PartnerDailyEarningsWidget::getStats(). --}}
@php
    $s = $this->getStats();
    $inr = function (float $n): string {
        $n = round($n); $sign = $n < 0 ? '-' : ''; $n = abs($n); $str = (string) $n;
        if (strlen($str) <= 3) return $sign . '₹' . $str;
        $last3 = substr($str, -3); $rest = substr($str, 0, -3);
        $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
        return $sign . '₹' . $rest . ',' . $last3;
    };
    $max = (float) $s['max'];
@endphp

<x-filament-widgets::widget>
    <div class="pde">
        <div class="pde-head">
            <div>
                <div class="pde-title">Money by day</div>
                <div class="pde-sub">Collected revenue · last 7 days</div>
            </div>
            <div class="pde-total">
                <div class="pde-total-val">{{ $inr($s['total']) }}</div>
                <div class="pde-total-lab">this week</div>
            </div>
        </div>

        <div class="pde-chart">
            @foreach ($s['bars'] as $i => $bar)
                @php
                    // Min 6% so a zero/tiny day still shows a nub; scaled to the peak.
                    $h = $max > 0 ? max(6, round($bar['value'] / $max * 100)) : 6;
                    $isBest = $i === $s['bestIdx'] && $bar['value'] > 0;
                @endphp
                <div @class(['pde-col', 'is-today' => $bar['isToday']]) title="{{ $bar['date'] }} · {{ $inr($bar['value']) }}">
                    <div class="pde-bar-wrap">
                        @if ($bar['value'] > 0)
                            <div class="pde-cap">{{ $inr($bar['value']) }}</div>
                        @endif
                        <div @class(['pde-bar', 'is-best' => $isBest, 'is-zero' => $bar['value'] <= 0])
                             style="height:{{ $h }}%"></div>
                    </div>
                    <div class="pde-day">{{ $bar['letter'] }}</div>
                </div>
            @endforeach
        </div>

        @if ($s['best'] > 0)
            <div class="pde-foot">
                <span class="pde-dot"></span>
                Best day: <strong>{{ $s['bestDate'] }}</strong> · {{ $inr($s['best']) }}
            </div>
        @endif
    </div>

    <style>
        .pde{background:#fff;border:1px solid #e7e9ee;border-radius:16px;padding:18px 18px 14px;
            box-shadow:0 1px 2px rgba(11,18,32,.06);}
        .pde-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:16px;}
        .pde-title{font-size:15px;font-weight:800;color:#0b1220;letter-spacing:-.01em;}
        .pde-sub{font-size:12px;color:#9aa2b1;margin-top:2px;}
        .pde-total{text-align:right;}
        .pde-total-val{font-size:20px;font-weight:800;color:#0a7d4e;letter-spacing:-.02em;
            font-variant-numeric:tabular-nums;line-height:1;}
        .pde-total-lab{font-size:11px;color:#9aa2b1;margin-top:3px;}

        .pde-chart{display:grid;grid-template-columns:repeat(7,1fr);gap:8px;align-items:end;
            height:150px;}
        .pde-col{display:flex;flex-direction:column;align-items:center;gap:7px;height:100%;justify-content:flex-end;}
        .pde-bar-wrap{width:100%;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;
            flex:1;min-height:0;}
        .pde-cap{font-size:9.5px;font-weight:700;color:#6b7382;margin-bottom:4px;white-space:nowrap;
            font-variant-numeric:tabular-nums;}
        .pde-bar{width:100%;max-width:34px;border-radius:7px 7px 3px 3px;
            background:linear-gradient(180deg,#93c9ff,#5aa2f5);min-height:4px;
            transition:filter .15s;}
        .pde-bar.is-best{background:linear-gradient(180deg,#22c07e,#0f9d63);
            box-shadow:0 6px 14px -6px rgba(15,157,99,.55);}
        .pde-bar.is-zero{background:#e7e9ee;}
        .pde-col.is-today .pde-bar{outline:2px solid rgba(37,99,235,.35);outline-offset:2px;
            animation:pde-pulse 2.4s ease-in-out infinite;}
        .pde-col.is-today .pde-day{color:#2563eb;font-weight:800;}
        .pde-day{font-size:11.5px;font-weight:600;color:#9aa2b1;}

        .pde-foot{display:flex;align-items:center;gap:7px;margin-top:14px;padding-top:12px;
            border-top:1px solid #f0f1f4;font-size:12px;color:#6b7382;}
        .pde-foot strong{color:#0b1220;font-weight:700;}
        .pde-dot{width:8px;height:8px;border-radius:50%;background:#0f9d63;flex:none;}

        @keyframes pde-pulse{0%,100%{outline-color:rgba(37,99,235,.15);}50%{outline-color:rgba(37,99,235,.5);}}

        .dark .pde{background:#111722;border-color:#1e2633;box-shadow:0 1px 2px rgba(0,0,0,.4);}
        .dark .pde-title,.dark .pde-foot strong{color:#eef1f6;}
        .dark .pde-sub,.dark .pde-total-lab,.dark .pde-day,.dark .pde-cap,.dark .pde-foot{color:#8b94a5;}
        .dark .pde-total-val{color:#28c882;}
        .dark .pde-bar{background:linear-gradient(180deg,#3f79c9,#2f6bff);}
        .dark .pde-bar.is-best{background:linear-gradient(180deg,#28c882,#0f9d63);}
        .dark .pde-bar.is-zero{background:#1e2633;}
        .dark .pde-foot{border-top-color:#1e2633;}
        .dark .pde-col.is-today .pde-day{color:#7fb0ff;}

        @media (max-width:560px){
            .pde-chart{gap:5px;height:132px;}
            .pde-cap{font-size:8.5px;}
            .pde-bar{border-radius:6px 6px 2px 2px;}
        }
        @media (prefers-reduced-motion:reduce){.pde-col.is-today .pde-bar{animation:none;}}
    </style>
</x-filament-widgets::widget>
