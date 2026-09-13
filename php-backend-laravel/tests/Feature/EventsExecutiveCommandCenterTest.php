<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Clusters\Events\Pages\EventHistory;
use App\Filament\Clusters\Events\Pages\EventsOverview;
use App\Filament\Clusters\Events\Pages\TicketCheckIn;
use App\Filament\Resources\Bookings\Pages\ListBookings;
use App\Filament\Resources\Bookings\Widgets\BookingsExecutiveHeroWidget;
use App\Filament\Resources\Events\Pages\ListEvents;
use App\Filament\Resources\Events\Widgets\EventsListStatsWidget;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EventsExecutiveCommandCenterTest extends TestCase
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

    public function test_events_overview_executive_command_center_renders_and_responds(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('control'));

        Livewire::test(EventsOverview::class)
            ->assertOk()
            ->assertSee('Events Command Center')
            ->assertSee('Total Event Gross Revenue')
            ->assertSee('Live Portfolio Stream')
            ->assertSee('Revenue & Attendance Trajectory', escape: false)
            ->assertSee('Live Ticketing Stream')
            ->assertSee('AI Yield & Operations Intelligence', escape: false)
            ->assertSee('Top Regional Markets')
            ->assertSee('Top Performing Venues')
            ->set('range', '7d')
            ->assertSet('range', '7d')
            ->call('applyAiOptimization', 'surge_vip')
            ->assertOk();
    }

    public function test_events_catalog_hero_widget_renders_intelligence_metrics(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('control'));

        Livewire::test(EventsListStatsWidget::class)
            ->assertOk()
            ->assertSee('Events Catalog Intelligence')
            ->assertSee('Portfolio Health')
            ->assertSee('Catalog Revenue')
            ->assertSee('Capacity Utilization');

        Livewire::test(ListEvents::class)
            ->assertOk()
            ->assertSee('Events Catalog Intelligence');
    }

    public function test_bookings_executive_hero_widget_renders_conversion_and_aov(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('control'));

        Livewire::test(BookingsExecutiveHeroWidget::class)
            ->assertOk()
            ->assertSee('Bookings Executive Intelligence')
            ->assertSee('Checkout Conversion')
            ->assertSee('Cancellations', escape: false)
            ->assertSee('Average Order Value (AOV)')
            ->assertSee('Acquisition Channels');

        Livewire::test(ListBookings::class)
            ->assertOk()
            ->assertSee('Bookings Executive Intelligence');
    }

    public function test_ticket_checkin_executive_gate_hero_renders_velocity_and_fraud_metrics(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('control'));

        Livewire::test(TicketCheckIn::class)
            ->assertOk()
            ->assertSee('Haraan Gate Console')
            ->assertSee('Live Gate Velocity')
            ->assertSee('QR Recognition Success')
            ->assertSee('No-Show Prediction')
            ->assertSee('Fraud', escape: false)
            ->assertSee('Start camera')
            ->assertSee('Manual Code Entry')
            ->assertSee('Outdoor Mode');
    }

    public function test_event_history_executive_command_center_renders_trajectory_and_city_breakdown(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('control'));

        Livewire::test(EventHistory::class)
            ->assertOk()
            ->assertSee('Lifetime Event GMV')
            ->assertSee('Lifetime Turnout')
            ->assertSee('Capacity Realized')
            ->assertSee('Quarterly Revenue Trajectory')
            ->assertSee('City-Wise Historical Performance')
            ->assertSee('Bengaluru')
            ->assertSee('Export Historical CSV');
    }
}
