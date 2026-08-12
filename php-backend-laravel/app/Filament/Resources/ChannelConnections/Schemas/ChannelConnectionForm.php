<?php

namespace App\Filament\Resources\ChannelConnections\Schemas;

use App\Models\ChannelConnection;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ChannelConnectionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('partner_id')
                    ->label('Partner')
                    ->relationship('partner', 'name', fn ($query) => $query->whereNotNull('partner_type'))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->helperText('Every DM to this account is attributed to them.'),

                TextInput::make('external_id')
                    ->label('Instagram account id')
                    ->required()
                    ->helperText('The professional account id from the Meta dashboard (a long number, '
                        . 'not the @handle). Inbound DMs are routed by this.'),

                TextInput::make('username')
                    ->label('@handle')
                    ->prefix('@')
                    ->helperText('Display only — makes this list readable.'),

                TextInput::make('page_id')
                    ->label('Linked Facebook page id')
                    ->helperText('Instagram messaging requires the account to be linked to a page.'),

                TextInput::make('access_token')
                    ->label('Page access token')
                    ->password()
                    ->revealable()
                    // Encrypted at rest; blank on edit means "keep the stored one",
                    // so the secret is never round-tripped to the browser.
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->helperText('Stored encrypted. Leave blank when editing to keep the current token.'),

                DateTimePicker::make('token_expires_at')
                    ->label('Token expires')
                    ->helperText('Optional. Past this, replies stop and the connection reads as unusable.'),

                Select::make('status')
                    ->options([
                        ChannelConnection::STATUS_ACTIVE => 'Active',
                        ChannelConnection::STATUS_DISCONNECTED => 'Disconnected',
                        ChannelConnection::STATUS_ERROR => 'Error — token rejected by Meta',
                    ])
                    ->default(ChannelConnection::STATUS_ACTIVE)
                    ->required(),
            ]);
    }
}
