<?php

declare(strict_types=1);

namespace App\Services\Membership;

use App\Models\MemberFeatureDefinition;
use App\Models\MemberPlan;
use App\Models\MemberPlanEntitlement;
use App\Models\MemberPlanPrice;
use App\Support\Membership\MemberFeature;

/** Catalogue maintenance for /control: complete entitlement grids and Razorpay plan linking. */
class MemberCatalog
{
    public function __construct(private readonly MemberRazorpayClient $razorpay) {}

    /**
     * Give a plan a row for every registered feature (missing ones start OFF), and make sure
     * the feature catalogue has copy for every key. A plan edited in /control always shows
     * the full grid, so a feature can't be silently absent from one tier.
     */
    public function ensureComplete(MemberPlan $plan): void
    {
        foreach (MemberFeature::keys() as $index => $key) {
            MemberFeatureDefinition::query()->firstOrCreate(['key' => $key], [
                'name' => MemberFeature::label($key),
                'type' => MemberFeature::type($key),
                'sort' => ($index + 1) * 10,
                'is_visible' => true,
            ]);

            if ($plan->exists) {
                MemberPlanEntitlement::query()->firstOrCreate(
                    ['plan_id' => $plan->id, 'feature_key' => $key],
                    ['enabled' => false, 'limit_value' => MemberFeature::isBoolean($key) ? null : 0],
                );
            }
        }
    }

    /**
     * Create the Razorpay plan behind a price and link it. After this the price's amount and
     * interval are frozen (see MemberPlanPrice).
     *
     * @throws MembershipException
     */
    public function linkRazorpayPlan(MemberPlanPrice $price): MemberPlanPrice
    {
        $price->loadMissing('plan');

        if (filled($price->razorpay_plan_id)) {
            throw new MembershipException('This price is already linked to a Razorpay plan.', 422, 'already_linked');
        }

        if ($price->amount_paise < 100) {
            throw new MembershipException('A paid price must be at least ₹1.', 422, 'amount_too_low');
        }

        if ($price->plan === null || $price->plan->is_default) {
            throw new MembershipException('The default plan is free and cannot be sold.', 422, 'default_plan');
        }

        $id = $this->razorpay->createPlan(
            name: 'Haraan '.$price->plan->name.' ('.strtolower(MemberPlanPrice::intervalLabel($price->interval)).')',
            interval: $price->interval,
            amountPaise: $price->amount_paise,
            currency: $price->currency,
            notes: ['kind' => 'member', 'plan_code' => $price->plan->code, 'price_id' => (string) $price->id],
        );

        $price->forceFill(['razorpay_plan_id' => $id])->save();

        return $price;
    }
}
