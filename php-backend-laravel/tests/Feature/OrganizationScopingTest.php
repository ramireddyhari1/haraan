<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Venues\VenueResource;
use App\Models\OrganizationUnit;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 1c — panel tenant scoping must isolate a district manager to their own
 * org subtree while leaving super-admins and unassigned admins unrestricted.
 */
class OrganizationScopingTest extends TestCase
{
    use RefreshDatabase;

    private OrganizationUnit $districtA;
    private OrganizationUnit $districtB;
    private Venue $venueA;
    private Venue $venueB;
    private Venue $venueGlobal;

    protected function setUp(): void
    {
        parent::setUp();

        $state = OrganizationUnit::create(['type' => 'STATE', 'name' => 'TestState', 'active' => true]);
        $this->districtA = OrganizationUnit::create(['type' => 'DISTRICT', 'name' => 'DistA', 'parent_id' => $state->id, 'active' => true]);
        $this->districtB = OrganizationUnit::create(['type' => 'DISTRICT', 'name' => 'DistB', 'parent_id' => $state->id, 'active' => true]);
        $areaA = OrganizationUnit::create(['type' => 'AREA', 'name' => 'AreaA', 'parent_id' => $this->districtA->id, 'active' => true]);

        $this->venueA = $this->makeVenue('VenueA', $this->districtA->id);
        $this->makeVenue('VenueA-Area', $areaA->id); // descendant of DistA → manager A should see it
        $this->venueB = $this->makeVenue('VenueB', $this->districtB->id);
        $this->venueGlobal = $this->makeVenue('VenueGlobal', null); // platform-wide
    }

    private function makeVenue(string $name, ?int $orgId): Venue
    {
        return Venue::create([
            'name' => $name,
            'category' => 'cricket',
            'location' => 'Test',
            'organization_id' => $orgId,
        ]);
    }

    private function manager(?int $orgId, string $role = 'OPS'): User
    {
        return User::create([
            'name' => 'Mgr',
            'email' => $role.'-'.($orgId ?? 'none').'-'.uniqid().'@test.local',
            'password' => bcrypt('secret'),
            'role' => $role,
            'organization_id' => $orgId,
        ]);
    }

    public function test_district_manager_sees_only_their_subtree(): void
    {
        $this->actingAs($this->manager($this->districtA->id));

        $names = VenueResource::getEloquentQuery()->pluck('name')->sort()->values()->all();

        $this->assertSame(['VenueA', 'VenueA-Area'], $names);
    }

    public function test_super_admin_sees_everything(): void
    {
        $this->actingAs($this->manager(null, 'ADMIN'));

        $this->assertSame(4, VenueResource::getEloquentQuery()->count());
    }

    public function test_unassigned_admin_is_unrestricted(): void
    {
        $this->actingAs($this->manager(null, 'OPS'));

        $this->assertSame(4, VenueResource::getEloquentQuery()->count());
    }

    public function test_manager_does_not_see_other_district_or_global(): void
    {
        $this->actingAs($this->manager($this->districtA->id));

        $ids = VenueResource::getEloquentQuery()->pluck('id')->all();

        $this->assertNotContains($this->venueB->id, $ids);
        $this->assertNotContains($this->venueGlobal->id, $ids);
    }
}
