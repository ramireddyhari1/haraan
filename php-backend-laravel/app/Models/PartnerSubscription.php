<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Which plan a partner is on, and whether it's paying.
 *
 * `halted` is Razorpay's word for "the card failed and we've stopped trying".
 * It suspends automation features — but never ticket delivery, which isn't a
 * plan feature at all.
 */
final class PartnerSubscription extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_TRIALING = 'trialing';

    /** Payment failed; automations off until it's fixed. */
    public const STATUS_HALTED = 'halted';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'partner_id', 'plan_id', 'status', 'current_period_start',
        'current_period_end', 'cancel_at', 'external_id', 'source', 'note',
    ];

    protected $casts = [
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'cancel_at' => 'datetime',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PartnerPlan::class, 'plan_id');
    }

    /** Entitlements only flow from a subscription that's actually paying. */
    public function isLive(): bool
    {
        return in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_TRIALING], true)
            && ($this->current_period_end === null || $this->current_period_end->isFuture());
    }
}
