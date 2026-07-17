<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Filament\Concerns\ScopesToOrganization;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class UserResource extends Resource
{
    use ScopesToOrganization;

    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|\UnitEnum|null $navigationGroup = 'People';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email', 'phone', 'district'];
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        return array_filter([
            'Email' => $record->email,
            'District' => $record->district,
        ]);
    }

    protected static ?string $navigationLabel = 'Staff & users';

    /** Super-admins and Operations can manage staff. */
    public static function canManageStaff(): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->isSuperAdmin() || $user->hasRoleEither(['OPS']));
    }

    public static function canAccess(): bool
    {
        return static::canManageStaff();
    }

    public static function canViewAny(): bool
    {
        return static::canManageStaff();
    }

    public static function canCreate(): bool
    {
        return static::canManageStaff();
    }

    /** Only a super-admin may edit/delete another super-admin — no privilege escalation. */
    public static function canEdit($record): bool
    {
        if ($record->isSuperAdmin() && ! (auth()->user()?->isSuperAdmin() ?? false)) {
            return false;
        }

        return static::canManageStaff();
    }

    public static function canDelete($record): bool
    {
        if ($record->isSuperAdmin() && ! (auth()->user()?->isSuperAdmin() ?? false)) {
            return false;
        }

        return static::canManageStaff();
    }

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\Users\RelationManagers\OrganizationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
