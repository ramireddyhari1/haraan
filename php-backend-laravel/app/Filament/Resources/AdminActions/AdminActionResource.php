<?php

namespace App\Filament\Resources\AdminActions;

use App\Filament\Resources\AdminActions\Pages\CreateAdminAction;
use App\Filament\Resources\AdminActions\Pages\EditAdminAction;
use App\Filament\Resources\AdminActions\Pages\ListAdminActions;
use App\Filament\Resources\AdminActions\Schemas\AdminActionForm;
use App\Filament\Resources\AdminActions\Tables\AdminActionsTable;
use App\Models\AdminAction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AdminActionResource extends Resource
{
    protected static ?string $model = AdminAction::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Audit Log';

    protected static ?string $modelLabel = 'audit entry';

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    /** Audit entries are written by the system, never created/edited by hand. */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return AdminActionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AdminActionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdminActions::route('/'),
        ];
    }
}
