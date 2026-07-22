{{-- Partner dashboard "Needs you" row: three signal cards, each ending in a
     suggestion + link. Self-contained (markup + inline CSS, theme-aware). --}}
@php $signals = $this->getSignals(); @endphp

<x-filament-widgets::widget>
    <div class="pna">
        <div class="pna-head">Needs your attention</div>
        <div class="pna-grid">
            @foreach ($signals as $s)
                <a href="{{ $s['url'] }}" class="pna-card tone-{{ $s['tone'] }}">
                    <div class="pna-top">
                        <span class="pna-ic">{{ $s['icon'] }}</span>
                        <span class="pna-lab">{{ $s['label'] }}</span>
                    </div>
                    <div class="pna-val">{{ $s['value'] }}</div>
                    <div class="pna-hint">{{ $s['hint'] }}</div>
                    <div class="pna-cta">{{ $s['cta'] }} <span aria-hidden="true">→</span></div>
                </a>
            @endforeach
        </div>
    </div>

    <style>
        .pna-head{font-size:12.5px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;
            color:#9aa2b1;margin:2px 0 10px;}
        .pna-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;}
        .pna-card{display:flex;flex-direction:column;gap:6px;text-decoration:none;
            background:#fff;border:1px solid #e7e9ee;border-left:3px solid #d6dae1;border-radius:14px;
            padding:16px 18px;box-shadow:0 1px 2px rgba(11,18,32,.06);
            transition:transform .12s cubic-bezier(.22,.61,.36,1),box-shadow .12s;}
        .pna-card:hover{transform:translateY(-2px);box-shadow:0 8px 22px -12px rgba(11,18,32,.22);}
        .pna-top{display:flex;align-items:center;gap:8px;}
        .pna-ic{font-size:15px;line-height:1;}
        .pna-lab{font-size:12.5px;font-weight:600;color:#6b7382;}
        .pna-val{font-size:24px;font-weight:800;color:#0b1220;letter-spacing:-.03em;
            font-variant-numeric:tabular-nums;line-height:1.1;}
        .pna-hint{font-size:12.5px;color:#6b7382;line-height:1.45;min-height:2.6em;}
        .pna-cta{font-size:12.5px;font-weight:700;color:#0a7d4e;margin-top:2px;}

        .pna-card.tone-warn{border-left-color:#c2790a;}
        .pna-card.tone-warn .pna-val{color:#b06d09;}
        .pna-card.tone-danger{border-left-color:#d64550;}
        .pna-card.tone-danger .pna-val{color:#c23c46;}
        .pna-card.tone-info{border-left-color:#2563eb;}
        .pna-card.tone-ok{border-left-color:#0f9d63;}

        .dark .pna-card{background:#111722;border-color:#1e2633;border-left-color:#2a3444;box-shadow:0 1px 2px rgba(0,0,0,.4);}
        .dark .pna-val{color:#eef1f6;}
        .dark .pna-lab,.dark .pna-hint{color:#8b94a5;}
        .dark .pna-cta{color:#28c882;}
        .dark .pna-card.tone-warn{border-left-color:#e2a13c;}
        .dark .pna-card.tone-warn .pna-val{color:#e2a13c;}
        .dark .pna-card.tone-danger{border-left-color:#f0757e;}
        .dark .pna-card.tone-danger .pna-val{color:#f0757e;}
        .dark .pna-card.tone-info{border-left-color:#5b9dff;}
        .dark .pna-card.tone-ok{border-left-color:#28c882;}

        @media (max-width:1024px){.pna-grid{grid-template-columns:1fr;}.pna-hint{min-height:0;}}
    </style>
</x-filament-widgets::widget>
