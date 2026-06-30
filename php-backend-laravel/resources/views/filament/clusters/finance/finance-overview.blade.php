<x-filament-panels::page>
    {{-- KPIs render via getHeaderWidgets(); quick links below. --}}
    <x-filament::section>
        <x-slot name="heading">Finance workspace</x-slot>
        <x-slot name="description">Payouts and coupons for the business. Process settlements, track revenue, manage discounts.</x-slot>

        <div class="flex flex-wrap gap-3">
            <x-filament::button tag="a" href="{{ \App\Filament\Resources\Payouts\PayoutResource::getUrl('index') }}" icon="heroicon-m-banknotes">
                Manage Payouts
            </x-filament::button>
            <x-filament::button tag="a" color="gray" href="{{ \App\Filament\Resources\Coupons\CouponResource::getUrl('index') }}" icon="heroicon-m-tag">
                Manage Coupons
            </x-filament::button>
        </div>
    </x-filament::section>
</x-filament-panels::page>
