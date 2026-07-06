<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Booking report</x-slot>
        <x-slot name="description">Download a CSV of all bookings across your events and venues for a date range.</x-slot>

        <div class="flex flex-wrap items-end gap-4">
            <label class="text-sm">
                <span class="mb-1 block font-medium text-gray-700 dark:text-gray-200">From</span>
                <input type="date" wire:model.live="from"
                       class="block rounded-lg border-gray-300 shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
            </label>

            <label class="text-sm">
                <span class="mb-1 block font-medium text-gray-700 dark:text-gray-200">To</span>
                <input type="date" wire:model.live="to"
                       class="block rounded-lg border-gray-300 shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
            </label>

            <x-filament::button wire:click="download" icon="heroicon-o-arrow-down-tray">
                Download CSV
            </x-filament::button>
        </div>

        <p class="mt-4 text-sm text-gray-500">
            {{ $this->rowCount() }} booking(s) in the selected range.
        </p>
    </x-filament::section>
</x-filament-panels::page>
