<?php

declare(strict_types=1);

namespace App\Filament\Resources\Events\Widgets;

use App\Models\Booking;
use App\Models\Event;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

/**
 * "Revenue by Ticket Type" breakdown for a single event. Real booking data;
 * bookings with no tier (legacy / untiered) fall into an "Untiered" bucket so
 * the numbers always reconcile with Total Revenue.
 */
class EventRevenueByTypeWidget extends Widget
{
    /** Injected by the page via InteractsWithRecord::getWidgetData(). */
    public ?Event $record = null;

    protected string $view = 'filament.resources.events.widgets.event-revenue-by-type';

    protected int | string | array $columnSpan = 'full';

    /** Statuses that represent money actually earned. */
    private const PAID = ['confirmed', 'paid', 'completed'];

    /**
     * @return array{rows: list<array{name: string, orders: int, tickets: int, revenue: float, pct: int}>, total: float}
     */
    protected function getViewData(): array
    {
        if (! $this->record) {
            return ['rows' => [], 'total' => 0.0];
        }

        $grouped = Booking::where('event_id', $this->record->id)
            ->whereIn(DB::raw('lower(status)'), self::PAID)
            ->groupBy('ticket_type_id')
            ->get([
                'ticket_type_id',
                DB::raw('COUNT(*) as orders'),
                DB::raw('SUM(quantity) as tickets'),
                DB::raw('SUM(total_amount) as revenue'),
            ]);

        $total = (float) $grouped->sum('revenue');

        // Resolve tier names in one query.
        $names = $this->record->ticketTypes()->pluck('name', 'id');

        $rows = $grouped
            ->map(function ($row) use ($names, $total): array {
                $revenue = (float) $row->revenue;

                return [
                    'name' => $row->ticket_type_id === null
                        ? 'Untiered'
                        : ($names[$row->ticket_type_id] ?? 'Deleted tier'),
                    'orders'  => (int) $row->orders,
                    'tickets' => (int) $row->tickets,
                    'revenue' => $revenue,
                    'pct'     => $total > 0 ? (int) round($revenue / $total * 100) : 0,
                ];
            })
            ->sortByDesc('revenue')
            ->values()
            ->all();

        return ['rows' => $rows, 'total' => $total];
    }
}
