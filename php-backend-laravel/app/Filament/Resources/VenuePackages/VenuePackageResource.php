<?php

declare(strict_types=1);

namespace App\Filament\Resources\VenuePackages;

use App\Filament\Resources\VenuePackages\Pages\EditVenuePackage;
use App\Filament\Resources\VenuePackages\Pages\ListVenuePackages;
use App\Filament\Resources\VenuePackages\RelationManagers\PassHoldersRelationManager;
use App\Models\AdminAction;
use App\Models\Venue;
use App\Models\VenuePackage;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Every partner's prepaid passes ("10 hours for ₹4,000") and who bought them.
 *
 * An offer's terms can be corrected or paused here. A pass already sold keeps
 * the terms it was sold on, and its balance is never typed in: sessions left is
 * always the sessions bought minus the redemption rows.
 */
class VenuePackageResource extends Resource
{
    protected static ?string $model = VenuePackage::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $cluster = \App\Filament\Clusters\GameHub\GameHubCluster::class;

    protected static ?string $navigationLabel = 'Packages & passes';

    protected static ?string $modelLabel = 'package';

    protected static ?string $pluralModelLabel = 'packages';

    protected static ?int $navigationSort = 8;

    protected static ?string $recordTitleAttribute = 'name';

    /** Admin only: partners manage their own offers in the app, never in /partner. */
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
        return VenuePackage::query()
            ->with(['partner:id,name', 'venue:id,name'])
            ->withCount('sales')
            ->withSum('sales', 'amount_paid');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Offer')
                ->description('Changes apply to new sales only. Passes already sold keep their sessions, price and expiry.')
                ->columns(2)
                ->schema([
                    TextInput::make('name')->required()->maxLength(120),
                    Select::make('venue_id')
                        ->label('Where it works')
                        ->options(fn (?VenuePackage $record): array => Venue::query()
                            ->where('partner_id', $record?->partner_id)->orderBy('name')->pluck('name', 'id')->all())
                        ->placeholder('Every branch'),
                    TextInput::make('price')->prefix('₹')->numeric()->minValue(1)->required(),
                    TextInput::make('sessions')->numeric()->minValue(1)->maxValue(500)->required(),
                    TextInput::make('validity_days')->label('Valid for (days)')->numeric()->minValue(1)->maxValue(1095)
                        ->helperText('Blank = never expires.'),
                    Toggle::make('is_active')->label('On sale')
                        ->helperText('Off = the desk can\'t sell it. Passes already sold still work.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label('Offer')
                    ->weight('medium')
                    ->description(fn (VenuePackage $record): string => collect([$record->partner?->name, $record->venue?->name ?? 'every branch'])->filter()->join(' · '))
                    ->searchable(),
                TextColumn::make('price')->money('INR')->sortable()
                    ->description(fn (VenuePackage $record): string => "{$record->sessions} sessions · ₹" . number_format($record->perSession()) . ' each'),
                TextColumn::make('validity_days')->label('Validity')
                    ->formatStateUsing(fn (?int $state): string => $state ? "{$state} days" : 'Never expires')
                    ->placeholder('Never expires'),
                TextColumn::make('sales_count')->label('Sold')->sortable(),
                TextColumn::make('sales_sum_amount_paid')->label('Sales')->money('INR')->placeholder('₹0'),
                TextColumn::make('is_active')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'On sale' : 'Paused')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('On sale'),
                SelectFilter::make('partner_id')
                    ->label('Partner')
                    ->relationship('partner', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make()->label('Open'),
                self::pauseAction(),
            ]);
    }

    /** Pause or resume sales. Audited. */
    public static function pauseAction(): Action
    {
        return Action::make('togglePause')
            ->label(fn (VenuePackage $record): string => $record->is_active ? 'Pause sales' : 'Resume sales')
            ->icon(fn (VenuePackage $record): string => $record->is_active ? 'heroicon-o-pause' : 'heroicon-o-play')
            ->color(fn (VenuePackage $record): string => $record->is_active ? 'warning' : 'success')
            ->requiresConfirmation()
            ->modalDescription(fn (VenuePackage $record): string => $record->is_active
                ? 'The desk can no longer sell this offer. Passes already sold still work.'
                : 'The desk can sell this offer again.')
            ->authorize(fn (VenuePackage $record): bool => static::canEdit($record))
            ->action(function (VenuePackage $record): void {
                $record->forceFill(['is_active' => ! $record->is_active])->save();
                AdminAction::log($record->is_active ? 'package.resumed' : 'package.paused', [
                    'partner_id' => $record->partner_id,
                    'name' => $record->name,
                ], $record);
                Notification::make()->title($record->is_active ? 'Back on sale' : 'Sales paused')->success()->send();
            });
    }

    public static function getRelations(): array
    {
        return [PassHoldersRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVenuePackages::route('/'),
            'edit' => EditVenuePackage::route('/{record}/edit'),
        ];
    }
}
