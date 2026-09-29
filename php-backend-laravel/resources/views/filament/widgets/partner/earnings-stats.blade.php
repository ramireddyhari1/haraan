{{-- Settlement card: what your bookings have taken, and how much of it has
     reached you. One figure, one two-part bar (paid out · still with Haraan),
     two labelled numbers. White card, hairlines — the money is the colour. --}}
@php $m = $this->getSummary(); @endphp

<x-filament-widgets::widget>
    <section class="pes">
        <header class="pes-head">
            <div>
                <div class="pes-lab">Collected on your bookings</div>
                <div class="pes-val">{{ $m['collected'] }}</div>
            </div>
            <div class="pes-month">
                <span class="pes-month-val">{{ $m['collectedMonth'] }}</span>
                <span class="pes-month-lab">this month</span>
            </div>
        </header>

        <div class="pes-bar" role="img"
             aria-label="{{ $m['pct'] }}% of collected money settled to you">
            <span class="pes-bar-paid" style="width:{{ $m['pct'] }}%"></span>
        </div>

        <dl class="pes-split">
            <div class="pes-cell">
                <dt><i class="pes-dot is-paid"></i>Settled to you</dt>
                <dd>{{ $m['settled'] }}</dd>
                <p>{{ $m['pct'] }}% paid out so far</p>
            </div>
            <div class="pes-cell">
                <dt><i class="pes-dot {{ $m['hasPending'] ? 'is-wait' : 'is-paid' }}"></i>{{ $m['hasPending'] ? 'Waiting to settle' : 'Pending' }}</dt>
                <dd>{{ $m['pending'] }}</dd>
                <p>{{ $m['hasPending'] ? 'Held by Haraan until the next payout' : 'All settled up' }}</p>
            </div>
        </dl>
    </section>

    <style>
        .pes{background:#fff;border:1px solid #e6e9f0;border-radius:16px;padding:18px 20px 16px;
            box-shadow:0 1px 2px rgba(15,23,42,.04);}
        .pes-head{display:flex;align-items:flex-end;justify-content:space-between;gap:16px;flex-wrap:wrap;}
        .pes-lab{font-size:11px;font-weight:700;letter-spacing:.09em;text-transform:uppercase;color:#64748b;}
        .pes-val{font-size:30px;font-weight:760;letter-spacing:-.035em;color:#0f172a;margin-top:4px;
            font-variant-numeric:tabular-nums;line-height:1.05;}
        .pes-month{display:flex;align-items:baseline;gap:6px;font-variant-numeric:tabular-nums;}
        .pes-month-val{font-size:15px;font-weight:700;color:#0f172a;}
        .pes-month-lab{font-size:12.5px;color:#64748b;}

        /* The bar: green is what reached the partner, the rest is the soft amber
           of money still in transit. It grows from zero once, on first paint. */
        .pes-bar{position:relative;height:10px;border-radius:99px;margin-top:16px;overflow:hidden;
            background:repeating-linear-gradient(135deg,#fdecc8 0 6px,#fbe3b2 6px 12px);}
        .pes-bar-paid{position:absolute;inset:0 auto 0 0;border-radius:99px;background:#0f9d63;
            transform-origin:left;animation:pes-grow .9s cubic-bezier(.2,.8,.2,1) .2s both;}
        @keyframes pes-grow{from{transform:scaleX(0)}}

        .pes-split{display:grid;grid-template-columns:1fr 1fr;margin:14px 0 0;}
        .pes-cell{padding:2px 0 0;}
        .pes-cell + .pes-cell{padding-left:18px;border-left:1px solid #eef1f6;}
        .pes-cell dt{display:flex;align-items:center;gap:7px;font-size:12.5px;font-weight:600;color:#475569;}
        .pes-cell dd{margin:4px 0 0;font-size:20px;font-weight:740;letter-spacing:-.02em;color:#0f172a;
            font-variant-numeric:tabular-nums;}
        .pes-cell p{margin:2px 0 0;font-size:12px;color:#94a3b8;}
        .pes-dot{width:8px;height:8px;border-radius:50%;flex:none;}
        .pes-dot.is-paid{background:#0f9d63;}
        .pes-dot.is-wait{background:#e8a33d;}
        @media (max-width:520px){
            .pes{padding:16px;}
            .pes-split{grid-template-columns:1fr;gap:12px;}
            .pes-cell + .pes-cell{padding:12px 0 0;border-left:0;border-top:1px solid #eef1f6;}
        }
        @media (prefers-reduced-motion:reduce){.pes-bar-paid{animation:none;}}
    </style>
</x-filament-widgets::widget>
