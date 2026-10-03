<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\Venue;
use App\Models\VenueSlot;
use App\Support\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The partner sets their venue's opening hours from the app: per day, 24 hours, past
 * midnight, or closed — and the slot template follows without losing prices.
 */
final class PartnerVenueHoursTest extends TestCase
{
    use RefreshDatabase;

    private Venue $venue;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $partner = User::factory()->create(['role' => 'partner', 'partner_type' => 'venue', 'status' => 'active']);
        $this->venue = Venue::create([
            'name' => 'Hours Turf', 'location' => 'Vaddeswaram', 'price' => 500,
            'is_active' => true, 'is_bookable' => true, 'partner_id' => $partner->id,
        ]);
        $secret = config('app.jwt_secret') ?: env('JWT_SECRET', 'change_me');
        $this->token = JwtService::issueForUser($partner, $secret);
    }

    private function save(array $days, int $len = 60)
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson("/api/partner/venues/{$this->venue->id}/hours", ['days' => $days, 'slot_minutes' => $len]);
    }

    private function every(?array $h): array
    {
        return array_fill_keys(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'], $h);
    }

    public function test_six_to_eleven_every_day_makes_seventeen_slots_a_day(): void
    {
        $this->save($this->every(['open' => '06:00', 'close' => '23:00']))
            ->assertOk()->assertJsonPath('days.Sat.open', '06:00');

        $this->assertSame(17 * 7, VenueSlot::where('venue_id', $this->venue->id)->count());
        $this->assertSame('Mon–Sun 6:00 AM–11:00 PM', $this->venue->fresh()->hours);
    }

    public function test_open_twenty_four_hours(): void
    {
        $this->save($this->every(['open' => '00:00', 'close' => '00:00']), 30)->assertOk();

        $this->assertSame(48, VenueSlot::where('venue_id', $this->venue->id)->where('day', 'Sunday')->count());
        $this->assertStringContainsString('Open 24 hours', $this->venue->fresh()->hours);
    }

    public function test_a_closed_day_gets_no_slots_and_is_not_bookable(): void
    {
        $days = $this->every(['open' => '06:00', 'close' => '22:00']);
        $days['Sun'] = null;
        $this->save($days)->assertOk()->assertJsonPath('days.Sun', null);

        $this->assertSame(0, VenueSlot::where('venue_id', $this->venue->id)->where('day', 'Sunday')->count());
        $this->assertFalse($this->venue->fresh()->isOpenOn(now()->next('Sunday')));
    }

    public function test_new_day_rows_keep_the_every_day_price_for_that_hour(): void
    {
        VenueSlot::create(['venue_id' => $this->venue->id, 'day' => 'Every day', 'time' => '7:00 PM', 'price' => 900]);

        $this->save($this->every(['open' => '18:00', 'close' => '21:00']))->assertOk();

        $this->assertEquals(900, VenueSlot::where('venue_id', $this->venue->id)->where('day', 'Saturday')->where('time', '7:00 PM')->value('price'));
    }

    public function test_every_day_closed_is_refused_and_bad_times_rejected(): void
    {
        $this->save($this->every(null))->assertStatus(422);
        $this->save($this->every(['open' => '25:00', 'close' => '23:00']))->assertStatus(422);
    }
}
