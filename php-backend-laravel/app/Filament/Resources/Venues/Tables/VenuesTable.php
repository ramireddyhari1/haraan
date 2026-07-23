<?php

namespace App\Filament\Resources\Venues\Tables;

use App\Filament\Resources\Venues\Pages\CreateVenue;
use App\Models\Venue;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * The venues list, rebuilt as a BookMyShow-style card grid to match the events
 * list rather than a raw column dump. Each venue is one card: a square photo on
 * the left, then a stack of name → tagline → category·live·bookable chips →
 * location → price·rating on the right. One scannable column on phones, two-up
 * on wide desktops (contentGrid). The low-signal admin columns (counts, sort
 * order, timestamps) don't belong on a card and stay on the edit form.
 */
class VenuesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->contentGrid(['default' => 1, 'xl' => 2])
            ->columns([
                Split::make([
                    ImageColumn::make('image')
                        ->label('')
                        ->extraImgAttributes(['style' => 'width:72px;height:72px;object-fit:cover;border-radius:14px;box-shadow:0 6px 16px -8px rgba(11,18,32,.45);'])
                        ->getStateUsing(fn (Venue $r): string => $r->images[0] ?? self::venuePlaceholder())
                        ->grow(false),

                    Stack::make([
                        TextColumn::make('name')
                            ->weight('bold')
                            ->size('lg')
                            ->searchable(['name', 'location'])
                            ->wrap(),

                        TextColumn::make('tagline')
                            ->color('gray')
                            ->size('sm')
                            ->wrap(),

                        // Chip row: category + live status + bookable/info.
                        Split::make([
                            TextColumn::make('category')
                                ->badge()
                                ->color(fn (?string $state): string => match ($state) {
                                    'Cricket' => 'success',
                                    'Football' => 'warning',
                                    'Badminton' => 'info',
                                    default => 'gray',
                                }),

                            TextColumn::make('is_active')
                                ->label('')
                                ->badge()
                                ->formatStateUsing(fn (bool $state): string => $state ? 'Live' : 'Hidden')
                                ->color(fn (bool $state): string => $state ? 'success' : 'gray'),

                            TextColumn::make('is_bookable')
                                ->label('')
                                ->badge()
                                ->formatStateUsing(fn (bool $state): string => $state ? 'Bookable' : 'Info only')
                                ->color(fn (bool $state): string => $state ? 'info' : 'gray'),
                        ])->grow(false),

                        TextColumn::make('location')
                            ->icon('heroicon-m-map-pin')
                            ->color('gray')
                            ->size('sm')
                            ->searchable()
                            ->wrap(),

                        // Price + rating, the two numbers an owner watches.
                        Split::make([
                            TextColumn::make('price')
                                ->money('INR')
                                ->weight('bold')
                                ->color('primary')
                                ->sortable(),

                            TextColumn::make('rating')
                                ->badge()
                                ->icon('heroicon-m-star')
                                ->iconColor('warning')
                                ->color('gray')
                                ->formatStateUsing(fn ($state, Venue $r): string => $state
                                    ? number_format((float) $state, 1) . ' (' . (int) $r->ratings_count . ')'
                                    : 'No ratings')
                                ->sortable(),
                        ])->grow(false),
                    ])->space(2),
                ]),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->options(['Cricket' => 'Cricket', 'Football' => 'Football', 'Badminton' => 'Badminton', 'Basketball' => 'Basketball']),
                TernaryFilter::make('is_bookable')->label('Bookable'),
                TernaryFilter::make('is_active')->label('Live'),
            ])
            // First-run state for a new venue-owner partner — mirror the events list.
            ->emptyStateIcon('heroicon-o-building-storefront')
            ->emptyStateHeading('No venues yet')
            ->emptyStateDescription('Add your first turf or venue to start taking bookings and listing it in the app.')
            ->emptyStateActions([
                Action::make('createFirstVenue')
                    ->label('Add your first venue')
                    ->icon('heroicon-m-plus')
                    ->button()
                    ->url(fn (): string => CreateVenue::getUrl()),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /** A neutral rounded placeholder for venues without a photo (self-contained). */
    private static function venuePlaceholder(): string
    {
        $svg = "<svg xmlns='http://www.w3.org/2000/svg' width='72' height='72'>"
            . "<rect width='72' height='72' rx='14' fill='#e8ecf3'/>"
            . "<path d='M20 50V30l16-10 16 10v20z' fill='none' stroke='#b6c0d0' stroke-width='3' stroke-linejoin='round'/>"
            . "<rect x='32' y='38' width='8' height='12' fill='#b6c0d0'/></svg>";

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}
