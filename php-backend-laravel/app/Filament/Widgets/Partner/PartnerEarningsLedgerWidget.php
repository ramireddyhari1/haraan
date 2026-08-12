<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Partner;

use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\Venues\VenueResource;
use App\Filament\Support\AvatarColumn;
use App\Filament\Support\BookingTablePresenter;
use App\Models\Booking;
use App\Support\MediaUrl;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The earnings ledger — every paying booking against the partner's events or
 * venues as a money row (what, who, how much, settled?). Derived from bookings
 * so it's always complete, unlike the sparse payouts table.
 *
 * Rebuilt as a premium transaction feed (self-contained Blade + inline CSS,
 * theme-aware) — each row is a card with an event poster, who paid, the amount,
 * payment + settlement chips and the payout date, with a sort control and a
 * Livewire "show more". The Stripe / BookMyShow-partner ledger feel.
 */
class PartnerEarningsLedgerWidget extends Widget
{
    protected string $view = 'filament.widgets.partner.earnings-ledger';

    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    protected static bool $isLazy = false;

    /** Sort mode, bound from the header select. */
    public string $ledgerSort = 'recent';

    /** How many rows to show; grows via "show more". */
    public int $limit = 8;

    /** Statuses that represent money actually collected. */
    private const PAID = ['confirmed', 'paid', 'completed', 'checked_in'];

    private const SETTLED = ['paid', 'processed', 'completed'];

    public function showMore(): void
    {
        $this->limit += 8;
    }

    public function updatedLedgerSort(): void
    {
        $this->limit = 8;
    }

    protected function partnerBookings(): Builder
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

    private function paidBookings(): Builder
    {
        $q = $this->partnerBookings()
            ->whereIn(DB::raw('lower(status)'), self::PAID)
            ->with(['user', 'event', 'venue', 'payout']);

        return $this->ledgerSort === 'amount'
            ? $q->orderByDesc('total_amount')
            : $q->latest();
    }

    /** Total paid rows (drives the "showing X of Y" + show-more visibility). */
    public function getTotalCount(): int
    {
        return $this->partnerBookings()
            ->whereIn(DB::raw('lower(status)'), self::PAID)
            ->count();
    }

    /**
     * View-ready transaction cards.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRows(): array
    {
        return $this->paidBookings()->limit($this->limit)->get()->map(function (Booking $r): array {
            $isVenue = (bool) $r->venue_id;
            $title = $r->event?->title ?? $r->venue?->name ?? '—';
            $who = (string) ($r->user?->name ?? $r->guest_name ?? 'Guest');

            $poster = $r->event?->heroImageUrl()
                ?? ($isVenue ? (MediaUrl::resolveMany($r->venue?->images)[0] ?? null) : null);

            $payoutStatus = $r->payout?->status;
            $settled = in_array(strtolower((string) $payoutStatus), self::SETTLED, true);

            return [
                'title'     => $title,
                'who'       => $who,
                'avatar'    => AvatarColumn::initials($who),
                'poster'    => $poster,
                'type'      => $isVenue ? 'Turf' : 'Event',
                'amount'    => $this->inr((float) $r->total_amount),
                'status'    => BookingTablePresenter::statusLabel($r->status),
                'tone'      => BookingTablePresenter::statusColor($r->status),
                'settled'   => $settled,
                'settleLbl' => $settled ? 'Settled' : 'Awaiting payout',
                'payoutOn'  => $settled && $r->payout?->processed_at
                    ? 'Paid ' . $r->payout->processed_at->format('d M Y')
                    : null,
                'date'      => $r->created_at?->format('d M Y') ?? '',
            ];
        })->all();
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
