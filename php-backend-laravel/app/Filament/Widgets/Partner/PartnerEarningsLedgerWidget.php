<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Partner;

use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\Venues\VenueResource;
use App\Filament\Support\BookingTablePresenter;
use App\Models\Booking;
use Filament\Facades\Filament;
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
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('id')
                    ->label('For')
                    ->formatStateUsing(fn (Booking $r): string => $r->event?->title ?? $r->venue?->name ?? '—')
                    ->description(fn (Booking $r): ?string => $r->user?->name)
                    ->wrap(),
                TextColumn::make('id')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (Booking $r): string => $r->venue_id ? 'Turf' : 'Event')
                    ->color(fn (Booking $r): string => $r->venue_id ? 'success' : 'primary'),
                TextColumn::make('total_amount')
                    ->label('Amount')
                    ->money('INR')
                    ->weight('bold')
                    ->alignEnd()
                    ->sortable(),
                BookingTablePresenter::statusColumn(),
                TextColumn::make('payout.status')
                    ->label('Settlement')
                    ->badge()
                    ->placeholder('Pending')
                    ->formatStateUsing(fn (?string $s): string => $s ? ucfirst(strtolower($s)) : 'Pending')
                    ->color(fn (?string $s): string => in_array(strtolower((string) $s), ['paid', 'processed', 'completed'], true) ? 'success' : 'warning'),
            ]);
    }
}
