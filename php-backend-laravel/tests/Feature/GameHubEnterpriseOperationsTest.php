<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Clusters\GameHub\Pages\GameHubIntegrations;
use App\Filament\Clusters\GameHub\Pages\GameHubMembers;
use App\Filament\Clusters\GameHub\Pages\GameHubNotifications;
use App\Filament\Clusters\GameHub\Pages\GameHubOverview;
use App\Filament\Clusters\GameHub\Pages\GameHubPricingRules;
use App\Filament\Clusters\GameHub\Pages\GameHubStaff;
use App\Filament\Clusters\GameHub\Pages\GameHubSupport;
use App\Filament\Clusters\GameHub\Pages\GameHubTournaments;
use App\Filament\Clusters\GameHub\Pages\Reports;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GameHubEnterpriseOperationsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Super Admin',
            'email' => 'admin@haraan.test',
            'password' => bcrypt('secret123'),
            'role' => 'ADMIN',
            'status' => 'active',
        ]);
    }

    public function test_gamehub_overview_command_center_renders_telemetry(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('control'));

        Livewire::test(GameHubOverview::class)
            ->assertOk()
            ->assertSee('Game Hub Operations Command')
            ->assertSee('AI Pulse Health')
            ->assertSee('Turf & Court Gross Revenue')
            ->assertSee('Real-Time Turf Occupancy')
            ->assertSee('Active Venues & Fleet')
            ->assertSee('Hourly Occupancy & Demand Velocity', escape: false)
            ->assertSee('Metro Regional Operations');
    }

    public function test_reports_executive_export_center_renders(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('control'));

        Livewire::test(Reports::class)
            ->assertOk()
            ->assertSee('Executive Analytics & Export Center')
            ->assertSee('Audited Gross Revenue')
            ->assertSee('Standardized Reporting Ledgers')
            ->assertSee('Executive Revenue & Settlement Ledger')
            ->assertSee('Active Automated Dispatch Rules');
    }

    public function test_extended_enterprise_cluster_pages_render(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('control'));

        Livewire::test(GameHubMembers::class)
            ->assertOk()
            ->assertSee('Player Community Command')
            ->assertSee('Player Tier Segmentation')
            ->assertSee('Top Active Fleet Members');

        Livewire::test(GameHubStaff::class)
            ->assertOk()
            ->assertSee('Field Operations Command')
            ->assertSee('Active Duty Deployment')
            ->assertSee('Live Staff Assignment Roster');

        Livewire::test(GameHubPricingRules::class)
            ->assertOk()
            ->assertSee('Surge Yield Engine')
            ->assertSee('Active Pricing & Surge Rules Matrix', escape: false);

        Livewire::test(GameHubTournaments::class)
            ->assertOk()
            ->assertSee('Championship League Hub')
            ->assertSee('Active Tournaments & League Brackets', escape: false);

        Livewire::test(GameHubNotifications::class)
            ->assertOk()
            ->assertSee('Automated Operations Notifications')
            ->assertSee('Communication Rails')
            ->assertSee('Automated Trigger Templates');

        Livewire::test(GameHubSupport::class)
            ->assertOk()
            ->assertSee('Dispute Center')
            ->assertSee('Inquiry Categories')
            ->assertSee('Active Operational Cases');

        Livewire::test(GameHubIntegrations::class)
            ->assertOk()
            ->assertSee('API Integrations Command')
            ->assertSee('Connected Units')
            ->assertSee('Physical IoT Hardware Fleet');
    }
}
