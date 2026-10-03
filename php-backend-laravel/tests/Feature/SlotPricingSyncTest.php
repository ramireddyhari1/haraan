<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\Venue;
use App\Models\VenueCourt;
use App\Models\VenueSlot;
use App\Support\JwtService;
use App\Support\VenueDayGrid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What a partner saves in Pricing & slots is what the desk grid shows and what a booking
 * charges — the same rows, the same times, the same price.
 */
final class SlotPricingSyncTest extends TestCase
{
    use RefreshDatabase;

    private Venue $venue;

    private VenueCourt $court;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $partner = User::factory()->create(['role' => 'partner', 'partner_type' => 'venue', 'status' => 'active']);
        $this->venue = Venue::create([
            'name' => 'Sync Turf', 'location' => 'Vaddeswaram', 'price' => 5,
            'is_active' => true, 'is_bookable' => true, 'partner_id' => $partner->id,
        ]);
        $this->court = VenueCourt::create(['venue_id' => $this->venue->id, 'name' => 'VADI', 'sports' => ['Cricket'], 'price' => 5, 'is_active' => true]);

        $secret = config('app.jwt_secret') ?: env('JWT_SECRET', 'change_me');
        $this->token = JwtService::issueForUser($partner, $secret);
    }

    private function save(array $body, ?int $slotId = null)
    {
        $url = '/api/partner/venues/'.$this->venue->id.'/slots'.($slotId ? '/'.$slotId : '');

        return $this->withHeader('Authorization', 'Bearer '.$this->token)->postJson($url, $body);
    }

    public function test_a_range_is_saved_as_its_start_time(): void
    {
        $this->save(['day' => 'Wednesday', 'time' => '06:00 AM - 07:00 AM'])->assertOk()
            ->assertJsonPath('slot.time', '6:00 AM');

        $this->assertSame('6:00 AM', VenueSlot::query()->value('time'));
    }

    public function test_unreadable_time_and_day_ranges_are_refused_not_guessed(): void
    {
        $this->save(['time' => 'evening'])->assertStatus(422);
        // "Mon-Fri" used to become "Every day" and run on weekends too.
        $this->save(['day' => 'Mon-Fri', 'time' => '6:00 AM'])->assertStatus(422);
        $this->assertSame(0, VenueSlot::query()->count());
    }

    public function test_the_same_day_and_time_twice_is_refused(): void
    {
        $this->save(['day' => 'Monday', 'time' => '6:00 AM'])->assertOk();
        $this->save(['day' => 'mon', 'time' => '06:00 AM - 07:00 AM'])->assertStatus(422);
        // A different day at the same time is fine.
        $this->save(['day' => 'Tuesday', 'time' => '6:00 AM'])->assertOk();
    }

    public function test_slot_price_is_what_the_desk_grid_shows(): void
    {
        $this->save(['day' => 'Every day', 'time' => '7:00 PM', 'price' => 800])->assertOk();
        $this->save(['day' => 'Every day', 'time' => '8:00 PM'])->assertOk();

        $grid = VenueDayGrid::build($this->venue->fresh(), '2026-09-30');
        $prices = collect($grid['slots'])->mapWithKeys(fn ($s) => [$s['time'] => $s['courts'][0]['price']]);

        $this->assertEquals(800, $prices['7:00 PM']);
        // No price of its own = the court's rate.
        $this->assertEquals(5, $prices['8:00 PM']);
    }

    public function test_slot_price_beats_the_court_peak_price(): void
    {
        $this->court->update(['peak_price' => 1000, 'peak_start' => '18:00', 'peak_end' => '23:00']);
        $this->save(['day' => 'Every day', 'time' => '7:00 PM', 'price' => 800])->assertOk();
        $this->save(['day' => 'Every day', 'time' => '9:00 PM'])->assertOk();

        $cells = collect(VenueDayGrid::build($this->venue->fresh(), '2026-09-30')['slots'])
            ->mapWithKeys(fn ($s) => [$s['time'] => $s['courts'][0]]);

        $this->assertEquals(800, $cells['7:00 PM']['price']);
        $this->assertFalse($cells['7:00 PM']['is_peak']);
        $this->assertEquals(1000, $cells['9:00 PM']['price']);
        $this->assertTrue($cells['9:00 PM']['is_peak']);
    }

    public function test_each_court_can_have_its_own_price_at_a_time(): void
    {
        $padi = VenueCourt::create(['venue_id' => $this->venue->id, 'name' => 'padi', 'sports' => ['Football'], 'price' => 5, 'is_active' => true]);

        $this->save(['day' => 'Every day', 'time' => '7:00 PM', 'price' => 300, 'courtPrices' => [(string) $padi->id => 800]])
            ->assertOk()
            ->assertJsonPath('slot.court_prices.'.$padi->id, 800);

        $cells = collect(collect(VenueDayGrid::build($this->venue->fresh(), '2026-09-30')['slots'])->firstWhere('time', '7:00 PM')['courts'])
            ->keyBy('court_id');

        // padi has its own price here; VADI falls back to the all-courts price.
        $this->assertEquals(800, $cells[$padi->id]['price']);
        $this->assertEquals(300, $cells[$this->court->id]['price']);
    }

    public function test_a_court_from_another_venue_is_refused(): void
    {
        $other = Venue::create(['name' => 'Other', 'location' => 'X', 'price' => 5, 'is_active' => true]);
        $foreign = VenueCourt::create(['venue_id' => $other->id, 'name' => 'Theirs', 'price' => 5, 'is_active' => true]);

        $this->save(['time' => '7:00 PM', 'courtPrices' => [(string) $foreign->id => 800]])->assertStatus(422);
    }

    public function test_slot_list_reads_as_the_week(): void
    {
        foreach ([['Sunday', '10:00 PM'], ['Monday', '7:00 AM'], ['Every day', '5:00 AM'], ['Monday', '6:00 AM']] as [$d, $t]) {
            $this->save(['day' => $d, 'time' => $t])->assertOk();
        }

        $list = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/partner/venues/'.$this->venue->id.'/slots')->assertOk()->json('data');

        $this->assertSame(
            ['Every day 5:00 AM', 'Monday 6:00 AM', 'Monday 7:00 AM', 'Sunday 10:00 PM'],
            array_map(fn ($s) => $s['day'].' '.$s['time'], $list),
        );
    }
}
