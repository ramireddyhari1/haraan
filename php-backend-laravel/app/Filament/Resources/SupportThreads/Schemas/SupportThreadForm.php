<?php

namespace App\Filament\Resources\SupportThreads\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SupportThreadForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('subject')
                    ->placeholder('General support')
                    ->maxLength(255),
                Select::make('status')
                    ->required()
                    ->options([
                        'open'    => 'Open — waiting on us',
                        'pending' => 'Pending — waiting on user',
                        'closed'  => 'Closed — resolved',
                    ]),
                // Assign to any control-panel user (worker/co-admin) who will handle it.
                Select::make('assigned_to')
                    ->label('Assigned to')
                    ->relationship('assignee', 'name')
                    ->searchable()
                    ->preload()
                    ->placeholder('Unassigned'),
            ]);
    }
}
