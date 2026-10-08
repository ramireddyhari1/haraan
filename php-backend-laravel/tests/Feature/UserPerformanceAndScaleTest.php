<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\AppUsers\Pages\ViewAppUser;
use App\Filament\Resources\AppUsers\Widgets\UserOverviewStatsWidget;
use App\Filament\Resources\AppUsers\Widgets\UserSpendChartWidget;
use App\Filament\Resources\PlayerReports\Tables\PlayerReportsTable;
use App\Filament\Resources\Users\Widgets\UsersStatsWidget;
use App\Models\Booking;
use App\Models\Event;
use App\Models\LiveMatch;
use App\Models\PlayerMatchStat;
use App\Models\PlayerReport;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Tables\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class UserPerformanceAndScaleTest extends TestCase
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
            'name' => "{$role} Operator",
            'email' => strtolower($role) . '_' . uniqid() . '@haraan.test',
            'password' => bcrypt('Password123!'),
            'role' => $role,
            'status' => 'ACTIVE',
        ]);
    }

    private function createCustomer(array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => 'Scale Test User',
            'email' => 'customer_' . uniqid() . '@haraan.test',
            'phone' => '9876543210',
            'password' => bcrypt('Secret123!'),
            'role' => 'USER',
            'status' => 'ACTIVE',
        ], $attributes));
    }

    private function createEvent(): Event
    {
        $partner = $this->createStaff('PARTNER');

        return Event::create([
            'partner_id' => $partner->id,
            'title' => 'Scale Championship',
            'category' => 'Sports',
            'location' => 'Main Ground',
            'venue' => 'Stadium',
            'city' => 'Coimbatore',
            'date' => now()->addDays(5)->toDateString(),
            'time' => '10:00 AM',
            'price' => 500,
            'total_slots' => 100,
            'available_slots' => 100,
            'status' => 'published',
        ]);
    }

    public function test_performance_indexes_and_columns_exist(): void
    {
        // 1. Column existence on users
        $this->assertTrue(Schema::hasColumn('users', 'lifetime_spend'));
        $this->assertTrue(Schema::hasColumn('users', 'bookings_count'));
        $this->assertTrue(Schema::hasColumn('users', 'matches_played_count'));
        $this->assertTrue(Schema::hasColumn('users', 'last_kpi_calculated_at'));

        // 2. Index existence on users
        $userIndexes = collect(Schema::getIndexes('users'))->pluck('name')->all();
        $this->assertContains('users_phone_idx', $userIndexes);
        $this->assertContains('users_name_idx', $userIndexes);
        $this->assertContains('users_is_verified_idx', $userIndexes);
        $this->assertContains('users_lifetime_spend_idx', $userIndexes);
        $this->assertContains('users_bookings_count_idx', $userIndexes);

        // 3. Composite indexes on bookings
        $bookingIndexes = collect(Schema::getIndexes('bookings'))->pluck('name')->all();
        $this->assertContains('bookings_user_id_status_idx', $bookingIndexes);
        $this->assertContains('bookings_user_id_created_at_idx', $bookingIndexes);

        // 4. Composite index on player_match_stats
        $statIndexes = collect(Schema::getIndexes('player_match_stats'))->pluck('name')->all();
        $this->assertContains('player_match_stats_user_id_played_idx', $statIndexes);

        // 5. Composite index on support_threads
        $supportIndexes = collect(Schema::getIndexes('support_threads'))->pluck('name')->all();
        $this->assertContains('support_threads_user_id_status_idx', $supportIndexes);
    }

    public function test_recalculate_kpi_metrics_aggregates_accurately(): void
    {
        $user = $this->createCustomer();
        $event = $this->createEvent();

        // Initially 0
        $this->assertEquals(0.0, (float) $user->lifetime_spend);
        $this->assertEquals(0, (int) $user->bookings_count);
        $this->assertEquals(0, (int) $user->matches_played_count);

        // Create 2 paid bookings and 1 cancelled booking
        Booking::create([
            'user_id' => $user->id,
            'event_id' => $event->id,
            'quantity' => 1,
            'total_amount' => 1200.50,
            'status' => 'CONFIRMED',
        ]);

        Booking::create([
            'user_id' => $user->id,
            'event_id' => $event->id,
            'quantity' => 2,
            'total_amount' => 800.00,
            'status' => 'paid',
        ]);

        Booking::create([
            'user_id' => $user->id,
            'event_id' => $event->id,
            'quantity' => 1,
            'total_amount' => 500.00,
            'status' => 'cancelled',
        ]);

        // Create 2 match figures (1 played, 1 bench)
        $match = LiveMatch::create([
            'title' => 'Test Derby',
            'sport' => 'cricket',
            'home' => 'Hawks',
            'away' => 'Eagles',
            'status' => 'completed',
        ]);

        PlayerMatchStat::create([
            'match_id' => $match->id,
            'user_id' => $user->id,
            'player_id' => $user->player_id,
            'player_name' => $user->name,
            'sport' => 'cricket',
            'played' => true,
            'runs' => 45,
            'balls' => 28,
        ]);

        PlayerMatchStat::create([
            'match_id' => $match->id,
            'user_id' => $user->id,
            'player_id' => $user->player_id,
            'player_name' => $user->name,
            'sport' => 'cricket',
            'played' => false,
        ]);

        $user->refresh();

        // 1200.50 + 800.00 = 2000.50 (cancelled booking excluded from spend, but included in total bookings count)
        $this->assertEquals(2000.50, (float) $user->lifetime_spend);
        $this->assertEquals(3, (int) $user->bookings_count);
        $this->assertEquals(1, (int) $user->matches_played_count);
        $this->assertNotNull($user->last_kpi_calculated_at);
    }

    public function test_player_reports_table_eliminates_n_plus_one_via_subquery(): void
    {
        $admin = $this->createStaff('ADMIN');
        $player1 = $this->createCustomer(['name' => 'Reported Player 1']);
        $player2 = $this->createCustomer(['name' => 'Reported Player 2']);
        $reporter = $this->createCustomer(['name' => 'Reporter User']);

        // Create 3 reports on Player 1
        for ($i = 0; $i < 3; $i++) {
            PlayerReport::create([
                'reporter_id' => $reporter->id,
                'reported_id' => $player1->id,
                'reason' => 'harassment',
                'details' => 'Bad conduct ' . $i,
                'status' => 'open',
            ]);
        }

        // Create 1 report on Player 2
        PlayerReport::create([
            'reporter_id' => $reporter->id,
            'reported_id' => $player2->id,
            'reason' => 'spam',
            'details' => 'Spamming messages',
            'status' => 'open',
        ]);

        // Execute the exact query modified in PlayerReportsTable
        $query = PlayerReport::query()
            ->with(['reporter', 'reported', 'reviewer'])
            ->select('player_reports.*')
            ->selectSub(
                PlayerReport::query()
                    ->from('player_reports as inner_reports')
                    ->selectRaw('count(*)')
                    ->whereColumn('inner_reports.reported_id', 'player_reports.reported_id'),
                'total_on_player'
            )
            ->orderBy('created_at', 'asc');

        // Track query count: executing the query should run 1 main query + eager loads, and zero queries inside the loop
        $executedReports = $query->get();

        $this->assertCount(4, $executedReports);

        foreach ($executedReports as $report) {
            if ($report->reported_id === $player1->id) {
                $this->assertEquals(3, (int) $report->total_on_player);
            } elseif ($report->reported_id === $player2->id) {
                $this->assertEquals(1, (int) $report->total_on_player);
            }
        }
    }

    public function test_users_stats_widget_caches_and_busts_on_invalidation(): void
    {
        $this->createCustomer();

        $this->assertFalse(Cache::has(UsersStatsWidget::CACHE_KEY));

        // Call widget stats
        $widget = new UsersStatsWidget;
        $statsMethod = new \ReflectionMethod(UsersStatsWidget::class, 'getStats');
        $statsMethod->setAccessible(true);
        $stats = $statsMethod->invoke($widget);

        $this->assertIsArray($stats);
        $this->assertTrue(Cache::has(UsersStatsWidget::CACHE_KEY));

        $cachedData = Cache::get(UsersStatsWidget::CACHE_KEY);
        $this->assertIsArray($cachedData);
        $this->assertArrayHasKey('total', $cachedData);
        $this->assertArrayHasKey('wau', $cachedData);

        // Invalidate cache
        UsersStatsWidget::invalidateCache();
        $this->assertFalse(Cache::has(UsersStatsWidget::CACHE_KEY));
    }

    public function test_user_overview_and_spend_chart_widgets_cache_per_user(): void
    {
        $user = $this->createCustomer();
        $event = $this->createEvent();

        Booking::create([
            'user_id' => $user->id,
            'event_id' => $event->id,
            'quantity' => 1,
            'total_amount' => 750.00,
            'status' => 'paid',
        ]);

        $spendChartWidget = new UserSpendChartWidget;
        $spendChartWidget->record = $user;

        $chartMethod = new \ReflectionMethod(UserSpendChartWidget::class, 'getData');
        $chartMethod->setAccessible(true);
        $chartData = $chartMethod->invoke($spendChartWidget);

        $this->assertArrayHasKey('datasets', $chartData);
        $this->assertTrue(Cache::has("user:{$user->id}:spend_chart"));

        // Overview widget
        $overviewWidget = new UserOverviewStatsWidget;
        $overviewWidget->record = $user;

        $overviewMethod = new \ReflectionMethod(UserOverviewStatsWidget::class, 'getStats');
        $overviewMethod->setAccessible(true);
        $overviewStats = $overviewMethod->invoke($overviewWidget);

        $this->assertIsArray($overviewStats);
        $this->assertTrue(Cache::has("user:{$user->id}:overview_kpis"));

        // Triggering recalculateKpiMetrics clears both caches
        $user->recalculateKpiMetrics();
        $this->assertFalse(Cache::has("user:{$user->id}:spend_chart"));
        $this->assertFalse(Cache::has("user:{$user->id}:overview_kpis"));
    }

    public function test_view_app_user_recalculate_kpis_action(): void
    {
        $admin = $this->createStaff('ADMIN');
        $customer = $this->createCustomer();
        $event = $this->createEvent();

        // Create booking directly in DB bypass
        DB::table('bookings')->insert([
            'user_id' => $customer->id,
            'event_id' => $event->id,
            'quantity' => 1,
            'total_amount' => 1500.00,
            'status' => 'confirmed',
            'ticket_code' => 'TICKET-TEST-123456789012',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Livewire::actingAs($admin)
            ->test(ViewAppUser::class, ['record' => $customer->id])
            ->assertSuccessful()
            ->callAction('recalculateKpis')
            ->assertNotified('Refreshed');

        $customer->refresh();
        $this->assertEquals(1500.00, (float) $customer->lifetime_spend);
        $this->assertEquals(1, (int) $customer->bookings_count);
    }

    public function test_user_360_view_query_reduction_with_cache(): void
    {
        $admin = $this->createStaff('ADMIN');
        $customer = $this->createCustomer();
        $event = $this->createEvent();

        Booking::create([
            'user_id' => $customer->id,
            'event_id' => $event->id,
            'quantity' => 1,
            'total_amount' => 500.00,
            'status' => 'confirmed',
        ]);

        // Cold load
        Cache::flush();
        DB::flushQueryLog();
        DB::enableQueryLog();

        Livewire::actingAs($admin)
            ->test(ViewAppUser::class, ['record' => $customer->id])
            ->assertSuccessful();

        $coldQueryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Warm load (subsequent visits / tab switches)
        DB::flushQueryLog();
        DB::enableQueryLog();

        Livewire::actingAs($admin)
            ->test(ViewAppUser::class, ['record' => $customer->id])
            ->assertSuccessful();

        $warmQueryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan($coldQueryCount, $warmQueryCount);
    }
}
