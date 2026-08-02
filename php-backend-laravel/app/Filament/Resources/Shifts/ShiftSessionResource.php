<?php

declare(strict_types=1);

namespace App\Filament\Resources\Shifts;

use App\Filament\Resources\Shifts\Pages\ListShiftSessions;
use App\Filament\Resources\Shifts\Tables\ShiftSessionsTable;
use App\Filament\Resources\Venues\VenueResource;
use App\Models\ShiftSession;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Desk shifts and their cash close-outs.
 *
 * The control an owner actually wants: the system claims how much cash should be
 * in the drawer, someone counts it, and the difference is recorded against a named
 * person. Shifts open themselves on the first cash taken
 * ({@see \App\Services\ShiftService}), so the only deliberate act is the count.
 *
 * Reading this is a **reports**-class capability, not a bookings one — a desk
 * person should not be able to browse everyone else's variances. They can still
 * close their own shift, which is handled per-row in the table.
 */
class ShiftSessionResource extends Resource
{
    protected static ?string $model = ShiftSession::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-inbox-stack';

    protected static ?string $cluster = \App\Filament\Clusters\GameHub\GameHubCluster::class;

    protected static ?string $navigationLabel = 'Cash & shifts';

    protected static ?string $modelLabel = 'shift';

    protected static ?int $navigationSort = 5;

    /** Shifts own through their venue, like blocks and venue bookings. */
    public static function getEloquentQuery(): Builder
    {
        $query = ShiftSession::query();

        $user = auth()->user();

        if (Filament::getCurrentPanel()?->getId() === 'partner' && $user !== null && ! $user->isSuperAdmin()) {
            $query->whereIn('venue_id', VenueResource::getEloquentQuery()->select('venues.id'));

            // A desk person sees only their own drawer; owners and managers with
            // the reports capability see the whole desk.
            if (! $user->hasPartnerPermission('reports')) {
                $query->where('user_id', $user->id);
            }
        }

        return $query;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->canManage('gamehub') ?? false;
    }

    /** Shifts are opened by taking money, or by the header action — never typed in. */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    /** Deleting a close-out would destroy the audit trail it exists to create. */
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    /** Open shifts — money sitting in a drawer nobody has counted yet. */
    public static function getNavigationBadge(): ?string
    {
        if (Filament::getCurrentPanel()?->getId() !== 'partner') {
            return null;
        }

        $open = static::getEloquentQuery()->open()->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Shifts still open';
    }

    public static function table(Table $table): Table
    {
        return ShiftSessionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShiftSessions::route('/'),
        ];
    }
}
