<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\Venue\VenueMoneyHealthWidget;
use App\Filament\Widgets\Venue\VenuePeakHoursWidget;
use App\Filament\Widgets\Venue\VenueTodayWidget;
use App\Filament\Widgets\Venue\VenueUpcomingWidget;
use App\Models\Booking;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueCourt;
use App\Services\BookingLedger;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The venue lane has its own dashboard, and the events lane is untouched.
 *
 * Ten of the twelve partner widgets self-gate on `partner_type === 'event'`, so
 * before this a venue owner landed on a near-empty page. These tests pin both
 * halves: the venue owner now gets venue widgets, and an event host's widget
 * list is byte-identical to what it was.
 */
class VenueDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function partner(string $type = 'venue'): User
    {
        return User::create([
            'name' => 'Rahul Krishnan',
            'email' => $type . '.partner@haraan.test',
            'password' => Hash::make('secret123'),
            'role' => 'PARTNER',
            'partner_type' => $type,
            'status' => 'active',
        ]);
    }

    private function inPartnerPanel(User $user, callable $fn): mixed
    {
        Filament::setCurrentPanel(Filament::getPanel('partner'));
        $this->actingAs($user);

        return $fn();
    }

    private function widgetsFor(User $user): array
    {
        return $this->inPartnerPanel($user, fn (): array => (new Dashboard)->getWidgets());
    }

    public function test_a_venue_owner_gets_the_venue_widgets(): void
    {
        $widgets = $this->widgetsFor($this->partner('venue'));

        $this->assertContains(VenueTodayWidget::class, $widgets);
        $this->assertContains(VenueMoneyHealthWidget::class, $widgets);
        $this->assertContains(VenuePeakHoursWidget::class, $widgets);
        $this->assertContains(VenueUpcomingWidget::class, $widgets);
    }

    /** Null partner_type is the historical default and must mean venue, not blank. */
    public function test_a_partner_with_no_type_falls_back_to_the_venue_lane(): void
    {
        $legacy = User::create([
            'name' => 'Legacy Partner',
            'email' => 'legacy@haraan.test',
            'password' => Hash::make('secret123'),
            'role' => 'PARTNER',
            'partner_type' => null,
            'status' => 'active',
        ]);

        $this->assertContains(VenueTodayWidget::class, $this->widgetsFor($legacy));
    }

    public function test_an_event_host_sees_no_venue_widgets_at_all(): void
    {
        $widgets = $this->widgetsFor($this->partner('event'));

        foreach ([VenueTodayWidget::class, VenueMoneyHealthWidget::class, VenuePeakHoursWidget::class, VenueUpcomingWidget::class] as $venueWidget) {
            $this->assertNotContains($venueWidget, $widgets, 'The events lane must be unchanged.');
        }

        // …and still gets its own full set.
        $this->assertGreaterThanOrEqual(9, count($widgets));
    }

    /** Even reached directly, a venue widget refuses to render for an event host. */
    public function test_venue_widgets_self_gate_on_the_lane(): void
    {
        $this->inPartnerPanel($this->partner('venue'), function (): void {
            $this->assertTrue(VenueTodayWidget::canView());
            $this->assertTrue(VenueMoneyHealthWidget::canView());
        });

        $this->inPartnerPanel($this->partner('event'), function (): void {
            $this->assertFalse(VenueTodayWidget::canView());
            $this->assertFalse(VenueMoneyHealthWidget::canView());
        });
    }

    /**
     * Today's revenue is money COLLECTED, not money invoiced — the whole reason
     * the ledger exists. A ₹4,400 booking with a ₹500 advance contributes ₹500.
     */
    public function test_todays_revenue_counts_collected_money_not_invoiced(): void
    {
        $partner = $this->partner('venue');

        $venue = Venue::create([
            'name' => 'Sportz Arena', 'location' => 'Gachibowli', 'price' => 1400,
            'is_active' => true, 'is_bookable' => true, 'partner_id' => $partner->id,
        ]);
        VenueCourt::create(['venue_id' => $venue->id, 'name' => 'Turf A', 'price' => 1400, 'is_active' => true]);

        $booking = Booking::create([
            'quantity' => 1,
            'total_amount' => 4400,
            'status' => 'CONFIRMED',
            'booking_type' => 'venue',
            'user_id' => $partner->id,
            'venue_id' => $venue->id,
            'slot_date' => now()->toDateString(),
            'start_time' => '19:00',
            'end_time' => '21:00',
            'channel' => 'offline',
        ]);

        app(BookingLedger::class)->collect($booking, 500, 'upi');

        $this->inPartnerPanel($partner, function () use ($booking): void {
            $revenue = $this->statValue(new VenueTodayWidget, 0);
            $this->assertSame('₹500', $revenue, 'Only the advance has actually been collected.');

            $pending = $this->statValue(new VenueMoneyHealthWidget, 0);
            $this->assertSame('₹3,900', $pending, 'The balance is what is pending.');

            $this->assertSame(3900.0, $booking->refresh()->balanceDue());
        });
    }

    /**
     * Read one rendered stat off a widget. `getStats()` is protected — reached by
     * reflection rather than widening production visibility just for a test.
     */
    private function statValue(object $widget, int $index): string
    {
        $method = new \ReflectionMethod($widget, 'getStats');
        $method->setAccessible(true);

        return (string) $method->invoke($widget)[$index]->getValue();
    }
}
