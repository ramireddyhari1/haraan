<?php

declare(strict_types=1);

namespace App\Filament\Resources\VenueCandidates\Tables;

use App\Filament\Resources\Venues\VenueResource;
use App\Models\VenueCandidate;
use App\Models\VenueInterest;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;

class VenueCandidatesTable
{
    private const SPORTS = ['Cricket', 'Football', 'Badminton', 'Pickleball', 'Tennis', 'Basketball', 'Volleyball'];

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label('Venue')
                    ->description(fn (VenueCandidate $record): string => (string) $record->address)
                    ->wrap()
                    ->searchable(['name', 'address'])
                    ->sortable(),

                TextColumn::make('area')
                    ->label('Area')
                    ->description(fn (VenueCandidate $record): string => (string) $record->city)
                    ->placeholder('—')
                    ->searchable(['area', 'city'])
                    ->sortable(),

                TextColumn::make('sports')
                    ->label('Sports')
                    ->badge()
                    ->color('gray')
                    ->placeholder('Not clear from the name'),

                TextColumn::make('crosscheck_status')
                    ->label('Playo')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        VenueCandidate::CHECK_MATCHED => 'Also on Playo',
                        VenueCandidate::CHECK_UNMATCHED => 'Not on Playo',
                        default => 'Not checked',
                    })
                    ->color(fn (string $state): string => $state === VenueCandidate::CHECK_MATCHED ? 'success' : 'gray')
                    ->description(fn (VenueCandidate $record): ?string => $record->crosscheck_name)
                    ->sortable(),

                TextColumn::make('phone')
                    ->label('Phone')
                    ->placeholder('None on Google')
                    ->copyable()
                    ->toggleable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        VenueCandidate::STATUS_APPROVED => 'Listed',
                        VenueCandidate::STATUS_REJECTED => 'Rejected',
                        VenueCandidate::STATUS_DUPLICATE => 'Already on Haraan',
                        default => 'To review',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        VenueCandidate::STATUS_APPROVED => 'success',
                        VenueCandidate::STATUS_REJECTED => 'danger',
                        VenueCandidate::STATUS_DUPLICATE => 'gray',
                        default => 'warning',
                    }),

                TextColumn::make('requests_count')
                    ->label('Asked to book')
                    ->state(fn (VenueCandidate $record): ?string => (int) $record->requests_count > 0 ? (string) $record->requests_count : null)
                    ->placeholder('—')
                    ->sortable(query: fn ($query, string $direction) => $query->orderBy('requests_count', $direction)),

                TextColumn::make('created_at')
                    ->label('Found')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('city')
                    ->label('City')
                    ->options(fn (): array => VenueCandidate::query()->whereNotNull('city')->distinct()->orderBy('city')->pluck('city', 'city')->all())
                    ->searchable(),
                SelectFilter::make('crosscheck_status')
                    ->label('Playo')
                    ->options([
                        VenueCandidate::CHECK_MATCHED => 'Also on Playo',
                        VenueCandidate::CHECK_UNMATCHED => 'Not on Playo',
                        VenueCandidate::CHECK_UNCHECKED => 'Not checked',
                    ]),
                SelectFilter::make('sport')
                    ->label('Sport')
                    ->options(array_combine(self::SPORTS, self::SPORTS))
                    ->query(fn ($query, array $data) => filled($data['value'] ?? null)
                        ? $query->where('sports', 'like', '%"' . $data['value'] . '"%')
                        : $query),
                TernaryFilter::make('has_phone')
                    ->label('Phone number')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('phone'),
                        false: fn ($query) => $query->whereNull('phone'),
                    ),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('List it')
                    ->icon('heroicon-o-check')
                    ->visible(fn (VenueCandidate $record): bool => in_array($record->status, [VenueCandidate::STATUS_NEW, VenueCandidate::STATUS_REJECTED], true))
                    ->requiresConfirmation()
                    ->modalHeading(fn (VenueCandidate $record): string => 'List ' . $record->name . '?')
                    ->modalDescription('It gets a page on the website marked “Not on Haraan yet”, with booking off. Players can ask to book and the owner can claim it. You can edit or hide it later under Venues.')
                    ->modalSubmitActionLabel('List it')
                    ->action(function (VenueCandidate $record): void {
                        $venue = $record->approve(auth()->id());
                        Notification::make()->title($record->name . ' is listed')
                            ->body('Add photos, hours and the right sports under Venues to make the page worth finding.')
                            ->actions([Action::make('edit')->label('Open venue')->url(VenueResource::getUrl('edit', ['record' => $venue]))])
                            ->success()->send();
                    }),
                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('gray')
                    ->visible(fn (VenueCandidate $record): bool => $record->isOpenForReview())
                    ->action(function (VenueCandidate $record): void {
                        $record->reject(auth()->id());
                        Notification::make()->title('Rejected — it won’t come back on the next search')->success()->send();
                    }),
                ActionGroup::make([
                    Action::make('maps')
                        ->label('Open in Google Maps')
                        ->icon('heroicon-o-map-pin')
                        ->visible(fn (VenueCandidate $record): bool => filled($record->maps_url))
                        ->url(fn (VenueCandidate $record): ?string => $record->maps_url, shouldOpenInNewTab: true),
                    Action::make('playo')
                        ->label('Open on Playo')
                        ->icon('heroicon-o-arrow-top-right-on-square')
                        ->visible(fn (VenueCandidate $record): bool => filled($record->crosscheck_url))
                        ->url(fn (VenueCandidate $record): ?string => $record->crosscheck_url, shouldOpenInNewTab: true),
                    Action::make('website')
                        ->label('Venue’s own website')
                        ->icon('heroicon-o-globe-alt')
                        ->visible(fn (VenueCandidate $record): bool => filled($record->website))
                        ->url(fn (VenueCandidate $record): ?string => $record->website, shouldOpenInNewTab: true),
                    Action::make('venue')
                        ->label('Open the Haraan venue')
                        ->icon('heroicon-o-building-storefront')
                        ->visible(fn (VenueCandidate $record): bool => $record->venue !== null)
                        ->url(fn (VenueCandidate $record): ?string => $record->venue ? VenueResource::getUrl('edit', ['record' => $record->venue]) : null),
                    Action::make('requests')
                        ->label('Who asked to book')
                        ->icon('heroicon-o-users')
                        ->visible(fn (VenueCandidate $record): bool => (int) $record->requests_count > 0)
                        ->modalHeading(fn (VenueCandidate $record): string => 'Asked to book ' . $record->name)
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Close')
                        ->modalContent(fn (VenueCandidate $record): HtmlString => self::requestsList($record)),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('approveMany')
                        ->label('List selected')
                        ->icon('heroicon-o-check')
                        ->requiresConfirmation()
                        ->modalDescription('Each one gets a website page with booking off. Rows already listed or already on Haraan are left as they are.')
                        ->action(function (Collection $records): void {
                            $done = 0;
                            foreach ($records as $record) {
                                if (in_array($record->status, [VenueCandidate::STATUS_NEW, VenueCandidate::STATUS_REJECTED], true)) {
                                    $record->approve(auth()->id());
                                    $done++;
                                }
                            }
                            Notification::make()->title($done . ($done === 1 ? ' venue listed' : ' venues listed'))->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('rejectMany')
                        ->label('Reject selected')
                        ->icon('heroicon-o-x-mark')
                        ->color('gray')
                        ->action(function (Collection $records): void {
                            $records->filter(fn (VenueCandidate $r): bool => $r->isOpenForReview())
                                ->each(fn (VenueCandidate $r) => $r->reject(auth()->id()));
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->emptyStateHeading('Nothing here yet')
            ->emptyStateDescription('Use Find venues to search a city on Google.')
            ->emptyStateIcon('heroicon-o-magnifying-glass-circle');
    }

    private static function requestsList(VenueCandidate $record): HtmlString
    {
        $cell = 'padding:6px 12px 6px 0';

        $rows = VenueInterest::query()->where('venue_id', $record->venue_id)->latest()->limit(200)->get()
            ->map(fn (VenueInterest $i): string => '<tr>'
                . '<td style="' . $cell . '">' . e($i->name) . '</td>'
                . '<td style="' . $cell . '">' . e($i->phone) . '</td>'
                . '<td style="' . $cell . '">' . e(implode(' · ', array_filter([$i->sport, $i->preferred_time])) ?: '—') . '</td>'
                . '<td style="padding:6px 0;white-space:nowrap">' . e((string) $i->created_at?->timezone('Asia/Kolkata')->format('j M, g:i A')) . '</td>'
                . '</tr>')
            ->implode('');

        return new HtmlString('<table style="width:100%;font-size:14px;text-align:left">'
            . '<thead><tr><th style="' . $cell . '">Name</th><th style="' . $cell . '">Phone</th><th style="' . $cell . '">Wants</th><th>Asked</th></tr></thead>'
            . '<tbody>' . $rows . '</tbody></table>');
    }
}
