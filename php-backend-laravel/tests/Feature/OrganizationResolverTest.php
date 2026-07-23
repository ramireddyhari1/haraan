<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\OrganizationUnit;
use App\Support\OrganizationResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_builds_state_district_tree_once_and_is_idempotent(): void
    {
        $first = OrganizationResolver::districtUnitId('Kerala', 'Ernakulam');
        $second = OrganizationResolver::districtUnitId('Kerala', 'Ernakulam');

        $this->assertSame($first, $second);
        $this->assertSame(1, OrganizationUnit::where('type', 'STATE')->where('name', 'Kerala')->count());
        $this->assertSame(1, OrganizationUnit::where('type', 'DISTRICT')->where('name', 'Ernakulam')->count());

        $district = OrganizationUnit::find($first);
        $this->assertSame('DISTRICT', $district->type);
        $this->assertSame('Kerala', $district->parent->name);
    }

    public function test_blank_geography_yields_null(): void
    {
        $this->assertNull(OrganizationResolver::districtUnitId('', 'Ernakulam'));
        $this->assertNull(OrganizationResolver::districtUnitId('Kerala', ' '));
        $this->assertNull(OrganizationResolver::districtUnitId(null, null));
    }
}
