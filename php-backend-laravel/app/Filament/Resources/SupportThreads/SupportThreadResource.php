<?php

namespace App\Filament\Resources\SupportThreads;

use App\Filament\Resources\SupportThreads\Pages\EditSupportThread;
use App\Filament\Resources\SupportThreads\Pages\ListSupportThreads;
use App\Filament\Resources\SupportThreads\RelationManagers\MessagesRelationManager;
use App\Filament\Resources\SupportThreads\Schemas\SupportThreadForm;
use App\Filament\Resources\SupportThreads\Tables\SupportThreadsTable;
use App\Models\SupportThread;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class SupportThreadResource extends Resource
{
    protected static ?string $model = SupportThread::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'Support';

    protected static ?string $modelLabel = 'support conversation';

    protected static ?int $navigationSort = 20;

    /** Any control-panel user may help with support. */
    public static function canAccess(): bool
    {
        return (bool) auth()->user();
    }

    /** New threads always start in the app — never created from the panel. */
    public static function canCreate(): bool
    {
        return false;
    }

    /** Red badge counts conversations waiting on a reply. */
    public static function getNavigationBadge(): ?string
    {
        $count = SupportThread::query()->where('admin_unread_count', '>', 0)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function form(Schema $schema): Schema
    {
        return SupportThreadForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SupportThreadsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            MessagesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSupportThreads::route('/'),
            'edit'  => EditSupportThread::route('/{record}/edit'),
        ];
    }
}
