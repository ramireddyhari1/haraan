<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Status banner --}}
        @if ($configured && $enabled)
            <x-filament::section>
                <div class="flex items-center gap-3">
                    <x-heroicon-o-check-circle class="h-8 w-8 text-success-500" />
                    <div>
                        <p class="text-base font-semibold text-success-600">Twilio WhatsApp is live</p>
                        <p class="text-sm text-gray-500">Sending from <strong>{{ $from ?? '—' }}</strong>. Ticket delivery and any WhatsApp messages go out through Twilio.</p>
                    </div>
                </div>
            </x-filament::section>
        @elseif ($configured && ! $enabled)
            <x-filament::section>
                <div class="flex items-center gap-3">
                    <x-heroicon-o-pause-circle class="h-8 w-8 text-warning-500" />
                    <div>
                        <p class="text-base font-semibold text-warning-600">Configured but turned off</p>
                        <p class="text-sm text-gray-500">Credentials are set, but <code>TWILIO_WHATSAPP_ENABLED</code> is false — no messages are sent. Set it to <code>true</code> to go live.</p>
                    </div>
                </div>
            </x-filament::section>
        @else
            <x-filament::section>
                <div class="flex items-center gap-3">
                    <x-heroicon-o-exclamation-triangle class="h-8 w-8 text-danger-500" />
                    <div>
                        <p class="text-base font-semibold text-danger-600">Not configured</p>
                        <p class="text-sm text-gray-500">Set <code>TWILIO_ACCOUNT_SID</code>, an API key (or auth token), and <code>TWILIO_WHATSAPP_FROM</code> in the server environment.</p>
                    </div>
                </div>
            </x-filament::section>
        @endif

        {{-- Test send --}}
        <x-filament::section heading="Send a test message" description="Fire a WhatsApp message through Twilio to confirm the sender and credentials.">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="flex-1">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Recipient number</label>
                    <input type="tel" wire:model="testNumber" placeholder="e.g. 9876543210 or +919876543210"
                           class="mt-1 block w-full rounded-lg border-gray-300 dark:border-white/10 dark:bg-gray-900 text-sm" />
                </div>
                <x-filament::button wire:click="sendTest" wire:loading.attr="disabled" icon="heroicon-m-paper-airplane"
                    :disabled="! $configured || ! $enabled">
                    Send test
                </x-filament::button>
            </div>
            <p class="mt-3 text-xs text-gray-400">
                Note: for business-initiated messages, the recipient must have messaged your number in the last 24h,
                or you must use an approved WhatsApp template. Delivery failures are logged.
            </p>
        </x-filament::section>

        {{-- Diagnostics --}}
        <x-filament::section collapsible collapsed heading="Details">
            <dl class="grid grid-cols-2 gap-3 text-sm">
                <dt class="text-gray-500">Provider</dt>
                <dd class="font-medium">Twilio</dd>
                <dt class="text-gray-500">Sender</dt>
                <dd class="font-medium">{{ $from ?? '—' }}</dd>
                <dt class="text-gray-500">Credentials</dt>
                <dd class="font-medium">{{ $configured ? 'Configured' : 'Missing' }}</dd>
                <dt class="text-gray-500">Enabled</dt>
                <dd class="font-medium">{{ $enabled ? 'Yes' : 'No' }}</dd>
            </dl>
        </x-filament::section>
    </div>
</x-filament-panels::page>
