<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueBusinessSuggestion;
use App\Models\VenueCourt;
use App\Models\VenueOperationsAlert;
use App\Support\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class OwnerOperationsApiTest extends TestCase
{
    use RefreshDatabase;

    private User $partner;
    private Venue $venue;
    private VenueCourt $court;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->partner = User::factory()->create([
            'role'         => 'partner',
            'partner_type' => 'venue',
            'status'       => 'active',
        ]);

        $this->venue = Venue::create([
            'name'        => 'Apex Arena Operations',
            'location'    => 'Madhapur',
            'price'       => 1000,
            'is_active'   => true,
            'is_bookable' => true,
            'partner_id'  => $this->partner->id,
        ]);

        $this->court = VenueCourt::create([
            'venue_id'  => $this->venue->id,
            'name'      => 'Court 1 Turf',
            'kind'      => 'football',
            'price'     => 1200,
            'is_active' => true,
            'sports'    => ['football', 'cricket'],
        ]);

        $secret = config('app.jwt_secret') ?: env('JWT_SECRET', 'change_me');
        $this->token = JwtService::issueForUser($this->partner, $secret);
    }

    public function test_operations_overview_and_revenue_metrics(): void
    {
        Booking::create([
            'user_id'        => $this->partner->id,
            'venue_id'       => $this->venue->id,
            'venue_court_id' => $this->court->id,
            'slot_date'      => Carbon::today()->toDateString(),
            'start_time'     => '18:00:00',
            'end_time'       => '19:00:00',
            'total_amount'   => 1200,
            'status'         => 'confirmed',
            'channel'        => 'whatsapp',
            'quantity'       => 1,
        ]);

        $resp = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson("/api/partner/venues/{$this->venue->id}/operations/overview");

        $resp->assertStatus(200);
        $resp->assertJsonPath('status', 'success');
        $resp->assertJsonStructure([
            'data' => [
                'revenue',
                'occupancy',
                'staff',
                'funnel',
                'leakage_alerts',
                'ai_suggestions',
            ],
        ]);

        $this->assertGreaterThanOrEqual(1200, $resp->json('data.revenue.today_revenue'));
    }

    public function test_occupancy_heatmap_matrix(): void
    {
        $resp = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson("/api/partner/venues/{$this->venue->id}/operations/occupancy");

        $resp->assertStatus(200);
        $resp->assertJsonPath('status', 'success');

        $heatmap = $resp->json('data.heatmap');
        $this->assertCount(7, $heatmap); // 7 days: Mon to Sun
        $this->assertCount(18, $heatmap[0]['hours']); // 18 hours: 06:00 to 23:00
    }

    public function test_resolve_revenue_leakage_alert(): void
    {
        $alert = VenueOperationsAlert::create([
            'venue_id'    => $this->venue->id,
            'alert_type'  => 'expired_hold_uncontacted',
            'severity'    => 'medium',
            'title'       => '3 Abandoned Holds',
            'description' => 'Follow up with abandoned hold customers.',
            'is_resolved' => false,
        ]);

        $resp = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/partner/venues/{$this->venue->id}/operations/alerts/{$alert->id}/resolve");

        $resp->assertStatus(200);

        $alert->refresh();
        $this->assertTrue($alert->is_resolved);
    }

    public function test_apply_ai_business_suggestion(): void
    {
        $suggestion = VenueBusinessSuggestion::create([
            'venue_id'                 => $this->venue->id,
            'category'                 => 'pricing',
            'title'                    => 'Off-Peak Discount',
            'rationale'                => 'Boost afternoon occupancy',
            'projected_revenue_impact' => 15000,
            'action_payload'           => [
                'action_type'  => 'create_pricing_rule',
                'rule_name'    => 'Test AI Rule',
                'pricing_mode' => 'multiplier',
                'factor'       => 0.85,
            ],
            'status'                   => 'pending',
        ]);

        $resp = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/partner/venues/{$this->venue->id}/operations/suggestions/{$suggestion->id}/apply");

        $resp->assertStatus(200);

        $suggestion->refresh();
        $this->assertEquals('applied', $suggestion->status);

        $this->assertDatabaseHas('pricing_rules', [
            'venue_id' => $this->venue->id,
            'name'     => 'Test AI Rule',
        ]);
    }
}
