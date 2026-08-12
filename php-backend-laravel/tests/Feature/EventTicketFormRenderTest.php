<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Events\Pages\CreateEvent;
use App\Filament\Resources\Events\Pages\EditEvent;
use App\Models\Event;
use App\Models\EventSlot;
use App\Models\TicketType;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The redesigned Tickets step (sessions repeater, District-style ticket cards,
 * gateway/platform fee sections, coupons) must actually build and render in both
 * the control (admin) and partner panels — a broken component API or closure would
 * throw when the schema is mounted, which these tests catch without a browser.
 */
class EventTicketFormRenderTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin', 'email' => 'admin@example.test',
            'password' => bcrypt('secret'), 'role' => 'ADMIN', 'status' => 'active',
        ]);
    }

    public function test_create_event_form_renders_in_control_panel(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('control'));

        Livewire::test(CreateEvent::class)
            ->assertOk()
            ->assertSee('Ticket configuration')
            ->assertSee('Add ticket type');
    }

    public function test_ticket_studio_creates_ticket_rows_on_save(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('control'));

        Livewire::test(CreateEvent::class)
            ->fillForm([
                'title' => 'Studio Event', 'category' => 'MUSIC', 'description' => 'A test event.',
                'date' => now()->addDays(5)->format('Y-m-d'), 'time' => '7:00 PM',
                'city' => 'Hyderabad', 'venue' => 'Arena', 'location' => 'Kondapur, Hyderabad',
                'booking_format' => 'OFFLINE', 'visibility' => 'PUBLIC', 'status' => 'draft',
                'ticketStudio' => [
                    'mode' => 'unified', 'seating' => true, 'slotCount' => 1,
                    'phases' => [['name' => 'Early Bird'], ['name' => 'Phase 2']],
                    'tickets' => [
                        ['key' => 'aaa', 'name' => 'VIP', 'seats' => 50, 'description' => 'Front row',
                         'price' => 999, 'free' => false, 'visible' => true, 'bulk' => true, 'minPer' => 2, 'maxPer' => 6, 'phase' => 1],
                        ['key' => 'bbb', 'name' => 'General', 'seats' => -1, 'description' => '',
                         'price' => 0, 'free' => true, 'visible' => true, 'bulk' => false, 'phase' => 0],
                        ['key' => 'ccc', 'name' => '', 'seats' => -1, 'free' => true, 'visible' => true], // blank → dropped
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $event = Event::where('title', 'Studio Event')->firstOrFail();

        $this->assertTrue((bool) $event->seat_selection);
        // Capacity is derived from the ticket seats: General is unlimited → high cap.
        $this->assertSame(100000, (int) $event->total_slots);
        $this->assertSame(100000, (int) $event->available_slots);
        // A default session is seeded on create even with the Sessions repeater hidden.
        $this->assertSame(1, $event->slots()->count());
        $this->assertSame(2, $event->ticketTypes()->count());

        // Release phases stored on the event, and the tier's phase assignment.
        $this->assertSame(['Early Bird', 'Phase 2'], array_column((array) $event->release_phases, 'name'));

        $vip = $event->ticketTypes()->where('name', 'VIP')->firstOrFail();
        $this->assertSame(50, (int) $vip->capacity);
        $this->assertSame(999.0, (float) $vip->price);
        $this->assertTrue((bool) $vip->bulk_booking);
        $this->assertSame(2, (int) $vip->min_per_order);
        $this->assertSame(6, (int) $vip->max_per_order);
        $this->assertSame(1, (int) $vip->release_phase);

        $general = $event->ticketTypes()->where('name', 'General')->firstOrFail();
        $this->assertSame(0.0, (float) $general->price);
        $this->assertNull($general->capacity); // -1 → unlimited
        $this->assertSame(0, (int) $general->release_phase);

        // The only earlier-phase tier (General) is unlimited, which never "sells out",
        // so it doesn't hold a later phase closed → VIP is released.
        $event->load('ticketTypes');
        $this->assertTrue($event->phaseReleased((int) $vip->release_phase));

        // But a capacity-bearing earlier tier with stock left DOES hold it closed.
        $general->update(['capacity' => 5, 'sold' => 0]);
        $event->load('ticketTypes');
        $this->assertFalse($event->phaseReleased((int) $vip->release_phase));
    }

    public function test_partner_create_event_shows_coupons_section(): void
    {
        $partner = User::create([
            'name' => 'Event Partner', 'email' => 'evpartner@example.test',
            'password' => bcrypt('secret'), 'role' => 'PARTNER',
            'partner_type' => 'event', 'status' => 'active',
        ]);

        $this->actingAs($partner);
        Filament::setCurrentPanel(Filament::getPanel('partner'));

        Livewire::test(CreateEvent::class)
            ->assertOk()
            ->assertSee('Coupons')
            ->assertSee('Create Coupon');
    }

    public function test_edit_event_with_slots_and_tiers_renders(): void
    {
        $admin = $this->admin();
        $event = Event::create([
            'partner_id' => $admin->id,
            'title' => 'Multi-session Show', 'category' => 'Music',
            'location' => 'Arena', 'venue' => 'Arena, Hyderabad',
            'date' => now()->addDays(10), 'time' => '19:00',
            'price' => 0, 'total_slots' => 200, 'available_slots' => 200,
            'images' => [], 'status' => 'published', 'tickets_per_slot' => true,
        ]);
        $slotA = EventSlot::create(['event_id' => $event->id, 'label' => 'Day 1', 'starts_at' => now()->addDays(10), 'sort' => 0]);
        EventSlot::create(['event_id' => $event->id, 'label' => 'Day 2', 'starts_at' => now()->addDays(11), 'sort' => 1]);
        TicketType::create([
            'event_id' => $event->id, 'event_slot_id' => $slotA->id,
            'name' => 'VIP', 'kind' => 'standard', 'price' => 999,
            'capacity' => 50, 'visible' => true, 'sort' => 0,
        ]);

        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('control'));

        Livewire::test(EditEvent::class, ['record' => $event->getRouteKey()])->assertOk();
    }
}
