<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">GameHub workspace</x-slot>
        <x-slot name="description">Venues, their slots and reviews. Toggle info-only vs bookable, manage availability, moderate reviews.</x-slot>

        <div class="flex flex-wrap gap-3">
            <x-filament::button tag="a" href="{{ \App\Filament\Resources\Venues\VenueResource::getUrl('index') }}" icon="heroicon-m-map-pin">
                Manage Venues
            </x-filament::button>
            <x-filament::button tag="a" color="gray" href="{{ \App\Filament\Resources\Venues\VenueResource::getUrl('create') }}" icon="heroicon-m-plus">
                Add Venue
            </x-filament::button>
        </div>
    </x-filament::section>
</x-filament-panels::page>
