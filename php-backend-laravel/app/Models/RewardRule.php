<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\AuditsAdminChanges;
use App\Support\Rewards\RewardConditions;
use App\Support\Rewards\RewardTypes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * One way to win something inside a program: when (trigger), for whom (typed conditions),
 * what (reward type + payload), how it unlocks (automatically or by an opted-in rewarded ad)
 * and how often (caps). Conditions and payload are validated against fixed registries —
 * {@see RewardConditions}, {@see RewardTypes} — never evaluated as code.
 */
final class RewardRule extends Model
{
    use AuditsAdminChanges;

    public const TRIGGER_COMPLETED = 'match_completed';

    public const TRIGGER_SETTLED = 'match_settled';

    public const TRIGGERS = [
        self::TRIGGER_COMPLETED => 'When the match finishes',
        self::TRIGGER_SETTLED => 'When the result is confirmed',
    ];

    public const UNLOCK_AUTO = 'auto';

    public const UNLOCK_AD = 'rewarded_ad';

    public const UNLOCK_METHODS = [
        self::UNLOCK_AUTO => 'Automatically',
        self::UNLOCK_AD => 'Player chooses to watch a short video',
    ];

    protected $fillable = [
        'program_id', 'name', 'trigger', 'conditions', 'reward_type', 'payload', 'unlock_method',
        'per_user_daily_cap', 'per_user_total_cap', 'expires_after_days', 'is_active', 'sort',
    ];

    protected $casts = [
        'conditions' => 'array',
        'payload' => 'array',
        'is_active' => 'boolean',
        'per_user_daily_cap' => 'integer',
        'per_user_total_cap' => 'integer',
        'expires_after_days' => 'integer',
        'sort' => 'integer',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(RewardProgram::class, 'program_id');
    }

    /**
     * A rule may narrow its program's geography — how one campaign offers a bigger discount
     * closer to the sponsor's store. Empty (the normal case) inherits the program's zones.
     */
    public function zones(): BelongsToMany
    {
        return $this->belongsToMany(RewardZone::class, 'reward_rule_zones', 'rule_id', 'zone_id')
            ->withPivot('mode');
    }

    public function includeZones(): BelongsToMany
    {
        return $this->zones()->wherePivot('mode', RewardZone::MODE_INCLUDE);
    }

    public function excludeZones(): BelongsToMany
    {
        return $this->zones()->wherePivot('mode', RewardZone::MODE_EXCLUDE);
    }

    public function isMoneyValue(): bool
    {
        return RewardTypes::isMoneyValue((string) $this->reward_type);
    }
}
