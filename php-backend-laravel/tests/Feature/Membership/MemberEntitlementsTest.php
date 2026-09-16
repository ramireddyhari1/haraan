<?php

declare(strict_types=1);

namespace Tests\Feature\Membership;

use App\Models\MemberEntitlementOverride;
use App\Models\MemberPlan;
use App\Models\MemberPlanEntitlement;
use App\Models\MemberSubscription;
use App\Services\Membership\EntitlementDenied;
use App\Services\Membership\MemberEntitlements;
use App\Support\Membership\MemberFeature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The entitlement engine is the one place member access is decided, so its resolution rules
 * are tested directly: which subscription wins, when access starts and stops across the
 * lifecycle, how overrides apply, and that quotas can't be over-consumed.
 */
class MemberEntitlementsTest extends TestCase
{
    use MembershipFixtures;
    use RefreshDatabase;

    private function entitlements(): MemberEntitlements
    {
        MemberEntitlements::flush();

        return app(MemberEntitlements::class);
    }

    public function test_the_migration_seeds_free_pro_and_hero_with_every_feature(): void
    {
        $this->assertSame(['free', 'pro', 'hero'], MemberPlan::query()->orderBy('rank')->pluck('code')->all());
        $this->assertSame('free', MemberPlan::default()->code);

        foreach (['free', 'pro', 'hero'] as $code) {
            $keys = $this->plan($code)->entitlements()->pluck('feature_key')->sort()->values()->all();
            $expected = MemberFeature::keys();
            sort($expected);
            $this->assertSame($expected, $keys, "{$code} is missing entitlement rows");
        }

        // Nothing is purchasable until /control links a Razorpay plan.
        $this->assertFalse(\App\Models\MemberPlanPrice::query()->where('is_active', true)->exists());
    }

    public function test_guests_and_members_without_a_plan_get_the_free_plan(): void
    {
        $ent = $this->entitlements();
        $member = $this->member();

        foreach ([null, $member] as $who) {
            $set = $ent->for($who);
            $this->assertSame('free', $set->plan->code);
            $this->assertNull($set->subscription);
            $this->assertFalse($set->allows(MemberFeature::ADS_HIDDEN));
            $this->assertFalse($set->allows(MemberFeature::AI_CAREER_READ));
            $this->assertSame(2, $set->limit(MemberFeature::AI_DELIVERY_REVIEW));
            $this->assertSame(1, $set->limit(MemberFeature::TOURNAMENTS_ACTIVE_HOSTED));
        }
    }

    public function test_an_active_subscription_grants_its_plan(): void
    {
        $member = $this->member();
        $this->paidSubscription($member, 'hero');

        $set = $this->entitlements()->for($member);

        $this->assertSame('hero', $set->plan->code);
        $this->assertTrue($set->allows(MemberFeature::ADS_HIDDEN));
        $this->assertTrue($set->allows(MemberFeature::SUPPORT_PRIORITY));
        $this->assertNull($set->limit(MemberFeature::AI_DELIVERY_REVIEW), 'Hero reviews are unlimited');
    }

    public function test_the_highest_ranked_granting_subscription_wins(): void
    {
        $member = $this->member();
        $this->paidSubscription($member, 'hero');
        $this->paidSubscription($member, 'pro');

        $this->assertSame('hero', $this->entitlements()->for($member)->plan->code);
    }

    /** @return array<string, array{0: string, 1: array<string, mixed>, 2: bool}> */
    public static function lifecycleAccess(): array
    {
        return [
            'active in period' => ['active', [], true],
            'active, renewal webhook late but within grace' => ['active', ['current_period_end' => '-10 hours'], true],
            'active, past grace' => ['active', ['current_period_end' => '-3 days'], false],
            'pending (Razorpay retrying) within grace' => ['pending', ['current_period_end' => '-1 day'], true],
            'pending past grace' => ['pending', ['current_period_end' => '-5 days'], false],
            'halted' => ['halted', [], false],
            'created (checkout not paid)' => ['created', [], false],
            'authenticated (mandate only)' => ['authenticated', [], false],
            'cancel scheduled, period not over' => ['active', ['cancel_at_period_end' => true], true],
            'cancel scheduled, period over — no grace' => ['active', ['cancel_at_period_end' => true, 'current_period_end' => '-1 hour'], false],
            'cancelled' => ['cancelled', [], false],
            'completed' => ['completed', [], false],
            'expired' => ['expired', [], false],
            'abandoned' => ['abandoned', [], false],
            'paused' => ['paused', [], false],
        ];
    }

