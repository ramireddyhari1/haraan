<?php

namespace App\Filament\Resources\Venues\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Lets a venue owner close specific dates for bookings (holidays / maintenance).
 * Enforced in BookingService::createVenueBooking.
 */
class BlockedDatesRelationManager extends RelationManager
{
    protected static string $relationship = 'blockedDates';

    protected static ?string $title = 'Blocked Dates';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('date')
                    ->required()
                    ->native(false),
                TextInput::make('reason')
                    ->maxLength(255)
                    ->placeholder('Maintenance / holiday'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('date')
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')->date()->sortable(),
                TextColumn::make('reason')->placeholder('—'),
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
