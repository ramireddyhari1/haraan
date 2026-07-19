<?php

declare(strict_types=1);

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\Events\Pages\EditEvent;
use App\Filament\Resources\Events\Widgets\EventAnalyticsStatsWidget;
use App\Filament\Resources\Events\Widgets\EventArrivalCurveWidget;
use App\Filament\Resources\Events\Widgets\EventAudienceWidget;
use App\Filament\Resources\Events\Widgets\EventComparisonWidget;
use App\Filament\Resources\Events\Widgets\EventCouponWidget;
use App\Filament\Resources\Events\Widgets\EventFunnelWidget;
use App\Filament\Resources\Events\Widgets\EventInsightsWidget;
use App\Filament\Resources\Events\Widgets\EventRefundWidget;
use App\Filament\Resources\Events\Widgets\EventRevenueByTypeWidget;
use App\Filament\Resources\Events\Widgets\EventSalesChartWidget;
use App\Filament\Resources\Events\Widgets\EventSalesPacingWidget;
use App\Filament\Resources\Events\Widgets\EventViewsWidget;
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

    /**
     * Headline numbers for the hero banner: money collected, tickets gone vs
     * capacity, page views, and sell-through. Revenue is the paid-booking sum;
     * sold comes from the authoritative slot count. Cheap, and independent of
     * the individual analytics widgets.
     *
     * @return array{revenue: float, sold: int, total: int, views: int, sellThrough: int, status: string, poster: ?string}
     */
    public function heroStats(): array
    {
        $e = $this->getRecord();
        $paid = ['confirmed', 'paid', 'completed', 'checked_in'];

        $revenue = (float) \App\Models\Booking::query()
            ->where('event_id', $e->id)
            ->whereIn(\Illuminate\Support\Facades\DB::raw('lower(status)'), $paid)
            ->sum('total_amount');

        $total = max(0, (int) $e->total_slots);
        $sold = $total > 0 ? max(0, $total - max(0, (int) $e->available_slots)) : 0;

        return [
            'revenue'     => $revenue,
            'sold'        => $sold,
            'total'       => $total,
            'views'       => max(0, (int) $e->views),
            'sellThrough' => $total > 0 ? (int) round($sold / $total * 100) : 0,
            'status'      => (string) $e->status,
            'poster'      => $e->heroImageUrl(),
        ];
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
            EventInsightsWidget::class,
            EventSalesPacingWidget::class,
            EventAnalyticsStatsWidget::class,
            EventViewsWidget::class,
            EventFunnelWidget::class,
            EventComparisonWidget::class,
            EventRevenueByTypeWidget::class,
            EventSalesChartWidget::class,
            EventAudienceWidget::class,
            EventArrivalCurveWidget::class,
            EventCouponWidget::class,
            EventRefundWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int | array
    {
        return 1;
    }
}