    #[DataProvider('lifecycleAccess')]
    public function test_access_follows_the_subscription_lifecycle(string $status, array $overrides, bool $grants): void
    {
        config(['membership.grace_hours' => 48]);
        $member = $this->member();

        if (isset($overrides['current_period_end'])) {
            $overrides['current_period_end'] = Carbon::now()->modify($overrides['current_period_end']);
        }

        $this->paidSubscription($member, 'pro', ['status' => $status] + $overrides);

        $this->assertSame($grants ? 'pro' : 'free', $this->entitlements()->for($member)->plan->code);
    }

    public function test_complimentary_grants_respect_their_end_date(): void
    {
        $member = $this->member();
        $grant = MemberSubscription::create([
            'user_id' => $member->id, 'plan_id' => $this->plan('pro')->id,
            'provider' => MemberSubscription::PROVIDER_ADMIN, 'status' => MemberSubscription::STATUS_ACTIVE,
            'current_period_end' => null,
        ]);
        $this->assertSame('pro', $this->entitlements()->for($member)->plan->code, 'no end date = indefinite');

        $grant->forceFill(['current_period_end' => Carbon::now()->subMinute()])->save();
        $this->assertSame('free', $this->entitlements()->for($member)->plan->code, 'admin grants get no grace');
    }

    public function test_overrides_replace_the_plan_value_in_either_direction_until_they_expire(): void
    {
        $member = $this->member();
        $this->paidSubscription($member, 'pro');

        $up = MemberEntitlementOverride::create([
            'user_id' => $member->id, 'feature_key' => MemberFeature::SUPPORT_PRIORITY,
            'enabled' => true, 'reason' => 'creator deal',
        ]);
        MemberEntitlementOverride::create([
            'user_id' => $member->id, 'feature_key' => MemberFeature::ADS_HIDDEN,
            'enabled' => false, 'reason' => 'ad QA account',
        ]);

        $set = $this->entitlements()->for($member);
        $this->assertTrue($set->allows(MemberFeature::SUPPORT_PRIORITY));
        $this->assertSame('override', $set->source(MemberFeature::SUPPORT_PRIORITY));
        $this->assertFalse($set->allows(MemberFeature::ADS_HIDDEN));

        $up->forceFill(['expires_at' => Carbon::now()->subSecond()])->save();
        $this->assertFalse($this->entitlements()->for($member)->allows(MemberFeature::SUPPORT_PRIORITY));
    }

    public function test_plan_values_are_read_from_the_database_not_code(): void
    {
        $member = $this->member();

        MemberPlanEntitlement::query()
            ->where('plan_id', $this->plan('free')->id)
            ->where('feature_key', MemberFeature::ADS_HIDDEN)
            ->update(['enabled' => true]);

        $this->assertTrue($this->entitlements()->allows($member, MemberFeature::ADS_HIDDEN));
    }

    public function test_a_feature_missing_from_a_plan_fails_closed(): void
    {
        $member = $this->member();
        $this->paidSubscription($member, 'hero');
        MemberPlanEntitlement::query()
            ->where('plan_id', $this->plan('hero')->id)
            ->where('feature_key', MemberFeature::ADS_HIDDEN)
            ->delete();

        $this->assertFalse($this->entitlements()->allows($member, MemberFeature::ADS_HIDDEN));
    }

    public function test_an_empty_catalogue_never_grants_paid_features(): void
    {
        MemberPlan::query()->update(['is_default' => false]);

        $set = $this->entitlements()->for($this->member());

        foreach (MemberFeature::keys() as $key) {
            $this->assertFalse($set->allows($key), $key);
        }
    }

    public function test_authorize_denies_with_the_cheapest_plan_that_unlocks_the_feature(): void
    {
        $member = $this->member();

        try {
            $this->entitlements()->authorize($member, MemberFeature::AI_CAREER_READ);
            $this->fail('Expected a denial');
        } catch (EntitlementDenied $e) {
            $this->assertSame(EntitlementDenied::CODE_UPGRADE_REQUIRED, $e->reason);
            $this->assertSame('free', $e->planCode);
            $this->assertSame('pro', $e->upgradePlanCode);
        }

        try {
            $this->entitlements()->authorize($member, MemberFeature::SUPPORT_PRIORITY);
            $this->fail('Expected a denial');
        } catch (EntitlementDenied $e) {
            $this->assertSame('hero', $e->upgradePlanCode, 'Pro does not include priority support');
        }
    }

