<?php

declare(strict_types=1);

namespace App\Support\Rewards;

use App\Models\MemberPlan;
use App\Models\RewardCodePool;
use App\Models\Venue;

/**
 * What a reward rule can give, and the shape of each type's payload.
 *
 * Money-value types (coupons, sponsor codes, membership trials) ALWAYS start locked and unlock
 * only once the match result is verified — that is not configurable, so a fake self-scored match
 * can never turn into something worth money.
 */
final class RewardTypes
{
    public const BONUS_XP = 'bonus_xp';

    public const HARAAN_COUPON = 'haraan_coupon';

    public const SPONSOR_CODE = 'sponsor_code';

    public const MEMBERSHIP_TRIAL = 'membership_trial';

    public const OFFER_LINK = 'offer_link';

    /** Ledger rows that record something earned, not granted by a rule. */
    public const BADGE = 'badge';

    public const STREAK = 'streak';

    public const RULE_TYPES = [
        self::BONUS_XP => 'Bonus XP',
        self::HARAAN_COUPON => 'Haraan coupon (one owner, single use)',
        self::SPONSOR_CODE => 'Sponsor code from a code pool',
        self::MEMBERSHIP_TRIAL => 'Membership trial',
        self::OFFER_LINK => 'Partner offer link',
    ];

    private const MONEY_VALUE = [self::HARAAN_COUPON, self::SPONSOR_CODE, self::MEMBERSHIP_TRIAL];

    public static function isRuleType(string $type): bool
    {
        return array_key_exists($type, self::RULE_TYPES);
    }

    public static function isMoneyValue(string $type): bool
    {
        return in_array($type, self::MONEY_VALUE, true);
    }

    /**
     * Validate and normalise a payload for a type. Returns only the known keys.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws \InvalidArgumentException
     */
    public static function normalizePayload(string $type, array $payload): array
    {
        $int = static function (string $key, int $min, int $max, ?int $default = null) use ($payload): ?int {
            $v = $payload[$key] ?? $default;
            if ($v === null || $v === '') {
                return null;
            }
            if (! is_numeric($v) || (int) $v != $v || (int) $v < $min || (int) $v > $max) {
                throw new \InvalidArgumentException("“{$key}” must be a whole number from {$min} to {$max}.");
            }

            return (int) $v;
        };

        switch ($type) {
            case self::BONUS_XP:
                return ['amount' => $int('amount', 1, 10000) ?? throw new \InvalidArgumentException('Bonus XP needs an amount.')];

            case self::HARAAN_COUPON:
                $kind = (string) ($payload['discount_type'] ?? 'fixed');
                if (! in_array($kind, ['fixed', 'percent'], true)) {
                    throw new \InvalidArgumentException('Discount type must be fixed or percent.');
                }
                $discount = $int('discount', 1, $kind === 'percent' ? 100 : 100000)
                    ?? throw new \InvalidArgumentException('A coupon needs a discount.');
                $scope = (string) ($payload['scope'] ?? 'all');
                if (! in_array($scope, ['event', 'venue', 'all'], true)) {
                    throw new \InvalidArgumentException('Coupon scope must be event, venue or all.');
                }

                // A venue-scoped reward may name the one venue it is good at — how a sponsored
                // "₹100 off at this turf" is expressed. The column and its checkout enforcement
                // (Coupon::appliesToVenue) already exist; nothing new is invented here. Left
                // empty, a venue-scoped coupon stays good at any venue, as it is today.
                $venueId = $scope === 'venue' ? $int('venue_id', 1, PHP_INT_MAX) : null;
                if ($venueId !== null && ! Venue::query()->whereKey($venueId)->exists()) {
                    throw new \InvalidArgumentException('That venue does not exist.');
                }

                return array_merge([
                    'discount_type' => $kind,
                    'discount' => $discount,
                    'max_discount' => $int('max_discount', 1, 100000),
                    'min_order' => $int('min_order', 0, 1000000),
                    'scope' => $scope,
                ], $venueId === null ? [] : ['venue_id' => $venueId], [
                    'valid_days' => $int('valid_days', 1, 365, 30),
                ]);

            case self::SPONSOR_CODE:
                $pool = $int('pool_id', 1, PHP_INT_MAX) ?? throw new \InvalidArgumentException('Choose a code pool.');
                if (! RewardCodePool::query()->whereKey($pool)->exists()) {
                    throw new \InvalidArgumentException('That code pool does not exist.');
                }

                return ['pool_id' => $pool];

            case self::MEMBERSHIP_TRIAL:
                $plan = $int('plan_id', 1, PHP_INT_MAX) ?? throw new \InvalidArgumentException('Choose a plan.');
                if (! MemberPlan::query()->whereKey($plan)->exists()) {
                    throw new \InvalidArgumentException('That plan does not exist.');
                }

                return ['plan_id' => $plan, 'days' => $int('days', 1, 90, 7)];

            case self::OFFER_LINK:
                $url = trim((string) ($payload['url'] ?? ''));
                if (! preg_match('#^https://#i', $url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
                    throw new \InvalidArgumentException('An offer link must be a valid https:// URL.');
                }

                return ['url' => $url, 'cta_text' => mb_substr(trim((string) ($payload['cta_text'] ?? 'Open offer')), 0, 30)];
        }

        throw new \InvalidArgumentException("Unknown reward type [{$type}].");
    }
}
