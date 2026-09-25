<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\AuditsAdminChanges;
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
    use AuditsAdminChanges;

    public const INTERVAL_MONTH = 'month';

    /** Billed every 3 months. */
    public const INTERVAL_QUARTER = 'quarter';

    /** Billed every 6 months. */
    public const INTERVAL_HALF_YEAR = 'half_year';

    public const INTERVAL_YEAR = 'year';

    /** Shortest to longest — the order plans are offered in. */
    public const INTERVALS = [self::INTERVAL_MONTH, self::INTERVAL_QUARTER, self::INTERVAL_HALF_YEAR, self::INTERVAL_YEAR];

    /**
     * Each interval as Razorpay bills it ([period, interval]) and how people say it.
     *
     * @var array<string, array{period: string, every: int, months: int, label: string, suffix: string}>
     */
    private const TERMS = [
        self::INTERVAL_MONTH => ['period' => 'monthly', 'every' => 1, 'months' => 1, 'label' => 'Monthly', 'suffix' => '/month'],
        self::INTERVAL_QUARTER => ['period' => 'monthly', 'every' => 3, 'months' => 3, 'label' => '3 months', 'suffix' => '/3 months'],
        self::INTERVAL_HALF_YEAR => ['period' => 'monthly', 'every' => 6, 'months' => 6, 'label' => '6 months', 'suffix' => '/6 months'],
        self::INTERVAL_YEAR => ['period' => 'yearly', 'every' => 1, 'months' => 12, 'label' => 'Yearly', 'suffix' => '/year'],
    ];

    /** @return array<string, string> interval => label, for pickers. */
    public static function intervalOptions(): array
    {
        return array_map(fn (array $t): string => $t['label'], self::TERMS);
    }

    public static function intervalLabel(string $interval): string
    {
        return self::TERMS[$interval]['label'] ?? $interval;
    }

    /**
     * How Razorpay bills [interval]: its `period` and `interval` fields.
     *
     * @return array{period: string, every: int}
     */
    public static function razorpayTerm(string $interval): array
    {
        $term = self::TERMS[$interval] ?? throw new \InvalidArgumentException("Unknown member price interval [{$interval}].");

        return ['period' => $term['period'], 'every' => $term['every']];
    }

    public static function monthsIn(string $interval): int
    {
        return self::TERMS[$interval]['months'] ?? 1;
    }

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

    /** "₹99/month", "₹249/3 months" */
    public function label(): string
    {
        $rupees = $this->amount_paise / 100;
        $amount = '₹'.(fmod($rupees, 1.0) === 0.0 ? number_format($rupees) : number_format($rupees, 2));

        return $amount.(self::TERMS[$this->interval]['suffix'] ?? '/month');
    }
}
