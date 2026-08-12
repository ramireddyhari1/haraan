<?php

declare(strict_types=1);

namespace App\Filament\Resources\Events\RelationManagers;

use App\Filament\Resources\Events\Schemas\TicketTypeFields;
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
        // Shared with the create wizard's inline tier editor (EventForm) so both
        // offer the same presets and fields — see TicketTypeFields.
        return $schema->components(TicketTypeFields::components());
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
