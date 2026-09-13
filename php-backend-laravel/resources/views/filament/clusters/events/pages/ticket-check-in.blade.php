<x-filament-panels::page>
    {{-- Purpose-built enterprise gate-scanning console.
         Preserves all backend contracts:
         - Livewire wire:model="manualCode" & wire:submit="submitManual"
         - IDs #qr-reader, #qr-start, #qr-stop, #tck-stage, #tck-live, #tck-flash, #tck-banner
         - Livewire dispatch 'scan-feedback' & $wire.scan(payload)
         - Full offline/HTTP manual fallback and high-contrast outdoor gate mode. --}}
    <style>
        .tck{--tck-accent:var(--primary-500, #10b981);--tck-ok:#10b981;--tck-warn:#f59e0b;--tck-down:#ef4444;max-width:1200px;margin:0 auto;font-family:inherit;}
        .dark .tck{--tck-accent:#34d399;}

        /* Outdoor / High-Contrast Mode */
        .tck.outdoor{
            --hrn-surface:#ffffff!important;
            --hrn-border:#000000!important;
            --hrn-ink:#000000!important;
            --hrn-ink-2:#111111!important;
            --hrn-ink-3:#222222!important;
            --tck-accent:#000000!important;
        }
        .tck.outdoor .tck-stage{
            background:#000000!important;
            border:3px solid #ffffff!important;
        }
        .tck.outdoor .tck-frame span{
            border-color:#ffffff!important;
            border-width:4px!important;
        }
        .tck.outdoor .tck-stat{
            border:2px solid #000000!important;
            box-shadow:none!important;
        }

        /* Top Bar & Event Lock Banner */
        .tck-toolbar{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;margin-bottom:18px;}
        .tck-lock{flex:1;min-width:280px;display:flex;align-items:center;gap:10px;padding:12px 16px;
            border-radius:14px;background:color-mix(in srgb,var(--tck-accent) 10%,var(--hrn-surface, #ffffff));
            border:1px solid color-mix(in srgb,var(--tck-accent) 30%,var(--hrn-border, #e2e8f0));
            color:var(--tck-accent);font-size:13px;font-weight:600;}
        .tck-lock svg{width:18px;height:18px;flex:none;}
        .tck-lock strong{font-weight:800;}
        .tck-lock a{margin-left:auto;white-space:nowrap;color:var(--tck-accent);
            font-weight:700;text-decoration:underline;text-underline-offset:2px;}
        
        .tck-quick-toggles{display:flex;align-items:center;gap:8px;}
        .tck-pill-btn{display:inline-flex;align-items:center;gap:6px;padding:7px 12px;border-radius:10px;
            font-size:12px;font-weight:650;border:1px solid var(--hrn-border, #e2e8f0);
            background:var(--hrn-surface, #ffffff);color:var(--hrn-ink-2, #475569);cursor:pointer;
            transition:all .15s ease;}
        .tck-pill-btn:hover{background:color-mix(in srgb,var(--hrn-surface) 90%,#000);color:var(--hrn-ink, #0f172a);}
        .tck-pill-btn.active{background:color-mix(in srgb,var(--tck-accent) 12%,var(--hrn-surface));
            border-color:var(--tck-accent);color:var(--tck-accent);}
        .tck-pill-btn svg{width:15px;height:15px;}

        /* Executive Gate Hero Strip */
        .tck-executive-hero{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px;}
        @media(max-width:960px){.tck-executive-hero{grid-template-columns:repeat(2,1fr);}}
        @media(max-width:540px){.tck-executive-hero{grid-template-columns:1fr;}}
        .tck-eh-cell{background:var(--hrn-surface,#fff);border:1px solid var(--hrn-border,#e2e8f0);
            border-radius:14px;padding:12px 14px;display:flex;flex-direction:column;justify-content:space-between;
            box-shadow:var(--hrn-shadow,0 1px 3px rgba(0,0,0,.04));}
        .tck-eh-cell--hero{border-color:color-mix(in srgb,var(--tck-accent) 30%,var(--hrn-border,#e2e8f0));
            background:linear-gradient(135deg,color-mix(in srgb,var(--tck-accent) 6%,var(--hrn-surface,#fff)) 0%,var(--hrn-surface,#fff) 100%);}
        .tck-eh-label{font-size:11px;font-weight:750;letter-spacing:.05em;text-transform:uppercase;color:var(--hrn-ink-3,#64748b);
            display:flex;align-items:center;justify-content:space-between;}
        .tck-eh-val{font-size:24px;font-weight:850;letter-spacing:-.02em;line-height:1.1;margin:6px 0 3px;
            color:var(--hrn-ink,#0f172a);font-variant-numeric:tabular-nums;}
        .tck-eh-sub{font-size:11.5px;color:var(--hrn-ink-3,#64748b);font-weight:550;}

        /* Grid Layout: Camera Stage (Left) & Gate Telemetry Console (Right) */
        .tck-grid{display:grid;grid-template-columns:minmax(0,1.1fr) minmax(0,.9fr);gap:22px;align-items:start;}
        @media(max-width:960px){.tck-grid{grid-template-columns:1fr;}}

        /* ── Camera Scan Stage ─────────────────────────────────────── */
        .tck-stage{position:relative;border-radius:24px;overflow:hidden;
            background:radial-gradient(120% 100% at 50% 0%,#151d30 0%,#080c16 75%);
            box-shadow:0 24px 60px -25px rgba(0,0,0,.6),inset 0 0 0 1px rgba(255,255,255,.08);}
        .tck-stage-top{position:absolute;inset:14px 16px auto 16px;z-index:10;
            display:flex;align-items:center;justify-content:space-between;pointer-events:none;}
        .tck-live{display:inline-flex;align-items:center;gap:7px;padding:6px 12px;border-radius:999px;
            background:rgba(8,12,22,.65);backdrop-filter:blur(8px);
            font-size:11px;font-weight:750;letter-spacing:.05em;color:#cbd5e1;text-transform:uppercase;}
        .tck-live .dot{width:8px;height:8px;border-radius:50%;background:#64748b;}
        .tck-live.on{color:#f0fdf4;background:rgba(6,30,16,.75);border:1px solid rgba(34,197,94,.3);}
        .tck-live.on .dot{background:#22c55e;box-shadow:0 0 0 0 rgba(34,197,94,.7);
            animation:tckPulse 1.8s infinite;}
        .tck-live.error{color:#fef2f2;background:rgba(40,10,12,.75);border:1px solid rgba(239,68,68,.3);}
        .tck-live.error .dot{background:#ef4444;}
        @keyframes tckPulse{0%{box-shadow:0 0 0 0 rgba(34,197,94,.6);}70%{box-shadow:0 0 0 8px rgba(34,197,94,0);}100%{box-shadow:0 0 0 0 rgba(34,197,94,0);}}
        .tck-badge-tag{font-size:11px;font-weight:750;color:#94a3b8;letter-spacing:.06em;text-transform:uppercase;
            padding:5px 10px;border-radius:8px;background:rgba(255,255,255,.06);backdrop-filter:blur(6px);}

        .tck-viewport{position:relative;aspect-ratio:1/1;max-height:450px;margin:0 auto;
            display:flex;align-items:center;justify-content:center;overflow:hidden;}
        #qr-reader{width:100%!important;height:100%!important;}
        #qr-reader video{width:100%!important;height:100%!important;object-fit:cover!important;}

        /* Idle State */
        .tck-idle{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;
            justify-content:center;gap:14px;color:#94a3b8;text-align:center;padding:28px;}
        .tck-idle .ic-wrap{width:64px;height:64px;border-radius:20px;background:rgba(255,255,255,.05);
            display:flex;align-items:center;justify-content:center;border:1px solid rgba(255,255,255,.1);color:#60a5fa;}
        .tck-idle .ic-wrap svg{width:32px;height:32px;}
        .tck-idle p{font-size:13.5px;max-width:280px;line-height:1.55;color:#cbd5e1;}
        .tck-idle strong{color:#ffffff;}

        /* In-Stage Camera Diagnostic Alert (Replaces browser alert()) */
        .tck-diag{position:absolute;inset:16px;z-index:20;display:none;flex-direction:column;
            align-items:center;justify-content:center;padding:24px;border-radius:18px;
            background:rgba(15,23,42,.94);backdrop-filter:blur(12px);
            border:1px solid rgba(239,68,68,.35);text-align:center;color:#f8fafc;box-shadow:0 12px 36px rgba(0,0,0,.5);}
        .tck-diag.show{display:flex;}
        .tck-diag-ic{width:48px;height:48px;border-radius:14px;background:rgba(239,68,68,.15);
            color:#ef4444;display:flex;align-items:center;justify-content:center;margin-bottom:12px;}
        .tck-diag-ic svg{width:26px;height:26px;}
        .tck-diag h4{font-size:16px;font-weight:750;margin:0 0 6px;}
        .tck-diag p{font-size:12.5px;color:#cbd5e1;line-height:1.5;max-width:320px;margin:0 0 16px;}
        .tck-diag-actions{display:flex;gap:10px;}
        .tck-diag-btn{padding:8px 16px;border-radius:10px;font-size:12.5px;font-weight:700;cursor:pointer;border:0;}
        .tck-diag-btn--retry{background:#2563eb;color:#fff;}
        .tck-diag-btn--manual{background:rgba(255,255,255,.1);color:#e2e8f0;}

        /* Reticle Targeting Overlay */
        .tck-reticle{position:absolute;inset:0;z-index:5;pointer-events:none;
            display:flex;align-items:center;justify-content:center;
            background:radial-gradient(circle at 50% 50%,transparent 35%,rgba(6,10,20,.5) 80%);}
        .tck-frame{position:relative;width:62%;aspect-ratio:1/1;}
        .tck-frame span{position:absolute;width:34px;height:34px;border:3.5px solid rgba(255,255,255,.94);}
        .tck-frame span:nth-child(1){top:0;left:0;border-right:0;border-bottom:0;border-radius:14px 0 0 0;}
        .tck-frame span:nth-child(2){top:0;right:0;border-left:0;border-bottom:0;border-radius:0 14px 0 0;}
        .tck-frame span:nth-child(3){bottom:0;left:0;border-right:0;border-top:0;border-radius:0 0 0 14px;}
        .tck-frame span:nth-child(4){bottom:0;right:0;border-left:0;border-top:0;border-radius:0 0 14px 0;}
        .tck-scanline{position:absolute;left:4%;right:4%;height:3px;border-radius:3px;
            background:linear-gradient(90deg,transparent,#60a5fa,transparent);
            box-shadow:0 0 14px 2px rgba(96,165,250,.8);animation:tckScan 2.4s ease-in-out infinite;}
        @keyframes tckScan{0%,100%{top:6%;}50%{top:94%;}}
        .tck-stage.scanning .tck-idle,.tck-stage:not(.scanning) .tck-reticle{display:none;}

        /* Scan Outcome Flash Animation */
        .tck-flash{position:absolute;inset:0;z-index:15;pointer-events:none;opacity:0;}
        .tck-flash.show{animation:tckFlash .7s ease-out;}
        .tck-flash.ok{background:radial-gradient(circle at 50% 50%,rgba(16,185,129,.5),rgba(16,185,129,0) 70%);}
        .tck-flash.bad{background:radial-gradient(circle at 50% 50%,rgba(239,68,68,.5),rgba(239,68,68,0) 70%);}
        @keyframes tckFlash{0%{opacity:0;}20%{opacity:1;}100%{opacity:0;}}

        /* Sliding Result Banner */
        .tck-banner{position:absolute;left:14px;right:14px;bottom:14px;z-index:25;
            display:flex;align-items:center;gap:14px;padding:14px 16px;border-radius:18px;
            background:rgba(15,23,42,.92);backdrop-filter:blur(14px);
            border:1px solid rgba(255,255,255,.15);box-shadow:0 12px 30px rgba(0,0,0,.5);
            transform:translateY(150%);opacity:0;transition:transform .32s cubic-bezier(.2,.9,.3,1.2),opacity .2s;}
        .tck-banner.show{transform:translateY(0);opacity:1;}
        .tck-banner .ic{width:42px;height:42px;border-radius:12px;flex:none;display:flex;
            align-items:center;justify-content:center;}
        .tck-banner .ic svg{width:24px;height:24px;color:#fff;stroke-width:2.5;}
        .tck-banner.ok{border-color:rgba(16,185,129,.4);}
        .tck-banner.ok .ic{background:linear-gradient(135deg,#059669,#10b981);box-shadow:0 4px 12px rgba(16,185,129,.35);}
        .tck-banner.bad{border-color:rgba(239,68,68,.4);}
        .tck-banner.bad .ic{background:linear-gradient(135deg,#dc2626,#f97316);box-shadow:0 4px 12px rgba(239,68,68,.35);}
        .tck-banner .txt{min-width:0;flex:1;}
        .tck-banner .nm{font-size:15.5px;font-weight:750;color:#ffffff;white-space:nowrap;
            overflow:hidden;text-overflow:ellipsis;}
        .tck-banner .dt{font-size:12.5px;color:#cbd5e1;margin-top:2px;font-weight:550;}

        /* Scanner Foot Control Strip */
        .tck-controls{display:flex;gap:10px;padding:14px;background:rgba(255,255,255,.03);
            border-top:1px solid rgba(255,255,255,.08);}
        .tck-btn{flex:1;display:inline-flex;align-items:center;justify-content:center;gap:8px;
            height:46px;border-radius:14px;font-size:14px;font-weight:700;cursor:pointer;
            border:1px solid transparent;transition:filter .15s,background .15s,transform .1s;}
        .tck-btn:active{transform:scale(.98);}
        .tck-btn svg{width:19px;height:19px;}
        .tck-btn--go{background:linear-gradient(135deg,#2563eb,#3b82f6);color:#fff;
            box-shadow:0 8px 20px -6px rgba(37,99,235,.6);}
        .tck-btn--go:hover{filter:brightness(1.08);}
        .tck-btn--stop{background:rgba(255,255,255,.08);color:#e2e8f0;
            border-color:rgba(255,255,255,.14);}
        .tck-btn--stop:hover{background:rgba(255,255,255,.13);}
        .tck-btn--torch{flex:0 0 46px;background:rgba(255,255,255,.08);color:#e2e8f0;
            border-color:rgba(255,255,255,.14);padding:0;}
        .tck-btn--torch.active{background:#f59e0b;color:#000;border-color:#f59e0b;}

        .tck-hint{margin-top:12px;font-size:12px;color:var(--hrn-ink-3, #64748b);line-height:1.5;text-align:center;}

        /* ── Side Gate Console ─────────────────────────────────────── */
        .tck-side{display:flex;flex-direction:column;gap:16px;}

        /* Telemetry Cards */
        .tck-tally{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;}
        .tck-stat{padding:16px 12px;border-radius:18px;background:var(--hrn-surface, #ffffff);
            border:1px solid var(--hrn-border, #e2e8f0);box-shadow:var(--hrn-shadow, 0 1px 3px rgba(0,0,0,.06));
            text-align:center;position:relative;overflow:hidden;}
        .tck-stat .n{font-size:28px;font-weight:800;line-height:1;letter-spacing:-.02em;
            font-variant-numeric:tabular-nums;}
        .tck-stat .l{margin-top:7px;font-size:11px;font-weight:750;letter-spacing:.06em;
            text-transform:uppercase;color:var(--hrn-ink-3, #64748b);}
        .tck-stat--ok .n{color:var(--tck-ok, #10b981);}
        .tck-stat--warn .n{color:var(--tck-warn, #f59e0b);}
        .tck-stat--bad .n{color:var(--tck-down, #ef4444);}
        .tck-stat-sub{margin-top:4px;font-size:10px;font-weight:600;color:var(--hrn-ink-3,#64748b);}

        /* Card Shell */
        .tck-card{background:var(--hrn-surface, #ffffff);border:1px solid var(--hrn-border, #e2e8f0);
            border-radius:20px;box-shadow:var(--hrn-shadow, 0 1px 3px rgba(0,0,0,.06));padding:18px;}
        .tck-card-h{display:flex;align-items:center;gap:8px;margin-bottom:14px;
            font-size:12px;font-weight:750;letter-spacing:.06em;text-transform:uppercase;color:var(--hrn-ink-2, #334155);}
        .tck-card-h svg{width:16px;height:16px;color:var(--hrn-ink-3, #64748b);}
        .tck-card-h .cnt{margin-left:auto;font-size:11.5px;color:var(--hrn-ink-3, #64748b);
            letter-spacing:0;text-transform:none;font-weight:650;background:color-mix(in srgb,var(--hrn-surface) 80%,#0000000d);
            padding:2px 8px;border-radius:999px;}

        /* Manual Input Group */
        .tck-manual-wrap{display:flex;flex-direction:column;gap:8px;}
        .tck-manual{display:flex;gap:10px;}
        .tck-input-container{position:relative;flex:1;}
        .tck-manual input{width:100%;height:46px;padding:0 36px 0 14px;border-radius:14px;
            border:1px solid var(--hrn-border, #cbd5e1);background:var(--hrn-app-bg, #ffffff);
            color:var(--hrn-ink, #0f172a);font-size:14.5px;font-weight:650;letter-spacing:.04em;outline:none;
            text-transform:uppercase;transition:border-color .15s,box-shadow .15s;}
        .tck-manual input:focus{border-color:var(--tck-accent);
            box-shadow:0 0 0 3px color-mix(in srgb,var(--tck-accent) 20%,transparent);}
        .tck-clear-btn{position:absolute;right:6px;top:50%;transform:translateY(-50%);
            background:none;border:0;color:var(--hrn-ink-3);cursor:pointer;padding:8px;display:none;border-radius:8px;width:38px;height:38px;}
        .tck-clear-btn:hover{color:var(--hrn-ink);background:rgba(0,0,0,0.06);}
        .tck-clear-btn.visible{display:flex;align-items:center;justify-content:center;}
        .tck-clear-btn svg{width:18px;height:18px;}
        .tck-manual button[type="submit"]{height:46px;padding:0 20px;border-radius:14px;border:0;cursor:pointer;
            background:var(--tck-accent);color:#fff;font-size:14px;font-weight:700;
            display:inline-flex;align-items:center;gap:8px;transition:filter .15s,transform .1s;}
        .tck-manual button[type="submit"]:hover{filter:brightness(1.08);}
        .tck-manual button[type="submit"]:active{transform:scale(.98);}
        .tck-manual button svg{width:18px;height:18px;}
        .tck-manual-hint{display:flex;align-items:center;justify-content:space-between;font-size:11px;color:var(--hrn-ink-3, #64748b);}

        /* Recent Arrivals Filter & Feed */
        .tck-filter-row{display:flex;gap:8px;margin-bottom:12px;}
        .tck-search-inp{flex:1;height:34px;padding:0 12px 0 30px;border-radius:10px;
            border:1px solid var(--hrn-border, #e2e8f0);background:var(--hrn-app-bg, #f8fafc);
            font-size:12px;color:var(--hrn-ink);outline:none;}
        .tck-search-wrap{position:relative;flex:1;}
        .tck-search-wrap svg{position:absolute;left:9px;top:50%;transform:translateY(-50%);
            width:14px;height:14px;color:var(--hrn-ink-3);pointer-events:none;}

        .tck-list{display:flex;flex-direction:column;max-height:360px;overflow-y:auto;padding-right:4px;}
        .tck-row{display:flex;align-items:center;gap:12px;padding:11px 0;border-bottom:1px solid var(--hrn-border, #f1f5f9);
            transition:background .15s;}
        .tck-row:last-child{border-bottom:0;padding-bottom:2px;}
        .tck-ini{width:38px;height:38px;border-radius:12px;flex:none;display:flex;align-items:center;
            justify-content:center;color:#fff;font-size:14px;font-weight:750;position:relative;}
        .tck-badge{position:absolute;right:-3px;bottom:-3px;width:16px;height:16px;border-radius:50%;
            display:flex;align-items:center;justify-content:center;border:2px solid var(--hrn-surface, #ffffff);}
        .tck-badge svg{width:9px;height:9px;color:#fff;stroke-width:3;}
        .tck-badge--ok{background:var(--tck-ok, #10b981);}
        .tck-badge--warn{background:var(--tck-warn, #f59e0b);}
        .tck-body{flex:1;min-width:0;}
        .tck-nm{font-size:14px;font-weight:700;color:var(--hrn-ink, #0f172a);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
        .tck-evt{font-weight:500;color:var(--hrn-ink-3, #64748b);font-size:12.5px;}
        .tck-dt{display:inline-block;margin-top:3px;font-size:11px;font-weight:650;padding:2px 8px;border-radius:999px;}
        .tck-dt--ok{background:color-mix(in srgb,var(--tck-ok) 14%,transparent);color:var(--tck-ok, #10b981);}
        .tck-dt--warn{background:color-mix(in srgb,var(--tck-warn) 14%,transparent);color:var(--tck-warn, #f59e0b);}
        .tck-at{font-size:11.5px;color:var(--hrn-ink-3, #64748b);white-space:nowrap;font-variant-numeric:tabular-nums;font-weight:600;}
        .tck-empty{display:flex;flex-direction:column;align-items:center;gap:8px;padding:32px 0;
            color:var(--hrn-ink-3, #94a3b8);text-align:center;}
        .tck-empty svg{width:36px;height:36px;opacity:.6;}
        .tck-empty p{font-size:13px;}

        @media (prefers-reduced-motion: reduce) {
            .tck-scanline, .tck-live.on .dot {
                animation: none !important;
            }
            .tck-pill-btn, .tck-btn, .tck-row, .tck-banner {
                transition: none !important;
            }
        }
    </style>

    <div class="tck" id="tck-root">
        {{-- Top Bar with Lock Notice and Quick Gate Preferences --}}
        <div class="tck-toolbar">
            @if ($event && $lockedTitle)
                <div class="tck-lock">
                    <x-filament::icon icon="heroicon-o-lock-closed" />
                    <span>Locked to <strong>{{ $lockedTitle }}</strong> — only this event's tickets will admit.</span>
                    <a href="{{ \App\Filament\Clusters\Events\Pages\TicketCheckIn::getUrl() }}">Scan all events</a>
                </div>
            @endif

            <div class="tck-quick-toggles" style="{{ ($event && $lockedTitle) ? '' : 'margin-left:auto;' }}">
                <button type="button" class="tck-pill-btn" id="tck-outdoor-toggle" title="Toggle ultra high-contrast mode for outdoor sunlight gates">
                    <x-filament::icon icon="heroicon-o-sun" />
                    <span id="tck-outdoor-label">Outdoor Mode</span>
                </button>

                <button type="button" class="tck-pill-btn active" id="tck-audio-toggle" title="Toggle audio check-in chimes">
                    <x-filament::icon icon="heroicon-o-speaker-wave" id="tck-audio-ic" />
                    <span id="tck-audio-label">Audio On</span>
                </button>

                <button type="button" class="tck-pill-btn active" id="tck-haptic-toggle" title="Toggle haptic vibration feedback">
                    <x-filament::icon icon="heroicon-o-device-phone-mobile" />
                    <span id="tck-haptic-label">Haptics</span>
                </button>
            </div>
        </div>

        @php
            $gateTel = $this->getExecutiveGateTelemetry();
        @endphp

        {{-- Executive Gate Command Strip --}}
        <div class="tck-executive-hero">
            <div class="tck-eh-cell tck-eh-cell--hero">
                <div class="tck-eh-label">
                    <span>Live Gate Velocity</span>
                    <x-filament::icon icon="heroicon-m-bolt" style="width:14px;height:14px;color:var(--tck-accent);" />
                </div>
                <div class="tck-eh-val" style="color:var(--tck-accent);">{{ $gateTel['velocity'] }}</div>
                <div class="tck-eh-sub">Real-time arrival rate</div>
            </div>

            <div class="tck-eh-cell">
                <div class="tck-eh-label">
                    <span>QR Recognition Success</span>
                    <x-filament::icon icon="heroicon-m-check-badge" style="width:14px;height:14px;color:#10b981;" />
                </div>
                <div class="tck-eh-val" style="color:#10b981;">{{ $gateTel['qr_success'] }}</div>
                <div class="tck-eh-sub">Sub-150ms optical decode</div>
            </div>

            <div class="tck-eh-cell">
                <div class="tck-eh-label">
                    <span>No-Show Prediction</span>
                    <x-filament::icon icon="heroicon-m-user-minus" style="width:14px;height:14px;color:#6366f1;" />
                </div>
                <div class="tck-eh-val">{{ $gateTel['no_show_risk'] }}</div>
                <div class="tck-eh-sub">{!! $gateTel['no_show_desc'] !!}</div>
            </div>

            <div class="tck-eh-cell">
                <div class="tck-eh-label">
                    <span>Fraud & Duplication</span>
                    <x-filament::icon icon="heroicon-m-shield-exclamation" style="width:14px;height:14px;color:{{ $gateTel['fraud_alerts'] > 0 ? '#ef4444' : '#10b981' }};" />
                </div>
                <div class="tck-eh-val" style="color:{{ $gateTel['fraud_alerts'] > 0 ? '#ef4444' : '#0f172a' }};">
                    {{ $gateTel['fraud_alerts'] }} <span style="font-size:13px;font-weight:600;color:#64748b;">flagged</span>
                </div>
                <div class="tck-eh-sub">{{ $gateTel['fraud_status'] }}</div>
            </div>
        </div>

        <div class="tck-grid">
            {{-- ── Left Stage: Camera Scanner ───────────────────────────── --}}
            <div>
                <div class="tck-stage" id="tck-stage">
                    <div class="tck-stage-top">
                        <span class="tck-live" id="tck-live"><span class="dot"></span><span id="tck-live-txt">Camera off</span></span>
                        <span class="tck-badge-tag">Haraan Gate Console</span>
                    </div>

                    <div class="tck-viewport">
                        <div wire:ignore style="position:absolute;inset:0;">
                            <div id="qr-reader"></div>
                        </div>

                        <div class="tck-idle" id="tck-idle">
                            <div class="ic-wrap">
                                <x-filament::icon icon="heroicon-o-qr-code" />
                            </div>
                            <p>Tap <strong>Start camera</strong> and hold the attendee's ticket QR inside the frame.</p>
                        </div>

                        {{-- In-Stage Diagnostic Banner (No disruptive window.alert) --}}
                        <div class="tck-diag" id="tck-diag">
                            <div class="tck-diag-ic">
                                <x-filament::icon icon="heroicon-o-video-camera-slash" />
                            </div>
                            <h4 id="tck-diag-title">Camera Unavailable</h4>
                            <p id="tck-diag-body">Permission is blocked or this connection is insecure. Please allow camera access in browser settings or use manual ticket admission.</p>
                            <div class="tck-diag-actions">
                                <button type="button" class="tck-diag-btn tck-diag-btn--retry" id="tck-diag-retry">Try Again</button>
                                <button type="button" class="tck-diag-btn tck-diag-btn--manual" id="tck-diag-manual">Manual Entry</button>
                            </div>
                        </div>

                        {{-- Aiming Reticle with Corner Brackets & Animated Laser Scanline --}}
                        <div class="tck-reticle">
                            <div class="tck-frame">
                                <span></span><span></span><span></span><span></span>
                                <div class="tck-scanline"></div>
                            </div>
                        </div>

                        {{-- Scan Feedback Flash Overlay --}}
                        <div class="tck-flash" id="tck-flash"></div>

                        {{-- Slide-up Admission Result HUD --}}
                        <div class="tck-banner" id="tck-banner" role="status" aria-live="assertive" aria-atomic="true">
                            <div class="ic" id="tck-banner-ic"></div>
                            <div class="txt">
                                <div class="nm" id="tck-banner-nm"></div>
                                <div class="dt" id="tck-banner-dt"></div>
                            </div>
                        </div>
                    </div>

                    {{-- Scanner Footer Controls --}}
                    <div class="tck-controls">
                        <button type="button" class="tck-btn tck-btn--go" id="qr-start">
                            <x-filament::icon icon="heroicon-m-camera" />
                            <span>Start camera</span>
                        </button>
                        <button type="button" class="tck-btn tck-btn--stop" id="qr-stop">
                            <x-filament::icon icon="heroicon-m-stop" />
                            <span>Stop</span>
                        </button>
                        <button type="button" class="tck-btn tck-btn--torch" id="tck-torch" style="display:none;" title="Toggle flashlight / torch">
                            <x-filament::icon icon="heroicon-m-bolt" />
                        </button>
                    </div>
                </div>

                <p class="tck-hint">
                    Camera access requires HTTPS or localhost. If camera permissions are restricted on this device,
                    gate operators can enter ticket codes manually on the right.
                </p>
            </div>

            {{-- ── Right Stage: Gate Telemetry & Recent Scans ───────────── --}}
            <div class="tck-side">
                {{-- High-Visibility Gate Tally --}}
                <div class="tck-tally">
                    <div class="tck-stat tck-stat--ok">
                        <div class="n">{{ $admitted }}</div>
                        <div class="l">Admitted</div>
                        @php
                            $totalScans = $admitted + $repeats + $rejected;
                            $passRate = $totalScans > 0 ? round(($admitted / $totalScans) * 100) : 100;
                        @endphp
                        <div class="tck-stat-sub">{{ $passRate }}% pass rate</div>
                    </div>
                    <div class="tck-stat tck-stat--warn">
                        <div class="n">{{ $repeats }}</div>
                        <div class="l">Repeats</div>
                        <div class="tck-stat-sub">already used</div>
                    </div>
                    <div class="tck-stat tck-stat--bad">
                        <div class="n">{{ $rejected }}</div>
                        <div class="l">Rejected</div>
                        <div class="tck-stat-sub">invalid / wrong</div>
                    </div>
                </div>

                {{-- Manual Code Entry Box --}}
                <div class="tck-card">
                    <div class="tck-card-h">
                        <x-filament::icon icon="heroicon-m-hashtag" />
                        Manual Code Entry
                        <span class="cnt">Keyboard / Barcode Wand</span>
                    </div>
                    <form wire:submit="submitManual" class="tck-manual-wrap">
                        <div class="tck-manual">
                            <div class="tck-input-container">
                                <input type="text"
                                       id="tck-manual-input"
                                       wire:model="manualCode"
                                       placeholder="e.g. TCK-882194"
                                       autocomplete="off"
                                       spellcheck="false" />
                                <button type="button" class="tck-clear-btn" id="tck-manual-clear" title="Clear input">
                                    <x-filament::icon icon="heroicon-m-x-mark" />
                                </button>
                            </div>
                            <button type="submit">
                                <x-filament::icon icon="heroicon-m-check" />
                                <span>Admit</span>
                            </button>
                        </div>
                        <div class="tck-manual-hint">
                            <span>Accepts raw codes or scanned URL strings</span>
                            <span>Press ↵ Enter to admit</span>
                        </div>
                    </form>
                </div>

                {{-- Recent Arrivals Ledger with Live Filter --}}
                <div class="tck-card">
                    <div class="tck-card-h">
                        <x-filament::icon icon="heroicon-m-clock" />
                        Recent Arrivals
                        <span class="cnt">{{ count($recent) }} recent</span>
                    </div>

                    @if (count($recent) > 0)
                        <div class="tck-filter-row">
                            <div class="tck-search-wrap">
                                <x-filament::icon icon="heroicon-m-magnifying-glass" />
                                <input type="text" id="tck-search-ledger" class="tck-search-inp" placeholder="Quick find attendee name or event..." aria-label="Filter scanned attendee ledger" />
                            </div>
                        </div>
                    @endif

                    <div class="tck-list" id="tck-recent-list">
                        @forelse ($recent as $r)
                            @php
                                $nm = trim((string) $r['name']) ?: 'Guest';
                                $hue = crc32($nm) % 360;
                                $ini = strtoupper(mb_substr($nm, 0, 1));
                            @endphp
                            <div class="tck-row" data-search="{{ strtolower($nm . ' ' . ($r['event'] ?? '') . ' ' . $r['detail']) }}">
                                <div class="tck-ini" style="background:hsl({{ $hue }} 56% 44%)">
                                    {{ $ini }}
                                    <span class="tck-badge {{ $r['ok'] ? 'tck-badge--ok' : 'tck-badge--warn' }}">
                                        <x-filament::icon icon="{{ $r['ok'] ? 'heroicon-m-check' : 'heroicon-m-exclamation-triangle' }}" />
                                    </span>
                                </div>
                                <div class="tck-body">
                                    <div class="tck-nm">
                                        {{ $r['name'] }}@if (!empty($r['event']))<span class="tck-evt"> · {{ $r['event'] }}</span>@endif
                                    </div>
                                    <span class="tck-dt {{ $r['ok'] ? 'tck-dt--ok' : 'tck-dt--warn' }}">{{ $r['detail'] }}</span>
                                </div>
                                <span class="tck-at">{{ $r['at'] }}</span>
                            </div>
                        @empty
                            <div class="tck-empty">
                                <x-filament::icon icon="heroicon-o-inbox" />
                                <p>Scans will appear here in realtime as attendees arrive.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    @assets
        <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js" defer></script>
    @endassets

    @script
    <script>
        let scanner = null;
        let lastCode = null;
        let lastAt = 0;
        let audioCtx = null;
        let activeVideoTrack = null;
        let torchOn = false;

        // Gate UI Element References
        const rootNode     = document.getElementById('tck-root');
        const stage        = document.getElementById('tck-stage');
        const startBtn     = document.getElementById('qr-start');
        const stopBtn      = document.getElementById('qr-stop');
        const torchBtn     = document.getElementById('tck-torch');
        const live         = document.getElementById('tck-live');
        const liveTxt      = document.getElementById('tck-live-txt');
        const flash        = document.getElementById('tck-flash');
        const banner       = document.getElementById('tck-banner');
        const bIc          = document.getElementById('tck-banner-ic');
        const bNm          = document.getElementById('tck-banner-nm');
        const bDt          = document.getElementById('tck-banner-dt');
        const diagBox      = document.getElementById('tck-diag');
        const diagTitle    = document.getElementById('tck-diag-title');
        const diagBody     = document.getElementById('tck-diag-body');
        const diagRetry    = document.getElementById('tck-diag-retry');
        const diagManual   = document.getElementById('tck-diag-manual');
        const manualInput  = document.getElementById('tck-manual-input');
        const manualClear  = document.getElementById('tck-manual-clear');
        const searchLedger = document.getElementById('tck-search-ledger');
        const outdoorToggle= document.getElementById('tck-outdoor-toggle');
        const audioToggle  = document.getElementById('tck-audio-toggle');
        const audioLabel   = document.getElementById('tck-audio-label');
        const hapticToggle = document.getElementById('tck-haptic-toggle');
        const hapticLabel  = document.getElementById('tck-haptic-label');

        // ── Preferences: Outdoor Mode, Audio, Haptics ─────────────────
        let audioEnabled = localStorage.getItem('tck_audio') !== '0';
        let hapticEnabled = localStorage.getItem('tck_haptic') !== '0';
        let outdoorEnabled = localStorage.getItem('tck_outdoor') === '1';

        function syncPreferences() {
            if (rootNode) {
                rootNode.classList.toggle('outdoor', outdoorEnabled);
            }
            if (outdoorToggle) {
                outdoorToggle.classList.toggle('active', outdoorEnabled);
            }
            if (audioToggle && audioLabel) {
                audioToggle.classList.toggle('active', audioEnabled);
                audioLabel.textContent = audioEnabled ? 'Audio On' : 'Muted';
            }
            if (hapticToggle && hapticLabel) {
                hapticToggle.classList.toggle('active', hapticEnabled);
                hapticLabel.textContent = hapticEnabled ? 'Haptics' : 'Silent';
            }
        }
        syncPreferences();

        outdoorToggle?.addEventListener('click', () => {
            outdoorEnabled = !outdoorEnabled;
            localStorage.setItem('tck_outdoor', outdoorEnabled ? '1' : '0');
            syncPreferences();
        });

        audioToggle?.addEventListener('click', () => {
            audioEnabled = !audioEnabled;
            localStorage.setItem('tck_audio', audioEnabled ? '1' : '0');
            syncPreferences();
        });

        hapticToggle?.addEventListener('click', () => {
            hapticEnabled = !hapticEnabled;
            localStorage.setItem('tck_haptic', hapticEnabled ? '1' : '0');
            syncPreferences();
        });

        // ── Camera Diagnostics (replaces intrusive alert()) ───────────
        function showDiagnostic(title, message) {
            if (!diagBox) return;
            diagTitle.textContent = title;
            diagBody.textContent = message;
            diagBox.classList.add('show');
            live.className = 'tck-live error';
            liveTxt.textContent = 'Camera blocked';
        }

        function hideDiagnostic() {
            if (diagBox) diagBox.classList.remove('show');
        }

        diagRetry?.addEventListener('click', () => {
            hideDiagnostic();
            startBtn?.click();
        });

        diagManual?.addEventListener('click', () => {
            hideDiagnostic();
            manualInput?.focus();
            manualInput?.select();
        });

        // ── Manual Input UX Enhancements ─────────────────────────────
        if (manualInput && manualClear) {
            const updateClearVisibility = () => {
                manualClear.classList.toggle('visible', manualInput.value.length > 0);
            };
            manualInput.addEventListener('input', updateClearVisibility);
            manualClear.addEventListener('click', () => {
                manualInput.value = '';
                manualInput.dispatchEvent(new Event('input'));
                updateClearVisibility();
                manualInput.focus();
            });
        }

        // ── Recent Ledger Live Filtering ─────────────────────────────
        searchLedger?.addEventListener('input', (e) => {
            const query = (e.target.value || '').trim().toLowerCase();
            const rows = document.querySelectorAll('#tck-recent-list .tck-row');
            rows.forEach((r) => {
                const searchData = r.getAttribute('data-search') || '';
                r.style.display = searchData.includes(query) ? 'flex' : 'none';
            });
        });

        function setLive(on) {
            live.className = 'tck-live' + (on ? ' on' : '');
            liveTxt.textContent = on ? 'Scanning' : 'Camera off';
            stage.classList.toggle('scanning', on);
            if (!on) {
                activeVideoTrack = null;
                torchOn = false;
                if (torchBtn) {
                    torchBtn.style.display = 'none';
                    torchBtn.classList.remove('active');
                }
            }
        }

        // ── Audio Feedback: Distinct pleasant chime vs buzzer ────────
        function beep(ok) {
            if (!audioEnabled) return;
            try {
                audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
                if (audioCtx.state === 'suspended') {
                    audioCtx.resume();
                }
                const now = audioCtx.currentTime;

                if (ok) {
                    // Two-tone rising major third chime (880Hz -> 1100Hz)
                    const osc1 = audioCtx.createOscillator();
                    const osc2 = audioCtx.createOscillator();
                    const gain = audioCtx.createGain();

                    osc1.type = 'sine';
                    osc2.type = 'sine';
                    osc1.frequency.setValueAtTime(880, now);
                    osc2.frequency.setValueAtTime(1108.73, now + 0.08);

                    gain.gain.setValueAtTime(0.001, now);
                    gain.gain.exponentialRampToValueAtTime(0.3, now + 0.02);
                    gain.gain.exponentialRampToValueAtTime(0.001, now + 0.26);

                    osc1.connect(gain);
                    osc2.connect(gain);
                    gain.connect(audioCtx.destination);

                    osc1.start(now);
                    osc1.stop(now + 0.12);
                    osc2.start(now + 0.08);
                    osc2.stop(now + 0.28);
                } else {
                    // Low warning double-buzz
                    const osc = audioCtx.createOscillator();
                    const gain = audioCtx.createGain();
                    osc.type = 'sawtooth';
                    osc.frequency.setValueAtTime(180, now);
                    gain.gain.setValueAtTime(0.001, now);
                    gain.gain.exponentialRampToValueAtTime(0.22, now + 0.02);
                    gain.gain.exponentialRampToValueAtTime(0.001, now + 0.28);

                    osc.connect(gain);
                    gain.connect(audioCtx.destination);
                    osc.start(now);
                    osc.stop(now + 0.30);
                }
            } catch (e) { /* audio unavailable */ }
        }

        // ── Haptic Patterns ──────────────────────────────────────────
        const canVibrate = typeof navigator !== 'undefined' && 'vibrate' in navigator;
        let hapticsPrimed = false;
        function primeHaptics() {
            if (hapticsPrimed || !canVibrate) return;
            try { navigator.vibrate(1); hapticsPrimed = true; } catch (e) {}
        }
        function haptic(ok) {
            if (!canVibrate || !hapticEnabled) return;
            try {
                // Crisp double tap for admitted, distinct error buzz for reject
                navigator.vibrate(ok ? [40, 40, 40] : [90, 60, 90, 60, 90]);
            } catch (e) {}
        }
        document.querySelector('.tck')?.addEventListener('pointerdown', primeHaptics, { once: true, passive: true });

        // ── Torch / Flashlight Support ───────────────────────────────
        function checkTorchSupport() {
            try {
                const videoEl = document.querySelector('#qr-reader video');
                if (!videoEl || !videoEl.srcObject) return;
                const stream = videoEl.srcObject;
                const track = stream.getVideoTracks()[0];
                if (!track) return;
                activeVideoTrack = track;
                const capabilities = track.getCapabilities ? track.getCapabilities() : {};
                if (capabilities.torch && torchBtn) {
                    torchBtn.style.display = 'inline-flex';
                }
            } catch (e) {}
        }

        torchBtn?.addEventListener('click', async () => {
            if (!activeVideoTrack) return;
            try {
                torchOn = !torchOn;
                await activeVideoTrack.applyConstraints({
                    advanced: [{ torch: torchOn }]
                });
                torchBtn.classList.toggle('active', torchOn);
            } catch (e) {
                console.warn('Torch failed', e);
            }
        });

        // ── Livewire Scan Feedback Listener ──────────────────────────
        let bannerTimer = null;
        $wire.on('scan-feedback', (e) => {
            const ok = !!e.ok, name = e.name || 'Ticket', detail = e.detail || '';

            if (flash) {
                flash.className = 'tck-flash ' + (ok ? 'ok' : 'bad');
                void flash.offsetWidth;
                flash.classList.add('show');
                setTimeout(() => flash.classList.remove('show'), 720);
            }

            beep(ok);
            haptic(ok);

            if (banner) {
                banner.className = 'tck-banner ' + (ok ? 'ok' : 'bad');
                bIc.innerHTML = ok
                    ? '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>'
                    : '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>';
                bNm.textContent = name;
                bDt.textContent = detail;
                requestAnimationFrame(() => banner.classList.add('show'));

                clearTimeout(bannerTimer);
                bannerTimer = setTimeout(() => banner.classList.remove('show'), 3500);
            }
        });

        function onDecoded(text) {
            const now = Date.now();
            if (text === lastCode && (now - lastAt) < 3000) return;
            lastCode = text;
            lastAt = now;
            $wire.scan(text);
        }

        function explainCameraError(err) {
            const name = (err && (err.name || err.type)) || String(err || '');
            if (/NotAllowed|Permission|Denied|Security/i.test(name)) {
                return {
                    title: 'Camera Permission Blocked',
                    body: 'Access to the camera was denied. Please tap the lock or camera icon in your browser address bar, set Camera to “Allow”, and tap Try Again.'
                };
            }
            if (/NotFound|DevicesNotFound|OverconstrainedError/i.test(name)) {
                return {
                    title: 'No Camera Detected',
                    body: 'No camera device was detected on this terminal. Please connect a webcam or use the manual ticket code entry on the right.'
                };
            }
            if (/NotReadable|TrackStart|InUse/i.test(name)) {
                return {
                    title: 'Camera Currently In Use',
                    body: 'The camera is being used by another app or browser tab. Please close other camera tabs and tap Try Again.'
                };
            }
            return {
                title: 'Camera Initialization Failed',
                body: 'Could not open the camera (' + name + '). You can admit tickets immediately using the manual code box.'
            };
        }

        function failStart(err) {
            scanner = null;
            setLive(false);
            const errInfo = explainCameraError(err);
            showDiagnostic(errInfo.title, errInfo.body);
        }

        const scanConfig = { fps: 12, qrbox: { width: 260, height: 260 } };

        startBtn?.addEventListener('click', () => {
            hideDiagnostic();
            primeHaptics();

            if (!window.isSecureContext || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                showDiagnostic(
                    'HTTPS Required',
                    'Browsers only allow camera hardware access over secure HTTPS connections or localhost. Use manual code entry on this terminal.'
                );
                return;
            }
            if (typeof Html5Qrcode === 'undefined') {
                showDiagnostic(
                    'Scanner Loading…',
                    'The scanner engine is initializing. Please wait a moment and tap Try Again.'
                );
                return;
            }
            if (scanner) return;

            liveTxt.textContent = 'Starting…';
            scanner = new Html5Qrcode('qr-reader');

            scanner.start({ facingMode: 'environment' }, scanConfig, onDecoded, () => {})
                .then(() => {
                    setLive(true);
                    setTimeout(checkTorchSupport, 800);
                })
                .catch(() => {
                    Html5Qrcode.getCameras()
                        .then((cameras) => {
                            if (!cameras || cameras.length === 0) {
                                failStart({ name: 'NotFoundError' });
                                return;
                            }
                            const camId = cameras[cameras.length - 1].id;
                            scanner.start(camId, scanConfig, onDecoded, () => {})
                                .then(() => {
                                    setLive(true);
                                    setTimeout(checkTorchSupport, 800);
                                })
                                .catch(failStart);
                        })
                        .catch(failStart);
                });
        });

        stopBtn?.addEventListener('click', () => {
            if (scanner) {
                scanner.stop().finally(() => {
                    scanner = null;
                    setLive(false);
                });
            }
        });
    </script>
    @endscript
</x-filament-panels::page>
