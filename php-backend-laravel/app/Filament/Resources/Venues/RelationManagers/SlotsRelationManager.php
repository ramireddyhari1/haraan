<?php

namespace App\Filament\Resources\Venues\RelationManagers;

use App\Models\VenueSlot;
use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SlotsRelationManager extends RelationManager
{
    protected static string $relationship = 'slots';

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

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // The website and the app show a slot only on its day, so the day has to be
                // chosen here. This form used to ask for the time alone, and new rows got the
                // column default "Today", which matches no date: the slot never showed.
                Select::make('day')
                    ->options(array_combine(
                        [VenueSlot::EVERY_DAY, ...VenueSlot::WEEKDAYS],
                        [VenueSlot::EVERY_DAY, ...VenueSlot::WEEKDAYS],
                    ))
                    ->default(VenueSlot::EVERY_DAY)
                    ->required()
                    ->native(false)
                    ->helperText('A weekday slot replaces the "Every day" slots on that day.'),
                TextInput::make('time')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('6:00 AM'),
                Toggle::make('is_available')
                    ->label('Open for booking')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('time')
            ->columns([
                TextColumn::make('day')
                    ->badge()
                    ->sortable(),
                TextColumn::make('time')
                    ->searchable(),
                IconColumn::make('is_available')
                    ->label('Open')
                    ->boolean(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
                AssociateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DissociateAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DissociateBulkAction::make(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
