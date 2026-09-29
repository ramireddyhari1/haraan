<?php

declare(strict_types=1);

namespace App\Filament\Resources\VenueBookings\Widgets;

use App\Filament\Widgets\ListSummaryWidget;
use App\Models\Booking;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Venue bookings list summary: the last 30 days as recorded, money as the
 * payment ledger saw it (an advance is what was collected, not the invoice),
 * and the slots already booked for the coming week.
 */
class VenueBookingsExecutiveHeroWidget extends ListSummaryWidget
{
    private const METHOD_NAMES = ['cash' => 'Cash', 'upi' => 'UPI', 'card' => 'Card', 'razorpay' => 'Online (Razorpay)', 'online' => 'Online'];

    public function getSummary(): array
    {
        $since = now()->subDays(30);
        $venue = fn () => Booking::query()->where('booking_type', 'venue');
        $recent = fn () => $venue()->where('created_at', '>=', $since);

        $total = $recent()->count();
        $paid = $recent()->whereIn(DB::raw('lower(status)'), self::PAID)->count();
        $cancelled = $recent()->whereIn(DB::raw('lower(status)'), self::CANCELLED)->count();

        $byMethod = DB::table('booking_payments')
            ->whereIn('booking_id', $venue()->select('id'))
            ->where('collected_at', '>=', $since)
            ->where('amount', '>', 0)
            ->selectRaw('lower(method) as m, SUM(amount) as total')
            ->groupBy('m')
            ->pluck('total', 'm');
        $collected = (float) $byMethod->sum();

        $withCoupon = $recent()->whereNotNull('coupon_code')->where('coupon_code', '!=', '');
        $couponUses = (clone $withCoupon)->count();
        $couponOff = (float) (clone $withCoupon)->sum('discount');

        $rows = [];
        $today = Carbon::today();
        $ahead = $venue()
            ->whereNotIn(DB::raw('lower(status)'), [...self::CANCELLED, 'expired'])
            ->whereBetween('slot_date', [$today->toDateString(), $today->copy()->addDays(6)->toDateString()])
            ->selectRaw('date(slot_date) as d, COUNT(*) as n')
            ->groupBy('d')
            ->pluck('n', 'd');
        foreach (range(0, 6) as $i) {
            $day = $today->copy()->addDays($i);
            $n = (int) ($ahead[$day->toDateString()] ?? 0);
            $rows[] = [
                'primary' => $i === 0 ? 'Today' : ($i === 1 ? 'Tomorrow' : $day->format('l')),
                'secondary' => $day->format('j M'),
                'trailing' => $n === 0 ? 'none yet' : number_format($n) . ' ' . str('slot')->plural($n),
            ];
        }

        return [
            'title' => 'Venue bookings',
            'window' => 'Last 30 days',
            'stats' => [
                ['label' => 'Bookings', 'value' => number_format($total), 'sub' => number_format($paid) . ' confirmed or paid'],
                ['label' => 'Collected', 'value' => self::inr($collected), 'sub' => 'from the payment ledger', 'tone' => $collected > 0 ? 'good' : null],
                ['label' => 'Cancelled', 'value' => self::pct($cancelled, $total), 'sub' => number_format($cancelled) . ' of ' . number_format($total),
                    'tone' => $total >= 10 && $cancelled / $total >= 0.08 ? 'warn' : null],
                ['label' => 'Coupons used', 'value' => number_format($couponUses), 'sub' => $couponUses > 0 ? self::inr($couponOff) . ' off in all' : null],
            ],
            'split' => [
                'label' => 'How the money came in',
                'parts' => self::parts(collect($byMethod)
                    ->mapWithKeys(fn ($v, $m) => [self::METHOD_NAMES[$m] ?? ucfirst((string) $m) => (float) $v])
                    ->all()),
            ],
            'list' => [
                'title' => 'Booked for the next 7 days',
                'rows' => $rows,
                'empty' => 'Nothing booked for the coming week yet.',
            ],
        ];
    }
}
