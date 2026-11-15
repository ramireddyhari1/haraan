<?php

namespace App\Filament\Resources\SupportThreads\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class SupportThreadForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                // Who the agent is talking to — read-only, shown above the fields.
                View::make('filament.resources.support-threads.user-card'),
                Section::make('Details')
                    ->compact()
                    ->extraAttributes(['class' => 'stc-details'])
                    ->schema([
                        Select::make('status')
                            ->required()
                            ->native(false)
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
                        // Users pick this in the app, but they guess wrong often — the
                        // team can reclassify so topic analytics stay honest.
                        Select::make('category_id')
                            ->label('Topic')
                            ->relationship('category', 'label')
                            ->searchable()
                            ->preload()
                            ->placeholder('Unsorted'),
                        TextInput::make('subject')
                            ->placeholder('General support')
                            ->maxLength(255),
                    ]),
            ]);
    }
}
