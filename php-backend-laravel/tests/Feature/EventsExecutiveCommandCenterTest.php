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
use App\Models\Booking;
use App\Models\Event;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The events console pages: they must render, and every figure on them must come from
 * the database. Each test also pins that the sample content these pages used to show
 * (made-up cities, buyers, "AI" cards, a fake no-show rate) cannot creep back.
 */
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

    private function signIn(): User
    {
        $u = $this->admin();
        $this->actingAs($u);
        Filament::setCurrentPanel(Filament::getPanel('control'));

        return $u;
    }

    private function partner(): User
    {
        return User::create([
            'name' => 'Host Co',
            'email' => 'host@haraan.test',
            'password' => bcrypt('secret123'),
            'role' => 'PARTNER',
            'status' => 'active',
        ]);
    }

    private function event(array $attrs): Event
    {
        return Event::forceCreate($attrs + [
            'title' => 'Sample',
            'partner_id' => $this->partner()->id,
            'time' => '19:00',
            'price' => 500,
            'total_slots' => 100,
            'available_slots' => 100,
            'status' => 'published',
        ]);
    }

    private function paidBooking(Event $e, int $qty, float $amount, int $scanned = 0): void
    {
        Booking::forceCreate([
            'user_id' => User::first()->id,
            'event_id' => $e->id,
            'booking_type' => 'event',
            'status' => 'confirmed',
            'quantity' => $qty,
            'total_amount' => $amount,
            'checked_in_count' => $scanned,
        ]);
    }

    public function test_events_overview_shows_real_takings_and_seat_rows(): void
    {
        $this->signIn();
        $e = $this->event(['title' => 'Rooftop Ghazal Evening', 'date' => now()->addDays(3)->toDateString(), 'city' => 'Pune', 'category' => 'Music', 'available_slots' => 60]);
        $this->paidBooking($e, 4, 2000);

        Livewire::test(EventsOverview::class)
            ->assertOk()
            ->assertSee('Ticket takings')
            ->assertSee('₹2,000')
            ->assertSee('Rooftop Ghazal Evening')
            ->assertSee('40%')            // 40 of 100 seats gone
            ->assertSee('Pune')
            ->assertSee('Music')
            ->assertSet('hero.tickets', 4)
            ->call('setRange', '7d')
            ->assertSet('range', '7d')
            ->assertDontSee('Bangalore Open Air')
            ->assertDontSee('Sneha Rao')
            ->assertDontSee('Palace Grounds');
    }

    public function test_events_overview_empty_states(): void
    {
        $this->signIn();

        Livewire::test(EventsOverview::class)
            ->assertOk()
            ->assertSee('Nothing is on sale.')
            ->assertSee('No ticket orders yet.')
            ->assertSee('₹0');
    }

    public function test_events_list_header_is_real(): void
    {
        $this->signIn();

        Livewire::test(EventsListStatsWidget::class)
            ->assertOk()
            ->assertSee('All events')
            ->assertSee('Ticket takings')
            ->assertSee('₹0')
            ->assertDontSee('18,42,000')
            ->assertDontSee('Health Score');

        Livewire::test(ListEvents::class)->assertOk();
    }

    public function test_bookings_header_widget_renders(): void
    {
        $this->signIn();

        Livewire::test(BookingsExecutiveHeroWidget::class)
            ->assertOk()
            ->assertSee('Paid bookings')
            ->assertSee('Average order');

        Livewire::test(ListBookings::class)->assertOk();
    }

    public function test_ticket_checkin_shows_real_gate_figures(): void
    {
        $this->signIn();
        $e = $this->event(['title' => 'Gate Night', 'date' => now()->toDateString()]);
        $this->paidBooking($e, 5, 2500, 2);

        Livewire::test(TicketCheckIn::class)
            ->assertOk()
            ->assertSee('Gate scanner')
            ->assertSee("Inside · today's events")
            ->assertSee('Let in by you')
            ->assertSee('Start camera')
            ->assertDontSee('No-Show Prediction')
            ->assertDontSee('142 / hr');

        $this->assertSame(['inside' => 2, 'expected' => 5], array_intersect_key(
            Livewire::test(TicketCheckIn::class)->instance()->getGateFigures(),
            ['inside' => 1, 'expected' => 1],
        ));
    }

    public function test_event_history_uses_real_past_events(): void
    {
        $this->signIn();
        $past = $this->event(['title' => 'Old Gig', 'date' => now()->subDays(20)->toDateString(), 'city' => 'Kochi', 'available_slots' => 50]);
        $this->paidBooking($past, 50, 25000, 40);

        Livewire::test(EventHistory::class)
            ->assertOk()
            ->assertSee('Takings from events already held')
            ->assertSee('₹25,000')
            ->assertSee('Kochi')
            ->assertSee('80%')            // 40 of 50 tickets scanned
            ->assertSee('Export Historical CSV')
            ->assertDontSee('Bengaluru')
            ->assertDontSee('YoY');
    }
}
