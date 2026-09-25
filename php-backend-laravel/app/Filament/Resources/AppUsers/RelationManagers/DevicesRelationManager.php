<?php

declare(strict_types=1);

namespace App\Filament\Resources\AppUsers\RelationManagers;

use App\Models\DeviceToken;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DevicesRelationManager extends RelationManager
{
    protected static string $relationship = 'deviceTokens';

    protected static ?string $title = 'Connected Devices';

    protected static string | \BackedEnum | null $icon = 'heroicon-o-device-phone-mobile';

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function getBadge(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): ?string
    {
        /** @var \App\Models\User $ownerRecord */
        $count = $ownerRecord->deviceTokens()->count();

        return $count > 0 ? (string) $count : null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('last_seen_at', 'desc')
            ->columns([
                TextColumn::make('platform')
                    ->label('Platform')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => ucfirst(strtolower((string) $state)))
                    ->color(fn (?string $state): string => match (strtolower((string) $state)) {
                        'android' => 'success',
                        'ios' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('token')
                    ->label('Device push token')
                    ->formatStateUsing(fn (string $state): string => strlen($state) > 20
                        ? substr($state, 0, 10) . '••••••••' . substr($state, -6)
                        : $state)
                    ->copyable()
                    ->copyableState(fn (DeviceToken $r): string => $r->token),

                TextColumn::make('last_seen_at')
                    ->label('Last active')
                    ->since()
                    ->placeholder('Never')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('First registered')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),
            ]);
    }
}
