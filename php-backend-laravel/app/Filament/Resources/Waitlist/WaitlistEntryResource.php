<?php

declare(strict_types=1);

namespace App\Filament\Resources\Waitlist;

use App\Filament\Resources\Venues\VenueResource;
use App\Filament\Resources\Waitlist\Pages\ListWaitlistEntries;
use App\Filament\Resources\Waitlist\Schemas\WaitlistEntryForm;
use App\Filament\Resources\Waitlist\Tables\WaitlistEntriesTable;
use App\Models\WaitlistEntry;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * People waiting for a court-hour that was already sold.
 *
 * Cancellation is a venue's biggest single loss event. This is the surface that
 * turns it into revenue: add anyone who asked for a full slot, and when that slot
 * frees up {@see \App\Observers\BookingObserver} offers it automatically to the
 * front of the queue.
 *
 * Adding to the list is a bookings-class act — a front-desk person taking a call
 * should be able to do it.
 */
class WaitlistEntryResource extends Resource
{
    protected static ?string $model = WaitlistEntry::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $cluster = \App\Filament\Clusters\GameHub\GameHubCluster::class;

    protected static ?string $navigationLabel = 'Waitlist';

    protected static ?string $modelLabel = 'waitlist entry';

    protected static ?string $pluralModelLabel = 'waitlist';

    protected static ?int $navigationSort = 6;

    /** Entries own through their venue, like blocks, shifts and venue bookings. */
    public static function getEloquentQuery(): Builder
    {
        $query = WaitlistEntry::query();

        $user = auth()->user();

        if (Filament::getCurrentPanel()?->getId() === 'partner' && $user !== null && ! $user->isSuperAdmin()) {
            $query->whereIn('venue_id', VenueResource::getEloquentQuery()->select('venues.id'));
        }

        return $query;
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return ($user?->canManage('gamehub') ?? false)
            && $user->hasPartnerPermission('bookings');
    }

    public static function canCreate(): bool
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

    /** Live offers — somebody is waiting on a call right now. */
    public static function getNavigationBadge(): ?string
    {
        if (Filament::getCurrentPanel()?->getId() !== 'partner') {
            return null;
        }

        $count = static::getEloquentQuery()
            ->where('status', WaitlistEntry::STATUS_OFFERED)
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Freed slots offered and awaiting an answer';
    }

    public static function form(Schema $schema): Schema
    {
        return WaitlistEntryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WaitlistEntriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWaitlistEntries::route('/'),
        ];
    }
}
