<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Widgets\Venue\BranchComparisonWidget;
use App\Models\Booking;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueCourt;
use App\Support\PartnerBranchContext;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The owner intelligence layer.
 *
 * The single claim worth testing: utilisation ranks branches AGAINST THEMSELVES,
 * so a big outlet that is coasting sorts below a small one that is full. Revenue
 * and bookings can't say that — they just reward whoever has more courts.
 */
class BranchComparisonTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Venue $big;
    private Venue $small;

    protected function setUp(): void
    {
        parent::setUp();

        PartnerBranchContext::flush();

        $this->owner = User::create([
            'name' => 'Big Bean Coffee',
            'email' => 'owner@bigbean.test',
            'password' => Hash::make('secret123'),
            'role' => 'PARTNER',
            'partner_type' => 'venue',
            'status' => 'active',
        ]);

        // Twelve courts, lightly used. Earns more, runs emptier.
        $this->big = $this->branch('Koramangala', courts: 12);
        // Two courts, busy.
        $this->small = $this->branch('HSR', courts: 2);

        Filament::setCurrentPanel(Filament::getPanel('partner'));
        $this->actingAs($this->owner);
    }

    protected function tearDown(): void
    {
        PartnerBranchContext::flush();
        parent::tearDown();
    }

    private function branch(string $label, int $courts): Venue
    {
        $v = Venue::create([
            'name' => 'Big Bean Coffee',
            'branch_label' => $label,
            'location' => $label,
            'price' => 500,
            'is_active' => true,
            'is_bookable' => true,
            'partner_id' => $this->owner->id,
        ]);

        for ($i = 1; $i <= $courts; $i++) {
            VenueCourt::create([
                'venue_id' => $v->id, 'name' => "Court $i", 'price' => 500, 'is_active' => true,
            ]);
        }

        return $v;
    }

    /** A two-hour paid booking today, with the money actually collected. */
    private function booking(Venue $venue, float $amount): Booking
    {
        $b = Booking::create([
            'quantity' => 1,
            'total_amount' => $amount,
            'status' => 'CONFIRMED',
            'booking_type' => 'venue',
            'user_id' => $this->owner->id,
            'venue_id' => $venue->id,
            'slot_date' => today()->toDateString(),
            'start_time' => '19:00',
            'end_time' => '21:00',
            'channel' => 'offline',
            'guest_name' => 'Anika Rao',
            'guest_phone' => '9876543210',
        ]);

        DB::table('booking_payments')->insert([
            'booking_id' => $b->id,
            'amount' => $amount,
            'method' => 'cash',
            'collected_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $b;
    }

    private function rows(): array
    {
        return (new BranchComparisonWidget)->getRows();
    }

    public function test_it_ranks_by_utilisation_not_by_revenue(): void
    {
        // Koramangala earns far more in absolute terms…
        for ($i = 0; $i < 6; $i++) {
            $this->booking($this->big, 5000);
        }
        // …but HSR, with two courts, is proportionally busier.
        for ($i = 0; $i < 6; $i++) {
            $this->booking($this->small, 500);
        }

        $rows = $this->rows();

        $this->assertCount(2, $rows);
        $this->assertSame('HSR', $rows[0]['branch'], 'The fuller branch must rank first.');
        $this->assertSame('Koramangala', $rows[1]['branch']);

        // And the revenue column still tells the truth about the money.
        $byName = collect($rows)->keyBy('branch');
        $this->assertSame(30000.0, $byName['Koramangala']['revenue']);
        $this->assertSame(3000.0, $byName['HSR']['revenue']);
        $this->assertGreaterThan(
            $byName['Koramangala']['utilisation'],
            $byName['HSR']['utilisation'],
            'Utilisation compares a branch to its own capacity.',
        );
    }

    public function test_a_branch_far_below_the_best_is_flagged_soft(): void
    {
        // HSR must be genuinely busy — 30 two-hour bookings over two courts is
        // ~7% of a 30-day window, comfortably past the "is this comparable at
        // all" floor. A quieter fixture proves nothing about the ratio rule.
        for ($i = 0; $i < 30; $i++) {
            $this->booking($this->small, 500);
        }
        // Koramangala gets one booking across twelve courts.
        $this->booking($this->big, 5000);

        $rows = collect($this->rows())->keyBy('branch');

        $this->assertGreaterThanOrEqual(5, $rows['HSR']['utilisation'], 'Best branch must clear the floor.');

        $this->assertFalse($rows['HSR']['is_soft'], 'The best branch is never soft.');
        $this->assertTrue($rows['Koramangala']['is_soft']);

        $headline = (new BranchComparisonWidget)->getHeadline();
        $this->assertNotNull($headline);
        $this->assertStringContainsString('Koramangala', $headline);
    }

    /**
     * Caught against production data, not in a fixture: with the chain's best
     * outlet at 1% utilisation, a pure ratio test flagged the empty branch as
     * "running well below your best" — which blames one outlet for a business
     * that is simply quiet everywhere.
     */
    public function test_a_quiet_chain_blames_nobody(): void
    {
        // One two-hour booking across three courts over 30 days ≈ 0% utilisation
        // on both branches. Nothing here is comparable to anything.
        $this->booking($this->big, 500);

        $rows = $this->rows();

        $this->assertLessThan(5, max(array_column($rows, 'utilisation')), 'Fixture must be genuinely quiet.');

        foreach ($rows as $row) {
            $this->assertFalse(
                $row['is_soft'],
                "{$row['branch']} must not be flagged when the best branch is idle too.",
            );
        }

        $this->assertNull((new BranchComparisonWidget)->getHeadline());
    }

    public function test_evenly_run_branches_raise_no_alarm(): void
    {
        // Same load per court on both.
        for ($i = 0; $i < 6; $i++) {
            $this->booking($this->big, 500);
        }
        $this->booking($this->small, 500);

        foreach ($this->rows() as $row) {
            $this->assertFalse($row['is_soft'], "{$row['branch']} should not be flagged.");
        }

        $this->assertNull((new BranchComparisonWidget)->getHeadline());
    }

    public function test_it_is_hidden_from_single_branch_partners(): void
    {
        $solo = User::create([
            'name' => 'Sportz Arena',
            'email' => 'solo@haraan.test',
            'password' => Hash::make('secret123'),
            'role' => 'PARTNER',
            'partner_type' => 'venue',
            'status' => 'active',
        ]);
        Venue::create([
            'name' => 'Sportz Arena', 'branch_label' => 'Gachibowli', 'location' => 'Gachibowli',
            'price' => 500, 'is_active' => true, 'is_bookable' => true, 'partner_id' => $solo->id,
        ]);

        $this->actingAs($solo);
        PartnerBranchContext::flush();

        $this->assertFalse(BranchComparisonWidget::canView());
    }

    public function test_it_hides_once_a_single_branch_is_selected(): void
    {
        $this->assertTrue(BranchComparisonWidget::canView(), 'Visible on "All branches".');

        // Comparing one outlet to itself is a table with one row.
        PartnerBranchContext::select($this->big->id);

        $this->assertFalse(BranchComparisonWidget::canView());
    }

    public function test_an_event_host_never_sees_it(): void
    {
        $host = User::create([
            'name' => 'Gig Co',
            'email' => 'host@haraan.test',
            'password' => Hash::make('secret123'),
            'role' => 'PARTNER',
            'partner_type' => 'event',
            'status' => 'active',
        ]);

        $this->actingAs($host);
        PartnerBranchContext::flush();

        $this->assertFalse(BranchComparisonWidget::canView());
    }

    public function test_it_renders(): void
    {
        $this->booking($this->small, 500);

        Livewire::test(BranchComparisonWidget::class)
            ->assertOk()
            ->assertSee('HSR')
            ->assertSee('Koramangala')
            ->assertSee('Utilisation');
    }
}
