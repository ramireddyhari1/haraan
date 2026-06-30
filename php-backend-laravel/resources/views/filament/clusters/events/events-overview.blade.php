<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Events workspace</x-slot>
        <x-slot name="description">Events and their bookings. Track sell-through, manage seat capacity, confirm or cancel bookings.</x-slot>

        <div class="flex flex-wrap gap-3">
            <x-filament::button tag="a" href="{{ \App\Filament\Resources\Events\EventResource::getUrl('index') }}" icon="heroicon-m-ticket">
                Manage Events
            </x-filament::button>
            <x-filament::button tag="a" color="gray" href="{{ \App\Filament\Resources\Bookings\BookingResource::getUrl('index') }}" icon="heroicon-m-calendar-days">
                View Bookings
            </x-filament::button>
        </div>
    </x-filament::section>
</x-filament-panels::page>
