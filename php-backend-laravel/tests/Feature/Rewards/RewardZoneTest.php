<?php

declare(strict_types=1);

namespace Tests\Feature\Rewards;

use App\Models\RewardGrant;
use App\Models\RewardZone;
use App\Support\Rewards\RewardTypes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Location-targeted rewards (docs/location-rewards-design.md).
 *
 * The invariants under test are the ones that make the feature safe to turn on: a zone can only
 * ever narrow who is eligible, it never unlocks anything, and a client-supplied position can
 * sort the vault but can never grant.
 */
final class RewardZoneTest extends TestCase
{
    use RefreshDatabase;
    use RewardFixtures;

    /** Gachibowli, Hyderabad — the point every fixture match is played on. */
    private const MATCH_LAT = 17.4401;

    private const MATCH_LNG = 78.3489;

    /** ~14 km away, across the city. */
    private const FAR_LAT = 17.3616;

    private const FAR_LNG = 78.4747;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rules(['rewards.geo_enabled' => true]);
    }

    private function zone(array $attributes = []): RewardZone
    {
        return RewardZone::query()->create(array_merge([
            'name' => 'Gachibowli 5 km',
            'kind' => RewardZone::KIND_CIRCLE,
            'anchor_type' => RewardZone::ANCHOR_POINT,
            'latitude' => self::MATCH_LAT,
            'longitude' => self::MATCH_LNG,
            'radius_m' => 5000,
            'is_active' => true,
        ], $attributes));
    }

    /** A match at the fixture point, with real coordinates and place names. */
    private function matchHere(array $home, array $away, array $attributes = [])
    {
        return $this->finishedMatch($home, $away, array_merge([
            'latitude' => self::MATCH_LAT,
            'longitude' => self::MATCH_LNG,
            'locality' => 'Gachibowli',
            'district' => 'Hyderabad',
            'state' => 'Telangana',
        ], $attributes));
    }

    public function test_a_match_inside_the_zone_wins_and_one_outside_does_not(): void
    {
        $program = $this->program();
        $program->zones()->attach($this->zone()->id, ['mode' => RewardZone::MODE_INCLUDE]);
        $this->rule($program, RewardTypes::BONUS_XP, ['amount' => 20]);

        [$near, $far] = [$this->player(), $this->player()];

        $this->matchHere([$near], [$this->player()]);
        $this->finishedMatch([$far], [$this->player()], ['latitude' => self::FAR_LAT, 'longitude' => self::FAR_LNG]);

        self::assertSame(1, RewardGrant::query()->where('user_id', $near->id)->where('type', RewardTypes::BONUS_XP)->count());
        self::assertSame(0, RewardGrant::query()->where('user_id', $far->id)->where('type', RewardTypes::BONUS_XP)->count());
    }

    public function test_an_exclude_zone_beats_an_include_zone(): void
    {
        $program = $this->program();
        $program->zones()->attach($this->zone(['name' => 'Telangana', 'kind' => RewardZone::KIND_AREA, 'state' => 'Telangana'])->id, ['mode' => RewardZone::MODE_INCLUDE]);
        $program->zones()->attach($this->zone()->id, ['mode' => RewardZone::MODE_EXCLUDE]);
        $this->rule($program, RewardTypes::BONUS_XP, ['amount' => 20]);

        $player = $this->player();
        $this->matchHere([$player], [$this->player()]);

        self::assertSame(0, RewardGrant::query()->where('user_id', $player->id)->where('type', RewardTypes::BONUS_XP)->count());
    }

    public function test_the_grant_records_why_it_was_given(): void
    {
        $program = $this->program();
        $program->zones()->attach($this->zone()->id, ['mode' => RewardZone::MODE_INCLUDE]);
        $this->rule($program, RewardTypes::BONUS_XP, ['amount' => 20]);

        $player = $this->player();
        $this->matchHere([$player], [$this->player()]);

        $grant = RewardGrant::query()->where('user_id', $player->id)->where('type', RewardTypes::BONUS_XP)->sole();
        self::assertSame('Gachibowli 5 km', $grant->value['geo']['zone']);
        self::assertSame('match_gps', $grant->value['geo']['source']);
        self::assertEqualsWithDelta(0.0, $grant->value['geo']['distance_km'], 0.1);
        self::assertNotNull($grant->zone_id);
    }

    public function test_a_targeted_money_reward_still_starts_locked_and_needs_the_stricter_trust(): void
    {
        $this->rules(['rewards.min_trust_to_unlock' => 'medium', 'rewards.geo_min_trust_for_zone_money' => 'high']);

        $program = $this->program();
        $program->zones()->attach($this->zone()->id, ['mode' => RewardZone::MODE_INCLUDE]);
        $this->rule($program, RewardTypes::HARAAN_COUPON, ['discount' => 100, 'scope' => 'all']);

        $player = $this->player();
        $match = $this->matchHere([$player], [$this->player()]);

        $grant = RewardGrant::query()->where('user_id', $player->id)->where('type', RewardTypes::HARAAN_COUPON)->sole();
        self::assertSame(RewardGrant::LOCKED, $grant->status, 'a zone never unlocks anything');
        self::assertSame('high', $grant->requiredTrustLevel());

        // Medium would have been enough for an untargeted coupon. It is not enough for this one.
        $this->settle($match, 'medium');
        self::assertSame(RewardGrant::LOCKED, $grant->fresh()->status);
        self::assertSame('trust_too_low', $grant->fresh()->status_reason);

        $this->settle($match->fresh(), 'high');
        self::assertSame(RewardGrant::AVAILABLE, $grant->fresh()->status);
    }

    public function test_an_untargeted_program_is_unaffected_by_the_stricter_bar(): void
    {
        $this->rules(['rewards.min_trust_to_unlock' => 'medium', 'rewards.geo_min_trust_for_zone_money' => 'verified']);

        $this->rule($this->program(), RewardTypes::HARAAN_COUPON, ['discount' => 100, 'scope' => 'all']);

        $player = $this->player();
        $match = $this->matchHere([$player], [$this->player()]);

        $grant = RewardGrant::query()->where('user_id', $player->id)->where('type', RewardTypes::HARAAN_COUPON)->sole();
        self::assertNull($grant->requiredTrustLevel());

        $this->settle($match, 'medium');
        self::assertSame(RewardGrant::AVAILABLE, $grant->fresh()->status);
    }

    public function test_with_the_switch_off_zones_are_ignored_entirely(): void
    {
        $this->rules(['rewards.geo_enabled' => false]);

        $program = $this->program();
        $program->zones()->attach($this->zone()->id, ['mode' => RewardZone::MODE_INCLUDE]);
        $this->rule($program, RewardTypes::BONUS_XP, ['amount' => 20]);

        $player = $this->player();
        // Far outside the zone, and it wins anyway — which is exactly today's behaviour.
        $this->finishedMatch([$player], [$this->player()], ['latitude' => self::FAR_LAT, 'longitude' => self::FAR_LNG]);

        $grant = RewardGrant::query()->where('user_id', $player->id)->where('type', RewardTypes::BONUS_XP)->sole();
        self::assertNull($grant->value['geo'] ?? null);
        self::assertNull($grant->zone_id);
    }

    public function test_a_match_without_coordinates_cannot_satisfy_an_area_zone_by_default(): void
    {
        $program = $this->program();
        $program->zones()->attach($this->zone(['name' => 'Telangana', 'kind' => RewardZone::KIND_AREA, 'state' => 'Telangana'])->id, ['mode' => RewardZone::MODE_INCLUDE]);
        $this->rule($program, RewardTypes::BONUS_XP, ['amount' => 20]);

        $player = $this->player();
        $this->finishedMatch([$player], [$this->player()], ['state' => 'Telangana']);

        self::assertSame(0, RewardGrant::query()->where('user_id', $player->id)->where('type', RewardTypes::BONUS_XP)->count());

        // Loosened, the same match qualifies on its name alone.
        $this->rules(['rewards.geo_require_coordinates' => false]);
        $other = $this->player();
        $this->finishedMatch([$other], [$this->player()], ['state' => 'Telangana']);

        $grant = RewardGrant::query()->where('user_id', $other->id)->where('type', RewardTypes::BONUS_XP)->sole();
        self::assertSame('name', $grant->value['geo']['source']);
        self::assertArrayNotHasKey('distance_km', $grant->value['geo'], 'an area has no distance to report');
    }

    public function test_an_empty_area_zone_matches_nothing(): void
    {
        $program = $this->program();
        $program->zones()->attach($this->zone(['name' => 'Nowhere', 'kind' => RewardZone::KIND_AREA, 'latitude' => null, 'longitude' => null, 'radius_m' => null])->id, ['mode' => RewardZone::MODE_INCLUDE]);
        $this->rule($program, RewardTypes::BONUS_XP, ['amount' => 20]);

        $player = $this->player();
        $this->matchHere([$player], [$this->player()]);

        self::assertSame(0, RewardGrant::query()->where('user_id', $player->id)->where('type', RewardTypes::BONUS_XP)->count());
    }

    public function test_the_tighter_zone_is_tried_first(): void
    {
        // Both reach this match; only one reward is allowed per match.
        $this->rules(['rewards.max_grants_per_match' => 1]);

        $wide = $this->program(['name' => 'National', 'priority' => 100]);
        $wide->zones()->attach($this->zone(['name' => 'Telangana 200 km', 'radius_m' => 200000])->id, ['mode' => RewardZone::MODE_INCLUDE]);
        $this->rule($wide, RewardTypes::BONUS_XP, ['amount' => 5]);

        $tight = $this->program(['name' => 'Gachibowli turf', 'priority' => 100]);
        $tight->zones()->attach($this->zone()->id, ['mode' => RewardZone::MODE_INCLUDE]);
        $this->rule($tight, RewardTypes::BONUS_XP, ['amount' => 50]);

        $player = $this->player();
        $this->matchHere([$player], [$this->player()]);

        $grant = RewardGrant::query()->where('user_id', $player->id)->where('type', RewardTypes::BONUS_XP)->sole();
        self::assertSame($tight->id, (int) $grant->program_id, 'the 5 km offer beats the 200 km one');
    }

    public function test_one_ground_cannot_drain_a_sponsors_budget_in_a_day(): void
    {
        $this->rules(['rewards.geo_ground_grants_per_day' => 2]);

        $program = $this->program();
        $program->zones()->attach($this->zone()->id, ['mode' => RewardZone::MODE_INCLUDE]);
        $this->rule($program, RewardTypes::BONUS_XP, ['amount' => 20]);

        // Three separate matches on the same patch of ground.
        foreach ([1, 2, 3] as $_) {
            $this->matchHere([$this->player()], [$this->player()]);
        }

        self::assertSame(2, RewardGrant::query()->where('type', RewardTypes::BONUS_XP)->count());
    }

    public function test_a_spoofed_position_reorders_the_vault_but_grants_nothing(): void
    {
        $program = $this->program();
        $program->zones()->attach($this->zone()->id, ['mode' => RewardZone::MODE_INCLUDE]);
        $this->rule($program, RewardTypes::BONUS_XP, ['amount' => 20]);

        $player = $this->player();
        // Played far outside the zone, but claims to be standing in the middle of it.
        $this->finishedMatch([$player], [$this->player()], ['latitude' => self::FAR_LAT, 'longitude' => self::FAR_LNG]);

        $before = RewardGrant::query()->where('user_id', $player->id)->count();

        $this->asMember($player)
            ->getJson('/api/rewards?near='.self::MATCH_LAT.','.self::MATCH_LNG)
            ->assertOk()
            ->assertJsonPath('meta.sorted_by', 'distance');
        self::assertSame($before, RewardGrant::query()->where('user_id', $player->id)->count(), 'asking nicely grants nothing');
        self::assertSame(0, RewardGrant::query()->where('user_id', $player->id)->whereNotNull('zone_id')->count());
    }
}
