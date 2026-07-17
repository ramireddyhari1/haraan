<?php

declare(strict_types=1);

namespace App\Filament\Resources\Notifications\Widgets;

use App\Models\Notification;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The campaign scoreboard above the notifications list — how the messaging is landing
 * in aggregate: how many were sent, how many opens they earned, the blended open rate,
 * and which single campaign performed best. Reads are the only delivery signal we have
 * today (bell opens); FCM delivery receipts would slot in here later.
 */
class NotificationAnalyticsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $sent = Notification::query()->where('status', 'sent')->withCount('reads')->get();

        if ($sent->isEmpty()) {
            return [
                Stat::make('Sent campaigns', '0')
                    ->description('Nothing delivered yet')
                    ->descriptionIcon('heroicon-m-paper-airplane')
                    ->color('gray'),
            ];
        }

        $totalReach = $sent->sum(fn (Notification $n) => $n->reach());
        $totalOpens = (int) $sent->sum('reads_count');
        $blended = $totalReach > 0 ? round(min($totalOpens, $totalReach) / $totalReach * 100, 1) : 0.0;

        $best = $sent
            ->filter(fn (Notification $n) => $n->openRate() !== null)
            ->sortByDesc(fn (Notification $n) => $n->openRate())
            ->first();

        return [
            Stat::make('Sent campaigns', (string) $sent->count())
                ->description($totalOpens . ' total opens')
                ->descriptionIcon('heroicon-m-paper-airplane')
                ->color('primary'),

            Stat::make('Blended open rate', $blended . '%')
                ->description($totalOpens . ' opens across ' . $totalReach . ' reached')
                ->descriptionIcon($blended >= 25 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-chart-bar')
                ->color($blended >= 40 ? 'success' : ($blended >= 15 ? 'warning' : 'danger')),

            $best !== null
                ? Stat::make('Best campaign', $best->openRate() . '%')
                    ->description(\Illuminate\Support\Str::limit($best->title, 34))
                    ->descriptionIcon('heroicon-m-trophy')
                    ->color('success')
                : Stat::make('Best campaign', '—')
                    ->description('No opens recorded yet')
                    ->descriptionIcon('heroicon-m-trophy')
                    ->color('gray'),
        ];
    }
}
