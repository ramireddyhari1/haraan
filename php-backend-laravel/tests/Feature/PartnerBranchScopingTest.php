<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CustomerPackage;
use App\Models\PackageRedemption;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenuePackage;
use App\Support\JwtService;
use App\Support\PartnerCapabilities;
use App\Support\PartnerLane;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The branch is the security boundary.
 *
 * A chain's desk person is assigned to one outlet. Every one of these tests
 * exists because the API used to scope on `partner_id` alone, which is the same
 * thing as "any branch of the business" — invisible while every partner had one
 * venue, and wrong the day one of them opened a second.
 */
class PartnerBranchScopingTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Venue $koramangala;
    private Venue $hsr;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::create([
            'name' => 'Big Bean Coffee',
            'email' => 'owner@bigbean.test',
            'password' => Hash::make('secret123'),
            'role' => 'PARTNER',
            'partner_type' => 'cafe',
            'status' => 'active',
        ]);

        $this->koramangala = $this->branch('Koramangala', 'BB-KOR');
        $this->hsr = $this->branch('HSR', 'BB-HSR');
    }

    private function branch(string $label, string $code): Venue
    {
        return Venue::create([
            'name' => 'Big Bean Coffee',
            'branch_label' => $label,
            'branch_code' => $code,
            'kind' => 'cafe',
            'location' => $label,
            'price' => 200,
            'is_active' => true,
            'is_bookable' => true,
            'partner_id' => $this->owner->id,
        ]);
    }

    /** A desk person under this owner, optionally pinned to specific branches. */
    private function desk(array $venues, array $permissions = ['bookings', 'checkin', 'reports']): User
    {
        $staff = User::create([
            'name' => 'Desk '.uniqid(),
            'email' => uniqid('desk').'@bigbean.test',
            'password' => Hash::make('secret123'),
            'role' => 'PARTNER',
            'partner_type' => 'venue',
            'status' => 'active',
            'parent_partner_id' => $this->owner->id,
            'staff_permissions' => $permissions,
        ]);

        $staff->assignedVenues()->sync(collect($venues)->pluck('id')->all());

        return $staff;
    }

    private function as(User $user): self
    {
        $this->withHeader('Authorization', 'Bearer '.JwtService::issueForUser(
            $user,
            (string) config('app.jwt_secret', env('JWT_SECRET', 'change_me')),
        ));

        return $this;
    }

    // ---------------------------------------------------------------------
    //  The boundary
    // ---------------------------------------------------------------------

    public function test_desk_staff_cannot_reach_an_unassigned_branch_by_id(): void
    {
        $desk = $this->desk([$this->koramangala]);

        // Their own branch is fine.
        $this->as($desk)->getJson("/api/partner/venues/{$this->koramangala->id}/day")
            ->assertOk();

        // The sibling branch does not exist as far as they are concerned. 404, not
        // 403 — a 403 would confirm the id belongs to their business.
        $this->as($desk)->getJson("/api/partner/venues/{$this->hsr->id}/day")
            ->assertNotFound();
    }

    public function test_desk_staff_cannot_write_to_an_unassigned_branch(): void
    {
        $desk = $this->desk([$this->koramangala]);

        $this->as($desk)->postJson("/api/partner/venues/{$this->hsr->id}/block", [
            'date' => today()->addDay()->toDateString(),
        ])->assertNotFound();

        $this->assertDatabaseMissing('venue_blocked_dates', ['venue_id' => $this->hsr->id]);
    }

    public function test_owner_reaches_every_branch(): void
    {
        foreach ([$this->koramangala, $this->hsr] as $branch) {
            $this->as($this->owner)->getJson("/api/partner/venues/{$branch->id}/day")
                ->assertOk();
        }
    }

    public function test_unassigned_desk_staff_still_see_the_whole_business(): void
    {
        // Assignment is an opt-in narrowing: a desk person with no branches listed
        // is not locked out of all of them, which would break every single-branch
        // partner the moment this shipped.
        $desk = $this->desk([]);

        $this->as($desk)->getJson("/api/partner/venues/{$this->hsr->id}/day")->assertOk();
    }

    public function test_branch_list_only_shows_assigned_branches(): void
    {
        $desk = $this->desk([$this->koramangala]);

        $body = $this->as($desk)->getJson('/api/partner/venues')->assertOk()->json('data');

        $this->assertCount(1, $body);
        $this->assertSame('Koramangala', $body[0]['branch']);
    }

    // ---------------------------------------------------------------------
    //  Context
    // ---------------------------------------------------------------------

    public function test_context_reports_altitude_and_scoped_branches(): void
    {
        $ownerBody = $this->as($this->owner)->getJson('/api/partner/context')->assertOk()->json();

        $this->assertSame('owner', $ownerBody['user']['altitude']);
        $this->assertSame('cafe', $ownerBody['business']['type']);
        $this->assertCount(2, $ownerBody['branches']);
        // A café gets events out of the box; a sports venue does not.
        $this->assertContains('events', $ownerBody['business']['capabilities']);

        // All four permissions = a branch manager, not a desk person.
        $manager = $this->desk([$this->koramangala], User::STAFF_PERMISSIONS);
        $managerBody = $this->as($manager)->getJson('/api/partner/context')->assertOk()->json();

        $this->assertSame('manager', $managerBody['user']['altitude']);
        $this->assertCount(1, $managerBody['branches']);

        $desk = $this->desk([$this->hsr], ['checkin']);
        $deskBody = $this->as($desk)->getJson('/api/partner/context')->assertOk()->json();

        $this->assertSame('desk', $deskBody['user']['altitude']);
        $this->assertSame('HSR', $deskBody['branches'][0]['branch']);
    }

    public function test_branch_capabilities_override_the_business_default(): void
    {
        // The HSR outlet is a coffee counter: no bookings, no consoles.
        $this->hsr->update(['capabilities' => ['offers']]);

        $branches = collect($this->as($this->owner)->getJson('/api/partner/context')->json('branches'))
            ->keyBy('branch');

        $this->assertSame(['offers'], $branches['HSR']['capabilities']);
        $this->assertContains('bookings', $branches['Koramangala']['capabilities']);
    }

    public function test_capabilities_follow_the_partner_type_preset(): void
    {
        $sports = User::create([
            'name' => 'Sportz Arena',
            'email' => 'sportz@haraan.test',
            'password' => Hash::make('secret123'),
            'role' => 'PARTNER',
            'partner_type' => 'venue',
            'status' => 'active',
        ]);

        $this->assertSame(PartnerCapabilities::PRESETS['venue'], PartnerCapabilities::forBusiness($sports));
        // A sports venue has no events lane; a café does. That difference is the
        // whole reason the two are separate types.
        $this->assertNotContains('events', PartnerCapabilities::forBusiness($sports));
        $this->assertContains('events', PartnerCapabilities::forBusiness($this->owner));

        // A legacy row with no type at all must not blow up or come back empty.
        $legacy = User::create([
            'name' => 'Legacy Partner',
            'email' => 'legacy@haraan.test',
            'password' => Hash::make('secret123'),
            'role' => 'PARTNER',
            'status' => 'active',
        ]);

        $this->assertSame(PartnerCapabilities::PRESETS['venue'], PartnerCapabilities::forBusiness($legacy));
    }

    public function test_each_partner_type_mounts_its_own_lane(): void
    {
        $this->assertSame('cafe', (new User(['partner_type' => 'cafe']))->partnerLane());
        $this->assertSame('gamehub', (new User(['partner_type' => 'venue']))->partnerLane());
        $this->assertSame('events', (new User(['partner_type' => 'event']))->partnerLane());

        // A café is NOT the sports console with a different label on it.
        $this->assertNotSame(
            (new User(['partner_type' => 'cafe']))->partnerLane(),
            (new User(['partner_type' => 'venue']))->partnerLane(),
        );

        // Legacy/unknown still land somewhere usable rather than on a blank page.
        $this->assertSame('gamehub', (new User(['partner_type' => null]))->partnerLane());
        $this->assertSame('gamehub', (new User(['partner_type' => 'restaurant']))->partnerLane());
    }

    public function test_each_branch_lane_has_its_own_word_for_a_bookable_unit(): void
    {
        $this->assertSame('court', PartnerLane::resourceNoun(PartnerLane::GAMEHUB));
        $this->assertSame('tables', PartnerLane::resourceNoun(PartnerLane::CAFE, plural: true));
        $this->assertSame('table-hours', PartnerLane::resourceHours(PartnerLane::CAFE));

        // The events lane books nothing physical — asking is a caller bug, and it
        // must fail loudly rather than invent a word that reaches a dashboard.
        $this->expectException(\InvalidArgumentException::class);
        PartnerLane::resourceNoun(PartnerLane::EVENTS);
    }

    // ---------------------------------------------------------------------
    //  Overview scoping
    // ---------------------------------------------------------------------

    public function test_overview_narrows_to_one_branch_and_says_so(): void
    {
        $all = $this->as($this->owner)->getJson('/api/partner/overview')->assertOk()->json('scope');

        $this->assertNull($all['venue_id']);
        $this->assertSame(2, $all['branches']);
        $this->assertTrue($all['includes_events']);

        $one = $this->as($this->owner)
            ->getJson("/api/partner/overview?venue_id={$this->koramangala->id}")
            ->assertOk()->json('scope');

        $this->assertSame($this->koramangala->id, $one['venue_id']);
        $this->assertSame(1, $one['branches']);
        // Events carry no venue, so a branch view must not claim them.
        $this->assertFalse($one['includes_events']);
    }

    public function test_a_branch_filter_the_caller_cannot_reach_yields_nothing(): void
    {
        $desk = $this->desk([$this->koramangala]);

        $scope = $this->as($desk)
            ->getJson("/api/partner/overview?venue_id={$this->hsr->id}")
            ->assertOk()->json('scope');

        $this->assertSame(0, $scope['branches']);
    }

    // ---------------------------------------------------------------------
    //  Memberships — the branch lock
    // ---------------------------------------------------------------------

    private function pass(?Venue $lockedTo, string $phone = '9876543210'): CustomerPackage
    {
        $offer = VenuePackage::create([
            'partner_id' => $this->owner->id,
            'venue_id' => $lockedTo?->id,
            'name' => $lockedTo ? 'Gaming Pass — '.$lockedTo->branchName() : 'Gaming Pass — all branches',
            'price' => 999,
            'sessions' => 10,
            'is_active' => true,
        ]);

        return CustomerPackage::create([
            'venue_package_id' => $offer->id,
            'partner_id' => $this->owner->id,
            'sold_at_venue_id' => $lockedTo?->id,
            'customer_phone' => $phone,
            'customer_name' => 'Anika Rao',
            'sessions_total' => 10,
            'amount_paid' => 999,
            'payment_method' => 'cash',
        ]);
    }

    public function test_a_branch_locked_pass_is_not_offered_at_another_branch(): void
    {
        $this->pass($this->koramangala);

        $here = $this->as($this->owner)
            ->getJson("/api/partner/packages/holder?phone=9876543210&venue_id={$this->koramangala->id}")
            ->assertOk()->json('data');

        $this->assertCount(1, $here, 'The branch it is locked to must offer it.');

        // The bug this whole test file exists for: before the fix this returned the
        // pass, the desk spent a session, and the customer was given something the
        // offer never entitled them to.
        $there = $this->as($this->owner)
            ->getJson("/api/partner/packages/holder?phone=9876543210&venue_id={$this->hsr->id}")
            ->assertOk()->json('data');

        $this->assertCount(0, $there, 'A pass locked to Koramangala must not be usable at HSR.');
    }

    public function test_a_chain_wide_pass_works_at_every_branch(): void
    {
        $this->pass(null);

        foreach ([$this->koramangala, $this->hsr] as $branch) {
            $rows = $this->as($this->owner)
                ->getJson("/api/partner/packages/holder?phone=9876543210&venue_id={$branch->id}")
                ->assertOk()->json('data');

            $this->assertCount(1, $rows, "A chain-wide pass must work at {$branch->branchName()}.");
            $this->assertNull($rows[0]['valid_at_venue_id']);
        }
    }

    public function test_selling_a_pass_credits_the_branch_that_sold_it(): void
    {
        $offer = VenuePackage::create([
            'partner_id' => $this->owner->id,
            'venue_id' => null,
            'name' => 'Gaming Pass',
            'price' => 999,
            'sessions' => 10,
            'is_active' => true,
        ]);

        $this->as($this->owner)->postJson("/api/partner/packages/{$offer->id}/sell", [
            'customerPhone' => '9876543211',
            'customerName' => 'Dev Menon',
            'venueId' => $this->hsr->id,
        ])->assertCreated();

        $this->assertDatabaseHas('customer_packages', [
            'customer_phone' => '9876543211',
            'sold_at_venue_id' => $this->hsr->id,
        ]);
    }

    public function test_a_sale_is_never_credited_to_a_branch_the_seller_cannot_reach(): void
    {
        $offer = VenuePackage::create([
            'partner_id' => $this->owner->id,
            'venue_id' => null,
            'name' => 'Gaming Pass',
            'price' => 999,
            'sessions' => 10,
            'is_active' => true,
        ]);

        $desk = $this->desk([$this->koramangala], ['pricing']);

        $this->as($desk)->postJson("/api/partner/packages/{$offer->id}/sell", [
            'customerPhone' => '9876543212',
            'venueId' => $this->hsr->id,
        ])->assertNotFound();

        $this->assertDatabaseMissing('customer_packages', ['customer_phone' => '9876543212']);
    }

    public function test_redemption_records_the_branch_that_honoured_it(): void
    {
        // A walk-in redemption has no booking, so without the column there is no
        // way back to the branch that gave the session away.
        $pass = $this->pass(null);

        PackageRedemption::create([
            'customer_package_id' => $pass->id,
            'booking_id' => null,
            'redeemed_at_venue_id' => $this->hsr->id,
        ]);

        $this->assertDatabaseHas('package_redemptions', [
            'customer_package_id' => $pass->id,
            'redeemed_at_venue_id' => $this->hsr->id,
        ]);
        $this->assertSame(9, $pass->fresh()->remaining());
    }

    // ---------------------------------------------------------------------
    //  Lanes
    // ---------------------------------------------------------------------

    public function test_the_cafe_owner_in_this_fixture_is_on_the_cafe_lane(): void
    {
        $this->assertSame('cafe', $this->owner->partnerLane());
    }
}
