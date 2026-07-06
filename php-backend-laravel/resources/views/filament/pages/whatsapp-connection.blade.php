<x-filament-panels::page>
    {{-- Polls the bridge every 5s so status + QR stay live without a manual refresh. --}}
    <div wire:poll.5s="refreshStatus" class="space-y-6">

        {{-- Status banner --}}
        @if (! $reachable)
            <x-filament::section>
                <div class="flex items-center gap-3">
                    <x-heroicon-o-exclamation-triangle class="h-8 w-8 text-danger-500" />
                    <div>
                        <p class="text-base font-semibold text-danger-600">Bridge unreachable</p>
                        <p class="text-sm text-gray-500">The WhatsApp bridge service isn't responding. It may be restarting — the watchdog auto-restarts it within a couple of minutes.</p>
                    </div>
                </div>
            </x-filament::section>
        @elseif ($ready)
            <x-filament::section>
                <div class="flex items-center gap-3">
                    <x-heroicon-o-check-circle class="h-8 w-8 text-success-500" />
                    <div>
                        <p class="text-base font-semibold text-success-600">Connected — OTP is sending normally</p>
                        <p class="text-sm text-gray-500">WhatsApp is linked. Users receive their login codes.</p>
                    </div>
                </div>
            </x-filament::section>
        @else
            <x-filament::section>
                <div class="space-y-5">
                    <div class="flex items-center gap-3">
                        <x-heroicon-o-qr-code class="h-8 w-8 text-warning-500" />
                        <div>
                            <p class="text-base font-semibold text-warning-600">Disconnected — scan to reconnect</p>
                            <p class="text-sm text-gray-500">OTP is <strong>not</strong> sending. Open WhatsApp on the OTP phone ▸ <strong>Settings ▸ Linked Devices ▸ Link a device</strong>, then scan the code below.</p>
                        </div>
                    </div>

                    @if ($qr)
                        <div class="flex justify-center">
                            <img src="{{ $qr }}" alt="WhatsApp QR" class="h-64 w-64 rounded-lg border border-gray-200 bg-white p-2 shadow-sm" />
                        </div>
                        <p class="text-center text-xs text-gray-400">The QR refreshes automatically every few seconds. Once you scan, this page flips to “Connected”.</p>
                    @else
                        <p class="text-center text-sm text-gray-500">Waiting for the bridge to generate a QR…</p>
                    @endif
                </div>
            </x-filament::section>
        @endif

        {{-- Diagnostics --}}
        <x-filament::section collapsible collapsed heading="Diagnostics">
            <dl class="grid grid-cols-2 gap-3 text-sm">
                <dt class="text-gray-500">Last event</dt>
                <dd class="font-medium">{{ $event ?? '—' }}</dd>
                <dt class="text-gray-500">Updated at</dt>
                <dd class="font-medium">{{ $at ?? '—' }}</dd>
            </dl>
        </x-filament::section>
    </div>
</x-filament-panels::page>
