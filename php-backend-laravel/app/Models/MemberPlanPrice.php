<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What a plan costs per interval. Once linked to a Razorpay plan, amount and interval are
 * frozen — Razorpay plans are immutable, and a subscriber is charged what the linked plan
 * says, not what this row says. A price change is a new row.
 *
 * @property int $id
 * @property int $plan_id
 * @property string $interval
 * @property int $amount_paise
 * @property string $currency
 * @property string|null $razorpay_plan_id
 * @property bool $is_active
 * @property-read MemberPlan|null $plan
 */
final class MemberPlanPrice extends Model
{
    public const INTERVAL_MONTH = 'month';

    public const INTERVAL_YEAR = 'year';

    public const INTERVALS = [self::INTERVAL_MONTH, self::INTERVAL_YEAR];

    protected $fillable = ['plan_id', 'interval', 'amount_paise', 'currency', 'razorpay_plan_id', 'is_active'];

    protected $casts = [
        'amount_paise' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        self::updating(function (MemberPlanPrice $price): void {
            if ($price->getOriginal('razorpay_plan_id') !== null && $price->isDirty(['amount_paise', 'interval', 'currency'])) {
                throw new \LogicException('This price is linked to a Razorpay plan and can no longer change. Add a new price instead.');
            }
        });

        self::saving(function (MemberPlanPrice $price): void {
            // A price without a Razorpay plan has nothing to charge against, so it can't be sold.
            if ($price->is_active && blank($price->razorpay_plan_id)) {
                $price->is_active = false;
            }
        });
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MemberPlan::class, 'plan_id');
    }

    public function isPurchasable(): bool
    {
        return $this->is_active
            && filled($this->razorpay_plan_id)
            && $this->amount_paise >= 100
            && $this->plan !== null
            && $this->plan->is_active;
    }

    /** "₹99/month" */
    public function label(): string
    {
        $rupees = $this->amount_paise / 100;
        $amount = '₹' . (fmod($rupees, 1.0) === 0.0 ? number_format($rupees) : number_format($rupees, 2));

        return $amount . ($this->interval === self::INTERVAL_YEAR ? '/year' : '/month');
    }
}
