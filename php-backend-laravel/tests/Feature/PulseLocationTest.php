<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\Venue;
use App\Models\VenueCourt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Pulse "near you" rules (web /gamehub + app /api/venues):
 *  - nothing beyond 50 km is listed as local; the empty state names the nearest instead,
 *  - a venue with no pin is placed from its address on Google Maps,
 *  - one city rule everywhere: city field or area text, never the street address.
 */
class PulseLocationTest extends TestCase
{
    use RefreshDatabase;

    private function venue(array $attrs): Venue
    {
        $venue = Venue::create(array_merge([
            'price' => 800, 'is_active' => true, 'is_bookable' => true,
            'partner_id' => User::factory()->create()->id, 'status' => 'published',
            'images' => ['https://example.test/turf.jpg'],
        ], $attrs));
        VenueCourt::create(['venue_id' => $venue->id, 'name' => 'TURF 1', 'price' => 800, 'is_active' => true]);
        $venue->slots()->create(['day' => 'Every day', 'time' => '6:00 AM', 'is_available' => true, 'capacity' => 1]);

        return $venue;
    }

    private function pulse(string $cookies): string
    {
        $jar = [];
        foreach (explode(';', $cookies) as $pair) {
            [$k, $v] = array_map('trim', explode('=', $pair, 2));
            $jar[$k] = $v;
        }

        return $this->withUnencryptedCookies($jar)->get('/gamehub')->assertOk()->getContent();
    }

    public function test_far_city_lists_nothing_as_local_and_names_the_nearest(): void
    {
        config(['services.google_maps.key' => '']);
        $this->venue(['name' => 'Blasters Cricket Club', 'location' => 'Tadepalle', 'city' => 'Vijayawada', 'latitude' => 16.4817, 'longitude' => 80.612]);

        // Chennai centre, ~380 km away.
        $html = $this->pulse('haraan_city=Chennai; hb_geo=13.0827,80.2707');

        $this->assertStringNotContainsString('class="mhub__vcard', $html);
        $this->assertStringContainsString('Nearest on Haraan: Blasters Cricket Club', $html);
    }

    public function test_near_viewer_sees_the_venue_with_distance(): void
    {
        config(['services.google_maps.key' => '']);
        $this->venue(['name' => 'Blasters Cricket Club', 'location' => 'Tadepalle', 'city' => 'Vijayawada', 'latitude' => 16.4817, 'longitude' => 80.612]);

        $html = $this->pulse('haraan_city=Vijayawada; hb_geo=16.5062,80.6480');

        $this->assertStringContainsString('Blasters Cricket Club', $html);
        $this->assertStringNotContainsString('Nearest on Haraan', $html);
    }

    public function test_unpinned_venue_is_placed_from_its_address(): void
    {
        config(['services.google_maps.key' => 'test-key']);
        Http::fake(['maps.googleapis.com/*' => Http::response([
            'results' => [['geometry' => ['location' => ['lat' => 16.43, 'lng' => 80.57]]]],
        ])]);
        $this->venue(['name' => 'Cover drive kitchen', 'location' => 'Tenali road Atmakur', 'city' => 'Mangalagiri']);

        $html = $this->pulse('haraan_city=Vijayawada; hb_geo=16.5062,80.6480');
        $this->assertStringContainsString('Cover drive kitchen', $html);

        $card = collect($this->getJson('/api/venues')->assertOk()->json('data'))->firstWhere('name', 'Cover drive kitchen');
        $this->assertEqualsWithDelta(16.43, $card['latitude'], 0.001);
        $this->assertTrue($card['location_estimated']);
        $this->assertSame('Mangalagiri', $card['city']);
    }

    public function test_control_cities_page_suggests_venue_cities_with_a_point(): void
    {
        config(['services.google_maps.key' => '']);
        $this->venue(['name' => 'A', 'location' => 'Benz Circle', 'city' => 'Vijayawada', 'latitude' => 16.50, 'longitude' => 80.64]);
        $this->venue(['name' => 'B', 'location' => 'Labbipet', 'city' => 'vijayawada', 'latitude' => 16.52, 'longitude' => 80.66]);

        $cities = (new \App\Filament\Pages\Cities)->venueCities();

        $this->assertSame('Vijayawada', $cities[0]['name']);
        $this->assertSame(2, $cities[0]['venues']);
        $this->assertEqualsWithDelta(16.51, $cities[0]['lat'], 0.001);
    }

    public function test_city_rule_ignores_the_street_address(): void
    {
        config(['services.google_maps.key' => '']);
        $this->venue([
            'name' => 'Road Side Nets', 'location' => 'Benz Circle', 'city' => 'Vijayawada',
            'address' => '12 Guntur Road, Vijayawada',
        ]);

        $this->assertStringNotContainsString('Road Side Nets', $this->pulse('haraan_city=Guntur'));
        $this->assertStringContainsString('Road Side Nets', $this->pulse('haraan_city=Vijayawada'));
    }
}