    public function test_limits_deny_at_the_ceiling_and_allow_unlimited(): void
    {
        $free = $this->member();
        $ent = $this->entitlements();

        $ent->assertWithinLimit($free, MemberFeature::TOURNAMENTS_ACTIVE_HOSTED, 0);

        try {
            $ent->assertWithinLimit($free, MemberFeature::TOURNAMENTS_ACTIVE_HOSTED, 1);
            $this->fail('Expected a denial');
        } catch (EntitlementDenied $e) {
            $this->assertSame(EntitlementDenied::CODE_LIMIT_REACHED, $e->reason);
            $this->assertSame(1, $e->limit);
            $this->assertSame(1, $e->used);
            $this->assertSame('pro', $e->upgradePlanCode);
        }

        $hero = $this->member();
        $this->paidSubscription($hero, 'hero');
        $this->entitlements()->assertWithinLimit($hero, MemberFeature::TOURNAMENTS_ACTIVE_HOSTED, 500);
        $this->addToAssertionCount(1);
    }

    public function test_quota_consumption_stops_exactly_at_the_limit_and_resets_monthly(): void
    {
        $member = $this->member();
        $ent = $this->entitlements();

        $ent->consume($member, MemberFeature::AI_DELIVERY_REVIEW);
        $ent->consume($member, MemberFeature::AI_DELIVERY_REVIEW);
        $this->assertSame(2, $ent->usage($member, MemberFeature::AI_DELIVERY_REVIEW));

        try {
            $ent->consume($member, MemberFeature::AI_DELIVERY_REVIEW);
            $this->fail('Third review on Free should be refused');
        } catch (EntitlementDenied $e) {
            $this->assertSame(EntitlementDenied::CODE_LIMIT_REACHED, $e->reason);
        }
        $this->assertSame(2, $ent->usage($member, MemberFeature::AI_DELIVERY_REVIEW), 'a refused consume writes nothing');

        $ent->release($member, MemberFeature::AI_DELIVERY_REVIEW);
        $this->assertSame(1, $ent->usage($member, MemberFeature::AI_DELIVERY_REVIEW));

        Carbon::setTestNow(Carbon::now()->startOfMonth()->addMonth()->addDay());
        try {
            $this->assertSame(0, $ent->usage($member, MemberFeature::AI_DELIVERY_REVIEW));
            $ent->consume($member, MemberFeature::AI_DELIVERY_REVIEW);
            $this->assertSame(1, $ent->usage($member, MemberFeature::AI_DELIVERY_REVIEW));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_release_never_goes_below_zero(): void
    {
        $member = $this->member();
        $this->entitlements()->release($member, MemberFeature::AI_DELIVERY_REVIEW);

        $this->assertSame(0, $this->entitlements()->usage($member, MemberFeature::AI_DELIVERY_REVIEW));
    }

    public function test_subscription_writes_flush_memoised_entitlements_within_a_request(): void
    {
        $member = $this->member();
        $ent = app(MemberEntitlements::class);
        MemberEntitlements::flush();

        $this->assertSame('free', $ent->for($member)->plan->code);
        $this->paidSubscription($member, 'pro');
        $this->assertSame('pro', $ent->for($member)->plan->code);
    }

    /**
     * The architectural rule: no controller, job or Filament class compares plan codes. Every
     * gate goes through MemberEntitlements with a MemberFeature key.
     */
    public function test_no_plan_code_checks_are_scattered_through_the_app(): void
    {
        $offenders = [];
        $allowed = [
            app_path('Services/Membership'),
            app_path('Models/MemberPlan.php'),
        ];

        foreach (File::allFiles(app_path()) as $file) {
            $path = $file->getPathname();
            foreach ($allowed as $prefix) {
                if (str_starts_with($path, $prefix)) {
                    continue 2;
                }
            }

            $source = $file->getContents();
            if (preg_match('/(===?|!==?)\s*[\'"](pro|hero)[\'"]|[\'"](pro|hero)[\'"]\s*(===?|!==?)|->code\s*===?\s*[\'"]|is_pro|isPro|isHero|is_hero/i', $source)
                && preg_match('/member|subscription|entitle/i', $source)) {
                $offenders[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $path);
            }
        }

        $this->assertSame([], $offenders, 'Use MemberEntitlements with a MemberFeature key instead of comparing plan codes.');
    }
}
