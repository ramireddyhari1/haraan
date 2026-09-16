<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only history of a member subscription: every webhook applied, every admin action,
 * every member action. `provider_event_id` is UNIQUE, which is what turns a webhook Razorpay
 * redelivers into a no-op.
 *
 * @property string $type
 * @property string|null $from_status
 * @property string|null $to_status
 * @property array<string, mixed>|null $payload
 */
final class MemberSubscriptionEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'subscription_id', 'user_id', 'type', 'provider_event_id',
        'from_status', 'to_status', 'actor_id', 'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(MemberSubscription::class, 'subscription_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
