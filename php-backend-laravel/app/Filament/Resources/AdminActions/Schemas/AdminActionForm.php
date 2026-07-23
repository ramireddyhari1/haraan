<?php

namespace App\Filament\Resources\AdminActions\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class AdminActionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->relationship('user', 'name'),
                TextInput::make('action')
                    ->required(),
                Textarea::make('meta')
                    ->columnSpanFull(),
                TextInput::make('ip'),
            ]);
    }
}
