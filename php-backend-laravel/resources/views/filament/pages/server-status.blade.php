<x-filament-panels::page>
    @php
        // Self-contained styling: Filament ships a precompiled CSS bundle that does NOT include
        // arbitrary Tailwind utilities used in custom views, so this page styles itself with a
        // scoped <style> block (.ss-*). Dark mode keys off Filament's class toggle (html.dark).
        // Icons come from App\Support\OpsIcons — Lucide line icons + the official brand marks.

        $all = collect($meters)->concat($services);
        $down = $all->where('status', 'down')->count();
        $warn = $all->where('status', 'warn')->count();

        [$tone, $icon, $head] = $down > 0
            ? ['down', 'circle-x', $down === 1 ? '1 system is down' : "{$down} systems are down"]
            : ($warn > 0
                ? ['warn', 'triangle-alert', $warn === 1 ? '1 thing needs a look' : "{$warn} things need a look"]
                : ['ok', 'circle-check', 'All systems operational']);

        $word = ['ok' => 'Operational', 'warn' => 'Degraded', 'down' => 'Down', 'idle' => 'Not in use'];
        $facts = array_filter([$facts['host'] ?? null, $facts['os'] ?? null, isset($facts['uptime']) ? 'up '.$facts['uptime'] : null]);
    @endphp

    <style>
        .ss{
            --s-card:var(--hrn-surface,#fff);--s-line:var(--hrn-border,#e4e7ec);--s-soft:#f3f4f6;
            --s-ink:var(--hrn-ink,#0b1220);--s-ink2:var(--hrn-ink-2,#344054);--s-ink3:var(--hrn-ink-3,#667085);
            --s-track:#eaecf0;--s-tick:#98a2b3;--s-bar:#344054;
            --s-ok:#12a150;--s-warn:#d97706;--s-down:#dc2626;--s-idle:#98a2b3;
            --s-mono:ui-monospace,SFMono-Regular,"SF Mono",Menlo,Consolas,monospace;
            display:flex;flex-direction:column;gap:20px;color:var(--s-ink);
        }
        html.dark .ss{
            --s-card:var(--hrn-surface,#111827);--s-line:var(--hrn-border,#263041);--s-soft:#1a2232;
            --s-ink:var(--hrn-ink,#f2f4f7);--s-ink2:var(--hrn-ink-2,#cfd4dc);--s-ink3:var(--hrn-ink-3,#8b95a7);
            --s-track:#263041;--s-tick:#5b6577;--s-bar:#cfd4dc;
            --s-ok:#2fc46f;--s-warn:#f2a93b;--s-down:#f26b6b;--s-idle:#5b6577;
        }

        /* Status line — no box, just the verdict and the facts about the box. */
        .ss-top{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:10px 24px;}
        .ss-verdict{display:flex;align-items:center;gap:10px;min-width:0;}
        .ss-verdict svg{flex:none;}
        .ss-verdict.ok svg{color:var(--s-ok);} .ss-verdict.warn svg{color:var(--s-warn);} .ss-verdict.down svg{color:var(--s-down);}
        .ss-verdict h2{margin:0;font-size:17px;font-weight:650;letter-spacing:-.01em;}
        .ss-facts{margin:2px 0 0;font:12px/1.4 var(--s-mono);color:var(--s-ink3);}
        .ss-checked{display:flex;align-items:center;gap:7px;font-size:12px;color:var(--s-ink3);font-variant-numeric:tabular-nums;}
        .ss-checked svg{opacity:.8;}
        .ss-checked .spin{display:none;animation:ss-spin .8s linear infinite;}
        .ss-checked.busy .spin{display:inline-block;} .ss-checked.busy .still{display:none;}
        @keyframes ss-spin{to{transform:rotate(360deg);}}

        .ss-panel{background:var(--s-card);border:1px solid var(--s-line);border-radius:12px;overflow:hidden;}
        .ss-panel-h{display:flex;align-items:baseline;justify-content:space-between;padding:12px 18px;border-bottom:1px solid var(--s-line);}
        .ss-panel-h h3{margin:0;font-size:13px;font-weight:600;color:var(--s-ink2);}
        .ss-panel-h span{font-size:12px;color:var(--s-ink3);}

        /* Host meters: three columns split by hairlines. */
        .ss-meters{display:grid;grid-template-columns:repeat(3,1fr);}
        .ss-m{padding:16px 18px 18px;min-width:0;}
        .ss-m + .ss-m{border-left:1px solid var(--s-line);}
        .ss-m-h{display:flex;align-items:center;gap:7px;font-size:13px;color:var(--s-ink3);}
        .ss-m-h .st{margin-left:auto;font-size:12px;font-weight:600;}
        .ss-m-v{margin:10px 0 0;font-size:30px;font-weight:650;letter-spacing:-.02em;line-height:1;font-variant-numeric:tabular-nums;}
        .ss-m-v small{font-size:15px;font-weight:500;color:var(--s-ink3);margin-left:2px;}
        .ss-bar{position:relative;margin-top:14px;height:6px;border-radius:3px;background:var(--s-track);}
        .ss-bar i{position:absolute;left:0;top:0;bottom:0;border-radius:3px;background:var(--s-bar);transition:width .5s ease;}
        .ss-bar i.warn{background:var(--s-warn);} .ss-bar i.down{background:var(--s-down);}
        .ss-bar b{position:absolute;top:-3px;bottom:-3px;width:1px;background:var(--s-tick);}
        .ss-m-d{margin:10px 0 0;font:12px/1.45 var(--s-mono);color:var(--s-ink3);overflow-wrap:anywhere;}
        @media(max-width:860px){.ss-meters{grid-template-columns:1fr;}.ss-m + .ss-m{border-left:0;border-top:1px solid var(--s-line);}}

        /* Service rows. */
        .ss-row{display:grid;grid-template-columns:32px minmax(0,1fr) auto 128px;align-items:center;gap:14px;padding:12px 18px;}
        .ss-row + .ss-row{border-top:1px solid var(--s-line);}
        .ss-logo{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;background:var(--s-soft);color:var(--s-ink3);}
        .ss-logo.sqlite{color:#0f80cc;} .ss-logo.mysql{color:#4479a1;} .ss-logo.redis{color:#ff4438;}
        .ss-logo.laravel{color:#ff2d20;} .ss-logo.php{color:#777bb4;} .ss-logo.whatsapp{color:#25d366;}
        html.dark .ss-logo.mysql{color:#6a9fd0;} html.dark .ss-logo.php{color:#9a9ed6;}
        .ss-row.idle .ss-logo{filter:grayscale(1);opacity:.55;}
        .ss-name{font-size:14px;font-weight:600;color:var(--s-ink);}
        .ss-det{margin-top:2px;font:12px/1.4 var(--s-mono);color:var(--s-ink3);overflow-wrap:anywhere;}
        .ss-metric{font:13px var(--s-mono);color:var(--s-ink2);white-space:nowrap;text-align:right;}
        .ss-st{display:flex;align-items:center;gap:7px;justify-content:flex-end;font-size:13px;font-weight:500;white-space:nowrap;}
        .ss-st b{width:8px;height:8px;border-radius:50%;flex:none;}
        .ss-st.ok{color:var(--s-ink2);} .ss-st.ok b{background:var(--s-ok);}
        .ss-st.warn{color:var(--s-warn);} .ss-st.warn b{background:var(--s-warn);}
        .ss-st.down{color:var(--s-down);} .ss-st.down b{background:var(--s-down);}
        .ss-st.idle{color:var(--s-ink3);} .ss-st.idle b{background:transparent;box-shadow:inset 0 0 0 1.5px var(--s-idle);}
        .ss-m-h .st.warn{color:var(--s-warn);} .ss-m-h .st.down{color:var(--s-down);} .ss-m-h .st.ok,.ss-m-h .st.idle{display:none;}
        @media(max-width:640px){
            .ss-row{grid-template-columns:32px minmax(0,1fr) auto;row-gap:4px;padding:12px 14px;}
            .ss-metric{grid-column:2;grid-row:2;text-align:left;font-size:12px;}
            .ss-st{grid-column:3;grid-row:1;}
        }
    </style>

    <div class="ss" wire:poll.10s="refresh">
        <div class="ss-top">
            <div class="ss-verdict {{ $tone }}">
                {!! \App\Support\OpsIcons::line($icon, 22) !!}
                <div style="min-width:0">
                    <h2>{{ $head }}</h2>
                    @if ($facts)<p class="ss-facts">{{ implode(' · ', $facts) }}</p>@endif
                </div>
            </div>
            <div class="ss-checked" wire:loading.class="busy">
                <span class="still">{!! \App\Support\OpsIcons::line('clock', 14) !!}</span>
                <span class="spin"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-6.22-8.56"/></svg></span>
                Checked {{ $checkedAt }} IST · every 10 s
            </div>
        </div>

        <section class="ss-panel" aria-label="Host">
            <div class="ss-panel-h"><h3>Host</h3><span>bars mark the warning and critical lines</span></div>
            <div class="ss-meters">
                @foreach ($meters as $m)
                    <div class="ss-m">
                        <div class="ss-m-h">
                            {!! \App\Support\OpsIcons::render($m['icon'], 16) !!}
                            <span>{{ $m['label'] }}</span>
                            <span class="st {{ $m['status'] }}">{{ $word[$m['status']] }}</span>
                        </div>
                        <p class="ss-m-v">{{ $m['value'] }}@if ($m['unit'])<small>{{ $m['unit'] }}</small>@endif</p>
                        @if (! is_null($m['pct']))
                            <div class="ss-bar" role="meter" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $m['pct'] }}" aria-label="{{ $m['label'] }}">
                                <i class="{{ $m['status'] }}" style="width:{{ max(1, min(100, (int) $m['pct'])) }}%"></i>
                                @foreach ($m['ticks'] as $t)<b style="left:{{ $t }}%"></b>@endforeach
                            </div>
                        @endif
                        <p class="ss-m-d">{{ $m['detail'] }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="ss-panel" aria-label="Services">
            <div class="ss-panel-h"><h3>Services</h3><span>{{ count($services) }} checked</span></div>
            @foreach ($services as $s)
                <div class="ss-row {{ $s['status'] }}">
                    <div class="ss-logo {{ $s['brand'] }}">{!! \App\Support\OpsIcons::render($s['icon'], 18) !!}</div>
                    <div style="min-width:0">
                        <div class="ss-name">{{ $s['name'] }}</div>
                        <div class="ss-det">{{ $s['detail'] }}</div>
                    </div>
                    <div class="ss-metric">{{ $s['metric'] ?? '' }}</div>
                    <div class="ss-st {{ $s['status'] }}"><b></b>{{ $s['note'] ?? $word[$s['status']] }}</div>
                </div>
            @endforeach
        </section>
    </div>
</x-filament-panels::page>
