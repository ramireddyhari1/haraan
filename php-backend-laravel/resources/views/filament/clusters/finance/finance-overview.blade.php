<x-filament-panels::page>
    {{-- KPIs render via getHeaderWidgets(); quick links below use the panel-wide .hrn-* design system. --}}
    <section>
        <div class="hrn-sec-h"><h2>Manage</h2></div>
        <div class="hrn-radar">
            <a href="{{ \App\Filament\Resources\Payouts\PayoutResource::getUrl('index') }}" class="hrn-row"
               style="--_dot:var(--hrn-ok-strong);--_tile:var(--hrn-ok-bg);--_text:var(--hrn-ok);">
                <div class="hrn-tile"><x-filament::icon icon="heroicon-o-banknotes" /></div>
                <div class="hrn-row-main">
                    <div class="hrn-row-t">Manage payouts</div>
                    <div class="hrn-row-s">Process settlements and track revenue owed to hosts and venues.</div>
                </div>
                <span style="color:var(--hrn-ink-3);flex:none;"><x-filament::icon icon="heroicon-m-chevron-right" style="width:18px;height:18px;" /></span>
            </a>
            <a href="{{ \App\Filament\Resources\Coupons\CouponResource::getUrl('index') }}" class="hrn-row"
               style="--_dot:var(--hrn-warn-strong);--_tile:var(--hrn-warn-bg);--_text:var(--hrn-warn);">
                <div class="hrn-tile"><x-filament::icon icon="heroicon-o-tag" /></div>
                <div class="hrn-row-main">
                    <div class="hrn-row-t">Manage coupons</div>
                    <div class="hrn-row-s">Create and track discount codes across events and venues.</div>
                </div>
                <span style="color:var(--hrn-ink-3);flex:none;"><x-filament::icon icon="heroicon-m-chevron-right" style="width:18px;height:18px;" /></span>
            </a>
        </div>
    </section>
</x-filament-panels::page>
