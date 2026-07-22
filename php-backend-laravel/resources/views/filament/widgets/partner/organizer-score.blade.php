{{-- Partner "Organiser score": composite trust gauge + component bars + the
     weakest-link suggestion. Self-contained (markup + inline CSS, theme-aware). --}}
@php
    $d = $this->getScore();
    $score = (int) $d['score'];
    // Gauge geometry: r=52, circumference ≈ 326.7; arc = score%.
    $circ = 326.7;
    $arc = round($circ * min(100, max(0, $score)) / 100, 1);
    $ringColor = match ($d['tierTone']) { 'ok' => '#0f9d63', 'info' => '#2563eb', default => '#c2790a' };
@endphp

<x-filament-widgets::widget>
    <div class="pos">
        <div class="pos-gauge">
            <svg viewBox="0 0 120 120" class="pos-ring" aria-hidden="true">
                <circle cx="60" cy="60" r="52" fill="none" stroke="currentColor" stroke-width="9" class="pos-track"/>
                <circle cx="60" cy="60" r="52" fill="none" stroke="{{ $ringColor }}" stroke-width="9"
                    stroke-linecap="round" stroke-dasharray="{{ $arc }} {{ $circ }}"
                    transform="rotate(-90 60 60)"/>
            </svg>
            <div class="pos-center">
                <div class="pos-num">{{ $d['hasData'] ? $score : '—' }}</div>
                <div class="pos-of">/ 100</div>
            </div>
        </div>

        <div class="pos-body">
            <div class="pos-head">
                <div class="pos-title">Organiser score</div>
                <span class="pos-tier tone-{{ $d['tierTone'] }}">{{ $d['tier'] }}</span>
            </div>
            <div class="pos-sub">A composite of how well you sell, deliver and retain — the trust signal buyers feel.</div>

            <div class="pos-bars">
                @foreach ($d['components'] as $c)
                    <div class="pos-comp">
                        <div class="pos-clab"><span>{{ $c['label'] }}</span><b>{{ $c['value'] }}</b></div>
                        <div class="pos-track2"><div class="pos-fill" style="width:{{ min(100, max(2, $c['value'])) }}%"></div></div>
                    </div>
                @endforeach
            </div>

            @if ($d['hasData'] && $d['suggestion'])
                <div class="pos-tip"><span>↗</span> {{ $d['suggestion'] }}</div>
            @elseif (! $d['hasData'])
                <div class="pos-tip pos-tip-mute">Your score builds as bookings, check-ins and repeat buyers come in.</div>
            @endif
        </div>
    </div>

    <style>
        .pos{display:grid;grid-template-columns:auto 1fr;gap:26px;align-items:center;
            background:#fff;border:1px solid #e7e9ee;border-radius:16px;padding:22px 24px;
            box-shadow:0 1px 2px rgba(11,18,32,.06);}
        .pos-gauge{position:relative;width:132px;height:132px;flex:0 0 132px;}
        .pos-ring{width:132px;height:132px;color:#eef1f5;}
        .pos-track{color:#eef1f5;}
        .pos-center{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;}
        .pos-num{font-size:38px;font-weight:800;color:#0b1220;letter-spacing:-.04em;line-height:1;font-variant-numeric:tabular-nums;}
        .pos-of{font-size:11.5px;color:#9aa2b1;margin-top:2px;}

        .pos-head{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
        .pos-title{font-size:16px;font-weight:800;color:#0b1220;letter-spacing:-.02em;}
        .pos-tier{font-size:11.5px;font-weight:700;padding:3px 10px;border-radius:999px;}
        .pos-tier.tone-ok{color:#0a7d4e;background:#e9f6ef;}
        .pos-tier.tone-info{color:#2563eb;background:#e8effd;}
        .pos-tier.tone-warn{color:#b06d09;background:#fbf1e0;}
        .pos-sub{font-size:12.5px;color:#6b7382;margin-top:4px;line-height:1.45;max-width:52ch;}

        .pos-bars{display:grid;grid-template-columns:1fr 1fr;gap:12px 22px;margin-top:16px;}
        .pos-clab{display:flex;justify-content:space-between;font-size:12px;color:#6b7382;margin-bottom:5px;}
        .pos-clab b{color:#0b1220;font-variant-numeric:tabular-nums;}
        .pos-track2{height:7px;border-radius:999px;background:#eef1f5;overflow:hidden;}
        .pos-fill{height:100%;border-radius:999px;background:linear-gradient(90deg,#25a874,#0f9d63);}

        .pos-tip{margin-top:16px;font-size:13px;color:#0a5f42;background:#e9f6ef;border:1px solid #cbe9d9;
            border-radius:10px;padding:10px 13px;display:flex;gap:8px;line-height:1.45;}
        .pos-tip span{font-weight:800;}
        .pos-tip-mute{color:#6b7382;background:#f6f7f9;border-color:#e7e9ee;}

        .dark .pos{background:#111722;border-color:#1e2633;box-shadow:0 1px 2px rgba(0,0,0,.4);}
        .dark .pos-ring,.dark .pos-track{color:#1a222f;}
        .dark .pos-num,.dark .pos-title,.dark .pos-clab b{color:#eef1f6;}
        .dark .pos-of{color:#5e6675;} .dark .pos-sub,.dark .pos-clab{color:#8b94a5;}
        .dark .pos-track2{background:#1a222f;}
        .dark .pos-tip{background:#0f2a20;border-color:#1e4535;color:#8fe4bd;}
        .dark .pos-tip-mute{background:#141a24;border-color:#1e2633;color:#8b94a5;}
        .dark .pos-tier.tone-ok{background:#0f2a20;color:#28c882;}
        .dark .pos-tier.tone-info{background:#13203a;color:#5b9dff;}
        .dark .pos-tier.tone-warn{background:#2c2413;color:#e2a13c;}

        @media (max-width:720px){
            .pos{grid-template-columns:1fr;justify-items:center;text-align:center;gap:16px;}
            .pos-head{justify-content:center;} .pos-sub{max-width:none;}
            .pos-bars{grid-template-columns:1fr;text-align:left;width:100%;}
            .pos-tip{text-align:left;}
        }
    </style>
</x-filament-widgets::widget>
