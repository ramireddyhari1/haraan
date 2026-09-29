{{-- A GameHub page made of summary panels (see Concerns\SummarisesVenues).
     Every figure is read from the database by the page's getPanels(). --}}
<x-filament-panels::page>
    <div style="display: grid; gap: 16px">
        @foreach ($this->getPanels() as $panel)
            <x-summary-panel :s="$panel" />
        @endforeach
    </div>
</x-filament-panels::page>
