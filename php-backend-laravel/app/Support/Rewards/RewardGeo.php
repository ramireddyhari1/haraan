<?php

declare(strict_types=1);

namespace App\Support\Rewards;

use App\Models\LiveMatch;
use App\Models\MatchGround;
use App\Models\RewardProgram;
use App\Models\RewardRule;
use App\Models\RewardZone;
use App\Support\PlatformRules;
use Illuminate\Support\Collection;

/**
 * Resolves where a finished match happened, and which reward zones contain it.
 *
 * The whole design rests on one rule: **the match's GPS fix is what gets targeted, never the
 * player's phone.** A player's reported position is spoofable in seconds, and a spoofable input
 * that decides who receives something worth money is a fraud vector, not a feature. A public
 * match cannot be created without a real fix (Match/StoreMatchRequest), and that fix is already
 * governed by the trust and verification pipeline — so targeting it adds no new fraud surface.
 *
 * Built once per match, then asked about each program and rule. Distance uses the same
 * haversine the ActionBoard feed uses; there is deliberately not a second copy of that maths
 * in the codebase.
 *
 * @see docs/location-rewards-design.md
 */
final class RewardGeo
{
    private const EARTH_KM = 6371.0;

    private readonly ?float $latitude;

    private readonly ?float $longitude;

    private readonly string $pointSource;

    private function __construct(
        private readonly LiveMatch $match,
        ?float $latitude,
        ?float $longitude,
        string $pointSource,
    ) {
        $this->latitude = $latitude;
        $this->longitude = $longitude;
        $this->pointSource = $pointSource;
    }

    /**
     * A match's position: its own GPS fix first, then the ground it resolved to, then nothing.
     * A match with no point can still satisfy an area zone by name, unless
     * `rewards.geo_require_coordinates` forbids it (the default).
     */
    public static function forMatch(LiveMatch $match): self
    {
        if ($match->latitude !== null && $match->longitude !== null) {
            return new self($match, (float) $match->latitude, (float) $match->longitude, ZoneMatch::SOURCE_MATCH_GPS);
        }

        if ($match->ground_id !== null) {
            $ground = MatchGround::query()->whereKey($match->ground_id)->first(['latitude', 'longitude']);
            if ($ground?->latitude !== null && $ground->longitude !== null) {
                return new self($match, (float) $ground->latitude, (float) $ground->longitude, ZoneMatch::SOURCE_GROUND);
            }
        }

        return new self($match, null, null, ZoneMatch::SOURCE_NAME);
    }

    public static function enabled(): bool
    {
        return PlatformRules::bool('rewards.geo_enabled');
    }

