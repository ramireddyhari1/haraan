<?php

declare(strict_types=1);

namespace App\Filament\Clusters\Marketing\Widgets;

use App\Models\Ad;
use App\Models\Coupon;
use App\Models\FeedItem;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MarketingStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = -2;

    protected function getStats(): array
    {
        $activeAds = Ad::where('is_active', true)->count();
        $forYou = FeedItem::where('section', 'for_you')->where('is_active', true)->count();
        $trending = FeedItem::where('section', 'trending')->where('is_active', true)->count();
        $activeCoupons = Coupon::where('active', true)->count();
        $couponUses = (int) Coupon::sum('uses');
        $couponCap = (int) Coupon::sum('max_uses');
        $redemptionRate = $couponCap > 0 ? round($couponUses / $couponCap * 100) : 0;

        return [
            Stat::make('Active Ads', (string) $activeAds)
                ->description('Live in the home feed')
                ->descriptionIcon('heroicon-m-megaphone')
                ->color('warning'),
            Stat::make('Feed Cards', (string) ($forYou + $trending))
                ->description("$forYou For You · $trending Trending")
                ->descriptionIcon('heroicon-m-squares-2x2')
                ->color('info'),
            Stat::make('Active Coupons', (string) $activeCoupons)
                ->description("$couponUses redemptions")
                ->descriptionIcon('heroicon-m-tag')
                ->color('success'),
            Stat::make('Redemption Rate', $redemptionRate . '%')
                ->description('Uses vs total cap')
                ->descriptionIcon('heroicon-m-chart-pie')
                ->color('primary'),
        ];
    }
}
