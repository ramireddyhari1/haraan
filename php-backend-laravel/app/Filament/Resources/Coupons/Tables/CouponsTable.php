<?php

namespace App\Filament\Resources\Coupons\Tables;

use App\Models\Coupon;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CouponsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('code')
                    ->weight('bold')
                    ->badge()
                    ->color('gray')
                    ->copyable()
                    ->searchable(),

                TextColumn::make('event.title')
                    ->label('Scope')
                    ->placeholder('All events')
                    ->description(fn (Coupon $r): ?string => $r->event_id ? null : 'Site-wide')
                    ->searchable(),

                TextColumn::make('discount')
                    ->label('Takes off')
                    ->money('INR')
                    ->sortable(),

                // Redemptions vs cap — with a colour that warns as the code is used up.
                TextColumn::make('uses')
                    ->label('Redemptions')
                    ->badge()
                    ->state(fn (Coupon $r): string => self::usageLabel($r))
                    ->color(fn (Coupon $r): string => self::usageColor($r))
                    ->sortable(),

                TextColumn::make('active')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state ? 'Active' : 'Inactive')
                    ->color(fn ($state): string => $state ? 'success' : 'gray'),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('active')->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /** "12 / 100" used vs cap, or just the count when the coupon is uncapped. */
    private static function usageLabel(Coupon $r): string
    {
        $max = (int) $r->max_uses;
        $used = (int) $r->uses;

        return $max > 0 ? "{$used} / {$max}" : (string) $used;
    }

    /** Grey while unused, green while redeeming, red once the cap is hit. */
    private static function usageColor(Coupon $r): string
    {
        $max = (int) $r->max_uses;
        $used = (int) $r->uses;

        if ($max > 0 && $used >= $max) {
            return 'danger';
        }

        return $used > 0 ? 'success' : 'gray';
    }
}
