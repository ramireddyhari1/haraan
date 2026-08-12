{{-- Partner dashboard "Needs you" row: three signal cards, each ending in a
     suggestion + link. Premium card treatment — tone-tinted icon chip, big
     value, alert-grade accents for warn/danger, calm for all-clear. Self-
     contained markup + inline CSS, theme-aware, no Vite rebuild. --}}
@php $signals = $this->getSignals(); @endphp

<x-filament-widgets::widget>
    <div class="pna">
        <div class="pna-head">
            <span class="pna-head-t">Needs your attention</span>
            <span class="pna-head-s">Signals worth acting on right now</span>
        </div>

        <div class="pna-grid">
            @foreach ($signals as $i => $s)
                <a href="{{ $s['url'] }}" class="pna-card tone-{{ $s['tone'] }}" style="--d:{{ $i * 60 }}ms">
                    <div class="pna-top">
                        <span class="pna-ic">{{ $s['icon'] }}</span>
                        <span class="pna-lab">{{ $s['label'] }}</span>
                        @if ($s['tone'] === 'ok')
                            <span class="pna-flag" aria-hidden="true">All clear</span>
                        @elseif ($s['tone'] === 'danger')
                            <span class="pna-flag" aria-hidden="true">Action</span>
                        @elseif ($s['tone'] === 'warn')
                            <span class="pna-flag" aria-hidden="true">Watch</span>
                        @endif
                    </div>

                    <div class="pna-val">{{ $s['value'] }}</div>
                    <div class="pna-hint">{{ $s['hint'] }}</div>
                    <div class="pna-cta">{{ $s['cta'] }} <span aria-hidden="true">→</span></div>
                </a>
            @endforeach
        </div>
    </div>

    <style>
        .pna-head{margin:2px 0 12px;}
        .pna-head-t{display:block;font-size:12.5px;font-weight:700;letter-spacing:.08em;
            text-transform:uppercase;color:#9aa2b1;}
        .pna-head-s{display:block;font-size:12.5px;color:#98a2b3;margin-top:2px;}

        .pna-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;}

        .pna-card{position:relative;display:flex;flex-direction:column;gap:7px;text-decoration:none;
            overflow:hidden;background:#fff;border:1px solid #ebedf2;border-radius:16px;
            padding:16px 18px 15px;box-shadow:0 1px 2px rgba(11,18,32,.05);
            transition:transform .14s cubic-bezier(.22,.61,.36,1),box-shadow .14s,border-color .14s;
            animation:pna-in .4s cubic-bezier(.22,.61,.36,1) both;animation-delay:var(--d);}
        @keyframes pna-in{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
        /* Tone accent bar down the left edge. */
        .pna-card::before{content:"";position:absolute;left:0;top:0;bottom:0;width:3px;
            background:linear-gradient(180deg,#12b76a,#0a7d4e);}
        .pna-card:hover{transform:translateY(-2px);border-color:#dfe3ea;
            box-shadow:0 14px 28px -16px rgba(11,18,32,.3);}

        .pna-top{display:flex;align-items:center;gap:9px;}
        .pna-ic{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;
            border-radius:10px;font-size:16px;line-height:1;flex:none;
            background:linear-gradient(180deg,#eafaf1,#d6f5e3);box-shadow:inset 0 0 0 1px rgba(16,140,86,.14);}
        .pna-lab{font-size:12.5px;font-weight:600;color:#475467;flex:1;min-width:0;
            white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
        .pna-flag{font-size:9.5px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;
            padding:3px 7px;border-radius:999px;flex:none;
            color:#067a48;background:linear-gradient(180deg,#eafaf1,#d6f5e3);
            box-shadow:inset 0 0 0 1px rgba(16,140,86,.18);}

        .pna-val{font-size:26px;font-weight:800;color:#0b1220;letter-spacing:-.03em;
            font-variant-numeric:tabular-nums;line-height:1.1;margin-top:2px;}
        .pna-hint{font-size:12.5px;color:#667085;line-height:1.45;min-height:2.6em;}
        .pna-cta{display:inline-flex;align-items:center;gap:5px;font-size:12.5px;font-weight:700;
            color:#0a7d4e;margin-top:2px;transition:gap .14s;}
        .pna-card:hover .pna-cta{gap:9px;}

        /* ---- Tones ---- */
        .pna-card.tone-warn::before{background:linear-gradient(180deg,#f5a623,#c2790a);}
        .pna-card.tone-warn{background:linear-gradient(180deg,#fffdf8,#fff);}
        .pna-card.tone-warn .pna-ic{background:linear-gradient(180deg,#fff5e6,#ffe9c7);
            box-shadow:inset 0 0 0 1px rgba(194,121,10,.16);}
        .pna-card.tone-warn .pna-val{color:#b06d09;}
        .pna-card.tone-warn .pna-flag{color:#a15c00;background:linear-gradient(180deg,#fff5e6,#ffe9c7);
            box-shadow:inset 0 0 0 1px rgba(194,121,10,.2);}
        .pna-card.tone-warn .pna-cta{color:#b06d09;}

        .pna-card.tone-danger::before{background:linear-gradient(180deg,#f0757e,#c23c46);}
        .pna-card.tone-danger{background:linear-gradient(180deg,#fffafa,#fff);}
        .pna-card.tone-danger .pna-ic{background:linear-gradient(180deg,#fef0f0,#ffe0e0);
            box-shadow:inset 0 0 0 1px rgba(214,69,80,.16);}
        .pna-card.tone-danger .pna-val{color:#c23c46;}
        .pna-card.tone-danger .pna-flag{color:#b42318;background:linear-gradient(180deg,#fef0f0,#ffe0e0);
            box-shadow:inset 0 0 0 1px rgba(214,69,80,.2);}
        .pna-card.tone-danger .pna-cta{color:#c23c46;}

        .pna-card.tone-info::before{background:linear-gradient(180deg,#5b9dff,#2563eb);}
        .pna-card.tone-info .pna-ic{background:linear-gradient(180deg,#eef4ff,#dbe8ff);
            box-shadow:inset 0 0 0 1px rgba(37,99,235,.16);}
        .pna-card.tone-info .pna-val{color:#2563eb;}
        .pna-card.tone-info .pna-cta{color:#2563eb;}

        /* ---- Dark theme ---- */
        .dark .pna-head-s{color:#6b7382;}
        .dark .pna-card{background:#111722;border-color:#1e2633;box-shadow:0 1px 2px rgba(0,0,0,.4);}
        .dark .pna-card:hover{border-color:#2a3444;box-shadow:0 14px 28px -16px rgba(0,0,0,.6);}
        .dark .pna-val{color:#eef1f6;}
        .dark .pna-lab{color:#8b94a5;}
        .dark .pna-hint{color:#8b94a5;}
        .dark .pna-ic{background:linear-gradient(180deg,#0f2a1e,#0c2119);
            box-shadow:inset 0 0 0 1px rgba(40,200,130,.2);}
        .dark .pna-flag{color:#5ce6a4;background:linear-gradient(180deg,#0f2a1e,#0c2119);
            box-shadow:inset 0 0 0 1px rgba(40,200,130,.24);}
        .dark .pna-cta{color:#28c882;}

        .dark .pna-card.tone-warn{background:#151209;}
        .dark .pna-card.tone-warn .pna-ic{background:linear-gradient(180deg,#2a2010,#211a0c);
            box-shadow:inset 0 0 0 1px rgba(226,161,60,.22);}
        .dark .pna-card.tone-warn .pna-val,.dark .pna-card.tone-warn .pna-cta{color:#e2a13c;}
        .dark .pna-card.tone-warn .pna-flag{color:#f0b862;background:linear-gradient(180deg,#2a2010,#211a0c);
            box-shadow:inset 0 0 0 1px rgba(226,161,60,.26);}

        .dark .pna-card.tone-danger{background:#171010;}
        .dark .pna-card.tone-danger .pna-ic{background:linear-gradient(180deg,#2a1414,#210f0f);
            box-shadow:inset 0 0 0 1px rgba(240,117,126,.22);}
        .dark .pna-card.tone-danger .pna-val,.dark .pna-card.tone-danger .pna-cta{color:#f0757e;}
        .dark .pna-card.tone-danger .pna-flag{color:#f59a9a;background:linear-gradient(180deg,#2a1414,#210f0f);
            box-shadow:inset 0 0 0 1px rgba(240,117,126,.26);}

        .dark .pna-card.tone-info .pna-ic{background:linear-gradient(180deg,#0e1c33,#0b1626);
            box-shadow:inset 0 0 0 1px rgba(91,157,255,.22);}
        .dark .pna-card.tone-info .pna-val,.dark .pna-card.tone-info .pna-cta{color:#5b9dff;}

        @media (prefers-reduced-motion:reduce){.pna-card{animation:none;}}
        @media (max-width:1024px){.pna-grid{grid-template-columns:1fr;}.pna-hint{min-height:0;}}
    </style>
</x-filament-widgets::widget>
