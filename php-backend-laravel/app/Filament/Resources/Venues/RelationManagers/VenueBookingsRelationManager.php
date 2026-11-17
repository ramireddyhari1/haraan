<?php

declare(strict_types=1);

namespace App\Filament\Resources\Venues\RelationManagers;

use App\Filament\Resources\Venues\Schemas\VenueForm;
use App\Models\Booking;
use App\Models\Venue;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Tables\Enums\FiltersLayout;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Court-hour bookings taken here, read-only.
 *
 * Read-only on purpose: a booking is created through
 * {@see \App\Services\BookingService}, which runs the court-hour conflict engine,
 * and money against it moves only through {@see \App\Services\BookingLedger}.
 * A row typed in here would bypass both — it would double-book a court and
 * report a payment that no ledger entry backs. The Day bookings grid is the
 * place to take a booking; this is the place to answer questions about one.
 */
class VenueBookingsRelationManager extends RelationManager
{
    protected static string $relationship = 'bookings';

    protected static ?string $title = 'Bookings & payments';

    protected static string | \BackedEnum | null $icon = 'heroicon-o-banknotes';

    /** Statuses that represent money taken. Prod casing is mixed. */
    private const PAID = ['confirmed', 'paid', 'completed', 'checked_in'];

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $upcoming = $ownerRecord->bookings()
            ->whereIn(DB::raw('lower(status)'), self::PAID)
            ->whereDate('slot_date', '>=', now()->toDateString())
            ->count();

