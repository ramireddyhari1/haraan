<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\AuditsAdminChanges;
use App\Support\Rewards\BadgeMetrics;
use Illuminate\Database\Eloquent\Model;

/**
 * A badge a player can unlock, edited in /control → Rewards → Badges. `metric` must be one
 * of {@see BadgeMetrics}; unlocked when the player's value for it reaches
 * `threshold` (for district_rank: when the rank is at or better than it).
 */
final class BadgeDefinition extends Model
{
    use AuditsAdminChanges;

    public const TIERS = ['bronze' => 'Bronze', 'silver' => 'Silver', 'gold' => 'Gold'];

    protected $fillable = [
        'key', 'name', 'description', 'icon', 'tier', 'metric', 'threshold', 'show_progress', 'bonus_xp', 'is_active', 'sort',
    ];

    protected $casts = [
        'threshold' => 'integer',
        'show_progress' => 'boolean',
        'bonus_xp' => 'integer',
        'is_active' => 'boolean',
        'sort' => 'integer',
    ];
}
