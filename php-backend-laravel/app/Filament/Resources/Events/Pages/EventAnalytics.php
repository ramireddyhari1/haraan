<?php

declare(strict_types=1);

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\Events\Pages\EditEvent;
use App\Filament\Resources\Events\Widgets\EventAnalyticsStatsWidget;
use App\Filament\Resources\Events\Widgets\EventArrivalCurveWidget;
use App\Filament\Resources\Events\Widgets\EventCouponWidget;
use App\Filament\Resources\Events\Widgets\EventRevenueByTypeWidget;
use App\Filament\Resources\Events\Widgets\EventSalesChartWidget;
use App\Filament\Resources\Events\Widgets\EventSalesPacingWidget;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

/**
 * Per-event host analytics dashboard. Reached from the events table
 * ("Analytics" row action) and shows revenue / attendance performance for a
 * single event. Widgets receive the bound record via
 * InteractsWithRecord::getWidgetData().
 */
class EventAnalytics extends Page
{
    use InteractsWithRecord;

    protected static string $resource = EventResource::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $title = 'Analytics';

    protected string $view = 'filament.resources.events.pages.event-analytics';

    public function mount(int | string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string
    {
        return $this->getRecord()->title . ' — Analytics';
    }

    public static function canAccess(array $parameters = []): bool
    {
        return auth()->user()?->canManage('events') ?? false;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('edit')
                ->label('Edit event')
                ->icon('heroicon-m-pencil-square')
                ->url(fn (): string => EditEvent::getUrl(['record' => $this->getRecord()])),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            EventSalesPacingWidget::class,
            EventAnalyticsStatsWidget::class,
            EventRevenueByTypeWidget::class,
            EventSalesChartWidget::class,
            EventArrivalCurveWidget::class,
            EventCouponWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int | array
    {
        return 1;
    }
}
