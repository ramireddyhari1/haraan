<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use App\Models\Venue;
use App\Support\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The partner report behind the app's Reports screen and its PDF. */
final class PartnerBookingReportTest extends TestCase
{
    use RefreshDatabase;

    private User $partner;

    private Venue $venue;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->partner = User::factory()->create(['role' => 'partner', 'partner_type' => 'venue', 'status' => 'active', 'name' => 'Our Center']);
        $this->venue = Venue::create(['name' => 'Report Turf', 'location' => 'Madhapur', 'price' => 500, 'partner_id' => $this->partner->id]);

        $secret = config('app.jwt_secret') ?: env('JWT_SECRET', 'change_me');
        $this->token = JwtService::issueForUser($this->partner, $secret);
    }

    private function booking(string $bookedOn, string $playedOn, float $paid): Booking
    {
        $b = Booking::forceCreate([
            'booking_type' => 'venue', 'venue_id' => $this->venue->id, 'user_id' => $this->partner->id,
            'channel' => 'online', 'status' => 'CONFIRMED', 'payment_status' => $paid >= 500 ? 'paid' : 'unpaid',
            'slot_date' => $playedOn, 'start_time' => '07:00', 'end_time' => '08:00',
            'quantity' => 1, 'total_amount' => 500, 'amount_paid' => $paid,
        ]);
        $b->forceFill(['created_at' => $bookedOn.' 10:00:00'])->save();

        return $b;
    }

    private function report(string $query)
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/partner/reports/bookings?format=json&'.$query);
    }

    public function test_rows_carry_what_was_paid_and_the_business_name(): void
    {
        $this->booking('2026-09-01', '2026-09-05', 500);

        $res = $this->report('from=2026-09-01&to=2026-09-30')->assertOk();

        $res->assertJsonPath('partner', 'Our Center')
            ->assertJsonPath('rows.0.amount_paid', '500.00')
            ->assertJsonPath('rows.0.payment_status', 'paid');
    }

    public function test_a_period_can_be_read_by_booking_date_or_by_play_date(): void
    {
        // Booked in August for a game in September.
        $this->booking('2026-08-28', '2026-09-02', 0);

        $this->report('from=2026-09-01&to=2026-09-30&by=booked')->assertOk()->assertJsonCount(0, 'rows');
        $this->report('from=2026-09-01&to=2026-09-30&by=played')->assertOk()->assertJsonCount(1, 'rows');
    }

    public function test_the_csv_keeps_its_old_columns_first(): void
    {
        $this->booking('2026-09-01', '2026-09-05', 500);

        $csv = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->get('/api/partner/reports/bookings?from=2026-09-01&to=2026-09-30')->assertOk()->getContent();

        $this->assertStringStartsWith('"Booking ID","Booked At",Type,Item', $csv);
        $this->assertStringContainsString('"Amount Paid","Payment Status"', strtok($csv, "\n"));
    }
}
