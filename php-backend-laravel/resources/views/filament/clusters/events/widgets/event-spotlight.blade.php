{{-- Events overview hero — a clean, cinematic poster for the active event, with
     revenue / tickets / one insight in a separate calm card below. Restrained,
     BookMyShow-grade hierarchy. Self-contained markup + inline CSS, theme-aware. --}}
@php $s = $this->getSpotlight(); @endphp

<x-filament-widgets::widget>
    @if ($s === null)
        <a href="{{ $this->createUrl() }}" class="eh-blank">
            <div class="eh-blank-ic" aria-hidden="true">🎬</div>
            <div class="eh-blank-t">Your event story starts here</div>
            <div class="eh-blank-d">Create your first event and this space becomes a live view of how it’s performing.</div>
            <span class="eh-blank-cta">Create an event <span aria-hidden="true">→</span></span>
        </a>
    @else
        {{-- Poster hero --}}
        <div class="eh-card">
            @if ($s['poster'])
                <img class="eh-bg" src="{{ $s['poster'] }}" alt="" loading="lazy" />
            @else
                <div class="eh-bg eh-bg-fallback" aria-hidden="true"></div>
            @endif
            <div class="eh-scrim" aria-hidden="true"></div>

            <div class="eh-in">
                <div class="eh-top">
                    <span class="eh-eyebrow"><span class="eh-dot"></span>{{ $s['isFuture'] ? 'Your next event' : 'Latest event' }}</span>
                    <span class="eh-status tone-{{ $s['sTone'] }}">{{ $s['sLabel'] }}</span>
                </div>

                <div class="eh-foot">
                    <h2 class="eh-title">{{ $s['title'] }}</h2>
                    <div class="eh-meta">
                        {{ $s['whenWhere'] }}@if ($s['countdown'])<span class="eh-soon"> · {{ $s['countdown'] }}</span>@endif
                    </div>

                    @if ($s['seatsText'])
                        <div class="eh-prog">
                            <div class="eh-prog-top"><b>{{ $s['pct'] }}%</b> sold<span class="eh-prog-seats">{{ $s['seatsText'] }}</span></div>
                            <div class="eh-bar"><span style="width:{{ max(2, $s['pct']) }}%"></span></div>
                        </div>
                    @endif

                    <div class="eh-cta">
                        <a href="{{ $s['analytics'] }}" class="eh-btn eh-btn-primary">
                            <svg viewBox="0 0 24 24" fill="none" width="15" height="15" aria-hidden="true">
                                <path d="M4 19V5M4 19h16M8 16v-5M13 16V8M18 16v-3" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            Analytics
                        </a>
                        <a href="{{ $s['manage'] }}" class="eh-btn">Manage</a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Metrics card --}}
        <div class="em-card">
            <div class="em-grid">
                <div class="em-cell">
                    <div class="em-lab">Revenue</div>
                    <div class="em-val">{{ $s['revenue'] }}</div>
                </div>
                <div class="em-div" aria-hidden="true"></div>
                <div class="em-cell">
                    <div class="em-lab">Tickets sold</div>
                    <div class="em-val">{{ $s['sold'] }}<span>/ {{ $s['total'] }}</span></div>
                </div>
                <div class="em-div" aria-hidden="true"></div>
                <div class="em-cell">
                    <div class="em-lab">Last 7 days</div>
                    <div class="em-val">@if ($s['recent7'] > 0)<span class="em-up" aria-hidden="true">▲</span>@endif{{ $s['recent7'] }}<span>sold</span></div>
                </div>
            </div>

            @if ($s['primary'])
                @php $ins = $s['primary']; $tag = ! empty($ins['url']) ? 'a' : 'div'; @endphp
                <{{ $tag }} class="em-insight tone-{{ $ins['tone'] }}" @if (! empty($ins['url'])) href="{{ $ins['url'] }}" target="_blank" rel="noopener" @endif>
                    <span class="em-insight-ic" aria-hidden="true">{{ $ins['ic'] }}</span>
                    <span>{{ $ins['text'] }}</span>
                    @if (! empty($ins['url']))<span class="em-insight-go" aria-hidden="true">→</span>@endif
                </{{ $tag }}>
            @endif
        </div>
    @endif

    <style>
        /* ── Poster hero ── */
        .eh-card{position:relative;overflow:hidden;border-radius:22px;min-height:328px;display:flex;isolation:isolate;
            box-shadow:0 20px 48px -26px rgba(11,18,32,.5),0 2px 6px rgba(11,18,32,.12);
            animation:eh-in .5s cubic-bezier(.22,.61,.36,1) both;}
        @keyframes eh-in{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}
        .eh-bg{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;object-position:center 22%;z-index:0;
            transition:transform 8s ease-out;}
        .eh-card:hover .eh-bg{transform:scale(1.05);}
        .eh-bg-fallback{background:radial-gradient(120% 130% at 15% 0%,#4d80ff,#2f6bff 46%,#123a9e);}
        /* Poster stays visible up top; dark only where the text sits. */
        .eh-scrim{position:absolute;inset:0;z-index:1;
            background:
                linear-gradient(180deg,rgba(8,16,42,.42) 0%,rgba(8,16,42,0) 20%,rgba(8,16,42,0) 44%,rgba(9,20,56,.72) 74%,rgba(10,24,66,.95) 100%);}
        .eh-in{position:relative;z-index:2;display:flex;flex-direction:column;width:100%;padding:18px 20px;color:#fff;}

        .eh-top{display:flex;align-items:center;justify-content:space-between;gap:10px;}
        .eh-eyebrow{display:inline-flex;align-items:center;gap:7px;font-size:11px;font-weight:800;letter-spacing:.1em;
            text-transform:uppercase;color:rgba(255,255,255,.94);text-shadow:0 1px 10px rgba(6,14,40,.6);}
        .eh-dot{width:7px;height:7px;border-radius:50%;background:#34e5a0;box-shadow:0 0 0 3px rgba(52,229,160,.28);}
        .eh-status{font-size:11px;font-weight:800;padding:5px 11px;border-radius:999px;color:#fff;
            background:rgba(255,255,255,.18);box-shadow:inset 0 0 0 1px rgba(255,255,255,.22);backdrop-filter:blur(6px);}
        .eh-status.tone-success{background:rgba(18,183,106,.42);}
        .eh-status.tone-warning{background:rgba(245,166,35,.44);}
        .eh-status.tone-danger{background:rgba(240,90,100,.46);}

        .eh-foot{margin-top:auto;}
        .eh-title{font-size:27px;font-weight:800;letter-spacing:-.04em;line-height:1.08;margin:0;max-width:18ch;
            text-shadow:0 2px 18px rgba(6,14,40,.55);}
        .eh-meta{font-size:12.5px;color:rgba(255,255,255,.9);margin-top:7px;font-weight:500;
            text-shadow:0 1px 10px rgba(6,14,40,.6);}
        .eh-soon{color:#fff;font-weight:700;}

        .eh-prog{margin-top:14px;max-width:420px;}
        .eh-prog-top{display:flex;align-items:baseline;gap:8px;font-size:12.5px;color:rgba(255,255,255,.86);}
        .eh-prog-top b{font-size:15px;font-weight:800;color:#fff;letter-spacing:-.02em;}
        .eh-prog-seats{margin-left:auto;font-weight:700;color:#fff;}
        .eh-bar{height:7px;border-radius:999px;background:rgba(255,255,255,.24);overflow:hidden;margin-top:7px;}
        .eh-bar>span{display:block;height:100%;border-radius:999px;background:#fff;box-shadow:0 0 10px rgba(255,255,255,.5);
            animation:eh-grow .7s cubic-bezier(.22,.61,.36,1) both;}
        @keyframes eh-grow{from{width:0 !important}}

        .eh-cta{display:flex;gap:9px;margin-top:16px;}
        .eh-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;font-size:13px;font-weight:700;
            padding:10px 16px;border-radius:12px;text-decoration:none;color:#fff;
            background:rgba(255,255,255,.16);box-shadow:inset 0 0 0 1px rgba(255,255,255,.22);backdrop-filter:blur(6px);
            transition:background .13s,transform .1s;}
        .eh-btn:hover{background:rgba(255,255,255,.26);transform:translateY(-1px);}
        .eh-btn-primary{color:#14306b;background:#fff;box-shadow:none;}
        .eh-btn-primary:hover{background:#eef2f8;}

        /* ── Metrics card ── */
        .em-card{margin-top:12px;background:#fff;border:1px solid #ebedf2;border-radius:16px;padding:16px 18px;
            box-shadow:0 1px 2px rgba(11,18,32,.05);
            animation:eh-in .5s cubic-bezier(.22,.61,.36,1) both;animation-delay:.06s;}
        .em-grid{display:flex;align-items:center;gap:8px;}
        .em-cell{flex:1;min-width:0;}
        .em-div{width:1px;align-self:stretch;background:#eef1f5;flex:none;margin:2px 0;}
        .em-lab{font-size:11px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:#98a2b3;}
        .em-val{font-size:22px;font-weight:800;color:#0b1220;letter-spacing:-.035em;margin-top:5px;
            font-variant-numeric:tabular-nums;white-space:nowrap;}
        .em-val span{font-size:12px;font-weight:600;color:#98a2b3;margin-left:4px;}
        .em-val .em-up{font-size:12px;color:#0a9d57;margin:0 3px 0 0;}

        .em-insight{display:flex;align-items:center;gap:9px;margin-top:15px;padding:12px 14px;border-radius:13px;
            font-size:13px;font-weight:600;text-decoration:none;color:#344054;background:#f4f6fa;
            box-shadow:inset 0 0 0 1px rgba(120,120,120,.08);transition:background .13s,transform .1s;}
        a.em-insight:hover{transform:translateY(-1px);}
        .em-insight-ic{font-size:15px;line-height:1;}
        .em-insight-go{margin-left:auto;font-weight:800;color:#2563eb;}
        .em-insight.tone-up{color:#067a48;background:#eafaf1;box-shadow:inset 0 0 0 1px rgba(16,140,86,.16);}
        .em-insight.tone-warn{color:#8a5a00;background:#fff6e8;box-shadow:inset 0 0 0 1px rgba(194,121,10,.18);}
        .em-insight.tone-info{color:#1e56c9;background:#eef4ff;box-shadow:inset 0 0 0 1px rgba(37,99,235,.16);}
        .em-insight.tone-action{color:#fff;background:#2563eb;box-shadow:0 8px 18px -8px rgba(37,99,235,.5);}
        .em-insight.tone-action .em-insight-go{color:#fff;}
        a.em-insight.tone-action:hover{background:#1d4ed8;}

        /* ── Empty ── */
        .eh-blank{display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;
            min-height:260px;padding:32px;border-radius:22px;text-decoration:none;color:#fff;
            background:radial-gradient(120% 130% at 15% 0%,#2f6bff,#1e50e6 55%,#123a9e);
            box-shadow:0 18px 44px -24px rgba(11,18,32,.55);}
        .eh-blank-ic{font-size:36px;margin-bottom:12px;}
        .eh-blank-t{font-size:19px;font-weight:800;letter-spacing:-.02em;}
        .eh-blank-d{font-size:12.5px;color:rgba(255,255,255,.75);margin-top:6px;max-width:36ch;line-height:1.5;}
        .eh-blank-cta{margin-top:16px;font-size:13px;font-weight:800;color:#14306b;background:#fff;padding:10px 18px;border-radius:12px;}

        /* ── Dark ── */
        .dark .em-card{background:#111722;border-color:#1e2633;box-shadow:0 1px 2px rgba(0,0,0,.4);}
        .dark .em-val{color:#eef1f6;}
        .dark .em-div{background:#1e2633;}
        .dark .em-insight{color:#cbd2dd;background:#1a2230;box-shadow:inset 0 0 0 1px rgba(255,255,255,.05);}
        .dark .em-insight.tone-up{color:#5ce6a4;background:#0f2a1e;}
        .dark .em-insight.tone-warn{color:#e6c766;background:#221c0d;}
        .dark .em-insight.tone-info{color:#7fb0ff;background:#16233b;}
        .dark .em-insight.tone-action{color:#fff;background:#2563eb;}

        @media (prefers-reduced-motion:reduce){.eh-card,.em-card{animation:none;}.eh-card:hover .eh-bg{transform:none;}.eh-bar>span{animation:none;}}
        @media (min-width:760px){
            .eh-card{min-height:360px;}
            .eh-title{font-size:32px;max-width:20ch;}
        }
        @media (max-width:480px){
            .eh-title{font-size:24px;}
            .em-grid{gap:6px;}
            .em-val{font-size:19px;}
        }
    </style>
</x-filament-widgets::widget>
