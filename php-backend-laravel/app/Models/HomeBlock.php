<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BroadcastsContentChanges;
use App\Models\Concerns\TargetsOrganizations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One ordered, typed block of the app home screen. Visibility layers (see
 * isVisibleFor): is_active + schedule window → org/district targeting →
 * feature-flag gate. The app renders blocks in sort_order by `type`.
 */
final class HomeBlock extends Model
{
    use BroadcastsContentChanges;
    use TargetsOrganizations;

    /** Clients refetch /api/home/layout when blocks change. */
    protected string $contentDomain = 'home';

    /** Block types the app knows how to render. Adding one is data-only here. */
    public const TYPES = [
        'hero' => 'Hero / ActionBoard banner',
        'sports_chips' => 'Sports chips row',
        'ad_strip' => 'Ad / promo strip',
        'feed_section' => 'Feed section (For You / Trending)',
        'venues' => 'Popular venues carousel',
        'leaderboard' => 'Leaderboard teaser',
        'actionboard' => 'ActionBoard summary',
    ];

    protected $fillable = [
        'type', 'title', 'config', 'sort_order', 'is_active',
        'organization_ids', 'feature_flag_key', 'starts_at', 'ends_at',
    ];

    protected $casts = [
        'config' => 'array',
        'organization_ids' => 'array',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    /** Active + currently within any configured schedule window. */
    public function scopeLive(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    /** Whether this (already schedule-live) block should show for a given viewer. */
    public function isVisibleFor(?User $user, ?string $appVersion = null): bool
    {
        if (! $this->matchesOrganization($user)) {
            return false;
        }

        if ($this->feature_flag_key !== null) {
            $flag = FeatureFlag::where('key', $this->feature_flag_key)->first();
            if ($flag === null || ! $flag->isEnabledFor($user, $appVersion)) {
                return false;
            }
        }

        return true;
    }

    /** Shape sent to the app. */
    public function toAppArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'config' => (object) ($this->config ?? []),
        ];
    }
}
