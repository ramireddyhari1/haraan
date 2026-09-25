<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Bookings\Pages\ListBookings;
use App\Filament\Resources\LiveMatches\Pages\ListLiveMatches;
use App\Filament\Resources\MemberSubscriptions\Pages\ListMemberSubscriptions;
use App\Filament\Resources\Rewards\Pages\ManageRewardGrants;
use App\Filament\Resources\SupportThreads\Pages\ListSupportThreads;
use App\Models\Booking;
use App\Models\Event;
use App\Models\LiveMatch;
use App\Models\MemberPlan;
use App\Models\MemberSubscription;
use App\Models\RewardGrant;
use App\Models\SupportThread;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class CrossResourceUserFiltersTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin Operator',
            'email' => 'admin.filters@example.com',
            'password' => Hash::make('secret-password'),
            'role' => 'ADMIN',
            'status' => 'ACTIVE',
        ]);
    }

    private function createUser(string $name, string $email, ?string $playerId = null): User
    {
        return User::create([
            'name' => $name,
            'email' => $email,
            'player_id' => $playerId,
            'password' => Hash::make('secret-password'),
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('control'));
    }

    public function test_bookings_table_filters_by_user_id(): void
    {
        $user1 = $this->createUser('User One', 'user1@example.com');
        $user2 = $this->createUser('User Two', 'user2@example.com');

        $partner = $this->createUser('Partner', 'partner@example.com');
        $partner->role = 'PARTNER';
        $partner->save();

        $event = new Event();
        $event->partner_id = $partner->id;
        $event->title = 'Sample Event';
        $event->category = 'Sports';
        $event->location = 'Stadium';
        $event->venue = 'Arena';
        $event->date = now()->addDays(2);
        $event->time = '18:00';
        $event->price = 100;
        $event->total_slots = 50;
        $event->available_slots = 50;
        $event->status = 'published';
        $event->save();

        $booking1 = Booking::create([
            'user_id' => $user1->id,
            'event_id' => $event->id,
            'quantity' => 1,
            'total_amount' => 100,
            'status' => 'CONFIRMED',
        ]);

        $booking2 = Booking::create([
            'user_id' => $user2->id,
            'event_id' => $event->id,
            'quantity' => 1,
            'total_amount' => 100,
            'status' => 'CONFIRMED',
        ]);

        Livewire::test(ListBookings::class)
            ->filterTable('user_id', $user1->id)
            ->assertCanSeeTableRecords([$booking1])
            ->assertCanNotSeeTableRecords([$booking2]);
    }

    public function test_support_threads_table_filters_by_user_id(): void
    {
        $user1 = $this->createUser('User One', 'user1@example.com');
        $user2 = $this->createUser('User Two', 'user2@example.com');

        $thread1 = SupportThread::create([
            'user_id' => $user1->id,
            'subject' => 'Issue with booking',
            'status' => 'open',
        ]);

        $thread2 = SupportThread::create([
            'user_id' => $user2->id,
            'subject' => 'Payment question',
            'status' => 'open',
        ]);

        Livewire::test(ListSupportThreads::class)
            ->filterTable('user_id', $user1->id)
            ->assertCanSeeTableRecords([$thread1])
            ->assertCanNotSeeTableRecords([$thread2]);
    }

    public function test_reward_grants_table_filters_by_user_id(): void
    {
        $user1 = $this->createUser('User One', 'user1@example.com');
        $user2 = $this->createUser('User Two', 'user2@example.com');

        $grant1 = RewardGrant::create([
            'user_id' => $user1->id,
            'source' => 'rule',
            'title' => 'First Match Bonus',
            'type' => 'bonus_xp',
            'status' => RewardGrant::AVAILABLE,
            'dedupe_key' => 'grant-1',
        ]);

        $grant2 = RewardGrant::create([
            'user_id' => $user2->id,
            'source' => 'rule',
            'title' => 'Second Match Bonus',
            'type' => 'bonus_xp',
            'status' => RewardGrant::AVAILABLE,
            'dedupe_key' => 'grant-2',
        ]);

        Livewire::test(ManageRewardGrants::class)
            ->filterTable('user_id', $user1->id)
            ->assertCanSeeTableRecords([$grant1])
            ->assertCanNotSeeTableRecords([$grant2]);
    }

    public function test_member_subscriptions_table_filters_by_user_id(): void
    {
        $user1 = $this->createUser('User One', 'user1@example.com');
        $user2 = $this->createUser('User Two', 'user2@example.com');

        $plan = MemberPlan::create([
            'code' => 'pro-annual',
            'name' => 'Pro Annual',
            'tagline' => 'Full access',
            'rank' => 1,
            'is_active' => true,
        ]);

        $sub1 = MemberSubscription::create([
            'user_id' => $user1->id,
            'plan_id' => $plan->id,
            'status' => MemberSubscription::STATUS_ACTIVE,
            'provider' => MemberSubscription::PROVIDER_ADMIN,
        ]);

        $sub2 = MemberSubscription::create([
            'user_id' => $user2->id,
            'plan_id' => $plan->id,
            'status' => MemberSubscription::STATUS_ACTIVE,
            'provider' => MemberSubscription::PROVIDER_ADMIN,
        ]);

        Livewire::test(ListMemberSubscriptions::class)
            ->filterTable('user_id', $user1->id)
            ->assertCanSeeTableRecords([$sub1])
            ->assertCanNotSeeTableRecords([$sub2]);
    }

    public function test_live_matches_table_filters_by_creator_or_player(): void
    {
        $user1 = $this->createUser('Player One', 'player1@example.com', 'PL001');
        $user2 = $this->createUser('Player Two', 'player2@example.com', 'PL002');
        $otherHost = $this->createUser('Other Host', 'otherhost@example.com');

        // Match 1: Created by user1
        $match1 = LiveMatch::create([
            'title' => 'Match One Hosted by User 1',
            'user_id' => $user1->id,
            'status' => 'Scheduled',
            'visibility' => LiveMatch::VIS_LOCAL,
            'home' => 'Team A',
            'away' => 'Team B',
        ]);

        // Match 2: Created by someone else, but user1 played (in player_match_stats)
        $match2 = LiveMatch::create([
            'title' => 'Match Two Played by User 1',
            'user_id' => $otherHost->id,
            'status' => 'Live',
            'visibility' => LiveMatch::VIS_LOCAL,
            'home' => 'Team C',
            'away' => 'Team D',
        ]);

        DB::table('player_match_stats')->insert([
            'match_id' => $match2->id,
            'player_id' => 'PL001',
            'player_name' => 'Player One',
            'runs' => 45,
            'balls' => 20,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Match 3: For user2 only
        $match3 = LiveMatch::create([
            'title' => 'Match Three for User 2',
            'user_id' => $user2->id,
            'status' => 'Scheduled',
            'visibility' => LiveMatch::VIS_LOCAL,
            'home' => 'Team E',
            'away' => 'Team F',
        ]);

        Livewire::test(ListLiveMatches::class)
            ->filterTable('user_id', $user1->id)
            ->assertCanSeeTableRecords([$match1, $match2])
            ->assertCanNotSeeTableRecords([$match3]);
    }
}
