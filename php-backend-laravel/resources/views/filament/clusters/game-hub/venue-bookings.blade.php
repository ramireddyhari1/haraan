<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Venue + date controls --}}
        <div class="flex flex-wrap items-end gap-4">
            <label class="text-sm">
                <span class="mb-1 block font-medium text-gray-700 dark:text-gray-200">Venue</span>
                <select wire:model.live="venueId"
                        class="block w-56 rounded-lg border-gray-300 shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    @foreach ($this->venueOptions() as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </label>

            <label class="text-sm">
                <span class="mb-1 block font-medium text-gray-700 dark:text-gray-200">Date</span>
                <input type="date" wire:model.live="date"
                       class="block rounded-lg border-gray-300 shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
            </label>

            <x-filament::button wire:click="toggleClosed" :color="$this->isBlocked() ? 'success' : 'gray'">
                {{ $this->isBlocked() ? 'Reopen day' : 'Close day' }}
            </x-filament::button>
        </div>

        @if ($this->isBlocked())
            <x-filament::section>
                <p class="text-danger-600 font-medium">This day is closed (maintenance / holiday). Reopen it to take bookings.</p>
            </x-filament::section>
        @else
            {{-- Add walk-in booking --}}
            <x-filament::section>
                <x-slot name="heading">Add walk-in booking</x-slot>

                <div class="flex flex-wrap items-end gap-3">
                    <select wire:model="formSlotId"
                            class="block w-64 rounded-lg border-gray-300 shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                        <option value="">Select slot…</option>
                        @foreach ($this->openSlotOptions() as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>

                    <input type="text" wire:model="guestName" placeholder="Customer name"
                           class="block rounded-lg border-gray-300 shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    <input type="text" wire:model="guestPhone" placeholder="Phone (optional)"
                           class="block rounded-lg border-gray-300 shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">

                    <x-filament::button wire:click="addWalkIn">Book</x-filament::button>
                </div>
            </x-filament::section>
        @endif

        {{-- Slot grid --}}
        <div class="space-y-3">
            @forelse ($this->slots() as $slot)
                <x-filament::section>
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="font-semibold text-gray-900 dark:text-white">{{ $slot['time'] ?: $slot['label'] }}</div>
                            @if ($slot['price'] > 0)
                                <div class="text-sm text-gray-500">₹{{ number_format($slot['price']) }}</div>
                            @endif
                        </div>
                        <div class="text-lg font-bold {{ $slot['available'] <= 0 ? 'text-danger-600' : 'text-success-600' }}">
                            {{ $slot['booked'] }}/{{ $slot['capacity'] }}
                        </div>
                    </div>

                    @foreach ($slot['bookings'] as $b)
                        <div class="mt-3 flex items-center justify-between border-t border-gray-100 pt-3 text-sm dark:border-gray-700">
                            <div>
                                <span class="font-medium text-gray-900 dark:text-white">{{ $b['customer'] }}</span>
                                <span class="text-gray-500">
                                    · {{ $b['channel'] === 'offline' ? 'Walk-in' : 'Online' }}{{ $b['checked_in'] > 0 ? ' · checked in' : '' }}
                                </span>
                            </div>
                            <x-filament::button size="xs" color="danger" wire:click="cancelBooking({{ $b['id'] }})">
                                Cancel
                            </x-filament::button>
                        </div>
                    @endforeach
                </x-filament::section>
            @empty
                <x-filament::section>
                    <p class="text-gray-500">No slots configured for this venue.</p>
                </x-filament::section>
            @endforelse
        </div>
    </div>
</x-filament-panels::page>
