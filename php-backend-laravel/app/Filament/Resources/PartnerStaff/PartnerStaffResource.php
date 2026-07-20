<?php

declare(strict_types=1);

namespace App\Filament\Resources\PartnerStaff;

use App\Filament\Resources\PartnerStaff\Pages\CreatePartnerStaff;
use App\Filament\Resources\PartnerStaff\Pages\EditPartnerStaff;
use App\Filament\Resources\PartnerStaff\Pages\ListPartnerStaff;
use App\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;

/**
 * Desk & scanner team for a partner. Staff are Users tied to the owner by
 * parent_partner_id, with a subset of capabilities (staff_permissions). This is
 * the web twin of the /api/partner/staff endpoints the app already uses.
 *
 * Only owners (not staff themselves) manage the team, and only in the /partner
 * console — the query is hard-scoped to the owner's own staff.
 */
class PartnerStaffResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'Staff & team';

    protected static ?string $modelLabel = 'staff member';

    protected static ?int $navigationSort = 6;

    protected static ?string $recordTitleAttribute = 'name';

    /** Owners only, in the partner console only. */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return Filament::getCurrentPanel()?->getId() === 'partner'
            && $user !== null
            && ! $user->isDeskStaff();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('parent_partner_id', auth()->user()?->effectivePartnerId());
    }

    // The User model has an admin-only policy; bypass it here — access is the
    // owner check in canAccess(), and every query is already scoped to the
    // owner's own staff, so create/edit/delete are safe within that lane.
    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    public static function canCreate(): bool
    {
        return static::canAccess();
    }

    public static function canView(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return static::canAccess();
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return static::canAccess();
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return static::canAccess();
    }

    /** @return array<string, string> */
    private static function permissionOptions(): array
    {
        return [
            'bookings' => 'Bookings — view & manage bookings',
            'checkin' => 'Check-in — scan tickets at the gate',
            'pricing' => 'Pricing — edit prices & slots',
            'reports' => 'Reports — view & export reports',
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(120),
            TextInput::make('email')
                ->email()
                ->required()
                ->maxLength(190)
                ->unique(table: 'users', column: 'email', ignoreRecord: true),
            TextInput::make('password')
                ->password()
                ->revealable()
                ->helperText(fn (string $operation): string => $operation === 'edit'
                    ? 'Leave blank to keep the current password.'
                    : 'At least 6 characters.')
                ->minLength(6)
                ->required(fn (string $operation): bool => $operation === 'create')
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->dehydrateStateUsing(fn (string $state): string => Hash::make($state)),
            CheckboxList::make('staff_permissions')
                ->label('Permissions')
                ->options(self::permissionOptions())
                ->columns(1)
                ->helperText('What this team member can do in the app.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->weight('bold')
                    ->searchable(),
                TextColumn::make('email')
                    ->color('gray')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('staff_permissions')
                    ->label('Permissions')
                    ->badge()
                    ->placeholder('No access')
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                TextColumn::make('created_at')
                    ->label('Added')
                    ->since()
                    ->sortable(),
            ])
            ->emptyStateHeading('No team members yet')
            ->emptyStateDescription('Add desk or gate staff and choose exactly what they can do.')
            ->emptyStateIcon(Heroicon::OutlinedUsers);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPartnerStaff::route('/'),
            'create' => CreatePartnerStaff::route('/create'),
            'edit' => EditPartnerStaff::route('/{record}/edit'),
        ];
    }
}
