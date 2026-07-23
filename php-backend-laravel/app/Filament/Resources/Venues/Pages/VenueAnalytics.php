<?php

declare(strict_types=1);

namespace App\Filament\Resources\Venues\Pages;

use App\Filament\Resources\Venues\VenueResource;
use App\Filament\Resources\Venues\Widgets\VenueAnalyticsStatsWidget;
use App\Filament\Resources\Venues\Widgets\VenueBookingsChartWidget;
use App\Filament\Resources\Venues\Widgets\VenuePeakDaysWidget;
use BackedEnum;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

/**
 * Per-venue owner analytics — the GameHub twin of the event-host Analytics page.
 * Reached from an "Analytics" row action on the venues table. Widgets receive
 * the bound venue via InteractsWithRecord::getWidgetData().
 */
class VenueAnalytics extends Page
{
    use InteractsWithRecord;

    protected static string $resource = VenueResource::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $title = 'Analytics';

    protected string $view = 'filament.resources.venues.pages.venue-analytics';

    public function mount(int | string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string
    {
        return $this->getRecord()->name . ' — Analytics';
    }

    public static function canAccess(array $parameters = []): bool
    {
        $user = auth()->user();

        return ($user?->canManage('gamehub') ?? false) && $user->hasPartnerPermission('reports');
    }

    protected function getHeaderWidgets(): array
    {
        return [
            VenueAnalyticsStatsWidget::class,
            VenueBookingsChartWidget::class,
            VenuePeakDaysWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int | array
    {
        return 1;
    }
}
