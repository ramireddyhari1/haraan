{{-- Conversion: who looked, who bought. Two bars on one scale (unique visitors
     is the full width; buyers are their true share of it) and the rate beside
     them. Data from getFunnel(). --}}
@php
    $f = $this->getFunnel();
    $days = $f['days'] ?? 30;
    $nf = fn ($n) => number_format((int) $n);
    $base = max(1, (int) ($f['uniqueViews'] ?: $f['pageViews']));
    $buyPct = $f['sales'] > 0 ? max(1.5, min(100, $f['sales'] / $base * 100)) : 0;
    $convTxt = $f['conversion'] !== null ? number_format($f['conversion'], 1) . '%' : '—';
@endphp

<x-filament-widgets::widget>
    <section class="pfn">
        <header class="pfn-head">
            <h3>Conversion</h3>
            <span>last {{ $days }} days</span>
        </header>

        @if (! $f['hasData'])
            <p class="pfn-empty">
                Nobody has opened your event pages in this window yet. Share the links from an
                event's Analytics page — each view lands here, tagged with where it came from.
            </p>
        @else
            <div class="pfn-body">
                <div class="pfn-rows">
                    <div class="pfn-row">
                        <div class="pfn-row-top">
                            <span>Looked</span>
                            <b>{{ $nf($f['uniqueViews']) }}</b>
                            <em>{{ $nf($f['pageViews']) }} {{ \Illuminate\Support\Str::plural('view', $f['pageViews']) }}</em>
                        </div>
                        <div class="pfn-bar"><i style="width:100%"></i></div>
                    </div>
                    <div class="pfn-row">
                        <div class="pfn-row-top">
                            <span>Bought</span>
                            <b>{{ $nf($f['sales']) }}</b>
                            <em>paid</em>
                        </div>
                        <div class="pfn-bar is-buy"><i style="width:{{ $buyPct }}%"></i></div>
                    </div>
                </div>
                <div class="pfn-rate">
                    <b>{{ $convTxt }}</b>
                    <span>of visitors bought</span>
                </div>
            </div>
        @endif
    </section>

    <style>
        .pfn{background:#fff;border:1px solid #e6e9f0;border-radius:16px;padding:16px 20px 18px;
            box-shadow:0 1px 2px rgba(15,23,42,.04);}
        .pfn-head{display:flex;align-items:baseline;justify-content:space-between;gap:10px;}
        .pfn-head h3{font-size:15px;font-weight:680;letter-spacing:-.012em;color:#0f172a;}
        .pfn-head span{font-size:12px;color:#94a3b8;}
        .pfn-empty{margin-top:8px;font-size:13px;line-height:1.55;color:#64748b;max-width:60ch;}
        .pfn-body{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:28px;align-items:center;margin-top:14px;}
        .pfn-rows{display:flex;flex-direction:column;gap:14px;min-width:0;}
        .pfn-row-top{display:flex;align-items:baseline;gap:8px;font-size:12.5px;color:#475569;font-weight:600;}
        .pfn-row-top b{font-size:15px;color:#0f172a;font-variant-numeric:tabular-nums;}
        .pfn-row-top em{font-style:normal;font-weight:500;color:#94a3b8;margin-left:auto;font-variant-numeric:tabular-nums;}
        .pfn-bar{height:8px;border-radius:99px;background:#f1f4f9;margin-top:7px;overflow:hidden;}
        .pfn-bar i{display:block;height:100%;border-radius:99px;background:#bcd0fb;transform-origin:left;
            animation:pfn-grow .8s cubic-bezier(.2,.8,.2,1) .15s both;}
        .pfn-bar.is-buy i{background:#0f9d63;animation-delay:.3s;}
        @keyframes pfn-grow{from{transform:scaleX(0)}}
        .pfn-rate{text-align:right;padding-left:24px;border-left:1px solid #eef1f6;}
        .pfn-rate b{display:block;font-size:30px;font-weight:760;letter-spacing:-.04em;color:#0f172a;
            font-variant-numeric:tabular-nums;line-height:1;}
        .pfn-rate span{display:block;margin-top:6px;font-size:12px;color:#64748b;}
        @media (max-width:560px){
            .pfn-body{grid-template-columns:1fr;gap:16px;}
            .pfn-rate{text-align:left;padding:14px 0 0;border-left:0;border-top:1px solid #eef1f6;}
        }
        @media (prefers-reduced-motion:reduce){.pfn-bar i{animation:none;}}
    </style>
</x-filament-widgets::widget>
