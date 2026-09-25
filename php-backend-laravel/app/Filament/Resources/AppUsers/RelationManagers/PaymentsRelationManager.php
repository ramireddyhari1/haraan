<?php

declare(strict_types=1);

namespace App\Filament\Resources\AppUsers\RelationManagers;

use App\Models\BookingPayment;
use App\Models\User;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'bookingPayments';

    protected static ?string $title = 'Payments';

    protected static string | \BackedEnum | null $icon = 'heroicon-o-banknotes';

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function getBadge(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): ?string
    {
        /** @var \App\Models\User $ownerRecord */
        $count = $ownerRecord->bookingPayments()->count();

        return $count > 0 ? (string) $count : null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['booking', 'collector']))
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),

                TextColumn::make('booking_id')
                    ->label('Booking #')
                    ->formatStateUsing(fn (?int $state): string => $state ? "#{$state}" : '—')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('amount')
                    ->label('Amount')
                    ->formatStateUsing(fn (BookingPayment $r): string => ($r->amount < 0 ? '-' : '+') . '₹' . number_format(abs((float) $r->amount), 2))
                    ->color(fn (BookingPayment $r): string => (float) $r->amount >= 0 ? 'success' : 'danger')
                    ->weight('bold')
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('method')
                    ->label('Method')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => strtoupper((string) $state))
                    ->color(fn (?string $state): string => match (strtolower((string) $state)) {
                        'upi' => 'info',
                        'cash' => 'warning',
                        'card' => 'primary',
                        default => 'gray',
                    }),

                TextColumn::make('reference')
                    ->label('Payment ref')
                    ->copyable()
                    ->placeholder('—'),

                TextColumn::make('collector.name')
                    ->label('Collected by')
                    ->placeholder('Online gateway')
                    ->description(fn (BookingPayment $r): ?string => $r->collector?->email),

                TextColumn::make('note')
                    ->label('Note')
                    ->placeholder('—')
                    ->wrap(),

                TextColumn::make('collected_at')
                    ->label('Timestamp')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),
            ]);
    }
}
