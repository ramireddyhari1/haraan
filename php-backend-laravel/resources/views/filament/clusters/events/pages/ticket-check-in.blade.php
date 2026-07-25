<x-filament-panels::page>
    {{-- Purpose-built gate-scanning console. Reuses the panel-wide --hrn-* tokens;
         the scanner element IDs (qr-reader / qr-start / qr-stop) and decode flow
         are unchanged — only the shell and feedback layer are new. --}}
    <style>
        .tck{--tck-accent:#1e50e6;max-width:1120px;margin:0 auto;}
        .dark .tck{--tck-accent:#5b8cff;}

        /* Lock banner */
        .tck-lock{display:flex;align-items:center;gap:10px;margin-bottom:18px;padding:12px 16px;
            border-radius:14px;background:color-mix(in srgb,var(--tck-accent) 8%,var(--hrn-surface));
            border:1px solid color-mix(in srgb,var(--tck-accent) 26%,var(--hrn-border));
            color:var(--tck-accent);font-size:13px;font-weight:600;}
        .tck-lock svg{width:18px;height:18px;flex:none;}
        .tck-lock strong{font-weight:800;}
        .tck-lock a{margin-left:auto;white-space:nowrap;color:var(--tck-accent);
            font-weight:700;text-decoration:underline;text-underline-offset:2px;}

        /* Layout: scan stage + side console */
        .tck-grid{display:grid;grid-template-columns:minmax(0,1.05fr) minmax(0,.95fr);gap:22px;align-items:start;}
        @media(max-width:960px){.tck-grid{grid-template-columns:1fr;}}

        /* ── Scan stage ─────────────────────────────────────────── */
        .tck-stage{position:relative;border-radius:22px;overflow:hidden;
            background:radial-gradient(120% 90% at 50% -10%,#1c2740 0%,#0a0f1c 62%);
            box-shadow:0 24px 60px -30px rgba(5,10,25,.7),inset 0 0 0 1px rgba(255,255,255,.06);}
        .tck-stage-top{position:absolute;inset:14px 16px auto 16px;z-index:3;
            display:flex;align-items:center;justify-content:space-between;pointer-events:none;}
        .tck-live{display:inline-flex;align-items:center;gap:7px;padding:5px 11px;border-radius:999px;
            background:rgba(8,12,22,.55);backdrop-filter:blur(6px);
            font-size:11px;font-weight:700;letter-spacing:.04em;color:#cdd6e6;text-transform:uppercase;}
        .tck-live .dot{width:7px;height:7px;border-radius:50%;background:#64748b;}
        .tck-live.on{color:#eafff3;}
        .tck-live.on .dot{background:#22c55e;box-shadow:0 0 0 0 rgba(34,197,94,.6);
            animation:tckPulse 1.8s infinite;}
        @keyframes tckPulse{0%{box-shadow:0 0 0 0 rgba(34,197,94,.55);}70%{box-shadow:0 0 0 7px rgba(34,197,94,0);}100%{box-shadow:0 0 0 0 rgba(34,197,94,0);}}
        .tck-tag{font-size:11px;font-weight:700;color:#8b96ab;letter-spacing:.05em;text-transform:uppercase;}

        .tck-viewport{position:relative;aspect-ratio:1/1;max-height:440px;margin:0 auto;
            display:flex;align-items:center;justify-content:center;}
        #qr-reader{width:100%;height:100%;}
        #qr-reader video{width:100%!important;height:100%!important;object-fit:cover!important;}
        .tck-idle{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;
            justify-content:center;gap:12px;color:#7b879e;text-align:center;padding:24px;}
        .tck-idle svg{width:44px;height:44px;opacity:.65;}
        .tck-idle p{font-size:13px;max-width:230px;line-height:1.5;}

        /* Reticle */
        .tck-reticle{position:absolute;inset:0;z-index:2;pointer-events:none;
            display:flex;align-items:center;justify-content:center;
            background:radial-gradient(circle at 50% 50%,transparent 32%,rgba(6,10,20,.4) 78%);}
        .tck-frame{position:relative;width:60%;aspect-ratio:1/1;}
        .tck-frame span{position:absolute;width:30px;height:30px;border:3px solid rgba(255,255,255,.92);}
        .tck-frame span:nth-child(1){top:0;left:0;border-right:0;border-bottom:0;border-radius:12px 0 0 0;}
        .tck-frame span:nth-child(2){top:0;right:0;border-left:0;border-bottom:0;border-radius:0 12px 0 0;}
        .tck-frame span:nth-child(3){bottom:0;left:0;border-right:0;border-top:0;border-radius:0 0 0 12px;}
        .tck-frame span:nth-child(4){bottom:0;right:0;border-left:0;border-top:0;border-radius:0 0 12px 0;}
        .tck-scanline{position:absolute;left:6%;right:6%;height:2px;border-radius:2px;
            background:linear-gradient(90deg,transparent,#5b8cff,transparent);
            box-shadow:0 0 12px 1px rgba(91,140,255,.7);animation:tckScan 2.4s ease-in-out infinite;}
        @keyframes tckScan{0%,100%{top:8%;}50%{top:92%;}}
        .tck-stage.scanning .tck-idle,.tck-stage:not(.scanning) .tck-reticle{display:none;}

        /* Flash overlay on result */
        .tck-flash{position:absolute;inset:0;z-index:4;pointer-events:none;opacity:0;}
        .tck-flash.show{animation:tckFlash .7s ease-out;}
        .tck-flash.ok{background:radial-gradient(circle at 50% 50%,rgba(34,197,94,.42),rgba(34,197,94,0) 70%);}
        .tck-flash.bad{background:radial-gradient(circle at 50% 50%,rgba(239,68,68,.42),rgba(239,68,68,0) 70%);}
        @keyframes tckFlash{0%{opacity:0;}18%{opacity:1;}100%{opacity:0;}}

        /* Result banner slides up from the stage bottom */
        .tck-banner{position:absolute;left:12px;right:12px;bottom:12px;z-index:5;
            display:flex;align-items:center;gap:12px;padding:13px 15px;border-radius:16px;
            background:rgba(10,15,26,.82);backdrop-filter:blur(10px);
            border:1px solid rgba(255,255,255,.1);
            transform:translateY(140%);opacity:0;transition:transform .32s cubic-bezier(.2,.9,.3,1.2),opacity .2s;}
        .tck-banner.show{transform:translateY(0);opacity:1;}
        .tck-banner .ic{width:38px;height:38px;border-radius:11px;flex:none;display:flex;
            align-items:center;justify-content:center;}
        .tck-banner .ic svg{width:22px;height:22px;color:#fff;}
        .tck-banner.ok .ic{background:linear-gradient(140deg,#16a34a,#22c55e);}
        .tck-banner.bad .ic{background:linear-gradient(140deg,#dc2626,#f97316);}
        .tck-banner .txt{min-width:0;flex:1;}
        .tck-banner .nm{font-size:15px;font-weight:750;color:#fff;white-space:nowrap;
            overflow:hidden;text-overflow:ellipsis;}
        .tck-banner .dt{font-size:12px;color:#aab4c6;margin-top:1px;}

        /* Stage footer controls */
        .tck-controls{display:flex;gap:10px;padding:14px;background:rgba(255,255,255,.02);
            border-top:1px solid rgba(255,255,255,.06);}
        .tck-btn{flex:1;display:inline-flex;align-items:center;justify-content:center;gap:8px;
            height:44px;border-radius:12px;font-size:13.5px;font-weight:700;cursor:pointer;
            border:1px solid transparent;transition:filter .15s,background .15s;}
        .tck-btn svg{width:18px;height:18px;}
        .tck-btn--go{background:linear-gradient(140deg,#1e50e6,#3b74ff);color:#fff;
            box-shadow:0 8px 18px -8px rgba(30,80,230,.8);}
        .tck-btn--go:hover{filter:brightness(1.06);}
        .tck-btn--stop{background:rgba(255,255,255,.06);color:#cdd6e6;
            border-color:rgba(255,255,255,.12);}
        .tck-btn--stop:hover{background:rgba(255,255,255,.1);}
        .tck-hint{margin-top:10px;font-size:11.5px;color:var(--hrn-ink-3);line-height:1.5;text-align:center;}

        /* ── Side console ───────────────────────────────────────── */
        .tck-side{display:flex;flex-direction:column;gap:16px;}

        /* Tally */
        .tck-tally{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;}
        .tck-stat{padding:14px 12px;border-radius:16px;background:var(--hrn-surface);
            border:1px solid var(--hrn-border);box-shadow:var(--hrn-shadow);text-align:center;}
        .tck-stat .n{font-size:26px;font-weight:800;line-height:1;letter-spacing:-.02em;
            font-variant-numeric:tabular-nums;}
        .tck-stat .l{margin-top:6px;font-size:10.5px;font-weight:700;letter-spacing:.05em;
            text-transform:uppercase;color:var(--hrn-ink-3);}
        .tck-stat--ok .n{color:var(--hrn-ok);}
        .tck-stat--warn .n{color:var(--hrn-warn);}
        .tck-stat--bad .n{color:var(--hrn-down);}

        /* Card wrapper */
        .tck-card{background:var(--hrn-surface);border:1px solid var(--hrn-border);
            border-radius:18px;box-shadow:var(--hrn-shadow);padding:16px;}
        .tck-card-h{display:flex;align-items:center;gap:8px;margin-bottom:12px;
            font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--hrn-ink-2);}
        .tck-card-h svg{width:15px;height:15px;color:var(--hrn-ink-3);}
        .tck-card-h .cnt{margin-left:auto;font-size:11px;color:var(--hrn-ink-3);letter-spacing:0;text-transform:none;font-weight:600;}

        /* Manual entry */
        .tck-manual{display:flex;gap:9px;}
        .tck-manual input{flex:1;height:44px;padding:0 14px;border-radius:12px;
            border:1px solid var(--hrn-border);background:var(--hrn-app-bg,transparent);
            color:var(--hrn-ink);font-size:14px;font-weight:600;letter-spacing:.02em;outline:none;
            transition:border-color .15s,box-shadow .15s;}
        .tck-manual input:focus{border-color:var(--tck-accent);
            box-shadow:0 0 0 3px color-mix(in srgb,var(--tck-accent) 18%,transparent);}
        .tck-manual button{height:44px;padding:0 18px;border-radius:12px;border:0;cursor:pointer;
            background:var(--tck-accent);color:#fff;font-size:13.5px;font-weight:700;
            display:inline-flex;align-items:center;gap:7px;transition:filter .15s;}
        .tck-manual button:hover{filter:brightness(1.06);}
        .tck-manual button svg{width:17px;height:17px;}

        /* Recent feed */
        .tck-row{display:flex;align-items:center;gap:12px;padding:11px 0;border-bottom:1px solid var(--hrn-border);}
        .tck-row:last-child{border-bottom:0;padding-bottom:2px;}
        .tck-ini{width:36px;height:36px;border-radius:50%;flex:none;display:flex;align-items:center;
            justify-content:center;color:#fff;font-size:13.5px;font-weight:700;position:relative;}
        .tck-badge{position:absolute;right:-2px;bottom:-2px;width:15px;height:15px;border-radius:50%;
            display:flex;align-items:center;justify-content:center;border:2px solid var(--hrn-surface);}
        .tck-badge svg{width:9px;height:9px;color:#fff;stroke-width:3;}
        .tck-badge--ok{background:var(--hrn-ok);}
        .tck-badge--warn{background:var(--hrn-warn);}
        .tck-body{flex:1;min-width:0;}
        .tck-nm{font-size:14px;font-weight:650;color:var(--hrn-ink);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
        .tck-evt{font-weight:400;color:var(--hrn-ink-3);}
        .tck-dt{display:inline-block;margin-top:3px;font-size:11px;font-weight:600;padding:2px 9px;border-radius:999px;}
        .tck-dt--ok{background:var(--hrn-ok-bg);color:var(--hrn-ok);}
        .tck-dt--warn{background:var(--hrn-warn-bg);color:var(--hrn-warn);}
        .tck-at{font-size:11px;color:var(--hrn-ink-3);white-space:nowrap;font-variant-numeric:tabular-nums;}
        .tck-empty{display:flex;flex-direction:column;align-items:center;gap:8px;padding:26px 0;
            color:var(--hrn-ink-3);text-align:center;}
        .tck-empty svg{width:30px;height:30px;opacity:.5;}
        .tck-empty p{font-size:12.5px;}
    </style>

    <div class="tck">
        @if ($event && $lockedTitle)
            <div class="tck-lock">
                <x-filament::icon icon="heroicon-o-lock-closed" />
                <span>Locked to <strong>{{ $lockedTitle }}</strong> — only this event's tickets will admit.</span>
                <a href="{{ \App\Filament\Clusters\Events\Pages\TicketCheckIn::getUrl() }}">Scan all events</a>
            </div>
        @endif

        <div class="tck-grid">
            {{-- ── Scan stage ─────────────────────────────────────── --}}
            <div>
                <div class="tck-stage" id="tck-stage">
                    <div class="tck-stage-top">
                        <span class="tck-live" id="tck-live"><span class="dot"></span><span id="tck-live-txt">Camera off</span></span>
                        <span class="tck-tag">Gate scanner</span>
                    </div>

                    <div class="tck-viewport">
                        <div wire:ignore style="position:absolute;inset:0;">
                            <div id="qr-reader"></div>
                        </div>

                        <div class="tck-idle" id="tck-idle">
                            <x-filament::icon icon="heroicon-o-qr-code" />
                            <p>Tap <strong>Start camera</strong> and hold the attendee's ticket QR inside the frame.</p>
                        </div>

                        <div class="tck-reticle">
                            <div class="tck-frame">
                                <span></span><span></span><span></span><span></span>
                                <div class="tck-scanline"></div>
                            </div>
                        </div>

                        <div class="tck-flash" id="tck-flash"></div>

                        <div class="tck-banner" id="tck-banner">
                            <div class="ic" id="tck-banner-ic"></div>
                            <div class="txt">
                                <div class="nm" id="tck-banner-nm"></div>
                                <div class="dt" id="tck-banner-dt"></div>
                            </div>
                        </div>
                    </div>

                    <div class="tck-controls">
                        <button type="button" class="tck-btn tck-btn--go" id="qr-start">
                            <x-filament::icon icon="heroicon-m-camera" />
                            <span>Start camera</span>
                        </button>
                        <button type="button" class="tck-btn tck-btn--stop" id="qr-stop">
                            <x-filament::icon icon="heroicon-m-stop" />
                            <span>Stop</span>
                        </button>
                    </div>
                </div>

                <p class="tck-hint">
                    The camera needs a secure (HTTPS) connection — which this panel has. If the camera is
                    blocked on your device, use manual entry on the right.
                </p>
            </div>

            {{-- ── Side console ───────────────────────────────────── --}}
            <div class="tck-side">
                <div class="tck-tally">
                    <div class="tck-stat tck-stat--ok">
                        <div class="n">{{ $admitted }}</div>
                        <div class="l">Admitted</div>
                    </div>
                    <div class="tck-stat tck-stat--warn">
                        <div class="n">{{ $repeats }}</div>
                        <div class="l">Repeats</div>
                    </div>
                    <div class="tck-stat tck-stat--bad">
                        <div class="n">{{ $rejected }}</div>
                        <div class="l">Rejected</div>
                    </div>
                </div>

                <div class="tck-card">
                    <div class="tck-card-h">
                        <x-filament::icon icon="heroicon-m-hashtag" />
                        Enter code manually
                    </div>
                    <form wire:submit="submitManual" class="tck-manual">
                        <input type="text" wire:model="manualCode" placeholder="Ticket code" autofocus autocomplete="off" />
                        <button type="submit">
                            <x-filament::icon icon="heroicon-m-check" />
                            <span>Admit</span>
                        </button>
                    </form>
                </div>

                <div class="tck-card">
                    <div class="tck-card-h">
                        <x-filament::icon icon="heroicon-m-clock" />
                        Recent arrivals
                        <span class="cnt">{{ count($recent) }}</span>
                    </div>

                    @forelse ($recent as $r)
                        @php
                            $nm = trim((string) $r['name']) ?: 'Guest';
                            $hue = crc32($nm) % 360;
                            $ini = strtoupper(mb_substr($nm, 0, 1));
                        @endphp
                        <div class="tck-row">
                            <div class="tck-ini" style="background:hsl({{ $hue }} 52% 46%)">
                                {{ $ini }}
                                <span class="tck-badge {{ $r['ok'] ? 'tck-badge--ok' : 'tck-badge--warn' }}">
                                    <x-filament::icon icon="{{ $r['ok'] ? 'heroicon-m-check' : 'heroicon-m-exclamation-triangle' }}" />
                                </span>
                            </div>
                            <div class="tck-body">
                                <div class="tck-nm">
                                    {{ $r['name'] }}@if ($r['event'])<span class="tck-evt"> · {{ $r['event'] }}</span>@endif
                                </div>
                                <span class="tck-dt {{ $r['ok'] ? 'tck-dt--ok' : 'tck-dt--warn' }}">{{ $r['detail'] }}</span>
                            </div>
                            <span class="tck-at">{{ $r['at'] }}</span>
                        </div>
                    @empty
                        <div class="tck-empty">
                            <x-filament::icon icon="heroicon-o-inbox" />
                            <p>Scans will appear here as attendees arrive.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    @assets
        <script src="https://unpkg.com/html5-qrcode" defer></script>
    @endassets

    @script
    <script>
        let scanner = null;
        let lastCode = null;
        let lastAt = 0;
        let audioCtx = null;

        const stage    = document.getElementById('tck-stage');
        const startBtn = document.getElementById('qr-start');
        const stopBtn  = document.getElementById('qr-stop');
        const live     = document.getElementById('tck-live');
        const liveTxt  = document.getElementById('tck-live-txt');
        const flash    = document.getElementById('tck-flash');
        const banner   = document.getElementById('tck-banner');
        const bIc      = document.getElementById('tck-banner-ic');
        const bNm      = document.getElementById('tck-banner-nm');
        const bDt      = document.getElementById('tck-banner-dt');

        function setLive(on) {
            live.classList.toggle('on', on);
            liveTxt.textContent = on ? 'Scanning' : 'Camera off';
            stage.classList.toggle('scanning', on);
        }

        // WebAudio beep — distinct up-tone for pass, low buzz for fail. No asset needed.
        function beep(ok) {
            try {
                audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
                const o = audioCtx.createOscillator();
                const g = audioCtx.createGain();
                o.connect(g); g.connect(audioCtx.destination);
                o.type = ok ? 'sine' : 'square';
                o.frequency.value = ok ? 880 : 200;
                g.gain.setValueAtTime(0.001, audioCtx.currentTime);
                g.gain.exponentialRampToValueAtTime(0.25, audioCtx.currentTime + 0.01);
                g.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + (ok ? 0.18 : 0.32));
                o.start();
                o.stop(audioCtx.currentTime + (ok ? 0.19 : 0.33));
            } catch (e) { /* audio not available — silent */ }
        }

        // ── Haptics ───────────────────────────────────────────────
        // The Vibration API only fires after the page has a "user activation",
        // and a scan result arrives async (outside the tap), so we PRIME it on
        // the first user gesture, then fire distinct patterns on each result.
        const canVibrate = typeof navigator !== 'undefined' && 'vibrate' in navigator;
        let hapticsPrimed = false;
        function primeHaptics() {
            if (hapticsPrimed || !canVibrate) return;
            try { navigator.vibrate(1); hapticsPrimed = true; } catch (e) {}
        }
        function haptic(ok) {
            if (!canVibrate) return;
            // Crisp double-tap = admitted; long triple-buzz = rejected/repeat.
            try { navigator.vibrate(ok ? [35, 30, 35] : [90, 55, 90, 55, 90]); } catch (e) {}
        }
        // Prime on the very first interaction anywhere on the page.
        document.querySelector('.tck')?.addEventListener('pointerdown', primeHaptics, { once: true, passive: true });

        let bannerTimer = null;
        $wire.on('scan-feedback', (e) => {
            const ok = !!e.ok, name = e.name || 'Ticket', detail = e.detail || '';

            flash.className = 'tck-flash ' + (ok ? 'ok' : 'bad');
            void flash.offsetWidth;           // restart animation
            flash.classList.add('show');
            setTimeout(() => flash.classList.remove('show'), 720);

            beep(ok);
            haptic(ok);

            banner.className = 'tck-banner ' + (ok ? 'ok' : 'bad');
            bIc.innerHTML = ok
                ? '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>'
                : '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>';
            bNm.textContent = name;
            bDt.textContent = detail;
            requestAnimationFrame(() => banner.classList.add('show'));

            clearTimeout(bannerTimer);
            bannerTimer = setTimeout(() => banner.classList.remove('show'), 3200);
        });

        function onDecoded(text) {
            const now = Date.now();
            if (text === lastCode && (now - lastAt) < 3000) return;   // ignore repeat frames
            lastCode = text;
            lastAt = now;
            $wire.scan(text);
        }

        startBtn?.addEventListener('click', () => {
            primeHaptics();
            if (typeof Html5Qrcode === 'undefined') {
                alert('Scanner library still loading — try again in a moment.');
                return;
            }
            if (scanner) return;
            scanner = new Html5Qrcode('qr-reader');
            Html5Qrcode.getCameras().then((cameras) => {
                if (!cameras || cameras.length === 0) {
                    alert('No camera found.');
                    scanner = null;
                    return;
                }
                scanner.start(
                    { facingMode: 'environment' },
                    { fps: 10, qrbox: 250 },
                    onDecoded,
                    () => {} // ignore per-frame decode failures
                ).then(() => setLive(true)).catch((err) => {
                    alert('Could not start camera: ' + err + '\nCamera needs HTTPS.');
                    scanner = null;
                });
            }).catch((err) => {
                alert('Camera access failed: ' + err + '\nCamera needs HTTPS.');
                scanner = null;
            });
        });

        stopBtn?.addEventListener('click', () => {
            if (scanner) {
                scanner.stop().finally(() => { scanner = null; setLive(false); });
            }
        });
    </script>
    @endscript
</x-filament-panels::page>
