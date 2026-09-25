<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\AppUsers\AppUserResource;
use App\Filament\Resources\AppUsers\Pages\ViewAppUser;
use App\Filament\Resources\AppUsers\RelationManagers\ActivityTimelineRelationManager;
use App\Filament\Resources\AppUsers\RelationManagers\BookingsRelationManager;
use App\Filament\Resources\AppUsers\RelationManagers\DevicesRelationManager;
use App\Filament\Resources\AppUsers\RelationManagers\GameHubActivityRelationManager;
use App\Filament\Resources\AppUsers\RelationManagers\PaymentsRelationManager;
use App\Filament\Resources\AppUsers\RelationManagers\RewardGrantsRelationManager;
use App\Filament\Resources\AppUsers\RelationManagers\SupportThreadsRelationManager;
use App\Filament\Resources\AppUsers\Schemas\UserInfolist;
use App\Filament\Resources\AppUsers\Widgets\UserOverviewStatsWidget;
use App\Filament\Resources\AppUsers\Widgets\UserSpendChartWidget;
use App\Models\AdminAction;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\DeviceToken;
use App\Models\Event;
use App\Models\LiveMatch;
use App\Models\MemberPlan;
use App\Models\MemberSubscription;
use App\Models\PlayerMatchStat;
use App\Models\RewardGrant;
use App\Models\SupportCategory;
use App\Models\SupportThread;
use App\Models\User;
use App\Models\Venue;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class User360ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('control'));
    }

    private function createStaff(string $role): User
    {
        return User::create([
            'name' => "{$role} Operator",
            'email' => strtolower($role) . '@haraan.test',
            'password' => bcrypt('Password123!'),
            'role' => $role,
            'status' => 'ACTIVE',
        ]);
    }

    private function createCustomer(array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => 'Kishore Kumar',
            'username' => 'kishorek',
            'email' => 'kishore.kumar@example.com',
            'password' => bcrypt('Password123!'),
            'phone' => '+919876543210',
            'date_of_birth' => '1995-05-15',
            'gender' => 'male',
            'district' => 'Coimbatore',
            'state' => 'Tamil Nadu',
            'nationality' => 'Indian',
            'primary_sport' => 'Cricket',
            'player_role' => 'All-Rounder',
            'batting_style' => 'Right-hand bat',
            'bowling_style' => 'Right-arm medium',
            'career_matches' => 18,
            'career_runs' => 450,
            'career_wickets' => 12,
            'career_overs_bowled' => '42.0',
            'ranked_xp' => 1250,
            'casual_xp' => 300,
            'trust_score' => 95,
            'role' => 'USER',
            'status' => 'ACTIVE',
        ], $attributes));
    }

    public function test_access_permissions_for_user_360_page(): void
    {
        $customer = $this->createCustomer();

        // 1. Super-admin can access
        $admin = $this->createStaff('ADMIN');
        $this->actingAs($admin);
        $this->assertTrue(AppUserResource::canView($customer));
        Livewire::test(ViewAppUser::class, ['record' => $customer->getRouteKey()])->assertOk();

        // 2. OPS operator can access
        $ops = $this->createStaff('OPS');
        $this->actingAs($ops);
        $this->assertTrue(AppUserResource::canView($customer));
        Livewire::test(ViewAppUser::class, ['record' => $customer->getRouteKey()])->assertOk();

        // 3. Unauthorized staff (Finance, Marketing, Partner, User) cannot view
        foreach (['FINANCE', 'MARKETING', 'PARTNER', 'USER'] as $role) {
            $staff = $this->createStaff($role);
            $this->actingAs($staff);
            $this->assertFalse(AppUserResource::canView($customer), "Role {$role} should not view app users");
        }
    }

    public function test_view_app_user_page_renders_identity_and_sports_profile(): void
    {
        $admin = $this->createStaff('ADMIN');
        $this->actingAs($admin);

        $customer = $this->createCustomer();

        Livewire::test(ViewAppUser::class, ['record' => $customer->getRouteKey()])
            ->assertOk()
            ->assertSee($customer->name)
            ->assertSee($customer->player_id)
            ->assertSee('@' . $customer->username)
            ->assertSee('Cricket')
            ->assertSee('Coimbatore');
    }

    public function test_pii_protection_masked_for_ops_unmasked_and_audited_for_super_admin(): void
    {
        $customer = $this->createCustomer();

        // Standard OPS operator: PII is masked
        $ops = $this->createStaff('OPS');
        $this->actingAs($ops);
        $this->assertFalse(UserInfolist::canViewPii($customer));

        // Super-admin: PII is unmasked and audited on view
        $admin = $this->createStaff('ADMIN');
        $this->actingAs($admin);
        $this->assertTrue(UserInfolist::canViewPii($customer));

        // Visiting as super-admin triggers audit
        Livewire::test(ViewAppUser::class, ['record' => $customer->getRouteKey()])->assertOk();

        $this->assertDatabaseHas('admin_actions', [
            'user_id' => $admin->id,
            'action' => 'user.pii_viewed',
            'subject_type' => 'User',
            'subject_id' => $customer->id,
        ]);
    }

    public function test_overview_stats_widget_computes_metrics_accurately(): void
    {
        $customer = $this->createCustomer();

        // 1. Create Venue & Bookings
        $partner = User::create([
            'name' => 'Turf Partner',
            'email' => 'partner@haraan.test',
            'password' => bcrypt('Password123!'),
            'role' => 'PARTNER',
            'status' => 'ACTIVE',
        ]);

        $venue = Venue::create([
            'partner_id' => $partner->id,
            'name' => 'Marina Arena',
            'city' => 'Chennai',
            'address' => 'Beach Road',
            'location' => 'Beach Road, Chennai',
        ]);

        // Paid booking (₹1500)
        Booking::create([
            'user_id' => $customer->id,
            'venue_id' => $venue->id,
            'booking_type' => 'venue',
            'total_amount' => 1500.00,
            'quantity' => 1,
            'status' => 'confirmed',
        ]);

        // Cancelled booking (₹800)
        Booking::create([
            'user_id' => $customer->id,
            'venue_id' => $venue->id,
            'booking_type' => 'venue',
            'total_amount' => 800.00,
            'quantity' => 1,
            'status' => 'cancelled',
        ]);

        // 2. Active Subscription
        $plan = MemberPlan::create([
            'code' => 'pro-athlete',
            'name' => 'Pro Athlete Pass',
            'is_active' => true,
        ]);

        MemberSubscription::create([
            'user_id' => $customer->id,
            'plan_id' => $plan->id,
            'provider' => 'razorpay',
            'status' => 'active',
            'starts_at' => now()->subDays(10),
            'current_period_start' => now()->subDays(10),
            'current_period_end' => now()->addDays(20),
        ]);

        // 3. Match stats
        $match = LiveMatch::create([
            'title' => 'Sunday Premier Match',
            'home' => 'Coimbatore CC',
            'away' => 'Salem Strikers',
            'status' => 'COMPLETED',
            'visibility' => LiveMatch::VIS_LOCAL,
        ]);

        PlayerMatchStat::create([
            'match_id' => $match->id,
            'user_id' => $customer->id,
            'player_name' => $customer->name,
            'sport' => 'cricket',
            'side' => 'home',
            'played' => true,
            'runs' => 64,
            'balls' => 38,
            'wickets' => 2,
            'overs_bowled' => '4.0',
            'runs_conceded' => 24,
            'result' => 'win',
        ]);

        // 4. Support Thread
        $cat = SupportCategory::create(['label' => 'Account Issue', 'icon_key' => 'account']);
        SupportThread::create([
            'user_id' => $customer->id,
            'category_id' => $cat->id,
            'subject' => 'Need receipt copy',
            'status' => 'open',
        ]);

        // 5. Reward Grant
        RewardGrant::create([
            'user_id' => $customer->id,
            'source' => 'milestone',
            'title' => 'Century Milestone Reward',
            'type' => 'bonus_xp',
            'bonus_xp' => 250,
            'status' => 'available',
            'dedupe_key' => 'milestone-100-' . $customer->id,
        ]);

        // Test widget directly
        $widget = new UserOverviewStatsWidget();
        $widget->record = $customer;

        $reflection = new \ReflectionClass($widget);
        $getStatsMethod = $reflection->getMethod('getStats');
        $getStatsMethod->setAccessible(true);
        $stats = $getStatsMethod->invoke($widget);

        $this->assertCount(6, $stats);
        $this->assertSame('₹1,500', $stats[0]->getValue());
        $this->assertSame('2', $stats[1]->getValue()); // 2 total bookings
        $this->assertSame('Pro Athlete Pass', $stats[2]->getValue()); // Subscription
        $this->assertSame('1', $stats[3]->getValue()); // 1 match
        $this->assertSame('1 open', $stats[4]->getValue()); // 1 open ticket
        $this->assertSame('1 active', $stats[5]->getValue()); // 1 reward
    }

    public function test_spend_chart_widget_generates_monthly_buckets(): void
    {
        $customer = $this->createCustomer();

        $widget = new UserSpendChartWidget();
        $widget->record = $customer;

        $reflection = new \ReflectionClass($widget);
        $getDataMethod = $reflection->getMethod('getData');
        $getDataMethod->setAccessible(true);
        $data = $getDataMethod->invoke($widget);

        $this->assertArrayHasKey('labels', $data);
        $this->assertArrayHasKey('datasets', $data);
        $this->assertCount(12, $data['labels']);
        $this->assertCount(12, $data['datasets'][0]['data']);
    }

    public function test_relation_managers_render_user_records(): void
    {
        $admin = $this->createStaff('ADMIN');
        $this->actingAs($admin);

        $customer = $this->createCustomer();

        // Create booking & booking payment
        $booking = Booking::create([
            'user_id' => $customer->id,
            'total_amount' => 500.00,
            'quantity' => 1,
            'status' => 'confirmed',
        ]);

        $payment = BookingPayment::create([
            'booking_id' => $booking->id,
            'amount' => 500.00,
            'method' => 'upi',
            'reference' => 'UPI-TXN-12345',
            'collected_at' => now(),
        ]);

        // Create match stat
        $match = LiveMatch::create([
            'title' => 'Championship Final',
            'home' => 'Team A',
            'away' => 'Team B',
            'status' => 'COMPLETED',
        ]);

        $stat = PlayerMatchStat::create([
            'match_id' => $match->id,
            'user_id' => $customer->id,
            'player_name' => $customer->name,
            'sport' => 'cricket',
            'played' => true,
            'runs' => 45,
            'balls' => 20,
        ]);

        // Create reward grant
        $reward = RewardGrant::create([
            'user_id' => $customer->id,
            'source' => 'actionboard',
            'title' => 'Top Scorer Trophy',
            'type' => 'badge',
            'status' => 'available',
            'dedupe_key' => 'top-scorer-' . $customer->id,
        ]);

        // Create support thread
        $thread = SupportThread::create([
            'user_id' => $customer->id,
            'subject' => 'Booking clarification',
            'status' => 'open',
        ]);

        // Create admin action
        $action = AdminAction::create([
            'user_id' => $admin->id,
            'action' => 'user.verified',
            'subject_type' => 'User',
            'subject_id' => $customer->id,
            'meta' => ['is_verified' => true],
        ]);

        // Create device token
        $device = DeviceToken::create([
            'user_id' => $customer->id,
            'token' => 'fcm-sample-token-string-xyz-12345678',
            'platform' => 'android',
            'last_seen_at' => now(),
        ]);

        // Test BookingsRelationManager
        Livewire::test(BookingsRelationManager::class, ['ownerRecord' => $customer, 'pageClass' => ViewAppUser::class])
            ->assertOk()
            ->assertCanSeeTableRecords([$booking]);

        // Test PaymentsRelationManager
        Livewire::test(PaymentsRelationManager::class, ['ownerRecord' => $customer, 'pageClass' => ViewAppUser::class])
            ->assertOk()
            ->assertCanSeeTableRecords([$payment]);

        // Test GameHubActivityRelationManager
        Livewire::test(GameHubActivityRelationManager::class, ['ownerRecord' => $customer, 'pageClass' => ViewAppUser::class])
            ->assertOk()
            ->assertCanSeeTableRecords([$stat]);

        // Test RewardGrantsRelationManager
        Livewire::test(RewardGrantsRelationManager::class, ['ownerRecord' => $customer, 'pageClass' => ViewAppUser::class])
            ->assertOk()
            ->assertCanSeeTableRecords([$reward]);

        // Test SupportThreadsRelationManager
        Livewire::test(SupportThreadsRelationManager::class, ['ownerRecord' => $customer, 'pageClass' => ViewAppUser::class])
            ->assertOk()
            ->assertCanSeeTableRecords([$thread]);

        // Test ActivityTimelineRelationManager
        Livewire::test(ActivityTimelineRelationManager::class, ['ownerRecord' => $customer, 'pageClass' => ViewAppUser::class])
            ->assertOk()
            ->assertCanSeeTableRecords([$action]);

        // Test DevicesRelationManager
        Livewire::test(DevicesRelationManager::class, ['ownerRecord' => $customer, 'pageClass' => ViewAppUser::class])
            ->assertOk()
            ->assertCanSeeTableRecords([$device]);
    }

    public function test_toggle_status_action_suspends_and_reactivates_with_audit(): void
    {
        $admin = $this->createStaff('ADMIN');
        $this->actingAs($admin);

        $customer = $this->createCustomer(['status' => 'ACTIVE']);
        $customer->forceFill(['token_version' => 1])->save();

        // 1. Suspend account
        Livewire::test(ViewAppUser::class, ['record' => $customer->getRouteKey()])
            ->callAction('toggleStatus')
            ->assertHasNoActionErrors();

        $customer->refresh();
        $this->assertSame('SUSPENDED', $customer->status);
        $this->assertSame(2, (int) $customer->token_version); // Session invalidated

        $this->assertDatabaseHas('admin_actions', [
            'user_id' => $admin->id,
            'action' => 'user.suspended',
            'subject_type' => 'User',
            'subject_id' => $customer->id,
        ]);

        // 2. Reactivate account
        Livewire::test(ViewAppUser::class, ['record' => $customer->getRouteKey()])
            ->callAction('toggleStatus')
            ->assertHasNoActionErrors();

        $customer->refresh();
        $this->assertSame('ACTIVE', $customer->status);

        $this->assertDatabaseHas('admin_actions', [
            'user_id' => $admin->id,
            'action' => 'user.reactivated',
            'subject_type' => 'User',
            'subject_id' => $customer->id,
        ]);
    }

    public function test_toggle_verification_action_updates_blue_tick_and_audit(): void
    {
        $admin = $this->createStaff('ADMIN');
        $this->actingAs($admin);

        $customer = $this->createCustomer(['is_verified' => false]);

        // 1. Grant verification
        Livewire::test(ViewAppUser::class, ['record' => $customer->getRouteKey()])
            ->callAction('toggleVerification')
            ->assertHasNoActionErrors();

        $customer->refresh();
        $this->assertTrue((bool) $customer->is_verified);
        $this->assertNotNull($customer->verified_at);

        $this->assertDatabaseHas('admin_actions', [
            'user_id' => $admin->id,
            'action' => 'user.verified',
            'subject_type' => 'User',
            'subject_id' => $customer->id,
        ]);

        // 2. Remove verification
        Livewire::test(ViewAppUser::class, ['record' => $customer->getRouteKey()])
            ->callAction('toggleVerification')
            ->assertHasNoActionErrors();

        $customer->refresh();
        $this->assertFalse((bool) $customer->is_verified);
        $this->assertNull($customer->verified_at);

        $this->assertDatabaseHas('admin_actions', [
            'user_id' => $admin->id,
            'action' => 'user.unverified',
            'subject_type' => 'User',
            'subject_id' => $customer->id,
        ]);
    }

    public function test_revoke_all_sessions_action_increments_token_version_and_audits(): void
    {
        $admin = $this->createStaff('ADMIN');
        $this->actingAs($admin);

        $customer = $this->createCustomer();
        $customer->forceFill(['token_version' => 3])->save();

        Livewire::test(ViewAppUser::class, ['record' => $customer->getRouteKey()])
            ->callAction('revokeSessions')
            ->assertHasNoActionErrors();

        $customer->refresh();
        $this->assertSame(4, (int) $customer->token_version);

        $this->assertDatabaseHas('admin_actions', [
            'user_id' => $admin->id,
            'action' => 'user.sessions_revoked',
            'subject_type' => 'User',
            'subject_id' => $customer->id,
        ]);
    }
}
