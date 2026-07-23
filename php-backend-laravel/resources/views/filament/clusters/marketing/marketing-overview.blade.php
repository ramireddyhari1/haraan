<x-filament-panels::page>
    {{-- Quick links use the panel-wide .hrn-* design system. --}}
    <section>
        <div class="hrn-sec-h"><h2>Manage</h2></div>
        <div class="hrn-radar">
            <a href="{{ \App\Filament\Resources\Ads\AdResource::getUrl('index') }}" class="hrn-row"
               style="--_dot:#3b82f6;--_tile:rgba(59,130,246,.12);--_text:#2563eb;">
                <div class="hrn-tile"><x-filament::icon icon="heroicon-o-megaphone" /></div>
                <div class="hrn-row-main">
                    <div class="hrn-row-t">Manage ads</div>
                    <div class="hrn-row-s">Home-feed banners, login posters and sponsored placements.</div>
                </div>
                <span style="color:var(--hrn-ink-3);flex:none;"><x-filament::icon icon="heroicon-m-chevron-right" style="width:18px;height:18px;" /></span>
            </a>
            <a href="{{ \App\Filament\Resources\FeedItems\FeedItemResource::getUrl('index') }}" class="hrn-row"
               style="--_dot:#8b5cf6;--_tile:rgba(139,92,246,.12);--_text:#7c3aed;">
                <div class="hrn-tile"><x-filament::icon icon="heroicon-o-squares-2x2" /></div>
                <div class="hrn-row-main">
                    <div class="hrn-row-t">Manage feed</div>
                    <div class="hrn-row-s">For You / Trending cards surfaced in the app home.</div>
                </div>
                <span style="color:var(--hrn-ink-3);flex:none;"><x-filament::icon icon="heroicon-m-chevron-right" style="width:18px;height:18px;" /></span>
            </a>
            <a href="{{ \App\Filament\Resources\Coupons\CouponResource::getUrl('index') }}" class="hrn-row"
               style="--_dot:var(--hrn-warn-strong);--_tile:var(--hrn-warn-bg);--_text:var(--hrn-warn);">
                <div class="hrn-tile"><x-filament::icon icon="heroicon-o-tag" /></div>
                <div class="hrn-row-main">
                    <div class="hrn-row-t">Manage coupons</div>
                    <div class="hrn-row-s">Discount codes for campaigns and promotions.</div>
                </div>
                <span style="color:var(--hrn-ink-3);flex:none;"><x-filament::icon icon="heroicon-m-chevron-right" style="width:18px;height:18px;" /></span>
            </a>
        </div>
    </section>
</x-filament-panels::page>
