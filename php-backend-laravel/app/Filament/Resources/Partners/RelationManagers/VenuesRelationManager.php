<?php

declare(strict_types=1);

namespace App\Filament\Resources\Partners\RelationManagers;

use App\Filament\Resources\Venues\Pages\EditVenue;
use App\Models\Booking;
use App\Models\User;
use App\Models\Venue;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

/**
 * The venues this partner (a venue owner) manages, shown as a tab on the
 * partner overview page. Read-first — admins come here to see what a partner
 * runs and how each venue earns, then jump into the venue to manage it.
 */
class VenuesRelationManager extends RelationManager
{
    protected static string $relationship = 'venues';

    protected static ?string $title = 'Venues';

    protected static string | \BackedEnum | null $icon = 'heroicon-o-map-pin';

    /** Statuses that represent money actually earned. */
    private const PAID = ['confirmed', 'paid', 'completed', 'checked_in'];

    /**
     * Show the Venues tab whenever this partner actually manages venues — not
     * just for venue-typed partners — mirroring the Events tab's "has rows" rule.
     */
    public static function canViewForRecord(mixed $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof User
            && $ownerRecord->venues()->exists();
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->weight('bold')
                    ->description(fn (Venue $r): ?string => $r->city)
                    ->searchable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                TextColumn::make('bookings')
                    ->label('Bookings')
                    ->state(fn (Venue $r): string => number_format((int) Booking::query()
                        ->where('booking_type', 'venue')
                        ->where('venue_id', $r->id)
                        ->whereIn(DB::raw('lower(status)'), self::PAID)
                        ->count())),
                TextColumn::make('revenue')
                    ->label('Revenue')
                    ->state(fn (Venue $r): string => '₹' . number_format((float) Booking::query()
                        ->where('booking_type', 'venue')
                        ->where('venue_id', $r->id)
                        ->whereIn(DB::raw('lower(status)'), self::PAID)
                        ->sum('total_amount'))),
                TextColumn::make('rating')
                    ->label('Rating')
                    ->formatStateUsing(fn (?float $state): string => $state ? number_format((float) $state, 1) . ' ★' : '—'),
            ])
            ->recordActions([
                Action::make('manage')
                    ->label('Manage')
                    ->icon('heroicon-m-pencil-square')
                    ->url(fn (Venue $r): string => EditVenue::getUrl(['record' => $r]))
                    ->visible(fn (): bool => auth()->user()?->isSuperAdmin() ?? false),
            ])
            ->emptyStateHeading('No venues yet')
            ->emptyStateDescription('This partner has no venues assigned.');
    }
}
