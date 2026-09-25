<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Membership\MemberEntitlements;
use App\Support\Membership\MembershipSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A member's Razorpay subscription, or a plan granted from /control.
 *
 * Status mirrors Razorpay's own vocabulary so a webhook maps one-to-one, plus `abandoned`
 * (checkout dismissed) and, for admin grants, `revoked`. Whether the row is worth anything
 * RIGHT NOW is answered by grantsAccess() and nowhere else.
 *
 * @property int $id
 * @property int $user_id
 * @property int $plan_id
 * @property int|null $price_id
 * @property string $provider
 * @property string $status
 * @property string|null $provider_subscription_id
 * @property Carbon|null $current_period_start
 * @property Carbon|null $current_period_end
 * @property Carbon|null $starts_at
 * @property bool $cancel_at_period_end
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $ended_at
 * @property int|null $replaces_subscription_id
 * @property string $change_type
 * @property int $paid_count
 * @property Carbon|null $last_event_at
 * @property Carbon|null $checkout_expires_at
 * @property string|null $note
 * @property-read MemberPlan|null $plan
 * @property-read MemberPlanPrice|null $price
 * @property-read User|null $user
 */
final class MemberSubscription extends Model
{
    public const PROVIDER_RAZORPAY = 'razorpay';

    public const PROVIDER_ADMIN = 'admin';

    // Razorpay's lifecycle.
    public const STATUS_CREATED = 'created';

    public const STATUS_AUTHENTICATED = 'authenticated';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PENDING = 'pending';

    public const STATUS_HALTED = 'halted';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_EXPIRED = 'expired';

    // Ours.
    public const STATUS_ABANDONED = 'abandoned';

    public const STATUS_REVOKED = 'revoked';

    /** Statuses a Razorpay subscription can still move out of. */
    public const OPEN_STATUSES = [
        self::STATUS_CREATED, self::STATUS_AUTHENTICATED, self::STATUS_ACTIVE,
        self::STATUS_PENDING, self::STATUS_HALTED, self::STATUS_PAUSED,
    ];

    /** Nothing moves a subscription out of these. */
    public const TERMINAL_STATUSES = [
        self::STATUS_CANCELLED, self::STATUS_COMPLETED, self::STATUS_EXPIRED,
        self::STATUS_ABANDONED, self::STATUS_REVOKED,
    ];

    public const CHANGE_NEW = 'new';

    public const CHANGE_UPGRADE = 'upgrade';

    public const CHANGE_DOWNGRADE = 'downgrade';

    public const CHANGE_INTERVAL = 'interval';

    protected $fillable = [
        'user_id', 'plan_id', 'price_id', 'provider', 'status', 'provider_subscription_id',
        'current_period_start', 'current_period_end', 'starts_at', 'cancel_at_period_end',
        'cancelled_at', 'ended_at', 'replaces_subscription_id', 'change_type', 'paid_count',
        'last_event_at', 'checkout_expires_at', 'note', 'granted_by',
    ];

    protected $casts = [
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'starts_at' => 'datetime',
        'cancel_at_period_end' => 'boolean',
        'cancelled_at' => 'datetime',
        'ended_at' => 'datetime',
        'last_event_at' => 'datetime',
        'checkout_expires_at' => 'datetime',
        'paid_count' => 'integer',
    ];

    protected static function booted(): void
    {
        // Entitlements are memoised per request; any write to a subscription invalidates them.
        $flush = fn (MemberSubscription $s) => MemberEntitlements::flush($s->user_id);
        self::saved($flush);
        self::deleted($flush);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MemberPlan::class, 'plan_id');
    }

    public function price(): BelongsTo
    {
        return $this->belongsTo(MemberPlanPrice::class, 'price_id');
    }

    public function replaces(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_subscription_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(MemberPayment::class, 'subscription_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(MemberSubscriptionEvent::class, 'subscription_id');
    }

    /** Rows that could be granting something — the resolver narrows with grantsAccess(). */
    public function scopeCandidates(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_ACTIVE, self::STATUS_PENDING]);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES, true);
    }

    /**
     * Whether this row entitles its member to its plan at [$now].
     *
     *  - active / pending: until the period end plus grace. Pending means Razorpay is still
     *    retrying the renewal; a late webhook for a charge that did go through looks the same
     *    from here. Grace covers both, and a halt ends it the moment Razorpay gives up.
     *  - active with a scheduled cancel: exactly until the period end — they asked to stop.
     *  - admin grant: until its end date, or indefinitely without one.
     *  - anything else — never paid, payment failed for good, ended — grants nothing.
     */
    public function grantsAccess(?Carbon $now = null): bool
    {
        $now ??= Carbon::now();

        if ($this->provider === self::PROVIDER_ADMIN) {
            return $this->status === self::STATUS_ACTIVE
                && ($this->current_period_end === null || $this->current_period_end->greaterThan($now));
        }

        if (! in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_PENDING], true)) {
            return false;
        }

        // Razorpay reports active before we've seen a period on a very early webhook; the
        // activation event itself carries the period, so this is only ever momentary.
        if ($this->current_period_end === null) {
            return $this->status === self::STATUS_ACTIVE;
        }

        if ($this->cancel_at_period_end) {
            return $this->current_period_end->greaterThan($now);
        }

        $graceHours = max(0, MembershipSettings::int('grace_hours'));

        return $this->current_period_end->copy()->addHours($graceHours)->greaterThan($now);
    }

    /** Access granted only because of grace — the renewal hasn't been confirmed yet. */
    public function inGrace(?Carbon $now = null): bool
    {
        $now ??= Carbon::now();

        return $this->grantsAccess($now)
            && $this->provider === self::PROVIDER_RAZORPAY
            && $this->current_period_end !== null
            && $this->current_period_end->lessThanOrEqualTo($now);
    }
}
