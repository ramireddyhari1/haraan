{{-- Partner dashboard conversion funnel: Page Views → Sales → Conversion %.
     Self-contained (markup + inline CSS, theme-aware). Data from
     PartnerFunnelWidget::getFunnel(). --}}
@php
    $f = $this->getFunnel();
    $window = 'last ' . ($f['days'] ?? 30) . ' days';
    $nf = fn ($n) => number_format((int) $n);
    // Bar widths: views is the full bar; sales is proportional to unique visitors
    // so the narrowing reads as the real drop-off.
    $base = max(1, (int) ($f['uniqueViews'] ?: $f['pageViews']));
    $salesPct = min(100, (int) round(($f['sales'] / $base) * 100));
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
            <div class="pfn-grid">
                {{-- Stage 1 — Page views --}}
                <div class="pfn-stage" data-accent="blue">
                    <div class="pfn-slab">Page views</div>
                    <div class="pfn-sval">{{ $nf($f['pageViews']) }}</div>
                    <div class="pfn-ssub">{{ $nf($f['uniqueViews']) }} unique visitors</div>
                    <div class="pfn-bar"><span style="width:100%"></span></div>
                </div>

                {{-- Stage 2 — Sales --}}
                <div class="pfn-stage" data-accent="green">
                    <div class="pfn-slab">Sales</div>
                    <div class="pfn-sval">{{ $nf($f['sales']) }}</div>
                    <div class="pfn-ssub">paid bookings</div>
                    <div class="pfn-bar"><span style="width:{{ max(4, $salesPct) }}%"></span></div>
                </div>

                {{-- Stage 3 — Conversion --}}
                <div class="pfn-stage pfn-conv" data-accent="indigo">
                    <div class="pfn-slab">Conversion</div>
                    <div class="pfn-sval">{{ $f['conversion'] !== null ? number_format($f['conversion'], 1) . '%' : '—' }}</div>
                    <div class="pfn-ssub">of unique visitors bought</div>
                    <svg class="pfn-ring" viewBox="0 0 36 36" aria-hidden="true">
                        <path class="pfn-ring-bg" d="M18 2.5a15.5 15.5 0 1 1 0 31 15.5 15.5 0 0 1 0-31"/>
                        @php $pct = min(100, (float) ($f['conversion'] ?? 0)); $dash = round($pct / 100 * 97.4, 1); @endphp
                        <path class="pfn-ring-fg" stroke-dasharray="{{ $dash }} 97.4"
                              d="M18 2.5a15.5 15.5 0 1 1 0 31 15.5 15.5 0 0 1 0-31"/>
                    </svg>
                </div>
            </div>
        @endif
    </div>

    <style>
        .pfn{background:#fff;border:1px solid #e7e9ee;border-radius:16px;
            box-shadow:0 1px 2px rgba(11,18,32,.06);padding:18px 20px;}
        .pfn-head{display:flex;align-items:baseline;justify-content:space-between;gap:10px;margin-bottom:14px;}
        .pfn-title{font-size:14px;font-weight:800;color:#0b1220;letter-spacing:-.01em;}
        .pfn-sub{font-size:11.5px;color:#9aa2b1;font-weight:600;}
        .pfn-empty{font-size:13px;color:#7a8394;line-height:1.5;padding:6px 0 2px;}

        .pfn-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;}
        .pfn-stage{position:relative;border:1px solid #eceef3;border-radius:13px;padding:14px 15px;
            background:#fbfcfe;border-top:3px solid transparent;overflow:hidden;}
        .pfn-stage[data-accent="blue"]{border-top-color:#5aa2f5;}
        .pfn-stage[data-accent="green"]{border-top-color:#0f9d63;}
        .pfn-stage[data-accent="indigo"]{border-top-color:#7c86f0;}
        .pfn-slab{font-size:12px;color:#6b7382;font-weight:700;}
        .pfn-sval{font-size:27px;font-weight:800;color:#0b1220;letter-spacing:-.03em;
            font-variant-numeric:tabular-nums;line-height:1.1;margin-top:2px;}
        .pfn-ssub{font-size:11px;color:#9aa2b1;margin-top:1px;}
        .pfn-bar{margin-top:10px;height:6px;border-radius:6px;background:#eef1f6;overflow:hidden;}
        .pfn-bar span{display:block;height:100%;border-radius:6px;
            background:linear-gradient(90deg,#3b82f6,#1e50e6);}
        .pfn-stage[data-accent="green"] .pfn-bar span{background:linear-gradient(90deg,#12b473,#0f9d63);}

        .pfn-conv .pfn-ring{position:absolute;right:12px;top:12px;width:44px;height:44px;}
        .pfn-ring-bg{fill:none;stroke:#eef1f6;stroke-width:3.4;}
        .pfn-ring-fg{fill:none;stroke:#6a75ef;stroke-width:3.4;stroke-linecap:round;
            transition:stroke-dasharray .5s ease;}

        .dark .pfn{background:#111722;border-color:#1e2633;box-shadow:0 1px 2px rgba(0,0,0,.4);}
        .dark .pfn-title,.dark .pfn-sval{color:#eef1f6;}
        .dark .pfn-stage{background:#0e141e;border-color:#1e2633;}
        .dark .pfn-slab{color:#8b94a5;} .dark .pfn-ssub,.dark .pfn-sub{color:#5e6675;}
        .dark .pfn-empty{color:#8b94a5;}
        .dark .pfn-bar{background:#1e2633;} .dark .pfn-ring-bg{stroke:#1e2633;}

        @media (max-width:640px){
            .pfn-grid{grid-template-columns:1fr;}
        }
    </style>
</x-filament-widgets::widget>
