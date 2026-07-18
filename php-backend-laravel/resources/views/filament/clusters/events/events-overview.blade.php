<x-filament-panels::page>
    {{-- Shared design system (.hrn-*) is injected panel-wide via the theme render hook. --}}
    <div style="display:flex;flex-direction:column;gap:22px;">
        <section>
            <div class="hrn-sec-h"><h2>Manage</h2></div>
            <div class="hrn-radar">
                <a href="{{ \App\Filament\Resources\Events\EventResource::getUrl('index') }}" class="hrn-row"
                   style="--_dot:var(--hrn-ok-strong);--_tile:var(--hrn-ok-bg);--_text:var(--hrn-ok);">
                    <div class="hrn-tile"><x-filament::icon icon="heroicon-o-ticket" /></div>
                    <div class="hrn-row-main">
                        <div class="hrn-row-t">Manage events</div>
                        <div class="hrn-row-s">Create, edit and publish events, tiers and seat capacity.</div>
                    </div>
                    <span style="color:var(--hrn-ink-3);flex:none;">
                        <x-filament::icon icon="heroicon-m-chevron-right" style="width:18px;height:18px;" />
                    </span>
                </a>
                <a href="{{ \App\Filament\Resources\Bookings\BookingResource::getUrl('index') }}" class="hrn-row"
                   style="--_dot:#3b82f6;--_tile:rgba(59,130,246,.12);--_text:#2563eb;">
                    <div class="hrn-tile"><x-filament::icon icon="heroicon-o-calendar-days" /></div>
                    <div class="hrn-row-main">
                        <div class="hrn-row-t">View bookings</div>
                        <div class="hrn-row-s">Confirm or cancel bookings and track sell-through.</div>
                    </div>
                    <span style="color:var(--hrn-ink-3);flex:none;">
                        <x-filament::icon icon="heroicon-m-chevron-right" style="width:18px;height:18px;" />
                    </span>
                </a>
            </div>
        </section>
    </div>
</x-filament-panels::page>
