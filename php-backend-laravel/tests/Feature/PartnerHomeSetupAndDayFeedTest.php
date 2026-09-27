<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueSlot;
use App\Support\BusinessClock;
use App\Support\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Home's "get set up" screen, and the desk's day list.
 *
 *  - A venue with slots on other days (or closed today) is not a venue that was never
 *    set up: Home must not tell it "isn't bookable yet".
 *  - The desk's day list is every booking for that date, not the latest 100 rows.
 */
final class PartnerHomeSetupAndDayFeedTest extends TestCase
{
    use RefreshDatabase;

    private User $partner;

    private Venue $venue;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->partner = User::factory()->create(['role' => 'partner', 'partner_type' => 'venue', 'status' => 'active']);
        $this->venue = Venue::create(['name' => 'Setup Turf', 'location' => 'Madhapur', 'price' => 500, 'is_active' => true, 'partner_id' => $this->partner->id]);

        $secret = config('app.jwt_secret') ?: env('JWT_SECRET', 'change_me');
        $this->token = JwtService::issueForUser($this->partner, $secret);
    }

    private function get2(string $path)
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->token)->getJson('/api/partner'.$path);
    }

    public function test_a_venue_with_no_slots_today_is_still_set_up(): void
    {
        // A slot on some OTHER weekday only: today has no capacity.
        $otherDay = BusinessClock::todayDate()->addDay()->format('l');
        VenueSlot::create(['venue_id' => $this->venue->id, 'day' => $otherDay, 'time' => '6:00 AM']);

        $data = $this->get2('/today')->assertOk()->json('data');

        $this->assertSame(0, $data['capacity']['total']);
        $this->assertTrue($data['setup']['has_slots']);
    }

    public function test_a_venue_with_no_slots_at_all_needs_setting_up(): void
    {
        $this->assertFalse($this->get2('/today')->assertOk()->json('data.setup.has_slots'));
    }

    public function test_the_day_feed_is_the_whole_day_not_the_latest_hundred(): void
    {
        $day = BusinessClock::todayDate()->addDays(3)->toDateString();
        $other = BusinessClock::todayDate()->addDays(4)->toDateString();
        $player = User::factory()->create();

        $row = fn (string $date) => [
            'booking_type' => 'venue', 'venue_id' => $this->venue->id, 'user_id' => $player->id,
            'channel' => 'online', 'status' => 'CONFIRMED', 'slot_date' => $date,
            'start_time' => '07:00', 'end_time' => '08:00', 'quantity' => 1, 'total_amount' => 500,
        ];
        foreach (range(1, 120) as $i) {
            Booking::forceCreate($row($day));
        }
        // Newer rows on another date used to push the day's own rows out of the 100.
        foreach (range(1, 30) as $i) {
            Booking::forceCreate($row($other));
        }

        $this->get2('/bookings?date='.$day)->assertOk()->assertJsonCount(120, 'data');
        $this->get2('/bookings')->assertOk()->assertJsonCount(100, 'data');
    }
}
