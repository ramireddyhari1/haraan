<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\OrganizationUnit;
use App\Models\User;

/**
 * Shared district-targeting for models with a nullable `organization_ids` JSON
 * column (feature flags, home blocks). Empty/null targets everyone; otherwise a
 * user matches when their home org falls within any targeted org's subtree.
 * Requires the array cast on `organization_ids`.
 */
trait TargetsOrganizations
{
    public function matchesOrganization(?User $user): bool
    {
        $targets = $this->organization_ids ?? [];
        if ($targets === []) {
            return true;
        }

        if ($user?->organization_id === null) {
            return false;
        }

        $expanded = OrganizationUnit::whereIn('id', $targets)
            ->get()
            ->flatMap->descendantAndSelfIds()
            ->unique();

        return $expanded->contains($user->organization_id);
    }
}
