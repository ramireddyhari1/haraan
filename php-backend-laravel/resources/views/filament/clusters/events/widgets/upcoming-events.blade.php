{{-- Upcoming events as premium cards: poster, date chip, when/where, a ticket
     sell-through meter, a health pill and quick actions. Self-contained,
     theme-aware. --}}
@php $events = $this->getEvents(); @endphp

<x-filament-widgets::widget>
    @if (! empty($events))
    <div class="ue">
        <div class="ue-head">More upcoming events</div>
            <div class="ue-grid">
                @foreach ($events as $i => $e)
                    <div class="ue-card" wire:key="ue-{{ $i }}" style="--d:{{ min($i, 6) * 50 }}ms">
                        <a href="{{ $e['analytics'] }}" class="ue-poster tone-{{ $e['sTone'] }}">
                            @if ($e['poster'])
                                <img src="{{ $e['poster'] }}" alt="" loading="lazy" />
                            @else
                                <span class="ue-poster-glyph" aria-hidden="true">🎫</span>
                            @endif
                            <span class="ue-date"><b>{{ $e['day'] }}</b>{{ $e['mon'] }}</span>
                            <span class="ue-pill tone-{{ $e['sTone'] }}">{{ $e['sLabel'] }}</span>
                        </a>

                        <div class="ue-body">
                            <a href="{{ $e['analytics'] }}" class="ue-title">{{ $e['title'] }}</a>
                            @if ($e['whenWhere'])
                                <div class="ue-meta">{{ $e['whenWhere'] }}</div>
                            @endif

                            <div class="ue-tickets">
                                <div class="ue-tickets-top">
                                    <span class="ue-tickets-n">{{ $e['sold'] }} <span>/ {{ $e['total'] }}</span></span>
                                    <span class="ue-tickets-pct">{{ $e['pct'] }}%</span>
                                </div>
                                <div class="ue-meter tone-{{ $e['sTone'] }}"><span style="width:{{ $e['pct'] }}%"></span></div>
                            </div>

                            <div class="ue-actions">
                                <a href="{{ $e['analytics'] }}" class="ue-btn ue-btn-primary">
                                    <svg viewBox="0 0 24 24" fill="none" width="14" height="14" aria-hidden="true">
                                        <path d="M4 19V5M4 19h16M8 16v-5M13 16V8M18 16v-3" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                    Analytics
                                </a>
                                <a href="{{ $e['manage'] }}" class="ue-btn">
                                    <svg viewBox="0 0 24 24" fill="none" width="14" height="14" aria-hidden="true">
                                        <path d="M4 20h4L18.5 9.5a2.1 2.1 0 00-3-3L5 17v3z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                                    </svg>
                                    Manage
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
    </div>
    @endif

    <style>
        .ue-head{font-size:15px;font-weight:800;letter-spacing:-.01em;color:#0b1220;margin:2px 0 12px;}
        .ue-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
        .ue-card{display:flex;flex-direction:column;background:#fff;border:1px solid #ebedf2;border-radius:18px;
            overflow:hidden;box-shadow:0 1px 2px rgba(11,18,32,.05);
            transition:transform .15s cubic-bezier(.22,.61,.36,1),box-shadow .15s,border-color .15s;
            animation:ue-in .42s cubic-bezier(.22,.61,.36,1) both;animation-delay:var(--d);}
        .ue-card:hover{transform:translateY(-3px);border-color:#dfe3ea;box-shadow:0 16px 34px -18px rgba(11,18,32,.32);}
        @keyframes ue-in{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}

        .ue-poster{position:relative;display:block;height:118px;overflow:hidden;text-decoration:none;
            background:linear-gradient(150deg,#eef4ff,#dbe8ff);}
        .ue-poster.tone-success{background:linear-gradient(150deg,#eafaf1,#d6f5e3);}
        .ue-poster.tone-warning{background:linear-gradient(150deg,#fff5e6,#ffe9c7);}
        .ue-poster.tone-danger{background:linear-gradient(150deg,#fdecee,#ffdfe2);}
        .ue-poster img{width:100%;height:100%;object-fit:cover;display:block;
            transition:transform .35s cubic-bezier(.22,.61,.36,1);}
        .ue-card:hover .ue-poster img{transform:scale(1.05);}
        .ue-poster-glyph{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:34px;opacity:.5;}
        .ue-date{position:absolute;top:10px;left:10px;display:flex;flex-direction:column;align-items:center;
            line-height:1;padding:5px 9px;border-radius:10px;background:rgba(255,255,255,.94);color:#0b1220;
            box-shadow:0 4px 10px -4px rgba(11,18,32,.3);backdrop-filter:blur(2px);}
        .ue-date b{font-size:16px;font-weight:800;letter-spacing:-.02em;}
        .ue-date{font-size:9px;font-weight:800;letter-spacing:.08em;color:#667085;}
        .ue-pill{position:absolute;top:11px;right:10px;font-size:10px;font-weight:800;letter-spacing:.02em;
            padding:4px 9px;border-radius:999px;color:#067a48;background:rgba(255,255,255,.94);
            box-shadow:0 2px 6px -2px rgba(11,18,32,.25);}
        .ue-pill.tone-warning{color:#a15c00;}
        .ue-pill.tone-danger{color:#b42318;}
        .ue-pill.tone-gray{color:#475467;}

        .ue-body{padding:13px 14px 14px;display:flex;flex-direction:column;gap:0;}
        .ue-title{font-size:14.5px;font-weight:700;color:#0b1220;letter-spacing:-.01em;line-height:1.3;text-decoration:none;
            display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
        .ue-title:hover{color:#2563eb;}
        .ue-meta{font-size:11.5px;color:#98a2b3;margin-top:4px;
            white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}

        .ue-tickets{margin-top:12px;}
        .ue-tickets-top{display:flex;align-items:baseline;justify-content:space-between;}
        .ue-tickets-n{font-size:13px;font-weight:800;color:#0b1220;font-variant-numeric:tabular-nums;}
        .ue-tickets-n span{font-size:11px;font-weight:600;color:#98a2b3;}
        .ue-tickets-pct{font-size:11.5px;font-weight:700;color:#0a9d57;}
        .ue-meter{height:6px;border-radius:999px;background:#eef1f5;overflow:hidden;margin-top:6px;}
        .ue-meter>span{display:block;height:100%;border-radius:999px;background:#0a9d57;
            animation:ue-grow .7s cubic-bezier(.22,.61,.36,1) both;}
        .ue-meter.tone-warning>span{background:#c2790a;}
        .ue-meter.tone-danger>span{background:#d64550;}
        .ue-meter.tone-gray>span{background:#98a2b3;}
        @keyframes ue-grow{from{width:0 !important}}

        .ue-actions{display:flex;gap:8px;margin-top:14px;}
        .ue-btn{flex:1;display:inline-flex;align-items:center;justify-content:center;gap:6px;font-size:12.5px;
            font-weight:700;padding:9px 10px;border-radius:11px;text-decoration:none;cursor:pointer;
            color:#344054;background:#f4f6fa;border:1px solid transparent;
            transition:background .13s,color .13s,transform .1s,border-color .13s;}
        .ue-btn:hover{background:#eaeff6;transform:translateY(-1px);}
        .ue-btn-primary{color:#fff;background:#2563eb;}
        .ue-btn-primary:hover{background:#1d4ed8;}

        .ue-empty{text-align:center;padding:34px 16px;background:#fff;border:1px solid #ebedf2;border-radius:18px;}
        .ue-empty-ic{font-size:30px;line-height:1;margin-bottom:8px;}
        .ue-empty-t{font-size:15px;font-weight:700;color:#0b1220;}
        .ue-empty-d{font-size:12.5px;color:#667085;margin-top:4px;}

        /* Dark */
        .dark .ue-head{color:#eef1f6;}
        .dark .ue-card{background:#111722;border-color:#1e2633;box-shadow:0 1px 2px rgba(0,0,0,.4);}
        .dark .ue-card:hover{border-color:#2a3444;box-shadow:0 16px 34px -18px rgba(0,0,0,.6);}
        .dark .ue-poster{background:linear-gradient(150deg,#0e1c33,#0b1626);}
        .dark .ue-date{background:rgba(20,27,38,.92);color:#eef1f6;}
        .dark .ue-pill{background:rgba(20,27,38,.92);color:#5ce6a4;}
        .dark .ue-pill.tone-warning{color:#f0b862;}
        .dark .ue-pill.tone-danger{color:#f59a9a;}
        .dark .ue-pill.tone-gray{color:#aab3c2;}
        .dark .ue-title{color:#eef1f6;}
        .dark .ue-title:hover{color:#7fb0ff;}
        .dark .ue-meta{color:#6b7382;}
        .dark .ue-tickets-n{color:#eef1f6;}
        .dark .ue-meter{background:#1a2230;}
        .dark .ue-btn{color:#cbd2dd;background:#1a2230;}
        .dark .ue-btn:hover{background:#222c3b;}
        .dark .ue-btn-primary{color:#fff;background:#2563eb;}
        .dark .ue-empty{background:#111722;border-color:#1e2633;}
        .dark .ue-empty-t{color:#eef1f6;}
        .dark .ue-empty-d{color:#8b94a5;}

        @media (prefers-reduced-motion:reduce){.ue-card{animation:none;}.ue-meter>span{animation:none;}}
        @media (max-width:720px){.ue-grid{grid-template-columns:1fr;}}
    </style>
</x-filament-widgets::widget>
