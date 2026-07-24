{{-- Events-overview activity feed — recent bookings as premium transaction
     cards. Self-contained markup + inline CSS, theme-aware. --}}
@php $bookings = $this->getBookings(); @endphp

<x-filament-widgets::widget>
    <div class="lb">
        <div class="lb-head">
            <span class="lb-live" aria-hidden="true"></span>
            Latest bookings
        </div>

        @if (empty($bookings))
            <div class="lb-empty">
                <div class="lb-empty-ic">🎟️</div>
                <div class="lb-empty-t">No bookings yet</div>
                <div class="lb-empty-d">Bookings from the app and walk-ins will appear here as they come in.</div>
            </div>
        @else
            <div class="lb-list">
                @foreach ($bookings as $i => $b)
                    <div class="lb-card tone-{{ $b['tone'] }}" wire:key="lb-{{ $i }}" style="--d:{{ min($i, 8) * 40 }}ms">
                        <img class="lb-av" src="{{ $b['avatar'] }}" alt="" loading="lazy" />
                        <div class="lb-main">
                            <div class="lb-name">{{ $b['name'] }}</div>
                            @if ($b['line'])<div class="lb-line">{{ $b['line'] }}</div>@endif
                            <div class="lb-meta">
                                <span class="lb-badge tone-{{ $b['tone'] }}"><span class="lb-dot" aria-hidden="true"></span>{{ $b['status'] }}</span>
                                <span class="lb-chan">{{ $b['channel'] }}</span>
                                <span class="lb-sep" aria-hidden="true">·</span>
                                <span class="lb-since" title="{{ $b['stamp'] }}">{{ $b['since'] }}</span>
                            </div>
                        </div>
                        <div class="lb-money">
                            <div class="lb-amt">{{ $b['amount'] }}</div>
                            <div class="lb-qty">{{ $b['qty'] }} {{ $b['qty'] === 1 ? 'ticket' : 'tickets' }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <style>
        .lb-head{display:flex;align-items:center;gap:9px;font-size:15px;font-weight:800;letter-spacing:-.01em;
            color:#0b1220;margin:2px 0 12px;}
        .lb-live{width:8px;height:8px;border-radius:50%;background:#12b76a;position:relative;flex:none;
            box-shadow:0 0 0 3px rgba(18,183,106,.16);}
        .lb-live::after{content:"";position:absolute;inset:-3px;border-radius:50%;
            border:1px solid rgba(18,183,106,.5);animation:lb-ping 1.8s ease-out infinite;}
        @keyframes lb-ping{0%{transform:scale(.7);opacity:.9}100%{transform:scale(2.1);opacity:0}}

        .lb-list{display:flex;flex-direction:column;gap:10px;}
        .lb-card{display:flex;align-items:center;gap:13px;background:#fff;border:1px solid #ebedf2;border-radius:16px;
            padding:14px 16px;box-shadow:0 1px 2px rgba(11,18,32,.05);position:relative;overflow:hidden;
            transition:transform .14s cubic-bezier(.22,.61,.36,1),box-shadow .14s,border-color .14s;
            animation:lb-in .38s cubic-bezier(.22,.61,.36,1) both;animation-delay:var(--d);}
        @keyframes lb-in{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
        .lb-card::before{content:"";position:absolute;left:0;top:0;bottom:0;width:3px;
            background:linear-gradient(180deg,#12b76a,#0a7d4e);opacity:0;transition:opacity .14s;}
        .lb-card:hover{transform:translateY(-2px);border-color:#dfe3ea;box-shadow:0 12px 26px -14px rgba(11,18,32,.28);}
        .lb-card:hover::before{opacity:1;}
        .lb-card.tone-warning::before{background:linear-gradient(180deg,#f5a623,#c2790a);}
        .lb-card.tone-danger::before{background:linear-gradient(180deg,#f0757e,#c23c46);}
        .lb-card.tone-gray::before{background:linear-gradient(180deg,#98a2b3,#667085);}

        .lb-av{width:44px;height:44px;border-radius:50%;flex:none;object-fit:cover;
            box-shadow:0 0 0 1px rgba(11,18,32,.06),0 2px 6px rgba(11,18,32,.12);}
        .lb-main{flex:1;min-width:0;}
        .lb-name{font-size:14.5px;font-weight:700;color:#0b1220;letter-spacing:-.01em;
            white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
        .lb-line{font-size:12.5px;color:#667085;margin-top:1px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
        .lb-meta{display:flex;align-items:center;gap:7px;margin-top:7px;font-size:11.5px;color:#98a2b3;}
        .lb-badge{display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:700;
            padding:3px 9px 3px 8px;border-radius:999px;line-height:1;flex:none;
            color:#067a48;background:linear-gradient(180deg,#eafaf1,#d6f5e3);box-shadow:inset 0 0 0 1px rgba(16,140,86,.16);}
        .lb-dot{width:5px;height:5px;border-radius:50%;background:currentColor;flex:none;}
        .lb-badge.tone-warning{color:#a15c00;background:linear-gradient(180deg,#fff5e6,#ffe9c7);box-shadow:inset 0 0 0 1px rgba(194,121,10,.18);}
        .lb-badge.tone-danger{color:#b42318;background:linear-gradient(180deg,#fef0f0,#ffe0e0);box-shadow:inset 0 0 0 1px rgba(214,69,80,.18);}
        .lb-badge.tone-gray{color:#475467;background:linear-gradient(180deg,#f4f6f9,#e9edf3);box-shadow:inset 0 0 0 1px rgba(102,112,133,.16);}
        .lb-chan{font-weight:600;color:#667085;}
        .lb-sep{color:#cbd2dd;}

        .lb-money{text-align:right;flex:none;padding-left:6px;}
        .lb-amt{font-size:19px;font-weight:800;color:#0b1220;letter-spacing:-.03em;line-height:1.05;
            font-variant-numeric:tabular-nums;}
        .lb-qty{font-size:11px;font-weight:600;color:#98a2b3;margin-top:3px;}

        .lb-empty{text-align:center;padding:34px 16px;background:#fff;border:1px solid #ebedf2;border-radius:16px;}
        .lb-empty-ic{font-size:30px;line-height:1;margin-bottom:8px;}
        .lb-empty-t{font-size:15px;font-weight:700;color:#0b1220;}
        .lb-empty-d{font-size:12.5px;color:#667085;margin-top:4px;max-width:32ch;margin:4px auto 0;line-height:1.45;}

        /* Dark */
        .dark .lb-head{color:#eef1f6;}
        .dark .lb-card{background:#111722;border-color:#1e2633;box-shadow:0 1px 2px rgba(0,0,0,.4);}
        .dark .lb-card:hover{border-color:#2a3444;box-shadow:0 12px 26px -14px rgba(0,0,0,.6);}
        .dark .lb-av{box-shadow:0 0 0 1px rgba(255,255,255,.08),0 2px 6px rgba(0,0,0,.4);}
        .dark .lb-name{color:#eef1f6;}
        .dark .lb-line{color:#8b94a5;}
        .dark .lb-meta,.dark .lb-since{color:#6b7382;}
        .dark .lb-chan{color:#8b94a5;}
        .dark .lb-sep{color:#3a4354;}
        .dark .lb-amt{color:#f4f7fb;}
        .dark .lb-qty{color:#6b7382;}
        .dark .lb-badge{color:#5ce6a4;background:linear-gradient(180deg,#0f2a1e,#0c2119);box-shadow:inset 0 0 0 1px rgba(40,200,130,.22);}
        .dark .lb-badge.tone-warning{color:#f0b862;background:linear-gradient(180deg,#2a2010,#211a0c);box-shadow:inset 0 0 0 1px rgba(226,161,60,.24);}
        .dark .lb-badge.tone-danger{color:#f59a9a;background:linear-gradient(180deg,#2a1414,#210f0f);box-shadow:inset 0 0 0 1px rgba(240,117,126,.24);}
        .dark .lb-badge.tone-gray{color:#aab3c2;background:linear-gradient(180deg,#1a2230,#151c28);box-shadow:inset 0 0 0 1px rgba(139,148,165,.2);}
        .dark .lb-empty{background:#111722;border-color:#1e2633;}
        .dark .lb-empty-t{color:#eef1f6;}
        .dark .lb-empty-d{color:#8b94a5;}

        @media (prefers-reduced-motion:reduce){.lb-card{animation:none;}.lb-live::after{animation:none;}}
        @media (max-width:480px){.lb-card{padding:12px 13px;gap:11px;}.lb-av{width:40px;height:40px;}.lb-amt{font-size:17px;}}
    </style>
</x-filament-widgets::widget>
