<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Partner;

use App\Filament\Pages\Partner\PartnerEarnings;
use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\Venues\VenueResource;
use App\Models\Event;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The dashboard's "Needs you" row — what turns an informative dashboard into an
 * intelligent one. Three signal cards, each ending in a suggestion and a link:
 *
 *   1. Sellout risk   — events ≥85% sold with a future date (event lane), or
 *                        this week's incoming bookings (venue lane).
 *   2. Pending settlement — money collected but not yet paid out.
 *   3. Refund watch   — refund/cancel rate over the last 14 days.
 *
 * Each card resolves to a calm "all clear" state when there's nothing to act on,
 * so the row never reads as empty. Partner-scoped + lane-aware; self-contained
 * Blade + inline CSS, no Vite rebuild.
 */
class PartnerNeedsAttentionWidget extends Widget
{
    use \App\Filament\Concerns\ScopesToPartnerEvents;
    use \App\Filament\Concerns\ScopesToPartnerVenues;

    protected string $view = 'filament.widgets.partner.needs-attention';

    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    protected static bool $isLazy = false;

    private const PAID = ['confirmed', 'paid', 'completed', 'checked_in'];

    private const SETTLED = ['paid', 'processed', 'completed'];

    private function isEventLane(): bool
    {
        return auth()->user()?->partner_type === 'event';
    }

    private function laneBookings(): Builder
    {
        return $this->isEventLane() ? $this->scopedBookingQuery() : $this->scopedVenueBookingQuery();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getSignals(): array
    {
        return [
            $this->isEventLane() ? $this->selloutSignal() : $this->incomingSignal(),
            $this->settlementSignal(),
            $this->refundSignal(),
        ];
    }

    /** Event lane: how many live events are about to sell out. */
    private function selloutSignal(): array
    {
        $events = $this->scopedEventQuery()
            ->whereRaw('lower(status) = ?', ['published'])
            ->where('date', '>=', now()->startOfDay())
            ->where('total_slots', '>', 0)
            ->get(['id', 'title', 'date', 'total_slots', 'available_slots']);

        $atRisk = $events->filter(function (Event $e): bool {
            $sold = $e->total_slots - max(0, (int) $e->available_slots);
            return $e->available_slots > 0 && ($sold / $e->total_slots) >= 0.85;
        })->sortBy('date')->values();

        if ($atRisk->isEmpty()) {
            return [
                'icon' => '🎯', 'tone' => 'ok', 'label' => 'Sellout risk',
                'value' => 'None', 'hint' => 'No live event is close to selling out yet.',
                'url' => EventResource::getUrl(), 'cta' => 'View events',
            ];
        }

        $next = $atRisk->first();
        $days = (int) now()->startOfDay()->diffInDays($next->date, false);
        $when = $days <= 0 ? 'today' : ($days === 1 ? 'in 1 day' : "in {$days} days");

        return [
            'icon' => '🔥', 'tone' => 'warn',
            'label' => 'Sellout risk',
            'value' => $atRisk->count() . ' ' . ($atRisk->count() === 1 ? 'event' : 'events'),
            'hint' => "“{$next->title}” is almost full ({$when}). Consider adding capacity or raising the price.",
            'url' => EventResource::getUrl(), 'cta' => 'Review events',
        ];
    }

    /** Venue lane: bookings that came in this week. */
    private function incomingSignal(): array
    {
        $new = (clone $this->scopedVenueBookingQuery())
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        return [
            'icon' => '📅', 'tone' => $new > 0 ? 'info' : 'ok',
            'label' => 'New bookings · 7 days',
            'value' => (string) $new,
            'hint' => $new > 0
                ? 'Fresh turf bookings this week — check your slot grid for clashes.'
                : 'No new bookings in the last week.',
            'url' => VenueResource::getUrl(), 'cta' => 'View venues',
        ];
    }

    /** Money collected but not yet settled to the partner. */
    private function settlementSignal(): array
    {
        $pending = (float) (clone $this->laneBookings())
            ->whereIn(DB::raw('lower(status)'), self::PAID)
            ->whereDoesntHave('payout', fn (Builder $q) => $q->whereIn(DB::raw('lower(status)'), self::SETTLED))
            ->sum('total_amount');

        return [
            'icon' => '💸', 'tone' => $pending > 0 ? 'warn' : 'ok',
            'label' => 'Pending settlement',
            'value' => $this->inr($pending),
            'hint' => $pending > 0
                ? 'Collected but not yet paid out. Track each payout in Earnings.'
                : 'Everything collected has been settled.',
            'url' => PartnerEarnings::getUrl(), 'cta' => 'Open earnings',
        ];
    }

    /** Refund / cancellation rate over the current 14-day window. */
    private function refundSignal(): array
    {
        $since = now()->startOfDay()->subDays(13);
        $all = (clone $this->laneBookings())->where('created_at', '>=', $since)->count();
        $bad = (clone $this->laneBookings())
            ->where('created_at', '>=', $since)
            ->whereIn(DB::raw('lower(status)'), ['refunded', 'cancelled', 'canceled'])
            ->count();

        $rate = $all > 0 ? round($bad / $all * 100, 1) : 0.0;
        $high = $rate >= 5.0;

        return [
            'icon' => $high ? '⚠️' : '✅', 'tone' => $high ? 'danger' : 'ok',
            'label' => 'Refund watch · 14 days',
            'value' => number_format($rate, 1) . '%',
            'hint' => $high
                ? 'Refunds are elevated — check recent cancellations for a pattern.'
                : 'Refund rate is healthy.',
            'url' => $this->isEventLane() ? EventResource::getUrl() : VenueResource::getUrl(),
            'cta' => 'Investigate',
        ];
    }

    /** ₹18,42,900 — Indian grouping. */
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
