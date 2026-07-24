<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A tier in the catalogue. `features` is the capability list entitlement checks
 * read; `included_conversations` is the monthly quota.
 */
final class PartnerPlan extends Model
{
    /** Outbound journeys: reminders and the review request. */
    public const FEATURE_JOURNEYS = 'automations.journeys';

    /** Inbound keyword auto-replies (compliance replies are never gated). */
    public const FEATURE_INBOUND = 'automations.inbound';

    /** Instagram DMs — phase 3, defined here so plans can be priced for it. */
    public const FEATURE_INSTAGRAM = 'automations.instagram';

    protected $fillable = [
        'code', 'name', 'description', 'price_inr', 'included_conversations',
        'features', 'is_default', 'is_active', 'sort',
    ];

    protected $casts = [
        'features' => 'array',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'price_inr' => 'integer',
        'included_conversations' => 'integer',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(PartnerSubscription::class, 'plan_id');
    }

    public function includes(string $feature): bool
    {
        return in_array($feature, $this->features ?? [], true);
    }

    /**
     * The plan a partner has when they've never subscribed. Falls back to a
     * transient free plan so an empty catalogue can't hand anyone paid features.
     */
    public static function default(): self
    {
        return static::query()->where('is_default', true)->first()
            ?? new self(['code' => 'none', 'name' => 'No plan', 'included_conversations' => 0, 'features' => []]);
    }
}
