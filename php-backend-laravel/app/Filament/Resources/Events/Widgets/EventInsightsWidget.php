<?php

declare(strict_types=1);

namespace App\Filament\Resources\Events\Widgets;

use App\Models\Booking;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Filament\Widgets\Widget;

/**
 * AI-style insights — the differentiator. Instead of raw numbers, surface prioritised, plain-English
 * recommendations a host can act on. Every insight is computed from REAL data for this event (views,
 * bookings, pace, buyer demographics, check-ins, coupons, rating) — nothing is fabricated, and an
 * insight is only shown when there's enough data to state it honestly. Read-only; record injected.
 */
class EventInsightsWidget extends Widget
{
    public ?Event $record = null;

    protected int | string | array $columnSpan = 'full';

    protected string $view = 'filament.resources.events.widgets.event-insights';

    private const PAID = ['confirmed', 'paid', 'completed', 'checked_in'];

    /**
     * @return array<int,array{tone:string,icon:string,title:string,body:string}>
     */
    public function getInsights(): array
    {
        $event = $this->record;
        if (! $event) {
            return [];
        }

        $paid = fn () => Booking::where('event_id', $event->id)->whereIn(DB::raw('lower(status)'), self::PAID);

        $views = max((int) $event->views, 0);
        $orders = (int) $paid()->count();
        $attendees = (int) $paid()->sum('quantity');
        $revenue = (float) $paid()->sum('total_amount');
        $checkedIn = (int) $paid()->sum('checked_in_count');
        $capacity = max((int) $event->total_slots, 0);
        $available = max((int) $event->available_slots, 0);
        $sold = max($capacity - $available, 0);
        $fill = $capacity > 0 ? $sold / $capacity : 0.0;
        $eventPassed = $event->date?->isPast() ?? false;

        $out = [];

        // 1) Conversion: high interest, low purchase.
        if ($views >= 30) {
            $conv = $orders / $views;
            if ($conv < 0.03) {
                $out[] = $this->tip('heroicon-o-cursor-arrow-ripple', 'High interest, low conversion',
                    number_format($views) . ' people viewed this event but only ' . $this->pct($conv) . ' booked. Consider an Early Bird discount or a lower entry tier to convert the interest.');
            } elseif ($conv >= 0.06) {
                $out[] = $this->good('heroicon-o-bolt', 'Strong conversion',
                    'Views are converting at ' . $this->pct($conv) . ' — well above typical. Drive more traffic; it will sell.');
            }
        }

        // 2) Sell-out forecast from realised velocity.
        if ($capacity > 0 && ! $eventPassed) {
            if ($fill >= 1.0) {
                $out[] = $this->good('heroicon-o-check-badge', 'Sold out', 'Every ticket is gone. Consider adding capacity or a waitlist for the next drop.');
            } else {
                $forecast = $this->selloutForecast($event, $sold, $available);
                if ($forecast) {
                    $out[] = $forecast;
                }
            }
        }

        // 3) Peak booking window — when to schedule promos.
        $peak = $this->peakBookingWindow($event);
        if ($peak) {
            $out[] = $this->tip('heroicon-o-clock', 'Best time to promote', $peak);
        }

        // 4) Audience — age + geography recommendation.
        $audience = $this->audienceInsight($event);
        if ($audience) {
            $out[] = $audience;
        }

        // 5) Show-up prediction from the host's history.
        $showup = $this->showUpPrediction($event, $attendees, $checkedIn, $eventPassed);
        if ($showup) {
            $out[] = $showup;
        }

        // 6) Fill / pricing lever.
        if ($capacity > 0 && ! $eventPassed && $fill >= 0.85 && $fill < 1.0) {
            $out[] = $this->good('heroicon-o-arrow-trending-up', 'Almost sold out',
                round($fill * 100) . '% of seats are gone. You have pricing power — raise the price on the last tier or add a small batch of premium seats.');
        }

        // 7) Best coupon (or a nudge to try one).
        $coupon = $this->couponInsight($event, $revenue, $views, $orders);
        if ($coupon) {
            $out[] = $coupon;
        }

        // 8) Refund pressure.
        $refunded = (int) Booking::where('event_id', $event->id)->whereRaw('lower(status) = ?', ['refunded'])->count();
        if ($orders > 0 && $refunded / max(1, $orders) > 0.1) {
            $out[] = $this->warn('heroicon-o-arrow-uturn-left', 'Refunds are high',
                $refunded . ' refunds against ' . $orders . ' paid orders (' . $this->pct($refunded / $orders) . '). Check whether the event details set the right expectations.');
        }

        // 9) Rating signal.
        if ($event->rating !== null && (float) $event->rating > 0) {
            $r = (float) $event->rating;
            $out[] = $r >= 4.0
                ? $this->good('heroicon-o-star', 'Loved by attendees', 'Rated ' . number_format($r, 1) . '/5. Feature this rating in your next event\'s promotion — social proof sells.')
                : $this->tip('heroicon-o-star', 'Rating needs attention', 'Rated ' . number_format($r, 1) . '/5. Gather feedback on what to improve before your next event.');
        }

        return $out;
    }

