<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A member tier — Free, Pro, Hero. `rank` orders value, so "is this an upgrade?" is a
 * number comparison and never a plan-code comparison.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $tagline
 * @property string|null $description
 * @property int $rank
 * @property bool $is_default
 * @property bool $is_active
 * @property int $sort
 */
final class MemberPlan extends Model
{
    public const CODE_FREE = 'free';

    protected $fillable = ['code', 'name', 'tagline', 'description', 'rank', 'is_default', 'is_active', 'sort'];

    protected $casts = [
        'rank' => 'integer',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'sort' => 'integer',
    ];

    protected static function booted(): void
    {
        // Exactly one default plan: whichever was saved as default last wins.
        self::saved(function (MemberPlan $plan): void {
            if ($plan->is_default) {
                self::query()->whereKeyNot($plan->id)->where('is_default', true)->update(['is_default' => false]);
            }
            \App\Services\Membership\MemberEntitlements::flush();
        });
    }

    public function prices(): HasMany
    {
        return $this->hasMany(MemberPlanPrice::class, 'plan_id');
    }

    public function entitlements(): HasMany
    {
        return $this->hasMany(MemberPlanEntitlement::class, 'plan_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(MemberSubscription::class, 'plan_id');
    }

    /**
     * The plan everyone has without a paying subscription. When the catalogue has no default
     * a transient plan with no entitlements stands in — an empty table must never hand out
     * paid features.
     */
    public static function default(): self
    {
        $plan = self::query()->with('entitlements')->where('is_default', true)->first();

        if ($plan !== null) {
            return $plan;
        }

        $fallback = new self(['code' => self::CODE_FREE, 'name' => 'Free', 'rank' => 0, 'is_default' => true]);
        $fallback->setRelation('entitlements', new Collection());

        return $fallback;
    }
}
