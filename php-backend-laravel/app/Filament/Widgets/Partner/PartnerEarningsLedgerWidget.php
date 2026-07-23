<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Partner;

use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\Venues\VenueResource;
use App\Filament\Support\BookingTablePresenter;
use App\Models\Booking;
use Filament\Facades\Filament;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * The earnings ledger — every paying booking against the partner's events or
 * venues as a money row (what, who, how much, settled?). Derived from bookings
 * so it's always complete, unlike the sparse payouts table.
 */
class PartnerEarningsLedgerWidget extends TableWidget
{
    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    protected static bool $isLazy = false;

    /** Statuses that represent money actually collected. */
    private const PAID = ['confirmed', 'paid', 'completed', 'checked_in'];

    protected function getTableHeading(): ?string
    {
        return 'Earnings ledger';
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

    public function table(Table $table): Table
    {
        return $table
            ->query(
                $this->partnerBookings()
                    ->whereIn(\Illuminate\Support\Facades\DB::raw('lower(status)'), self::PAID)
                    ->with(['user', 'event', 'venue', 'payout'])
                    ->latest()
            )
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading('No earnings yet')
            ->emptyStateDescription('Paid bookings against your events and venues will appear here.')
            ->emptyStateIcon('heroicon-o-banknotes')
            // One money row = one card (what/who, then a meta row of amount ·
            // type · status · settlement · date). Reads as a ledger list on
            // phones instead of a 6-column table that scrolls sideways.
            ->contentGrid(['default' => 1])
            ->columns([
                Split::make([
                    Stack::make([
                        TextColumn::make('for_title')
                            ->state(fn (Booking $r): string => $r->event?->title ?? $r->venue?->name ?? '—')
                            ->description(fn (Booking $r): ?string => $r->user?->name)
                            ->weight('bold')
                            ->wrap(),

                        Split::make([
                            TextColumn::make('total_amount')
                                ->money('INR')
                                ->weight('bold')
                                ->color('primary')
                                ->sortable()
                                ->grow(false),

                            TextColumn::make('booking_type')
                                ->badge()
                                ->state(fn (Booking $r): string => $r->venue_id ? 'Turf' : 'Event')
                                ->color(fn (Booking $r): string => $r->venue_id ? 'success' : 'primary')
                                ->grow(false),

                            BookingTablePresenter::statusColumn()
                                ->grow(false),

                            TextColumn::make('payout.status')
                                ->badge()
                                ->placeholder('Unsettled')
                                ->formatStateUsing(fn (?string $s): string => $s ? ucfirst(strtolower($s)) : 'Unsettled')
                                ->color(fn (?string $s): string => in_array(strtolower((string) $s), ['paid', 'processed', 'completed'], true) ? 'success' : 'warning')
                                ->grow(false),

                            TextColumn::make('created_at')
                                ->date('d M Y')
                                ->color('gray')
                                ->size('sm')
                                ->sortable(),
                        ])->grow(false),
                    ]),
                ]),
            ]);
    }
}
