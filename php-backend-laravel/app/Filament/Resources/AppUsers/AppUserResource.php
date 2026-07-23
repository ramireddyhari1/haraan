<?php

declare(strict_types=1);

namespace App\Filament\Resources\AppUsers;

use App\Filament\Concerns\ScopesToOrganization;
use App\Filament\Resources\AppUsers\Pages\EditAppUser;
use App\Filament\Resources\AppUsers\Pages\ListAppUsers;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * App end-users — the people who use the Haraan app/site (players, ticket
 * buyers). Split out from the internal Staff list so operators manage the two
 * populations separately. Read-and-manage only: users self-register, so there's
 * no admin create; edit is for status/support, delete is super-admin only.
 */
class AppUserResource extends Resource
{
    // Layer the "regular user" role filter on top of org scoping.
    use ScopesToOrganization {
        getEloquentQuery as protected scopedOrgQuery;
    }

    /** Roles that are NOT app users (internal staff + partners have their own lists). */
    public const NON_USER_ROLES = ['ADMIN', 'COADMIN', 'OPS', 'FINANCE', 'MARKETING', 'PARTNER'];

    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|\UnitEnum|null $navigationGroup = 'People';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Users';

    protected static ?string $modelLabel = 'user';

    protected static ?string $recordTitleAttribute = 'name';

    /** Everyone who isn't internal staff or a partner — treats a null role as a user. */
    public static function getEloquentQuery(): Builder
    {
        return static::scopedOrgQuery()
            ->whereRaw(
                "upper(coalesce(role, 'USER')) not in (" . implode(',', array_fill(0, count(self::NON_USER_ROLES), '?')) . ')',
                self::NON_USER_ROLES,
            );
    }

    /** Super-admins and Operations manage the people lists. */
    private static function canManagePeople(): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->isSuperAdmin() || $user->hasRoleEither(['OPS']));
    }

    public static function canAccess(): bool
    {
        return static::canManagePeople();
    }

    public static function canViewAny(): bool
    {
        return static::canManagePeople();
    }

    public static function canCreate(): bool
    {
        return false; // app users self-register
    }

    public static function canEdit(Model $record): bool
    {
        return static::canManagePeople();
    }

    /** Deleting a real user account is destructive — super-admins only. */
    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email', 'phone', 'district'];
    }

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAppUsers::route('/'),
            'edit' => EditAppUser::route('/{record}/edit'),
        ];
    }
}
