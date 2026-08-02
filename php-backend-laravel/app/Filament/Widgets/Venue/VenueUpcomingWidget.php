<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Venue;

use App\Filament\Widgets\Venue\Concerns\ScopesToVenueLane;
use App\Models\Booking;
use Illuminate\Support\Carbon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * What's coming through the gate next, and who still owes money when they do.
 *
 * The balance column is the reason this widget exists rather than a plain
 * "recent bookings" list: the front desk needs to know, before the customer walks
 * up, that this is the ₹3,900 one. That is only answerable now that payment state
 * is separate from booking status.
 */
class VenueUpcomingWidget extends TableWidget
{
    use ScopesToVenueLane;

    protected static ?int $sort = 4;

    protected static bool $isLazy = false;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Next up')
            ->description('The next reservations at your venues')
            ->query(
                $this->liveBookings()
                    ->where('slot_date', '>=', Carbon::today())
                    ->orderBy('slot_date')
                    ->orderBy('start_time')
                    ->limit(8)
            )
            ->paginated(false)
            ->columns([
                TextColumn::make('slot_date')
                    ->label('When')
                    ->formatStateUsing(fn (Booking $record): string => trim(sprintf(
                        '%s · %s',
                        Carbon::parse($record->slot_date)->isToday()
                            ? 'Today'
                            : Carbon::parse($record->slot_date)->format('D, d M'),
                        (string) $record->start_time,
                    ), ' ·')),

                TextColumn::make('customer')
                    ->label('Customer')
                    ->state(fn (Booking $record): string => $record->guest_name
                        ?: ($record->attendee_name ?: ($record->user?->name ?? 'Walk-in')))
                    ->description(fn (Booking $record): ?string => $record->guest_phone),

                TextColumn::make('venueCourt.name')
                    ->label('Court')
                    ->placeholder('—'),

                TextColumn::make('channel')
                    ->label('Source')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'offline' => 'Walk-in',
                        'online' => 'App',
                        null, '' => '—',
                        default => ucfirst($state),
                    })
                    ->color(fn (?string $state): string => $state === 'offline' ? 'gray' : 'primary'),

                TextColumn::make('total_amount')
                    ->label('Amount')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => $this->inr((float) $state)),

                TextColumn::make('payment_status')
                    ->label('Payment')
                    ->badge()
                    ->formatStateUsing(fn (Booking $record): string => $record->hasBalanceDue()
                        ? $this->inr($record->balanceDue()) . ' due'
                        : match ($record->payment_status) {
                            'paid' => 'Paid',
                            'refunded' => 'Refunded',
                            'part_refunded' => 'Part refunded',
                            default => 'Unpaid',
                        })
                    ->color(fn (Booking $record): string => match (true) {
                        $record->hasBalanceDue() => 'warning',
                        $record->payment_status === 'paid' => 'success',
                        in_array($record->payment_status, ['refunded', 'part_refunded'], true) => 'danger',
                        default => 'gray',
                    }),
            ])
            ->emptyStateHeading('Nothing booked yet')
            ->emptyStateDescription('Reservations from the app, the desk and WhatsApp all land here.')
            ->emptyStateIcon('heroicon-o-calendar-days');
    }
}
