<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">{{ $this->getRecord()->name }}</x-slot>
        <x-slot name="description">
            {{ $this->getRecord()->location }}
            @if ($this->getRecord()->category)
                · {{ $this->getRecord()->category }}
            @endif
        </x-slot>

        <div class="flex flex-wrap gap-3">
            <x-filament::button
                tag="a"
                color="gray"
                icon="heroicon-m-pencil-square"
                href="{{ \App\Filament\Resources\Venues\VenueResource::getUrl('edit', ['record' => $this->getRecord()]) }}"
            >
                Edit venue
            </x-filament::button>
            <x-filament::button
                tag="a"
                color="gray"
                icon="heroicon-m-qr-code"
                href="{{ \App\Filament\Clusters\Events\Pages\TicketCheckIn::getUrl() }}"
            >
                Check-in scanner
            </x-filament::button>
        </div>
    </x-filament::section>
</x-filament-panels::page>
