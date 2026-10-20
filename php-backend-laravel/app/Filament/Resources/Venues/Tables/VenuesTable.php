<?php

namespace App\Filament\Resources\Venues\Tables;

use App\Filament\Resources\Venues\Pages\CreateVenue;
use App\Filament\Resources\Venues\VenueResource;
use App\Models\Venue;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
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
                        ->getStateUsing(fn (Venue $r): string => (is_array($r->images) ? collect($r->images)->first(fn ($i) => trim((string) $i) !== '') : null)
                            ?? self::venuePlaceholder((string) $r->category))
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

                        // Chip row: category + lifecycle status badge + bookable/info.
                        Split::make([
                            TextColumn::make('category')
                                ->badge()
                                ->color(fn (?string $state): string => match ($state) {
                                    'Cricket' => 'success',
                                    'Football' => 'warning',
                                    'Badminton', 'Pickleball' => 'info',
                                    default => 'gray',
                                }),

                            // What a customer gets today, and if nothing, how far setup has to go:
                            // "Setup 5/6 · Photos" says what to do; "Setup Incomplete" didn't.
                            TextColumn::make('lifecycle_status')
                                ->label('')
                                ->badge()
                                ->getStateUsing(fn (Venue $r): string => self::stateLabel($r))
                                ->icon(fn (Venue $r): string => $r->isOnSale() ? 'heroicon-m-check-circle' : ($r->isReadyForPublish() ? 'heroicon-m-rocket-launch' : 'heroicon-m-wrench-screwdriver'))
                                ->color(fn (Venue $r): string => match (true) {
                                    $r->isOnSale() => 'success',
                                    $r->isReadyForPublish() => 'info',
                                    default => 'warning',
                                }),
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
                SelectFilter::make('status')
                    ->label('Lifecycle Status')
                    ->options([
                        'published' => 'Published',
                        'draft' => 'Draft',
                    ]),
                SelectFilter::make('category')
                    ->options(['Cricket' => 'Cricket', 'Football' => 'Football', 'Badminton' => 'Badminton', 'Pickleball' => 'Pickleball', 'Basketball' => 'Basketball', 'Tennis' => 'Tennis', 'Volleyball' => 'Volleyball']),
                TernaryFilter::make('is_bookable')->label('Bookable'),
                TernaryFilter::make('is_active')->label('Live'),
            ])
            // First-run state. A partner cannot create a listing (VenueResource::canCreate),
            // so pointing them at a "add your first venue" button they aren't allowed to
            // press would be a dead end — tell them who does it instead.
            ->emptyStateIcon('heroicon-o-building-storefront')
            ->emptyStateHeading(fn (): string => VenueResource::canCreate()
                ? 'No venues yet'
                : 'No venue assigned to you yet')
            ->emptyStateDescription(fn (): string => VenueResource::canCreate()
                ? 'Add your first turf or venue to start taking bookings and listing it in the app.'
                : 'The Haraan team sets up your venue and assigns it to this account. Once that is done it appears here and you can manage its hours, pricing and bookings. Contact support if you are expecting one.')
            ->emptyStateActions(array_values(array_filter([
                VenueResource::canCreate()
                    ? Action::make('createFirstVenue')
                        ->label('Add your first venue')
                        ->icon('heroicon-m-plus')
                        ->button()
                        ->url(fn (): string => CreateVenue::getUrl())
                    : null,
            ])))
            // Clicking a card opens the 360 rather than the form: reading a venue is
            // the common act, editing it the occasional one.
            ->recordUrl(fn (Venue $record): string => VenueResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                ViewAction::make()
                    ->label('Venue 360')
                    ->icon('heroicon-m-squares-2x2'),
                EditAction::make(),
                // An unfinished venue opens its form at the setup steps; Publish only shows
                // when it will succeed, instead of always answering with an error list.
                Action::make('finishSetup')
                    ->label('Finish setup')
                    ->icon('heroicon-m-wrench-screwdriver')
                    ->color('primary')
                    ->visible(fn (Venue $r): bool => ! $r->isReadyForPublish() && VenueResource::canEdit($r))
                    ->url(fn (Venue $r): string => VenueResource::getUrl('edit', ['record' => $r])),
                Action::make('publish')
                    ->label('Publish')
                    ->icon('heroicon-m-globe-alt')
                    ->color('success')
                    ->visible(fn (Venue $r): bool => ! $r->isPublished() && $r->isReadyForPublish())
                    ->action(function (Venue $r): void {
                        $errors = $r->readinessErrors();
                        if (! empty($errors)) {
                            \Filament\Notifications\Notification::make()
                                ->title('Cannot publish venue — Setup incomplete')
                                ->body("Please fix the following issues before publishing:\n• " . implode("\n• ", $errors))
                                ->danger()
                                ->send();

                            return;
                        }

                        $r->publish();
                        \Filament\Notifications\Notification::make()
                            ->title('Venue Published!')
                            ->body('This venue is now live and bookable.')
                            ->success()
                            ->send();
                    }),
                Action::make('unpublish')
                    ->label('Unpublish')
                    ->icon('heroicon-m-eye-slash')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (Venue $r): bool => $r->isPublished())
                    ->action(function (Venue $r): void {
                        $r->unpublish();
                        \Filament\Notifications\Notification::make()
                            ->title('Venue Unpublished')
                            ->body('This venue has been returned to draft.')
                            ->info()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // Deletes only venues without bookings and says which it kept: a venue's
                    // bookings reference it by id only, so deleting one would orphan them.
                    DeleteBulkAction::make()
                        ->action(function (\Illuminate\Support\Collection $records): void {
                            $kept = $records->filter(fn (Venue $v): bool => $v->hasBookings());
                            $records->reject(fn (Venue $v): bool => $v->hasBookings())->each->delete();

                            if ($kept->isNotEmpty()) {
                                \Filament\Notifications\Notification::make()
                                    ->title($kept->count() . ' venue(s) kept — they have bookings')
                                    ->body($kept->pluck('name')->implode(', ') . '. Unpublish them instead.')
                                    ->warning()
                                    ->persistent()
                                    ->send();
                            }
                        }),
                ]),
            ]);
    }

    /** "Live", "Paused", "Ready to publish" or "Setup 5/6 · Photos" — see VenueSetupSteps. */
    public static function stateLabel(Venue $r): string
    {
        if ($r->isOnSale()) {
            return 'Live';
        }
        if ($r->isReadyForPublish()) {
            return $r->visibilityState() === Venue::STATE_PAUSED ? 'Bookings paused' : 'Ready to publish';
        }

        $required = collect(\App\Support\VenueSetupSteps::forVenue($r))->where('required', true);
        $next = $required->firstWhere('done', false);

        return 'Setup ' . $required->where('done', true)->count() . '/' . $required->count()
            . ($next ? ' · ' . $next['label'] : '');
    }

    /**
     * No photo yet: the venue's own court, drawn (filament/venue/court-pitch) on a soft tile,
     * so the list still tells a cricket box from a badminton hall at a glance.
     */
    private static function venuePlaceholder(string $sport = ''): string
    {
        static $cache = [];
        $key = strtolower(trim($sport));

        return $cache[$key] ??= (static function () use ($sport): string {
            $court = view('filament.venue.court-pitch', ['sport' => $sport, 'kind' => null])->render();
            // A data-URI image can't read the page's CSS variables, so pin the light palette.
            $court = str_replace(
                ['var(--cc-ink)', 'var(--cc-blue-soft)', 'var(--cc-surface)', 'class="cb-pitch" viewBox'],
                ['#4b5b78', '#e1eaff', '#ffffff', 'x="6" y="16" width="60" height="40" viewBox'],
                $court,
            );
            $court = preg_replace('/\s(role|aria-label)="[^"]*"/', '', $court);

            $svg = "<svg xmlns='http://www.w3.org/2000/svg' width='72' height='72' viewBox='0 0 72 72'>"
                . "<rect width='72' height='72' rx='14' fill='#f4f7fe'/>"
                . "<ellipse cx='36' cy='60' rx='24' ry='3' fill='#dfe7fb'/>"
                . $court
                . '</svg>';

            return 'data:image/svg+xml;base64,' . base64_encode($svg);
        })();
    }
}
