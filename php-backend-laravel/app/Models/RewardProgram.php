<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\AuditsAdminChanges;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * A reward campaign: Haraan's own (Bonus XP, coupons, trials) or a sponsor's. It carries the
 * targeting, schedule, budget and the creative the player sees; its rules say what is given
 * and when. Only `live` programs inside their window are evaluated.
 */
final class RewardProgram extends Model
{
    use AuditsAdminChanges;

    public const KIND_HARAAN = 'haraan';

    public const KIND_SPONSORED = 'sponsored';

    public const KINDS = [self::KIND_HARAAN => 'Haraan reward', self::KIND_SPONSORED => 'Sponsored'];

    public const STATUSES = ['draft' => 'Draft', 'live' => 'Live', 'paused' => 'Paused', 'ended' => 'Ended'];

    protected $fillable = [
        'kind', 'sponsor_id', 'name', 'status', 'starts_at', 'ends_at', 'priority', 'sports', 'match_types',
        'members_only', 'budget_total', 'budget_daily', 'headline', 'description', 'disclosure', 'terms_url',
        'card_image', 'brand_color',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'sports' => 'array',
        'match_types' => 'array',
        'members_only' => 'boolean',
        'budget_total' => 'integer',
        'budget_daily' => 'integer',
        'grants_count' => 'integer',
        'priority' => 'integer',
    ];

    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(RewardSponsor::class, 'sponsor_id');
    }

    public function rules(): HasMany
    {
        return $this->hasMany(RewardRule::class, 'program_id')->orderBy('sort')->orderBy('id');
    }

    public function grants(): HasMany
    {
        return $this->hasMany(RewardGrant::class, 'program_id');
    }

    /**
     * Where this program may fire. Empty = everywhere, which is what every program did before
     * location targeting existed — so an untargeted program is not a special case anywhere.
     */
    public function zones(): BelongsToMany
    {
        return $this->belongsToMany(RewardZone::class, 'reward_program_zones', 'program_id', 'zone_id')
            ->withPivot('mode');
    }

    /**
     * The two halves of `zones()`, as their own relations so /control can edit each list
     * independently. Both are constrained by the pivot's mode, so syncing one never disturbs
     * the other.
     */
    public function includeZones(): BelongsToMany
    {
        return $this->zones()->wherePivot('mode', RewardZone::MODE_INCLUDE);
    }

    public function excludeZones(): BelongsToMany
    {
        return $this->zones()->wherePivot('mode', RewardZone::MODE_EXCLUDE);
    }

    public function scopeRunning(Builder $query): Builder
    {
        return $query->where('status', 'live')
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    public function isSponsored(): bool
    {
        return $this->kind === self::KIND_SPONSORED;
    }

    public function targetsSport(?string $sport): bool
    {
        $sports = array_filter((array) $this->sports);

        return $sports === [] || in_array(strtolower((string) ($sport ?: 'cricket')), array_map('strtolower', $sports), true);
    }

    public function targetsMatchType(?string $type): bool
    {
        $types = array_filter((array) $this->match_types);

        return $types === [] || in_array(strtolower((string) ($type ?: 'casual')), array_map('strtolower', $types), true);
    }

    /**
     * Running programs with their active rules. Read once per engine run (not per player).
     *
     * @return Collection<int, self>
     */
    public static function liveWithRules(): Collection
    {
        return self::query()->running()
            ->with(['sponsor', 'zones', 'rules' => fn ($q) => $q->where('is_active', true), 'rules.zones'])
            ->orderBy('priority')->orderBy('id')->get();
    }
}
