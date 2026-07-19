<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Partner;

use App\Filament\Support\BookingTablePresenter;
use App\Models\Booking;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * "What just came in" for the partner home — the latest bookings against the
 * partner's own events (event lane) or venues (venue lane), scoped so nothing
 * from other partners ever leaks. Read-only glance; deep links live in the
 * Bookings / Day-bookings sections.
 */
class PartnerRecentBookingsWidget extends TableWidget
{
    use \App\Filament\Concerns\ScopesToPartnerEvents;
    use \App\Filament\Concerns\ScopesToPartnerVenues;

    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    // Render eagerly — on the short dashboard grid a lazy table never intersects.
    protected static bool $isLazy = false;

    protected function getTableHeading(): ?string
    {
        return 'Recent bookings';
    }

    private function isEventLane(): bool
    {
        return auth()->user()?->partner_type === 'event';
    }

    protected function getTableQuery(): Builder
    {
        $base = $this->isEventLane() ? $this->scopedBookingQuery() : $this->scopedVenueBookingQuery();

        return $base->with(['user', 'event', 'venue'])->latest()->limit(8);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
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
        if ($r->event) {
            return $r->event->title;
        }

        if ($r->venue) {
            return trim($r->venue->name . ($r->slot_label ? ' · ' . $r->slot_label : ''));
        }

        return null;
    }
}
