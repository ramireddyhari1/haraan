<?php

declare(strict_types=1);

namespace App\Support\Rewards;

use App\Models\RewardZone;

/**
 * The answer to "does this match sit inside this program's geography, and how tightly?".
 *
 * A null `zone` means the program targets everywhere — the way every program behaves today.
 * That is deliberately a match, not an absence of one, so untargeted programs keep working
 * unchanged while still flowing through the same code path.
 */
final class ZoneMatch
{
    public const SOURCE_ANYWHERE = 'anywhere';

    public const SOURCE_MATCH_GPS = 'match_gps';

    public const SOURCE_GROUND = 'ground';

    public const SOURCE_NAME = 'name';

    public function __construct(
        public readonly ?RewardZone $zone,
        public readonly ?float $distanceKm,
        public readonly string $source,
    ) {}

    public static function anywhere(): self
    {
        return new self(null, null, self::SOURCE_ANYWHERE);
    }

    public function isTargeted(): bool
    {
        return $this->zone !== null;
    }

    /**
     * How tight this match is, for "the tighter zone wins". A circle sorts by its radius; an
     * area and "anywhere" sort last, because neither can claim to be more local than a circle
     * someone deliberately drew.
     */
    public function tightnessKm(): float
    {
        return $this->zone?->radiusKm() ?? PHP_FLOAT_MAX;
    }

    /** @return array<string, mixed>|null the snapshot stored on the grant */
    public function snapshot(?string $minTrust): ?array
    {
        if ($this->zone === null) {
            return null;
        }

        return array_filter([
            'zone_id' => (int) $this->zone->id,
            'zone' => (string) $this->zone->name,
            'distance_km' => $this->distanceKm === null ? null : round($this->distanceKm, 2),
            'source' => $this->source,
            'min_trust' => $minTrust,
            'redeem_at' => $this->zone->redeemAt(),
        ], static fn ($v): bool => $v !== null);
    }
}
