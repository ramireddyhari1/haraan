<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\LiveMatch;
use App\Models\MatchGround;
use App\Models\Venue;
use App\Support\SeoUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The venue SEO pages: search titles, the "near {area}" pages, and the matches-played
 * block on a venue page.
 */
final class VenueSeoPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        // Four listed venues around Benz Circle (within ~2 km), one across the river (~8 km).
        $this->listed('Crazy Turf', 'Benz Circle', ['Football'], 16.4990, 80.6560, ['open' => '06:00', 'close' => '23:00']);
        $this->listed('Smash Arena', 'Benz Circle', ['Badminton'], 16.5010, 80.6580);
        $this->listed('Gunadala Box', 'Gunadala', ['Cricket'], 16.5150, 80.6610, ['open' => '05:30', 'close' => '22:30']);
        $this->listed('Patamata Sports', 'Patamata', ['Cricket', 'Football'], 16.4930, 80.6650);
        $this->listed('River Turf', 'tadepalle', ['Cricket'], 16.4800, 80.6000);
    }

    /** @param list<string> $sports */
    private function listed(string $name, string $area, array $sports, float $lat, float $lng, ?array $hours = null): Venue
    {
        return Venue::create([
            'name' => $name,
            'city' => 'Vijayawada',
            'location' => $area,
            'address' => '1-2-3, ' . $area . ', Vijayawada',
            'category' => $sports[0],
            'sports' => $sports,
            'latitude' => $lat,
            'longitude' => $lng,
            'contact_phone' => '9876543210',
            'status' => Venue::STATUS_LISTED,
            'is_active' => false,
            'is_bookable' => false,
            'hours_json' => $hours ? array_map(fn () => $hours, array_flip(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'])) : null,
        ]);
    }

    public function test_city_page_title_leads_with_turfs_and_courts(): void
    {
        $res = $this->get('/venues/vijayawada');

        $res->assertOk();
        $res->assertSee('<title>Turfs &amp; Courts in Vijayawada: 5 Venues, Timings &amp; Photos | Haraan</title>', false);
        $res->assertSee('<meta name="description" content="5 sports venues in Vijayawada for cricket, football &amp; badminton, including Benz Circle and Gunadala — timings, photos &amp; directions; 2 open by 6 AM.">', false);
    }

    public function test_sport_page_title_names_the_sport(): void
    {
        $this->get('/venues/vijayawada/cricket')
            ->assertOk()
            ->assertSee('<title>Cricket Grounds in Vijayawada: 3 Venues, Timings &amp; Photos | Haraan</title>', false);
    }

    public function test_listed_venue_title_and_snippet_say_what_it_is(): void
    {
        $venue = Venue::where('name', 'Crazy Turf')->first();
        $res = $this->get(SeoUrl::venue($venue));

        $res->assertOk();
        $res->assertSee('<title>Crazy Turf, Benz Circle – Football Turf in Vijayawada | Haraan</title>', false);
        $res->assertSee('Crazy Turf is a football turf in Benz Circle, Vijayawada.', false);
        $res->assertDontSee('does not take bookings on Haraan yet', false);
        // FAQ shown on the page and as markup.
        $res->assertSee('What are the timings of Crazy Turf?');
        $res->assertSee('"@type":"FAQPage"', false);
        // Nearby venues by distance, nearest first.
        $res->assertSee('Near Crazy Turf');
    }

    public function test_near_page_lists_venues_within_three_km_nearest_first(): void
    {
        $res = $this->get('/venues/vijayawada/near/benz-circle');

        $res->assertOk();
        $res->assertSee('Turfs &amp; courts near Benz Circle, Vijayawada', false);
        $res->assertSee('index,follow', false);
        $res->assertSeeInOrder(['Crazy Turf', 'Smash Arena', 'Patamata Sports', 'Gunadala Box']);
        $res->assertDontSee('River Turf');
        $res->assertSee('Which is the nearest turf to Benz Circle, Vijayawada?');
    }

    public function test_lowercase_area_is_one_area_and_thin_page_is_noindex(): void
    {
        $this->get('/venues/vijayawada/near/tadepalle')
            ->assertOk()
            ->assertSee('near Tadepalle', false)
            ->assertSee('noindex,follow', false);
    }

    public function test_unknown_area_is_404(): void
    {
        $this->get('/venues/vijayawada/near/nowhere')->assertNotFound();
    }

    public function test_sitemap_lists_indexable_near_pages_only(): void
    {
        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString('/venues/vijayawada/near/', $xml);
        $this->assertStringNotContainsString('/venues/vijayawada/near/tadepalle', $xml);
    }

    public function test_city_page_links_to_near_pages(): void
    {
        $this->get('/venues/vijayawada')->assertOk()->assertSee('/venues/vijayawada/near/', false);
    }

    public function test_venue_page_shows_matches_scored_at_its_ground(): void
    {
        $venue = Venue::where('name', 'Gunadala Box')->first();
        $ground = MatchGround::create([
            'name_key' => MatchGround::nameKey('Gunadala Box'),
            'name' => 'Gunadala Box',
            'district' => 'Vijayawada',
            'latitude' => 16.5151,
            'longitude' => 80.6612,
            'matches_played' => 2,
        ]);
        foreach (['Lions', 'Tigers'] as $i => $team) {
            LiveMatch::create([
                'title' => $team . ' game', 'home' => 'A' . $i, 'away' => 'B' . $i,
                'home_full' => $team . ' XI', 'away_full' => 'Kings XI',
                'status' => 'finished', 'sport' => 'cricket', 'is_private' => false, 'ground_id' => $ground->id,
            ]);
        }

        $res = $this->get(SeoUrl::venue($venue));

        $res->assertOk();
        $res->assertSee('Played here on Haraan');
        $res->assertSee('matches scored here');
        $res->assertSee('Lions XI');
        $res->assertSee('Are cricket matches played at Gunadala Box?');
    }

    public function test_no_activity_block_without_real_activity(): void
    {
        $venue = Venue::where('name', 'Smash Arena')->first();

        $this->get(SeoUrl::venue($venue))->assertOk()->assertDontSee('Played here on Haraan');
    }
}
