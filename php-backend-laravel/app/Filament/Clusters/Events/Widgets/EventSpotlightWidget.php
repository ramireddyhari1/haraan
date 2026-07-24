<?php

declare(strict_types=1);

namespace App\Filament\Clusters\Events\Widgets;

use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\Events\Pages\EventAnalytics;
use App\Models\Booking;
use App\Models\Event;
use App\Models\Payout;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

/**
 * The Events overview hero — the partner's most relevant event as a single
 * cinematic card: poster, live countdown, a sell-through progress ring, revenue,
 * tickets, occupancy and a composite health score. One answer to "how is my
 * event performing right now?" instead of a wall of stat boxes.
 *
 * Picks the soonest published future event; falls back to the latest published
 * (or any) event so the hero is never empty while a partner has a catalog.
 * Self-contained Blade + inline CSS, theme-aware, no Vite rebuild.
 */
class EventSpotlightWidget extends Widget
{
    use \App\Filament\Concerns\RefreshesOnContentUpdate;
    use \App\Filament\Concerns\ScopesToPartnerEvents;

    protected string $view = 'filament.clusters.events.widgets.event-spotlight';

    protected static ?int $sort = -3;

    protected int | string | array $columnSpan = 'full';

    protected static bool $isLazy = false;

    /** Statuses that represent money actually collected. */
    private const PAID = ['confirmed', 'paid', 'completed', 'checked_in'];

    /** Payout statuses that mean the partner has been paid. */
    private const SETTLED = ['paid', 'processed', 'completed'];

    private function pickEvent(): ?Event
    {
        return $this->scopedEventQuery()->whereRaw("lower(status) = 'published'")
                ->whereDate('date', '>=', now()->toDateString())->orderBy('date')->first()
            ?? $this->scopedEventQuery()->whereRaw("lower(status) = 'published'")
                ->orderByDesc('date')->first()
            ?? $this->scopedEventQuery()->orderByDesc('date')->first();
    }

    /**
     * View-ready hero payload, or null when the partner has no events yet.
     *
     * @return array<string, mixed>|null
     */
    public function getSpotlight(): ?array
    {
        $e = $this->pickEvent();
        if ($e === null) {
            return null;
        }

        $total = max(0, (int) $e->total_slots);
        $avail = max(0, (int) $e->available_slots);
        $sold = max(0, $total - $avail);
        $pct = $total > 0 ? (int) round($sold / $total * 100) : 0;

        $paid = fn () => Booking::query()->where('event_id', $e->id)
            ->whereIn(DB::raw('lower(status)'), self::PAID);

        $revenue = (float) $paid()->sum('total_amount');
        $recent7 = (int) (clone $paid())->where('created_at', '>=', now()->subDays(7))->sum('quantity');

        // Today vs yesterday booking velocity.
        $today = (int) (clone $paid())->whereDate('created_at', today())->sum('quantity');
        $yesterday = (int) (clone $paid())->whereDate('created_at', today()->subDay())->sum('quantity');
        $velPct = $yesterday > 0 ? (int) round(($today - $yesterday) / $yesterday * 100) : ($today > 0 ? 100 : 0);

        // Pending settlement for THIS event: collected minus what's been paid out.
        $settled = (float) Payout::query()
            ->whereIn('booking_id', Booking::query()->where('event_id', $e->id)->select('id'))
            ->whereIn(DB::raw('lower(status)'), self::SETTLED)
            ->sum('amount');
        $pending = max(0.0, $revenue - $settled);

        $isFuture = $e->date !== null && $e->date->isFuture();

        // Seats line, shown once on the poster.
        $seatsText = match (true) {
            $total > 0 && $avail <= 0 => 'Sold out',
            $total > 0                => number_format($avail) . ' seats left',
            default                   => null,
        };

        // A compact "when" phrase instead of a boxed countdown.
        $countdownText = null;
        if ($isFuture) {
            $days = (int) ceil(now()->diffInDays($e->date, false));
            $countdownText = match (true) {
                $days <= 0  => 'Today',
                $days === 1 => 'Tomorrow',
                $days <= 45 => "in {$days} days",
                default     => 'in ' . (int) round($days / 7) . ' weeks',
            };
        }

        // The single most useful nudge for the metrics card (seats live on the poster).
        $primary = null;
        if ($today > 0 && $velPct > 0) {
            $primary = ['ic' => '📈', 'text' => "Bookings up {$velPct}% today", 'tone' => 'up'];
        } elseif ($today > 0 && $velPct < 0) {
            $primary = ['ic' => '📉', 'text' => 'Bookings down ' . abs($velPct) . '% today', 'tone' => 'warn'];
        } elseif ($today > 0) {
            $primary = ['ic' => '✅', 'text' => "{$today} tickets sold today", 'tone' => 'up'];
        } elseif ($isFuture && $total > 0 && $avail > 0 && $pct < 60) {
            $primary = ['ic' => '🚀', 'text' => 'Promote to lift sales', 'tone' => 'action', 'url' => url('/events/' . $e->id)];
        } elseif ($pending > 0) {
            $primary = ['ic' => '💸', 'text' => $this->inr($pending) . ' awaiting settlement', 'tone' => 'info'];
        }

        // Health = sell-through weighted with recent momentum; a sold-out event is
        // simply 100. Momentum rewards tickets moved in the last week.
        $momentum = $total > 0 ? min(100, (int) round($recent7 / $total * 100 * 3)) : 0;
        $health = ($total > 0 && $avail <= 0) ? 100 : (int) min(100, round($pct * 0.65 + $momentum * 0.35));

        [$hBand, $hTone] = match (true) {
            $health >= 75 => ['Excellent', 'hot'],
            $health >= 50 => ['Healthy', 'good'],
            $health >= 25 => ['Warming up', 'warm'],
            default       => ['Needs a push', 'cool'],
        };

        [$sLabel, $sTone] = match (true) {
            $total > 0 && $avail <= 0 => ['Sold out', 'danger'],
            $total > 0 && $pct >= 85  => ['Almost full', 'warning'],
            $sold > 0                 => ['On sale', 'success'],
            default                   => ['Just listed', 'gray'],
        };

        return [
            'title'      => (string) $e->title,
            'poster'     => $e->heroImageUrl(),
            'whenWhere'  => $this->whenWhere($e),
            'countdown'  => $countdownText,
            'isFuture'   => $isFuture,
            'revenue'    => $this->inr($revenue),
            'sold'       => number_format($sold),
            'total'      => number_format($total),
            'pct'        => min(100, max(0, $pct)),
            'seatsText'  => $seatsText,
            'recent7'    => $recent7,
            'primary'    => $primary,
            'health'     => $health,
            'hBand'      => $hBand,
            'hTone'      => $hTone,
            'sLabel'     => $sLabel,
            'sTone'      => $sTone,
            'analytics'  => EventAnalytics::getUrl(['record' => $e]),
            'manage'     => EventResource::getUrl('edit', ['record' => $e]),
            'createUrl'  => EventResource::getUrl('create'),
        ];
    }

    /** Where a partner starts a first event, for the empty hero. */
    public function createUrl(): string
    {
        return EventResource::getUrl('create');
    }

    private function whenWhere(Event $e): ?string
    {
        $bits = [];
        if ($e->date !== null) {
            $bits[] = $e->date->format('D, d M Y · g:i A');
        }
        $place = trim((string) ($e->venue ?: $e->location));
        if ($place !== '') {
            $bits[] = $place;
        }

        return $bits === [] ? null : implode(' · ', $bits);
    }

    /** ₹1,84,290 — Indian grouping, whole rupees. */
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
