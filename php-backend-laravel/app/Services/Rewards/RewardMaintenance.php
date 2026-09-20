<?php

declare(strict_types=1);

namespace App\Services\Rewards;

use App\Models\RewardedAdSession;
use App\Models\RewardGrant;
use App\Models\User;
use App\Support\PlatformRules;
use App\Support\Rewards\RewardTypes;

/**
 * The scheduled housekeeping of the reward ledger (every 15 minutes):
 *  - rewards not unlocked or claimed by their expiry expire (a reserved sponsor code goes
 *    back to its pool);
 *  - claimed reward coupons that ran out unused are marked expired;
 *  - players get one reminder per reward before it expires;
 *  - rewarded-ad sessions Google never verified expire.
 */
final class RewardMaintenance
{
    public function __construct(
        private readonly RewardLedger $ledger,
        private readonly RewardNotifier $notifier,
    ) {}

    /** @return array{expired: int, coupons_expired: int, warned: int, sessions_expired: int} */
    public function run(): array
    {
        $expired = 0;
        RewardGrant::query()->whereIn('status', RewardGrant::OPEN)
            ->whereNotNull('expires_at')->where('expires_at', '<', now())
            ->orderBy('id')->chunkById(200, function ($grants) use (&$expired): void {
                foreach ($grants as $grant) {
                    $expired += $this->ledger->expire($grant) ? 1 : 0;
                }
            });

        $couponsExpired = RewardGrant::query()
            ->where('status', RewardGrant::CLAIMED)->where('type', RewardTypes::HARAAN_COUPON)
            ->whereIn('coupon_id', fn ($q) => $q->select('id')->from('coupons')
                ->where('uses', 0)->whereNotNull('expires_at')->where('expires_at', '<', now()))
            ->update(['status' => RewardGrant::EXPIRED, 'status_reason' => 'coupon_expired', 'updated_at' => now()]);

        $warned = 0;
        if (PlatformRules::bool('rewards.notify_expiring') && ! PlatformRules::bool('ops.rewards_disabled')) {
            $soon = RewardGrant::query()->where('status', RewardGrant::AVAILABLE)
                ->whereNull('expiry_warned_at')
                ->whereNotNull('expires_at')
                ->whereBetween('expires_at', [now(), now()->addHours(PlatformRules::int('rewards.expiry_warning_hours'))])
                ->orderBy('expires_at')->get()->groupBy('user_id');

            foreach ($soon as $userId => $grants) {
                $user = User::query()->find($userId);
                if ($user === null) {
                    continue;
                }
                RewardGrant::query()->whereIn('id', $grants->pluck('id'))->update(['expiry_warned_at' => now()]);
                $this->notifier->expiring($user, $grants->values()->all());
                $warned += $grants->count();
            }
        }

        $sessions = RewardedAdSession::query()->where('status', RewardedAdSession::PENDING)
            ->where('expires_at', '<', now())
            ->update(['status' => RewardedAdSession::EXPIRED, 'updated_at' => now()]);

        return ['expired' => $expired, 'coupons_expired' => $couponsExpired, 'warned' => $warned, 'sessions_expired' => $sessions];
    }
}
