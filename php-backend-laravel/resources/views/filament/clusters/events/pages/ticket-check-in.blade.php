<x-filament-panels::page>
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
                The camera needs a secure (HTTPS) connection. Over a plain <code>http://</code> panel the browser
                blocks it — use manual entry on the right until the panel has TLS.
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
                    <div class="flex items-center justify-between border-b border-gray-100 py-2 text-sm last:border-0 dark:border-gray-800">
                        <div>
                            <span class="font-medium text-gray-950 dark:text-white">{{ $r['name'] }}</span>
                            @if ($r['event'])
                                <span class="text-gray-400 dark:text-gray-500">· {{ $r['event'] }}</span>
                            @endif
                            <div class="text-xs {{ $r['ok'] ? 'text-green-600' : 'text-amber-600' }}">{{ $r['detail'] }}</div>
                        </div>
                        <span class="text-xs text-gray-400">{{ $r['at'] }}</span>
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
