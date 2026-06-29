<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Phase 1c — panel-only tenant scoping. A Filament resource that uses this trait
 * has every query (list/view/edit/delete) restricted to the current admin's
 * organization subtree, unless the admin is unrestricted (super_admin or no org
 * assigned). See User::scopedOrganizationIds() for the policy.
 *
 * This deliberately overrides only the resource's Eloquent query, so the mobile
 * API (which shares these models) is unaffected. Records with a null
 * organization_id are platform-wide and are hidden from scoped managers by
 * design — they belong to super admins.
 */
trait ScopesToOrganization
{
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = auth()->user();
        if ($user === null) {
            return $query;
        }

        $ids = $user->scopedOrganizationIds();
        if ($ids === null) {
            return $query; // unrestricted
        }

        return $query->whereIn($query->getModel()->getTable().'.'.static::organizationScopeColumn(), $ids);
    }

    /** Column on the resource's model that holds the owning organization id. */
    protected static function organizationScopeColumn(): string
    {
        return 'organization_id';
    }
}
