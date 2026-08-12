<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Status banner --}}
        @if ($configured && $enabled)
            <x-filament::section>
                <div class="flex items-center gap-3">
                    <x-heroicon-o-check-circle class="h-8 w-8 text-success-500" />
                    <div>
                        <p class="text-base font-semibold text-success-600">WhatsApp Cloud API is live</p>
                        <p class="text-sm text-gray-500">Sending from phone number id <strong>{{ $from ?? '—' }}</strong>. Ticket delivery and every WhatsApp message goes out through Meta.</p>
                    </div>
                </div>
            </x-filament::section>
        @elseif ($configured && ! $enabled)
            <x-filament::section>
                <div class="flex items-center gap-3">
                    <x-heroicon-o-pause-circle class="h-8 w-8 text-warning-500" />
                    <div>
                        <p class="text-base font-semibold text-warning-600">Configured but turned off</p>
                        <p class="text-sm text-gray-500">Credentials are set, but <code>META_WHATSAPP_ENABLED</code> is false — no messages are sent. Set it to <code>true</code> to go live.</p>
                    </div>
                </div>
            </x-filament::section>
        @else
            <x-filament::section>
                <div class="flex items-center gap-3">
                    <x-heroicon-o-exclamation-triangle class="h-8 w-8 text-danger-500" />
                    <div>
                        <p class="text-base font-semibold text-danger-600">Not configured</p>
                        <p class="text-sm text-gray-500">Set <code>META_WHATSAPP_PHONE_NUMBER_ID</code> and <code>META_WHATSAPP_TOKEN</code> in the server environment.</p>
                    </div>
                </div>
            </x-filament::section>
        @endif

        {{-- There is no SMS fallback any more: Meta does WhatsApp, not SMS. Say so
             plainly, because "why didn't they get a text?" is the obvious question. --}}
        <x-filament::section>
            <div class="flex items-center gap-3">
                <x-heroicon-o-envelope class="h-8 w-8 text-gray-400" />
                <div>
                    <p class="text-base font-semibold text-gray-600 dark:text-gray-300">No SMS fallback</p>
                    <p class="text-sm text-gray-500">
                        Meta doesn't do SMS. If a WhatsApp send fails, the customer's copy of the
                        ticket is the <strong>email</strong>, which is sent independently and never
                        depends on WhatsApp succeeding.
                    </p>
                </div>
            </div>
        </x-filament::section>

        {{-- Test send --}}
        <x-filament::section heading="Send a test message" description="Fire a message through the Cloud API to confirm the number and token.">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="flex-1">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Recipient number</label>
                    <input type="tel" wire:model="testNumber" placeholder="e.g. 9876543210 or +919876543210"
                           class="mt-1 block w-full rounded-lg border-gray-300 dark:border-white/10 dark:bg-gray-900 text-sm" />
                </div>
                <x-filament::button wire:click="sendTest" wire:loading.attr="disabled" icon="heroicon-m-chat-bubble-left-right"
                    color="success" :disabled="! $configured || ! $enabled">
                    Test WhatsApp
                </x-filament::button>
            </div>
            <p class="mt-3 text-xs text-gray-400">
                A plain text message only works if the recipient messaged you in the last 24 hours.
                Outside that window Meta accepts approved templates only — see Platform → Templates.
            </p>
        </x-filament::section>

        {{-- Diagnostics --}}
        <x-filament::section collapsible collapsed heading="Details">
            <dl class="grid grid-cols-2 gap-3 text-sm">
                <dt class="text-gray-500">Provider</dt>
                <dd class="font-medium">Meta WhatsApp Cloud API</dd>
                <dt class="text-gray-500">Phone number id</dt>
                <dd class="font-medium">{{ $from ?? '—' }}</dd>
                <dt class="text-gray-500">Credentials</dt>
                <dd class="font-medium">{{ $configured ? 'Configured' : 'Missing' }}</dd>
                <dt class="text-gray-500">Enabled</dt>
                <dd class="font-medium">{{ $enabled ? 'Yes' : 'No' }}</dd>
                <dt class="text-gray-500">Inbound webhook</dt>
                <dd class="font-medium">/api/webhooks/meta</dd>
            </dl>
        </x-filament::section>
    </div>
</x-filament-panels::page>
