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
    $unit = $t['isEvent'] ? 'booking' : 'booking';
    $alert = $this->getAlert();
    $next = $this->getNextEvent();
@endphp

<x-filament-widgets::widget>
    @if ($alert)
        <a href="{{ $alert['url'] }}" class="pqa-alert pqa-alert-{{ $alert['tone'] }}">
            <span class="pqa-alert-ic">{{ $alert['icon'] }}</span>
            <span class="pqa-alert-tx">{{ $alert['text'] }}</span>
            <span class="pqa-alert-cta">{{ $alert['cta'] }} →</span>
        </a>
    @endif

    <div class="pqa">
        <div class="pqa-hi">
            <div class="pqa-greet">{{ $this->getGreeting() }} 👋</div>
            <div class="pqa-today">
                <span class="pqa-amt">{{ $inr($t['revenue']) }}</span>
                <span class="pqa-today-lab">earned today</span>
            </div>
            <div class="pqa-meta">
                <span class="pqa-chip">{{ $t['count'] }} {{ \Illuminate\Support\Str::plural($unit, $t['count']) }} today</span>
                @if ($t['weekDelta'] !== null)
                    <span class="pqa-chip pqa-mom {{ $t['weekDelta'] < 0 ? 'is-down' : 'is-up' }}">
                        {{ $t['weekDelta'] < 0 ? '▼' : '▲' }} {{ abs($t['weekDelta']) }}% this week
                    </span>
                @endif
                @if ($next)
                    <a href="{{ $next['url'] }}" class="pqa-chip pqa-next" title="{{ $next['title'] }}">
                        📅 <span class="pqa-next-t">{{ $next['title'] }}</span> · {{ $next['when'] }}@if ($next['pct'] !== null) · {{ $next['pct'] }}% sold @endif
                    </a>
                @endif
            </div>
        </div>

        <div class="pqa-actions">
            @foreach ($this->getActions() as $action)
                <a href="{{ $action['url'] }}" @class(['pqa-btn', 'pqa-btn-primary' => $action['primary'] ?? false])>
                    <x-filament::icon :icon="$action['icon']" class="pqa-ic" />
                    <span>{{ $action['label'] }}</span>
                </a>
            @endforeach
        </div>
    </div>

    <style>
        /* Smart alert ribbon above the hero — the one thing that needs the operator
           now (sellout risk / pending settlement). Light-theme bar on the page bg. */
        .pqa-alert{display:flex;align-items:center;gap:10px;text-decoration:none;
            padding:10px 14px;border-radius:12px;margin-bottom:12px;
            font-size:13px;font-weight:600;border:1px solid;line-height:1.3;}
        .pqa-alert-ic{font-size:15px;flex:none;}
        .pqa-alert-tx{flex:1;min-width:0;}
        .pqa-alert-cta{font-weight:800;white-space:nowrap;opacity:.9;}
        .pqa-alert:hover{filter:brightness(.99);}
        .pqa-alert-hot{background:#fff4ed;border-color:#ffd6bd;color:#9a3412;}
        .pqa-alert-info{background:#eef4ff;border-color:#d3e0fb;color:#1e50e6;}

        /* "Next event" chip — the command-bar context inside the hero. */
        .pqa-next{text-decoration:none;max-width:100%;display:inline-flex;align-items:center;gap:4px;}
        .pqa-next:hover{background:rgba(255,255,255,.18);}
        .pqa-next-t{max-width:12rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}

        /* Gradient "hero" band — same blue aurora as the partner sign-in, so the
           console opens with the brand feel the login set up. Always-dark, so it
           reads identically in light and dark theme. */
        .pqa{position:relative;overflow:hidden;isolation:isolate;
            display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;
            border-radius:18px;padding:20px 22px;
            background:
                radial-gradient(900px 380px at 12% -40%, rgba(59,130,246,.55), transparent 60%),
                radial-gradient(700px 340px at 106% 0%, rgba(99,102,241,.45), transparent 60%),
                linear-gradient(150deg,#0a1738 0%,#0b1c46 52%,#0a1230 100%);
            box-shadow:0 18px 40px -22px rgba(10,23,56,.7),0 0 0 1px rgba(255,255,255,.06);}
        .pqa::before{content:"";position:absolute;inset:0;z-index:-1;opacity:.16;
            background-image:linear-gradient(rgba(255,255,255,.5) 1px,transparent 1px),
                linear-gradient(90deg,rgba(255,255,255,.5) 1px,transparent 1px);
            background-size:40px 40px;
            -webkit-mask-image:radial-gradient(120% 100% at 20% 0%,#000 30%,transparent 72%);
            mask-image:radial-gradient(120% 100% at 20% 0%,#000 30%,transparent 72%);}
        .pqa-greet{font-size:16px;font-weight:700;letter-spacing:-.01em;color:rgba(224,232,255,.82);}
        .pqa-today{display:flex;align-items:baseline;gap:8px;margin-top:5px;}
        .pqa-amt{font-size:32px;font-weight:800;letter-spacing:-.03em;color:#fff;line-height:1.02;
            font-variant-numeric:tabular-nums;}
        .pqa-today-lab{font-size:13px;font-weight:600;color:rgba(224,232,255,.66);}
        .pqa-meta{display:flex;align-items:center;gap:8px;margin-top:9px;flex-wrap:wrap;}
        .pqa-chip{font-size:12px;font-weight:600;color:#eaf0ff;
            padding:4px 10px;border-radius:999px;background:rgba(255,255,255,.10);
            box-shadow:inset 0 0 0 1px rgba(255,255,255,.14);font-variant-numeric:tabular-nums;}
        .pqa-mom.is-up{color:#7ff0bd;background:rgba(16,185,129,.16);box-shadow:inset 0 0 0 1px rgba(16,185,129,.3);}
        .pqa-mom.is-down{color:#ffcf9a;background:rgba(245,158,11,.16);box-shadow:inset 0 0 0 1px rgba(245,158,11,.3);}
        .pqa-actions{display:flex;gap:10px;flex-wrap:wrap;}
        .pqa-btn{display:inline-flex;align-items:center;gap:7px;
            font-size:13.5px;font-weight:600;color:#eaf0ff;text-decoration:none;
            padding:10px 15px;border-radius:11px;
            background:rgba(255,255,255,.09);backdrop-filter:blur(6px);
            box-shadow:inset 0 0 0 1px rgba(255,255,255,.16);transition:background .15s,transform .05s;}
        .pqa-btn:hover{background:rgba(255,255,255,.16);}
        .pqa-btn:active{transform:translateY(1px);}
        .pqa-btn-primary{color:#fff;background-image:linear-gradient(180deg,#2f6bff,#1e50e6);
            box-shadow:0 8px 18px -8px rgba(37,99,235,.6);}
        .pqa-btn-primary:hover{background-image:linear-gradient(180deg,#3a74ff,#2456ea);}
        .pqa-ic{width:18px;height:18px;}

        @media (max-width:640px){
            .pqa{padding:16px;border-radius:16px;}
            .pqa-actions{width:100%;}
            .pqa-btn{flex:1 1 auto;justify-content:center;}
        }
        @media (prefers-reduced-motion:reduce){.pqa::before{opacity:.12;}}
    </style>
</x-filament-widgets::widget>
