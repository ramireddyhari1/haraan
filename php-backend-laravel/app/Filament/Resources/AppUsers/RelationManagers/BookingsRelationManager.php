<?php

declare(strict_types=1);

namespace App\Filament\Resources\AppUsers\RelationManagers;

use App\Filament\Support\BookingTablePresenter;
use App\Models\Booking;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BookingsRelationManager extends RelationManager
{
    protected static string $relationship = 'bookings';

    protected static ?string $title = 'Bookings';

    protected static string | \BackedEnum | null $icon = 'heroicon-o-calendar-days';

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function getBadge(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): ?string
    {
        /** @var User $ownerRecord */
        $count = (int) ($ownerRecord->bookings_count ?? $ownerRecord->bookings()->count());

        return $count > 0 ? (string) $count : null;
    }

    public function table(Table $table): Table
    {
        /** @var User $user */
        $user = $this->getOwnerRecord();

        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['event', 'venue']))
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),

                TextColumn::make('ticket_code')
                    ->label('Ticket code')
                    ->copyable()
                    ->badge()
                    ->color('info')
                    ->searchable(),

                TextColumn::make('booking_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => strtolower((string) $state) === 'venue' ? 'Turf' : 'Event')
                    ->color(fn (?string $state): string => strtolower((string) $state) === 'venue' ? 'success' : 'primary'),

                TextColumn::make('item_name')
                    ->label('Venue / Event')
                    ->state(fn (Booking $r): string => $r->booking_type === 'venue'
                        ? ($r->venue?->name ?? 'Venue booking')
                        : ($r->event?->title ?? 'Event ticket'))
                    ->weight('bold'),

                TextColumn::make('booking_schedule')
                    ->label('Date & Slot')
                    ->state(function (Booking $r): string {
                        if ($r->booking_date) {
                            $slot = trim(($r->start_time ?? '') . ' - ' . ($r->end_time ?? ''));

                            return $r->booking_date . ($slot !== '-' ? ' (' . $slot . ')' : '');
                        }

                        return $r->event?->date ? $r->event->date->format('d M Y') : '—';
                    }),

                TextColumn::make('quantity')
                    ->label('Qty')
                    ->numeric()
                    ->alignEnd(),

                TextColumn::make('total_amount')
                    ->label('Amount')
                    ->money('INR')
                    ->weight('bold')
                    ->alignEnd()
                    ->sortable(),

                BookingTablePresenter::statusColumn()->sortable(),

                TextColumn::make('created_at')
                    ->label('Booked')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),
            ])
            ->headerActions([
                Action::make('openInBookings')
                    ->label('View in Bookings desk')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (): string => route('filament.control.events.resources.bookings.index', [
                        'tableFilters' => ['user_id' => ['value' => $user->id]],
                    ]))
                    ->openUrlInNewTab(),
            ]);
    }
}
