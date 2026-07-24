<?php

namespace App\Filament\Resources\ChannelConnections\Tables;

use App\Models\ChannelConnection;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ChannelConnectionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('username')
                    ->label('Account')
                    ->formatStateUsing(fn ($state): string => $state ? '@' . $state : '—')
                    ->weight('bold')
                    ->searchable(),

                TextColumn::make('partner.name')->label('Partner')->searchable(),

                TextColumn::make('external_id')->label('Account id')->color('gray')->limit(20),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        ChannelConnection::STATUS_ACTIVE => 'success',
                        ChannelConnection::STATUS_ERROR => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('token_expires_at')
                    ->label('Token expires')
                    ->dateTime('d M Y')
                    ->placeholder('No expiry')
                    ->color(fn ($record): string => $record->token_expires_at?->isPast() ? 'danger' : 'gray'),

                TextColumn::make('last_error')
                    ->label('Last error')
                    ->placeholder('—')
                    ->limit(40)
                    ->color('danger')
                    ->tooltip(fn ($record): ?string => $record->last_error),
            ])
            ->recordActions([EditAction::make()])
            ->emptyStateHeading('No Instagram accounts linked')
            ->emptyStateDescription('DMs to an unlinked account are ignored — there is no partner to attribute them to.');
    }
}
