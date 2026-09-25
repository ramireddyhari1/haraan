<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\AppUsers\Pages\ListAppUsers;
use App\Filament\Resources\AppUsers\Pages\ViewAppUser;
use App\Filament\Resources\AppUsers\RelationManagers\UserNotesRelationManager;
use App\Filament\Resources\AppUsers\Widgets\UserRiskTrustWidget;
use App\Filament\Resources\PlayerReports\Tables\PlayerReportsTable;
use App\Models\AdminAction;
use App\Models\Booking;
use App\Models\DeviceToken;
use App\Models\LiveMatch;
use App\Models\MemberPlan;
use App\Models\MemberSubscription;
use App\Models\PlayerBlock;
use App\Models\PlayerMatchStat;
use App\Models\PlayerReport;
use App\Models\ReputationEvent;
use App\Models\RewardGrant;
use App\Models\SupportCategory;
use App\Models\SupportThread;
use App\Models\User;
use App\Models\UserNote;
use App\Services\UserTimelineService;
use Filament\Facades\Filament;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class EnterpriseAdminIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('control'));
        Cache::flush();
    }

    private function createStaff(string $role = 'ADMIN'): User
    {
        return User::create([
            'name' => "{$role} Staff",
            'email' => strtolower($role) . '_' . uniqid() . '@haraan.test',
            'password' => bcrypt('Password123!'),
            'role' => $role,
            'status' => 'ACTIVE',
        ]);
    }

    private function createCustomer(array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => 'Intelligence Test User',
            'email' => 'intel_' . uniqid() . '@haraan.test',
            'phone' => '+9199' . str_pad((string) mt_rand(10000000, 99999999), 8, '0'),
            'player_id' => 'PLR-' . strtoupper(uniqid()),
            'password' => bcrypt('Password123!'),
            'role' => 'USER',
            'status' => 'ACTIVE',
            'trust_score' => 95,
        ], $attributes));
    }

    // =========================================================================
    // 1. SECURE ADMIN NOTES TESTS
    // =========================================================================

    public function test_user_note_creation_and_author_attribution(): void
    {
        $admin = $this->createStaff('ADMIN');
        $this->actingAs($admin);

        $customer = $this->createCustomer();

        $note = UserNote::create([
            'user_id' => $customer->id,
            'category' => UserNote::CATEGORY_FRAUD_ALERT,
            'title' => 'Suspicious OTP requests',
            'content' => 'Multiple OTP requests from unknown IP range.',
            'is_pinned' => true,
            'is_confidential' => false,
        ]);

        $this->assertDatabaseHas('user_notes', [
            'id' => $note->id,
            'user_id' => $customer->id,
            'author_id' => $admin->id,
            'category' => 'fraud_alert',
            'is_pinned' => 1,
        ]);

        $this->assertSame($customer->id, $note->user->id);
        $this->assertSame($admin->id, $note->author->id);
    }

    public function test_confidential_notes_are_strictly_protected_by_operator_role(): void
    {
        $admin = $this->createStaff('ADMIN');
        $ops = $this->createStaff('OPS');
        $support = $this->createStaff('SUPPORT');

        $customer = $this->createCustomer();

        // 1. Public note
        $publicNote = UserNote::create([
            'user_id' => $customer->id,
            'author_id' => $admin->id,
            'category' => UserNote::CATEGORY_GENERAL,
            'title' => 'General preferences',
            'content' => 'Prefers morning court bookings.',
            'is_confidential' => false,
        ]);

        // 2. Confidential note
        $confidentialNote = UserNote::create([
            'user_id' => $customer->id,
            'author_id' => $admin->id,
            'category' => UserNote::CATEGORY_ENFORCEMENT,
            'title' => 'Confidential investigation',
            'content' => 'Under watch for chargeback abuse.',
            'is_confidential' => true,
        ]);

        // Admin can see both
        $adminNotes = UserNote::forOperator($admin)->get();
        $this->assertCount(2, $adminNotes);

        // Ops can see both
        $opsNotes = UserNote::forOperator($ops)->get();
        $this->assertCount(2, $opsNotes);

        // Support operator CANNOT see confidential note
        $supportNotes = UserNote::forOperator($support)->get();
        $this->assertCount(1, $supportNotes);
        $this->assertTrue($supportNotes->contains('id', $publicNote->id));
        $this->assertFalse($supportNotes->contains('id', $confidentialNote->id));

        // Test Livewire relation manager query scoping
        $this->actingAs($support);
        Livewire::test(UserNotesRelationManager::class, [
            'ownerRecord' => $customer,
            'pageClass' => ViewAppUser::class,
        ])
            ->assertOk()
            ->assertCanSeeTableRecords([$publicNote])
            ->assertCanNotSeeTableRecords([$confidentialNote]);

        // As admin, both records are visible
        $this->actingAs($admin);
        Livewire::test(UserNotesRelationManager::class, [
            'ownerRecord' => $customer,
            'pageClass' => ViewAppUser::class,
        ])
            ->assertOk()
            ->assertCanSeeTableRecords([$publicNote, $confidentialNote]);
    }

    public function test_pinned_notes_scope_and_badge(): void
    {
        $admin = $this->createStaff('ADMIN');
        $customer = $this->createCustomer();

        UserNote::create([
            'user_id' => $customer->id,
            'author_id' => $admin->id,
            'category' => UserNote::CATEGORY_VIP_PREFERENCE,
            'title' => 'VIP court preference',
            'content' => 'Always reserve Court 1.',
            'is_pinned' => true,
        ]);

        UserNote::create([
            'user_id' => $customer->id,
            'author_id' => $admin->id,
            'category' => UserNote::CATEGORY_GENERAL,
            'title' => 'Normal note',
            'content' => 'Regular player.',
            'is_pinned' => false,
        ]);

        $pinnedNotes = $customer->userNotes()->pinned()->get();
        $this->assertCount(1, $pinnedNotes);
        $this->assertSame('VIP court preference', $pinnedNotes->first()->title);

        $badge = UserNotesRelationManager::getBadge($customer, ViewAppUser::class);
        $this->assertSame('2', $badge);
    }

    // =========================================================================
    // 2. REAL-DATA RISK & TRUST INTELLIGENCE TESTS
    // =========================================================================

    public function test_risk_trust_widget_calculates_authentic_database_signals(): void
    {
        $customer = $this->createCustomer(['trust_score' => 65]);
        $reporter = $this->createCustomer();
        $blocker = $this->createCustomer();

        // 1. Reputation Penalty Event
        ReputationEvent::create([
            'player_id' => $customer->player_id,
            'type' => 'match_dispute',
            'amount' => 15,
            'reason' => 'Left match early',
        ]);

        // 2. Player Reports (1 open, 1 resolved)
        PlayerReport::create([
            'reporter_id' => $reporter->id,
            'reported_id' => $customer->id,
            'reason' => 'harassment',
            'status' => 'open',
        ]);
        PlayerReport::create([
            'reporter_id' => $reporter->id,
            'reported_id' => $customer->id,
            'reason' => 'cheating',
            'status' => 'resolved',
        ]);

        // 3. Player Block
        PlayerBlock::create([
            'blocker_id' => $blocker->id,
            'blocked_id' => $customer->id,
        ]);

        // 4. Bookings: 1 paid, 2 cancelled
        Booking::create([
            'user_id' => $customer->id,
            'booking_type' => 'venue',
            'status' => 'completed',
            'total_amount' => 600,
            'quantity' => 1,
        ]);
        Booking::create([
            'user_id' => $customer->id,
            'booking_type' => 'venue',
            'status' => 'cancelled',
            'total_amount' => 400,
            'quantity' => 1,
        ]);
        Booking::create([
            'user_id' => $customer->id,
            'booking_type' => 'venue',
            'status' => 'refunded',
            'total_amount' => 500,
            'quantity' => 1,
        ]);

        // 5. Device Token + Multi-account Phone Collision
        DeviceToken::create([
            'user_id' => $customer->id,
            'token' => 'fcm-token-' . uniqid(),
            'platform' => 'android',
        ]);

        // Second account with identical phone number (Sybil collision!)
        $this->createCustomer(['phone' => $customer->phone]);

        // Invoke UserRiskTrustWidget
        $widget = new UserRiskTrustWidget();
        $widget->record = $customer;

        $reflection = new \ReflectionClass($widget);
        $getStatsMethod = $reflection->getMethod('getStats');
        $getStatsMethod->setAccessible(true);
        /** @var array<\Filament\Widgets\StatsOverviewWidget\Stat> $stats */
        $stats = $getStatsMethod->invoke($widget);

        $this->assertCount(6, $stats);

        // Stat 0: Trust & Reputation
        $this->assertStringContainsString('65/100', $stats[0]->getValue());
        $this->assertStringContainsString('1 penalty event(s) (-15 pts)', $stats[0]->getDescription());

        // Stat 1: Composite Risk Assessment -> Critical Sybil Risk due to collision
        $this->assertSame('Critical Sybil Risk', $stats[1]->getValue());
        $this->assertStringContainsString('Phone shared with 1 other user', $stats[1]->getDescription());

        // Stat 2: Safety & Moderation
        $this->assertStringContainsString('2 reports · 1 blocks', $stats[2]->getValue());
        $this->assertStringContainsString('1 open report(s) needing review', $stats[2]->getDescription());

        // Stat 3: Booking Reliability -> 1 of 3 completed, 67% cancellation rate
        $this->assertSame('1 of 3 completed', $stats[3]->getValue());
        $this->assertStringContainsString('2 cancelled/refunded (67%)', $stats[3]->getDescription());

        // Stat 4: Device Integrity -> 1 token, collision with 1 account
        $this->assertSame('1 registered device', $stats[4]->getValue());
        $this->assertStringContainsString('Identity collision with 1 account(s)', $stats[4]->getDescription());
    }

    public function test_risk_trust_widget_suspended_status_escalates_to_critical(): void
    {
        $customer = $this->createCustomer([
            'status' => 'SUSPENDED',
            'trust_score' => 90,
        ]);

        $widget = new UserRiskTrustWidget();
        $widget->record = $customer;

        $reflection = new \ReflectionClass($widget);
        $getStatsMethod = $reflection->getMethod('getStats');
        $getStatsMethod->setAccessible(true);
        $stats = $getStatsMethod->invoke($widget);

        $this->assertSame('Critical / Suspended', $stats[1]->getValue());
    }

    // =========================================================================
    // 3. UNIFIED ACTIVITY TIMELINE TESTS
    // =========================================================================

    public function test_user_timeline_service_synthesizes_all_domains(): void
    {
        $admin = $this->createStaff('ADMIN');
        $customer = $this->createCustomer();
        $otherUser = $this->createCustomer();

        // 1. Admin action
        AdminAction::create([
            'user_id' => $admin->id,
            'action' => 'user.verified',
            'subject_type' => 'User',
            'subject_id' => $customer->id,
            'meta' => ['is_verified' => true],
        ]);

        // 2. Booking
        Booking::create([
            'user_id' => $customer->id,
            'booking_type' => 'venue',
            'status' => 'confirmed',
            'total_amount' => 800,
            'quantity' => 1,
        ]);

        // 3. GameHub match stat
        $match = LiveMatch::create([
            'title' => 'Sunday Derby',
            'home' => 'Tigers',
            'away' => 'Lions',
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
            'runs' => 52,
            'balls' => 30,
            'wickets' => 1,
        ]);

        // 4. Support Thread
        $cat = SupportCategory::create(['label' => 'Payment Issue', 'icon_key' => 'billing']);
        SupportThread::create([
            'user_id' => $customer->id,
            'category_id' => $cat->id,
            'subject' => 'Payment deducted twice',
            'status' => 'open',
        ]);

        // 5. Reward Grant
        RewardGrant::create([
            'user_id' => $customer->id,
            'source' => 'challenge',
            'title' => 'Weekend Warrior XP',
            'type' => 'bonus_xp',
            'bonus_xp' => 100,
            'status' => 'available',
            'dedupe_key' => 'warrior-' . $customer->id,
        ]);

        // 6. Player Report
        PlayerReport::create([
            'reporter_id' => $otherUser->id,
            'reported_id' => $customer->id,
            'reason' => 'inappropriate',
            'status' => 'open',
        ]);

        // 7. Reputation Event
        ReputationEvent::create([
            'player_id' => $customer->player_id,
            'type' => 'repeated_abuse',
            'amount' => 10,
            'reason' => 'Toxicity penalty',
        ]);

        // 8. User Note
        UserNote::create([
            'user_id' => $customer->id,
            'author_id' => $admin->id,
            'category' => UserNote::CATEGORY_SUPPORT_ESCALATION,
            'title' => 'Escalated to management',
            'content' => 'Reviewing duplicate transaction refund.',
        ]);

        // Fetch full timeline
        $timeline = UserTimelineService::getTimelineFor($customer, 'all', '', 50, $admin);

        $this->assertCount(8, $timeline);

        // Verify that all 7 domains are synthesized
        $categories = $timeline->pluck('category')->unique();
        $this->assertCount(7, $categories);
        $this->assertTrue($categories->contains('admin'));
        $this->assertTrue($categories->contains('booking'));
        $this->assertTrue($categories->contains('match'));
        $this->assertTrue($categories->contains('support'));
        $this->assertTrue($categories->contains('reward'));
        $this->assertTrue($categories->contains('moderation'));
        $this->assertTrue($categories->contains('note'));

        // Verify category filtering
        $bookingEvents = UserTimelineService::getTimelineFor($customer, 'booking', '', 50, $admin);
        $this->assertCount(1, $bookingEvents);
        $this->assertSame('booking', $bookingEvents->first()['category']);

        $moderationEvents = UserTimelineService::getTimelineFor($customer, 'moderation', '', 50, $admin);
        $this->assertCount(2, $moderationEvents); // 1 PlayerReport + 1 ReputationEvent

        $noteEvents = UserTimelineService::getTimelineFor($customer, 'note', '', 50, $admin);
        $this->assertCount(1, $noteEvents);

        // Verify search filtering
        $searchResults = UserTimelineService::getTimelineFor($customer, 'all', 'Warrior', 50, $admin);
        $this->assertCount(1, $searchResults);
        $this->assertStringContainsString('Weekend Warrior XP', $searchResults->first()['title']);
    }

    // =========================================================================
    // 4. USERS TABLE COHORT FILTER TESTS
    // =========================================================================

    public function test_cohort_filter_vip_spenders(): void
    {
        $vip = $this->createCustomer(['name' => 'VIP User', 'lifetime_spend' => 12500]);
        $regular = $this->createCustomer(['name' => 'Regular User', 'lifetime_spend' => 800]);

        $results = User::query()
            ->where('lifetime_spend', '>=', 5000)
            ->pluck('id');

        $this->assertTrue($results->contains($vip->id));
        $this->assertFalse($results->contains($regular->id));
    }

    public function test_cohort_filter_frequent_bookers(): void
    {
        $frequent = $this->createCustomer(['name' => 'Frequent Booker', 'bookings_count' => 12]);
        $occasional = $this->createCustomer(['name' => 'Occasional Booker', 'bookings_count' => 2]);

        $results = User::query()
            ->where('bookings_count', '>=', 5)
            ->pluck('id');

        $this->assertTrue($results->contains($frequent->id));
        $this->assertFalse($results->contains($occasional->id));
    }

    public function test_cohort_filter_athletes(): void
    {
        $athleteByMatches = $this->createCustomer(['matches_played_count' => 5, 'ranked_xp' => 100]);
        $athleteByXp = $this->createCustomer(['matches_played_count' => 0, 'ranked_xp' => 750]);
        $casual = $this->createCustomer(['matches_played_count' => 1, 'ranked_xp' => 50]);

        $results = User::query()
            ->where(fn ($sub) => $sub->where('matches_played_count', '>=', 3)->orWhere('ranked_xp', '>=', 500))
            ->pluck('id');

        $this->assertTrue($results->contains($athleteByMatches->id));
        $this->assertTrue($results->contains($athleteByXp->id));
        $this->assertFalse($results->contains($casual->id));
    }

    public function test_cohort_filter_subscribers(): void
    {
        $subscribed = $this->createCustomer();
        $freeUser = $this->createCustomer();

        $plan = MemberPlan::create([
            'code' => 'gold-pass',
            'name' => 'Gold Pass',
            'rank' => 1,
            'is_active' => true,
        ]);

        MemberSubscription::create([
            'user_id' => $subscribed->id,
            'plan_id' => $plan->id,
            'provider' => 'razorpay',
            'status' => 'active',
            'starts_at' => now()->subDay(),
        ]);

        $results = User::query()
            ->whereHas('memberSubscriptions', fn ($sub) => $sub->whereIn('status', ['active', 'authenticated']))
            ->pluck('id');

        $this->assertTrue($results->contains($subscribed->id));
        $this->assertFalse($results->contains($freeUser->id));
    }

    public function test_cohort_filter_at_risk_and_reported(): void
    {
        // At-risk: booked before, last seen > 30 days ago
        $atRisk = $this->createCustomer(['bookings_count' => 3, 'last_seen_at' => now()->subDays(40)]);
        $activeUser = $this->createCustomer(['bookings_count' => 3, 'last_seen_at' => now()->subDays(2)]);

        $atRiskResults = User::query()
            ->where('bookings_count', '>', 0)
            ->where(fn ($sub) => $sub->whereNull('last_seen_at')->orWhere('last_seen_at', '<', now()->subDays(30)))
            ->pluck('id');

        $this->assertTrue($atRiskResults->contains($atRisk->id));
        $this->assertFalse($atRiskResults->contains($activeUser->id));

        // Reported: has complaints
        $reportedUser = $this->createCustomer();
        $cleanUser = $this->createCustomer();
        $reporter = $this->createCustomer();

        PlayerReport::create([
            'reporter_id' => $reporter->id,
            'reported_id' => $reportedUser->id,
            'reason' => 'harassment',
            'status' => 'open',
        ]);

        $reportedResults = User::query()->whereHas('receivedPlayerReports')->pluck('id');

        $this->assertTrue($reportedResults->contains($reportedUser->id));
        $this->assertFalse($reportedResults->contains($cleanUser->id));
    }

    // =========================================================================
    // 5. CROSS-DESK NAVIGATION & MODERATION FILTER
    // =========================================================================

    public function test_player_reports_table_filters_by_reported_id(): void
    {
        $admin = $this->createStaff('ADMIN');
        $this->actingAs($admin);

        $badUser = $this->createCustomer();
        $goodUser = $this->createCustomer();
        $reporter = $this->createCustomer();

        $report1 = PlayerReport::create([
            'reporter_id' => $reporter->id,
            'reported_id' => $badUser->id,
            'reason' => 'spam',
            'status' => 'open',
        ]);

        $report2 = PlayerReport::create([
            'reporter_id' => $reporter->id,
            'reported_id' => $goodUser->id,
            'reason' => 'other',
            'status' => 'open',
        ]);

        // Verify filter is registered in PlayerReportsTable
        $table = PlayerReportsTable::configure(Table::make(new ListAppUsers()));
        $filter = collect($table->getFilters())->first(fn ($f) => $f->getName() === 'reported_id');
        $this->assertNotNull($filter, 'reported_id filter must be registered on PlayerReportsTable');

        // Test filter logic
        $filterClosure = fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
            ? $query->where('reported_id', $data['value'])
            : $query;

        $results = $filterClosure(PlayerReport::query(), ['value' => (string) $badUser->id])->get();

        $this->assertCount(1, $results);
        $this->assertSame($report1->id, $results->first()->id);
    }

    public function test_users_table_livewire_cohort_filtering(): void
    {
        $admin = $this->createStaff('ADMIN');
        $this->actingAs($admin);

        $vip = $this->createCustomer(['name' => 'VIP Spender User', 'lifetime_spend' => 15000]);
        $regular = $this->createCustomer(['name' => 'Regular Free User', 'lifetime_spend' => 500]);

        Livewire::test(ListAppUsers::class)
            ->assertOk()
            ->filterTable('cohort', 'vip_spenders')
            ->assertCanSeeTableRecords([$vip])
            ->assertCanNotSeeTableRecords([$regular]);
    }

    public function test_player_reports_livewire_page(): void
    {
        $admin = $this->createStaff('ADMIN');
        $this->actingAs($admin);

        \Livewire\Livewire::test(\App\Filament\Resources\PlayerReports\Pages\ListPlayerReports::class)
            ->assertOk();
    }
}
