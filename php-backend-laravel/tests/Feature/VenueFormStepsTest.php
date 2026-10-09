<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Venues\Pages\CreateVenue;
use App\Filament\Resources\Venues\Pages\EditVenue;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueCourt;
use App\Models\VenueSlot;
use App\Support\VenueSetupSteps;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The stepped venue form: the setup journey reads the venue's real state (and agrees with
 * what publishing checks), and the hours drawing and bill preview follow the form as it is edited.
 */
final class VenueFormStepsTest extends TestCase
{
    use RefreshDatabase;

    private Venue $venue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::create([
            'name' => 'Admin', 'email' => 'admin@haraan.test', 'password' => bcrypt('x'),
            'role' => 'ADMIN', 'status' => 'active',
        ]));
        Filament::setCurrentPanel(Filament::getPanel('control'));

        $this->venue = Venue::create([
            'name' => 'Vaddeswaram', 'category' => 'Cricket', 'location' => 'Vaddeswaram', 'city' => 'Guntur',
            'price' => 0, 'is_active' => true, 'is_bookable' => true,
            'hours_json' => ['Mon' => ['open' => '06:00', 'close' => '22:00']],
        ]);
    }

    public function test_journey_agrees_with_publish_readiness(): void
    {
        $steps = collect(VenueSetupSteps::forVenue($this->venue))->keyBy('key');
        $this->assertTrue($steps['basics']['done']);
        $this->assertTrue($steps['location']['done']);
        $this->assertFalse($steps['courts']['done']);
        $this->assertFalse($steps['photos']['done']);
        $this->assertFalse($this->venue->isReadyForPublish());

        VenueCourt::create(['venue_id' => $this->venue->id, 'name' => 'VADI', 'price' => 500, 'is_active' => true]);
        VenueSlot::create(['venue_id' => $this->venue->id, 'day' => VenueSlot::EVERY_DAY, 'time' => '6:00 AM', 'is_available' => true]);
        $this->venue->update(['images' => ['venues/a.jpg']]);

        $steps = collect(VenueSetupSteps::forVenue($this->venue->fresh()))->keyBy('key');
        $required = $steps->where('required', true);
        $this->assertSame($required->count(), $required->where('done', true)->count());
        $this->assertTrue($this->venue->fresh()->isReadyForPublish(), 'Journey "all done" must mean publishing is allowed.');
        $this->assertSame('court rates', $steps['pricing']['hint']);
    }

    public function test_sport_picker_offers_pickleball_and_saves_main_sport(): void
    {
        Livewire::test(EditVenue::class, ['record' => $this->venue->getRouteKey()])
            ->assertOk()
            ->assertSee('Pickleball')
            ->assertSee('7 ft kitchen')
            ->fillForm(['category' => 'Pickleball', 'sports' => ['Cricket', 'Pickleball']])
            ->call('save')
            ->assertHasNoFormErrors();

        $venue = $this->venue->fresh();
        $this->assertSame('Pickleball', $venue->category);
        $this->assertSame(['Pickleball', 'Cricket'], $venue->sportsList());
    }

    public function test_edit_page_shows_journey_week_and_live_bill(): void
    {
        VenueCourt::create(['venue_id' => $this->venue->id, 'name' => 'VADI', 'price' => 500, 'is_active' => true]);

        Livewire::test(EditVenue::class, ['record' => $this->venue->getRouteKey()])
            ->assertOk()
            ->assertSee('steps done')
            ->assertSee('Hours &amp; slots', escape: false)
            ->assertSee('Open <b>1</b> day', escape: false)
            // Base ₹0 → the bill prices the cheapest court.
            ->assertSee('Cheapest court, 1 hour')
            ->assertSee('₹500')
            ->fillForm(['price' => 600, 'convenience_fee_type' => 'flat', 'convenience_fee_value' => 20])
            ->assertSee('Convenience fee')
            ->assertSee('₹620');
    }

    public function test_create_page_renders_journey_with_nothing_done(): void
    {
        Livewire::test(CreateVenue::class)
            ->assertOk()
            ->assertSee('0 of 6 steps done');
    }
}
