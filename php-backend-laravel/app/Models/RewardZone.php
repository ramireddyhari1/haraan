<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\AuditsAdminChanges;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A named piece of targeting geometry: "Kadapa city, 15 km" or "Telangana".
 *
 * Reusable on purpose. A marketer picks a zone rather than retyping coordinates into every
 * program, so the same geography means the same thing across a sponsor's campaigns and can be
 * reported on as one line.
 *
 * A circle anchored to a venue, ground or event reads that record's coordinates at match time
 * rather than copying them — move the venue and the zone moves with it. A `point` circle keeps
 * its own lat/lng.
 *
 * Nothing here decides value: a zone can only ever narrow WHO is eligible. Money-value rewards
 * still start locked and still wait for a verified result. See docs/location-rewards-design.md.
 */
final class RewardZone extends Model
{
    use AuditsAdminChanges;

    public const KIND_CIRCLE = 'circle';

    public const KIND_AREA = 'admin_area';

    public const KINDS = [
        self::KIND_CIRCLE => 'Circle — a point and a radius',
        self::KIND_AREA => 'Area — locality, district or state names',
    ];

    public const ANCHOR_POINT = 'point';

    public const ANCHORS = [
        self::ANCHOR_POINT => 'A map pin',
        'venue' => 'A Pulse venue (follows the venue)',
        'ground' => 'A known ground (follows the ground)',
        'event' => 'An event (follows the event)',
    ];

    public const MODE_INCLUDE = 'include';

    public const MODE_EXCLUDE = 'exclude';

    public const MODES = [self::MODE_INCLUDE => 'Include', self::MODE_EXCLUDE => 'Exclude'];

    protected $fillable = [
        'name', 'kind', 'anchor_type', 'anchor_id', 'latitude', 'longitude', 'radius_m',
        'locality', 'district', 'state', 'place_id', 'is_active', 'notes',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'radius_m' => 'integer',
        'anchor_id' => 'integer',
        'is_active' => 'boolean',
    ];

    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(RewardProgram::class, 'reward_program_zones', 'zone_id', 'program_id')
            ->withPivot('mode');
    }

    public function rules(): BelongsToMany
    {
        return $this->belongsToMany(RewardRule::class, 'reward_rule_zones', 'zone_id', 'rule_id')
            ->withPivot('mode');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isCircle(): bool
    {
        return $this->kind === self::KIND_CIRCLE;
    }

    /**
     * The circle's centre, following the anchor when it has one. Null when this zone is an
     * area, or when the anchor it points at has no coordinates (a venue nobody has pinned yet).
     *
     * @return array{0: float, 1: float}|null
     */
    public function centre(): ?array
    {
        if (! $this->isCircle()) {
            return null;
        }

        [$lat, $lng] = $this->anchor_type === self::ANCHOR_POINT
            ? [$this->latitude, $this->longitude]
            : $this->anchorCoordinates();

        return $lat === null || $lng === null ? null : [(float) $lat, (float) $lng];
    }

    public function radiusKm(): ?float
    {
        $metres = (int) ($this->radius_m ?? 0);

        return $metres > 0 ? $metres / 1000 : null;
    }

    /**
     * Where a reward from this zone is redeemed, for the vault's distance chip and Directions
     * action. Only circles have a point worth navigating to.
     *
     * @return array{name: string, latitude: float, longitude: float}|null
     */
    public function redeemAt(): ?array
    {
        $centre = $this->centre();

        return $centre === null ? null : [
            'name' => (string) $this->name,
            'latitude' => $centre[0],
            'longitude' => $centre[1],
        ];
    }

    /** @return array{0: float|null, 1: float|null} */
    private function anchorCoordinates(): array
    {
        $id = (int) ($this->anchor_id ?? 0);
        if ($id === 0) {
            return [null, null];
        }

        $row = match ($this->anchor_type) {
            'venue' => Venue::query()->whereKey($id)->first(['latitude', 'longitude']),
            'ground' => MatchGround::query()->whereKey($id)->first(['latitude', 'longitude']),
            'event' => Event::query()->whereKey($id)->first(['latitude', 'longitude']),
            default => null,
        };

        return $row === null ? [null, null] : [$row->latitude, $row->longitude];
    }
}
