<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Partner;

use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * "Organiser score" — one composite trust metric from four real sub-signals, so
 * quality becomes something an organiser can see and improve rather than guess
 * at. Weighted from sell-through (how full events run), check-in rate, refund
 * health (inverse of refunds), and repeat buyers. Ends in the single suggestion
 * that moves the needle most — the weakest component.
 *
 * Lane-aware (a venue owner has no capacity sell-through, so that weight is
 * redistributed). Partner-scoped; self-contained Blade + inline CSS.
 */
class PartnerOrganizerScoreWidget extends Widget
{
    use \App\Filament\Concerns\ScopesToPartnerEvents;
    use \App\Filament\Concerns\ScopesToPartnerVenues;

    protected string $view = 'filament.widgets.partner.organizer-score';

    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    protected static bool $isLazy = false;

    private const PAID = ['confirmed', 'paid', 'completed', 'checked_in'];

    private function isEventLane(): bool
    {
        return auth()->user()?->partner_type === 'event';
    }

    private function laneBookings(): Builder
    {
        return $this->isEventLane() ? $this->scopedBookingQuery() : $this->scopedVenueBookingQuery();
    }

    /**
     * @return array<string, mixed>
     */
    public function getScore(): array
    {
        $isEvent = $this->isEventLane();

        // --- sub-signals, each normalised 0–100 ---
        $sellThrough = $isEvent ? $this->sellThrough() : null;
        $checkIn = $this->checkInRate();
        $refundHealth = $this->refundHealth();
        $repeat = $this->repeatRate();

        // Weighted composite. Without sell-through (venue lane) the weight is
        // spread across the remaining three.
        if ($isEvent) {
            $weights = ['sell' => 0.30, 'checkin' => 0.25, 'refund' => 0.25, 'repeat' => 0.20];
            $score = $sellThrough * $weights['sell']
                + $checkIn * $weights['checkin']
                + $refundHealth * $weights['refund']
                + $repeat * $weights['repeat'];
        } else {
            $weights = ['checkin' => 0.34, 'refund' => 0.36, 'repeat' => 0.30];
            $score = $checkIn * $weights['checkin']
                + $refundHealth * $weights['refund']
                + $repeat * $weights['repeat'];
        }
        $score = (int) round($score);

        $components = array_filter([
            $isEvent ? ['key' => 'sell', 'label' => 'Sell-through', 'value' => (int) round($sellThrough), 'tip' => 'Fill events faster — add early-bird tiers or promote sooner.'] : null,
            ['key' => 'checkin', 'label' => 'Check-in rate', 'value' => (int) round($checkIn), 'tip' => 'More buyers should actually attend — send day-of reminders with the QR.'],
            ['key' => 'refund', 'label' => 'Refund health', 'value' => (int) round($refundHealth), 'tip' => 'Cut refunds — clearer event details and firm dates reduce cancellations.'],
            ['key' => 'repeat', 'label' => 'Repeat buyers', 'value' => (int) round($repeat), 'tip' => 'Win people back — follow up past attendees when you announce the next one.'],
        ]);

        // Weakest component drives the suggestion.
        $weakest = collect($components)->sortBy('value')->first();

        [$tier, $tierTone] = match (true) {
            $score >= 85 => ['Excellent · top tier', 'ok'],
            $score >= 70 => ['Strong', 'ok'],
            $score >= 50 => ['Growing', 'info'],
            default      => ['Building', 'warn'],
        };

        return [
            'score' => $score,
            'tier' => $tier,
            'tierTone' => $tierTone,
            'components' => array_values($components),
            'suggestion' => $weakest && $weakest['value'] < 80 ? $weakest['tip'] : null,
            'hasData' => $this->laneBookings()->exists(),
        ];
    }

    private function sellThrough(): float
    {
        $events = $this->scopedEventQuery()->where('total_slots', '>', 0)->get(['total_slots', 'available_slots']);
        if ($events->isEmpty()) {
            return 0.0;
        }
        $fills = $events->map(function ($e): float {
            $sold = (int) $e->total_slots - max(0, (int) $e->available_slots);
            return min(1.0, max(0.0, $sold / (int) $e->total_slots));
        });

        return round($fills->avg() * 100, 1);
    }

    private function checkInRate(): float
    {
        $paid = (clone $this->laneBookings())->whereIn(DB::raw('lower(status)'), self::PAID)->count();
        if ($paid === 0) {
            return 0.0;
        }
        $in = (clone $this->laneBookings())->whereRaw('lower(status) = ?', ['checked_in'])->count();

        return round($in / $paid * 100, 1);
    }

    private function refundHealth(): float
    {
        $all = (clone $this->laneBookings())->count();
        if ($all === 0) {
            return 100.0;
        }
        $bad = (clone $this->laneBookings())
            ->whereIn(DB::raw('lower(status)'), ['refunded', 'cancelled', 'canceled'])
            ->count();
        $rate = $bad / $all * 100;

        // 0% refunds → 100; ~25% refunds → 0.
        return round(max(0.0, 100 - $rate * 4), 1);
    }

    private function repeatRate(): float
    {
        $byUser = (clone $this->laneBookings())
            ->whereIn(DB::raw('lower(status)'), self::PAID)
            ->whereNotNull('user_id')
            ->select('user_id', DB::raw('count(*) as c'))
            ->groupBy('user_id')
            ->get();

        $distinct = $byUser->count();
        if ($distinct === 0) {
            return 0.0;
        }
        $returning = $byUser->where('c', '>=', 2)->count();

        return round($returning / $distinct * 100, 1);
    }
}
