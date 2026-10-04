<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\Venue;
use App\Models\VenueCourt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * A court once carried a peak price of -7 (typed into /control with no floor), and the
 * app and the web showed players "₹-7". A peak rate of zero or less is a typo: it must
 * never be charged and never be shown.
 */
class VenueNegativePeakPriceTest extends TestCase
{
    use RefreshDatabase;

    private function court(int $peak): VenueCourt
    {
        $partner = User::factory()->create();
        $venue = Venue::create([
            'name' => 'Peak Turf', 'location' => 'Vaddeswaram', 'price' => 5,
            'is_active' => true, 'is_bookable' => true, 'partner_id' => $partner->id,
            'status' => 'published', 'images' => ['https://example.test/turf.jpg'],
        ]);
        $venue->slots()->create(['day' => 'Every Day', 'time' => '6:00 PM', 'is_available' => true, 'capacity' => 1]);

        return VenueCourt::create([
            'venue_id' => $venue->id, 'name' => 'VADI', 'price' => 5, 'is_active' => true,
            'peak_price' => $peak, 'peak_days' => ['Fri'], 'peak_start' => '16:57', 'peak_end' => '22:57',
        ]);
    }

    public function test_a_negative_peak_is_never_charged(): void
    {
        $court = $this->court(-7);
        $friday = Carbon::parse('next friday');

        $this->assertFalse($court->isPeak($friday, '18:00'));
        $this->assertSame(5, $court->rateFor($friday, '18:00', 5));
        $this->assertNull($court->peakRate());
    }

    public function test_a_real_peak_still_applies(): void
    {
        $court = $this->court(900);
        $friday = Carbon::parse('next friday');

        $this->assertTrue($court->isPeak($friday, '18:00'));
        $this->assertSame(900, $court->rateFor($friday, '18:00', 5));
        $this->assertSame(5, $court->rateFor($friday, '10:00', 5));
    }

    public function test_the_public_venue_api_never_sends_a_negative_peak(): void
    {
        $court = $this->court(-7);

        $json = $this->getJson('/api/venues/' . $court->venue_id)->assertOk()->json();
        $courts = $json['data']['courts'] ?? $json['courts'] ?? [];

        $this->assertNotEmpty($courts);
        foreach ($courts as $c) {
            $this->assertNull($c['peak_price']);
        }
    }
}
