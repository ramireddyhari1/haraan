<x-filament-panels::page>
    {{-- Slim quick-actions toolbar. The overview content (upcoming events +
         recent bookings) is carried by the header/footer widgets around this
         view, so this stays a compact strip rather than being the whole page. --}}
    <style>
        .eo-actions{display:flex;flex-wrap:wrap;gap:10px;}
        .eo-chip{display:inline-flex;align-items:center;gap:8px;padding:9px 15px;border-radius:11px;
            border:1px solid var(--hrn-border);background:var(--hrn-surface);color:var(--hrn-ink);
            font-size:13px;font-weight:600;text-decoration:none;box-shadow:var(--hrn-shadow);
            transition:transform .15s,border-color .15s,box-shadow .15s;}
        .eo-chip:hover{transform:translateY(-1px);border-color:var(--_ac,var(--hrn-ok-strong));
            box-shadow:var(--hrn-shadow-hover);}
        .eo-chip svg{width:16px;height:16px;color:var(--_ac,var(--hrn-ink-2));}
    </style>

    <div class="eo-actions">
        <a href="{{ \App\Filament\Resources\Events\EventResource::getUrl('index') }}" class="eo-chip"
           style="--_ac:var(--hrn-ok-strong);">
            <x-filament::icon icon="heroicon-m-ticket" />
            Manage events
        </a>
        <a href="{{ \App\Filament\Resources\Bookings\BookingResource::getUrl('index') }}" class="eo-chip"
           style="--_ac:#3b82f6;">
            <x-filament::icon icon="heroicon-m-calendar-days" />
            View bookings
        </a>
    </div>
</x-filament-panels::page>
