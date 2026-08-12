<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The mobile sticky booking bar on /events/{id} used to read `events.price` raw.
 * An event authored in Ticket Studio leaves that column at 0 and carries its real
 * prices on the ticket types, so every tiered event announced "Free · entry" next
 * to a live Book Tickets button — while the ticket sheet directly above it listed
 * the correct ₹ prices. The bar must price off the tiers, as the desktop build does.
 */
class EventBookBarPriceTest extends TestCase
{
    use RefreshDatabase;

    private function event(array $overrides = []): Event
    {
        $partner = User::firstOrCreate(
            ['email' => 'host@example.test'],
            ['name' => 'Host', 'password' => bcrypt('secret'), 'role' => 'PARTNER', 'status' => 'active'],
        );

        return Event::create(array_merge([
            'partner_id'      => $partner->id,
            'title'           => 'Reddy Live Test',
            'category'        => 'Music',
            'location'        => 'K L University',
            'venue'           => 'K L University, Vijayawada',
            'city'            => 'Vijayawada',
            'date'            => now()->addDays(2),
            'time'            => '19:00',
            // Ticket Studio events keep the flat column at 0 — this is the trap.
            'price'           => 0,
            'total_slots'     => 100,
            'available_slots' => 100,
            'images'          => [],
            'status'          => 'published',
        ], $overrides));
    }

    private function tier(Event $event, string $name, float $price, int $sort = 1): TicketType
    {
        return TicketType::create([
            'event_id' => $event->id,
            'name'     => $name,
            'kind'     => 'paid',
            'price'    => $price,
            'quota'    => 50,
            'sold'     => 0,
            'sort'     => $sort,
        ]);
    }

    /** Pull the sticky bar's amount + label out of the rendered page. */
    private function bar(string $html): string
    {
        preg_match(
            '#<div class="dr-book-bar__price">(.*?)</div>#s',
            $html,
            $m,
        );

        return trim(preg_replace('/\s+/', ' ', strip_tags($m[1] ?? '')));
    }

    public function test_a_tiered_event_shows_its_cheapest_tier_not_free(): void
    {
        $event = $this->event();
        $this->tier($event, 'Gold', 1500, 1);
        $this->tier($event, 'Silver', 800, 2);

        $bar = $this->bar($this->get("/events/{$event->id}")->assertOk()->getContent());

        $this->assertSame('₹800 onwards', $bar);
        $this->assertStringNotContainsString('Free', $bar);
    }

    public function test_a_single_tier_reads_per_ticket_not_onwards(): void
    {
        $event = $this->event();
        $this->tier($event, 'General', 249);

        $this->assertSame('₹249 per ticket', $this->bar($this->get("/events/{$event->id}")->getContent()));
    }

    public function test_a_flat_priced_event_still_uses_its_own_price(): void
    {
        // No tiers at all — the legacy shape, where events.price IS the price.
        $event = $this->event(['price' => 499]);

        $this->assertSame('₹499 per ticket', $this->bar($this->get("/events/{$event->id}")->getContent()));
    }

    public function test_a_genuinely_free_event_still_says_free(): void
    {
        // The label is only wrong when it hides a real price; a free event keeps it.
        $event = $this->event(['price' => 0]);

        $this->assertSame('Free entry', $this->bar($this->get("/events/{$event->id}")->getContent()));
    }

    public function test_a_closed_sales_window_does_not_fall_back_to_free(): void
    {
        $event = $this->event();
        $tier = $this->tier($event, 'Gold', 1200);
        // Sales ended yesterday: no tier is on sale, but the event is not free.
        $tier->update(['sales_end' => now()->subDay()]);

        $this->assertStringNotContainsString('Free', $this->bar($this->get("/events/{$event->id}")->getContent()));
    }
}