    public function hasPosition(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /**
     * The programs that can fire for this match, in the order the engine should try them.
     *
     * Ordering is the only thing geography changes about selection: programs keep their
     * admin-set priority, and a zone-targeted program only jumps ahead of an equal-priority
     * untargeted one because a 3 km turf offer should beat a national campaign on the same
     * match. Eligibility, caps and budgets are untouched.
     *
     * @param  Collection<int, RewardProgram>  $programs
     * @return list<array{0: RewardProgram, 1: ZoneMatch}>
     */
    public function programTargets(Collection $programs): array
    {
        $targets = [];

        foreach ($programs as $program) {
            $hit = $this->resolve($program->zones);
            if ($hit !== null) {
                $targets[] = [$program, $hit];
            }
        }

        if (! PlatformRules::bool('rewards.geo_tighter_zone_wins')) {
            return $targets;
        }

        usort($targets, static function (array $a, array $b): int {
            return [(int) $a[0]->priority, $a[1]->tightnessKm(), (int) $a[0]->id]
                <=> [(int) $b[0]->priority, $b[1]->tightnessKm(), (int) $b[0]->id];
        });

        return $targets;
    }

    /**
     * A rule's own geography, when it narrows the program's (a tier inside one campaign).
     * With no zones of its own a rule inherits the program's match, which is what the vast
     * majority of rules will do.
     */
    public function ruleTarget(RewardRule $rule, ZoneMatch $programMatch): ?ZoneMatch
    {
        if ($rule->zones->isEmpty()) {
            return $programMatch;
        }

        return $this->resolve($rule->zones);
    }

    /**
     * Does this match sit inside the given set of zones? Returns the tightest include-zone it
     * matched, "anywhere" when there are no include-zones to satisfy, or null when it is
     * excluded or simply outside.
     *
     * @param  Collection<int, RewardZone>  $zones  carrying a `mode` pivot
     */
    public function resolve(Collection $zones): ?ZoneMatch
    {
        $active = $zones->filter(static fn (RewardZone $z): bool => (bool) $z->is_active);

        $mode = static fn (RewardZone $z): string => (string) ($z->pivot->mode ?? RewardZone::MODE_INCLUDE);

        foreach ($active->filter(fn (RewardZone $z): bool => $mode($z) === RewardZone::MODE_EXCLUDE) as $zone) {
            if ($this->contains($zone) !== null) {
                return null;
            }
        }

        $includes = $active->filter(fn (RewardZone $z): bool => $mode($z) === RewardZone::MODE_INCLUDE);
        if ($includes->isEmpty()) {
            return ZoneMatch::anywhere();
        }

        $best = null;
        foreach ($includes as $zone) {
            $distance = $this->contains($zone);
            if ($distance === null) {
                continue;
            }
            $hit = new ZoneMatch($zone, $distance === -1.0 ? null : $distance, $this->sourceFor($zone));
            if ($best === null || $hit->tightnessKm() < $best->tightnessKm()) {
                $best = $hit;
            }
        }

        return $best;
    }

    /**
     * Distance in km when the match falls inside this zone, -1.0 when it matched by name (no
     * distance to report), or null when it is outside.
     */
    public function contains(RewardZone $zone): ?float
    {
        return $zone->isCircle() ? $this->inCircle($zone) : $this->inArea($zone);
    }

    /**
     * The velocity key for this match's ground — a real ground id when one is known, otherwise
     * coordinates rounded to ~110 m so repeated games on the same patch still collapse into one
     * bucket. Null when we know neither, in which case there is nothing to rate-limit on.
     */
    public function groundKey(): ?string
    {
        if ($this->match->ground_id !== null) {
            return 'ground:'.(int) $this->match->ground_id;
        }

        if (! $this->hasPosition()) {
            return null;
        }

        return sprintf('pt:%.3f,%.3f', $this->latitude, $this->longitude);
    }

    /** Great-circle distance in km between the match and a point. */
    public function distanceTo(float $latitude, float $longitude): ?float
    {
        if (! $this->hasPosition()) {
            return null;
        }

        return self::haversine($this->latitude, $this->longitude, $latitude, $longitude);
    }

    public static function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $a1 = deg2rad($lat1);
        $a2 = deg2rad($lat2);
        $dLat = $a2 - $a1;
        $dLng = deg2rad($lng2 - $lng1);

        $h = sin($dLat / 2) ** 2 + cos($a1) * cos($a2) * sin($dLng / 2) ** 2;

        return self::EARTH_KM * 2 * atan2(sqrt($h), sqrt(1 - $h));
    }

    private function inCircle(RewardZone $zone): ?float
    {
        $centre = $zone->centre();
        $radius = $zone->radiusKm();
        if ($centre === null || $radius === null || ! $this->hasPosition()) {
            return null;
        }

        $km = self::haversine($this->latitude, $this->longitude, $centre[0], $centre[1]);

        return $km <= $radius ? $km : null;
    }

    /**
     * An area zone matches on place names. When the match has real coordinates we still report
     * no distance for it — an area has no centre worth measuring to, and inventing one would put
     * a fake "12 km away" on the player's card.
     */
    private function inArea(RewardZone $zone): ?float
    {
        if (! $this->hasPosition() && PlatformRules::bool('rewards.geo_require_coordinates')) {
            return null;
        }

        foreach (['locality', 'district', 'state'] as $field) {
            $want = (string) ($zone->{$field} ?? '');
            if ($want === '') {
                continue;
            }
            if (! self::sameName($want, (string) ($this->match->{$field} ?? ''))) {
                return null;
            }
        }

        // A zone with no names set matches nothing — an empty area is a configuration mistake,
        // not a licence to target the whole country.
        $named = array_filter([$zone->locality, $zone->district, $zone->state], static fn ($v): bool => (string) $v !== '');

        return $named === [] ? null : -1.0;
    }

    private function sourceFor(RewardZone $zone): string
    {
        return $zone->isCircle() ? $this->pointSource : ZoneMatch::SOURCE_NAME;
    }

    /** Casing and stray spacing shouldn't decide whether a player gets a reward. */
    private static function sameName(string $a, string $b): bool
    {
        $norm = static fn (string $s): string => preg_replace('/\s+/u', ' ', trim(mb_strtolower($s))) ?? '';

        $a = $norm($a);

        return $a !== '' && $a === $norm($b);
    }
}
