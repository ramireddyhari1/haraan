{{-- Partner dashboard "money hero": dominant revenue + supporting KPIs.
     Self-contained (markup + inline CSS, theme-aware) like the other bespoke
     partner strips. Data comes from PartnerKpiHeroWidget::getStats(). --}}
@php
    $s = $this->getStats();
    // Indian grouping: ₹18,42,900
    $inr = function (float $n): string {
        $n = round($n);
        $sign = $n < 0 ? '-' : '';
        $n = abs($n);
        $str = (string) $n;
        if (strlen($str) <= 3) {
            return $sign . '₹' . $str;
        }
        $last3 = substr($str, -3);
        $rest = substr($str, 0, -3);
        $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
        return $sign . '₹' . $rest . ',' . $last3;
    };
    $delta = $s['delta'];
    $ticketLabel = $s['isEventLane'] ? 'Tickets sold' : 'Bookings';
@endphp

<x-filament-widgets::widget>
    <div class="pkh">
        {{-- Revenue — the dominant figure --}}
        <div class="pkh-hero">
            <div class="pkh-lab">Revenue · last 14 days</div>
            <div class="pkh-val">{{ $inr($s['revenue']) }}</div>
            <div class="pkh-meta">
                @if ($delta !== null)
                    <span class="pkh-delta {{ $delta < 0 ? 'is-down' : 'is-up' }}">
                        {{ $delta < 0 ? '▼' : '▲' }} {{ number_format(abs($delta), 1) }}%
                    </span>
                    <span class="pkh-vs">vs previous 14 days</span>
                @else
                    <span class="pkh-vs">Your collected revenue across paid bookings</span>
                @endif
            </div>
            <svg class="pkh-spark" viewBox="0 0 120 34" preserveAspectRatio="none" aria-hidden="true">
                <defs>
                    <linearGradient id="pkhFill" x1="0" x2="0" y1="0" y2="1">
                        <stop offset="0" stop-color="#0f9d63" stop-opacity=".26"/>
                        <stop offset="1" stop-color="#0f9d63" stop-opacity="0"/>
                    </linearGradient>
                </defs>
                <path d="{{ $s['spark'] }} L120,34 L0,34 Z" fill="url(#pkhFill)"/>
                <path d="{{ $s['spark'] }}" fill="none" stroke="#0f9d63" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>

        {{-- Supporting KPIs --}}
        <div class="pkh-side">
            <div class="pkh-kpi">
                <div class="pkh-klab">{{ $ticketLabel }}</div>
                <div class="pkh-kval">{{ number_format($s['tickets']) }}</div>
                <div class="pkh-ksub">last 14 days</div>
            </div>
            <div class="pkh-kpi">
                <div class="pkh-klab">Checked in</div>
                <div class="pkh-kval">{{ $s['checkedInRate'] !== null ? $s['checkedInRate'] . '%' : '—' }}</div>
                <div class="pkh-ksub">of paid bookings</div>
            </div>
            <div class="pkh-kpi">
                <div class="pkh-klab">Refund rate</div>
                <div class="pkh-kval {{ $s['refundRate'] >= 5 ? 'is-warn' : '' }}">{{ number_format($s['refundRate'], 1) }}%</div>
                <div class="pkh-ksub">last 14 days</div>
            </div>
        </div>
    </div>

    <style>
        .pkh{display:grid;grid-template-columns:1.6fr 2fr;gap:14px;}
        .pkh-hero,.pkh-kpi{background:#fff;border:1px solid #e7e9ee;border-radius:16px;
            box-shadow:0 1px 2px rgba(11,18,32,.06);}
        .pkh-hero{padding:20px 22px;display:flex;flex-direction:column;position:relative;overflow:hidden;}
        .pkh-lab{font-size:12.5px;color:#6b7382;font-weight:600;}
        .pkh-val{font-size:34px;font-weight:800;color:#0b1220;letter-spacing:-.03em;
            font-variant-numeric:tabular-nums;margin-top:4px;line-height:1.05;}
        .pkh-meta{display:flex;align-items:center;gap:8px;margin-top:6px;flex-wrap:wrap;}
        .pkh-delta{font-size:12.5px;font-weight:700;font-variant-numeric:tabular-nums;}
        .pkh-delta.is-up{color:#0a7d4e;} .pkh-delta.is-down{color:#c2790a;}
        .pkh-vs{font-size:12px;color:#9aa2b1;}
        .pkh-spark{margin-top:auto;width:100%;height:38px;display:block;padding-top:10px;}

        .pkh-side{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;}
        .pkh-kpi{padding:16px 18px;display:flex;flex-direction:column;gap:3px;}
        .pkh-klab{font-size:12.5px;color:#6b7382;font-weight:600;}
        .pkh-kval{font-size:26px;font-weight:800;color:#0b1220;letter-spacing:-.03em;
            font-variant-numeric:tabular-nums;line-height:1.1;}
        .pkh-kval.is-warn{color:#c2790a;}
        .pkh-ksub{font-size:11.5px;color:#9aa2b1;}

        .dark .pkh-hero,.dark .pkh-kpi{background:#111722;border-color:#1e2633;box-shadow:0 1px 2px rgba(0,0,0,.4);}
        .dark .pkh-val,.dark .pkh-kval{color:#eef1f6;}
        .dark .pkh-lab,.dark .pkh-klab{color:#8b94a5;}
        .dark .pkh-vs,.dark .pkh-ksub{color:#5e6675;}
        .dark .pkh-delta.is-up{color:#28c882;}

        @media (max-width:1024px){
            .pkh{grid-template-columns:1fr;}
        }
        @media (max-width:560px){
            .pkh-side{grid-template-columns:1fr 1fr;}
            .pkh-side .pkh-kpi:last-child{grid-column:1 / -1;}
            .pkh-val{font-size:30px;}
        }
    </style>
</x-filament-widgets::widget>
