<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Partner;

use App\Filament\Support\BookingTablePresenter;
use App\Models\Booking;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
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
            // One booking = one card (avatar + who/what, then a meta row of
            // amount · qty · status · when). Reads as a clean list on phones
            // instead of a 6-column table that scrolls sideways.
            ->contentGrid(['default' => 1])
            ->columns([
                Split::make([
                    BookingTablePresenter::customerAvatarColumn()
                        ->grow(false),

                    Stack::make([
                        TextColumn::make('user.name')
                            ->label('Customer')
                            ->weight('bold')
                            ->description(fn (Booking $r): ?string => $this->lineFor($r))
                            ->placeholder('Guest')
                            ->wrap(),

                        Split::make([
                            TextColumn::make('total_amount')
                                ->money('INR')
                                ->weight('bold')
                                ->color('primary')
                                ->grow(false),

                            TextColumn::make('quantity')
                                ->badge()
                                ->color('gray')
                                ->formatStateUsing(fn (?int $state): string => '×' . max(1, (int) $state))
                                ->grow(false),

                            BookingTablePresenter::statusColumn()
                                ->grow(false),

                            TextColumn::make('created_at')
                                ->since()
                                ->color('gray')
                                ->size('sm')
                                ->tooltip(fn (Booking $r): string => $r->created_at->format('d M Y, H:i')),
                        ])->grow(false),
                    ]),
                ]),
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