        return $upcoming > 0 ? (string) $upcoming : null;
    }

    private function venue(): Venue
    {
        /** @var Venue $venue */
        $venue = $this->getOwnerRecord();

        return $venue;
    }

    /** "On a ₹1,000 booking: ₹200 online now (plus fees), ₹800 at the venue." */
    private static function example(float $pct): string
    {
        if ($pct <= 0 || $pct >= 100) {
            return '';
        }

        $advance = (int) ceil(1000 * $pct / 100);

        return 'On a ₹1,000 booking: ₹' . number_format($advance) . ' online now (plus fees), ₹' . number_format(1000 - $advance) . ' at the venue.';
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('ticket_code')
            ->defaultSort('slot_date', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['user']))
            ->description(fn (): string => 'Advance payment: ' . ($this->venue()->advanceLabel() ?? 'off — customers pay the full amount online'))
            ->headerActions([
                // Haraan's call, not the partner's: the share of the court price a customer may
                // pay online as an advance, the rest at the venue (Venue::payAtVenueFor()).
                Action::make('advancePayment')
                    ->label(fn (): string => $this->venue()->offersAdvance()
                        ? 'Advance ' . rtrim(rtrim(number_format($this->venue()->advancePercent(), 2), '0'), '.') . '%'
                        : 'Advance payment')
                    ->icon('heroicon-o-banknotes')
                    ->color(fn (): string => $this->venue()->offersAdvance() ? 'primary' : 'gray')
                    ->modalHeading('Advance payment')
                    ->modalDescription('Customers choose at checkout: pay only the advance online and the rest at the venue, or pay in full. Applies to new bookings only.')
                    ->modalWidth('lg')
                    ->modalSubmitActionLabel('Save')
                    ->fillForm(fn (): array => [
                        'enabled' => $this->venue()->offersAdvance(),
                        'advance_percent' => $this->venue()->offersAdvance() ? $this->venue()->advancePercent() : 20,
                    ])
                    ->schema([
                        Toggle::make('enabled')
                            ->label('Offer advance payment')
                            ->live(),
                        TextInput::make('advance_percent')
                            ->label('Advance (% of the court price)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(99)
                            ->suffix('%')
                            ->live(onBlur: true)
                            ->required(fn ($get): bool => (bool) $get('enabled'))
                            ->visible(fn ($get): bool => (bool) $get('enabled'))
                            ->helperText(fn ($get): string => trim(self::example((float) ($get('advance_percent') ?: 0)) . ' ' . VenueForm::ADVANCE_HELP)),
                    ])
                    ->action(function (array $data): void {
                        $venue = $this->venue();
                        $venue->advance_percent = ! empty($data['enabled']) ? round((float) $data['advance_percent'], 2) : null;
                        $venue->save();

                        Notification::make()
                            ->title($venue->offersAdvance() ? 'Advance payment on' : 'Advance payment off')
                            ->body($venue->advanceLabel() ?? 'Customers pay the full amount online.')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (): bool => Filament::getCurrentPanel()?->getId() !== 'partner'),
            ])
            ->emptyStateHeading('No bookings yet')
            ->emptyStateIcon('heroicon-o-calendar-days')
            ->columns([
                TextColumn::make('slot_date')
                    ->label('Date')
                    ->date('D d M Y')
                    ->description(fn (Booking $record): string => $record->slot_label
                        ?: implode('–', array_filter([$record->start_time, $record->end_time])))
                    ->sortable(),

                TextColumn::make('ticket_code')
                    ->label('Reference')
                    ->copyable()
                    ->placeholder('—')
                    ->searchable(),

                TextColumn::make('customer')
                    ->label('Customer')
                    ->state(fn (Booking $record): string => $record->user?->name
                        ?: ($record->guest_name ?: 'Walk-in'))
                    ->description(fn (Booking $record): ?string => $record->guest_phone
                        ?: $record->user?->phone)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->where('guest_name', 'like', "%{$search}%")
                        ->orWhereHas('user', fn (Builder $q) => $q->where('name', 'like', "%{$search}%"))),

                TextColumn::make('channel')
                    ->label('Channel')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state ? ucfirst($state) : 'App')
                    ->color(fn (?string $state): string => $state === 'walk_in' ? 'warning' : 'gray'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => ucfirst(strtolower((string) $state)))
                    ->color(fn (?string $state): string => match (strtolower((string) $state)) {
                        'confirmed', 'paid', 'completed', 'checked_in' => 'success',
                        'pending' => 'warning',
                        default => 'danger',
                    })
                    ->sortable(),

                TextColumn::make('total_amount')
                    ->label('Order total')
                    ->money('INR')
                    ->sortable(),

                TextColumn::make('pay_at_venue')
                    ->label('Pay at venue')
                    ->money('INR')
                    ->description(fn (Booking $record): ?string => (float) $record->pay_at_venue > 0
                        ? '₹' . number_format($record->onlineDue(), 2) . ' advance online'
                        : null)
                    ->color(fn (Booking $record): string => (float) $record->pay_at_venue > 0 ? 'warning' : 'gray')
                    ->toggleable(),

                TextColumn::make('amount_paid')
                    ->label('Collected')
                    ->money('INR')
                    ->description(fn (Booking $record): string => $record->payments()->count()
                        . ' ledger ' . str('entry')->plural($record->payments()->count()))
                    ->color('success'),

                TextColumn::make('balance_due')
                    ->label('Balance due')
                    ->state(fn (Booking $record): float => $record->balanceDue())
                    ->money('INR')
                    ->badge()
                    ->color(fn (Booking $record): string => $record->balanceDue() > 0.009 ? 'danger' : 'gray'),

                TextColumn::make('checked_in_count')
                    ->label('Checked in')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('razorpay_payment_id')
                    ->label('Gateway payment')
                    ->placeholder('Desk / offline')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'CONFIRMED' => 'Confirmed',
                        'PENDING'   => 'Pending',
                        'CANCELLED' => 'Cancelled',
                        'REFUNDED'  => 'Refunded',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereRaw('lower(status) = ?', [strtolower((string) $data['value'])])
                        : $query),

                Filter::make('upcoming')
                    ->label('Upcoming only')
                    ->query(fn (Builder $query): Builder => $query
                        ->whereDate('slot_date', '>=', now()->toDateString())),

                Filter::make('owing')
                    ->label('Money still owed')
                    ->query(fn (Builder $query): Builder => $query
                        ->whereColumn('amount_paid', '<', 'total_amount')),
            ], layout: FiltersLayout::AboveContent);
    }
}
