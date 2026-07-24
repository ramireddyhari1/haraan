<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Support\AvatarColumn;
use App\Filament\Support\BookingTablePresenter;
use App\Models\Booking;
use App\Support\MediaUrl;
use Filament\Widgets\Widget;

/**
 * The Events-overview activity feed — the "what just happened" strip of recent
 * bookings against the partner's events.
 *
 * Rebuilt as a premium transaction feed (self-contained Blade + inline CSS,
 * theme-aware) — avatar, who + what, amount, a gradient status badge and how it
 * arrived — matching the dashboard's recent-bookings cards. Read-only glance.
 */
class LatestBookingsWidget extends Widget
{
    use \App\Filament\Concerns\RefreshesOnContentUpdate;
    use \App\Filament\Concerns\ScopesToPartnerEvents;

    protected string $view = 'filament.widgets.latest-bookings';

    protected static ?int $sort = -10;

    protected int | string | array $columnSpan = 'full';

    protected static bool $isLazy = false;

    /**
     * View-ready booking cards.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getBookings(): array
    {
        return $this->scopedBookingQuery()
            ->with(['user', 'event', 'venue'])
            ->latest()
            ->limit(8)
            ->get()
            ->map(function (Booking $r): array {
                $name = (string) ($r->user?->name ?? $r->guest_name ?? 'Guest');
                $photo = MediaUrl::resolve($r->user?->avatar);

                return [
                    'name'    => $name,
                    'avatar'  => ($photo !== null && $photo !== '') ? $photo : AvatarColumn::initials($name),
                    'line'    => $this->lineFor($r),
                    'amount'  => $this->inr((float) $r->total_amount),
                    'qty'     => max(1, (int) $r->quantity),
                    'status'  => BookingTablePresenter::statusLabel($r->status),
                    'tone'    => BookingTablePresenter::statusColor($r->status),
                    'channel' => in_array(strtolower((string) $r->channel), ['walkin', 'walk_in', 'offline', 'desk'], true) ? 'Walk-in' : 'App',
                    'since'   => $r->created_at?->diffForHumans(null, true) . ' ago',
                    'stamp'   => $r->created_at?->format('d M Y, H:i') ?? '',
                ];
            })->all();
    }

    /** A human line for the booking's target — event title or venue + slot. */
    private function lineFor(Booking $r): ?string
    {
        if ($r->booking_type === 'venue') {
            return trim(($r->venue?->name ?? 'Venue') . ($r->slot_label ? ' · ' . $r->slot_label : ''));
        }

        return $r->event?->title;
    }

    /** ₹1,842 — Indian grouping, whole rupees. */
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
