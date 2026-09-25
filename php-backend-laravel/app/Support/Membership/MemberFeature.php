<?php

declare(strict_types=1);

namespace App\Support\Membership;

/**
 * The member entitlement keys code enforces.
 *
 * Keys and TYPES live here because a gate in a controller is written against them; the
 * VALUES (who gets what, how many) live in member_plan_entitlements and are edited in
 * /control. Adding a key means adding it here, to the catalogue migration, and to the
 * one place that enforces it — never a plan-code comparison at the call site.
 */
final class MemberFeature
{
    /** On/off. */
    public const TYPE_BOOLEAN = 'boolean';

    /** A ceiling counted live from the feature's own table (e.g. tournaments running now). */
    public const TYPE_LIMIT = 'limit';

    /** A ceiling that resets each calendar month, counted in member_usage_counters. */
    public const TYPE_QUOTA = 'quota';

    public const ADS_HIDDEN = 'ads.hidden';

    public const AI_CAREER_READ = 'ai.career_read';

    public const AI_DELIVERY_REVIEW = 'ai.delivery_review';

    public const TOURNAMENTS_ACTIVE_HOSTED = 'tournaments.active_hosted';

    public const MATCHES_CAMERA_ANGLES = 'matches.camera_angles';

    public const PROFILE_MEMBER_BADGE = 'profile.member_badge';

    public const SUPPORT_PRIORITY = 'support.priority';

    /**
     * Advanced match insights, per sport. The limit is how many sports the member unlocks:
     * a number means they choose that many (member_sport_selections), unlimited means every
     * sport, 0/off means none. Enforced by SportInsightsAccess before any insight is built.
     */
    public const INSIGHTS_ADVANCED_SPORTS = 'insights.advanced_sports';

    /** Hours before a ticket tier's sales_start a member may buy it. Enforced by MemberBookingPerks. */
    public const EVENTS_EARLY_ACCESS = 'events.early_access';

    /** Days beyond a venue's booking window a member may book. Enforced by VenueBookingWindow. */
    public const VENUES_PRIORITY_BOOKING = 'venues.priority_booking_days';

    /** Rewards that normally unlock with a rewarded video unlock without one. Enforced by RewardEngine. */
    public const REWARDS_AD_UNLOCK_SKIP = 'rewards.ad_unlock_skip';

    /** Access to reward programs marked members-only. Enforced by RewardEngine. */
    public const REWARDS_MEMBER_PROGRAMS = 'rewards.member_programs';

    /** Most sponsored rewards one player can win from one match. Enforced by RewardEngine. */
    public const REWARDS_OFFERS_PER_MATCH = 'rewards.offers_per_match';

    /** Extra Bonus XP, in percent. Bonus XP only — never competitive XP. Enforced by BonusXp. */
    public const REWARDS_BONUS_XP_BOOST = 'rewards.bonus_xp_boost';

    /** @var array<string, array{type: string, label: string}> */
    private const REGISTRY = [
        self::ADS_HIDDEN => ['type' => self::TYPE_BOOLEAN, 'label' => 'No ads'],
        self::AI_CAREER_READ => ['type' => self::TYPE_BOOLEAN, 'label' => 'AI career read'],
        self::AI_DELIVERY_REVIEW => ['type' => self::TYPE_QUOTA, 'label' => 'AI delivery reviews'],
        self::TOURNAMENTS_ACTIVE_HOSTED => ['type' => self::TYPE_LIMIT, 'label' => 'Tournaments you host'],
        self::MATCHES_CAMERA_ANGLES => ['type' => self::TYPE_LIMIT, 'label' => 'Camera angles per match'],
        self::PROFILE_MEMBER_BADGE => ['type' => self::TYPE_BOOLEAN, 'label' => 'Member badge'],
        self::SUPPORT_PRIORITY => ['type' => self::TYPE_BOOLEAN, 'label' => 'Priority support'],
        self::INSIGHTS_ADVANCED_SPORTS => ['type' => self::TYPE_LIMIT, 'label' => 'Advanced insights'],
        self::EVENTS_EARLY_ACCESS => ['type' => self::TYPE_LIMIT, 'label' => 'Early ticket access'],
        self::VENUES_PRIORITY_BOOKING => ['type' => self::TYPE_LIMIT, 'label' => 'Priority venue booking'],
        self::REWARDS_AD_UNLOCK_SKIP => ['type' => self::TYPE_BOOLEAN, 'label' => 'Unlock rewards without ads'],
        self::REWARDS_MEMBER_PROGRAMS => ['type' => self::TYPE_BOOLEAN, 'label' => 'Member-only rewards'],
        self::REWARDS_OFFERS_PER_MATCH => ['type' => self::TYPE_LIMIT, 'label' => 'Partner offers per match'],
        self::REWARDS_BONUS_XP_BOOST => ['type' => self::TYPE_LIMIT, 'label' => 'Bonus XP boost'],
    ];

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::REGISTRY);
    }

    public static function exists(string $key): bool
    {
        return isset(self::REGISTRY[$key]);
    }

    public static function type(string $key): string
    {
        return self::REGISTRY[$key]['type'] ?? throw new \InvalidArgumentException("Unknown member feature [{$key}].");
    }

    public static function label(string $key): string
    {
        return self::REGISTRY[$key]['label'] ?? $key;
    }

    public static function isBoolean(string $key): bool
    {
        return self::type($key) === self::TYPE_BOOLEAN;
    }
}
