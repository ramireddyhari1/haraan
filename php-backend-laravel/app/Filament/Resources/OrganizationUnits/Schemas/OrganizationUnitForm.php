<?php

namespace App\Filament\Resources\OrganizationUnits\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class OrganizationUnitForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('type')
                    ->required(),
                TextInput::make('parent_id')
                    ->numeric(),
                Toggle::make('active')
                    ->required(),
            ]);
    }
}
