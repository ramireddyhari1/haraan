<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Clusters\Events\Pages\TicketCheckIn;
use App\Models\Event;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TicketCheckInTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Gate Admin',
            'email' => 'admin@haraan.test',
            'password' => bcrypt('secret123'),
            'role' => 'ADMIN',
            'status' => 'active',
        ]);
    }

    private function customer(): User
    {
        return User::create([
            'name' => 'Customer',
            'email' => 'customer@haraan.test',
            'password' => bcrypt('secret123'),
            'role' => 'CUSTOMER',
            'status' => 'active',
        ]);
    }

    public function test_unauthorized_user_cannot_access_ticket_check_in(): void
    {
        $this->actingAs($this->customer());
        Filament::setCurrentPanel(Filament::getPanel('control'));

        $this->assertFalse(TicketCheckIn::canAccess());
    }

    public function test_admin_can_access_and_render_ticket_check_in(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('control'));

        $this->assertTrue(TicketCheckIn::canAccess());

        Livewire::test(TicketCheckIn::class)
            ->assertOk()
            ->assertSee('Haraan Gate Console')
            ->assertSee('Start camera')
            ->assertSee('Manual Code Entry')
            ->assertSee('Outdoor Mode');
    }

    public function test_event_lock_drops_when_event_does_not_exist(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('control'));

        Livewire::withQueryParams(['event' => 99999])
            ->test(TicketCheckIn::class)
            ->assertSet('event', null)
            ->assertSet('lockedTitle', null);
    }

    public function test_event_lock_honours_existing_event(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('control'));

        $event = Event::create([
            'title' => 'Neon Concert Fest',
            'category' => 'MUSIC',
            'description' => 'A live music fest.',
            'date' => now()->addDays(2)->format('Y-m-d'),
            'time' => '7:00 PM',
            'city' => 'Bengaluru',
            'venue' => 'Palace Grounds',
            'location' => 'Palace Grounds, Bengaluru',
            'booking_format' => 'OFFLINE',
            'visibility' => 'PUBLIC',
            'status' => 'published',
            'partner_id' => $admin->id,
        ]);

        Livewire::withQueryParams(['event' => $event->id])
            ->test(TicketCheckIn::class)
            ->assertSet('event', $event->id)
            ->assertSet('lockedTitle', 'Neon Concert Fest')
            ->assertSee('Locked to')
            ->assertSee('Neon Concert Fest');
    }

    public function test_scanning_invalid_payload_increments_rejected(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('control'));

        Livewire::test(TicketCheckIn::class)
            ->assertSet('rejected', 0)
            ->call('scan', 'completely-invalid-payload')
            ->assertSet('rejected', 1);
    }

    public function test_manual_entry_with_empty_code_is_noop(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('control'));

        Livewire::test(TicketCheckIn::class)
            ->set('manualCode', '   ')
            ->call('submitManual')
            ->assertSet('admitted', 0)
            ->assertSet('rejected', 0);
    }
}