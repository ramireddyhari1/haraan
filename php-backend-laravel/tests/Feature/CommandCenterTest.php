<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Pages\CommandCenter;
use App\Models\Booking;
use App\Models\Event;
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

    private function signIn(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('control'));
    }

    private function event(string $title): Event
    {
        return Event::forceCreate([
            'title' => $title,
            'partner_id' => User::where('role', 'PARTNER')->value('id') ?? $this->partner()->id,
            'date' => now()->addDays(5)->toDateString(),
            'time' => '19:00',
            'price' => 500,
            'total_slots' => 100,
            'available_slots' => 98,
            'city' => 'Hyderabad',
            'status' => 'published',
        ]);
    }

    public function test_unauthorized_user_cannot_access_command_center(): void
    {
        $this->actingAs($this->partner());
        Filament::setCurrentPanel(Filament::getPanel('control'));

        $this->assertFalse(CommandCenter::canAccess());
    }

    public function test_empty_platform_renders_honest_empty_states(): void
    {
        $this->signIn();

        Livewire::test(CommandCenter::class)
            ->assertOk()
            ->assertSee('Paid bookings')
            ->assertSee('₹0')
            ->assertSee('No paid bookings in this window.')
            ->assertSee('No bookings have been made yet.')
            ->assertSee('All clear')
            // None of the old sample content may leak back in.
            ->assertDontSee('Bangalore Open Air')
            ->assertDontSee('Turfpark Indiranagar')
            ->assertDontSee('Aditya Verma')
            ->assertDontSee('99.94%');
    }

    public function test_paid_booking_shows_in_totals_feed_and_cities(): void
    {
        $this->signIn();
        $event = $this->event('Monsoon Jazz Night');

        $buyer = User::create([
            'name' => 'Asha Buyer',
            'email' => 'asha@haraan.test',
            'password' => bcrypt('secret123'),
            'role' => 'USER',
            'status' => 'active',
        ]);

        Booking::forceCreate([
            'user_id' => $buyer->id,
            'event_id' => $event->id,
            'booking_type' => 'event',
            'status' => 'CONFIRMED',
            'quantity' => 2,
            'total_amount' => 123456,
        ]);

        Livewire::test(CommandCenter::class)
            ->assertOk()
            ->assertSee('₹1,23,456')
            ->assertSee('Monsoon Jazz Night')
            ->assertSee('Asha Buyer')
            ->assertSee('Hyderabad')
            ->assertSet('events.tickets', 2)
            ->assertSet('events.upcoming', 1)
            ->assertSet('hero.orders', 1);
    }

    public function test_it_switches_time_ranges(): void
    {
        $this->signIn();

        Livewire::test(CommandCenter::class)
            ->assertSet('range', '30d')
            ->call('setRange', 'today')
            ->assertSet('range', 'today')
            ->assertSet('series.unit', 'hour')
            ->call('setRange', 'all')
            ->assertSet('series.unit', 'month')
            ->call('setRange', 'nonsense')
            ->assertSet('range', '30d');
    }

    public function test_it_rebuilds_on_content_updated_event(): void
    {
        $this->signIn();

        Livewire::test(CommandCenter::class)
            ->dispatch('haraan-content-updated')
            ->assertOk();
    }

    public function test_search_finds_real_records_only(): void
    {
        $this->signIn();
        $this->event('Monsoon Jazz Night');

        Livewire::test(CommandCenter::class)
            ->set('searchQuery', 'jazz')
            ->assertSee('Monsoon Jazz Night')
            ->set('searchQuery', 'Turf')
            ->assertSee('Nothing matches');
    }

    public function test_attention_counts_open_support(): void
    {
        $this->signIn();

        \App\Models\SupportThread::forceCreate([
            'user_id' => User::first()->id,
            'subject' => 'Refund please',
            'status' => 'open',
        ]);

        $component = Livewire::test(CommandCenter::class);
        $support = collect($component->get('radar'))->firstWhere('title', 'Support waiting');

        $this->assertSame(1, $support['count']);
        $component->assertDontSee('All clear');
    }
}
