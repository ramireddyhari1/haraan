<?php

declare(strict_types=1);

namespace App\Support\Rewards;

use App\Support\ActionboardXp;
use App\Support\Membership\MemberFeature;

/**
 * The fixed list of conditions a reward rule may use. Admins pick from these in /control; a
 * rule's `conditions` JSON is validated here on save and interpreted here at run time — it is
 * never evaluated as an expression, so a rule can't do anything the list doesn't allow.
 */
final class RewardConditions
{
    public const RESULTS = [
        'any' => 'Any result',
        'won' => 'Won',
        'lost' => 'Lost',
        'tied' => 'Tied / no result',
        'not_lost' => 'Won or tied',
    ];

    public const TRUST_LEVELS = [
        'low' => 'Any (low or better)',
        'medium' => 'Medium — captains confirmed',
        'high' => 'High — organiser verified',
        'verified' => 'Verified — Haraan venue',
    ];

    /**
     * @param  array<string, mixed>|null  $conditions
     * @return array<string, mixed>
     *
     * @throws \InvalidArgumentException
     */
    public static function normalize(?array $conditions): array
    {
        $c = $conditions ?? [];
        $out = [];

        $result = (string) ($c['result'] ?? 'any');
        if (! array_key_exists($result, self::RESULTS)) {
            throw new \InvalidArgumentException('Unknown result condition.');
        }
        if ($result !== 'any') {
            $out['result'] = $result;
        }

        if (! empty($c['player_of_match'])) {
            $out['player_of_match'] = true;
        }

        foreach (['min_registered_per_side' => 11, 'min_play_streak_weeks' => 520] as $key => $max) {
            $v = $c[$key] ?? null;
            if ($v === null || $v === '' || (int) $v === 0) {
                continue;
            }
            if (! is_numeric($v) || (int) $v < 1 || (int) $v > $max) {
                throw new \InvalidArgumentException("“{$key}” must be between 1 and {$max}.");
            }
            $out[$key] = (int) $v;
        }

        $trust = (string) ($c['min_trust'] ?? 'low');
        if (! array_key_exists($trust, self::TRUST_LEVELS)) {
            throw new \InvalidArgumentException('Unknown trust level.');
        }
        if ($trust !== 'low') {
            $out['min_trust'] = $trust;
        }

        $feature = trim((string) ($c['member_feature'] ?? ''));
        if ($feature !== '') {
            if (! MemberFeature::exists($feature) || ! MemberFeature::isBoolean($feature)) {
                throw new \InvalidArgumentException('Member feature must be an on/off feature.');
            }
            $out['member_feature'] = $feature;
        }

        return $out;
    }

    /**
     * Does the context satisfy every condition? Member-feature checks go through
     * MemberEntitlements (via the context), never a plan code.
     *
     * @param  array<string, mixed>  $conditions
     */
    public static function passes(array $conditions, RewardContext $ctx): bool
    {
        $result = $conditions['result'] ?? 'any';
        $ok = match ($result) {
            'won' => $ctx->outcome === 'won',
            'lost' => $ctx->outcome === 'lost',
            'tied' => $ctx->outcome === 'tied',
            'not_lost' => in_array($ctx->outcome, ['won', 'tied'], true),
            default => true,
        };
        if (! $ok) {
            return false;
        }

        if (! empty($conditions['player_of_match']) && ! $ctx->isPlayerOfMatch) {
            return false;
        }

        if (isset($conditions['min_registered_per_side']) && $ctx->minRegisteredPerSide < (int) $conditions['min_registered_per_side']) {
            return false;
        }

        if (isset($conditions['min_play_streak_weeks']) && $ctx->playStreakWeeks < (int) $conditions['min_play_streak_weeks']) {
            return false;
        }

        if (isset($conditions['min_trust']) && ActionboardXp::trustRank($ctx->trustLevel) < ActionboardXp::trustRank((string) $conditions['min_trust'])) {
            return false;
        }

        if (isset($conditions['member_feature']) && ! $ctx->entitlements->allows((string) $conditions['member_feature'])) {
            return false;
        }

        return true;
    }
}
