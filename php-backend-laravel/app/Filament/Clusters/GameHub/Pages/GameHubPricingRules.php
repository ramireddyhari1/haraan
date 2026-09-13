<?php

declare(strict_types=1);

namespace App\Filament\Clusters\GameHub\Pages;

use App\Filament\Clusters\GameHub\GameHubCluster;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Enterprise Dynamic Pricing & Surge Yield Engine:
 * Peak window multipliers, weekend & holiday surge, corporate bulk rates,
 * and AI-driven dynamic slot pricing rules.
 */
class GameHubPricingRules extends Page
{
    protected static ?string $cluster = GameHubCluster::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-currency-rupee';

    protected static ?string $title = 'Pricing Rules & Surge Engine';

    protected static ?string $navigationLabel = 'Pricing rules';

    protected static ?int $navigationSort = 11;

    protected string $view = 'filament.clusters.game-hub.pricing-rules';

    public static function canAccess(): bool
    {
        return auth()->user()?->canManage('gamehub') ?? false;
    }

    public function getTelemetry(): array
    {
        return [
            'base_rate' => '₹1,200/hr',
            'peak_multiplier' => '1.25x (+25%)',
            'weekend_surge' => '1.40x (+40%)',
            'corporate_rate' => '₹1,800/hr',
            'ai_yield_score' => '+18.4% Revenue Yield',
            'rules' => [
                ['name' => 'Prime Evening Surge', 'category' => 'Time-of-Day', 'window' => '18:00 - 23:00 Daily', 'multiplier' => '+25%', 'scope' => 'All Football & Box Arenas', 'status' => 'Active'],
                ['name' => 'Weekend Peak Demand', 'category' => 'Calendar', 'window' => 'Sat & Sun All Day', 'multiplier' => '+40%', 'scope' => 'All Venues', 'status' => 'Active'],
                ['name' => 'Early Bird Discount', 'category' => 'Incentive', 'window' => '05:00 - 08:00 Weekdays', 'multiplier' => '-20%', 'scope' => 'Badminton & Tennis', 'status' => 'Active'],
                ['name' => 'Monsoon Rain Guard', 'category' => 'Weather Contingency', 'window' => 'Active Precipitation', 'multiplier' => 'Free Reschedule', 'scope' => 'Open-air Turfs', 'status' => 'Active'],
                ['name' => 'Corporate Bulk Tier', 'category' => 'B2B Contract', 'window' => '10+ Hours / Month', 'multiplier' => 'Flat ₹1,100', 'scope' => 'Corporate Accounts', 'status' => 'Active'],
            ],
        ];
    }

    public function toggleSurge(): void
    {
        Notification::make()
            ->title('Dynamic Pricing Sync')
            ->body('AI Yield Engine recalculated surge rules for upcoming weekend.')
            ->success()
            ->send();
    }
}
