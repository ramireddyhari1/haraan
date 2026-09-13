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
use App\Filament\Resources\LiveMatches\Pages\ListLiveMatches;
use App\Filament\Resources\LiveMatches\Widgets\MatchesExecutiveHeroWidget;
use App\Filament\Resources\Shifts\Pages\ListShiftSessions;
use App\Filament\Resources\Shifts\Widgets\ShiftSessionsExecutiveHeroWidget;
use App\Filament\Resources\VenueBlocks\Pages\ListVenueBlocks;
use App\Filament\Resources\VenueBlocks\Widgets\VenueBlocksExecutiveHeroWidget;
use App\Filament\Resources\VenueBookings\Pages\ListVenueBookings;
use App\Filament\Resources\VenueBookings\Widgets\VenueBookingsExecutiveHeroWidget;
use App\Filament\Resources\Venues\Pages\ListVenues;
use App\Filament\Resources\Venues\Widgets\VenuesExecutiveHeroWidget;
use App\Filament\Resources\Waitlist\Pages\ListWaitlistEntries;
use App\Filament\Resources\Waitlist\Widgets\WaitlistExecutiveHeroWidget;
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

    public function test_venues_executive_hero_and_list_render(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('control'));

        Livewire::test(VenuesExecutiveHeroWidget::class)
            ->assertOk()
            ->assertSee('Venues Fleet & Court Utilization')
            ->assertSee('Fleet Health')
            ->assertSee('Court Utilization Matrix')
            ->assertSee('Dynamic Pricing Engine')
            ->assertSee('7-Day Slot Availability');

        Livewire::test(ListVenues::class)
            ->assertOk()
            ->assertSee('Venues Fleet & Court Utilization');
    }

    public function test_matches_executive_hero_and_list_render(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('control'));

        Livewire::test(MatchesExecutiveHeroWidget::class)
            ->assertOk()
            ->assertSee('Match Operations & Live Scorer Network')
            ->assertSee('Live Now')
            ->assertSee('Referees & Officials')
            ->assertSee('Active Tournaments')
            ->assertSee('Live Match Radar');

        Livewire::test(ListLiveMatches::class)
            ->assertOk()
            ->assertSee('Match Operations & Live Scorer Network');
    }

    public function test_venue_bookings_executive_hero_and_list_render(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('control'));

        Livewire::test(VenueBookingsExecutiveHeroWidget::class)
            ->assertOk()
            ->assertSee('Bookings & Payment Settlement Command')
            ->assertSee('Conversion Rate')
            ->assertSee('Checkout Funnel Analytics')
            ->assertSee('Payment Rails Split')
            ->assertSee('7-Day Revenue Forecast');

        Livewire::test(ListVenueBookings::class)
            ->assertOk()
            ->assertSee('Bookings & Payment Settlement Command');
    }

    public function test_venue_blocks_executive_hero_and_list_render(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('control'));

        Livewire::test(VenueBlocksExecutiveHeroWidget::class)
            ->assertOk()
            ->assertSee('Court Blackouts & Conflict Guard Command')
            ->assertSee('Conflict Engine')
            ->assertSee('Active Blackout Holds')
            ->assertSee('Block Category Breakdown')
            ->assertSee('Fleet Capacity Impact');

        Livewire::test(ListVenueBlocks::class)
            ->assertOk()
            ->assertSee('Court Blackouts & Conflict Guard Command');
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

    public function test_shift_sessions_executive_hero_and_list_render(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('control'));

        Livewire::test(ShiftSessionsExecutiveHeroWidget::class)
            ->assertOk()
            ->assertSee('Front-Desk Shift & Cash Drawer Reconciliation')
            ->assertSee('Physical Cash in Drawers')
            ->assertSee('Counter Payment Rails')
            ->assertSee('Zero Cash Discrepancy')
            ->assertSee('Staff Shift Scorecard');

        Livewire::test(ListShiftSessions::class)
            ->assertOk()
            ->assertSee('Front-Desk Shift & Cash Drawer Reconciliation');
    }

    public function test_waitlist_executive_hero_and_list_render(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('control'));

        Livewire::test(WaitlistExecutiveHeroWidget::class)
            ->assertOk()
            ->assertSee('Auto-Allocation Engine')
            ->assertSee('Active Queue Depth')
            ->assertSee('Priority Dispatch Rules')
            ->assertSee('Expected Wait Time');

        Livewire::test(ListWaitlistEntries::class)
            ->assertOk()
            ->assertSee('Auto-Allocation Engine');
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