    // ------------------------------------------------------------------

    private function selloutForecast(Event $event, int $sold, int $available): ?array
    {
        if ($sold <= 0) {
            return $event->date && $event->date->isFuture()
                ? $this->warn('heroicon-o-megaphone', 'No sales yet', 'This event hasn\'t sold a ticket. Launch a promo, share the link on WhatsApp/Instagram, and add an Early Bird price to kick-start bookings.')
                : null;
        }

        $firstPaid = Booking::where('event_id', $event->id)->whereIn(DB::raw('lower(status)'), self::PAID)->min('created_at');
        if (! $firstPaid) {
            return null;
        }
        $days = max(Carbon::parse($firstPaid)->diffInDays(now(), false), 0.5);
        $perDay = $sold / $days;
        if ($perDay <= 0) {
            return null;
        }
        $daysToSellout = (int) ceil($available / $perDay);
        $selloutDate = now()->addDays($daysToSellout);

        if ($event->date && $selloutDate->gt($event->date)) {
            return $this->tip('heroicon-o-presentation-chart-line', 'On pace to under-sell',
                'At ' . round($perDay, 1) . ' tickets/day you\'ll finish around ' . round($sold + $perDay * max(0, now()->diffInDays($event->date, false))) . ' of ' . ($sold + $available) . '. Add a marketing push or discount to close the gap.');
        }

        return $this->good('heroicon-o-fire', 'On track to sell out',
            'At the current pace (' . round($perDay, 1) . ' tickets/day) this event is projected to sell out around ' . $selloutDate->format('D, d M') . '.');
    }

    private function peakBookingWindow(Event $event): ?string
    {
        $hours = Booking::where('event_id', $event->id)
            ->whereIn(DB::raw('lower(status)'), self::PAID)
            ->pluck('created_at')
            ->filter()
            ->map(fn ($t) => (int) Carbon::parse($t)->format('G'));

        if ($hours->count() < 5) {
            return null;
        }
        $topHour = (int) $hours->countBy()->sortDesc()->keys()->first();

        return 'Most bookings land around ' . $this->hourLabel($topHour) . '–' . $this->hourLabel($topHour + 1)
            . '. Schedule your notifications and social posts just before this window.';
    }

    private function audienceInsight(Event $event): ?array
    {
        $userIds = Booking::where('event_id', $event->id)
            ->whereIn(DB::raw('lower(status)'), self::PAID)
            ->whereNotNull('user_id')->distinct()->pluck('user_id');

        if ($userIds->count() < 5) {
            return null;
        }

        $users = User::whereIn('id', $userIds)->get(['date_of_birth', 'gender', 'district', 'state']);

        // Dominant age band.
        $ages = $users->map(fn ($u) => $u->date_of_birth ? Carbon::parse($u->date_of_birth)->age : null)->filter();
        $ageBand = null;
        if ($ages->count() >= 3) {
            $band = $ages->map(fn ($a) => match (true) {
                $a < 18 => 'under 18', $a <= 24 => '18–24', $a <= 34 => '25–34', $a <= 44 => '35–44', default => '45+',
            })->countBy()->sortDesc()->keys()->first();
            $ageBand = $band;
        }

        // Top place (district, else state).
        $place = $users->pluck('district')->filter()->countBy()->sortDesc()->keys()->first()
            ?? $users->pluck('state')->filter()->countBy()->sortDesc()->keys()->first();

        if (! $ageBand && ! $place) {
            return null;
        }

        $parts = [];
        if ($ageBand) {
            $parts[] = 'mostly ' . $ageBand;
        }
        if ($place) {
            $parts[] = 'from ' . $place;
        }

        return $this->info('heroicon-o-users', 'Who\'s buying',
            'Your buyers are ' . implode(' ', $parts) . '. Target similar age groups and regions in your next campaign for the best return.');
    }

