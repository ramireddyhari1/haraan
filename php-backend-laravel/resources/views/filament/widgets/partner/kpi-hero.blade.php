{{-- Performance over the chosen period: revenue leads, three supporting figures
     sit beside it in the same strip, split by hairlines. No icon chips — the
     label says what the number is. The sparkline is drawn only when there is
     money to draw; a flat line at zero says nothing. --}}
@php
    $s = $this->getStats();
    $inr = function (float $n): string {
        $n = round($n);
        $sign = $n < 0 ? '-' : '';
        $str = (string) abs($n);
        if (strlen($str) <= 3) {
            return $sign . '₹' . $str;
        }
        $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', substr($str, 0, -3));

        return $sign . '₹' . $rest . ',' . substr($str, -3);
    };
    $delta = $s['delta'];
    $days = $s['days'] ?? 14;
    $refundHigh = ($s['bookingCount'] ?? 0) >= 10 && ($s['refundRate'] ?? 0) >= 8;
    $refundCount = $s['refundCount'] ?? 0;
    $bookingCount = $s['bookingCount'] ?? 0;
@endphp

<x-filament-widgets::widget>
    <section class="pkh">
        <div class="pkh-main">
            <div class="pkh-lab">Revenue <span>· last {{ $days }} days</span></div>
            <div class="pkh-val" data-count-to="{{ (int) round($s['revenue']) }}" data-count-prefix="₹">{{ $inr($s['revenue']) }}</div>
            <div class="pkh-meta">
                @if ($delta !== null)
                    <span class="pkh-delta {{ $delta < 0 ? 'is-down' : 'is-up' }}">
                        {{ $delta < 0 ? '−' : '+' }}{{ number_format(abs($delta), 1) }}%
                    </span>
                    vs the {{ $days }} days before
                @elseif ($s['revenue'] > 0)
                    Collected across paid bookings
                @else
                    Nothing sold in this window yet
                @endif
            </div>
            @if ($s['revenue'] > 0)
                <svg class="pkh-spark" viewBox="0 0 120 34" preserveAspectRatio="none" aria-hidden="true">
                    <path d="{{ $s['spark'] }} L120,34 L0,34 Z" fill="#0f9d63" fill-opacity=".08"/>
                    <path d="{{ $s['spark'] }}" fill="none" stroke="#0f9d63" stroke-width="1.8"
                          stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke"/>
                </svg>
            @endif
        </div>

        <dl class="pkh-side">
            <div class="pkh-kpi">
                <dt>{{ $s['isEventLane'] ? 'Tickets sold' : 'Bookings' }}</dt>
                <dd data-count-to="{{ (int) $s['tickets'] }}">{{ number_format($s['tickets']) }}</dd>
            </div>
            <div class="pkh-kpi">
                <dt>Checked in</dt>
                <dd>{{ $s['checkedInRate'] !== null ? $s['checkedInRate'] . '%' : '—' }}</dd>
                <p>of paid bookings</p>
            </div>
            <div class="pkh-kpi">
                <dt>Refund rate</dt>
                <dd class="{{ $refundHigh ? 'is-warn' : '' }}">{{ $bookingCount > 0 ? number_format($s['refundRate'], 1) . '%' : '—' }}</dd>
                <p>
                    @if ($bookingCount > 0)
                        {{ number_format($refundCount) }} of {{ number_format($bookingCount) }} {{ \Illuminate\Support\Str::plural('booking', $bookingCount) }}
                    @else
                        No bookings yet
                    @endif
                </p>
            </div>
        </dl>
    </section>

    <style>
        .pkh{display:grid;grid-template-columns:minmax(0,1.25fr) minmax(0,2fr);background:#fff;
            border:1px solid #e6e9f0;border-radius:16px;box-shadow:0 1px 2px rgba(15,23,42,.04);overflow:hidden;}
        .pkh-main{padding:18px 20px 16px;display:flex;flex-direction:column;min-width:0;border-right:1px solid #eef1f6;}
        .pkh-lab{font-size:11px;font-weight:700;letter-spacing:.09em;text-transform:uppercase;color:#64748b;}
        .pkh-lab span{color:#94a3b8;font-weight:600;letter-spacing:.04em;text-transform:none;}
        .pkh-val{font-size:34px;font-weight:760;letter-spacing:-.04em;color:#0f172a;margin-top:6px;line-height:1;
            font-variant-numeric:tabular-nums;}
        .pkh-meta{margin-top:8px;font-size:12.5px;color:#64748b;display:flex;gap:6px;align-items:baseline;flex-wrap:wrap;}
        .pkh-delta{font-weight:700;font-variant-numeric:tabular-nums;}
        .pkh-delta.is-up{color:#0f9d63;}
        .pkh-delta.is-down{color:#b45309;}
        .pkh-spark{margin-top:auto;padding-top:12px;width:100%;height:52px;display:block;}
        .pkh-side{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));margin:0;}
        .pkh-kpi{padding:18px 18px 16px;min-width:0;}
        .pkh-kpi + .pkh-kpi{border-left:1px solid #eef1f6;}
        .pkh-kpi dt{font-size:11px;font-weight:700;letter-spacing:.09em;text-transform:uppercase;color:#64748b;}
        .pkh-kpi dd{margin:8px 0 0;font-size:24px;font-weight:740;letter-spacing:-.03em;color:#0f172a;
            font-variant-numeric:tabular-nums;}
        .pkh-kpi dd.is-warn{color:#b45309;}
        .pkh-kpi p{margin:3px 0 0;font-size:12px;color:#94a3b8;}
        @media (max-width:900px){
            .pkh{grid-template-columns:1fr;}
            .pkh-main{border-right:0;border-bottom:1px solid #eef1f6;}
        }
        @media (max-width:520px){
            .pkh-kpi{padding:14px 12px;}
            .pkh-kpi dd{font-size:20px;}
            .pkh-kpi dt{font-size:10px;letter-spacing:.06em;}
        }
    </style>
</x-filament-widgets::widget>
