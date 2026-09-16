<?php

declare(strict_types=1);

namespace Tests\Feature\Membership;

use App\Filament\Resources\MemberEntitlementOverrides\MemberEntitlementOverrideResource;
use App\Filament\Resources\MemberEntitlementOverrides\Pages\ManageMemberEntitlementOverrides;
use App\Filament\Resources\MemberFeatures\MemberFeatureResource;
use App\Filament\Resources\MemberFeatures\Pages\ManageMemberFeatures;
use App\Filament\Resources\MemberPlans\MemberPlanResource;
use App\Filament\Resources\MemberPlans\Pages\EditMemberPlan;
use App\Filament\Resources\MemberPlans\Pages\ListMemberPlans;
use App\Filament\Resources\MemberPlans\RelationManagers\PricesRelationManager;
use App\Filament\Resources\MemberSubscriptions\MemberSubscriptionResource;
use App\Filament\Resources\MemberSubscriptions\Pages\ListMemberSubscriptions;
use App\Filament\Resources\MemberSubscriptions\Pages\ViewMemberSubscription;
use App\Models\AdminAction;
use App\Models\MemberEntitlementOverride;
use App\Models\MemberPlanEntitlement;
use App\Models\MemberPlanPrice;
use App\Models\MemberSubscription;
use App\Models\MemberSubscriptionEvent;
use App\Models\User;
use App\Support\Membership\MemberFeature;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * /control membership management: who can reach it, that the catalogue can be priced and
 * linked to Razorpay safely, and that every admin intervention is applied through the
 * lifecycle service and audited.
 */
class MemberControlPanelTest extends TestCase
{
    use MembershipFixtures;
    use RefreshDatabase;

