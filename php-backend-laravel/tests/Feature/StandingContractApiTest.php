<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\StandingContract;
use App\Models\StandingContractSession;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueCourt;
use App\Support\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StandingContractApiTest extends TestCase
{
    use RefreshDatabase;

    private User $partner;
    private Venue $venue;
    private VenueCourt $court;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->partner = User::create([
            'name' => 'Partner Admin',
            'email' => 'partner@test.com',
            'password' => Hash::make('password'),
            'role' => 'PARTNER',
            'partner_type' => 'venue',
            'status' => 'active',
        ]);

        $this->venue = Venue::create([
            'name' => 'Apex Sports Club',
            'location' => 'Madhapur',
            'price' => 1200,
            'is_active' => true,
            'is_bookable' => true,
            'partner_id' => $this->partner->id,
        ]);

        $this->court = VenueCourt::create([
            'venue_id' => $this->venue->id,
            'name' => 'Badminton Court 1',
            'price' => 600,
            'is_active' => true,
        ]);

        $secret = config('app.jwt_secret') ?: env('JWT_SECRET', 'change_me');
        $this->token = JwtService::issueForUser($this->partner, $secret);
    }

    private function authHeaders(): array
    {
        return [
            'Authorization' => "Bearer {$this->token}",
            'Accept' => 'application/json',
        ];
    }

    public function test_can_check_conflicts_and_find_overlap(): void
    {
        // Seed an existing booking on next Monday 19:00 - 20:00
        $nextMonday = Carbon::now()->next(Carbon::MONDAY);
        Booking::create([
            'quantity' => 1,
            'total_amount' => 600,
            'status' => 'CONFIRMED',
            'booking_type' => 'venue',
            'user_id' => $this->partner->id,
            'venue_id' => $this->venue->id,
            'venue_court_id' => $this->court->id,
            'slot_date' => $nextMonday->toDateString(),
            'start_time' => '19:00',
            'end_time' => '20:00',
            'channel' => 'offline',
            'guest_name' => 'Single Player Walkin',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/partner/venues/{$this->venue->id}/standing-contracts/check-conflicts", [
                'court_id' => $this->court->id,
                'day_of_week' => 'monday',
                'start_time' => '19:00',
                'end_time' => '20:00',
                'active_from' => $nextMonday->toDateString(),
                'weeks' => 4,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('is_clear', false)
            ->assertJsonPath('conflicting_sessions_count', 1);

        $this->assertNotEmpty($response->json('conflicts'));
    }

    public function test_can_create_standing_contract_and_materialize_sessions(): void
    {
        $startDate = Carbon::now()->next(Carbon::TUESDAY)->toDateString();

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/partner/venues/{$this->venue->id}/standing-contracts", [
                'customer_name' => 'Hyderabad Smashers Club',
                'customer_phone' => '9876543210',
                'court_id' => $this->court->id,
                'sport' => 'Badminton',
                'day_of_week' => 'tuesday',
                'start_time' => '20:00',
                'end_time' => '21:00',
                'duration_minutes' => 60,
                'price_per_session' => 700.00,
                'security_deposit' => 2000.00,
                'advance_paid' => 700.00,
                'active_from' => $startDate,
                'auto_renew' => true,
                'auto_skip_conflicts' => true,
                'notes' => 'Weekly Tuesday night match',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('contract.customer_name', 'Hyderabad Smashers Club')
            ->assertJsonPath('contract.security_deposit', 2000);

        $contractId = $response->json('contract.id');
        $this->assertDatabaseHas('standing_contracts', [
            'id' => $contractId,
            'customer_name' => 'Hyderabad Smashers Club',
            'status' => 'active',
        ]);

        // Assert sessions generated ahead
        $sessionsCount = StandingContractSession::where('standing_contract_id', $contractId)->count();
        $this->assertGreaterThanOrEqual(4, $sessionsCount);

        // Assert child bookings exist in bookings table
        $bookingsCount = Booking::where('standing_contract_id', $contractId)->count();
        $this->assertGreaterThanOrEqual(4, $bookingsCount);
    }

    public function test_can_skip_session_and_free_booking(): void
    {
        $startDate = Carbon::now()->next(Carbon::WEDNESDAY)->toDateString();

        $createRes = $this->withHeaders($this->authHeaders())
            ->postJson("/api/partner/venues/{$this->venue->id}/standing-contracts", [
                'customer_name' => 'Wednesday Warriors',
                'customer_phone' => '9888877777',
                'court_id' => $this->court->id,
                'day_of_week' => 'wednesday',
                'start_time' => '07:00',
                'end_time' => '08:00',
                'price_per_session' => 500.00,
                'active_from' => $startDate,
            ]);

        $contractId = $createRes->json('contract.id');
        $firstSession = StandingContractSession::where('standing_contract_id', $contractId)->first();
        $this->assertNotNull($firstSession);
        $this->assertNotNull($firstSession->booking_id);

        // Skip this session
        $skipRes = $this->withHeaders($this->authHeaders())
            ->postJson("/api/partner/venues/{$this->venue->id}/standing-contracts/{$contractId}/skip-date", [
                'date' => $firstSession->session_date->toDateString(),
                'reason' => 'Venue holiday',
            ]);

        $skipRes->assertStatus(200)
            ->assertJsonPath('session.attendance_status', StandingContractSession::ATTENDANCE_SKIPPED_HOLIDAY);

        $firstSession->refresh();
        $this->assertNull($firstSession->booking_id);
    }

    public function test_can_track_attendance_and_detect_churn_risk(): void
    {
        $startDate = Carbon::now()->subWeeks(3)->next(Carbon::THURSDAY)->toDateString();

        $contract = StandingContract::create([
            'venue_id' => $this->venue->id,
            'venue_court_id' => $this->court->id,
            'customer_name' => 'Churn Risk Academy',
            'customer_phone' => '9111122222',
            'day_of_week' => 'thursday',
            'start_time' => '18:00',
            'end_time' => '19:00',
            'price_per_session' => 600,
            'active_from' => $startDate,
            'status' => 'active',
        ]);

        $s1 = StandingContractSession::create([
            'standing_contract_id' => $contract->id,
            'session_date' => Carbon::now()->subWeeks(2)->toDateString(),
            'start_time' => '18:00',
            'end_time' => '19:00',
            'venue_court_id' => $this->court->id,
            'price' => 600,
            'attendance_status' => 'absent',
        ]);

        $s2 = StandingContractSession::create([
            'standing_contract_id' => $contract->id,
            'session_date' => Carbon::now()->subWeeks(1)->toDateString(),
            'start_time' => '18:00',
            'end_time' => '19:00',
            'venue_court_id' => $this->court->id,
            'price' => 600,
            'attendance_status' => 'absent',
        ]);

        $contract->recalculateMetrics();

        $this->assertEquals(2, $contract->consecutive_missed_sessions);
        $this->assertTrue($contract->is_at_risk);
        $this->assertEquals('at_risk', $contract->status);

        // Fetch dashboard and assert alert appears
        $dashRes = $this->withHeaders($this->authHeaders())
            ->getJson("/api/partner/venues/{$this->venue->id}/standing-contracts/dashboard");

        $dashRes->assertStatus(200)
            ->assertJsonPath('metrics.at_risk_contracts', 1);
    }

    public function test_can_record_payment_and_audit_log(): void
    {
        $contract = StandingContract::create([
            'venue_id' => $this->venue->id,
            'venue_court_id' => $this->court->id,
            'customer_name' => 'Payment Group',
            'customer_phone' => '9999900000',
            'day_of_week' => 'friday',
            'start_time' => '17:00',
            'end_time' => '18:00',
            'price_per_session' => 600,
            'security_deposit' => 1000,
            'balance_due' => 600,
            'active_from' => Carbon::now()->toDateString(),
            'status' => 'active',
        ]);

        $res = $this->withHeaders($this->authHeaders())
            ->postJson("/api/partner/venues/{$this->venue->id}/standing-contracts/{$contract->id}/payment", [
                'amount' => 600.00,
                'payment_type' => 'monthly_fee',
                'method' => 'upi',
                'notes' => 'GPay reference #GPAY123',
            ]);

        $res->assertStatus(200)
            ->assertJsonPath('payment.amount', 600)
            ->assertJsonPath('payment.method', 'upi');

        $contract->refresh();
        $this->assertEquals(0.00, (float) $contract->balance_due);
        $this->assertDatabaseHas('standing_contract_logs', [
            'standing_contract_id' => $contract->id,
            'action' => 'payment_received',
        ]);
    }
}
