<?php

namespace App\Filament\Resources\ChannelConnections;

use App\Filament\Resources\ChannelConnections\Pages\CreateChannelConnection;
use App\Filament\Resources\ChannelConnections\Pages\EditChannelConnection;
use App\Filament\Resources\ChannelConnections\Pages\ListChannelConnections;
use App\Filament\Resources\ChannelConnections\Schemas\ChannelConnectionForm;
use App\Filament\Resources\ChannelConnections\Tables\ChannelConnectionsTable;
use App\Models\ChannelConnection;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

/**
 * Linked Instagram accounts. Super-admin only: a row here holds a page access
 * token that can read and send a partner's DMs.
 *
 * Entered by hand for now. A proper Meta OAuth flow would fetch the account id
 * and token itself; until then someone pastes them from the Meta dashboard.
 */
class ChannelConnectionResource extends Resource
{
    protected static ?string $model = ChannelConnection::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-link';

    protected static ?string $navigationLabel = 'Instagram accounts';

    protected static ?string $recordTitleAttribute = 'username';

    protected static ?int $navigationSort = 97;

    public static function canAccess(): bool
    {
        return auth()->user()?->canManage('admin') ?? false;
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Platform';
    }

    public static function form(Schema $schema): Schema
    {
        return ChannelConnectionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ChannelConnectionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListChannelConnections::route('/'),
            'create' => CreateChannelConnection::route('/create'),
            'edit' => EditChannelConnection::route('/{record}/edit'),
        ];
    }
}
