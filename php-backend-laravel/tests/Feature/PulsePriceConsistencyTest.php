<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\Venue;
use App\Models\VenueCourt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Prod venue 6 (Blasters Cricket Club): venue price ₹781, court ₹800, every slot ₹800.
 * The phone page's bar and the city page said ₹781/hr, the About text and the Pulse list
 * said ₹800, and checkout charged ₹800. Every "from" price must be what checkout charges.
 */
class PulsePriceConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private function venue(array $slotPrices = [800, 800], ?array $courtPrices = null): Venue
    {
        $partner = User::factory()->create();
        $venue = Venue::create([
            'name' => 'Blasters Cricket Club', 'location' => 'Tadepalle', 'city' => 'Vijayawada', 'price' => 781,
            'is_active' => true, 'is_bookable' => true, 'partner_id' => $partner->id,
            'status' => 'published', 'images' => ['https://example.test/turf.jpg'],
        ]);
        $court = VenueCourt::create(['venue_id' => $venue->id, 'name' => 'TURF 1', 'price' => 800, 'is_active' => true]);
        foreach (['6:00 AM', '7:00 AM'] as $i => $time) {
            $venue->slots()->create([
                'day' => 'Every day', 'time' => $time, 'is_available' => true, 'capacity' => 1,
                'price' => $slotPrices[$i] ?? 0,
                'court_prices' => $courtPrices !== null ? [$court->id => $courtPrices[$i]] : null,
            ]);
        }

        return $venue->fresh(['courts', 'slots']);
    }

    public function test_from_price_is_the_slot_rate_not_the_bare_venue_price(): void
    {
        $this->assertSame(800, $this->venue()->fromPrice());
    }

    public function test_a_cheaper_slot_or_per_court_price_wins(): void
    {
        $this->assertSame(600, $this->venue([600, 900])->fromPrice());
        $this->assertSame(500, $this->venue([900, 900], [500, 700])->fromPrice());
    }

    public function test_an_unpriced_slot_falls_back_to_the_court_rate(): void
    {
        $this->assertSame(800, $this->venue([0, 0])->fromPrice());
    }

    public function test_the_venue_page_never_shows_the_bare_venue_price(): void
    {
        $venue = $this->venue();

        $html = $this->get('/gamehub/'.$venue->id)->assertRedirect()->baseResponse->headers->get('Location');
        $page = $this->get($html)->assertOk()->getContent();

        $this->assertStringContainsString('₹800', $page);
        $this->assertStringNotContainsString('₹781', $page);
        // The slot board carries the server's own rate for each slot.
        $this->assertStringContainsString('"rates":{"TURF 1":800}', $page);
    }

    public function test_the_pulse_list_and_city_page_agree(): void
    {
        $this->venue();

        $this->assertStringNotContainsString('₹781', $this->get('/gamehub')->assertOk()->getContent());
        $city = $this->get('/venues/vijayawada');
        if ($city->status() === 200) {
            $this->assertStringNotContainsString('₹781', $city->getContent());
            $this->assertStringContainsString('₹800', $city->getContent());
        }
    }
}
