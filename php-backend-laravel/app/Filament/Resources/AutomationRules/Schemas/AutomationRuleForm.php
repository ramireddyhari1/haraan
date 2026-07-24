<?php

namespace App\Filament\Resources\AutomationRules\Schemas;

use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AutomationRuleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Rule name')
                    ->placeholder('e.g. Parking questions')
                    ->helperText('Internal only — the customer never sees this.')
                    ->required()
                    ->maxLength(120),

                Select::make('trigger_type')
                    ->label('Trigger')
                    ->options([
                        'keyword' => 'Keyword — fires when the message contains one of these words',
                        'fallback' => 'Fallback — fires when nothing else matched (the away message)',
                    ])
                    ->default('keyword')
                    ->live()
                    ->required(),

                TagsInput::make('keywords')
                    ->label('Keywords')
                    ->placeholder('parking')
                    ->helperText('Case-insensitive. Add each word or phrase separately.')
                    ->visible(fn ($get): bool => $get('trigger_type') === 'keyword')
                    ->required(fn ($get): bool => $get('trigger_type') === 'keyword'),

                Select::make('match_type')
                    ->label('Match')
                    ->options([
                        'contains' => 'Contains the keyword anywhere in the message',
                        'exact' => 'The whole message is exactly the keyword',
                    ])
                    ->default('contains')
                    ->visible(fn ($get): bool => $get('trigger_type') === 'keyword')
                    ->required(),

                Textarea::make('reply_body')
                    ->label('Reply')
                    ->rows(4)
                    ->maxLength(1000)
                    ->helperText('Sent from the shared Haraan number, so say who you are. '
                        . 'Only works inside the 24h window opened by their message.')
                    ->required(),

                Select::make('partner_id')
                    ->label('Applies to')
                    ->relationship('partner', 'name', fn ($query) => $query->whereNotNull('partner_type'))
                    ->searchable()
                    ->preload()
                    ->placeholder('Every partner (platform rule)')
                    ->helperText('Leave empty for a platform-wide rule. A partner rule always '
                        . 'beats the platform one.'),

                TextInput::make('priority')
                    ->numeric()
                    ->default(100)
                    ->required()
                    ->helperText('Lower runs first. Only breaks ties within the same scope.'),

                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }
}
