<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\OrganizationUnit;

/**
 * Maps free-text geography (state + district) onto the canonical
 * STATE > DISTRICT org tree, creating units on demand. Shared by profile save
 * (stamping a user's home org) and any future backfill so the tree stays
 * consistent regardless of entry point.
 */
final class OrganizationResolver
{
    /**
     * Resolve (and lazily create) the DISTRICT unit for a state/district pair.
     * Returns null when either value is blank — such records stay platform-wide.
     */
    public static function districtUnitId(?string $state, ?string $district): ?int
    {
        $state = trim((string) $state);
        $district = trim((string) $district);
        if ($state === '' || $district === '') {
            return null;
        }

        $stateUnit = OrganizationUnit::firstOrCreate(
            ['type' => 'STATE', 'name' => $state, 'parent_id' => null],
            ['active' => true],
        );

        $districtUnit = OrganizationUnit::firstOrCreate(
            ['type' => 'DISTRICT', 'name' => $district, 'parent_id' => $stateUnit->id],
            ['active' => true],
        );

        return $districtUnit->id;
    }
}
