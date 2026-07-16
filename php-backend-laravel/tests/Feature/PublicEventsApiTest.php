<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * /api/events is public and unauthenticated — it is the app's Events tab.
 *
 * It used to apply no status filter, so every draft an admin saved was live in the
 * app while the website (published-only) never showed it. These pin the endpoint shut.
 */
class PublicEventsApiTest extends TestCase
{
    use RefreshDatabase;

    private function partnerId(): int
    {
        // events.partner_id is NOT NULL and FKs to users.
        return User::firstOrCreate(
            ['email' => 'host@example.test'],
            ['name' => 'Host', 'password' => bcrypt('secret'), 'role' => 'user', 'status' => 'active'],
        )->id;
    }

    private function event(string $title, string $status): Event
    {
        return Event::create([
            'title'      => $title,
            'category'   => 'Music',
            'venue'      => 'Quake Arena',
            'city'       => 'Hyderabad',
            'date'       => now()->addWeek(),
            'time'       => '19:00',
            'price'      => 499,
            'status'     => $status,
            'partner_id' => $this->partnerId(),
        ]);
    }

    public function test_the_list_never_serves_a_draft(): void
    {
        $this->event('Live Show', 'published');
        $this->event('Secret Draft', 'draft');

        $this->getJson('/api/events')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Live Show');
    }

    public function test_a_draft_cannot_be_fetched_by_id(): void
    {
        $draft = $this->event('Secret Draft', 'draft');

        // The back door: filtering the list alone would still expose the detail.
        $this->getJson('/api/events/' . $draft->id)->assertNotFound();
    }

    public function test_a_published_event_is_still_fetchable_by_id(): void
    {
        $live = $this->event('Live Show', 'published');

        $this->getJson('/api/events/' . $live->id)
            ->assertOk()
            ->assertJsonPath('data.title', 'Live Show');
    }

    public function test_the_status_parameter_cannot_reopen_the_door(): void
    {
        $this->event('Live Show', 'published');
        $this->event('Secret Draft', 'draft');

        // `?status=` used to be honoured here. If it ever comes back, a public caller
        // must still not be able to ask for unpublished events.
        foreach (['draft', 'All', 'DRAFT'] as $attempt) {
            $this->getJson('/api/events?status=' . $attempt)
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonMissing(['title' => 'Secret Draft']);
        }
    }

    public function test_status_casing_does_not_hide_a_published_event(): void
    {
        // The column has carried mixed casing; the filter is lower()'d for that reason.
        $this->event('Shouty Show', 'PUBLISHED');

        $this->getJson('/api/events')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_search_still_works_and_stays_published_only(): void
    {
        $this->event('Karthik Live', 'published');
        $this->event('Karthik Draft', 'draft');

        $this->getJson('/api/events?search=Karthik')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Karthik Live');
    }
}
