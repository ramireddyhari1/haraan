<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Support\BookingTablePresenter;
use App\Models\Booking;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * The dashboard's activity strip — the "what just happened" feed that sits under the
 * overview cards, the way Razorpay surfaces recent settlements/updates. Read-only:
 * a glance, with a click-through to the full Bookings resource for anything deeper.
 */
class LatestBookingsWidget extends TableWidget
{
    use \App\Filament\Concerns\RefreshesOnContentUpdate;

    protected static ?int $sort = -10;

    protected int | string | array $columnSpan = 'full';

    protected function getTableHeading(): ?string
    {
        return 'Latest bookings';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Booking::query()
                    ->with(['user', 'event', 'venue'])
                    ->latest()
                    ->limit(8)
            )
            ->paginated(false)
            ->emptyStateHeading('No bookings yet')
            ->emptyStateDescription('Bookings from the app and walk-ins will appear here as they come in.')
            ->emptyStateIcon('heroicon-o-calendar-days')
            ->columns([
                BookingTablePresenter::customerAvatarColumn(),
                TextColumn::make('user.name')
                    ->label('Customer')
                    ->weight('bold')
                    ->description(fn (Booking $r): ?string => $this->lineFor($r))
                    ->placeholder('Guest'),
                TextColumn::make('booking_type')
                    ->label('For')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state === 'venue' ? 'Turf' : 'Event')
                    ->color(fn (?string $state): string => $state === 'venue' ? 'success' : 'primary'),
                TextColumn::make('quantity')
                    ->label('Qty')
                    ->numeric()
                    ->alignEnd(),
                TextColumn::make('total_amount')
                    ->label('Amount')
                    ->money('INR')
                    ->weight('bold')
                    ->alignEnd(),
                BookingTablePresenter::statusColumn(),
                TextColumn::make('created_at')
                    ->label('When')
                    ->since()
                    ->tooltip(fn (Booking $r): string => $r->created_at->format('d M Y, H:i')),
            ]);
    }

    /** A human line for the booking's target — event title or venue + slot. */
    private function lineFor(Booking $r): ?string
    {
        if ($r->booking_type === 'venue') {
            return trim(($r->venue?->name ?? 'Venue') . ($r->slot_label ? " · {$r->slot_label}" : ''));
        }

        return $r->event?->title;
    }
}
