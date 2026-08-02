<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Events\Pages\CreateEvent;
use App\Filament\Resources\Events\Pages\EditEvent;
use App\Models\Event;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The wizard's date field carried `->minDate(now())`, which registers an
 * `after_or_equal` validation rule — and Create and Edit share one schema. So once
 * an event's date passed, EVERY save from the edit screen failed validation on the
 * "When & Where" step: a host could not fix a typo, correct a venue or flip the
 * sold-out override without first moving the date into the future.
 *
 * minDate is now create-only. These pin both halves of that.
 */
class EventDateEditableTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin', 'email' => 'admin@example.test',
            'password' => bcrypt('secret'), 'role' => 'ADMIN', 'status' => 'active',
        ]);
    }

    private function pastEvent(User $owner): Event
    {
        return Event::create([
            'partner_id'      => $owner->id,
            'title'           => 'Last Month Show',
            // Uppercase key + a description: both are required by the shared schema,
            // so without them the save fails on those fields instead of the date.
            'category'        => 'MUSIC',
            'description'     => 'An event that has already happened.',
            'location'        => 'Arena',
            'venue'           => 'Arena, Hyderabad',
            'city'            => 'Hyderabad',
            'date'            => now()->subDays(30),
            'time'            => '7:00 PM',
            'price'           => 0,
            'total_slots'     => 100,
            'available_slots' => 100,
            'images'          => [],
            'status'          => 'published',
        ]);
    }

    public function test_a_past_event_can_still_be_edited_and_saved(): void
    {
        $admin = $this->admin();
        $event = $this->pastEvent($admin);

        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('control'));

        Livewire::test(EditEvent::class, ['record' => $event->getRouteKey()])
            ->fillForm(['title' => 'Last Month Show — corrected'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Last Month Show — corrected', $event->fresh()->title);
    }

    public function test_the_date_field_itself_accepts_a_past_value_on_edit(): void
    {
        // The sharpest form of the fix: the date is what minDate validated, so
        // re-saving a past event with another past date must pass.
        $admin = $this->admin();
        $event = $this->pastEvent($admin);

        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('control'));

        Livewire::test(EditEvent::class, ['record' => $event->getRouteKey()])
            ->fillForm(['date' => now()->subDays(45)->format('Y-m-d')])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(
            now()->subDays(45)->format('Y-m-d'),
            $event->fresh()->date?->format('Y-m-d'),
        );
    }

    public function test_a_new_event_still_cannot_be_scheduled_in_the_past(): void
    {
        // The rule is worth keeping where it belongs — this is the half we want.
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('control'));

        Livewire::test(CreateEvent::class)
            ->fillForm([
                'title' => 'Backdated Show', 'category' => 'MUSIC', 'description' => 'Nope.',
                'date' => now()->subDays(3)->format('Y-m-d'), 'time' => '7:00 PM',
                'city' => 'Hyderabad', 'venue' => 'Arena', 'location' => 'Kondapur, Hyderabad',
                'booking_format' => 'OFFLINE', 'visibility' => 'PUBLIC', 'status' => 'draft',
            ])
            ->call('create')
            ->assertHasFormErrors(['date']);
    }
}
