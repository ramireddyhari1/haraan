{{-- Partner dashboard conversion funnel: one connected tapering shape from
     Page Views → Sales, with the conversion % called out in the middle.
     Self-contained (markup + inline CSS, theme-aware). Data from getFunnel(). --}}
@php
    $f = $this->getFunnel();
    $window = 'last ' . ($f['days'] ?? 30) . ' days';
    $nf = fn ($n) => number_format((int) $n);
@endphp

<x-filament-widgets::widget>
    <div class="pfn">
        <div class="pfn-head">
            <div class="pfn-title">Conversion funnel</div>
            <div class="pfn-sub">{{ $window }}</div>
        </div>

        @if (! $f['hasData'])
            <div class="pfn-empty">
                No page views or sales in this period yet. Share your event links —
                views land here the moment people open them.
            </div>
        @else
            @php
                // The shape tapers from full height (page views) to a height
                // proportional to the share of unique visitors who bought.
                $base = max(1, (int) ($f['uniqueViews'] ?: $f['pageViews']));
                $frac = min(1, $f['sales'] / $base);
                $rightH = max(16, (int) round(84 * $frac));
                $topY = round((84 - $rightH) / 2, 1);
                $botY = round((84 + $rightH) / 2, 1);
                $convTxt = $f['conversion'] !== null ? number_format($f['conversion'], 1) . '%' : '—';
            @endphp

            <div class="pfn-funnel">
                <svg class="pfn-svg" viewBox="0 0 300 84" preserveAspectRatio="none" aria-hidden="true">
                    <defs>
                        <linearGradient id="pfnGrad" x1="0" x2="1" y1="0" y2="0">
                            <stop offset="0" stop-color="#3b82f6"/>
                            <stop offset="1" stop-color="#0f9d63"/>
                        </linearGradient>
                    </defs>
                    <polygon points="0,0 300,{{ $topY }} 300,{{ $botY }} 0,84" fill="url(#pfnGrad)"/>
                </svg>
                <div class="pfn-conv">
                    <span class="pfn-conv-n">{{ $convTxt }}</span>
                    <span class="pfn-conv-l">conversion</span>
                </div>
            </div>

            <div class="pfn-caps">
                <div class="pfn-cap">
                    <span class="pfn-cap-n">{{ $nf($f['pageViews']) }}</span>
                    <span class="pfn-cap-l">Page views <span class="pfn-cap-s">· {{ $nf($f['uniqueViews']) }} unique</span></span>
                </div>
                <div class="pfn-cap pfn-cap-right">
                    <span class="pfn-cap-n">{{ $nf($f['sales']) }}</span>
                    <span class="pfn-cap-l">Sales <span class="pfn-cap-s">· paid</span></span>
                </div>
            </div>
        @endif
    </div>

    <style>
        .pfn{background:#fff;border:1px solid #e9ecf2;border-radius:16px;
            box-shadow:0 1px 2px rgba(11,18,32,.06);padding:18px 20px;}
        .pfn-head{display:flex;align-items:baseline;justify-content:space-between;gap:10px;margin-bottom:14px;}
        .pfn-title{font-size:14px;font-weight:800;color:#0b1220;letter-spacing:-.01em;}
        .pfn-sub{font-size:11.5px;color:#9aa2b1;font-weight:600;}
        .pfn-empty{font-size:13px;color:#7a8394;line-height:1.5;padding:6px 0 2px;}

        .pfn-funnel{position:relative;}
        .pfn-svg{display:block;width:100%;height:96px;border-radius:12px;}
        .pfn-conv{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);
            text-align:center;color:#fff;pointer-events:none;
            text-shadow:0 1px 8px rgba(10,23,56,.5);}
        .pfn-conv-n{display:block;font-size:30px;font-weight:800;letter-spacing:-.02em;
            line-height:1;font-variant-numeric:tabular-nums;}
        .pfn-conv-l{display:block;font-size:10.5px;font-weight:700;letter-spacing:.07em;
            text-transform:uppercase;opacity:.92;margin-top:3px;}

        .pfn-caps{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-top:12px;}
        .pfn-cap{display:flex;flex-direction:column;min-width:0;}
        .pfn-cap-right{text-align:right;align-items:flex-end;}
        .pfn-cap-n{font-size:20px;font-weight:800;color:#0b1220;letter-spacing:-.02em;
            font-variant-numeric:tabular-nums;line-height:1.1;}
        .pfn-cap-l{font-size:12px;font-weight:600;color:#374151;margin-top:1px;}
        .pfn-cap-s{color:#9aa2b1;font-weight:500;}

        .dark .pfn{background:#111722;border-color:#1e2633;box-shadow:0 1px 2px rgba(0,0,0,.4);}
        .dark .pfn-title,.dark .pfn-cap-n{color:#eef1f6;}
        .dark .pfn-sub,.dark .pfn-cap-s{color:#5e6675;}
        .dark .pfn-cap-l{color:#c3cad6;} .dark .pfn-empty{color:#8b94a5;}
    </style>
</x-filament-widgets::widget>
