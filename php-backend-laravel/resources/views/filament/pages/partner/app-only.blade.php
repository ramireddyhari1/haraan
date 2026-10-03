<x-filament-panels::page>
    {{-- On a phone, app-tools.js replaces this with the partner app's screen. --}}
    <div style="max-width:28rem;padding:1.5rem;border:1px solid rgba(15,23,42,.08);border-radius:1rem;background:#fff">
        <p style="margin:0;font-weight:600;color:#0f172a">{{ $this->getTitle() }} runs on your phone.</p>
        <p style="margin:.4rem 0 0;font-size:.875rem;color:#64748b">
            Open haraan.app/partner on your phone, or the Haraan Partner app, and find it in the menu.
        </p>
    </div>
</x-filament-panels::page>
