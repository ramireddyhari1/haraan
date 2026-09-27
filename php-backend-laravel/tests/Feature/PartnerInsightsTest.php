<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Booking;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueCourt;
use App\Models\VenueSlot;
use App\Support\BusinessClock;
use App\Support\JwtService;
use App\Support\PlatformRules;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /api/partner/insights — the charts under partner Home. Every figure is court-hours
 * and rupees of live bookings placed by the same rule as Home and the desk grid.
 */
final class PartnerInsightsTest extends TestCase
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
            'name' => 'Insights Turf', 'location' => 'Madhapur', 'price' => 500,
            'is_active' => true, 'is_bookable' => true, 'partner_id' => $this->partner->id,
        ]);
        VenueCourt::create(['venue_id' => $this->venue->id, 'name' => 'A', 'price' => 500, 'is_active' => true]);
        VenueCourt::create(['venue_id' => $this->venue->id, 'name' => 'B', 'price' => 500, 'is_active' => true]);
        foreach (['6:00 AM', '7:00 AM', '8:00 AM', '2:00 PM', '3:00 PM'] as $time) {
            VenueSlot::create(['venue_id' => $this->venue->id, 'time' => $time, 'price' => 500, 'capacity' => 1]);
        }

        $secret = config('app.jwt_secret') ?: env('JWT_SECRET', 'change_me');
        $this->token = JwtService::issueForUser($this->partner, $secret);
    }

    private function booking(string $date, string $start, string $end, string $channel, float $amount, string $status = 'CONFIRMED'): Booking
    {
        return Booking::forceCreate([
            'booking_type' => 'venue', 'venue_id' => $this->venue->id,
            'user_id' => User::factory()->create()->id,
            'channel' => $channel, 'status' => $status, 'payment_status' => 'paid',
            'slot_date' => $date, 'start_time' => $start, 'end_time' => $end,
            'quantity' => 1, 'total_amount' => $amount, 'amount_paid' => $amount,
        ]);
    }

    private function insights(string $query = '')
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->token)->getJson('/api/partner/insights'.$query);
    }

    public function test_week_bars_and_channel_split_count_live_bookings_only(): void
    {
        $today = BusinessClock::today();
        // No court → the whole venue: each live booking takes both courts.
        $this->booking($today, '06:00', '07:00', 'online', 1000);
        $this->booking($today, '07:00', '08:00', 'offline', 500);
        $this->booking($today, '08:00', '09:00', 'whatsapp', 400);
        $this->booking($today, '14:00', '15:00', 'online', 900, 'CANCELLED');
        $this->booking($today, '15:00', '16:00', 'online', 900, 'PENDING');

        $data = $this->insights()->assertOk()->json('data');

        $this->assertTrue($data['enabled']);
        $this->assertSame('This week', $data['week']['label']);
        $day = collect($data['week']['days'])->firstWhere('today', true);
        $this->assertEquals(1900, $day['revenue']);
        $this->assertEquals(10, $day['total_hours']);   // 5 slots × 2 courts
        $this->assertEquals(6, $day['booked_hours']);   // 3 live hours × 2 courts

        $ch = $data['channels']['today'];
        $this->assertEquals(1000, $ch['app']['amount']);
        $this->assertEquals(2, $ch['app']['hours']);
        $this->assertEquals(500, $ch['walk_in']['amount']);
        $this->assertEquals(400, $ch['whatsapp']['amount']);
        $this->assertSame(1, $ch['whatsapp']['count']);
    }

    public function test_last_week_rides_behind_each_bar(): void
    {
        $lastWeekSameDay = Carbon::parse(BusinessClock::today())->subWeek()->toDateString();
        $this->booking($lastWeekSameDay, '06:00', '07:00', 'online', 700);

        $data = $this->insights()->assertOk()->json('data');
        $day = collect($data['week']['days'])->firstWhere('today', true);

        $this->assertEquals(700, $day['last_revenue']);
        $this->assertEquals(700, $data['week']['last']['revenue']);
        $this->assertNull($data['week']['next']);
    }

    public function test_a_future_week_reads_as_this_week_and_an_old_week_can_page_forward(): void
    {
        $future = Carbon::parse(BusinessClock::today())->addWeeks(3)->toDateString();
        $this->assertSame('This week', $this->insights('?week='.$future)->json('data.week.label'));

        $old = Carbon::parse(BusinessClock::today())->subWeeks(2)->toDateString();
        $week = $this->insights('?week='.$old)->assertOk()->json('data.week');
        $this->assertNotNull($week['next']);
    }

    public function test_quiet_hours_are_named_once_there_is_enough_data(): void
    {
        $today = Carbon::parse(BusinessClock::today());
        // Mornings full for four weeks; afternoons never booked.
        for ($i = 1; $i <= 28; $i++) {
            $date = $today->copy()->subDays($i)->toDateString();
            $this->booking($date, '06:00', '09:00', 'online', 1500);
        }

        $heat = $this->insights()->assertOk()->json('data.heatmap');

        $this->assertTrue($heat['ready']);
        $this->assertSame('6 AM', $heat['hours'][0]);
        $this->assertCount(7, $heat['rows']);
        $this->assertEquals(1.0, $heat['rows'][0]['fill'][0]);
        $this->assertSame('Every day', $heat['quiet'][0]['days']);
        $this->assertSame('2–4 PM', $heat['quiet'][0]['hours']);
        $this->assertSame(0, $heat['quiet'][0]['fill']);
    }

    public function test_quiet_hours_split_weekdays_from_the_weekend(): void
    {
        $today = Carbon::parse(BusinessClock::today());
        // Mornings full every day; afternoons full only at the weekend.
        for ($i = 1; $i <= 28; $i++) {
            $date = $today->copy()->subDays($i);
            $this->booking($date->toDateString(), '06:00', '09:00', 'online', 1500);
            if ($date->isWeekend()) {
                $this->booking($date->toDateString(), '14:00', '16:00', 'online', 1000);
            }
        }

        $quiet = $this->insights()->assertOk()->json('data.heatmap.quiet');

        $this->assertSame([['days' => 'Mon–Fri', 'hours' => '2–4 PM', 'fill' => 0]], $quiet);
    }

    public function test_a_new_venue_is_not_told_every_hour_is_quiet(): void
    {
        $heat = $this->insights()->assertOk()->json('data.heatmap');

        $this->assertFalse($heat['ready']);
        $this->assertSame([], $heat['quiet']);
    }

    public function test_tomorrow_lists_open_runs_with_a_share_link(): void
    {
        $tomorrow = Carbon::parse(BusinessClock::today())->addDay()->toDateString();
        $this->booking($tomorrow, '07:00', '08:00', 'online', 1000); // both courts at 7

        $venue = $this->insights()->assertOk()->json('data.tomorrow.venues.0');

        $this->assertEquals(8, $venue['open_hours']);
        $this->assertEquals(10, $venue['total_hours']);
        $this->assertSame(['6 AM – 7 AM', '8 AM – 9 AM', '2 PM – 4 PM'], array_column($venue['windows'], 'label'));
        $this->assertSame(2, $venue['windows'][2]['free_courts']);
        $this->assertStringEndsWith('/gamehub/'.$this->venue->id, $venue['share_url']);
        $this->assertStringContainsString('Insights Turf: 6 AM–7 AM, 8 AM–9 AM, 2 PM–4 PM', $venue['share_text']);
    }

    public function test_the_admin_can_switch_insights_off(): void
    {
        AppSetting::set(PlatformRules::storageKey('partner_insights.enabled'), '0', PlatformRules::GROUP);

        $this->insights()->assertOk()->assertJsonPath('data.enabled', false);
    }

    public function test_another_partners_venue_is_not_counted(): void
    {
        $other = User::factory()->create(['role' => 'partner', 'partner_type' => 'venue', 'status' => 'active']);
        $theirs = Venue::create([
            'name' => 'Not Mine', 'location' => 'X', 'price' => 500,
            'is_active' => true, 'is_bookable' => true, 'partner_id' => $other->id,
        ]);
        Booking::forceCreate([
            'booking_type' => 'venue', 'venue_id' => $theirs->id, 'user_id' => $other->id,
            'channel' => 'online', 'status' => 'CONFIRMED', 'payment_status' => 'paid',
            'slot_date' => BusinessClock::today(), 'start_time' => '06:00', 'end_time' => '07:00',
            'quantity' => 1, 'total_amount' => 9999, 'amount_paid' => 9999,
        ]);

        $this->assertEquals(0, $this->insights()->json('data.week.totals.revenue'));
    }

    public function test_haraan_brought_counts_online_bookings_and_real_players(): void
    {
        $today = BusinessClock::today();
        $this->booking($today, '06:00', '07:00', 'online', 1000);
        $this->booking($today, '07:00', '08:00', 'whatsapp', 400);
        $this->booking($today, '08:00', '09:00', 'offline', 500);
        $this->booking($today, '14:00', '15:00', 'online', 900, 'CANCELLED');

        $h = $this->insights()->assertOk()->json('data.haraan');

        $this->assertSame(2, $h['all_time']['count']);
        $this->assertEquals(1400, $h['all_time']['amount']);
        $this->assertSame(2, $h['this_month']['count']);
        $this->assertSame(1, $h['players']);          // the app booking's player; WhatsApp desk rows point at the partner
        $this->assertEquals(0.67, $h['share']);
    }

    public function test_growth_only_climbs_and_leaves_future_bookings_out(): void
    {
        $today = Carbon::parse(BusinessClock::today());
        $this->booking($today->copy()->subWeeks(2)->toDateString(), '06:00', '07:00', 'online', 500);
        $this->booking($today->toDateString(), '06:00', '07:00', 'offline', 700);
        $this->booking($today->copy()->addDays(3)->toDateString(), '06:00', '07:00', 'online', 900);

        $g = $this->insights()->assertOk()->json('data.growth');

        $this->assertSame('week', $g['unit']);
        $this->assertSame(2, $g['bookings']);
        $this->assertEquals(1200, $g['revenue']);
        $revenue = array_column($g['points'], 'revenue');
        $this->assertEquals($revenue, array_values(collect($revenue)->sort()->all()));
        $this->assertEquals(1200, end($revenue));
    }

    public function test_milestones_mark_what_is_reached_and_how_far_to_the_next(): void
    {
        $today = BusinessClock::today();
        foreach (['06:00', '07:00', '08:00'] as $t) {
            $this->booking($today, $t, substr($t, 0, 2) + 1 .':00', 'offline', 100);
        }
        $this->booking($today, '14:00', '15:00', 'online', 100);

        $m = $this->insights()->assertOk()->json('data.milestones');

        $this->assertSame(4, $m['total']);
        $this->assertSame('First booking', $m['reached'][0]['label']);
        $this->assertNotNull($m['first_online']);
        $this->assertSame(10, $m['next']['count']);
        $this->assertSame(6, $m['next']['remaining']);
        $this->assertEquals(0.33, $m['next']['progress']);   // 1 → 10, at 4
    }

    public function test_the_milestone_ladder_is_an_admin_rule(): void
    {
        AppSetting::set(PlatformRules::storageKey('partner_insights.milestones'), '2,3', PlatformRules::GROUP);
        $today = BusinessClock::today();
        $this->booking($today, '06:00', '07:00', 'offline', 100);
        $this->booking($today, '07:00', '08:00', 'offline', 100);

        $m = $this->insights()->assertOk()->json('data.milestones');

        $this->assertSame(['2 bookings'], array_column($m['reached'], 'label'));
        $this->assertSame(3, $m['next']['count']);
    }

    public function test_matches_put_the_batting_line_on_the_side_that_is_batting(): void
    {
        $booking = $this->booking(BusinessClock::today(), '06:00', '07:00', 'online', 500);
        $match = \App\Models\LiveMatch::create([
            'sport' => 'cricket', 'status' => 'live', 'home' => 'HAB', 'away' => 'HHH', 'title' => 'HAB vs HHH',
        ]);
        $match->forceFill([
            'venue_booking_id' => $booking->id, 'score_text' => '25/0', 'overs' => '1.1',
            'home_score' => 0, 'away_score' => 25,
            'over_summary' => [['over' => 1, 'batting' => 'HHH', 'runs' => 25]],
        ])->save();

        $row = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/partner/matches')->assertOk()->json('data.confirmed.0');

        $this->assertSame(2, $row['battingTeam']);
        $this->assertSame('25/0', $row['score2']);
        $this->assertSame('0', $row['score1']);
    }

    private function visit(string $date, string $start, ?string $phone, string $channel = 'offline', ?User $player = null, string $name = 'Ravi'): Booking
    {
        return Booking::forceCreate([
            'booking_type' => 'venue', 'venue_id' => $this->venue->id,
            'user_id' => $player?->id ?? $this->partner->id,
            'channel' => $channel, 'status' => 'CONFIRMED', 'payment_status' => 'paid',
            'slot_date' => $date, 'start_time' => $start, 'end_time' => sprintf('%02d:00', (int) substr($start, 0, 2) + 1),
            'quantity' => 1, 'total_amount' => 500, 'amount_paid' => 500,
            'guest_name' => $channel === 'online' ? null : $name, 'guest_phone' => $channel === 'online' ? null : $phone,
        ]);
    }

    public function test_customers_split_into_new_and_returning_by_their_first_visit(): void
    {
        $today = Carbon::parse(BusinessClock::today());
        $monthStart = $today->copy()->startOfMonth();
        // Ravi first came last month and is back this month; Sita is new this month.
        $this->visit($monthStart->copy()->subDays(10)->toDateString(), '06:00', '98765 43210');
        $this->visit($monthStart->toDateString(), '07:00', '+91 98765 43210');
        $this->visit($monthStart->toDateString(), '08:00', '9123456789', name: 'Sita');
        // A walk-in with no phone can't be recognised next time: not counted.
        $this->visit($monthStart->toDateString(), '14:00', null, name: 'Anon');

        $c = $this->insights()->assertOk()->json('data.customers');

        $this->assertSame(1, $c['month']['new']);
        $this->assertSame(1, $c['month']['returning']);
        $this->assertSame(1, $c['month']['last']['new']);
        $this->assertSame(2, $c['total']);
        $this->assertSame(1, $c['came_back']);
    }

    public function test_the_same_phone_in_the_app_and_at_the_desk_is_one_customer(): void
    {
        $today = Carbon::parse(BusinessClock::today());
        $player = User::factory()->create(['name' => 'Ravi Kumar', 'phone' => '+919876543210']);
        $this->visit($today->copy()->startOfMonth()->subDays(3)->toDateString(), '06:00', '9876543210');
        $this->visit($today->toDateString(), '07:00', null, 'online', $player);

        $list = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/partner/insights/customers?period=month')->assertOk()->json('data');

        $this->assertSame('This month', $list['label']);
        $this->assertCount(1, $list['customers']);
        $row = $list['customers'][0];
        $this->assertSame('returning', $row['type']);
        $this->assertSame('Ravi Kumar', $row['name']);
        $this->assertSame('9876543210', $row['phone']);
        $this->assertSame(2, $row['visits']);
        $this->assertSame(1, $row['visits_period']);
        $this->assertEquals(1000, $row['spent']);
        $this->assertSame('app', $row['via']);
        $this->assertCount(2, $row['recent']);
    }

    public function test_another_partners_customers_never_show(): void
    {
        $other = User::factory()->create(['role' => 'partner', 'partner_type' => 'venue', 'status' => 'active']);
        $theirs = Venue::create(['name' => 'Not Mine', 'location' => 'X', 'price' => 500, 'is_active' => true, 'is_bookable' => true, 'partner_id' => $other->id]);
        Booking::forceCreate([
            'booking_type' => 'venue', 'venue_id' => $theirs->id, 'user_id' => $other->id, 'channel' => 'offline',
            'status' => 'CONFIRMED', 'payment_status' => 'paid', 'slot_date' => BusinessClock::today(),
            'start_time' => '06:00', 'end_time' => '07:00', 'quantity' => 1, 'total_amount' => 500, 'amount_paid' => 500,
            'guest_name' => 'Secret', 'guest_phone' => '9000000001',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/partner/insights/customers')->assertOk()->assertJsonCount(0, 'data.customers');
    }
}
