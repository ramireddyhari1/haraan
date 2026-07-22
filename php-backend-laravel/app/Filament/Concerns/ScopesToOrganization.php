<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1c — panel-only tenant scoping. A Filament resource that uses this trait
 * has every query (list/view/edit/delete) restricted to the current admin's
 * organization subtree, unless the admin is unrestricted (super_admin or no org
 * assigned). See User::scopedOrganizationIds() for the policy.
 *
 * The partner console (/partner) is different: partners own records by
 * partner_id, not by organization, and have no org — so in that panel we scope
 * hard to the partner's own records instead. A model without a partner_id column
 * denies all rows in the partner panel rather than leaking the global table.
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

        // Partner console: hard-scope to the partner's own records by partner_id.
        // Super-admins (who can also open /partner) stay unrestricted.
        if (Filament::getCurrentPanel()?->getId() === 'partner' && ! $user->isSuperAdmin()) {
            $table = $query->getModel()->getTable();
            $column = static::partnerScopeColumn();

            if (! Schema::hasColumn($table, $column)) {
                return $query->whereRaw('1 = 0'); // no ownership column → show nothing
            }

            $query->where($table.'.'.$column, $user->effectivePartnerId());

            // Phase 3: a desk person can be limited to specific venues/events. When
            // they are, narrow the owner-scoped query to just those; unassigned
            // staff keep seeing everything. The partner dashboard widgets and
            // booking queries build on these two resource queries, so they inherit
            // the restriction automatically.
            $model = $query->getModel();
            if ($model instanceof \App\Models\Venue && ($venueIds = $user->scopedVenueIds()) !== null) {
                $query->whereIn($table.'.id', $venueIds);
            } elseif ($model instanceof \App\Models\Event && ($eventIds = $user->scopedEventIds()) !== null) {
                $query->whereIn($table.'.id', $eventIds);
            }

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

    /** Column on the resource's model that holds the owning partner id. */
    protected static function partnerScopeColumn(): string
    {
        return 'partner_id';
    }
}
