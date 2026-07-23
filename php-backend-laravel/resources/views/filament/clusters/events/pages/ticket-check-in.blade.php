<x-filament-panels::page>
    {{-- Scoped styling only — reuses the panel-wide --hrn-* tokens. The scanner
         markup and all JS below are untouched. --}}
    <style>
        .tc-row{display:flex;align-items:center;gap:11px;padding:10px 0;border-bottom:1px solid var(--hrn-border);}
        .tc-row:last-child{border-bottom:0;}
        .tc-ini{width:32px;height:32px;border-radius:50%;flex:none;display:flex;align-items:center;
            justify-content:center;color:#fff;font-size:12.5px;font-weight:700;}
        .tc-main{flex:1;min-width:0;}
        .tc-name{font-size:13.5px;font-weight:600;color:var(--hrn-ink);}
        .tc-evt{font-weight:400;color:var(--hrn-ink-3);}
        .tc-detail{display:inline-block;margin-top:3px;font-size:11px;font-weight:600;
            padding:2px 8px;border-radius:999px;}
        .tc-detail--ok{background:var(--hrn-ok-bg);color:var(--hrn-ok);}
        .tc-detail--warn{background:var(--hrn-warn-bg);color:var(--hrn-warn);}
        .tc-at{font-size:11px;color:var(--hrn-ink-3);white-space:nowrap;}
        .tc-lock{display:flex;align-items:center;gap:10px;margin-bottom:1rem;padding:11px 14px;
            border-radius:12px;background:#eef4ff;border:1px solid #d3e0fb;color:#1e50e6;
            font-size:13px;font-weight:600;}
        .tc-lock-ic{width:18px;height:18px;flex:none;}
        .tc-lock strong{font-weight:800;}
        .tc-lock-clear{margin-left:auto;white-space:nowrap;color:#1e50e6;font-weight:700;
            text-decoration:underline;}
    </style>

    @if ($event && $lockedTitle)
        <div class="tc-lock">
            <x-filament::icon icon="heroicon-o-lock-closed" class="tc-lock-ic" />
            <span>Locked to <strong>{{ $lockedTitle }}</strong> — only this event's tickets will check in.</span>
            <a href="{{ \App\Filament\Clusters\Events\Pages\TicketCheckIn::getUrl() }}" class="tc-lock-clear">Scan all events</a>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Camera scanner --}}
        <x-filament::section>
            <x-slot name="heading">Scan ticket QR</x-slot>
            <x-slot name="description">Point your camera at the attendee's ticket QR to check them in.</x-slot>

            <div wire:ignore>
                <div id="qr-reader" class="w-full overflow-hidden rounded-xl bg-gray-950"></div>
            </div>

            <div class="mt-3 flex gap-2">
                <x-filament::button id="qr-start" icon="heroicon-m-camera">Start camera</x-filament::button>
                <x-filament::button id="qr-stop" color="gray" icon="heroicon-m-stop">Stop</x-filament::button>
            </div>

            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                The browser only allows the camera over a secure (HTTPS) connection — which this panel has.
                If your device blocks camera access, use manual entry on the right.
            </p>
        </x-filament::section>

        {{-- Manual entry + recent scans --}}
        <x-filament::section>
            <x-slot name="heading">Enter code manually</x-slot>

            <form wire:submit="submitManual" class="flex items-center gap-2">
                <x-filament::input.wrapper class="flex-1">
                    <x-filament::input type="text" wire:model="manualCode" placeholder="Ticket code" autofocus />
                </x-filament::input.wrapper>
                <x-filament::button type="submit" icon="heroicon-m-check">Check in</x-filament::button>
            </form>

            <div class="mt-6">
                <h3 class="mb-2 text-sm font-semibold text-gray-950 dark:text-white">Recent</h3>
                @forelse ($recent as $r)
                    @php
                        $nm = trim((string) $r['name']) ?: 'Guest';
                        $hue = crc32($nm) % 360;
                        $ini = strtoupper(mb_substr($nm, 0, 1));
                    @endphp
                    <div class="tc-row">
                        <div class="tc-ini" style="background:hsl({{ $hue }} 52% 46%)">{{ $ini }}</div>
                        <div class="tc-main">
                            <div class="tc-name">
                                {{ $r['name'] }}@if ($r['event'])<span class="tc-evt"> · {{ $r['event'] }}</span>@endif
                            </div>
                            <span class="tc-detail {{ $r['ok'] ? 'tc-detail--ok' : 'tc-detail--warn' }}">{{ $r['detail'] }}</span>
                        </div>
                        <span class="tc-at">{{ $r['at'] }}</span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400">Scans will appear here.</p>
                @endforelse
            </div>
        </x-filament::section>
    </div>

    @assets
        <script src="https://unpkg.com/html5-qrcode" defer></script>
    @endassets

    @script
    <script>
        let scanner = null;
        let lastCode = null;
        let lastAt = 0;

        const startBtn = document.getElementById('qr-start');
        const stopBtn = document.getElementById('qr-stop');

        function onDecoded(text) {
            const now = Date.now();
            // Ignore repeated frames of the same code within 3s.
            if (text === lastCode && (now - lastAt) < 3000) return;
            lastCode = text;
            lastAt = now;
            $wire.scan(text);
        }

        startBtn?.addEventListener('click', () => {
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
                ).catch((err) => {
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
                scanner.stop().finally(() => { scanner = null; });
            }
        });
    </script>
    @endscript
</x-filament-panels::page>