    private FakeRazorpaySubscriptions $razorpay;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureRazorpay();
        $this->razorpay = FakeRazorpaySubscriptions::install();
        Filament::setCurrentPanel(Filament::getPanel('control'));
    }

    private function staff(string $role): User
    {
        return User::create([
            'name' => $role . ' staff', 'email' => strtolower($role) . '@haraan.test',
            'password' => bcrypt('secret123'), 'role' => $role, 'status' => 'active',
        ]);
    }

    public function test_access_is_limited_to_the_right_desks(): void
    {
        $this->actingAs($this->staff('ADMIN'));
        $this->assertTrue(MemberPlanResource::canAccess());
        $this->assertTrue(MemberFeatureResource::canAccess());
        $this->assertTrue(MemberSubscriptionResource::canAccess());
        $this->assertTrue(MemberEntitlementOverrideResource::canAccess());

        // Finance runs subscriptions and comps, but doesn't set prices.
        $this->actingAs($this->staff('FINANCE'));
        $this->assertFalse(MemberPlanResource::canAccess());
        $this->assertFalse(MemberFeatureResource::canAccess());
        $this->assertTrue(MemberSubscriptionResource::canAccess());
        $this->assertTrue(MemberEntitlementOverrideResource::canAccess());

        foreach (['MARKETING', 'PARTNER', 'USER'] as $role) {
            $this->actingAs($this->staff($role));
            $this->assertFalse(MemberPlanResource::canAccess(), $role);
            $this->assertFalse(MemberSubscriptionResource::canAccess(), $role);
            $this->assertFalse(MemberEntitlementOverrideResource::canAccess(), $role);
        }

        $this->assertFalse(MemberSubscriptionResource::canCreate(), 'subscriptions are never hand-created');
    }

    public function test_pages_render_for_an_admin(): void
    {
        $this->actingAs($this->staff('ADMIN'));
        $member = $this->member();
        $subscription = $this->paidSubscription($member, 'hero');

        Livewire::test(ListMemberPlans::class)->assertOk()->assertSee('Pro')->assertSee('Hero');
        Livewire::test(EditMemberPlan::class, ['record' => $this->plan('pro')->getRouteKey()])
            ->assertOk()
            ->assertSee('AI delivery reviews');
        Livewire::test(ListMemberSubscriptions::class)->assertOk()->assertCanSeeTableRecords([$subscription]);
        Livewire::test(ViewMemberSubscription::class, ['record' => $subscription->getRouteKey()])->assertOk()->assertSee($member->name);
        Livewire::test(ManageMemberFeatures::class)->assertOk();
        Livewire::test(ManageMemberEntitlementOverrides::class)->assertOk();
    }

    public function test_editing_a_plan_completes_its_entitlement_grid(): void
    {
        $this->actingAs($this->staff('ADMIN'));
        $pro = $this->plan('pro');
        MemberPlanEntitlement::query()->where('plan_id', $pro->id)->where('feature_key', MemberFeature::SUPPORT_PRIORITY)->delete();

        Livewire::test(EditMemberPlan::class, ['record' => $pro->getRouteKey()])
            ->fillForm(['tagline' => 'See your game.'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('See your game.', $pro->refresh()->tagline);
        $restored = MemberPlanEntitlement::query()->where('plan_id', $pro->id)->where('feature_key', MemberFeature::SUPPORT_PRIORITY)->first();
        $this->assertNotNull($restored);
        $this->assertFalse($restored->enabled, 'a restored row starts OFF');
        $this->assertTrue(AdminAction::query()->where('action', 'member_plan.updated')->exists());
    }

    public function test_a_price_is_linked_to_razorpay_then_frozen_then_put_on_sale(): void
    {
        $this->actingAs($this->staff('ADMIN'));
        $hero = $this->plan('hero');
        $price = MemberPlanPrice::query()->where('plan_id', $hero->id)->where('interval', 'month')->firstOrFail();

        // Can't be put on sale before it's linked.
        $price->forceFill(['is_active' => true])->save();
        $this->assertFalse($price->refresh()->is_active);

        Livewire::test(PricesRelationManager::class, ['ownerRecord' => $hero, 'pageClass' => EditMemberPlan::class])
            ->callTableAction('linkRazorpay', $price)
            ->assertHasNoTableActionErrors();

        $price->refresh();
        $this->assertStringStartsWith('plan_', (string) $price->razorpay_plan_id);
        $body = $this->razorpay->callsTo('/plans')[0]['body'];
        $this->assertSame('monthly', $body['period']);
        $this->assertSame(24900, $body['item']['amount']);

        try {
            $price->forceFill(['amount_paise' => 1000])->save();
            $this->fail('A linked price must not change amount');
        } catch (\LogicException) {
            $this->assertSame(24900, $price->refresh()->amount_paise);
        }

        Livewire::test(PricesRelationManager::class, ['ownerRecord' => $hero, 'pageClass' => EditMemberPlan::class])
            ->callTableAction('toggleSale', $price)
            ->assertHasNoTableActionErrors();
        $this->assertTrue($price->refresh()->is_active);

        $this->getJson('/api/membership/plans')->assertJsonPath('data.plans.2.prices.0.id', $price->id);
    }

    public function test_granting_extending_and_revoking_a_complimentary_plan(): void
    {
        $admin = $this->staff('FINANCE');
        $this->actingAs($admin);
        $member = $this->member();

        Livewire::test(ListMemberSubscriptions::class)
            ->callAction('grantPlan', data: [
                'user_id' => $member->id,
                'plan_id' => $this->plan('hero')->id,
                'until' => now()->addMonth()->format('Y-m-d H:i:s'),
                'note' => 'Tournament winner prize',
            ])
            ->assertHasNoActionErrors();

        $grant = MemberSubscription::query()->where('user_id', $member->id)->firstOrFail();
        $this->assertSame(MemberSubscription::PROVIDER_ADMIN, $grant->provider);
        $this->assertTrue($grant->grantsAccess());
        $this->assertTrue(MemberSubscriptionEvent::query()->where('subscription_id', $grant->id)->where('type', 'admin_granted')->where('actor_id', $admin->id)->exists());

        $this->assertSame('hero', $this->planOf($member));

        Livewire::test(ListMemberSubscriptions::class)
            ->callTableAction('extendGrant', $grant, ['until' => null])
            ->assertHasNoTableActionErrors();
        $this->assertNull($grant->refresh()->current_period_end);

        Livewire::test(ListMemberSubscriptions::class)
            ->callTableAction('revokeGrant', $grant, ['reason' => 'Awarded in error'])
            ->assertHasNoTableActionErrors();
        $this->assertSame(MemberSubscription::STATUS_REVOKED, $grant->refresh()->status);
        $this->assertSame('free', $this->planOf($member));
    }

    private function planOf(User $member): string
    {
        \App\Services\Membership\MemberEntitlements::flush();

        return app(\App\Services\Membership\MemberEntitlements::class)->for($member)->plan->code;
    }

    public function test_cancelling_a_paid_subscription_from_control_goes_through_razorpay(): void
    {
        $admin = $this->staff('ADMIN');
        $this->actingAs($admin);
        $member = $this->member();
        $subscription = $this->paidSubscription($member, 'pro');
        $this->razorpay->subscriptions[$subscription->provider_subscription_id] = $this->entity($subscription->provider_subscription_id, 'active');

        Livewire::test(ListMemberSubscriptions::class)
            ->callTableAction('cancelNow', $subscription)
            ->assertHasNoTableActionErrors();

        $this->assertSame(MemberSubscription::STATUS_CANCELLED, $subscription->refresh()->status);
        $this->assertSame(0, $this->razorpay->callsTo($subscription->provider_subscription_id . '/cancel')[0]['body']['cancel_at_cycle_end']);
        $this->assertTrue(MemberSubscriptionEvent::query()->where('subscription_id', $subscription->id)->where('actor_id', $admin->id)->exists());
        $this->assertTrue(AdminAction::query()->where('action', 'member_subscription.cancelled')->exists());
    }

    public function test_an_override_records_who_granted_it_and_takes_effect(): void
    {
        $admin = $this->staff('FINANCE');
        $this->actingAs($admin);
        $member = $this->member();

        Livewire::test(ManageMemberEntitlementOverrides::class)
            ->callAction('create', data: [
                'user_id' => $member->id,
                'feature_key' => MemberFeature::AI_DELIVERY_REVIEW,
                'enabled' => true,
                'limit_value' => 50,
                'reason' => 'Coaching partner',
            ])
            ->assertHasNoActionErrors();

        $override = MemberEntitlementOverride::query()->firstOrFail();
        $this->assertSame($admin->id, (int) $override->granted_by);

        $this->asMember($member)->getJson('/api/membership')
            ->assertJsonFragment(['key' => MemberFeature::AI_DELIVERY_REVIEW, 'limit' => 50]);
    }
}
