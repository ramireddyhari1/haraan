<?php

declare(strict_types=1);

namespace App\Filament\Resources\MemberPlans\Tables;

use App\Models\MemberPlan;
use App\Models\MemberPlanPrice;
use App\Models\MemberSubscription;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MemberPlansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('rank')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('prices')->withCount([
                'subscriptions as live_count' => fn (Builder $s) => $s->whereIn('status', [
                    MemberSubscription::STATUS_ACTIVE, MemberSubscription::STATUS_PENDING,
                ]),
            ]))
            ->columns([
                TextColumn::make('name')
                    ->weight('bold')
                    ->description(fn (MemberPlan $r): ?string => $r->tagline)
                    ->searchable(),

                TextColumn::make('code')->badge()->color('gray'),

                TextColumn::make('rank')->alignRight()->sortable(),

                TextColumn::make('prices_summary')
                    ->label('Prices')
                    ->state(function (MemberPlan $r): string {
                        if ($r->is_default) {
                            return 'Free';
                        }
                        $labels = $r->prices
                            ->map(fn (MemberPlanPrice $p): string => $p->label() . ($p->is_active ? '' : ' (off)'))
                            ->all();

                        return $labels === [] ? 'No prices' : implode(' · ', $labels);
                    })
                    // A paid plan shown in the app with no active price has no buy button
                    // behind it — members see it and can't take it.
                    ->description(fn (MemberPlan $r): ?string => self::unbuyable($r) ? 'Shown in the app but cannot be bought — add an active price' : null)
                    ->color(fn (MemberPlan $r): string => self::unbuyable($r) ? 'danger' : 'gray')
                    ->wrap(),

                TextColumn::make('live_count')
                    ->label('Live members')
                    ->alignRight(),

                IconColumn::make('is_default')->label('Default')->boolean(),
                IconColumn::make('is_active')->label('In app')->boolean(),
            ])
            ->recordActions([EditAction::make()])
            ->emptyStateHeading('No member plans')
            ->emptyStateDescription('Without a default plan, members get no entitlements at all.');
    }

    private static function unbuyable(MemberPlan $r): bool
    {
        return ! $r->is_default && $r->is_active && $r->prices->where('is_active', true)->isEmpty();
    }
}
