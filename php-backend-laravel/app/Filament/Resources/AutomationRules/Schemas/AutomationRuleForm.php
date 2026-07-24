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
                        'keyword' => 'Keyword — fires when a DM contains one of these words',
                        'fallback' => 'Fallback — fires when no other DM rule matched (the away message)',
                        'comment' => 'Instagram comment → DM — fires when someone COMMENTS on a post',
                    ])
                    ->default('keyword')
                    ->live()
                    ->required(),

                TagsInput::make('keywords')
                    ->label('Keywords')
                    ->placeholder('price')
                    ->helperText(fn ($get): string => $get('trigger_type') === 'comment'
                        ? 'Case-insensitive. LEAVE EMPTY to DM everyone who comments.'
                        : 'Case-insensitive. Add each word or phrase separately.')
                    ->visible(fn ($get): bool => in_array($get('trigger_type'), ['keyword', 'comment'], true))
                    ->required(fn ($get): bool => $get('trigger_type') === 'keyword'),

                Select::make('match_type')
                    ->label('Match')
                    ->options([
                        'contains' => 'Contains the keyword anywhere in the message',
                        'exact' => 'The whole message is exactly the keyword',
                    ])
                    ->default('contains')
                    ->visible(fn ($get): bool => in_array($get('trigger_type'), ['keyword', 'comment'], true))
                    ->required(),

                Textarea::make('reply_body')
                    ->label(fn ($get): string => $get('trigger_type') === 'comment' ? 'Private reply (the DM)' : 'Reply')
                    ->rows(4)
                    ->maxLength(1000)
                    ->helperText(fn ($get): string => $get('trigger_type') === 'comment'
                        ? 'DMed to whoever commented. Meta allows exactly ONE per comment, so put the '
                            . 'booking link in it.'
                        : 'Sent from the shared Haraan number, so say who you are. '
                            . 'Only works inside the 24h window opened by their message.')
                    ->required(),

                Textarea::make('public_reply_body')
                    ->label('Public reply under the comment')
                    ->rows(2)
                    ->maxLength(300)
                    ->placeholder('Just sent you a DM! 💬')
                    ->visible(fn ($get): bool => $get('trigger_type') === 'comment')
                    ->helperText('Optional, and visible to everyone reading the thread — which is what '
                        . 'makes the automation look deliberate rather than silent. Leave empty to skip it.'),

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
