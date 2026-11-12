<?php

namespace Tests\Unit;

use App\Filament\Support\ClusterSidebarNavigation;
use PHPUnit\Framework\TestCase;

class ClusterSidebarNavigationTest extends TestCase
{
    public function test_a_section_is_active_on_itself_and_its_child_pages(): void
    {
        $base = 'https://haraan.app/control/game-hub/venues';

        $this->assertTrue(ClusterSidebarNavigation::isUnder($base, $base));
        $this->assertTrue(ClusterSidebarNavigation::isUnder($base.'/', $base));
        $this->assertTrue(ClusterSidebarNavigation::isUnder($base.'/create', $base));
        $this->assertTrue(ClusterSidebarNavigation::isUnder($base.'/12/edit', $base));
    }

    public function test_a_sibling_that_only_shares_a_prefix_is_not_active(): void
    {
        // Events list (/events/events) used to light up on Events Overview (/events/events-overview).
        $this->assertFalse(ClusterSidebarNavigation::isUnder(
            'https://haraan.app/control/events/events-overview',
            'https://haraan.app/control/events/events',
        ));
    }
}
