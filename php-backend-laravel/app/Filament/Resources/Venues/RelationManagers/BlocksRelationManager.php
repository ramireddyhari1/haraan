<?php

declare(strict_types=1);

namespace App\Filament\Resources\Venues\RelationManagers;

use App\Models\AdminAction;
use App\Models\VenueBlock;
use App\Models\VenueCourt;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Time taken off the market at this venue.
 *
 * The same rows the standalone Blocked time resource writes, scoped to one venue
 * so the venue is implied rather than picked — and read by
 * {@see \App\Services\BookingService::assertCourtHourFree()}, which means a row
 * created here stops the app, the desk and the API from selling that court-hour
 * the moment it saves.
 */
class BlocksRelationManager extends RelationManager
{
    protected static string $relationship = 'blocks';

    protected static ?string $title = 'Blocked time';

    protected static string | \BackedEnum | null $icon = 'heroicon-o-no-symbol';

    protected static ?string $modelLabel = 'block';

    protected static ?string $pluralModelLabel = 'blocked time';

    /**
     * Filament makes relation managers read-only on a resource View page by
     * default. The Venue 360 IS the view page, and these are the controls it
     * exists to offer — so the default is declined here deliberately. Authority
     * still comes from the resource and the capability checks on each action.
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    /** Taking inventory off sale is the same class of act as repricing it. */
    public function canCreate(): bool
    {
        return auth()->user()?->hasPartnerPermission('pricing') ?? false;
    }

    public function canEdit(Model $record): bool
    {
        return $this->canCreate();
    }

    public function canDelete(Model $record): bool
    {
        return $this->canCreate();
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $active = $ownerRecord->blocks()
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', now()->toDateString()))
            ->count();

        return $active > 0 ? (string) $active : null;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('venue_court_id')
                ->label('Court')
                ->options(fn (): array => VenueCourt::query()
                    ->where('venue_id', $this->getOwnerRecord()->getKey())
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->searchable()
                ->placeholder('Every court at this venue')
                ->helperText('Leave empty to close the whole venue for this window.'),

            Select::make('kind')
                ->label('Reason')
                ->options(VenueBlock::KINDS)
                ->default('maintenance')
                ->native(false)
                ->required()
                ->live(),

            TextInput::make('title')
                ->label('Label')
                ->maxLength(120)
                ->placeholder(fn (Get $get): string => match ($get('kind')) {
                    'academy'    => 'U-14 batch',
                    'tournament' => 'Corporate League · QF',
                    'holiday'    => 'Independence Day',
                    'private'    => 'Private hire',
                    default      => 'Surface re-lay',
                })
                ->helperText('Shown on the calendar and in the message a customer sees when the slot is unavailable.')
                ->columnSpanFull(),

            DatePicker::make('starts_on')
                ->label('From')
                ->native(false)
                ->required()
                ->default(today()),

            DatePicker::make('ends_on')
                ->label('Until')
                ->native(false)
                ->required()
                ->default(today())
                ->afterOrEqual('starts_on')
                ->helperText('Same day for a one-off.'),

            Select::make('weekday')
                ->label('Repeat on')
                ->options([
                    0 => 'Sundays', 1 => 'Mondays', 2 => 'Tuesdays', 3 => 'Wednesdays',
                    4 => 'Thursdays', 5 => 'Fridays', 6 => 'Saturdays',
                ])
                ->native(false)
                ->placeholder('Every day in the range')
                ->helperText('For a weekly batch — pick the weekday and set a long date range.'),

            Toggle::make('all_day')
                ->label('All day')
                ->default(false)
                ->dehydrated(false)
                ->live()
                ->afterStateHydrated(fn (Toggle $component, ?VenueBlock $record) => $component->state(
                    $record === null ? false : $record->isAllDay(),
                ))
                ->afterStateUpdated(function (bool $state, callable $set): void {
                    if ($state) {
                        $set('start_time', null);
                        $set('end_time', null);
                    }
                })
                ->helperText('Takes the court off sale for the whole day.'),

            TimePicker::make('start_time')
                ->label('From time')
                ->seconds(false)
                ->displayFormat('H:i')
                ->format('H:i')
                ->visible(fn (Get $get): bool => ! $get('all_day'))
                ->requiredIf('all_day', false),

            TimePicker::make('end_time')
                ->label('Until time')
                ->seconds(false)
                ->displayFormat('H:i')
                ->format('H:i')
                ->visible(fn (Get $get): bool => ! $get('all_day'))
                ->requiredIf('all_day', false)
                ->after('start_time'),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->defaultSort('starts_on', 'desc')
            ->emptyStateHeading('Nothing blocked')
            ->emptyStateDescription('Every open hour at this venue is on sale.')
            ->emptyStateIcon('heroicon-o-no-symbol')
            ->columns([
                TextColumn::make('title')
                    ->label('Block')
                    ->state(fn (VenueBlock $record): string => $record->label())
                    ->weight('bold')
                    ->searchable(),

                TextColumn::make('kind')
                    ->label('Reason')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => VenueBlock::KINDS[$state] ?? ucfirst((string) $state))
                    ->color(fn (?string $state): string => match ($state) {
                        'maintenance' => 'danger',
                        'holiday'     => 'warning',
                        default       => 'info',
                    }),

                TextColumn::make('court.name')
                    ->label('Court')
                    ->placeholder('Whole venue')
                    ->badge()
                    ->color(fn (VenueBlock $record): string => $record->venue_court_id ? 'info' : 'gray'),

                TextColumn::make('starts_on')
                    ->label('Range')
                    ->state(fn (VenueBlock $record): string => $record->starts_on?->format('d M Y') === $record->ends_on?->format('d M Y')
                        ? (string) $record->starts_on?->format('d M Y')
                        : $record->starts_on?->format('d M Y') . ' → ' . $record->ends_on?->format('d M Y'))
                    ->sortable(),

                TextColumn::make('start_time')
                    ->label('Hours')
                    ->state(fn (VenueBlock $record): string => $record->isAllDay()
                        ? 'All day'
                        : $record->start_time . '–' . $record->end_time)
                    ->badge()
                    ->color('gray'),

                TextColumn::make('weekday')
                    ->label('Repeats')
                    ->state(fn (VenueBlock $record): string => $record->weekday === null
                        ? 'Every day in range'
                        : ['Sundays', 'Mondays', 'Tuesdays', 'Wednesdays', 'Thursdays', 'Fridays', 'Saturdays'][$record->weekday]),
            ])
            ->filters([
                Filter::make('current')
                    ->label('Active or upcoming')
                    ->query(fn (Builder $query): Builder => $query
                        ->where(fn (Builder $q) => $q
                            ->whereNull('ends_on')
                            ->orWhereDate('ends_on', '>=', now()->toDateString()))),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Block time')
                    ->mutateDataUsing(function (array $data): array {
                        $data['created_by'] = auth()->id();

                        return $data;
                    })
                    ->after(fn (VenueBlock $record) => AdminAction::log('venue.time_blocked', [
                        'block'  => $record->label(),
                        'kind'   => $record->kind,
                        'from'   => $record->starts_on?->toDateString(),
                        'to'     => $record->ends_on?->toDateString(),
                        'court'  => $record->venue_court_id,
                    ], $this->getOwnerRecord())),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->before(fn (VenueBlock $record) => AdminAction::log('venue.time_unblocked', [
                        'block' => $record->label(),
                    ], $this->getOwnerRecord())),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
