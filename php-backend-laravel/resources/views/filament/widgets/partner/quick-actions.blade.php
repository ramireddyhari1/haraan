{{-- Partner home action bar: greeting + a row of lane-aware quick-launch buttons.
     Inline styles (theme-agnostic, dark-aware) keep it self-contained like the
     other bespoke summary strips in this panel. --}}
@php
    $t = $this->getToday();
    // Indian grouping: ₹18,42,900
    $inr = function (float $n): string {
        $n = round($n); $sign = $n < 0 ? '-' : ''; $n = abs($n); $str = (string) $n;
        if (strlen($str) <= 3) return $sign . '₹' . $str;
        $last3 = substr($str, -3); $rest = substr($str, 0, -3);
        $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
        return $sign . '₹' . $rest . ',' . $last3;
    };
    $alert = $this->getAlert();
    $next = $this->getNextEvent();
@endphp

<x-filament-widgets::widget>
    @if ($alert)
        <a href="{{ $alert['url'] }}" wire:navigate class="pqa-alert pqa-alert-{{ $alert['tone'] }}">
            <span class="pqa-alert-ic"><x-filament::icon :icon="$alert['icon']" /></span>
            <span class="pqa-alert-tx">{{ $alert['text'] }}</span>
            <span class="pqa-alert-cta">{{ $alert['cta'] }}</span>
        </a>
    @endif

    {{-- The hero. A light card, not a dark slab: the date, who it's for, the one
         figure that matters today, a sentence of the other true numbers, and the
         partner's own place drawn on the right under the real sky. --}}
    <section class="pqh">
        <div class="pqh-copy">
            <div class="pqh-date">{{ \App\Support\BusinessClock::now()->format('l, j F') }}</div>
            <h2 class="pqh-greet">{{ $this->getGreeting() }}</h2>

            <div class="pqh-money">
                <span class="pqh-amt" data-count-to="{{ (int) round($t['revenue']) }}" data-count-prefix="₹">{{ $inr($t['revenue']) }}</span>
                <span class="pqh-amt-lab">{{ $t['isEvent'] ? 'sold today' : 'collected today' }}</span>
            </div>

            @php
                $facts = $this->getFacts();
                array_unshift($facts, $t['isEvent']
                    ? trans_choice('{0} No tickets yet today|{1} 1 ticket today|[2,*] :n tickets today', $t['count'], ['n' => number_format($t['count'])])
                    : trans_choice("{0} Nothing on today's sheet yet|{1} 1 booking on today's sheet|[2,*] :n bookings on today's sheet", $t['count'], ['n' => number_format($t['count'])]));
            @endphp
            <p class="pqh-facts">
                @if ($t['delta'] !== null)
                    <span class="pqh-delta {{ $t['delta'] < 0 ? 'is-down' : 'is-up' }}">
                        <svg viewBox="0 0 12 12" aria-hidden="true"><path d="{{ $t['delta'] < 0 ? 'M6 9.5 2 4.5h8z' : 'M6 2.5l4 5H2z' }}"/></svg>
                        {{ abs($t['delta']) }}% {{ $t['deltaLabel'] }}
                    </span>
                @endif
                {{ implode(' · ', $facts) }}
            </p>

            <div class="pqh-actions">
                @foreach ($this->getActions() as $action)
                    <a href="{{ $action['url'] }}" wire:navigate @class(['pqh-btn', 'is-primary' => $action['primary'] ?? false]) data-haptic>
                        <x-filament::icon :icon="$action['icon']" class="pqh-btn-ic" />
                        <span>{{ $action['label'] }}</span>
                    </a>
                @endforeach
            </div>
        </div>

        @php $scene = $this->getScene(); @endphp
        <div class="pqh-art" aria-hidden="true">
            <x-partner.scene :kind="$scene['kind']" :phase="$scene['phase']" />
        </div>
    </section>

    {{-- Next-event spotlight: poster + countdown + sell-through + one-tap check-in. --}}
    @if ($next)
        <div class="pns">
            @if ($next['poster'])
                <img src="{{ $next['poster'] }}" alt="" class="pns-poster">
            @else
                <div class="pns-poster pns-poster-ph"><x-filament::icon icon="heroicon-o-ticket" /></div>
            @endif
            <div class="pns-body">
                <div class="pns-kicker">Next event · {{ $next['when'] }}</div>
                <a href="{{ $next['url'] }}" class="pns-title" title="{{ $next['title'] }}">{{ $next['title'] }}</a>
                <div class="pns-meta">
                    @if ($next['date'])<span>{{ $next['date'] }}</span>@endif
                    @if ($next['total'] > 0)<span>· {{ number_format($next['sold']) }}/{{ number_format($next['total']) }} sold</span>@endif
                    @if ($next['pct'] !== null)<span class="pns-pct">· {{ $next['pct'] }}%</span>@endif
                </div>
                @if ($next['pct'] !== null)
                    <div class="pns-bar"><span style="width:{{ max(3, $next['pct']) }}%"></span></div>
                @endif
            </div>
            @if ($next['checkInUrl'])
                <a href="{{ $next['checkInUrl'] }}" class="pns-cta">
                    <x-filament::icon icon="heroicon-o-qr-code" class="pns-cta-ic" />
                    <span>Check-in</span>
                </a>
            @endif
        </div>
    @endif

    <style>
        /* Smart alert ribbon above the hero — the one thing that needs the operator
           now (sellout risk / pending settlement). Light-theme bar on the page bg. */
        .pqa-alert{display:flex;align-items:center;gap:11px;text-decoration:none;
            padding:10px 12px;border-radius:13px;margin-bottom:12px;
            font-size:13px;font-weight:600;border:1px solid;line-height:1.3;}
        .pqa-alert-ic{width:30px;height:30px;border-radius:9px;flex:none;
            display:flex;align-items:center;justify-content:center;}
        .pqa-alert-ic svg{width:17px;height:17px;stroke-width:1.9;}
        .pqa-alert-tx{flex:1;min-width:0;}
        .pqa-alert-cta{font-weight:800;white-space:nowrap;opacity:.9;}
        .pqa-alert:hover{filter:brightness(.99);}
        .pqa-alert-hot{background:#fff4ed;border-color:#ffd6bd;color:#9a3412;}
        .pqa-alert-hot .pqa-alert-ic{background:#ffe0cc;color:#c2410c;}
        .pqa-alert-info{background:#eef4ff;border-color:#d3e0fb;color:#1e50e6;}
        .pqa-alert-info .pqa-alert-ic{background:#dbe7fd;color:#1e50e6;}

        /* Next-event spotlight card. */
        .pns{display:flex;align-items:center;gap:14px;margin-top:12px;background:#fff;
            border:1px solid #e7e9ee;border-radius:15px;padding:12px 14px;
            box-shadow:0 1px 2px rgba(11,18,32,.05);}
        .pns-poster{width:52px;height:66px;border-radius:10px;object-fit:cover;flex:none;
            background:#eef2f8;box-shadow:0 1px 2px rgba(0,0,0,.08);}
        .pns-poster-ph{display:flex;align-items:center;justify-content:center;color:#9aa2b1;}
        .pns-poster-ph svg{width:24px;height:24px;}
        .pns-body{flex:1;min-width:0;}
        .pns-kicker{font-size:10.5px;font-weight:800;letter-spacing:.08em;color:#2f6bff;
            text-transform:uppercase;}
        .pns-title{display:block;font-size:15px;font-weight:800;color:#0b1220;text-decoration:none;
            letter-spacing:-.01em;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
        .pns-title:hover{color:#1e50e6;}
        .pns-meta{font-size:12px;color:#7a8394;margin-top:3px;display:flex;gap:5px;flex-wrap:wrap;
            font-variant-numeric:tabular-nums;}
        .pns-pct{font-weight:700;color:#1e50e6;}
        .pns-bar{margin-top:8px;height:6px;border-radius:6px;background:#eef1f6;overflow:hidden;max-width:280px;}
        .pns-bar span{display:block;height:100%;border-radius:6px;background:linear-gradient(90deg,#2f6bff,#1e50e6);}
        .pns-cta{flex:none;display:inline-flex;align-items:center;gap:6px;text-decoration:none;
            font-size:13px;font-weight:700;color:#fff;background:linear-gradient(180deg,#2f6bff,#1e50e6);
            padding:9px 15px;border-radius:11px;box-shadow:0 8px 18px -8px rgba(37,99,235,.6);
            white-space:nowrap;transition:filter .15s;}
        .pns-cta:hover{filter:brightness(1.06);}
        .pns-cta-ic{width:16px;height:16px;}
        @media (max-width:640px){
            .pns{flex-wrap:wrap;}
            .pns-cta{width:100%;justify-content:center;}
            .pns-bar{max-width:none;}
        }

    </style>
</x-filament-widgets::widget>
