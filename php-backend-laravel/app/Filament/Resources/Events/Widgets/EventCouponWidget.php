<?php

declare(strict_types=1);

namespace App\Filament\Resources\Events\Widgets;

use App\Models\Booking;
use App\Models\Event;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

/**
 * Coupon / promo attribution for a single event: which codes actually drove bookings, how
 * much revenue they brought, and how much discount they cost. The base stats widget only
 * shows a total redemption count — this breaks it down per code so a host can see which
 * promo worked. Read-only; record injected by the page.
 */
class EventCouponWidget extends Widget
{
    public ?Event $record = null;

    protected int | string | array $columnSpan = 'full';

    protected string $view = 'filament.resources.events.widgets.event-coupon';

    private const PAID = ['confirmed', 'paid', 'completed', 'checked_in'];

    /**
     * @return array<int,array{code:string,orders:int,tickets:int,discount:float,revenue:float}>
     */
    public function getCoupons(): array
    {
        $event = $this->record;
        if (! $event) {
            return [];
        }

        return Booking::query()
            ->where('event_id', $event->id)
            ->whereIn(DB::raw('lower(status)'), self::PAID)
            ->whereNotNull('coupon_code')
            ->where('coupon_code', '!=', '')
            ->selectRaw('upper(coupon_code) as code, count(*) as orders, sum(quantity) as tickets, sum(discount) as discount, sum(total_amount) as revenue')
            ->groupBy(DB::raw('upper(coupon_code)'))
            ->orderByDesc('revenue')
            ->get()
            ->map(fn ($r) => [
                'code' => (string) $r->code,
                'orders' => (int) $r->orders,
                'tickets' => (int) $r->tickets,
                'discount' => (float) $r->discount,
                'revenue' => (float) $r->revenue,
            ])
            ->all();
    }

    /** @return array{orders:int,discount:float,revenue:float} */
    public function getTotals(): array
    {
        $rows = $this->getCoupons();

        return [
            'orders' => array_sum(array_column($rows, 'orders')),
            'discount' => array_sum(array_column($rows, 'discount')),
            'revenue' => array_sum(array_column($rows, 'revenue')),
        ];
    }
}
