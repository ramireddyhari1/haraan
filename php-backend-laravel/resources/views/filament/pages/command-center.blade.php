<x-filament-panels::page>
    @php
        $palette = [
            'ok'   => ['text' => 'var(--cc-ok)',   'dot' => 'var(--cc-ok-d)',   'tile' => 'var(--cc-ok-bg)',   'bar' => 'var(--cc-ok-d)'],
            'warn' => ['text' => 'var(--cc-warn)', 'dot' => 'var(--cc-warn-d)', 'tile' => 'var(--cc-warn-bg)', 'bar' => 'var(--cc-warn-d)'],
            'down' => ['text' => 'var(--cc-down)', 'dot' => 'var(--cc-down-d)', 'tile' => 'var(--cc-down-bg)', 'bar' => 'var(--cc-down-d)'],
            'idle' => ['text' => 'var(--cc-idle)', 'dot' => 'var(--cc-idle-d)', 'tile' => 'var(--cc-idle-bg)', 'bar' => 'var(--cc-idle-d)'],
        ];
        $hero = $money['hero'] ?? ['gmv' => 0, 'net' => 0, 'trend' => ['—','flat'], 'paidCount' => 0, 'rangeLabel' => ''];
        $ranges = ['7d' => '7d', '30d' => '30d', '90d' => '90d', 'all' => 'All'];
    @endphp

    <style>
        .cc{--card:#fff;--border:#e8ecf3;--ink:#0b1220;--ink2:#5a6579;--ink3:#8a94a6;--track:#eef1f6;
            --cc-ok:#059669;--cc-ok-d:#10b981;--cc-ok-bg:#ecfdf5;--cc-warn:#d97706;--cc-warn-d:#f59e0b;--cc-warn-bg:#fffbeb;
            --cc-down:#dc2626;--cc-down-d:#ef4444;--cc-down-bg:#fef2f2;--cc-idle:#9aa4b2;--cc-idle-d:#cbd2dd;--cc-idle-bg:#f1f3f7;
            display:flex;flex-direction:column;gap:26px;}
        .dark .cc{--card:#111726;--border:rgba(255,255,255,.08);--ink:#f3f5f9;--ink2:#aeb7c6;--ink3:#7b8698;--track:rgba(255,255,255,.09);
            --cc-ok-bg:rgba(16,185,129,.13);--cc-warn-bg:rgba(245,158,11,.13);--cc-down-bg:rgba(239,68,68,.13);--cc-idle-bg:rgba(255,255,255,.05);}

        /* Hero */
        .cc-hero{position:relative;overflow:hidden;border-radius:20px;padding:26px 28px;color:#fff;
            background:linear-gradient(130deg,#2563eb 0%,#12b76a 100%);box-shadow:0 16px 40px -18px rgba(37,99,235,.55);}
        .cc-hero-top{position:relative;z-index:2;display:flex;flex-wrap:wrap;align-items:flex-start;justify-content:space-between;gap:16px;}
        .cc-eyebrow{font-size:12px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.8);}
        .cc-gmv{margin:6px 0 0;font-size:40px;font-weight:800;letter-spacing:-.02em;line-height:1;}
        .cc-heroline{margin-top:10px;display:flex;flex-wrap:wrap;align-items:center;gap:14px;font-size:13px;color:rgba(255,255,255,.9);}
        .cc-chip{display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,.16);box-shadow:inset 0 0 0 1px rgba(255,255,255,.22);border-radius:999px;padding:4px 11px;font-weight:700;}
        .cc-ranges{display:flex;gap:4px;background:rgba(255,255,255,.14);padding:4px;border-radius:12px;box-shadow:inset 0 0 0 1px rgba(255,255,255,.18);}
        .cc-range{border:0;background:transparent;color:rgba(255,255,255,.85);font-size:12.5px;font-weight:700;padding:6px 12px;border-radius:9px;cursor:pointer;transition:.15s;}
        .cc-range:hover{color:#fff;}
        .cc-range.on{background:#fff;color:#12603a;}
        .cc-live{position:relative;z-index:2;margin-top:18px;display:flex;align-items:center;gap:8px;font-size:12px;color:rgba(255,255,255,.72);}
        .cc-ping{position:relative;width:8px;height:8px;display:inline-block;}
        .cc-ping i{position:absolute;inset:0;border-radius:50%;background:#fff;}
        .cc-ping i.a{animation:ccping 1.5s cubic-bezier(0,0,.2,1) infinite;opacity:.75;}
        @keyframes ccping{75%,100%{transform:scale(2.4);opacity:0;}}
        .cc-wash{position:absolute;right:-40px;top:-60px;width:230px;height:230px;border-radius:50%;background:rgba(255,255,255,.14);filter:blur(40px);}

        .cc-sec-h{display:flex;align-items:center;gap:8px;margin:0 0 12px 2px;}
        .cc-sec-h h2{margin:0;font-size:11px;font-weight:700;letter-spacing:.09em;text-transform:uppercase;color:var(--ink2);}
        .cc-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:15px;}
        @media(max-width:1100px){.cc-grid{grid-template-columns:repeat(2,1fr);}}
        @media(max-width:600px){.cc-grid{grid-template-columns:1fr;}}

        .cc-card{position:relative;overflow:hidden;background:var(--card);border:1px solid var(--border);border-radius:16px;padding:17px;box-shadow:0 1px 2px rgba(11,18,32,.04);transition:transform .18s,box-shadow .18s;}
        .cc-card:hover{transform:translateY(-2px);box-shadow:0 14px 26px -16px rgba(11,18,32,.26);}
        .cc-card::before{content:"";position:absolute;left:0;top:0;bottom:0;width:4px;background:var(--_bar);}
        .cc-top{display:flex;align-items:center;justify-content:space-between;gap:10px;}
        .cc-tl{display:flex;align-items:center;gap:11px;}
        .cc-tile{width:38px;height:38px;border-radius:11px;display:flex;align-items:center;justify-content:center;background:var(--_tile);color:var(--_text);flex:none;}
        .cc-tile svg{width:19px;height:19px;}
        .cc-title{font-size:13.5px;font-weight:600;color:var(--ink2);}
        .cc-val{margin:14px 0 0;font-size:22px;font-weight:800;letter-spacing:-.02em;color:var(--ink);line-height:1.1;}
        .cc-meter{margin-top:10px;height:6px;border-radius:999px;background:var(--track);overflow:hidden;}
        .cc-meter i{display:block;height:100%;border-radius:999px;background:var(--_bar);transition:width .5s ease;}
        .cc-sub{margin:8px 0 0;font-size:12px;line-height:1.5;color:var(--ink3);}

        /* Radar rows */
        .cc-radar{display:flex;flex-direction:column;gap:10px;}
        .cc-row{display:flex;align-items:center;gap:14px;background:var(--card);border:1px solid var(--border);border-radius:14px;padding:14px 16px;text-decoration:none;transition:transform .15s,box-shadow .15s,border-color .15s;}
        .cc-row:hover{transform:translateX(2px);box-shadow:0 10px 22px -16px rgba(11,18,32,.3);border-color:var(--_dot);}
        .cc-row .cc-tile{width:40px;height:40px;}
        .cc-row-main{flex:1;min-width:0;}
        .cc-row-t{font-size:14px;font-weight:700;color:var(--ink);}
        .cc-row-s{font-size:12px;color:var(--ink3);margin-top:2px;}
        .cc-count{font-size:22px;font-weight:800;color:var(--_text);min-width:34px;text-align:right;letter-spacing:-.02em;}
        .cc-go{color:var(--ink3);flex:none;}
        .cc-go svg{width:18px;height:18px;}
        .cc-badgeclear{font-size:12px;font-weight:700;color:var(--cc-ok);}
    </style>

    <div class="cc" wire:poll.30s="build">
        {{-- ── Hero: GMV ─────────────────────────────────────────────── --}}
        <div class="cc-hero">
            <div class="cc-hero-top">
                <div>
                    <div class="cc-eyebrow">Gross bookings · {{ $hero['rangeLabel'] }}</div>
                    <div class="cc-gmv">₹{{ number_format($hero['gmv']) }}</div>
                    <div class="cc-heroline">
                        @php $t = $hero['trend']; @endphp
                        <span class="cc-chip">
                            @if($t[1]==='ok')▲@elseif($t[1]==='down')▼@else—@endif {{ $t[0] }} vs prev
                        </span>
                        <span>Net ₹{{ number_format($hero['net']) }}</span>
                        <span>· {{ number_format($hero['paidCount']) }} paid bookings</span>
                    </div>
                </div>
                <div class="cc-ranges">
                    @foreach($ranges as $key => $label)
                        <button type="button" wire:click="setRange('{{ $key }}')" class="cc-range {{ $range === $key ? 'on' : '' }}">{{ $label }}</button>
                    @endforeach
                </div>
            </div>
            <div class="cc-live"><span class="cc-ping"><i class="a"></i><i></i></span>Live · updates on new bookings &amp; content changes</div>
            <div class="cc-wash"></div>
        </div>

        {{-- ── Money ─────────────────────────────────────────────────── --}}
        <section>
            <div class="cc-sec-h"><h2>Money</h2></div>
            <div class="cc-grid">
                @foreach(($money['cards'] ?? []) as $c)
                    @php $s = $palette[$c['status']] ?? $palette['idle']; @endphp
                    <div class="cc-card" style="--_bar:{{ $s['bar'] }};--_tile:{{ $s['tile'] }};--_text:{{ $s['text'] }};">
                        <div class="cc-top"><div class="cc-tl">
                            <div class="cc-tile"><x-filament::icon :icon="$c['icon']" /></div>
                            <span class="cc-title">{{ $c['title'] }}</span>
                        </div></div>
                        <p class="cc-val">{{ $c['value'] }}</p>
                        <p class="cc-sub">{{ $c['sub'] }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- ── Payments health ──────────────────────────────────────── --}}
        <section>
            <div class="cc-sec-h"><h2>Payments health</h2></div>
            <div class="cc-grid">
                @foreach($health as $c)
                    @php $s = $palette[$c['status']] ?? $palette['idle']; @endphp
                    <div class="cc-card" style="--_bar:{{ $s['bar'] }};--_tile:{{ $s['tile'] }};--_text:{{ $s['text'] }};">
                        <div class="cc-top"><div class="cc-tl">
                            <div class="cc-tile"><x-filament::icon :icon="$c['icon']" /></div>
                            <span class="cc-title">{{ $c['title'] }}</span>
                        </div></div>
                        <p class="cc-val">{{ $c['value'] }}</p>
                        @if(!is_null($c['meter'] ?? null))
                            <div class="cc-meter"><i style="width:{{ max(2, min(100, (int) $c['meter'])) }}%"></i></div>
                        @endif
                        <p class="cc-sub">{{ $c['sub'] }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- ── Operational radar ────────────────────────────────────── --}}
        <section>
            <div class="cc-sec-h"><h2>Needs attention</h2></div>
            <div class="cc-radar">
                @foreach($radar as $item)
                    @php $s = $palette[$item['status']] ?? $palette['idle']; @endphp
                    <a href="{{ $item['url'] }}" class="cc-row" style="--_dot:{{ $s['dot'] }};--_tile:{{ $s['tile'] }};--_text:{{ $s['text'] }};">
                        <div class="cc-tile"><x-filament::icon :icon="$item['icon']" /></div>
                        <div class="cc-row-main">
                            <div class="cc-row-t">{{ $item['title'] }}</div>
                            <div class="cc-row-s">{{ $item['sub'] }}</div>
                        </div>
                        @if($item['count'] > 0)
                            <div class="cc-count">{{ $item['count'] }}</div>
                        @else
                            <div class="cc-badgeclear">✓ clear</div>
                        @endif
                        <span class="cc-go"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02Z" clip-rule="evenodd"/></svg></span>
                    </a>
                @endforeach
            </div>
        </section>
    </div>
</x-filament-panels::page>
