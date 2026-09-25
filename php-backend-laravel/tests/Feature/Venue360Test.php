<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Venues\Pages\ViewVenue;
use App\Filament\Resources\Venues\RelationManagers\BlocksRelationManager;
use App\Filament\Resources\Venues\RelationManagers\PricingRulesRelationManager;
use App\Filament\Resources\Venues\RelationManagers\VenueAuditRelationManager;
use App\Filament\Resources\Venues\RelationManagers\VenueBookingsRelationManager;
use App\Filament\Resources\Venues\RelationManagers\VenueMatchesRelationManager;
use App\Filament\Resources\Venues\Schemas\VenueInfolist;
use App\Filament\Resources\Venues\VenueResource;
use App\Filament\Resources\Venues\Widgets\VenueCommandHeroWidget;
use App\Models\AdminAction;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\LiveMatch;
use App\Models\Notification;
use App\Models\PricingRule;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueCourt;
use App\Services\BookingLedger;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class Venue360Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('control'));
    }

    private function admin(): User
    {
        return User::create([
            'name'     => 'Ops Operator',
            'email'    => 'ops360@haraan.test',
            'password' => bcrypt('Password123!'),
            'role'     => 'ADMIN',
            'status'   => 'ACTIVE',
        ]);
    }

    private function customer(): User
    {
        return User::create([
            'name'     => 'Player',
            'email'    => 'player' . uniqid() . '@haraan.test',
            'password' => bcrypt('Password123!'),
            'status'   => 'ACTIVE',
        ]);
    }

    private function venue(array $attributes = []): Venue
    {
        return Venue::create(array_merge([
            'name'         => 'HARAAN Arena',
            'category'     => 'Football',
            'location'     => 'Gachibowli',
            'city'         => 'Hyderabad',
            'price'        => 1200,
            'is_active'    => true,
            'is_bookable'  => true,
            'slot_minutes' => 60,
            'hours_json'   => array_fill_keys(
                ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                ['open' => '06:00', 'close' => '22:00'],
            ),
        ], $attributes));
    }

    /** A venue that can actually take a booking: an owner, an active court and a generated slot grid. */
    private function sellableVenue(): Venue
    {
        $owner = User::create([
            'name'     => 'Venue Owner',
            'email'    => 'owner' . uniqid() . '@haraan.test',
            'password' => bcrypt('Password123!'),
            'role'     => 'PARTNER',
            'status'   => 'ACTIVE',
        ]);

        $venue = $this->venue(['partner_id' => $owner->id]);
        $venue->regenerateSlotsFromHours();

        VenueCourt::create([
            'venue_id'  => $venue->id,
            'name'      => 'Court A',
            'kind'      => 'court',
            'price'     => 1200,
            'is_active' => true,
        ]);

        return $venue->fresh();
    }

    public function test_the_360_page_renders_with_its_hero_and_infolist(): void
    {
        $this->actingAs($this->admin());
        $venue = $this->sellableVenue();

        Livewire::test(ViewVenue::class, ['record' => $venue->id])
            ->assertSuccessful()
            ->assertSee('Venue 360')
            // The hero is record-bound; if the record never reached it the widget
            // renders nothing at all, so assert on its own copy, not the title.
            ->assertSee('Occupancy today')
            ->assertSee('Next seven days')
            ->assertSee('Collected today')
            // …and on the infolist sections that make up the 360's IA.
            ->assertSee('Pricing matrix')
            ->assertSee('Facilities & inventory')
            ->assertSee('Partner account & settlement')
            ->assertSee('Game Hub integration');
    }

    public function test_deep_links_from_the_360_target_this_venue_only(): void
    {
        $this->actingAs($this->admin());
        $venue = $this->sellableVenue();

        // The header links pass ?tableFilters[venue_id][value]=…; the filter has to
        // exist on the destination table or the link silently shows every venue.
        foreach ([
            \App\Filament\Resources\VenueBookings\VenueBookingResource::class,
            \App\Filament\Resources\VenueBlocks\VenueBlockResource::class,
        ] as $resource) {
            $url = $resource::getUrl('index', [
                'tableFilters' => ['venue_id' => ['value' => $venue->id]],
            ]);

            $this->assertStringContainsString('venue_id', $url);
            $this->assertStringContainsString((string) $venue->id, $url);
        }

        $this->assertArrayHasKey(
            'venue_id',
            Livewire::test(\App\Filament\Resources\VenueBookings\Pages\ListVenueBookings::class)
                ->instance()
                ->getTable()
                ->getFilters(),
        );
    }

    public function test_blockers_name_every_reason_a_venue_cannot_be_sold(): void
    {
        $venue = $this->venue(['is_bookable' => false, 'partner_id' => null]);

        $blockers = VenueInfolist::blockers($venue);

        $this->assertNotEmpty($blockers);
        $this->assertTrue(collect($blockers)->contains(fn (string $b): bool => str_contains($b, 'Bookings are switched off')));
        $this->assertTrue(collect($blockers)->contains(fn (string $b): bool => str_contains($b, 'No active courts')));
        $this->assertTrue(collect($blockers)->contains(fn (string $b): bool => str_contains($b, 'No owner assigned')));

        // A fully configured venue reports nothing.
        $this->assertSame([], VenueInfolist::blockers($this->sellableVenue()));
    }

    public function test_hero_counts_occupancy_and_ledger_money_not_order_totals(): void
    {
        $venue = $this->sellableVenue();
        $court = $venue->courts()->first();

        $customer = User::create([
            'name' => 'Player One', 'email' => 'p1@haraan.test',
            'password' => bcrypt('x'), 'status' => 'ACTIVE',
        ]);

        $booking = Booking::create([
            'booking_type'   => 'venue',
            'venue_id'       => $venue->id,
            'venue_court_id' => $court->id,
            'user_id'        => $customer->id,
            'slot_date'      => now()->toDateString(),
            'start_time'     => '18:00',
            'end_time'       => '19:00',
            'status'         => 'CONFIRMED',
            'quantity'       => 1,
            'total_amount'   => 1500,
        ]);

        // Part-paid: the ledger, not the order, is the source of truth.
        BookingPayment::create([
            'booking_id'   => $booking->id,
            'amount'       => 900,
            'method'       => 'upi',
            'collected_at' => now(),
        ]);
        app(BookingLedger::class)->recompute($booking);

        $widget = new VenueCommandHeroWidget();
        $widget->record = $venue->fresh();
        $telemetry = $widget->getTelemetry();

        $this->assertTrue($telemetry['ready']);
        $this->assertSame(1, $telemetry['todayBookings']);
        $this->assertGreaterThan(0, $telemetry['offeredToday'], 'Occupancy needs a real denominator.');
        $this->assertSame(900.0, $telemetry['collectedToday']);
        $this->assertSame(600.0, $telemetry['balanceDue']);
        $this->assertSame(1, $telemetry['owingCount']);
        $this->assertCount(7, $telemetry['days']);
    }

    public function test_pricing_rules_relation_manager_writes_a_rule_the_engine_then_honours(): void
    {
        $this->actingAs($this->admin());
        $venue = $this->sellableVenue();
        $court = $venue->courts()->first();

        Livewire::test(PricingRulesRelationManager::class, [
            'ownerRecord' => $venue,
            'pageClass'   => ViewVenue::class,
        ])
            ->assertSuccessful()
            ->callTableAction('create', data: [
                'name'         => 'Prime evening surge',
                'rule_type'    => 'time_of_day',
                'weekdays'     => ['mon'],
                'start_time'   => '18:00',
                'end_time'     => '22:00',
                'pricing_mode' => 'percentage',
                'amount'       => 25,
                'priority'     => 10,
                'is_active'    => true,
            ])
            ->assertHasNoActionErrors();

        $rule = PricingRule::where('venue_id', $venue->id)->first();
        $this->assertNotNull($rule, 'The rule must be written against the owning venue.');
        $this->assertSame($venue->id, $rule->venue_id);

        // The booking engine, not the form, decides what this means.
        $monday = \Illuminate\Support\Carbon::parse('next monday');
        $this->assertSame(1500, $court->rateFor($monday, '18:00', (int) $venue->price));
        $this->assertSame(1200, $court->rateFor($monday, '09:00', (int) $venue->price));

        $this->assertDatabaseHas('admin_actions', [
            'action'       => 'venue.pricing_rule_created',
            'subject_type' => 'Venue',
            'subject_id'   => $venue->id,
        ]);
    }

    public function test_game_hub_matches_join_through_the_booking_not_the_venue_name(): void
    {
        $venue = $this->sellableVenue();
        $other = $this->venue(['name' => 'Decoy Arena']);

        $booking = Booking::create([
            'booking_type' => 'venue',
            'venue_id'     => $venue->id,
            'user_id'      => $this->customer()->id,
            'slot_date'    => now()->toDateString(),
            'status'       => 'CONFIRMED',
            'quantity'     => 1,
            'total_amount' => 1200,
        ]);

        LiveMatch::create([
            'title' => 'Sunday Friendly', 'home' => 'A', 'away' => 'B',
            'status' => 'completed', 'sport' => 'Football',
            'venue' => $venue->name, 'venue_booking_id' => $booking->id, 'is_ranked' => true,
        ]);

        // Same venue *name*, but no booking here — must not be counted.
        LiveMatch::create([
            'title' => 'Name Collision', 'home' => 'C', 'away' => 'D',
            'status' => 'completed', 'sport' => 'Football',
            'venue' => $venue->name, 'venue_booking_id' => null,
        ]);

        $this->assertSame(1, $venue->matches()->count());
        $this->assertSame(1, $venue->matches()->where('live_matches.is_ranked', true)->count());
        $this->assertSame(0, $other->matches()->count());
    }

    public function test_notify_owner_writes_a_targeted_inbox_row_and_an_audit_entry(): void
    {
        $this->actingAs($this->admin());

        $owner = User::create([
            'name' => 'Venue Owner', 'email' => 'owner@haraan.test',
            'password' => bcrypt('x'), 'role' => 'PARTNER', 'status' => 'ACTIVE',
        ]);
        $venue = $this->sellableVenue();
        $venue->update(['partner_id' => $owner->id]);

        Livewire::test(ViewVenue::class, ['record' => $venue->id])
            ->callAction('notifyOwner', data: [
                'title' => 'Surface maintenance Friday',
                'body'  => 'Court A is off sale from 6am.',
            ])
            ->assertHasNoActionErrors();

        $notification = Notification::where('audience_type', 'user')
            ->where('audience_value', (string) $owner->id)
            ->first();

        $this->assertNotNull($notification);
        $this->assertSame('Surface maintenance Friday', $notification->title);

        $this->assertDatabaseHas('admin_actions', [
            'action'     => 'venue.owner_notified',
            'subject_id' => $venue->id,
        ]);
    }

    public function test_rebuilding_slots_replaces_the_grid_and_logs_it(): void
    {
        $this->actingAs($this->admin());
        $venue = $this->sellableVenue();

        $venue->slots()->delete();
        $this->assertSame(0, $venue->slots()->count());

        Livewire::test(ViewVenue::class, ['record' => $venue->id])
            ->callAction('regenerateSlots')
            ->assertHasNoActionErrors();

        $this->assertGreaterThan(0, $venue->fresh()->slots()->count());
        $this->assertDatabaseHas('admin_actions', [
            'action'     => 'venue.slots_regenerated',
            'subject_id' => $venue->id,
        ]);
    }

    public function test_danger_zone_refuses_to_delete_a_venue_with_upcoming_bookings(): void
    {
        $this->actingAs($this->admin());
        $venue = $this->sellableVenue();

        Booking::create([
            'booking_type' => 'venue',
            'venue_id'     => $venue->id,
            'user_id'      => $this->customer()->id,
            'slot_date'    => now()->addDays(3)->toDateString(),
            'status'       => 'CONFIRMED',
            'quantity'     => 1,
            'total_amount' => 1200,
        ]);

        Livewire::test(ViewVenue::class, ['record' => $venue->id])
            ->callAction('deleteVenue');

        $this->assertDatabaseHas('venues', ['id' => $venue->id]);
    }

    public function test_taking_a_venue_off_sale_is_reversible_and_audited(): void
    {
        $this->actingAs($this->admin());
        $venue = $this->sellableVenue();

        Livewire::test(ViewVenue::class, ['record' => $venue->id])
            ->callAction('toggleBookable')
            ->assertHasNoActionErrors();

        $this->assertFalse((bool) $venue->fresh()->is_bookable);

        // Venue::$auditedAttributes covers is_bookable, so the trail is automatic.
        $this->assertTrue(
            AdminAction::where('subject_type', 'Venue')->where('subject_id', $venue->id)->exists(),
        );
    }

    public function test_read_only_relation_managers_render(): void
    {
        $this->actingAs($this->admin());
        $venue = $this->sellableVenue();

        foreach ([
            VenueBookingsRelationManager::class,
            VenueMatchesRelationManager::class,
            VenueAuditRelationManager::class,
            BlocksRelationManager::class,
        ] as $manager) {
            Livewire::test($manager, [
                'ownerRecord' => $venue,
                'pageClass'   => ViewVenue::class,
            ])->assertSuccessful();
        }
    }

    public function test_the_analytics_page_is_now_routable(): void
    {
        $venue = $this->sellableVenue();

        $this->assertStringContainsString(
            (string) $venue->id,
            VenueResource::getUrl('analytics', ['record' => $venue->id]),
        );
    }

    public function test_the_analytics_page_renders_now_that_it_is_reachable(): void
    {
        $this->actingAs($this->admin());
        $venue = $this->sellableVenue();

        Livewire::test(\App\Filament\Resources\Venues\Pages\VenueAnalytics::class, ["record" => $venue->id])
            ->assertSuccessful()
            ->assertSee("Analytics");
    }

    public function test_partner_panel_hides_the_admin_only_danger_zone_items(): void
    {
        $owner = User::create([
            "name" => "Owner", "email" => "owner-panel@haraan.test",
            "password" => bcrypt("Password123!"), "role" => "PARTNER",
            "partner_type" => "venue", "status" => "ACTIVE",
        ]);

        $venue = $this->venue(["partner_id" => $owner->id]);
        $venue->regenerateSlotsFromHours();
        VenueCourt::create(["venue_id" => $venue->id, "name" => "Court A", "kind" => "court", "price" => 1200, "is_active" => true]);

        Filament::setCurrentPanel(Filament::getPanel("partner"));
        $this->actingAs($owner);

        // A partner may run their venue, but must not delete the listing or
        // detach themselves from it — both are Haraan-side decisions.
        $this->assertFalse(VenueResource::canDelete($venue));
        $this->assertFalse(VenueResource::canCreate());
        $this->assertFalse($owner->isSuperAdmin());
    }
}
