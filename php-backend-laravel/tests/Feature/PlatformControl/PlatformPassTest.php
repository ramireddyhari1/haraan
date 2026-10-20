<?php

declare(strict_types=1);

namespace Tests\Feature\PlatformControl;

use App\Filament\Pages\OperationsPage;
use App\Filament\Resources\FeatureFlags\Pages\ListFeatureFlags;
use App\Models\AdminAction;
use App\Models\FeatureFlag;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class PlatformPassTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(): User
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@h.test', 'password' => bcrypt('x'), 'role' => 'ADMIN', 'status' => 'active']);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('control'));

        return $admin;
    }

    public function test_operations_page_is_routed_in_the_control_panel(): void
    {
        $this->signIn();

        $this->assertContains(OperationsPage::class, Filament::getPanel('control')->getPages());
        $this->get(OperationsPage::getUrl())->assertOk()->assertSee('Everything is running normally');
    }

    public function test_feature_flags_say_whether_anything_reads_them(): void
    {
        $this->signIn();
        $read = FeatureFlag::create(['key' => 'server_driven_home', 'name' => 'Home', 'enabled' => true, 'rollout_percentage' => 100]);
        $dead = FeatureFlag::create(['key' => 'new_scorecard', 'name' => 'Scorecard', 'enabled' => true, 'rollout_percentage' => 25]);

        $this->assertNotNull($read->readBy());
        $this->assertNull($dead->readBy());

        Livewire::test(ListFeatureFlags::class)
            ->assertSee('Android home uses Home layout')
            ->assertSee('No effect — nothing reads it');
    }

    public function test_system_timestamps_show_in_business_time_and_wall_times_do_not(): void
    {
        $this->signIn();
        Carbon::setTestNow(Carbon::parse('2026-10-20 01:47:00', 'UTC'));
        AdminAction::create(['user_id' => auth()->id(), 'action' => 'venue.owner_notified', 'meta' => []]);

        // 01:47 UTC is 07:17 in India.
        $this->get(\App\Filament\Resources\AdminActions\AdminActionResource::getUrl('index'))
            ->assertOk()
            ->assertSee('07:17')
            ->assertDontSee('01:47');

        // A time-of-day column holds a wall-clock time and must not move.
        $this->assertNotSame('Asia/Kolkata', TextColumn::make('start_time')->time()->getTimezone());
        $this->assertSame('Asia/Kolkata', TextColumn::make('created_at')->dateTime()->getTimezone());
        $this->assertNotSame('Asia/Kolkata', TextColumn::make('starts_at')->dateTime()->getTimezone());

        Carbon::setTestNow();
    }
}
