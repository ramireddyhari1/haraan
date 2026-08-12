<?php

declare(strict_types=1);

namespace App\Filament\Resources\Partners\RelationManagers;

use App\Filament\Resources\Events\Pages\EventAnalytics;
use App\Models\Booking;
use App\Models\Event;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

/**
 * The events this partner (an organiser) has created, shown as a tab on the
 * partner overview page. Read-first — partners create their own events; admins
 * come here to see what a partner runs and jump into per-event analytics.
 */
class EventsRelationManager extends RelationManager
{
    protected static string $relationship = 'events';

    protected static ?string $title = 'Events';

    protected static string | \BackedEnum | null $icon = 'heroicon-o-ticket';

    /** Statuses that represent money actually earned. */
    private const PAID = ['confirmed', 'paid', 'completed', 'checked_in'];

    /**
     * Show the Events tab whenever this partner actually has events — not just
     * for event-typed partners. A venue owner who also runs events still sees
     * them here.
     */
    public static function canViewForRecord(mixed $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof User
            && $ownerRecord->events()->exists();
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->weight('bold')
                    ->description(fn (Event $r): ?string => $r->city)
                    ->searchable(),
                TextColumn::make('date')
                    ->dateTime('D, d M Y')
                    ->placeholder('TBD')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => ucfirst(strtolower((string) $state)))
                    ->color(fn (?string $state): string => strtolower((string) $state) === 'published' ? 'success' : 'gray'),
                TextColumn::make('sold')
                    ->label('Sold')
                    ->state(function (Event $r): string {
                        $cap  = max((int) $r->total_slots, 0);
                        $sold = $cap > 0 ? max($cap - max((int) $r->available_slots, 0), 0) : 0;
                        return $cap > 0 ? "{$sold} / {$cap}" : (string) $sold;
                    }),
                TextColumn::make('revenue')
                    ->label('Revenue')
                    ->state(fn (Event $r): string => '₹' . number_format((float) Booking::query()
                        ->where('event_id', $r->id)
                        ->whereIn(DB::raw('lower(status)'), self::PAID)
                        ->sum('total_amount'))),
            ])
            ->recordActions([
                Action::make('analytics')
                    ->label('Analytics')
                    ->icon('heroicon-m-chart-bar')
                    ->url(fn (Event $r): string => EventAnalytics::getUrl(['record' => $r]))
                    ->visible(fn (): bool => auth()->user()?->isSuperAdmin() ?? false),
            ])
            ->emptyStateHeading('No events yet')
            ->emptyStateDescription('This organiser has not created any events.');
    }
}
