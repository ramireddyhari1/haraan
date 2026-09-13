<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PricingRule;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueCourt;
use App\Support\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PricingMatrixApiTest extends TestCase
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
            'email' => 'partner_pricing@test.com',
            'password' => Hash::make('password'),
            'role' => 'PARTNER',
            'partner_type' => 'venue',
            'status' => 'active',
        ]);

        $this->venue = Venue::create([
            'name' => 'Apex Arena Hyderabad',
            'location' => 'Madhapur',
            'price' => 1200,
            'is_active' => true,
            'is_bookable' => true,
            'partner_id' => $this->partner->id,
        ]);

        $this->court = VenueCourt::create([
            'venue_id' => $this->venue->id,
            'name' => 'Turf A',
            'price' => 1000,
            'is_active' => true,
        ]);

        $secret = config('app.jwt_secret') ?: env('JWT_SECRET', 'change_me');
        $this->token = JwtService::issueForUser($this->partner, $secret);
    }

    public function test_can_fetch_dashboard_and_weekly_matrix(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/partner/venues/{$this->venue->id}/pricing/matrix?court_id={$this->court->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'court_id',
                'court_name',
                'base_rate',
                'min_rate',
                'max_rate',
                'average_rate',
                'matrix' => [
                    'monday' => ['day_name', 'date', 'slots'],
                    'sunday' => ['day_name', 'date', 'slots'],
                ],
            ]);

        $this->assertSame(1000, $response->json('base_rate'));
    }

    public function test_can_create_absolute_delta_and_percentage_pricing_rules(): void
    {
        // 1. Create absolute rule for Friday Evening
        $res1 = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/partner/venues/{$this->venue->id}/pricing/rules", [
                'name'         => 'Friday Prime Rush',
                'rule_type'    => 'time_of_day',
                'weekdays'     => ['friday'],
                'start_time'   => '18:00',
                'end_time'     => '22:00',
                'pricing_mode' => 'absolute',
                'amount'       => 1500,
                'priority'     => 10,
            ]);

        $res1->assertStatus(201)
            ->assertJsonPath('rule.amount', 1500)
            ->assertJsonPath('rule.pricing_mode', 'absolute');

        // 2. Create delta surge rule
        $res2 = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/partner/venues/{$this->venue->id}/pricing/rules", [
                'name'         => 'Weekend Floodlight Surge',
                'rule_type'    => 'time_of_day',
                'weekdays'     => ['saturday', 'sunday'],
                'start_time'   => '19:00',
                'end_time'     => '23:00',
                'pricing_mode' => 'delta',
                'amount'       => 300,
                'priority'     => 12,
            ]);

        $res2->assertStatus(201)
            ->assertJsonPath('rule.amount', 300)
            ->assertJsonPath('rule.pricing_mode', 'delta');

        $this->assertDatabaseCount('pricing_rules', 2);
        $this->assertDatabaseCount('pricing_rule_logs', 2);
    }

    public function test_rate_for_on_venue_court_evaluates_active_rules(): void
    {
        // Add rule: 20% discount on Tuesday mornings
        PricingRule::create([
            'venue_id'        => $this->venue->id,
            'venue_court_id'  => $this->court->id,
            'name'            => 'Tuesday Morning Discount',
            'rule_type'       => 'time_of_day',
            'weekdays'        => ['tuesday'],
            'start_time'      => '08:00',
            'end_time'        => '12:00',
            'pricing_mode'    => 'percentage',
            'amount'          => -20.0, // 20% off base 1000 = 800
            'priority'        => 20,
            'is_active'       => true,
        ]);

        // Next Tuesday at 09:00
        $tuesday = Carbon::now()->next(Carbon::TUESDAY);
        $rate = $this->court->rateFor($tuesday, '09:00', 1200);

        // 1000 - 20% = 800
        $this->assertSame(800, $rate);

        // Outside rule window (14:00) falls back to base rate 1000
        $rateOutside = $this->court->rateFor($tuesday, '14:00', 1200);
        $this->assertSame(1000, $rateOutside);
    }

    public function test_priority_resolution_and_guards(): void
    {
        // Low priority rule (priority 5): Flat 1200
        PricingRule::create([
            'venue_id'        => $this->venue->id,
            'name'            => 'General Friday',
            'weekdays'        => ['friday'],
            'start_time'      => '18:00',
            'end_time'        => '22:00',
            'pricing_mode'    => 'absolute',
            'amount'          => 1200,
            'priority'        => 5,
            'is_active'       => true,
        ]);

        // High priority court-specific rule with ceiling guard (priority 25): Delta +1000 capped at 1600
        PricingRule::create([
            'venue_id'        => $this->venue->id,
            'venue_court_id'  => $this->court->id,
            'name'            => 'Court A Friday Super Surge',
            'weekdays'        => ['friday'],
            'start_time'      => '18:00',
            'end_time'        => '22:00',
            'pricing_mode'    => 'delta',
            'amount'          => 1000, // 1000 + 1000 = 2000
            'max_price'       => 1600, // Capped at 1600
            'priority'        => 25,
            'is_active'       => true,
        ]);

        $friday = Carbon::now()->next(Carbon::FRIDAY);
        $rate = $this->court->rateFor($friday, '19:00', 1200);

        $this->assertSame(1600, $rate);
    }

    public function test_can_toggle_and_delete_rule(): void
    {
        $rule = PricingRule::create([
            'venue_id'        => $this->venue->id,
            'name'            => 'Test Rule',
            'weekdays'        => ['monday'],
            'start_time'      => '10:00',
            'end_time'        => '12:00',
            'pricing_mode'    => 'absolute',
            'amount'          => 700,
            'priority'        => 10,
            'is_active'       => true,
        ]);

        // Toggle
        $toggleRes = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/partner/venues/{$this->venue->id}/pricing/rules/{$rule->id}/toggle");

        $toggleRes->assertStatus(200)
            ->assertJsonPath('is_active', false);

        // Delete
        $delRes = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->deleteJson("/api/partner/venues/{$this->venue->id}/pricing/rules/{$rule->id}");

        $delRes->assertStatus(200);
        $this->assertDatabaseMissing('pricing_rules', ['id' => $rule->id]);
    }
}
