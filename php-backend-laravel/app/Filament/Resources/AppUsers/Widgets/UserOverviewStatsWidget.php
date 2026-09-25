<?php

declare(strict_types=1);

namespace App\Filament\Resources\AppUsers\Widgets;

use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Headline financial, membership, and activity telemetry for a single user's 360 profile.
 * Reads denormalized headline KPIs directly from the user record and caches relation
 * aggregates for high responsiveness.
 */
class UserOverviewStatsWidget extends StatsOverviewWidget
{
    public ?User $record = null;

    protected int | string | array $columnSpan = 'full';

    private const PAID_STATUSES = ['confirmed', 'paid', 'completed', 'checked_in'];
    private const LOST_STATUSES = ['cancelled', 'canceled', 'refunded', 'failed'];

    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        $user = $this->record;

        if (! $user) {
            return [];
        }

        /** @var array{lifetimeSpend: float, paidCount: int, totalBookings: int, lostCount: int, planName: string, subDescription: string, hasActiveSub: bool, matchesCount: int, rankedXp: int, casualXp: int, openTickets: int, totalTickets: int, activeRewards: int, totalBonusXp: int} $data */
        $data = Cache::remember("user:{$user->id}:overview_kpis", 60, function () use ($user): array {
            if ($user->last_kpi_calculated_at === null) {
                $user->recalculateKpiMetrics();
            }

            // 1. Financial & Bookings (Headline KPIs read from denormalized user columns)
            $lifetimeSpend = (float) ($user->lifetime_spend ?? 0.0);
            $totalBookings = (int) ($user->bookings_count ?? 0);

            $paidBookingsQuery = $user->bookings()->whereIn(DB::raw('lower(status)'), self::PAID_STATUSES);
            $paidCount = (int) (clone $paidBookingsQuery)->count();
            $lostCount = (int) $user->bookings()->whereIn(DB::raw('lower(status)'), self::LOST_STATUSES)->count();

            // 2. Membership Status
            $activeSub = $user->memberSubscriptions()
                ->with('plan')
                ->whereIn('status', ['active', 'authenticated'])
                ->latest('id')
                ->first();

            $planName = $activeSub?->plan?->name ?? 'Standard Free';
            $subDescription = $activeSub
                ? ($activeSub->current_period_end ? 'Active · Renews ' . $activeSub->current_period_end->format('d M Y') : 'Active entitlement')
                : 'No active paid pass';
            $hasActiveSub = $activeSub !== null;

            // 3. GameHub Activity
            $matchesCount = (int) ($user->matches_played_count ?? 0);
            $rankedXp = (int) ($user->ranked_xp ?? 0);
            $casualXp = (int) ($user->casual_xp ?? 0);

            // 4. Support Activity
            $openTickets = (int) $user->supportThreads()->whereIn('status', ['open', 'pending'])->count();
            $totalTickets = (int) $user->supportThreads()->count();

            // 5. Rewards & Loyalty
            $activeRewards = (int) $user->rewardGrants()->whereIn('status', ['available', 'claimed'])->count();
            $totalBonusXp = (int) $user->rewardGrants()->sum('bonus_xp');

            return [
                'lifetimeSpend'  => $lifetimeSpend,
                'paidCount'      => $paidCount,
                'totalBookings'  => $totalBookings,
                'lostCount'      => $lostCount,
                'planName'       => $planName,
                'subDescription' => $subDescription,
                'hasActiveSub'   => $hasActiveSub,
                'matchesCount'   => $matchesCount,
                'rankedXp'       => $rankedXp,
                'casualXp'       => $casualXp,
                'openTickets'    => $openTickets,
                'totalTickets'   => $totalTickets,
                'activeRewards'  => $activeRewards,
                'totalBonusXp'   => $totalBonusXp,
            ];
        });

        return [
            Stat::make('Lifetime spend', $this->money($data['lifetimeSpend']))
                ->description($data['paidCount'] . ' ' . str('paid booking')->plural($data['paidCount']))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Bookings placed', number_format($data['totalBookings']))
                ->description($data['lostCount'] > 0 ? "{$data['lostCount']} cancelled / refunded" : 'All bookings in good standing')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color($data['totalBookings'] > 0 ? 'primary' : 'gray'),

            Stat::make('Membership tier', $data['planName'])
                ->description($data['subDescription'])
                ->descriptionIcon('heroicon-m-sparkles')
                ->color($data['hasActiveSub'] ? 'warning' : 'gray'),

            Stat::make('GameHub matches', number_format($data['matchesCount']))
                ->description(number_format($data['rankedXp']) . ' Ranked XP · ' . number_format($data['casualXp']) . ' Casual XP')
                ->descriptionIcon('heroicon-m-trophy')
                ->color('info'),

            Stat::make('Support tickets', number_format($data['openTickets']) . ' open')
                ->description($data['totalTickets'] . ' total ' . str('ticket')->plural($data['totalTickets']))
                ->descriptionIcon('heroicon-m-chat-bubble-left-right')
                ->color($data['openTickets'] > 0 ? 'danger' : 'gray'),

            Stat::make('Rewards & bonus', number_format($data['activeRewards']) . ' active')
                ->description('+' . number_format($data['totalBonusXp']) . ' Bonus XP awarded')
                ->descriptionIcon('heroicon-m-gift')
                ->color('primary'),
        ];
    }

    private function money(float $amount): string
    {
        return '₹' . number_format($amount);
    }
}
