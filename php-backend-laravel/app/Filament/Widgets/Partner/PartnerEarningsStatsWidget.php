<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Partner;

use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\Venues\VenueResource;
use App\Models\Booking;
use App\Models\Payout;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Money at a glance for a partner: what they've collected, what's already been
 * settled to them, and what's still pending. Collected is derived from the
 * partner's own PAID bookings (always accurate); settled comes from the payouts
 * ledger; pending is the difference.
 *
 * Rebuilt as a premium fintech hero (self-contained Blade + inline CSS,
 * theme-aware) — a big "Collected" figure with a settlement progress bar and
 * two settled/pending tiles, the RazorpayX / Stripe balance feel.
 */
class PartnerEarningsStatsWidget extends Widget
{
    protected string $view = 'filament.widgets.partner.earnings-stats';

    protected static ?int $sort = -2;

    protected int | string | array $columnSpan = 'full';

    protected static bool $isLazy = false;

    /** Statuses that represent money actually collected. */
    private const PAID = ['confirmed', 'paid', 'completed', 'checked_in'];

    /** Payout statuses that mean the partner has been paid. */
    private const SETTLED = ['paid', 'processed', 'completed'];

    /** Bookings belonging to this partner — their events OR their venues. */
    private function partnerBookings(): Builder
    {
        $query = Booking::query();

        if (Filament::getCurrentPanel()?->getId() === 'partner' && ! auth()->user()?->isSuperAdmin()) {
            $eventIds = EventResource::getEloquentQuery()->select('events.id');
            $venueIds = VenueResource::getEloquentQuery()->select('venues.id');

            $query->where(fn (Builder $q) => $q
                ->whereIn('event_id', $eventIds)
                ->orWhereIn('venue_id', $venueIds));
        }

        return $query;
    }

    /**
     * View-ready money summary.
     *
     * @return array<string, mixed>
     */
    public function getSummary(): array
    {
        $paid = fn (): Builder => $this->partnerBookings()->whereIn(DB::raw('lower(status)'), self::PAID);

        $collected = (float) $paid()->sum('total_amount');
        $collectedMonth = (float) (clone $paid())->where('created_at', '>=', now()->startOfMonth())->sum('total_amount');

        $settled = (float) Payout::query()
            ->whereIn('booking_id', $this->partnerBookings()->select('bookings.id'))
            ->whereIn(DB::raw('lower(status)'), self::SETTLED)
            ->sum('amount');

        $pending = max($collected - $settled, 0);
        $pct = $collected > 0 ? (int) round($settled / $collected * 100) : 0;

        return [
            'collected'      => $this->inr($collected),
            'collectedMonth' => $this->inr($collectedMonth),
            'settled'        => $this->inr($settled),
            'pending'        => $this->inr($pending),
            'pct'            => min(100, max(0, $pct)),
            'hasPending'     => $pending > 0,
        ];
    }

    /** ₹18,42,900 — Indian grouping, whole rupees. */
    private function inr(float $n): string
    {
        $n = (int) round($n);
        $str = (string) abs($n);
        if (strlen($str) <= 3) {
            return '₹' . $str;
        }
        $last3 = substr($str, -3);
        $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', substr($str, 0, -3));

        return '₹' . $rest . ',' . $last3;
    }
}
