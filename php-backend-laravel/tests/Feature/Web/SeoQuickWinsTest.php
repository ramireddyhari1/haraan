<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\LiveMatch;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueCourt;
use App\Models\VenueSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class SeoQuickWinsTest extends TestCase
{
    use RefreshDatabase;

    private User $playerUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->playerUser = User::create([
            'name' => 'Karthik Raja',
            'email' => 'karthik@haraan.test',
            'password' => Hash::make('secret123'),
            'role' => 'USER',
            'status' => 'ACTIVE',
            'player_id' => 'HRNTEST01',
            'is_guest' => false,
            'player_role' => 'All-Rounder',
            'district' => 'Kadapa',
            'state' => 'Andhra Pradesh',
        ]);
    }

    public function test_lordicon_script_is_not_present_in_layout(): void
    {
        $response = $this->get('/events');

        $response->assertOk();
        $response->assertDontSee('cdn.lordicon.com/lordicon.js');
    }

    public function test_shared_footer_renders_on_public_pages_with_links(): void
    {
        $response = $this->get('/events');

        $response->assertOk();
        $response->assertSee('class="mfoot"', false);
        $response->assertSee('Terms of Service');
        $response->assertSee('Privacy Policy');
        $response->assertSee('ActionBoard');
        $response->assertSee('Partner Portal');
    }

    public function test_player_profile_has_dynamic_seo_metadata_and_schema(): void
    {
        $response = $this->get('/player/HRNTEST01');

        $response->assertOk();
        $response->assertSee('Karthik Raja — Cricket Profile');
        $response->assertSee('Karthik Raja (All-Rounder) from Kadapa, Andhra Pradesh', false);
        $response->assertSee('https://schema.org', false);
        $response->assertSee('"@type":"Person"', false);
    }

    public function test_actionboard_match_has_dynamic_seo_and_schema(): void
    {
        $match = LiveMatch::create([
            'title' => 'Kadapa Derby',
            'home' => 'KK',
            'away' => 'NS',
            'home_full' => 'Kadapa Kings',
            'away_full' => 'Nellore Stars',
            'home_score' => 45,
            'away_score' => 0,
            'status' => 'Live',
            'sport' => 'cricket',
            'competition' => 'Kadapa Premier League',
            'user_id' => $this->playerUser->id,
            'is_private' => false,
            'home_squad' => [['id' => 'HRNTEST01', 'name' => 'Karthik Raja']],
            'away_squad' => [['id' => null, 'name' => 'Opponent']],
        ]);

        $response = $this->get('/gamehub/actionboard/match/' . $match->id);

        $response->assertOk();
        $response->assertSee('Kadapa Kings vs Nellore Stars');
        $response->assertSee('Follow Kadapa Kings vs Nellore Stars (Cricket) on Haraan ActionBoard');
        $response->assertSee('"@type":"SportsEvent"', false);
        $response->assertSee('index,follow');
    }

    public function test_private_match_has_noindex_robots_for_creator(): void
    {
        $match = LiveMatch::create([
            'title' => 'Private Friendly',
            'home' => 'Alpha',
            'away' => 'Beta',
            'home_full' => 'Alpha XI',
            'away_full' => 'Beta XI',
            'home_score' => 10,
            'away_score' => 0,
            'status' => 'Live',
            'sport' => 'cricket',
            'user_id' => $this->playerUser->id,
            'is_private' => true,
        ]);

        $response = $this->actingAs($this->playerUser)->get('/gamehub/actionboard/match/' . $match->id);

        $response->assertOk();
        $response->assertSee('noindex,nofollow');
    }

    public function test_sitemap_includes_legal_pages_and_actionboard(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $response->assertSee(url('/legal/terms'));
        $response->assertSee(url('/legal/privacy'));
        $response->assertSee(url('/gamehub/actionboard'));
    }

    public function test_kadapa_city_and_sport_landing_pages_show_published_local_venues(): void
    {
        $venue = Venue::create([
            'name' => 'Kadapa Cricket Arena',
            'category' => 'Cricket',
            'sports' => ['Cricket', 'Football'],
            'city' => 'Kadapa',
            'location' => 'NGO Colony, Kadapa',
            'price' => 900,
            'rating' => 4.6,
            'images' => ['https://example.test/kadapa-arena.jpg'],
            'status' => 'published',
            'is_active' => true,
        ]);
        VenueCourt::create([
            'venue_id' => $venue->id,
            'name' => 'Main Turf',
            'sports' => ['Cricket'],
            'price' => 900,
            'is_active' => true,
        ]);
        VenueSlot::create([
            'venue_id' => $venue->id,
            'day' => VenueSlot::EVERY_DAY,
            'time' => '7:00 PM',
            'price' => 900,
            'capacity' => 1,
            'is_available' => true,
        ]);

        $this->get('/venues/kadapa')
            ->assertOk()
            ->assertSee('Turfs in Kadapa — Cricket, Box Cricket &amp; Badminton Courts', false)
            ->assertSee('Turfs in Kadapa | Book Cricket, Box Cricket &amp; Badminton | Haraan', false)
            ->assertSee('Kadapa Cricket Arena')
            ->assertSee('FAQPage')
            ->assertSee(url('/venues/kadapa'));

        $this->get('/venues/kadapa/cricket')
            ->assertOk()
            ->assertSee('Best Cricket Turfs in Kadapa')
            ->assertSee('Kadapa Cricket Arena')
            ->assertSee(url('/venues/kadapa/cricket'));

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee(url('/venues/kadapa'))
            ->assertSee(url('/venues/kadapa/cricket'));
    }
}
