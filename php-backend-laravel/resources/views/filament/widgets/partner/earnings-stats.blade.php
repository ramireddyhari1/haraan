{{-- Partner earnings hero: a big "Collected" balance with a settlement progress
     bar, plus settled / pending tiles. Fintech-console feel (Stripe / RazorpayX).
     Self-contained markup + inline CSS, theme-aware, no Vite rebuild. --}}
@php $m = $this->getSummary(); @endphp

<x-filament-widgets::widget>
    <div class="pes">
        {{-- Hero balance --}}
        <div class="pes-hero">
            <div class="pes-hero-bg" aria-hidden="true"></div>
            <div class="pes-hero-in">
                <div class="pes-hero-top">
                    <span class="pes-hero-lab">Total collected</span>
                    <span class="pes-hero-month">
                        <svg viewBox="0 0 24 24" fill="none" width="13" height="13" aria-hidden="true">
                            <path d="M3 17l6-6 4 4 8-8" stroke="currentColor" stroke-width="2.2"
                                stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M21 7v5m0-5h-5" stroke="currentColor" stroke-width="2.2"
                                stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        {{ $m['collectedMonth'] }} this month
                    </span>
                </div>

                <div class="pes-hero-val">{{ $m['collected'] }}</div>

                <div class="pes-prog">
                    <div class="pes-prog-bar"><span style="width:{{ $m['pct'] }}%"></span></div>
                    <div class="pes-prog-cap">
                        <span><b>{{ $m['pct'] }}%</b> settled to you</span>
                        <span>{{ $m['settled'] }} of {{ $m['collected'] }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Settled / Pending tiles --}}
        <div class="pes-tiles">
            <div class="pes-tile tone-info">
                <div class="pes-tile-top">
                    <span class="pes-tile-ic">
                        <svg viewBox="0 0 24 24" fill="none" width="16" height="16" aria-hidden="true">
                            <path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2.4"
                                stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    <span class="pes-tile-lab">Settled to you</span>
                </div>
                <div class="pes-tile-val">{{ $m['settled'] }}</div>
                <div class="pes-tile-sub">Paid out so far</div>
            </div>

            <div class="pes-tile {{ $m['hasPending'] ? 'tone-warn' : 'tone-ok' }}">
                <div class="pes-tile-top">
                    <span class="pes-tile-ic">
                        <svg viewBox="0 0 24 24" fill="none" width="16" height="16" aria-hidden="true">
                            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/>
                            <path d="M12 7v5l3 2" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    <span class="pes-tile-lab">Pending</span>
                </div>
                <div class="pes-tile-val">{{ $m['pending'] }}</div>
                <div class="pes-tile-sub">{{ $m['hasPending'] ? 'Awaiting settlement' : 'All settled up' }}</div>
            </div>
        </div>

        <p class="pes-note">
            Collected is the money taken on your bookings; settlements are paid out by Haraan.
        </p>
    </div>

    <style>
        .pes{display:flex;flex-direction:column;gap:14px;}

        /* Hero */
        .pes-hero{position:relative;overflow:hidden;border-radius:20px;
            box-shadow:0 18px 40px -22px rgba(21,71,170,.55);animation:pes-in .45s cubic-bezier(.22,.61,.36,1) both;}
        .pes-hero-bg{position:absolute;inset:0;
            background:radial-gradient(120% 140% at 0% 0%,#3d9bff 0%,#2563eb 44%,#1a4fd0 100%);}
        .pes-hero-bg::after{content:"";position:absolute;inset:0;
            background:radial-gradient(80% 120% at 100% 0%,rgba(255,255,255,.22),transparent 60%);}
        .pes-hero-in{position:relative;padding:20px 22px 22px;color:#fff;}
        .pes-hero-top{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;}
        .pes-hero-lab{font-size:12px;font-weight:700;letter-spacing:.09em;text-transform:uppercase;
            color:rgba(255,255,255,.82);}
        .pes-hero-month{display:inline-flex;align-items:center;gap:5px;font-size:12px;font-weight:700;
            padding:4px 10px;border-radius:999px;background:rgba(255,255,255,.18);color:#fff;
            box-shadow:inset 0 0 0 1px rgba(255,255,255,.22);backdrop-filter:blur(2px);}
        .pes-hero-val{font-size:40px;font-weight:800;letter-spacing:-.035em;line-height:1.05;margin:10px 0 16px;
            font-variant-numeric:tabular-nums;text-shadow:0 1px 0 rgba(0,0,0,.08);}

        .pes-prog-bar{height:8px;border-radius:999px;background:rgba(255,255,255,.24);overflow:hidden;}
        .pes-prog-bar>span{display:block;height:100%;border-radius:999px;
            background:linear-gradient(90deg,#eafff5,#ffffff);
            box-shadow:0 0 10px rgba(255,255,255,.6);animation:pes-grow .7s cubic-bezier(.22,.61,.36,1) both;}
        @keyframes pes-grow{from{width:0 !important}}
        .pes-prog-cap{display:flex;align-items:center;justify-content:space-between;gap:10px;
            margin-top:8px;font-size:12px;color:rgba(255,255,255,.9);}
        .pes-prog-cap b{font-weight:800;color:#fff;}

        /* Tiles */
        .pes-tiles{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
        .pes-tile{position:relative;overflow:hidden;background:#fff;border:1px solid #ebedf2;border-radius:16px;
            padding:15px 16px;box-shadow:0 1px 2px rgba(11,18,32,.05);
            animation:pes-in .45s cubic-bezier(.22,.61,.36,1) both;animation-delay:.06s;}
        .pes-tile::before{content:"";position:absolute;left:0;top:0;bottom:0;width:3px;
            background:linear-gradient(180deg,#5b9dff,#2563eb);}
        .pes-tile.tone-warn::before{background:linear-gradient(180deg,#f5a623,#c2790a);}
        .pes-tile.tone-ok::before{background:linear-gradient(180deg,#12b76a,#0a7d4e);}
        .pes-tile-top{display:flex;align-items:center;gap:8px;}
        .pes-tile-ic{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;
            border-radius:9px;flex:none;color:#2563eb;background:linear-gradient(180deg,#eef4ff,#dbe8ff);
            box-shadow:inset 0 0 0 1px rgba(37,99,235,.16);}
        .pes-tile.tone-warn .pes-tile-ic{color:#b06d09;background:linear-gradient(180deg,#fff5e6,#ffe9c7);
            box-shadow:inset 0 0 0 1px rgba(194,121,10,.16);}
        .pes-tile.tone-ok .pes-tile-ic{color:#0a7d4e;background:linear-gradient(180deg,#eafaf1,#d6f5e3);
            box-shadow:inset 0 0 0 1px rgba(16,140,86,.16);}
        .pes-tile-lab{font-size:12px;font-weight:600;color:#667085;}
        .pes-tile-val{font-size:24px;font-weight:800;color:#0b1220;letter-spacing:-.03em;line-height:1.1;
            margin-top:10px;font-variant-numeric:tabular-nums;}
        .pes-tile.tone-info .pes-tile-val{color:#1e56c9;}
        .pes-tile.tone-warn .pes-tile-val{color:#b06d09;}
        .pes-tile-sub{font-size:11.5px;color:#98a2b3;margin-top:3px;}

        .pes-note{font-size:12px;color:#98a2b3;margin:0;line-height:1.45;}

        @keyframes pes-in{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}

        /* Dark */
        .dark .pes-tile{background:#111722;border-color:#1e2633;box-shadow:0 1px 2px rgba(0,0,0,.4);}
        .dark .pes-tile-lab{color:#8b94a5;}
        .dark .pes-tile-val{color:#eef1f6;}
        .dark .pes-tile.tone-info .pes-tile-val{color:#7fb0ff;}
        .dark .pes-tile.tone-warn .pes-tile-val{color:#e2a13c;}
        .dark .pes-tile-sub{color:#6b7382;}
        .dark .pes-tile-ic{background:linear-gradient(180deg,#0e1c33,#0b1626);
            box-shadow:inset 0 0 0 1px rgba(91,157,255,.22);color:#7fb0ff;}
        .dark .pes-tile.tone-warn .pes-tile-ic{background:linear-gradient(180deg,#2a2010,#211a0c);
            box-shadow:inset 0 0 0 1px rgba(226,161,60,.22);color:#e2a13c;}
        .dark .pes-tile.tone-ok .pes-tile-ic{background:linear-gradient(180deg,#0f2a1e,#0c2119);
            box-shadow:inset 0 0 0 1px rgba(40,200,130,.22);color:#28c882;}
        .dark .pes-note{color:#6b7382;}

        @media (prefers-reduced-motion:reduce){.pes-hero,.pes-tile{animation:none;}.pes-prog-bar>span{animation:none;}}
        @media (max-width:420px){
            .pes-hero-val{font-size:34px;}
            .pes-tiles{grid-template-columns:1fr;}
        }
    </style>
</x-filament-widgets::widget>
