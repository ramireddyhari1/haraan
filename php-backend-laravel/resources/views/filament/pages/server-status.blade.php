<x-filament-panels::page>
    @php
        // Self-contained styling: Filament ships a precompiled CSS bundle that does NOT include
        // arbitrary Tailwind utilities used in custom views, so this page styles itself with a
        // scoped <style> block (.hss-*) instead of utility classes. Dark mode keys off Filament's
        // class-based toggle (html.dark), not prefers-color-scheme.
        $counts  = collect($cards)->countBy('status');
        $down = $counts['down'] ?? 0; $warn = $counts['warn'] ?? 0; $ok = $counts['ok'] ?? 0;
        $anyDown = $down > 0; $anyWarn = $warn > 0;

        $hero = $anyDown
            ? ['grad' => 'linear-gradient(135deg,#ef4444,#e11d48)', 'icon' => 'heroicon-o-exclamation-triangle', 'head' => 'Attention needed', 'sub' => $down.' system'.($down===1?'':'s').' down'.($anyWarn?' · '.$warn.' to watch':'')]
            : ($anyWarn
                ? ['grad' => 'linear-gradient(135deg,#f59e0b,#ea580c)', 'icon' => 'heroicon-o-exclamation-circle', 'head' => 'All up — '.$warn.' to watch', 'sub' => 'No outages. Some metrics are elevated.']
                : ['grad' => 'linear-gradient(135deg,#10b981,#0d9488)', 'icon' => 'heroicon-o-check-circle', 'head' => 'All systems operational', 'sub' => 'Every probe is green.']);

        $sections = [
            'data'     => ['label' => 'Data & cache',   'icon' => 'heroicon-m-circle-stack'],
            'host'     => ['label' => 'Host resources', 'icon' => 'heroicon-m-cpu-chip'],
            'services' => ['label' => 'Services',       'icon' => 'heroicon-m-squares-2x2'],
        ];
        $grouped = collect($cards)->groupBy('group');
        $chipLabel = ['ok' => 'Healthy', 'warn' => 'Watch', 'down' => 'Down', 'idle' => 'Inactive'];
    @endphp

    <style>
        .hss{--card:#fff;--border:#e8ecf3;--ink:#0b1220;--ink2:#5a6579;--ink3:#8a94a6;--track:#eef1f6;
             --ok:#059669;--ok-d:#10b981;--ok-bg:#ecfdf5;--warn:#d97706;--warn-d:#f59e0b;--warn-bg:#fffbeb;
             --down:#dc2626;--down-d:#ef4444;--down-bg:#fef2f2;--idle:#9aa4b2;--idle-d:#cbd2dd;--idle-bg:#f1f3f7;
             display:flex;flex-direction:column;gap:28px;}
        .dark .hss{--card:#111726;--border:rgba(255,255,255,.08);--ink:#f3f5f9;--ink2:#aeb7c6;--ink3:#7b8698;--track:rgba(255,255,255,.09);
             --ok-bg:rgba(16,185,129,.13);--warn-bg:rgba(245,158,11,.13);--down-bg:rgba(239,68,68,.13);--idle-bg:rgba(255,255,255,.05);}

        .hss-hero{position:relative;overflow:hidden;border-radius:18px;padding:22px 24px;color:#fff;box-shadow:0 10px 30px -14px rgba(2,6,23,.45);}
        .hss-hero-row{position:relative;z-index:2;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:16px;}
        .hss-hero-lead{display:flex;align-items:center;gap:16px;}
        .hss-badge{width:48px;height:48px;border-radius:13px;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,.2);box-shadow:inset 0 0 0 1px rgba(255,255,255,.3);}
        .hss-badge svg{width:28px;height:28px;}
        .hss-h1{margin:0;font-size:19px;font-weight:800;letter-spacing:-.01em;line-height:1.2;}
        .hss-hsub{margin:2px 0 0;font-size:13px;color:rgba(255,255,255,.85);}
        .hss-tally{display:flex;gap:8px;flex-wrap:wrap;}
        .hss-pill{display:inline-flex;align-items:center;gap:7px;background:rgba(255,255,255,.16);box-shadow:inset 0 0 0 1px rgba(255,255,255,.22);border-radius:999px;padding:5px 12px;font-size:13px;font-weight:700;}
        .hss-pill b{width:8px;height:8px;border-radius:50%;background:#fff;display:inline-block;}
        .hss-pill.soft b{background:rgba(255,255,255,.7);}
        .hss-live{position:relative;z-index:2;margin-top:16px;display:flex;align-items:center;gap:8px;font-size:12px;color:rgba(255,255,255,.78);}
        .hss-ping{position:relative;width:8px;height:8px;display:inline-block;}
        .hss-ping i{position:absolute;inset:0;border-radius:50%;background:#fff;}
        .hss-ping i.a{animation:hssping 1.4s cubic-bezier(0,0,.2,1) infinite;opacity:.75;}
        @keyframes hssping{75%,100%{transform:scale(2.3);opacity:0;}}
        .hss-wash{position:absolute;right:-30px;top:-44px;width:180px;height:180px;border-radius:50%;background:rgba(255,255,255,.12);filter:blur(32px);}

        .hss-sec-h{display:flex;align-items:center;gap:8px;margin:0 0 12px 2px;}
        .hss-sec-h svg{width:16px;height:16px;color:var(--ink3);}
        .hss-sec-h h2{margin:0;font-size:11px;font-weight:700;letter-spacing:.09em;text-transform:uppercase;color:var(--ink2);}
        .hss-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;}
        @media(max-width:1100px){.hss-grid{grid-template-columns:repeat(2,1fr);}}
        @media(max-width:640px){.hss-grid{grid-template-columns:1fr;}}

        .hss-card{position:relative;overflow:hidden;background:var(--card);border:1px solid var(--border);border-radius:16px;padding:18px 18px 16px;box-shadow:0 1px 2px rgba(11,18,32,.04);transition:transform .18s,box-shadow .18s;}
        .hss-card:hover{transform:translateY(-2px);box-shadow:0 14px 26px -16px rgba(11,18,32,.28);}
        .hss-card::before{content:"";position:absolute;left:0;top:0;bottom:0;width:4px;}
        .hss-card.ok::before{background:var(--ok-d);} .hss-card.warn::before{background:var(--warn-d);}
        .hss-card.down::before{background:var(--down-d);} .hss-card.idle::before{background:var(--idle-d);}
        .hss-top{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;}
        .hss-lft{display:flex;align-items:center;gap:12px;}
        .hss-tile{width:40px;height:40px;border-radius:12px;display:flex;align-items:center;justify-content:center;flex:none;}
        .hss-tile svg{width:20px;height:20px;}
        .hss-tile.ok{background:var(--ok-bg);color:var(--ok);} .hss-tile.warn{background:var(--warn-bg);color:var(--warn);}
        .hss-tile.down{background:var(--down-bg);color:var(--down);} .hss-tile.idle{background:var(--idle-bg);color:var(--idle);}
        .hss-title{font-size:14px;font-weight:600;color:var(--ink2);}
        .hss-chip{display:inline-flex;align-items:center;gap:6px;border-radius:999px;padding:4px 10px;font-size:12px;font-weight:700;background:var(--idle-bg);box-shadow:inset 0 0 0 1px var(--border);white-space:nowrap;}
        .hss-chip b{width:6px;height:6px;border-radius:50%;display:inline-block;}
        .hss-chip.ok{color:var(--ok);} .hss-chip.ok b{background:var(--ok-d);}
        .hss-chip.warn{color:var(--warn);} .hss-chip.warn b{background:var(--warn-d);}
        .hss-chip.down{color:var(--down);} .hss-chip.down b{background:var(--down-d);}
        .hss-chip.idle{color:var(--idle);} .hss-chip.idle b{background:var(--idle-d);}
        .hss-val{margin:15px 0 0;font-size:23px;font-weight:800;letter-spacing:-.02em;color:var(--ink);line-height:1.15;}
        .hss-meter{margin-top:11px;height:6px;border-radius:999px;background:var(--track);overflow:hidden;}
        .hss-meter i{display:block;height:100%;border-radius:999px;transition:width .5s ease;}
        .hss-meter i.ok{background:var(--ok-d);} .hss-meter i.warn{background:var(--warn-d);} .hss-meter i.down{background:var(--down-d);} .hss-meter i.idle{background:var(--idle-d);}
        .hss-subtext{margin:9px 0 0;font-size:12px;line-height:1.5;color:var(--ink3);}
    </style>

    <div class="hss" wire:poll.10s="refresh">
        {{-- Hero --}}
        <div class="hss-hero" style="background:{{ $hero['grad'] }}">
            <div class="hss-hero-row">
                <div class="hss-hero-lead">
                    <div class="hss-badge"><x-filament::icon :icon="$hero['icon']" /></div>
                    <div>
                        <p class="hss-h1">{{ $hero['head'] }}</p>
                        <p class="hss-hsub">{{ $hero['sub'] }}</p>
                    </div>
                </div>
                <div class="hss-tally">
                    <span class="hss-pill"><b></b>{{ $ok }} healthy</span>
                    @if ($anyWarn)<span class="hss-pill soft"><b></b>{{ $warn }} watch</span>@endif
                    @if ($anyDown)<span class="hss-pill"><b></b>{{ $down }} down</span>@endif
                </div>
            </div>
            <div class="hss-live"><span class="hss-ping"><i class="a"></i><i></i></span>Live · auto-refresh every 10s · last checked {{ $checkedAt }}</div>
            <div class="hss-wash"></div>
        </div>

        {{-- Grouped cards --}}
        @foreach ($sections as $key => $section)
            @if ($grouped->has($key))
                <section>
                    <div class="hss-sec-h"><x-filament::icon :icon="$section['icon']" /><h2>{{ $section['label'] }}</h2></div>
                    <div class="hss-grid">
                        @foreach ($grouped[$key] as $c)
                            @php $st = $c['status']; @endphp
                            <div class="hss-card {{ $st }}">
                                <div class="hss-top">
                                    <div class="hss-lft">
                                        <div class="hss-tile {{ $st }}"><x-filament::icon :icon="$c['icon']" /></div>
                                        <span class="hss-title">{{ $c['title'] }}</span>
                                    </div>
                                    <span class="hss-chip {{ $st }}"><b></b>{{ $chipLabel[$st] ?? 'n/a' }}</span>
                                </div>
                                <p class="hss-val">{{ $c['value'] }}</p>
                                @if (! is_null($c['meter'] ?? null))
                                    <div class="hss-meter"><i class="{{ $st }}" style="width:{{ max(2, min(100, (int) $c['meter'])) }}%"></i></div>
                                @endif
                                <p class="hss-subtext">{{ $c['sub'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
        @endforeach
    </div>
</x-filament-panels::page>
