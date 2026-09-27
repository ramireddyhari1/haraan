<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueCourt;
use App\Models\VenueSlot;
use App\Support\BusinessClock;
use App\Support\JwtService;
use App\Support\SlotGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** The one-click slot builder behind /control's and the partner app's "Generate slots". */
final class SlotGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private User $partner;

    private Venue $venue;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->partner = User::factory()->create(['role' => 'partner', 'partner_type' => 'venue', 'status' => 'active']);
        $this->venue = Venue::create([
            'name' => 'Gen Turf', 'location' => 'Madhapur', 'price' => 500,
            'is_active' => true, 'is_bookable' => true, 'partner_id' => $this->partner->id,
        ]);

        $secret = config('app.jwt_secret') ?: env('JWT_SECRET', 'change_me');
        $this->token = JwtService::issueForUser($this->partner, $secret);
    }

    private function times(string $date): array
    {
        return $this->venue->fresh()->slotsOn(Carbon::parse($date))->pluck('time')->all();
    }

    public function test_hourly_slots_every_day_between_open_and_close(): void
    {
        $r = SlotGenerator::generate($this->venue, '6:00 AM', '9:00 AM', 60, ['Every day']);

        $this->assertSame(3, $r['created']);
        $this->assertSame(['6:00 AM', '7:00 AM', '8:00 AM'], $this->times('2026-09-28'));
        $this->assertSame(60, $this->venue->fresh()->slot_minutes);
    }

    public function test_half_hour_slots_on_chosen_days_only(): void
    {
        SlotGenerator::generate($this->venue, '6:00 AM', '8:00 AM', 30, ['Saturday']);

        $this->assertSame(['6:00 AM', '6:30 AM', '7:00 AM', '7:30 AM'], $this->times('2026-10-03'));
        $this->assertSame([], $this->times('2026-10-04'));
        $this->assertSame(30, $this->venue->fresh()->slot_minutes);
    }

    public function test_closing_at_midnight_includes_the_last_evening_slot(): void
    {
        SlotGenerator::generate($this->venue, '10:00 PM', '12:00 AM', 60, ['Every day']);

        $this->assertSame(['10:00 PM', '11:00 PM'], $this->times('2026-09-28'));
    }

    public function test_add_keeps_existing_slots_and_their_price_replace_starts_over(): void
    {
        $priced = VenueSlot::create(['venue_id' => $this->venue->id, 'day' => 'Every day', 'time' => '6:00 AM', 'price' => 900]);

        $add = SlotGenerator::generate($this->venue, '6:00 AM', '8:00 AM', 60, ['Every day']);
        $this->assertSame(['created' => 1, 'kept' => 1], ['created' => $add['created'], 'kept' => $add['kept']]);
        $this->assertEquals(900, $priced->fresh()->price);

        $replace = SlotGenerator::generate($this->venue, '6:00 AM', '8:00 AM', 60, ['Every day'], 500, 1, 'replace');
        $this->assertSame(2, $replace['removed']);
        $this->assertNull($priced->fresh());
        $this->assertEquals([500, 500], VenueSlot::where('venue_id', $this->venue->id)->pluck('price')->map(fn ($p) => (float) $p)->all());
    }

    public function test_back_to_back_half_hour_walk_ins_do_not_clash(): void
    {
        VenueCourt::create(['venue_id' => $this->venue->id, 'name' => 'Court 1', 'price' => 500, 'is_active' => true]);
        SlotGenerator::generate($this->venue, '6:00 AM', '8:00 AM', 30, ['Every day']);
        $date = BusinessClock::todayDate()->addDay()->toDateString();
        $court = VenueCourt::where('venue_id', $this->venue->id)->value('id');
        $slot = fn (string $t) => VenueSlot::where('venue_id', $this->venue->id)->where('time', $t)->value('id');

        $walkIn = fn (string $t) => $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson("/api/partner/venues/{$this->venue->id}/bookings", [
                'slotId' => $slot($t), 'courtId' => $court, 'date' => $date,
                'guestName' => 'Desk', 'guestPhone' => '9000000001', 'paymentMethod' => 'cash',
            ]);

        $walkIn('6:00 AM')->assertCreated();
        $walkIn('6:30 AM')->assertCreated();
        $walkIn('6:30 AM')->assertStatus(409);

        $this->assertSame(['06:00|06:30', '06:30|07:00'], Booking::where('venue_id', $this->venue->id)
            ->orderBy('id')->get()->map(fn ($b) => $b->start_time.'|'.$b->end_time)->all());
    }

    public function test_the_partner_api_generates_and_refuses_bad_hours(): void
    {
        $api = fn (array $body) => $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson("/api/partner/venues/{$this->venue->id}/slots/generate", $body);

        $api(['open' => '6:00 AM', 'close' => '10:00 AM', 'step' => 60, 'days' => ['Every day']])
            ->assertOk()->assertJsonPath('created', 4)->assertJsonCount(4, 'data');

        $api(['open' => '9:00 PM', 'close' => '6:00 PM', 'step' => 60])->assertStatus(422);
        $api(['open' => '6:00 AM', 'close' => '9:00 AM', 'step' => 45])->assertStatus(422);
    }
}
