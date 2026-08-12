{{-- Partner dashboard "Recent bookings" — a premium card feed. Each row reads
     like a Stripe / BookMyShow-partner entry: avatar, who + what, a big revenue
     figure and a gradient status badge. Self-contained markup + inline CSS,
     theme-aware, no Vite rebuild. --}}
@php
    $bookings = $this->getBookings();
    $allUrl   = $this->allBookingsUrl();
@endphp

<x-filament-widgets::widget>
    <div class="prb">
        <div class="prb-head">
            <div class="prb-title">
                <span class="prb-live" aria-hidden="true"></span>
                Recent bookings
            </div>
            @if ($allUrl)
                <a href="{{ $allUrl }}" class="prb-all">View all <span aria-hidden="true">→</span></a>
            @endif
        </div>

        @if (empty($bookings))
            <div class="prb-empty">
                <div class="prb-empty-ic">🎟️</div>
                <div class="prb-empty-t">No bookings yet</div>
                <div class="prb-empty-d">Bookings from the app and walk-ins will appear here as they come in.</div>
            </div>
        @else
            <div class="prb-list">
                @foreach ($bookings as $i => $b)
                    <div class="prb-card tone-{{ $b['tone'] }}" style="--d:{{ $i * 45 }}ms">
                        <img class="prb-av" src="{{ $b['avatar'] }}" alt="" loading="lazy" />

                        <div class="prb-main">
                            <div class="prb-name">{{ $b['name'] }}</div>
                            @if ($b['line'])
                                <div class="prb-line">{{ $b['line'] }}</div>
                            @endif
                            <div class="prb-meta">
                                <span class="prb-badge tone-{{ $b['tone'] }}">
                                    <span class="prb-dot" aria-hidden="true"></span>{{ $b['status'] }}
                                </span>
                                <span class="prb-chan">{{ $b['channel'] }}</span>
                                <span class="prb-sep" aria-hidden="true">·</span>
                                <span class="prb-since" title="{{ $b['stamp'] }}">{{ $b['since'] }}</span>
                            </div>
                        </div>

                        <div class="prb-money">
                            <div class="prb-amt">{{ $b['amount'] }}</div>
                            <div class="prb-qty">{{ $b['qty'] }} {{ $b['qty'] === 1 ? 'ticket' : 'tickets' }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <style>
        .prb-head{display:flex;align-items:center;justify-content:space-between;margin:2px 0 12px;}
        .prb-title{display:flex;align-items:center;gap:9px;font-size:15px;font-weight:800;
            letter-spacing:-.01em;color:#0b1220;}
        .prb-live{width:8px;height:8px;border-radius:50%;background:#12b76a;position:relative;flex:none;
            box-shadow:0 0 0 3px rgba(18,183,106,.16);}
        .prb-live::after{content:"";position:absolute;inset:-3px;border-radius:50%;
            border:1px solid rgba(18,183,106,.5);animation:prb-ping 1.8s ease-out infinite;}
        @keyframes prb-ping{0%{transform:scale(.7);opacity:.9}100%{transform:scale(2.1);opacity:0}}
        .prb-all{font-size:12.5px;font-weight:700;color:#0a7d4e;text-decoration:none;white-space:nowrap;
            transition:opacity .12s;}
        .prb-all:hover{opacity:.7;}

        .prb-list{display:flex;flex-direction:column;gap:10px;}

        .prb-card{display:flex;align-items:center;gap:13px;background:#fff;border:1px solid #ebedf2;
            border-radius:16px;padding:14px 16px;box-shadow:0 1px 2px rgba(11,18,32,.05);position:relative;
            overflow:hidden;transition:transform .14s cubic-bezier(.22,.61,.36,1),box-shadow .14s,border-color .14s;
            animation:prb-in .38s cubic-bezier(.22,.61,.36,1) both;animation-delay:var(--d);}
        @keyframes prb-in{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
        .prb-card::before{content:"";position:absolute;left:0;top:0;bottom:0;width:3px;
            background:linear-gradient(180deg,#12b76a,#0a7d4e);opacity:0;transition:opacity .14s;}
        .prb-card:hover{transform:translateY(-2px);border-color:#dfe3ea;
            box-shadow:0 12px 26px -14px rgba(11,18,32,.28);}
        .prb-card:hover::before{opacity:1;}
        .prb-card.tone-warning::before{background:linear-gradient(180deg,#f5a623,#c2790a);}
        .prb-card.tone-danger::before{background:linear-gradient(180deg,#f0757e,#c23c46);}
        .prb-card.tone-gray::before{background:linear-gradient(180deg,#98a2b3,#667085);}

        .prb-av{width:44px;height:44px;border-radius:50%;flex:none;object-fit:cover;
            box-shadow:0 0 0 1px rgba(11,18,32,.06),0 2px 6px rgba(11,18,32,.12);}

        .prb-main{flex:1;min-width:0;}
        .prb-name{font-size:14.5px;font-weight:700;color:#0b1220;letter-spacing:-.01em;
            white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
        .prb-line{font-size:12.5px;color:#667085;margin-top:1px;
            white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
        .prb-meta{display:flex;align-items:center;gap:7px;margin-top:7px;font-size:11.5px;color:#98a2b3;}

        .prb-badge{display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:700;
            padding:3px 9px 3px 8px;border-radius:999px;letter-spacing:.01em;line-height:1;flex:none;
            color:#067a48;background:linear-gradient(180deg,#eafaf1,#d6f5e3);
            box-shadow:inset 0 0 0 1px rgba(16,140,86,.16);}
        .prb-dot{width:5px;height:5px;border-radius:50%;background:currentColor;flex:none;}
        .prb-badge.tone-warning{color:#a15c00;background:linear-gradient(180deg,#fff5e6,#ffe9c7);
            box-shadow:inset 0 0 0 1px rgba(194,121,10,.18);}
        .prb-badge.tone-danger{color:#b42318;background:linear-gradient(180deg,#fef0f0,#ffe0e0);
            box-shadow:inset 0 0 0 1px rgba(214,69,80,.18);}
        .prb-badge.tone-gray{color:#475467;background:linear-gradient(180deg,#f4f6f9,#e9edf3);
            box-shadow:inset 0 0 0 1px rgba(102,112,133,.16);}

        .prb-chan{font-weight:600;color:#667085;}
        .prb-sep{color:#cbd2dd;}
        .prb-since{color:#98a2b3;}

        .prb-money{text-align:right;flex:none;padding-left:6px;}
        .prb-amt{font-size:19px;font-weight:800;color:#0b1220;letter-spacing:-.03em;line-height:1.05;
            font-variant-numeric:tabular-nums;}
        .prb-qty{font-size:11px;font-weight:600;color:#98a2b3;margin-top:3px;}

        .prb-empty{text-align:center;padding:34px 16px;background:#fff;border:1px solid #ebedf2;
            border-radius:16px;}
        .prb-empty-ic{font-size:30px;line-height:1;margin-bottom:8px;}
        .prb-empty-t{font-size:15px;font-weight:700;color:#0b1220;}
        .prb-empty-d{font-size:12.5px;color:#667085;margin-top:4px;max-width:32ch;
            margin-left:auto;margin-right:auto;line-height:1.45;}

        /* ---- Dark theme ---- */
        .dark .prb-title{color:#eef1f6;}
        .dark .prb-all{color:#28c882;}
        .dark .prb-card{background:#111722;border-color:#1e2633;box-shadow:0 1px 2px rgba(0,0,0,.4);}
        .dark .prb-card:hover{border-color:#2a3444;box-shadow:0 12px 26px -14px rgba(0,0,0,.6);}
        .dark .prb-av{box-shadow:0 0 0 1px rgba(255,255,255,.08),0 2px 6px rgba(0,0,0,.4);}
        .dark .prb-name{color:#eef1f6;}
        .dark .prb-line{color:#8b94a5;}
        .dark .prb-meta,.dark .prb-since{color:#6b7382;}
        .dark .prb-chan{color:#8b94a5;}
        .dark .prb-sep{color:#3a4354;}
        .dark .prb-amt{color:#f4f7fb;}
        .dark .prb-qty{color:#6b7382;}
        .dark .prb-badge{color:#5ce6a4;background:linear-gradient(180deg,#0f2a1e,#0c2119);
            box-shadow:inset 0 0 0 1px rgba(40,200,130,.22);}
        .dark .prb-badge.tone-warning{color:#f0b862;background:linear-gradient(180deg,#2a2010,#211a0c);
            box-shadow:inset 0 0 0 1px rgba(226,161,60,.24);}
        .dark .prb-badge.tone-danger{color:#f59a9a;background:linear-gradient(180deg,#2a1414,#210f0f);
            box-shadow:inset 0 0 0 1px rgba(240,117,126,.24);}
        .dark .prb-badge.tone-gray{color:#aab3c2;background:linear-gradient(180deg,#1a2230,#151c28);
            box-shadow:inset 0 0 0 1px rgba(139,148,165,.2);}
        .dark .prb-empty{background:#111722;border-color:#1e2633;}
        .dark .prb-empty-t{color:#eef1f6;}
        .dark .prb-empty-d{color:#8b94a5;}

        @media (prefers-reduced-motion:reduce){
            .prb-card{animation:none;}
            .prb-live::after{animation:none;}
        }
        @media (max-width:480px){
            .prb-card{padding:12px 13px;gap:11px;}
            .prb-av{width:40px;height:40px;}
            .prb-amt{font-size:17px;}
        }
    </style>
</x-filament-widgets::widget>
