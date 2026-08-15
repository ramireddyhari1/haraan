<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\Venue;
use App\Support\PartnerBranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The topbar branch switcher.
 *
 * Session state is a convenience, never the boundary — so most of what these
 * tests pin down is how the selection behaves when it goes stale or is tampered
 * with, which is the only way a session-held filter can hurt anyone.
 */
class PartnerBranchSwitcherTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Venue $koramangala;
    private Venue $hsr;

    protected function setUp(): void
    {
        parent::setUp();

        PartnerBranchContext::flush();

        $this->owner = User::create([
            'name' => 'Big Bean Coffee',
            'email' => 'owner@bigbean.test',
            'password' => Hash::make('secret123'),
            'role' => 'PARTNER',
            'partner_type' => 'cafe',
            'status' => 'active',
        ]);

        $this->koramangala = $this->branch('Koramangala');
        $this->hsr = $this->branch('HSR');
    }

    protected function tearDown(): void
    {
        PartnerBranchContext::flush();

        parent::tearDown();
    }

    private function branch(string $label, ?User $owner = null): Venue
    {
        return Venue::create([
            'name' => 'Big Bean Coffee',
            'branch_label' => $label,
            'kind' => 'cafe',
            'location' => $label,
            'price' => 200,
            'is_active' => true,
            'is_bookable' => true,
            'partner_id' => ($owner ?? $this->owner)->id,
        ]);
    }

    public function test_no_selection_means_all_branches(): void
    {
        $this->actingAs($this->owner);

        $this->assertNull(PartnerBranchContext::currentId());
        $this->assertSame('All branches', PartnerBranchContext::label());
        $this->assertTrue(PartnerBranchContext::isMultiBranch());
    }

    public function test_selecting_a_branch_sticks_and_names_itself(): void
    {
        $this->actingAs($this->owner);

        PartnerBranchContext::select($this->koramangala->id);

        $this->assertSame($this->koramangala->id, PartnerBranchContext::currentId());
        $this->assertSame('Koramangala', PartnerBranchContext::label());
    }

    public function test_selecting_another_partners_branch_is_simply_not_a_selection(): void
    {
        $rival = User::create([
            'name' => 'Rival Cafe',
            'email' => 'rival@other.test',
            'password' => Hash::make('secret123'),
            'role' => 'PARTNER',
            'partner_type' => 'venue',
            'status' => 'active',
        ]);
        $theirs = $this->branch('Indiranagar', $rival);

        $this->actingAs($this->owner);
        PartnerBranchContext::select($theirs->id);

        // Not an error, not a 403 — a tampered id is just not a selection, and the
        // console keeps working on all of the caller's own branches.
        $this->assertNull(PartnerBranchContext::currentId());
        $this->assertSame('All branches', PartnerBranchContext::label());
    }

    public function test_a_selection_that_goes_stale_degrades_to_all_branches(): void
    {
        // A desk person pinned to Koramangala selects it, and is then reassigned.
        $desk = User::create([
            'name' => 'Desk',
            'email' => 'desk@bigbean.test',
            'password' => Hash::make('secret123'),
            'role' => 'PARTNER',
            'partner_type' => 'venue',
            'status' => 'active',
            'parent_partner_id' => $this->owner->id,
            'staff_permissions' => ['bookings'],
        ]);
        $desk->assignedVenues()->sync([$this->koramangala->id]);

        $this->actingAs($desk);
        PartnerBranchContext::select($this->koramangala->id);
        $this->assertSame($this->koramangala->id, PartnerBranchContext::currentId());

        // Reassigned to HSR. The stored id is now unreachable.
        $desk->assignedVenues()->sync([$this->hsr->id]);
        PartnerBranchContext::flush();

        // Reads as "all branches" (which for them is just HSR) instead of throwing
        // them out of a console they still have every right to be in.
        $this->assertNull(PartnerBranchContext::currentId());
        $this->assertFalse(PartnerBranchContext::isMultiBranch(), 'One assigned branch = no switcher.');
    }

    public function test_a_single_branch_partner_gets_no_switcher(): void
    {
        $solo = User::create([
            'name' => 'Sportz Arena',
            'email' => 'solo@haraan.test',
            'password' => Hash::make('secret123'),
            'role' => 'PARTNER',
            'partner_type' => 'venue',
            'status' => 'active',
        ]);
        $this->branch('Gachibowli', $solo);

        $this->actingAs($solo);

        // A dropdown with one option is chrome that does nothing.
        $this->assertFalse(PartnerBranchContext::isMultiBranch());
    }

    public function test_the_switch_route_sets_and_clears_the_selection(): void
    {
        $this->actingAs($this->owner);

        $this->from('/partner')
            ->post(route('partner.branch.switch'), ['venue_id' => (string) $this->hsr->id])
            ->assertRedirect('/partner');

        $this->assertSame($this->hsr->id, session('partner.branch_id'));

        // The "All branches" button posts an empty value.
        $this->from('/partner')
            ->post(route('partner.branch.switch'), ['venue_id' => ''])
            ->assertRedirect('/partner');

        $this->assertNull(session('partner.branch_id'));
    }

    public function test_the_switch_route_needs_a_signed_in_user(): void
    {
        $this->post(route('partner.branch.switch'), ['venue_id' => (string) $this->hsr->id])
            ->assertRedirect();

        $this->assertNull(session('partner.branch_id'));
    }

    /**
     * The topbar hook's own output.
     *
     * Rendering the whole dashboard would be the fuller test, but it 500s in the
     * test environment for an unrelated reason — PartnerQuickActionsWidget calls
     * Page::getUrl() for a game-hub page route that isn't registered here. That
     * predates this work, so the hook is exercised directly rather than left
     * unasserted behind someone else's breakage.
     */
    private function topbar(): string
    {
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('partner'));

        return (string) \Filament\Support\Facades\FilamentView::renderHook(
            \Filament\View\PanelsRenderHook::TOPBAR_START,
        );
    }

    public function test_the_switcher_renders_in_the_console_topbar(): void
    {
        $this->actingAs($this->owner);

        $html = $this->topbar();

        $this->assertStringContainsString('hrn-branch-btn', $html, 'The switcher must be in the topbar.');
        $this->assertStringContainsString('All branches', $html);
        $this->assertStringContainsString('Koramangala', $html);
        $this->assertStringContainsString('HSR', $html);
        $this->assertStringContainsString(route('partner.branch.switch'), $html);
    }

    public function test_the_switcher_only_offers_branches_the_user_may_reach(): void
    {
        $rival = User::create([
            'name' => 'Rival Cafe',
            'email' => 'rival3@other.test',
            'password' => Hash::make('secret123'),
            'role' => 'PARTNER',
            'partner_type' => 'venue',
            'status' => 'active',
        ]);
        $this->branch('Indiranagar', $rival);

        $this->actingAs($this->owner);

        $this->assertStringNotContainsString('Indiranagar', $this->topbar());
    }

    public function test_the_switcher_is_absent_for_a_single_branch_partner(): void
    {
        $solo = User::create([
            'name' => 'Sportz Arena',
            'email' => 'solo2@haraan.test',
            'password' => Hash::make('secret123'),
            'role' => 'PARTNER',
            'partner_type' => 'venue',
            'status' => 'active',
        ]);
        $this->branch('Gachibowli', $solo);

        $this->actingAs($solo);

        // Today's partners must see no change at all.
        $this->assertStringNotContainsString('hrn-branch-btn', $this->topbar());
    }

    /**
     * The reason the lane split and the café widgets had to ship together.
     *
     * Dashboard::getWidgets() falls through to the event list, and all ten of
     * those self-gate on `partner_type === 'event'` — so any lane without an
     * explicit branch renders NOTHING. The venue lane shipped hollow that way
     * once already; this asserts the café lane never does.
     */
    private function widgetsFor(User $u): array
    {
        $this->actingAs($u);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('partner'));

        return array_values(array_filter(
            (new \App\Filament\Pages\Dashboard)->getWidgets(),
            fn (string $w): bool => $w::canView(),
        ));
    }

    public function test_the_cafe_console_is_not_hollow(): void
    {
        $visible = $this->widgetsFor($this->owner);

        $this->assertNotEmpty($visible, 'A café partner must not land on an empty dashboard.');
        $this->assertContains(\App\Filament\Widgets\Cafe\CafeWhatsOnWidget::class, $visible);
        // Shares the branch-lane money widgets with a sports venue.
        $this->assertContains(\App\Filament\Widgets\Venue\VenueTodayWidget::class, $visible);
    }

    public function test_a_sports_venue_never_sees_the_cafe_widget(): void
    {
        $sports = User::create([
            'name' => 'Sportz Arena',
            'email' => 'sportz-lane@haraan.test',
            'password' => Hash::make('secret123'),
            'role' => 'PARTNER',
            'partner_type' => 'venue',
            'status' => 'active',
        ]);
        $this->branch('Gachibowli', $sports);

        $visible = $this->widgetsFor($sports);

        $this->assertNotEmpty($visible);
        $this->assertNotContains(\App\Filament\Widgets\Cafe\CafeWhatsOnWidget::class, $visible);
        $this->assertContains(\App\Filament\Widgets\Venue\VenueTodayWidget::class, $visible);
    }

    public function test_an_event_host_sees_neither_branch_lane_widget(): void
    {
        $host = User::create([
            'name' => 'Gig Co',
            'email' => 'host-lane@haraan.test',
            'password' => Hash::make('secret123'),
            'role' => 'PARTNER',
            'partner_type' => 'event',
            'status' => 'active',
        ]);

        $visible = $this->widgetsFor($host);

        $this->assertNotContains(\App\Filament\Widgets\Cafe\CafeWhatsOnWidget::class, $visible);
        $this->assertNotContains(\App\Filament\Widgets\Venue\VenueTodayWidget::class, $visible);
    }

    public function test_the_memo_never_serves_one_partner_the_others_branches(): void
    {
        $other = User::create([
            'name' => 'Rival Cafe',
            'email' => 'rival2@other.test',
            'password' => Hash::make('secret123'),
            'role' => 'PARTNER',
            'partner_type' => 'venue',
            'status' => 'active',
        ]);
        $this->branch('Indiranagar', $other);

        $this->actingAs($this->owner);
        $this->assertCount(2, PartnerBranchContext::branches());

        // Same process, no flush — a bare static would hand these two back.
        $this->actingAs($other);
        $branches = PartnerBranchContext::branches();

        $this->assertCount(1, $branches);
        $this->assertSame('Indiranagar', $branches->first()->branchName());
    }
}
