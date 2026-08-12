<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * /events/{id} is the public event page. It used to be an unfiltered findOrFail, so
 * a DRAFT event rendered for anyone guessing an id — title, poster, tiers and host —
 * and was indexable, even though booking and /api/events were already published-only.
 *
 * These pin the door shut while leaving the one legitimate hole open: the organiser
 * (and internal staff) previewing their own unpublished event.
 */
class EventDetailVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function partner(string $email = 'host@example.test'): User
    {
        return User::firstOrCreate(
            ['email' => $email],
            ['name' => 'Host', 'password' => bcrypt('secret'), 'role' => 'PARTNER', 'status' => 'active'],
        );
    }

    private function event(string $status, ?User $partner = null): Event
    {
        return Event::create([
            'partner_id'      => ($partner ?? $this->partner())->id,
            'title'           => 'Secret Line-up',
            'category'        => 'Music',
            'location'        => 'Test Arena',
            'venue'           => 'Test Arena, Hyderabad',
            'city'            => 'Hyderabad',
            'date'            => now()->addDays(7),
            'time'            => '19:00',
            'price'           => 249,
            'total_slots'     => 100,
            'available_slots' => 100,
            'images'          => [],
            'status'          => $status,
        ]);
    }

    public function test_a_guest_cannot_open_a_draft(): void
    {
        $draft = $this->event('draft');

        $this->get("/events/{$draft->id}")->assertNotFound();
    }

    public function test_a_logged_in_stranger_cannot_open_a_draft(): void
    {
        $draft = $this->event('draft');

        $stranger = User::create([
            'name' => 'Nosy Parker', 'email' => 'nosy@example.test',
            'password' => bcrypt('secret'), 'role' => 'user', 'status' => 'active',
        ]);

        $this->actingAs($stranger)->get("/events/{$draft->id}")->assertNotFound();
    }

    public function test_another_partner_cannot_open_someone_elses_draft(): void
    {
        $draft = $this->event('draft');
        $rival = $this->partner('rival@example.test');

        $this->actingAs($rival)->get("/events/{$draft->id}")->assertNotFound();
    }

    public function test_the_owning_partner_can_preview_their_own_draft(): void
    {
        $owner = $this->partner();
        $draft = $this->event('draft', $owner);

        $this->actingAs($owner)
            ->get("/events/{$draft->id}")
            ->assertOk()
            ->assertSee('Secret Line-up');
    }

    public function test_desk_staff_share_the_owners_preview(): void
    {
        $owner = $this->partner();
        $draft = $this->event('draft', $owner);

        // Desk staff resolve to their owner via effectivePartnerId(), so the event
        // they help run is the event they may preview.
        $desk = User::create([
            'name' => 'Desk Person', 'email' => 'desk@example.test',
            'password' => bcrypt('secret'), 'role' => 'PARTNER', 'status' => 'active',
            'parent_partner_id' => $owner->id,
        ]);

        $this->actingAs($desk)->get("/events/{$draft->id}")->assertOk();
    }

    public function test_an_admin_can_preview_any_draft(): void
    {
        $draft = $this->event('draft');

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin@example.test',
            'password' => bcrypt('secret'), 'role' => 'ADMIN', 'status' => 'active',
        ]);

        $this->actingAs($admin)->get("/events/{$draft->id}")->assertOk();
    }

    public function test_a_preview_is_never_indexable(): void
    {
        $owner = $this->partner();
        $draft = $this->event('draft', $owner);

        $this->actingAs($owner)
            ->get("/events/{$draft->id}")
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex,nofollow">', false);
    }

    public function test_a_published_event_is_still_public(): void
    {
        $live = $this->event('published');

        // The SEO partial always emits a robots tag; a live event must keep the
        // indexable one, so the preview override can't leak onto public pages.
        $this->get("/events/{$live->id}")
            ->assertOk()
            ->assertSee('Secret Line-up')
            ->assertSee('<meta name="robots" content="index,follow,max-image-preview:large">', false)
            ->assertDontSee('noindex', false);
    }

    public function test_status_casing_does_not_hide_a_published_event(): void
    {
        // The column has carried mixed casing; the filter is lower()'d for that reason.
        $live = $this->event('PUBLISHED');

        $this->get("/events/{$live->id}")->assertOk();
    }

    public function test_other_unpublished_statuses_are_hidden_too(): void
    {
        foreach (['archived', 'cancelled'] as $status) {
            $hidden = $this->event($status);

            $this->get("/events/{$hidden->id}")->assertNotFound();
        }
    }
}
