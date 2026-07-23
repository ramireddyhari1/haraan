<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Partner;

use App\Filament\Pages\Dashboard;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

/**
 * The conversion funnel — Page Views → Sales → Conversion % — for the organiser's
 * own events, over the dashboard's global period. This is the "how well am I turning
 * interest into tickets" story the money widgets can't tell on their own.
 *
 *   - Page Views: rows in event_views for the partner's events (recorded on every
 *     web + app event-detail open by EventViewRecorder), scoped through
 *     event.partner_id like bookings.
 *   - Sales: paid bookings in the same window.
 *   - Conversion: sales ÷ page views.
 *
 * Event lane only — event_views are per event, so a venue owner (turf bookings)
 * has no page-view funnel; they keep the money widgets. Self-contained Blade +
 * inline CSS, no Vite rebuild.
 */
class PartnerFunnelWidget extends Widget
{
    use \App\Filament\Concerns\ScopesToPartnerEvents;
    use \Filament\Widgets\Concerns\InteractsWithPageFilters;

    protected string $view = 'filament.widgets.partner.funnel';

    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    protected static bool $isLazy = false;

    /** Statuses that represent a real sale. */
    private const PAID = ['confirmed', 'paid', 'completed', 'checked_in'];

    /**
     * Only the event lane, and only desk staff with the 'reports' capability —
     * views are event-only, so a venue owner has no funnel to show.
     */
    public static function canView(): bool
    {
        $u = auth()->user();

        return $u?->partner_type === 'event'
            && ($u?->hasPartnerPermission('reports') ?? false);
    }

    /** The window (in days) from the dashboard's global period control. */
    private function windowDays(): int
    {
        $range = (int) ($this->pageFilters['range'] ?? Dashboard::DEFAULT_PERIOD);

        return in_array($range, [7, 14, 30, 90], true) ? $range : Dashboard::DEFAULT_PERIOD;
    }

    /**
     * @return array<string, mixed>
     */
    public function getFunnel(): array
    {
        $days = $this->windowDays();
        $start = now()->startOfDay()->subDays($days - 1);

        $views = (clone $this->scopedEventViewQuery())->where('created_at', '>=', $start);
        $pageViews = (int) $views->count();
        $uniqueViews = (int) (clone $this->scopedEventViewQuery())
            ->where('created_at', '>=', $start)
            ->distinct('visitor_key')->count('visitor_key');

        $sales = (int) (clone $this->scopedBookingQuery())
            ->whereIn(DB::raw('lower(status)'), self::PAID)
            ->where('created_at', '>=', $start)
            ->count();

        // Conversion against unique visitors (people), not raw opens — the honest
        // "how many of the humans who looked actually bought" rate.
        $denominator = $uniqueViews > 0 ? $uniqueViews : $pageViews;
        $conversion = $denominator > 0 ? round($sales / $denominator * 100, 1) : null;

        return [
            'days' => $days,
            'pageViews' => $pageViews,
            'uniqueViews' => $uniqueViews,
            'sales' => $sales,
            'conversion' => $conversion,
            'hasData' => $pageViews > 0 || $sales > 0,
        ];
    }
}
