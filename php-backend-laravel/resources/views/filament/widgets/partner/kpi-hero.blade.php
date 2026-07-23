{{-- Partner dashboard "money hero": dominant revenue + supporting KPIs, framed as
     a "Performance" section and sharing the icon-chip system of the Today strip.
     Self-contained (markup + inline CSS, theme-aware). Data from getStats(). --}}
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
    $window = 'last ' . ($s['days'] ?? 14) . ' days';
    // Only sound the refund alarm on a meaningful sample — 1 refund of 5 shouldn't
    // read as a crisis. Amber needs ≥10 bookings AND ≥8% refunds; else it stays calm.
    $refundHigh = ($s['bookingCount'] ?? 0) >= 10 && ($s['refundRate'] ?? 0) >= 8;
    $refundCount = $s['refundCount'] ?? 0;
    $bookingCount = $s['bookingCount'] ?? 0;
@endphp

<x-filament-widgets::widget>
    <div class="pkh-sec">Performance <span class="pkh-sec-sub">· {{ $window }}</span></div>

    <div class="pkh">
        {{-- Revenue — the dominant figure --}}
        <div class="pkh-hero" data-accent="green">
            <span class="pkh-ic"><x-filament::icon icon="heroicon-o-banknotes" /></span>
            <div class="pkh-lab">Revenue · {{ $window }}</div>
            <div class="pkh-val">{{ $inr($s['revenue']) }}</div>
            <div class="pkh-meta">
                @if ($delta !== null)
                    <span class="pkh-delta {{ $delta < 0 ? 'is-down' : 'is-up' }}">
                        {{ $delta < 0 ? '▼' : '▲' }} {{ number_format(abs($delta), 1) }}%
                    </span>
                    <span class="pkh-vs">vs previous {{ $s['days'] ?? 14 }} days</span>
                @else
                    <span class="pkh-vs">Collected across paid bookings</span>
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
            <div class="pkh-kpi" data-accent="blue">
                <span class="pkh-ic"><x-filament::icon icon="heroicon-o-ticket" /></span>
                <div class="pkh-kval">{{ number_format($s['tickets']) }}</div>
                <div class="pkh-klab">{{ $ticketLabel }}</div>
                <div class="pkh-ksub">{{ $window }}</div>
            </div>
            <div class="pkh-kpi" data-accent="indigo">
                <span class="pkh-ic"><x-filament::icon icon="heroicon-o-check-badge" /></span>
                <div class="pkh-kval">{{ $s['checkedInRate'] !== null ? $s['checkedInRate'] . '%' : '—' }}</div>
                <div class="pkh-klab">Checked in</div>
                <div class="pkh-ksub">of paid bookings</div>
            </div>
            <div class="pkh-kpi" data-accent="{{ $refundHigh ? 'amber' : 'slate' }}">
                <span class="pkh-ic"><x-filament::icon icon="heroicon-o-arrow-uturn-left" /></span>
                <div class="pkh-kval {{ $refundHigh ? 'is-warn' : '' }}">{{ number_format($s['refundRate'], 1) }}%</div>
                <div class="pkh-klab">Refund rate</div>
                <div class="pkh-ksub">
                    @if ($bookingCount > 0)
                        {{ number_format($refundCount) }} of {{ number_format($bookingCount) }} {{ \Illuminate\Support\Str::plural('booking', $bookingCount) }}
                    @else
                        no bookings yet
                    @endif
                </div>
            </div>
        </div>
    </div>

    <style>
        .pkh-sec{font-size:11px;font-weight:800;letter-spacing:.07em;text-transform:uppercase;
            color:#7a8394;margin:2px 2px 10px;}
        .pkh-sec-sub{color:#aab2c0;font-weight:600;}

        .pkh{display:grid;grid-template-columns:1.6fr 2fr;gap:14px;}
        .pkh-hero,.pkh-kpi{background:#fff;border:1px solid #e9ecf2;border-radius:16px;
            box-shadow:0 1px 2px rgba(11,18,32,.06);transition:box-shadow .15s,transform .05s;}
        .pkh-kpi:hover,.pkh-hero:hover{box-shadow:0 6px 16px -8px rgba(11,18,32,.18);transform:translateY(-1px);}
        .pkh-hero{padding:18px 20px;display:flex;flex-direction:column;position:relative;overflow:hidden;}
        .pkh-lab{font-size:12.5px;color:#6b7382;font-weight:600;}
        .pkh-val{font-size:38px;font-weight:800;color:#0b1220;letter-spacing:-.03em;
            font-variant-numeric:tabular-nums;margin-top:3px;line-height:1.02;}
        .pkh-meta{display:flex;align-items:center;gap:8px;margin-top:8px;flex-wrap:wrap;}
        .pkh-delta{font-size:12px;font-weight:800;font-variant-numeric:tabular-nums;
            padding:3px 9px;border-radius:999px;}
        .pkh-delta.is-up{color:#0a7d4e;background:#e6f7ef;}
        .pkh-delta.is-down{color:#b26f04;background:#fdf1dc;}
        .pkh-vs{font-size:12px;color:#9aa2b1;}
        .pkh-spark{margin-top:auto;width:100%;height:54px;display:block;padding-top:12px;}

        .pkh-side{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;}
        .pkh-kpi{padding:15px 16px;display:flex;flex-direction:column;position:relative;}
        .pkh-klab{font-size:12.5px;color:#374151;font-weight:600;margin-top:2px;}
        .pkh-kval{font-size:24px;font-weight:800;color:#0b1220;letter-spacing:-.03em;
            font-variant-numeric:tabular-nums;line-height:1.1;}
        .pkh-kval.is-warn{color:#b26f04;}
        .pkh-ksub{font-size:11.5px;color:#9aa2b1;margin-top:1px;}

        /* Tinted icon chip per metric — same system as the Today strip. */
        .pkh-ic{width:30px;height:30px;border-radius:9px;display:flex;align-items:center;
            justify-content:center;margin-bottom:9px;}
        .pkh-ic svg{width:17px;height:17px;stroke-width:1.9;}
        [data-accent="green"]  .pkh-ic{background:#e6f7ef;color:#0f9d63;}
        [data-accent="blue"]   .pkh-ic{background:#e8f0ff;color:#2f6bff;}
        [data-accent="indigo"] .pkh-ic{background:#ecedfe;color:#5257e0;}
        [data-accent="amber"]  .pkh-ic{background:#fdf1dc;color:#b26f04;}
        [data-accent="slate"]  .pkh-ic{background:#eef1f6;color:#64748b;}

        .dark .pkh-hero,.dark .pkh-kpi{background:#111722;border-color:#1e2633;box-shadow:0 1px 2px rgba(0,0,0,.4);}
        .dark .pkh-val,.dark .pkh-kval{color:#eef1f6;}
        .dark .pkh-lab{color:#8b94a5;} .dark .pkh-klab{color:#c3cad6;}
        .dark .pkh-vs,.dark .pkh-ksub{color:#5e6675;}
        .dark .pkh-delta.is-up{color:#28c882;}
        .dark .pkh-sec{color:#8b94a5;} .dark .pkh-sec-sub{color:#5e6675;}

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
