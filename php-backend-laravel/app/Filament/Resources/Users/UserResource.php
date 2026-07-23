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
use Illuminate\Database\Eloquent\Builder;

class UserResource extends Resource
{
    // Alias the org-scoping query so we can layer the staff-role filter on top.
    use ScopesToOrganization {
        getEloquentQuery as protected scopedOrgQuery;
    }

    /** Internal team roles — this resource is the staff list only (partners + app users have their own). */
    public const STAFF_ROLES = ['ADMIN', 'COADMIN', 'OPS', 'FINANCE', 'MARKETING'];

    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static string|\UnitEnum|null $navigationGroup = 'People';

    protected static ?int $navigationSort = 2;

    /** Only internal staff accounts (role-scoped, case-insensitive), on top of org scoping. */
    public static function getEloquentQuery(): Builder
    {
        return static::scopedOrgQuery()
            ->whereRaw('upper(role) in (' . implode(',', array_fill(0, count(self::STAFF_ROLES), '?')) . ')', self::STAFF_ROLES);
    }

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

    protected static ?string $navigationLabel = 'Staff';

    protected static ?string $modelLabel = 'staff member';

    protected static ?string $pluralModelLabel = 'staff';

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
