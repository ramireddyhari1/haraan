<?php

declare(strict_types=1);

namespace App\Filament\Resources\AcademyBatches;

use App\Filament\Resources\AcademyBatches\Pages\EditAcademyBatch;
use App\Filament\Resources\AcademyBatches\Pages\ListAcademyBatches;
use App\Filament\Resources\AcademyBatches\RelationManagers\FeePaymentsRelationManager;
use App\Filament\Resources\AcademyBatches\RelationManagers\StudentsRelationManager;
use App\Models\AdminAction;
use App\Models\Venue;
use App\Models\VenueBatch;
use App\Support\BusinessClock;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Every partner's coaching batches, for the admin.
 *
 * Partners run these from the app (Academy): the roster, attendance and fees.
 * Here the admin sees all of them in one place and can correct a batch, pause
 * one, or take a student off it. Money is never edited: fees are ledger rows
 * (`academy_fee_payments`) and are only shown.
 */
class AcademyBatchResource extends Resource
{
    protected static ?string $model = VenueBatch::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $cluster = \App\Filament\Clusters\GameHub\GameHubCluster::class;

    protected static ?string $navigationLabel = 'Academy batches';

    protected static ?string $modelLabel = 'batch';

    protected static ?string $pluralModelLabel = 'academy batches';

    protected static ?int $navigationSort = 7;

    protected static ?string $recordTitleAttribute = 'name';

    public const WEEKDAYS = ['Mon' => 'Mon', 'Tue' => 'Tue', 'Wed' => 'Wed', 'Thu' => 'Thu', 'Fri' => 'Fri', 'Sat' => 'Sat', 'Sun' => 'Sun'];

    /** Admin only: partners manage their own batches in the app, never in /partner. */
    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'control'
            && (auth()->user()?->canManage('gamehub') ?? false);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return static::canAccess();
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $today = BusinessClock::today();

        return VenueBatch::query()
            ->with(['partner:id,name', 'venue:id,name'])
            ->withCount([
                'enrollments as students_count' => fn ($q) => $q->where('is_active', true),
                'enrollments as overdue_count' => fn ($q) => $q->where('is_active', true)
                    ->whereNotNull('paid_until')->whereDate('paid_until', '<', $today),
            ]);
    }

    /** Batches with at least one student behind on fees. */
    public static function getNavigationBadge(): ?string
    {
        $n = static::getEloquentQuery()->where('is_active', true)->get()->where('overdue_count', '>', 0)->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Batches with fees overdue';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Batch')
                ->description('Changes show in the partner\'s app straight away. Fees already collected are not affected.')
                ->columns(2)
                ->schema([
                    TextInput::make('name')->required()->maxLength(120),
                    TextInput::make('coach_name')->label('Coach')->maxLength(120),
                    TextInput::make('sport')->maxLength(60),
                    Select::make('venue_id')
                        ->label('Venue')
                        ->options(fn (?VenueBatch $record): array => Venue::query()
                            ->where('partner_id', $record?->partner_id)->orderBy('name')->pluck('name', 'id')->all())
                        ->placeholder('No venue (fees join no cash drawer)'),
                    CheckboxList::make('days')
                        ->options(self::WEEKDAYS)
                        ->columns(7)
                        ->helperText('None ticked = runs every day.')
                        ->columnSpanFull(),
                    TextInput::make('start_time')->label('Starts')->placeholder('06:30')
                        ->regex('/^([01]\d|2[0-3]):[0-5]\d$/')->validationMessages(['regex' => 'Use 24-hour time, e.g. 06:30.']),
                    TextInput::make('end_time')->label('Ends')->placeholder('07:30')
                        ->regex('/^([01]\d|2[0-3]):[0-5]\d$/')->validationMessages(['regex' => 'Use 24-hour time, e.g. 07:30.']),
                    TextInput::make('monthly_fee')->label('Fee a month')->prefix('₹')->numeric()->minValue(0)->required(),
                    TextInput::make('capacity')->label('Seats')->numeric()->minValue(1)->maxValue(500)
                        ->helperText('Blank = no limit. A full batch refuses new students.'),
                    Toggle::make('is_active')->label('Taking classes')
                        ->helperText('Off = paused: hidden from the partner\'s day board.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label('Batch')
                    ->weight('medium')
                    ->description(fn (VenueBatch $record): string => collect([$record->partner?->name, $record->venue?->name])->filter()->join(' · '))
                    ->searchable(),
                TextColumn::make('sport')->badge()->color('gray')->placeholder('—'),
                TextColumn::make('days')
                    ->label('When')
                    ->state(fn (VenueBatch $record): string => trim(
                        ($record->daysList() === [] ? 'Every day' : implode(' ', $record->daysList()))
                        . ($record->start_time ? ' · ' . $record->start_time . ($record->end_time ? '–' . $record->end_time : '') : ''),
                    )),
                TextColumn::make('monthly_fee')->label('Fee / month')->money('INR')->sortable(),
                TextColumn::make('students_count')
                    ->label('Students')
                    ->state(fn (VenueBatch $record): string => $record->capacity
                        ? "{$record->students_count} / {$record->capacity}"
                        : (string) $record->students_count)
                    ->color(fn (VenueBatch $record): string => $record->capacity && $record->students_count >= $record->capacity ? 'warning' : 'gray'),
                TextColumn::make('overdue_count')
                    ->label('Overdue')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'success')
                    ->formatStateUsing(fn (int $state): string => $state > 0 ? "{$state} behind" : 'All paid'),
                TextColumn::make('is_active')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Paused')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Active'),
                SelectFilter::make('partner_id')
                    ->label('Partner')
                    ->relationship('partner', 'name')
                    ->searchable()
                    ->preload(),
                Filter::make('overdue')
                    ->label('Fees overdue')
                    ->query(fn (Builder $query): Builder => $query->whereHas('enrollments', fn ($q) => $q
                        ->where('is_active', true)->whereNotNull('paid_until')
                        ->whereDate('paid_until', '<', BusinessClock::today()))),
            ])
            ->recordActions([
                EditAction::make()->label('Open'),
                self::pauseAction(),
            ]);
    }

    /** Pause or resume, from the list or the page. Audited. */
    public static function pauseAction(): Action
    {
        return Action::make('togglePause')
            ->label(fn (VenueBatch $record): string => $record->is_active ? 'Pause' : 'Resume')
            ->icon(fn (VenueBatch $record): BackedEnum|string => $record->is_active ? 'heroicon-o-pause' : 'heroicon-o-play')
            ->color(fn (VenueBatch $record): string => $record->is_active ? 'warning' : 'success')
            ->requiresConfirmation()
            ->modalDescription(fn (VenueBatch $record): string => $record->is_active
                ? 'The batch disappears from the partner\'s day board. Students, fees and attendance stay.'
                : 'The batch shows on the partner\'s day board again.')
            ->authorize(fn (VenueBatch $record): bool => static::canEdit($record))
            ->action(function (VenueBatch $record): void {
                $record->forceFill(['is_active' => ! $record->is_active])->save();
                AdminAction::log($record->is_active ? 'academy_batch.resumed' : 'academy_batch.paused', [
                    'partner_id' => $record->partner_id,
                    'name' => $record->name,
                ], $record);
                Notification::make()->title($record->is_active ? 'Batch resumed' : 'Batch paused')->success()->send();
            });
    }

    public static function getRelations(): array
    {
        return [
            StudentsRelationManager::class,
            FeePaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAcademyBatches::route('/'),
            'edit' => EditAcademyBatch::route('/{record}/edit'),
        ];
    }
}
