<?php

declare(strict_types=1);

namespace App\Filament\Resources\AppUsers\Widgets;

use App\Models\DeviceToken;
use App\Models\ReputationEvent;
use App\Models\User;
use App\Support\ContactPrefill;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

/**
 * Enterprise Risk & Trust Intelligence Widget for User 360.
 *
 * Grounded 100% in authentic, verifiable database facts:
 *  - Trust score & ActionBoard reputation penalties
 *  - Player moderation reports & community blocks
 *  - Booking fulfillment ratio & cancellation flakiness
 *  - Push device tokens & cross-account device collision detection
 *  - Credential stability and session token versioning
 */
class UserRiskTrustWidget extends StatsOverviewWidget
{
    public ?User $record = null;

    protected int | string | array $columnSpan = 'full';

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

        // 1. Trust Score & ActionBoard Reputation
        $trustScore = (int) ($user->trust_score ?? 100);
        $penaltiesCount = 0;
        $totalPenalties = 0;

        if ($user->player_id) {
            $penaltiesCount = (int) ReputationEvent::where('player_id', $user->player_id)->count();
            $totalPenalties = (int) ReputationEvent::where('player_id', $user->player_id)->sum('amount');
        }

        $tier = match (true) {
            $trustScore >= 90 => ['label' => 'Exemplary', 'color' => 'success'],
            $trustScore >= 75 => ['label' => 'Good Standing', 'color' => 'info'],
            $trustScore >= 50 => ['label' => 'Watchlist', 'color' => 'warning'],
            default           => ['label' => 'High Risk', 'color' => 'danger'],
        };

        // 2. Moderation Reports & Community Blocks
        $openReports = (int) $user->receivedPlayerReports()->where('status', 'open')->count();
        $totalReports = (int) $user->receivedPlayerReports()->count();
        $blockedByCount = (int) $user->blockedByOthers()->count();

        // 3. Booking Reliability & Flakiness
        $totalBookings = (int) ($user->bookings_count ?? $user->bookings()->count());
        $lostBookings = (int) $user->bookings()->whereIn(DB::raw('lower(status)'), ['cancelled', 'canceled', 'refunded', 'failed'])->count();
        $paidBookings = (int) $user->bookings()->whereIn(DB::raw('lower(status)'), ['confirmed', 'paid', 'completed', 'checked_in'])->count();
        $cancellationRate = $totalBookings > 0 ? (int) round(($lostBookings / $totalBookings) * 100) : 0;
        $isFlaky = $cancellationRate >= 40 && $totalBookings >= 3;

        // 4. Device Integrity & Cross-Account Collision Detection
        $myTokens = $user->deviceTokens()->pluck('token')->filter()->all();
        $tokenCollisions = empty($myTokens)
            ? 0
            : (int) DeviceToken::query()
                ->whereIn('token', $myTokens)
                ->where('user_id', '!=', $user->id)
                ->distinct('user_id')
                ->count('user_id');

        $phoneCollisions = filled($user->phone)
            ? (int) User::query()->where('phone', $user->phone)->where('id', '!=', $user->id)->count()
            : 0;

        $totalCollisions = $tokenCollisions + $phoneCollisions;

        // 5. Composite Risk Assessment (Derived solely from verified real metrics)
        $isSuspended = ! $user->isAccountActive();
        $riskAssessment = match (true) {
            $isSuspended                                => ['label' => 'Critical / Suspended', 'color' => 'danger', 'desc' => 'Account is suspended from platform'],
            $totalCollisions > 0                        => ['label' => 'Critical Sybil Risk', 'color' => 'danger', 'desc' => $phoneCollisions > 0 ? "Phone shared with {$phoneCollisions} other user(s)!" : "Device shared with {$tokenCollisions} other user(s)!"],
            $trustScore < 50 || $openReports >= 3       => ['label' => 'High Risk Profile', 'color' => 'danger', 'desc' => "{$openReports} open complaint(s) pending review"],
            $trustScore < 75 || $openReports >= 1       => ['label' => 'Moderate Concern', 'color' => 'warning', 'desc' => "Score {$trustScore} · {$openReports} open complaint"],
            $isFlaky                                    => ['label' => 'Booking Flakiness', 'color' => 'warning', 'desc' => "{$cancellationRate}% cancellation rate across {$totalBookings} orders"],
            default                                     => ['label' => 'Low Risk / Trusted', 'color' => 'success', 'desc' => 'Zero collisions · Clean standing'],
        };

        // 6. Credential & Identity Integrity
        $isVerified = (bool) $user->is_verified;
        $tokenVersion = (int) ($user->token_version ?? 1);
        $hasRealEmail = ContactPrefill::isRealEmail($user->email);

        return [
            Stat::make('Trust & Reputation', "{$trustScore}/100 · {$tier['label']}")
                ->description($penaltiesCount > 0 ? "{$penaltiesCount} penalty event(s) (-{$totalPenalties} pts)" : 'Zero reputation penalties on file')
                ->descriptionIcon('heroicon-m-shield-check')
                ->color($tier['color']),

            Stat::make('Risk Assessment', $riskAssessment['label'])
                ->description($riskAssessment['desc'])
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($riskAssessment['color']),

            Stat::make('Safety & Moderation', "{$totalReports} " . str('report')->plural($totalReports) . " · {$blockedByCount} blocks")
                ->description($openReports > 0 ? "{$openReports} open report(s) needing review" : ($totalReports > 0 ? 'All historical reports resolved' : 'No player complaints on file'))
                ->descriptionIcon('heroicon-m-flag')
                ->color($openReports > 0 ? 'danger' : ($totalReports > 0 ? 'warning' : 'gray')),

            Stat::make('Booking Reliability', "{$paidBookings} of {$totalBookings} completed")
                ->description($lostBookings > 0 ? "{$lostBookings} cancelled/refunded ({$cancellationRate}%)" : '100% booking fulfillment rate')
                ->descriptionIcon('heroicon-m-arrow-path-rounded-square')
                ->color($isFlaky ? 'danger' : ($lostBookings > 0 ? 'warning' : 'success')),

            Stat::make('Device Integrity', count($myTokens) . ' registered ' . str('device')->plural(count($myTokens)))
                ->description($totalCollisions > 0 ? "⚠️ Identity collision with {$totalCollisions} account(s)" : 'No multi-account collisions')
                ->descriptionIcon('heroicon-m-device-phone-mobile')
                ->color($totalCollisions > 0 ? 'danger' : 'success'),

            Stat::make('Account Integrity', $isVerified ? 'Verified Citizen' : 'Standard Account')
                ->description("v{$tokenVersion} token version · " . ($hasRealEmail ? 'Verified email' : 'Phone signup'))
                ->descriptionIcon($isVerified ? 'heroicon-m-check-badge' : 'heroicon-m-user')
                ->color($isVerified ? 'success' : 'gray'),
        ];
    }
}