    private function showUpPrediction(Event $event, int $attendees, int $checkedIn, bool $eventPassed): ?array
    {
        // If this event already ran, report actual; else predict from the host's history.
        if ($eventPassed && $attendees > 0) {
            $rate = (int) round($checkedIn / $attendees * 100);

            return $this->info('heroicon-o-user-group', 'Show-up result', $rate . '% of ticket-holders actually attended (' . $checkedIn . '/' . $attendees . ').');
        }

        if ($event->partner_id === null) {
            return null;
        }
        $pastEventIds = Event::where('partner_id', $event->partner_id)->where('id', '!=', $event->id)
            ->whereDate('date', '<', now())->pluck('id');
        if ($pastEventIds->isEmpty()) {
            return null;
        }
        $pastPaid = Booking::whereIn('event_id', $pastEventIds)->whereIn(DB::raw('lower(status)'), self::PAID);
        $pastAtt = (int) (clone $pastPaid)->sum('quantity');
        $pastIn = (int) (clone $pastPaid)->sum('checked_in_count');
        if ($pastAtt < 10) {
            return null;
        }
        $rate = (int) round($pastIn / $pastAtt * 100);

        return $this->info('heroicon-o-user-group', 'Predicted attendance',
            'Based on your past events, expect roughly ' . $rate . '% of ticket-holders to show up. Plan staffing and F&B accordingly.');
    }

    private function couponInsight(Event $event, float $revenue, int $views, int $orders): ?array
    {
        $top = Booking::where('event_id', $event->id)
            ->whereIn(DB::raw('lower(status)'), self::PAID)
            ->whereNotNull('coupon_code')->where('coupon_code', '!=', '')
            ->selectRaw('upper(coupon_code) as code, count(*) as c, sum(total_amount) as rev')
            ->groupBy(DB::raw('upper(coupon_code)'))
            ->orderByDesc('rev')->first();

        if ($top) {
            return $this->good('heroicon-o-tag', 'Top promo code',
                $top->code . ' drove ' . (int) $top->c . ' orders and ₹' . number_format((float) $top->rev) . '. It\'s working — extend or re-share it.');
        }

        // No coupons yet + soft conversion → suggest one.
        if ($views >= 30 && ($orders / max(1, $views)) < 0.04) {
            return $this->tip('heroicon-o-tag', 'Try a promo code',
                'No coupons redeemed yet and conversion is soft. A time-boxed Early Bird code often lifts bookings from undecided viewers.');
        }

        return null;
    }

    // ------------------------------------------------------------------

    private function pct(float $frac): string
    {
        return number_format($frac * 100, 1) . '%';
    }

    private function hourLabel(int $h): string
    {
        $h = ($h + 24) % 24;
        $suffix = $h < 12 ? 'AM' : 'PM';
        $display = $h % 12 === 0 ? 12 : $h % 12;

        return $display . ' ' . $suffix;
    }

    private function good(string $icon, string $title, string $body): array
    {
        return ['tone' => 'good', 'icon' => $icon, 'title' => $title, 'body' => $body];
    }

    private function tip(string $icon, string $title, string $body): array
    {
        return ['tone' => 'tip', 'icon' => $icon, 'title' => $title, 'body' => $body];
    }

    private function warn(string $icon, string $title, string $body): array
    {
        return ['tone' => 'warn', 'icon' => $icon, 'title' => $title, 'body' => $body];
    }

    private function info(string $icon, string $title, string $body): array
    {
        return ['tone' => 'info', 'icon' => $icon, 'title' => $title, 'body' => $body];
    }
}
