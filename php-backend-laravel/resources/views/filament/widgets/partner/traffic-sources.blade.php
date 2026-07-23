{{-- Partner dashboard "Traffic sources": event-view opens by channel, over the
     global period. Self-contained (markup + inline CSS, theme-aware). Data from
     PartnerTrafficSourcesWidget::getSources(). --}}
@php
    $d = $this->getSources();
    $window = 'last ' . ($d['days'] ?? 30) . ' days';
    $nf = fn ($n) => number_format((int) $n);
@endphp

<x-filament-widgets::widget>
    <div class="pts">
        <div class="pts-head">
            <div>
                <div class="pts-title">Traffic sources</div>
                <div class="pts-sub">{{ $window }} · {{ $nf($d['total']) }} views</div>
            </div>
            @if ($d['top'])
                <div class="pts-badge">Top: {{ $d['top'] }}</div>
            @endif
        </div>

        @if ($d['total'] === 0)
            <div class="pts-empty">
                No traffic yet. Tag your shared links with <code>?src=instagram</code>
                or <code>?src=whatsapp</code> and this breakdown fills in automatically.
            </div>
        @else
            <div class="pts-list">
                @foreach ($d['sources'] as $s)
                    <div class="pts-row">
                        <span class="pts-dot" style="background:{{ $s['color'] }}"></span>
                        <span class="pts-name">{{ $s['label'] }}</span>
                        <span class="pts-track">
                            <span class="pts-fill" style="width:{{ max(3, $s['pct']) }}%;background:{{ $s['color'] }}"></span>
                        </span>
                        <span class="pts-pct">{{ $s['pct'] }}%</span>
                        <span class="pts-cnt">{{ $nf($s['count']) }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <style>
        .pts{background:#fff;border:1px solid #e7e9ee;border-radius:16px;
            box-shadow:0 1px 2px rgba(11,18,32,.06);padding:18px 20px;}
        .pts-head{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;margin-bottom:14px;}
        .pts-title{font-size:14px;font-weight:800;color:#0b1220;letter-spacing:-.01em;}
        .pts-sub{font-size:11.5px;color:#9aa2b1;font-weight:600;margin-top:1px;}
        .pts-badge{font-size:11px;font-weight:700;color:#1e50e6;background:rgba(47,107,255,.1);
            padding:4px 9px;border-radius:999px;white-space:nowrap;}
        .pts-empty{font-size:13px;color:#7a8394;line-height:1.55;}
        .pts-empty code{background:#f1f4f9;padding:1px 5px;border-radius:5px;font-size:12px;color:#475569;}

        .pts-list{display:flex;flex-direction:column;gap:11px;}
        .pts-row{display:grid;grid-template-columns:10px 96px 1fr 40px 44px;align-items:center;gap:9px;}
        .pts-dot{width:9px;height:9px;border-radius:50%;}
        .pts-name{font-size:12.5px;font-weight:600;color:#374151;white-space:nowrap;
            overflow:hidden;text-overflow:ellipsis;}
        .pts-track{height:8px;border-radius:6px;background:#eef1f6;overflow:hidden;}
        .pts-fill{display:block;height:100%;border-radius:6px;transition:width .4s ease;}
        .pts-pct{font-size:12.5px;font-weight:800;color:#0b1220;text-align:right;
            font-variant-numeric:tabular-nums;}
        .pts-cnt{font-size:11.5px;color:#9aa2b1;text-align:right;font-variant-numeric:tabular-nums;}

        .dark .pts{background:#111722;border-color:#1e2633;box-shadow:0 1px 2px rgba(0,0,0,.4);}
        .dark .pts-title,.dark .pts-pct{color:#eef1f6;}
        .dark .pts-name{color:#c3cad6;} .dark .pts-sub,.dark .pts-cnt{color:#5e6675;}
        .dark .pts-track{background:#1e2633;}
        .dark .pts-badge{color:#7fb0ff;background:rgba(59,130,246,.16);}
        .dark .pts-empty{color:#8b94a5;} .dark .pts-empty code{background:#1e2633;color:#c3cad6;}

        @media (max-width:520px){
            .pts-row{grid-template-columns:10px 74px 1fr 36px;}
            .pts-cnt{display:none;}
        }
    </style>
</x-filament-widgets::widget>
