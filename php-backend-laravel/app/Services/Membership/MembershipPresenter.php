<?php

declare(strict_types=1);

namespace App\Services\Membership;

use App\Models\MemberFeatureDefinition;
use App\Models\MemberPayment;
use App\Models\MemberPlan;
use App\Models\MemberPlanPrice;
use App\Models\MemberSubscription;
use App\Models\User;
use App\Support\Membership\MemberFeature;
use Illuminate\Support\Carbon;

/** Shapes membership state for the app. Reads only; every decision it shows comes from MemberEntitlements. */
final class MembershipPresenter
{
    public function __construct(private readonly MemberEntitlements $entitlements) {}

    /** @return array<string, mixed> */
    public function catalogue(?User $user): array
    {
        $current = $this->entitlements->for($user)->plan;
        $definitions = MemberFeatureDefinition::query()->where('is_visible', true)->orderBy('sort')->get();

        $plans = MemberPlan::query()
            ->with(['entitlements', 'prices' => fn ($q) => $q->where('is_active', true)->orderBy('amount_paise')])
            ->where('is_active', true)
            ->orderBy('rank')
            ->get()
            ->map(function (MemberPlan $plan) use ($current, $definitions): array {
                $features = [];
                foreach ($definitions as $definition) {
                    if (! MemberFeature::exists($definition->key)) {
                        continue;
                    }
                    $row = $plan->entitlements->firstWhere('feature_key', $definition->key);
                    $type = MemberFeature::type($definition->key);
                    $enabled = $row !== null && $row->enabled
                        && ($type === MemberFeature::TYPE_BOOLEAN || $row->limit_value === null || $row->limit_value > 0);

                    $features[] = [
                        'key' => $definition->key,
                        'name' => $definition->name,
                        'description' => $definition->description,
                        'type' => $type,
                        'unit' => $definition->unit,
                        'enabled' => $enabled,
                        'limit' => $type === MemberFeature::TYPE_BOOLEAN || ! $enabled ? null : $row?->limit_value,
                        'unlimited' => $type !== MemberFeature::TYPE_BOOLEAN && $enabled && $row?->limit_value === null,
                    ];
                }

                return [
                    'code' => $plan->code,
                    'name' => $plan->name,
                    'tagline' => $plan->tagline,
                    'description' => $plan->description,
                    'rank' => $plan->rank,
                    'is_default' => $plan->is_default,
                    'is_current' => $plan->code === $current->code,
                    'prices' => $plan->prices
                        ->each(fn (MemberPlanPrice $p) => $p->setRelation('plan', $plan))
                        ->filter(fn (MemberPlanPrice $p) => $p->isPurchasable())
                        ->map(fn (MemberPlanPrice $p) => $this->price($p))
                        ->values()
                        ->all(),
                    'features' => $features,
                ];
            })
            ->all();

        return [
            'current_plan' => $current->code,
            'plans' => $plans,
        ];
    }

    /** @return array<string, mixed> */
    public function membership(User $user): array
    {
        MemberEntitlements::flush($user->id);
        $set = $this->entitlements->for($user);
        $granting = $set->subscription;
        $now = Carbon::now();

        $scheduled = MemberSubscription::query()
            ->with(['plan', 'price'])
            ->where('user_id', $user->id)
            ->where('provider', MemberSubscription::PROVIDER_RAZORPAY)
            ->where('status', MemberSubscription::STATUS_AUTHENTICATED)
            ->where('starts_at', '>', $now)
            ->latest('id')
            ->first();

        // A failed card only needs the member's attention if nothing else is covering them.
        $latestRazorpay = MemberSubscription::query()
            ->where('user_id', $user->id)
            ->where('provider', MemberSubscription::PROVIDER_RAZORPAY)
            ->whereNotIn('status', [MemberSubscription::STATUS_CREATED, MemberSubscription::STATUS_ABANDONED])
            ->latest('id')
            ->first();

        $attention = null;
        if ($granting?->status === MemberSubscription::STATUS_PENDING || $granting?->inGrace($now)) {
            $attention = 'payment_retrying';
        } elseif ($latestRazorpay?->status === MemberSubscription::STATUS_HALTED
            && ($granting === null || $granting->plan?->rank < $latestRazorpay->plan?->rank)) {
            $attention = 'payment_failed';
        }

        return [
            'plan' => [
                'code' => $set->plan->code,
                'name' => $set->plan->name,
                'rank' => $set->plan->rank,
            ],
            'source' => $granting === null ? 'default' : $granting->provider,
            'subscription' => $granting === null ? null : $this->subscription($granting, $now),
            'scheduled_change' => $scheduled === null ? null : [
                'plan' => ['code' => $scheduled->plan?->code, 'name' => $scheduled->plan?->name],
                'interval' => $scheduled->price?->interval,
                'amount_paise' => $scheduled->price?->amount_paise,
                'starts_at' => $scheduled->starts_at?->toIso8601String(),
            ],
            'attention' => $attention,
            'badge' => $set->allows(MemberFeature::PROFILE_MEMBER_BADGE) ? $set->plan->code : null,
            'entitlements' => $this->entitlements->describe($user),
            'insight_sports' => app(SportInsightsAccess::class)->status($user),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function payments(User $user): array
    {
        return MemberPayment::query()
            ->with('subscription.plan')
            ->where('user_id', $user->id)
            ->latest('paid_at')
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn (MemberPayment $p) => [
                'id' => $p->provider_payment_id,
                'plan' => $p->subscription?->plan?->name,
                'amount_paise' => $p->amount_paise,
                'currency' => $p->currency,
                'status' => $p->status,
                'method' => $p->method,
                'paid_at' => $p->paid_at?->toIso8601String(),
            ])
            ->all();
    }

    /** @return array<string, mixed> */
    public function subscription(MemberSubscription $s, ?Carbon $now = null): array
    {
        $now ??= Carbon::now();
        $s->loadMissing(['plan', 'price']);
        $isRazorpay = $s->provider === MemberSubscription::PROVIDER_RAZORPAY;
        $renews = $isRazorpay && $s->status === MemberSubscription::STATUS_ACTIVE && ! $s->cancel_at_period_end;

        return [
            'id' => $s->id,
            'status' => $s->status,
            'provider' => $s->provider,
            'plan' => ['code' => $s->plan?->code, 'name' => $s->plan?->name],
            'interval' => $s->price?->interval,
            'amount_paise' => $s->price?->amount_paise,
            'current_period_start' => $s->current_period_start?->toIso8601String(),
            'current_period_end' => $s->current_period_end?->toIso8601String(),
            'renews_at' => $renews ? $s->current_period_end?->toIso8601String() : null,
            'ends_at' => ! $renews ? $s->current_period_end?->toIso8601String() : null,
            'cancel_at_period_end' => $s->cancel_at_period_end,
            'in_grace' => $s->inGrace($now),
            'can_cancel' => $isRazorpay && in_array($s->status, [MemberSubscription::STATUS_ACTIVE, MemberSubscription::STATUS_PENDING], true) && ! $s->cancel_at_period_end,
        ];
    }

    /** @return array<string, mixed> */
    private function price(MemberPlanPrice $price): array
    {
        return [
            'id' => $price->id,
            'interval' => $price->interval,
            'amount_paise' => $price->amount_paise,
            'currency' => $price->currency,
            'label' => $price->label(),
        ];
    }
}
