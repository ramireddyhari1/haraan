{{-- Portfolio strip — whole-catalog context beneath the event hero. Four merged
     metrics on one elegant surface (not four boxes). Self-contained, theme-aware. --}}
@php $m = $this->getSummary(); @endphp

<x-filament-widgets::widget>
    <div class="ep">
        <div class="ep-cap">Across all your events</div>
        <div class="ep-row">
            <div class="ep-cell">
                <div class="ep-val">{{ $m['events'] }}</div>
                <div class="ep-lab">Events</div>
            </div>
            <div class="ep-div" aria-hidden="true"></div>
            <div class="ep-cell">
                <div class="ep-val">{{ $m['sold'] }}<span>/ {{ $m['totalSeats'] }}</span></div>
                <div class="ep-lab">Tickets sold</div>
            </div>
            <div class="ep-div" aria-hidden="true"></div>
            <div class="ep-cell">
                <div class="ep-val">{{ $m['revenue'] }}</div>
                <div class="ep-lab">Revenue · {{ $m['bookings'] }} bookings</div>
            </div>
            <div class="ep-div" aria-hidden="true"></div>
            <div class="ep-cell ep-cell-st">
                <div class="ep-st">
                    <div class="ep-st-ring tone-{{ $m['tone'] }}" style="--p:{{ $m['sellThrough'] }}"><span>{{ $m['sellThrough'] }}%</span></div>
                    <div>
                        <div class="ep-val ep-val-sm">Sell-through</div>
                        <div class="ep-lab">{{ $m['soldOut'] > 0 ? $m['soldOut'].' sold out' : 'Seats vs capacity' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .ep{background:#fff;border:1px solid #ebedf2;border-radius:16px;padding:15px 18px;
            box-shadow:0 1px 2px rgba(11,18,32,.05);}
        .ep-cap{font-size:11px;font-weight:700;letter-spacing:.09em;text-transform:uppercase;color:#9aa2b1;margin-bottom:12px;}
        .ep-row{display:flex;align-items:center;gap:8px;}
        .ep-cell{flex:1;min-width:0;}
        .ep-cell-st{flex:1.4;}
        .ep-div{width:1px;align-self:stretch;background:#eef1f5;flex:none;margin:2px 0;}
        .ep-val{font-size:22px;font-weight:800;color:#0b1220;letter-spacing:-.035em;line-height:1.1;
            font-variant-numeric:tabular-nums;white-space:nowrap;}
        .ep-val-sm{font-size:14px;letter-spacing:-.01em;}
        .ep-val span{font-size:12px;font-weight:600;color:#98a2b3;margin-left:4px;}
        .ep-lab{font-size:11.5px;color:#98a2b3;margin-top:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}

        .ep-st{display:flex;align-items:center;gap:11px;}
        .ep-st-ring{--p:0;position:relative;width:44px;height:44px;flex:none;border-radius:50%;
            background:conic-gradient(var(--ac,#2563eb) calc(var(--p)*1%),#eef1f5 0);
            display:flex;align-items:center;justify-content:center;}
        .ep-st-ring::before{content:"";position:absolute;inset:5px;border-radius:50%;background:#fff;}
        .ep-st-ring span{position:relative;font-size:11px;font-weight:800;color:#0b1220;font-variant-numeric:tabular-nums;}
        .ep-st-ring.tone-cool{--ac:#2563eb;} .ep-st-ring.tone-warm{--ac:#c2790a;} .ep-st-ring.tone-hot{--ac:#d64550;}

        .dark .ep{background:#111722;border-color:#1e2633;box-shadow:0 1px 2px rgba(0,0,0,.4);}
        .dark .ep-val,.dark .ep-st-ring span{color:#eef1f6;}
        .dark .ep-lab{color:#6b7382;}
        .dark .ep-div{background:#1e2633;}
        .dark .ep-st-ring::before{background:#111722;}
        .dark .ep-st-ring{background:conic-gradient(var(--ac) calc(var(--p)*1%),#1a2230 0);}
        .dark .ep-st-ring.tone-cool{--ac:#5b9dff;} .dark .ep-st-ring.tone-warm{--ac:#e2a13c;} .dark .ep-st-ring.tone-hot{--ac:#f0757e;}

        @media (max-width:820px){
            .ep-row{flex-wrap:wrap;gap:14px 8px;}
            .ep-cell{flex-basis:calc(50% - 4px);}.ep-cell-st{flex-basis:100%;}
            .ep-div{display:none;}
        }
    </style>
</x-filament-widgets::widget>
