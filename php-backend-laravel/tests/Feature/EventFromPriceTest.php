<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * CreateEvent pins `events.price` to 0 for every event the wizard creates — the
 * tiers do the pricing. Every surface that used to read that column raw therefore
 * announced "Free" / "₹0" for real, paid events: the home rails, the events page,
 * the trending + list cards, and `price` in the API (i.e. the Android app).
 *
 * Event::fromPrice() is the single source of that number now. These pin it, and
 * pin the eager-loading that keeps the card lists off an N+1.
 */
class EventFromPriceTest extends TestCase
{
    use RefreshDatabase;

    private function partner(): User
    {
        return User::firstOrCreate(
            ['email' => 'host@example.test'],
            ['name' => 'Host', 'password' => bcrypt('secret'), 'role' => 'PARTNER', 'status' => 'active'],
        );
    }

    private function event(array $overrides = []): Event
    {
        return Event::create(array_merge([
            'partner_id'      => $this->partner()->id,
            'title'           => 'Tiered Show',
            'category'        => 'Music',
            'location'        => 'Arena',
            'venue'           => 'Arena, Hyderabad',
            'city'            => 'Hyderabad',
            'date'            => now()->addDays(6),
            'time'            => '19:00',
            // What the wizard always writes now.
            'price'           => 0,
            'total_slots'     => 100,
            'available_slots' => 100,
            'images'          => ['https://example.test/poster.jpg'],
            'status'          => 'published',
        ], $overrides));
    }

    private function tier(Event $e, string $name, float $price, array $extra = []): TicketType
    {
        return TicketType::create(array_merge([
            'event_id' => $e->id,
            'name'     => $name,
            'kind'     => 'paid',
            'price'    => $price,
            'quota'    => 50,
            'sold'     => 0,
            'visible'  => true,
            'sort'     => 1,
        ], $extra));
    }

    public function test_from_price_is_the_cheapest_buyable_tier(): void
    {
        $e = $this->event();
        $this->tier($e, 'Gold', 1500);
        $this->tier($e, 'Silver', 800);

        $this->assertSame(800.0, $e->fresh()->fromPrice());
        $this->assertSame(2, $e->fresh()->priceTierCount());
    }

    public function test_from_price_falls_back_to_the_column_for_tierless_events(): void
    {
        // The legacy flat shape: no tiers, so the column really is the price.
        $e = $this->event(['price' => 499]);

        $this->assertSame(499.0, $e->fromPrice());
        $this->assertSame(0, $e->priceTierCount());
    }

    public function test_a_closed_sales_window_still_reports_a_price(): void
    {
        $e = $this->event();
        $this->tier($e, 'Gold', 1200, ['sales_end' => now()->subDay()]);

        // Nothing is buyable this second, but the event is not free.
        $this->assertSame(1200.0, $e->fresh()->fromPrice());
    }

    public function test_a_genuinely_free_event_is_still_free(): void
    {
        $e = $this->event();
        $this->tier($e, 'Free entry', 0);

        $this->assertSame(0.0, $e->fresh()->fromPrice());
    }

    public function test_the_api_serves_the_tier_price_not_the_zero_column(): void
    {
        $e = $this->event();
        $this->tier($e, 'Gold', 1500);
        $this->tier($e, 'Silver', 800);

        $res = $this->getJson('/api/events')->assertOk();

        $this->assertSame(800.0, (float) $res->json('data.0.price'), 'app cards would read "Free"');
        $this->assertSame(0.0, (float) $res->json('data.0.basePrice'), 'raw column still exposed');
    }

    public function test_the_events_page_cards_show_the_tier_price(): void
    {
        $e = $this->event();
        $this->tier($e, 'Gold', 1500);
        $this->tier($e, 'Silver', 800);

        $html = $this->get('/events')->assertOk()->getContent();

        $this->assertStringContainsString('₹800', $html);
        $this->assertStringNotContainsString('>Free<', $html);
    }

    public function test_the_home_rails_show_the_tier_price(): void
    {
        $e = $this->event();
        $this->tier($e, 'Silver', 800);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('₹800', $html);
    }

    public function test_the_api_list_does_not_n_plus_one_over_tiers(): void
    {
        foreach (range(1, 6) as $i) {
            $e = $this->event(['title' => "Show {$i}"]);
            $this->tier($e, 'Gold', 100 * $i);
        }

        DB::enableQueryLog();
        $this->getJson('/api/events')->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Eager-loaded: a handful of queries regardless of how many events there are.
        // Without ->with('ticketTypes') this grows with the event count.
        $this->assertLessThan(12, $count, "query count {$count} suggests an N+1 over ticketTypes");
    }
}
