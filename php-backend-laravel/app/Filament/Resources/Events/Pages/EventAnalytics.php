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
use App\Filament\Resources\Events\Widgets\EventShareLinksWidget;
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

    /**
     * Flip the event's manual "Sold out" override from the hero toggle. When on,
     * the event reads as sold out everywhere buyers see it (website, app, booking
     * API) regardless of the real slot count, and the booking endpoint refuses new
     * orders. Turning it back off restores normal availability-based selling.
     */
    public function toggleSoldOut(): void
    {
        $e = $this->getRecord();
        $e->is_sold_out = ! $e->is_sold_out;
        $e->save();

        \Filament\Notifications\Notification::make()
            ->title($e->is_sold_out ? 'Marked as sold out' : 'Back on sale')
            ->body($e->is_sold_out
                ? 'Buyers now see “Sold out” and can’t book this event.'
                : 'Tickets are selling again, subject to availability.')
            ->success()
            ->send();
    }

    public static function canAccess(array $parameters = []): bool
    {
        $user = auth()->user();

        return ($user?->canManage('events') ?? false) && $user->hasPartnerPermission('reports');
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

    /**
     * Footer (not header) widgets: the page renders header widgets ABOVE the
     * view slot and footer widgets BELOW it, so putting these in the footer lets
     * the poster hero (the view) lead the page and the analytics stack follow.
     */
    protected function getFooterWidgets(): array
    {
        return [
            EventInsightsWidget::class,
            EventSalesPacingWidget::class,
            EventAnalyticsStatsWidget::class,
            EventViewsWidget::class,
            EventFunnelWidget::class,
            // The tool that fills Traffic sources cleanly: per-channel tagged links.
            EventShareLinksWidget::class,
            EventComparisonWidget::class,
            EventRevenueByTypeWidget::class,
            EventSalesChartWidget::class,
            EventAudienceWidget::class,
            EventArrivalCurveWidget::class,
            EventCouponWidget::class,
            EventRefundWidget::class,
        ];
    }

    public function getFooterWidgetsColumns(): int | array
    {
        return 1;
    }
}
