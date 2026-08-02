<?php

declare(strict_types=1);

namespace App\Filament\Resources\VenueBookings\Tables;

use App\Models\Booking;
use App\Services\BookingLedger;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * The chase list.
 *
 * Every column here answers a desk question: when are they coming, who are they,
 * which court, how did they book, and — the one that pays for the software — how
 * much are they still short.
 *
 * "Collect payment" and "Refund" write through {@see BookingLedger}, never
 * directly to the booking, so `amount_paid`/`payment_status` stay derived and the
 * ledger keeps its invariant.
 */
class VenueBookingsTable
{
    /** Money-collected statuses, matched case-insensitively (rows are mixed case). */
    private const LIVE = ['confirmed', 'paid', 'completed', 'checked_in'];

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('slot_date', 'desc')
            ->columns([
                TextColumn::make('slot_date')
                    ->label('When')
                    ->sortable()
                    ->formatStateUsing(fn (Booking $record): string => trim(sprintf(
                        '%s · %s',
                        self::dayLabel($record->slot_date),
                        (string) $record->start_time,
                    ), ' ·'))
                    ->description(fn (Booking $record): ?string => $record->end_time
                        ? 'until ' . $record->end_time
                        : null),

                TextColumn::make('customer')
                    ->label('Customer')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->where(fn (Builder $q) => $q
                            ->where('guest_name', 'like', "%{$search}%")
                            ->orWhere('guest_phone', 'like', "%{$search}%")
                            ->orWhere('attendee_name', 'like', "%{$search}%")
                            ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', "%{$search}%"))))
                    ->state(fn (Booking $record): string => $record->guest_name
                        ?: ($record->attendee_name ?: ($record->user?->name ?? 'Walk-in')))
                    ->description(fn (Booking $record): ?string => $record->guest_phone ?: $record->attendee_phone),

                TextColumn::make('venue.name')
                    ->label('Venue')
                    ->toggleable(isToggledHiddenByDefault: true),

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
                        default => ucfirst((string) $state),
                    })
                    ->color(fn (?string $state): string => $state === 'offline' ? 'gray' : 'primary'),

                TextColumn::make('total_amount')
                    ->label('Total')
                    ->alignEnd()
                    ->sortable()
                    ->formatStateUsing(fn ($state): string => self::inr((float) $state)),

                TextColumn::make('amount_paid')
                    ->label('Collected')
                    ->alignEnd()
                    ->sortable()
                    ->formatStateUsing(fn ($state): string => self::inr((float) $state))
                    ->color(fn (Booking $record): string => (float) $record->amount_paid <= 0 ? 'gray' : 'success'),

                TextColumn::make('payment_status')
                    ->label('Balance')
                    ->badge()
                    ->alignEnd()
                    ->formatStateUsing(fn (Booking $record): string => $record->hasBalanceDue()
                        ? self::inr($record->balanceDue()) . ' due'
                        : match ($record->payment_status) {
                            'paid' => 'Settled',
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

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => ucfirst(strtolower((string) $state)))
                    ->color(fn (?string $state): string => match (strtolower((string) $state)) {
                        'confirmed', 'paid', 'completed', 'checked_in' => 'success',
                        'cancelled', 'canceled' => 'danger',
                        default => 'gray',
                    })
                    ->toggleable(),
            ])
            ->filters([
                Filter::make('balance_due')
                    ->label('Balance due')
                    ->query(fn (Builder $query): Builder => $query
                        ->whereIn(\Illuminate\Support\Facades\DB::raw('lower(status)'), self::LIVE)
                        ->whereNotIn('payment_status', ['refunded', 'part_refunded'])
                        ->whereRaw('coalesce(amount_paid, 0) < total_amount')),

                Filter::make('upcoming')
                    ->label('Upcoming only')
                    ->query(fn (Builder $query): Builder => $query->whereDate('slot_date', '>=', today())),

                SelectFilter::make('payment_status')
                    ->label('Payment')
                    ->options([
                        'unpaid' => 'Unpaid',
                        'partial' => 'Partly paid',
                        'paid' => 'Settled',
                        'refunded' => 'Refunded',
                        'part_refunded' => 'Part refunded',
                    ]),

                SelectFilter::make('channel')
                    ->label('Source')
                    ->options(['online' => 'App', 'offline' => 'Walk-in']),
            ])
            ->recordActions([
                Action::make('collect')
                    ->label('Collect')
                    ->icon('heroicon-m-banknotes')
                    ->color('success')
                    ->visible(fn (Booking $record): bool => $record->hasBalanceDue())
                    ->modalHeading(fn (Booking $record): string => 'Collect ' . self::inr($record->balanceDue()))
                    ->modalSubmitActionLabel('Record payment')
                    ->schema([
                        TextInput::make('amount')
                            ->label('Amount received')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            // Defaults to the whole balance — the common case is settling in full.
                            ->default(fn (Booking $record): float => $record->balanceDue())
                            ->helperText(fn (Booking $record): string => 'Balance due is ' . self::inr($record->balanceDue())
                                . '. Enter less to record a part payment.'),
                        Select::make('method')
                            ->label('How was it paid?')
                            ->options([
                                'cash' => 'Cash',
                                'upi' => 'UPI',
                                'card' => 'Card',
                                'online' => 'Online / gateway',
                            ])
                            ->default('cash')
                            ->native(false)
                            ->required(),
                        TextInput::make('reference')
                            ->label('Reference')
                            ->placeholder('UPI ref, receipt number…')
                            ->maxLength(120),
                        Textarea::make('note')->label('Note')->rows(2)->maxLength(200),
                    ])
                    ->action(function (Booking $record, array $data, BookingLedger $ledger): void {
                        $ledger->collect(
                            $record,
                            (float) $data['amount'],
                            (string) $data['method'],
                            auth()->user(),
                            $data['reference'] ?? null,
                            $data['note'] ?? null,
                        );

                        $record->refresh();

                        Notification::make()
                            ->success()
                            ->title(self::inr((float) $data['amount']) . ' recorded')
                            ->body($record->hasBalanceDue()
                                ? self::inr($record->balanceDue()) . ' still due.'
                                : 'This booking is now fully settled.')
                            ->send();
                    }),

                Action::make('refund')
                    ->label('Refund')
                    ->icon('heroicon-m-arrow-uturn-left')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Record a refund')
                    ->modalDescription('This records the money going back out. It does not call the payment gateway.')
                    ->visible(fn (Booking $record): bool => (float) $record->amount_paid > 0)
                    ->schema([
                        TextInput::make('amount')
                            ->label('Amount refunded')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->default(fn (Booking $record): float => (float) $record->amount_paid)
                            ->maxValue(fn (Booking $record): float => (float) $record->amount_paid)
                            ->helperText(fn (Booking $record): string => 'At most ' . self::inr((float) $record->amount_paid)
                                . ' — that is everything collected so far.'),
                        Select::make('method')
                            ->label('Refunded by')
                            ->options(['cash' => 'Cash', 'upi' => 'UPI', 'online' => 'Online / gateway'])
                            ->default('upi')
                            ->native(false)
                            ->required(),
                        Textarea::make('note')->label('Reason')->rows(2)->maxLength(200),
                    ])
                    ->action(function (Booking $record, array $data, BookingLedger $ledger): void {
                        $ledger->refund(
                            $record,
                            (float) $data['amount'],
                            (string) $data['method'],
                            auth()->user(),
                            null,
                            $data['note'] ?? null,
                        );

                        Notification::make()
                            ->warning()
                            ->title(self::inr((float) $data['amount']) . ' refunded')
                            ->send();
                    }),

                Action::make('history')
                    ->label('Payments')
                    ->icon('heroicon-m-clock')
                    ->color('gray')
                    ->modalHeading('Payment history')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->visible(fn (Booking $record): bool => $record->payments()->exists())
                    ->modalContent(fn (Booking $record) => view('filament.venue.payment-history', [
                        'booking' => $record,
                        'payments' => $record->payments()->with('collector')->latest('collected_at')->get(),
                    ])),
            ])
            ->emptyStateHeading('No bookings yet')
            ->emptyStateDescription('Reservations from the app, the desk and WhatsApp all land here.')
            ->emptyStateIcon('heroicon-o-banknotes');
    }

    /** "Today", "Tomorrow", or "Sat, 02 Aug". */
    private static function dayLabel(mixed $date): string
    {
        if ($date === null) {
            return '—';
        }

        $day = Carbon::parse($date);

        return match (true) {
            $day->isToday() => 'Today',
            $day->isTomorrow() => 'Tomorrow',
            $day->isYesterday() => 'Yesterday',
            default => $day->format('D, d M'),
        };
    }

    /** ₹1,84,200 — Indian digit grouping. */
    private static function inr(float $n): string
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
