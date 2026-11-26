<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Venues\Pages\EditVenue;
use App\Filament\Resources\Venues\RelationManagers\CourtsRelationManager;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueCourt;
use App\Models\VenueSlot;
use App\Services\VenueSlotAvailability;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/** A court keeping shorter hours than its venue: nets 6 AM – 6 PM on a 24-hour turf. */
final class CourtHoursTest extends TestCase
{
    use RefreshDatabase;

    private Venue $venue;

    private VenueCourt $turf;

    private VenueCourt $nets;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::create([
            'name' => 'Admin', 'email' => 'admin@haraan.test', 'password' => bcrypt('x'),
            'role' => 'ADMIN', 'status' => 'active',
        ]));
        Filament::setCurrentPanel(Filament::getPanel('control'));

        $this->venue = Venue::create([
            'name' => 'Stumps', 'location' => 'Kadapa', 'price' => 300, 'slot_minutes' => 30,
            'sports' => ['Cricket'], 'is_active' => true, 'is_bookable' => true,
        ]);
        $this->turf = VenueCourt::create(['venue_id' => $this->venue->id, 'name' => 'Court 1', 'sports' => ['Cricket'], 'is_active' => true]);
        $this->nets = VenueCourt::create(['venue_id' => $this->venue->id, 'name' => 'Court 2', 'sports' => ['Cricket Nets'], 'is_active' => true,
            'open_time' => '06:00', 'close_time' => '18:00']);
        foreach (['5:30 AM', '6:00 AM', '5:30 PM', '6:00 PM', '11:30 PM'] as $t) {
            VenueSlot::create(['venue_id' => $this->venue->id, 'day' => VenueSlot::EVERY_DAY, 'time' => $t, 'is_available' => true]);
        }
    }

    public function test_court_window_maths(): void
    {
        $this->assertSame('6:00 AM – 6:00 PM', $this->nets->hoursLabel());
        $this->assertFalse($this->nets->isOpenFor(330, 30));     // 5:30 AM
        $this->assertTrue($this->nets->isOpenFor(360, 30));      // 6:00 AM
        $this->assertTrue($this->nets->isOpenFor(1050, 30));     // 5:30 PM – 6 PM fits
        $this->assertFalse($this->nets->isOpenFor(1050, 60));    // 5:30 – 6:30 PM does not
        $this->assertFalse($this->nets->isOpenFor(1080, 30));    // 6:00 PM

        $late = new VenueCourt(['open_time' => '18:00', 'close_time' => '02:00']);
        $this->assertTrue($late->isOpenFor(60, 30));             // 1 AM, past midnight
        $this->assertFalse($late->isOpenFor(600, 30));           // 10 AM
        $this->assertNull((new VenueCourt(['open_time' => '00:00', 'close_time' => '00:00']))->hoursLabel());
        $this->assertTrue($this->turf->isOpenFor(1410, 30));     // no hours = always
    }

    public function test_board_hatches_slots_outside_the_court_hours(): void
    {
        $board = Livewire::test(CourtsRelationManager::class, ['ownerRecord' => $this->venue, 'pageClass' => EditVenue::class])
            ->assertOk()->assertSee('6:00 AM – 6:00 PM')
            ->instance()->courtBoard();

        $nets = collect(collect($board['courts'])->firstWhere('name', 'Court 2')['chips'])->keyBy('time');
        $turf = collect(collect($board['courts'])->firstWhere('name', 'Court 1')['chips'])->keyBy('time');

        $this->assertFalse($nets['5:30 AM']['open']);
        $this->assertTrue($nets['6:00 AM']['open']);
        $this->assertTrue($nets['5:30 PM']['open']);
        $this->assertFalse($nets['6:00 PM']['open']);
        $this->assertFalse($nets['11:30 PM']['open']);
        $this->assertTrue($turf['11:30 PM']['open']);
    }

    public function test_hours_action_saves_and_clears(): void
    {
        Livewire::test(CourtsRelationManager::class, ['ownerRecord' => $this->venue, 'pageClass' => EditVenue::class])
            ->callTableAction('courtHours', $this->turf, ['own_hours' => true, 'open_time' => '07:00', 'close_time' => '22:00'])
            ->assertHasNoTableActionErrors();
        $this->assertSame(['07:00', '22:00'], [$this->turf->fresh()->open_time, $this->turf->fresh()->close_time]);

        Livewire::test(CourtsRelationManager::class, ['ownerRecord' => $this->venue, 'pageClass' => EditVenue::class])
            ->callTableAction('courtHours', $this->turf, ['own_hours' => false])
            ->assertHasNoTableActionErrors();
        $this->assertNull($this->turf->fresh()->open_time);
        $this->assertNull($this->turf->fresh()->close_time);
    }

    public function test_availability_only_counts_courts_open_at_that_time(): void
    {
        $rows = collect(app(VenueSlotAvailability::class)->forDate($this->venue, Carbon::tomorrow(), 1, $this->nets->id));
        $byTime = $rows->keyBy(fn ($r) => $r['time'] ?? $r['slot']['time'] ?? null);
        $this->assertNotEmpty($byTime);
        $states = $rows->pluck('state')->all();
        $this->assertContains(VenueSlotAvailability::CLOSED, $states);
        $this->assertContains(VenueSlotAvailability::OPEN, $states);
    }
}
