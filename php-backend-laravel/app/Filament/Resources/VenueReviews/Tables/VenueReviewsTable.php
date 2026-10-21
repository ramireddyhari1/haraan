<?php

declare(strict_types=1);

namespace App\Filament\Resources\VenueReviews\Tables;

use App\Models\Venue;
use App\Models\VenueReview;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class VenueReviewsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('rating')
                    ->label('Rating')
                    ->state(fn (VenueReview $record): string => str_repeat('★', (int) $record->rating) . str_repeat('☆', 5 - (int) $record->rating))
                    ->color(fn (VenueReview $record): string => match (true) {
                        (int) $record->rating <= 2 => 'danger',
                        (int) $record->rating === 3 => 'warning',
                        default => 'success',
                    })
                    ->sortable(query: fn ($query, string $direction) => $query->orderBy('rating', $direction)),

                TextColumn::make('text')
                    ->label('Review')
                    ->placeholder('Stars only — no comment')
                    ->limit(110)
                    ->tooltip(fn (VenueReview $record): ?string => mb_strlen((string) $record->text) > 110 ? $record->text : null)
                    ->wrap()
                    ->searchable(),

                TextColumn::make('venue.name')
                    ->label('Venue')
                    ->description(fn (VenueReview $record): string => (string) ($record->venue?->location ?? ''))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label('By')
                    ->description(fn (VenueReview $record): string => $record->user?->email ?? ($record->booking_id ? 'Booking #' . $record->booking_id : 'Guest'))
                    ->searchable(['name']),

                IconColumn::make('booking_id')
                    ->label('Played here')
                    ->state(fn (VenueReview $record): bool => $record->booking_id !== null)
                    ->boolean()
                    ->trueIcon('heroicon-s-check-badge')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->tooltip(fn (VenueReview $record): string => $record->booking_id ? 'Tied to a real booking at this venue' : 'No booking at this venue'),

                TextColumn::make('source')
                    ->label('From')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        VenueReview::SOURCE_APP => 'App',
                        VenueReview::SOURCE_WEB => 'Website',
                        VenueReview::SOURCE_LINK => 'Booking link',
                        default => 'Earlier',
                    })
                    ->color('gray'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (VenueReview $record): string => match (true) {
                        $record->trashed() => 'Deleted',
                        ! $record->is_active => 'Hidden',
                        default => 'Live',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Deleted' => 'gray',
                        'Hidden' => 'warning',
                        default => 'success',
                    })
                    ->description(fn (VenueReview $record): ?string => ! $record->is_active && $record->hidden_reason ? $record->hidden_reason : null),

                TextColumn::make('created_at')
                    ->label('Posted')
                    ->since()
                    ->dateTimeTooltip('d M Y, g:i A', 'Asia/Kolkata')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('venue_id')
                    ->label('Venue')
                    ->options(fn (): array => Venue::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable(),
                SelectFilter::make('rating')
                    ->label('Stars')
                    ->options([5 => '5 ★', 4 => '4 ★', 3 => '3 ★', 2 => '2 ★', 1 => '1 ★']),
                SelectFilter::make('source')
                    ->label('From')
                    ->options([
                        VenueReview::SOURCE_APP => 'App',
                        VenueReview::SOURCE_WEB => 'Website',
                        VenueReview::SOURCE_LINK => 'Booking link',
                    ]),
                TernaryFilter::make('played')
                    ->label('Played here')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('booking_id'),
                        false: fn ($query) => $query->whereNull('booking_id'),
                    ),
            ])
            ->recordActions([
                Action::make('hide')
                    ->label('Hide')
                    ->icon('heroicon-o-eye-slash')
                    ->color('warning')
                    ->visible(fn (VenueReview $record): bool => ! $record->trashed() && $record->is_active)
                    ->schema([
                        TextInput::make('reason')
                            ->label('Why (admins only)')
                            ->placeholder('Abusive language, spam, not about this venue…')
                            ->maxLength(160),
                    ])
                    ->action(function (VenueReview $record, array $data): void {
                        $record->forceFill(['is_active' => false, 'hidden_reason' => $data['reason'] ?: null])->save();
                        Notification::make()->title('Review hidden — it no longer counts toward the venue rating')->success()->send();
                    }),
                Action::make('show')
                    ->label('Show')
                    ->icon('heroicon-o-eye')
                    ->color('success')
                    ->visible(fn (VenueReview $record): bool => ! $record->trashed() && ! $record->is_active)
                    ->action(function (VenueReview $record): void {
                        $record->forceFill(['is_active' => true, 'hidden_reason' => null])->save();
                        Notification::make()->title('Review is live again')->success()->send();
                    }),
                ActionGroup::make([
                    Action::make('openVenue')
                        ->label('Open venue page')
                        ->icon('heroicon-o-arrow-top-right-on-square')
                        ->url(fn (VenueReview $record): ?string => $record->venue ? \App\Support\SeoUrl::venue($record->venue) : null, shouldOpenInNewTab: true),
                    DeleteAction::make()->label('Delete')->modalDescription('It disappears from the venue page and the rating. You can restore it from "Deleted".'),
                    RestoreAction::make(),
                    ForceDeleteAction::make()->label('Delete forever'),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('hideMany')
                        ->label('Hide selected')
                        ->icon('heroicon-o-eye-slash')
                        ->color('warning')
                        ->action(fn (Collection $records) => $records->each(fn (VenueReview $r) => $r->forceFill(['is_active' => false])->save()))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('showMany')
                        ->label('Show selected')
                        ->icon('heroicon-o-eye')
                        ->color('success')
                        ->action(fn (Collection $records) => $records->each(fn (VenueReview $r) => $r->forceFill(['is_active' => true, 'hidden_reason' => null])->save()))
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make()->label('Delete forever'),
                ]),
            ])
            ->emptyStateHeading('No reviews here')
            ->emptyStateDescription('Reviews from the app, the website and booking links land here the moment they are posted.')
            ->emptyStateIcon('heroicon-o-star');
    }
}
