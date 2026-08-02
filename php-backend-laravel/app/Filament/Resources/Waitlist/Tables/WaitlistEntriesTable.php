<?php

declare(strict_types=1);

namespace App\Filament\Resources\Waitlist\Tables;

use App\Models\WaitlistEntry;
use App\Services\WaitlistService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The waitlist, sorted so live offers float to the top — those are the ones with
 * a clock running.
 */
class WaitlistEntriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Offered first, then oldest request — the order you should work the list.
            ->defaultSort('offered_at', 'desc')
            ->columns([
                TextColumn::make('guest_name')
                    ->label('Who')
                    ->state(fn (WaitlistEntry $record): string => $record->contactName())
                    ->description(fn (WaitlistEntry $record): ?string => $record->contactPhone())
                    ->searchable(['guest_name', 'guest_phone'])
                    ->weight('medium'),

                TextColumn::make('wanted_on')
                    ->label('Wants')
                    ->state(fn (WaitlistEntry $record): string => $record->windowLabel())
                    ->description(fn (WaitlistEntry $record): string => $record->court?->name ?? 'Any court')
                    ->sortable(),

                TextColumn::make('venue.name')
                    ->label('Venue')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => WaitlistEntry::STATUSES[$state] ?? ucfirst((string) $state))
                    ->color(fn (?string $state): string => match ($state) {
                        WaitlistEntry::STATUS_OFFERED => 'success',
                        WaitlistEntry::STATUS_CONVERTED => 'primary',
                        WaitlistEntry::STATUS_WAITING => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('offer_expires_at')
                    ->label('Offer')
                    ->placeholder('—')
                    ->state(function (WaitlistEntry $record): ?string {
                        if ($record->status !== WaitlistEntry::STATUS_OFFERED) {
                            return null;
                        }

                        return $record->isOfferLive()
                            ? 'expires ' . $record->offer_expires_at?->diffForHumans(short: true)
                            : 'lapsed';
                    })
                    ->color(fn (WaitlistEntry $record): string => $record->isOfferLive() ? 'success' : 'danger'),

                TextColumn::make('notified_at')
                    ->label('Told them')
                    ->placeholder('Not yet')
                    ->since()
                    ->color(fn (WaitlistEntry $record): string => $record->notified_at === null ? 'danger' : 'gray'),

                TextColumn::make('created_at')
                    ->label('Asked')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('active')
                    ->label('Still in play')
                    ->default()
                    ->query(fn (Builder $query): Builder => $query->active()),

                SelectFilter::make('status')
                    ->options(WaitlistEntry::STATUSES),
            ])
            ->recordActions([
                Action::make('notified')
                    ->label('Mark told')
                    ->icon('heroicon-m-chat-bubble-left-right')
                    ->color('success')
                    ->visible(fn (WaitlistEntry $record): bool => $record->status === WaitlistEntry::STATUS_OFFERED
                        && $record->notified_at === null)
                    ->action(function (WaitlistEntry $record, WaitlistService $waitlist): void {
                        $waitlist->markNotified($record);

                        Notification::make()
                            ->success()
                            ->title('Marked as contacted')
                            ->body('The offer stays live until it expires.')
                            ->send();
                    }),

                Action::make('booked')
                    ->label('They took it')
                    ->icon('heroicon-m-check-circle')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Mark this as booked')
                    ->modalDescription('Use this once you have actually taken the booking on the Day bookings grid — this only closes the waitlist entry.')
                    ->visible(fn (WaitlistEntry $record): bool => $record->status === WaitlistEntry::STATUS_OFFERED)
                    ->action(function (WaitlistEntry $record): void {
                        $record->forceFill(['status' => WaitlistEntry::STATUS_CONVERTED])->save();

                        Notification::make()->success()->title('Recovered from the waitlist')->send();
                    }),

                Action::make('passed')
                    ->label('Passed')
                    ->icon('heroicon-m-x-mark')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription('They do not want it. The slot stays available for whoever else was offered it.')
                    ->visible(fn (WaitlistEntry $record): bool => in_array(
                        $record->status,
                        [WaitlistEntry::STATUS_OFFERED, WaitlistEntry::STATUS_WAITING],
                        true,
                    ))
                    ->action(function (WaitlistEntry $record, WaitlistService $waitlist): void {
                        $waitlist->cancel($record);

                        Notification::make()->title('Removed from the waitlist')->send();
                    }),

                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('Nobody waiting')
            ->emptyStateDescription('Add anyone who asks for a slot that is already sold. When it frees up, they are offered it automatically.')
            ->emptyStateIcon('heroicon-o-queue-list');
    }
}
