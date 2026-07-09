<?php

declare(strict_types=1);

namespace App\Filament\Resources\Events\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Lets a host define priced ticket tiers (General / Group / VIP …) on an event.
 * Shown as a tab on the Edit Event page.
 */
class TicketTypesRelationManager extends RelationManager
{
    protected static string $relationship = 'ticketTypes';

    protected static ?string $title = 'Ticket Types';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Preset picker — prefills the fields for a common ticket model so a
                // host never has to reason about the raw columns. Not persisted.
                Select::make('preset')
                    ->label('Start from a preset')
                    ->options([
                        'general'    => 'General Admission',
                        'free'       => 'Free Pass',
                        'tier'       => 'Tier / Category (Gold, VIP…)',
                        'early_bird' => 'Early Bird (timed)',
                        'dynamic'    => 'Dynamic Pricing (price rises as it sells)',
                        'bundle'     => 'Bundle (Couple / Group / Family)',
                        'addon'      => 'Add-on (parking, food…)',
                        'donation'   => 'Donation (pay what you want)',
                    ])
                    ->live()
                    ->dehydrated(false)
                    ->afterStateUpdated(function ($state, Set $set): void {
                        match ($state) {
                            'free'       => self::applyPreset($set, 'Free Pass', 'standard', 0),
                            'general'    => self::applyPreset($set, 'General Admission', 'standard', 0),
                            'tier'       => self::applyPreset($set, 'Gold', 'standard', 0),
                            'early_bird' => self::applyPreset($set, 'Early Bird', 'standard', 0),
                            'dynamic'    => self::applyDynamicPreset($set),
                            'bundle'     => self::applyPreset($set, 'Couple Pass', 'standard', 0, admits: 2),
                            'addon'      => self::applyPreset($set, 'Parking', 'addon', 0),
                            'donation'   => self::applyPreset($set, 'Donation', 'donation', 0, minPrice: 0),
                            default      => null,
                        };
                    })
                    ->helperText('Optional — pick the closest model, then tweak below.'),

                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('General / Couple / VIP'),
                Select::make('kind')
                    ->options([
                        'standard' => 'Standard entry',
                        'addon'    => 'Add-on (extra, not entry)',
                        'donation' => 'Donation (pay what you want)',
                    ])
                    ->default('standard')
                    ->required()
                    ->live(),
                TextInput::make('price')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->prefix('₹')
                    ->default(0)
                    ->helperText(fn (Set $set, $get) => $get('kind') === 'donation'
                        ? 'Suggested amount — buyers can pay more.'
                        : null),
                TextInput::make('admits')
                    ->numeric()
                    ->minValue(1)
                    ->default(1)
                    ->helperText('People admitted per ticket. Use 2+ for Couple/Group/Family bundles.'),
                TextInput::make('min_price')
                    ->label('Minimum amount')
                    ->numeric()
                    ->minValue(0)
                    ->prefix('₹')
                    ->visible(fn ($get) => $get('kind') === 'donation')
                    ->helperText('Floor for pay-what-you-want donations.'),
                TextInput::make('capacity')
                    ->numeric()
                    ->minValue(0)
                    ->helperText('Leave blank for unlimited (bounded by the event capacity).'),
                Repeater::make('pricing_phases')
                    ->label('Dynamic price phases')
                    ->schema([
                        TextInput::make('label')
                            ->placeholder('Early bird')
                            ->required()
                            ->maxLength(40),
                        TextInput::make('price')
                            ->numeric()
                            ->minValue(1)
                            ->prefix('₹')
                            ->required(),
                        TextInput::make('capacity')
                            ->label('Spots at this price')
                            ->numeric()
                            ->minValue(1)
                            ->required(),
                    ])
                    ->columns(3)
                    ->addActionLabel('Add phase')
                    ->reorderable()
                    ->collapsible()
                    ->visible(fn ($get) => $get('kind') === 'standard')
                    ->helperText('Optional. Price climbs as spots sell — the first phase fills, then the next. '
                        . 'The live price is always the earliest phase with room left. Leave empty for a flat price.'),
                DateTimePicker::make('sales_start')
                    ->label('On sale from')
                    ->helperText('Leave blank to sell immediately (used for Early Bird).'),
                DateTimePicker::make('sales_end')
                    ->label('On sale until')
                    ->helperText('Leave blank for no end (used for Early Bird).'),
                TextInput::make('sort')
                    ->numeric()
                    ->default(0)
                    ->helperText('Lower numbers show first.'),
            ]);
    }

    /** Prefill the tier fields from a chosen preset. */
    private static function applyPreset(
        Set $set,
        string $name,
        string $kind,
        float $price,
        int $admits = 1,
        ?float $minPrice = null,
    ): void {
        $set('name', $name);
        $set('kind', $kind);
        $set('price', $price);
        $set('admits', $admits);
        $set('min_price', $minPrice);
    }

    /** Prefill a demand-based dynamic-pricing tier with a sensible 3-phase ladder. */
    private static function applyDynamicPreset(Set $set): void
    {
        $set('name', 'Regular Ticket');
        $set('kind', 'standard');
        $set('price', 499);
        $set('admits', 1);
        $set('min_price', null);
        $set('pricing_phases', [
            ['label' => 'Early bird', 'price' => 350, 'capacity' => 20],
            ['label' => 'Phase 1', 'price' => 450, 'capacity' => 180],
            ['label' => 'Phase 2', 'price' => 499, 'capacity' => 100],
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('name')
                    ->weight('bold')
                    ->searchable(),
                TextColumn::make('kind')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => ucfirst($state))
                    ->color(fn (string $state) => match ($state) {
                        'addon' => 'warning',
                        'donation' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('price')
                    ->money('INR')
                    ->sortable(),
                TextColumn::make('admits')
                    ->label('Admits')
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('sold')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('capacity')
                    ->numeric()
                    ->placeholder('Unlimited')
                    ->sortable(),
                TextColumn::make('sort')
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
