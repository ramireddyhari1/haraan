<x-filament-panels::page>
    {{-- Quick links use the panel-wide .hrn-* design system. --}}
    <section>
        <div class="hrn-sec-h"><h2>Manage</h2></div>
        <div class="hrn-radar">
            <a href="{{ \App\Filament\Resources\Venues\VenueResource::getUrl('index') }}" class="hrn-row"
               style="--_dot:var(--hrn-ok-strong);--_tile:var(--hrn-ok-bg);--_text:var(--hrn-ok);">
                <div class="hrn-tile"><x-filament::icon icon="heroicon-o-map-pin" /></div>
                <div class="hrn-row-main">
                    <div class="hrn-row-t">Manage venues</div>
                    <div class="hrn-row-s">Toggle info-only vs bookable, manage slots, courts and reviews.</div>
                </div>
                <span style="color:var(--hrn-ink-3);flex:none;"><x-filament::icon icon="heroicon-m-chevron-right" style="width:18px;height:18px;" /></span>
            </a>
            <a href="{{ \App\Filament\Resources\Venues\VenueResource::getUrl('create') }}" class="hrn-row"
               style="--_dot:#3b82f6;--_tile:rgba(59,130,246,.12);--_text:#2563eb;">
                <div class="hrn-tile"><x-filament::icon icon="heroicon-o-plus-circle" /></div>
                <div class="hrn-row-main">
                    <div class="hrn-row-t">Add a venue</div>
                    <div class="hrn-row-s">Onboard a new turf, court or ground to the GameHub catalog.</div>
                </div>
                <span style="color:var(--hrn-ink-3);flex:none;"><x-filament::icon icon="heroicon-m-chevron-right" style="width:18px;height:18px;" /></span>
            </a>
        </div>
    </section>
</x-filament-panels::page>
