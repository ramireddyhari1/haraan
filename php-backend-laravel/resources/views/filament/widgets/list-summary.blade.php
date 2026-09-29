{{-- Shared summary strip for list pages (see App\Filament\Widgets\ListSummaryWidget). --}}
<x-filament-widgets::widget>
    <x-summary-panel :s="$this->getSummary()" />
</x-filament-widgets::widget>
