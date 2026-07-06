<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">{{ $this->getRecord()->title }}</x-slot>
        <x-slot name="description">
            {{ $this->getRecord()->venue ?: $this->getRecord()->location }}
            @if ($this->getRecord()->date)
                · {{ $this->getRecord()->date->format('D, d M Y') }}
            @endif
        </x-slot>

        <div class="flex flex-wrap gap-3">
            <x-filament::button
                tag="a"
                color="gray"
                icon="heroicon-m-pencil-square"
                href="{{ \App\Filament\Resources\Events\EventResource::getUrl('edit', ['record' => $this->getRecord()]) }}"
            >
                Edit event
            </x-filament::button>
            <x-filament::button
                tag="a"
                color="gray"
                icon="heroicon-m-calendar-days"
                href="{{ \App\Filament\Resources\Bookings\BookingResource::getUrl('index') }}"
            >
                View bookings
            </x-filament::button>
        </div>
    </x-filament::section>
</x-filament-panels::page>
