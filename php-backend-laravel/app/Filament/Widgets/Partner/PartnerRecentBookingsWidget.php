<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Partner;

use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Support\AvatarColumn;
use App\Filament\Support\BookingTablePresenter;
use App\Models\Booking;
use App\Support\MediaUrl;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;

/**
 * "What just came in" for the partner home — the latest bookings against the
 * partner's own events (event lane) or venues (venue lane), scoped so nothing
 * from other partners ever leaks.
 *
 * Rebuilt as a premium card feed (self-contained Blade + inline CSS, theme-aware,
 * no Vite rebuild) so each booking reads like a Stripe / BookMyShow-partner row:
 * avatar, who + what, a big revenue figure, a gradient status badge and the
 * channel it arrived on. Read-only glance; deep links live in the Bookings /
 * Day-bookings sections.
 */
class PartnerRecentBookingsWidget extends Widget
{
    use \App\Filament\Concerns\ScopesToPartnerEvents;
    use \App\Filament\Concerns\ScopesToPartnerVenues;

    protected string $view = 'filament.widgets.partner.recent-bookings';

    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    // Render eagerly — on the short dashboard grid a lazy widget never intersects.
    protected static bool $isLazy = false;

    /** Booking list — desk staff need the 'bookings' capability to see it. */
    public static function canView(): bool
    {
        return auth()->user()?->hasPartnerPermission('bookings') ?? false;
    }

    private function isEventLane(): bool
    {
        return auth()->user()?->partner_type === 'event';
    }

    private function baseQuery(): Builder
    {
        $base = $this->isEventLane() ? $this->scopedBookingQuery() : $this->scopedVenueBookingQuery();

        return $base->with(['user', 'event', 'venue'])->latest()->limit(8);
    }

    /** Where the "View all" header link points, or null when the desk can't reach it. */
    public function allBookingsUrl(): ?string
    {
        return BookingResource::canAccess() ? BookingResource::getUrl() : null;
    }

    /**
     * Flattened, view-ready rows so the Blade stays dumb.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getBookings(): array
    {
        return $this->baseQuery()->get()->map(function (Booking $r): array {
            $name = (string) ($r->user?->name ?? $r->guest_name ?? 'Guest');
            $photo = MediaUrl::resolve($r->user?->avatar);

            return [
                'name'     => $name,
                'avatar'   => ($photo !== null && $photo !== '') ? $photo : AvatarColumn::initials($name),
                'line'     => $this->lineFor($r),
                'amount'   => $this->inr((float) $r->total_amount),
                'qty'      => max(1, (int) $r->quantity),
                'status'   => BookingTablePresenter::statusLabel($r->status),
                'tone'     => BookingTablePresenter::statusColor($r->status),
                'channel'  => $this->channelFor($r),
                'since'    => $r->created_at?->diffForHumans(null, true) . ' ago',
                'stamp'    => $r->created_at?->format('d M Y, H:i') ?? '',
            ];
        })->all();
    }

    /** A human line for the booking's target — event title or venue + slot. */
    private function lineFor(Booking $r): ?string
    {
        if ($r->event) {
            return $r->event->title;
        }

        if ($r->venue) {
            return trim($r->venue->name . ($r->slot_label ? ' · ' . $r->slot_label : ''));
        }

        return null;
    }

    /** How the booking reached the desk: an offline walk-in, or the app. */
    private function channelFor(Booking $r): string
    {
        return in_array(strtolower((string) $r->channel), ['walkin', 'walk_in', 'offline', 'desk'], true)
            ? 'Walk-in'
            : 'App';
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
