<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Marketing workspace</x-slot>
        <x-slot name="description">Promotions across the app: home-feed ads, For You / Trending cards, and discount coupons.</x-slot>

        <div class="flex flex-wrap gap-3">
            <x-filament::button tag="a" href="{{ \App\Filament\Resources\Ads\AdResource::getUrl('index') }}" icon="heroicon-m-megaphone">
                Manage Ads
            </x-filament::button>
            <x-filament::button tag="a" color="gray" href="{{ \App\Filament\Resources\FeedItems\FeedItemResource::getUrl('index') }}" icon="heroicon-m-squares-2x2">
                Manage Feed
            </x-filament::button>
            <x-filament::button tag="a" color="gray" href="{{ \App\Filament\Resources\Coupons\CouponResource::getUrl('index') }}" icon="heroicon-m-tag">
                Manage Coupons
            </x-filament::button>
        </div>
    </x-filament::section>
</x-filament-panels::page>
