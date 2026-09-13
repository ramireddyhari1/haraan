<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Pages\CommandCenter;
use App\Models\Booking;
use App\Models\Event;
use App\Models\Payout;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CommandCenterTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@haraan.test',
            'password' => bcrypt('secret123'),
            'role' => 'ADMIN',
            'status' => 'active',
        ]);
    }

    private function partner(): User
    {
        return User::create([
            'name' => 'Partner Owner',
            'email' => 'partner@haraan.test',
            'password' => bcrypt('secret123'),
            'role' => 'PARTNER',
            'status' => 'active',
        ]);
    }

    public function test_unauthorized_user_cannot_access_command_center(): void
    {
        $this->actingAs($this->partner());
        Filament::setCurrentPanel(Filament::getPanel('control'));

        $this->assertFalse(CommandCenter::canAccess());
    }

    public function test_superadmin_can_access_and_render_command_center(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('control'));

        $this->assertTrue(CommandCenter::canAccess());

        Livewire::test(CommandCenter::class)
            ->assertOk()
            ->assertSee('Command Center')
            ->assertSee('Gross Platform Volume (GMV)')
            ->assertSee('Capital & Settlements')
            ->assertSee('Operational Radar');
    }

    public function test_it_switches_time_ranges(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('control'));

        Livewire::test(CommandCenter::class)
            ->assertSet('range', '30d')
            ->call('setRange', '7d')
            ->assertSet('range', '7d')
            ->call('setRange', 'all')
            ->assertSet('range', 'all');
    }

    public function test_it_rebuilds_on_content_updated_event(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('control'));

        Livewire::test(CommandCenter::class)
            ->dispatch('haraan-content-updated')
            ->assertOk();
    }

    public function test_executive_command_center_renders_c_suite_pillars_and_verticals(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('control'));

        Livewire::test(CommandCenter::class)
            ->assertOk()
            ->assertSee('Gross Revenue')
            ->assertSee('Net Profit')
            ->assertSee('Growth Velocity')
            ->assertSee("Run-Rate")
            ->assertSee('Live Active Users')
            ->assertSee('AI Health Score')
            ->assertSee('Events & Experiences')
            ->assertSee('Sports Venue Booking')
            ->assertSee('SaaS & Subscriptions')
            ->assertSee('Multi-Stream Revenue Trajectory')
            ->assertSee('Conversion Funnel')
            ->assertSee('Live Operations Stream')
            ->assertSee('Regional Hub Velocity')
            ->assertSee('Enterprise Infrastructure SLA');
    }

    public function test_executive_command_center_search_and_stream_filter(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('control'));

        Livewire::test(CommandCenter::class)
            ->set('searchQuery', 'Turf')
            ->assertSee('Indiranagar Prime Turf Arena')
            ->call('setStreamFilter', 'venues')
            ->assertSet('activeStreamFilter', 'venues')
            ->call('applyAiAction', 'surge_pricing')
            ->assertOk();
    }
}
