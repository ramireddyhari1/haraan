<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Ad;
use App\Models\LiveMatch;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueCourt;
use App\Models\VenueSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The read paths every app/web viewer hits stay bounded and cheap as users grow:
 * page sizes are clamped, the shared lists are cached but never stale after an edit,
 * and the public ActionBoard no longer loads (or shows) every match ever played.
 */
class ScalabilityGuardsTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::create([
            'name' => 'Owner', 'email' => 'owner'.random_int(1, 999999).'@haraan.test',
            'password' => bcrypt('secret123'), 'role' => 'user', 'status' => 'active',
        ]);
    }

    public function test_events_page_size_is_clamped(): void
    {
        $this->getJson('/api/events?limit=100000')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 50);
    }

    public function test_venue_list_is_cached_but_an_edit_shows_immediately(): void
    {
        $venue = Venue::create([
            'name' => 'Shuttle House', 'location' => 'Labbipet', 'city' => 'Vijayawada', 'price' => 400,
            'images' => ['venues/a.jpg'], 'status' => 'published', 'published_at' => now(),
            'is_active' => true, 'is_bookable' => true, 'partner_id' => $this->owner()->id,
        ]);
        // Listed only once it has a court and a slot (Venue::scopePublished).
        VenueCourt::create(['venue_id' => $venue->id, 'name' => 'Court 1', 'price' => 400, 'is_active' => true]);
        VenueSlot::create(['venue_id' => $venue->id, 'day' => 'Daily', 'time' => '6:00 AM', 'price' => 400, 'is_available' => true]);

        $this->getJson('/api/venues')->assertOk()->assertJsonPath('data.0.name', 'Shuttle House');
        $this->assertTrue(cache()->has(Venue::LIST_CACHE_KEY));

        $venue->update(['name' => 'Smash Arena']);

        $this->getJson('/api/venues')->assertOk()->assertJsonPath('data.0.name', 'Smash Arena');
    }

    public function test_login_posters_are_cached_but_a_save_shows_immediately(): void
    {
        $poster = Ad::create([
            'title' => 'Monsoon Cup', 'image' => 'https://cdn.example/a.jpg',
            'placement' => 'login_poster', 'is_active' => true, 'sort_order' => 1,
        ]);

        $this->getJson('/api/login-posters')->assertOk()->assertJsonPath('0.title', 'Monsoon Cup');
        $this->assertTrue(cache()->has(Ad::LOGIN_POSTERS_CACHE_KEY));

        $poster->update(['title' => 'Winter Cup']);

        $this->getJson('/api/login-posters')->assertOk()->assertJsonPath('0.title', 'Winter Cup');
    }

    public function test_public_actionboard_never_lists_private_matches(): void
    {
        LiveMatch::create([
            'title' => 'Open game', 'home' => 'KK', 'away' => 'NS', 'home_full' => 'Kadapa Kings', 'away_full' => 'North Stars',
            'home_score' => 0, 'away_score' => 0, 'status' => 'Live', 'sport' => 'cricket', 'competition' => 'Open Derby Cup',
        ]);
        LiveMatch::create([
            'title' => 'Private game', 'home' => 'PX', 'away' => 'PY', 'home_full' => 'Secret Strikers', 'away_full' => 'Hidden Hawks',
            'home_score' => 0, 'away_score' => 0, 'status' => 'Live', 'sport' => 'cricket', 'competition' => 'Members Only Cup',
            'is_private' => true,
        ]);

        $this->get('/gamehub/actionboard')
            ->assertOk()
            ->assertSee('Open Derby Cup')
            ->assertDontSee('Members Only Cup');
    }
}
