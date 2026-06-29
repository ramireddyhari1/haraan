<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Phase 1 (Filament Shield) — auto-map existing string roles into spatie roles.
 *
 * Idempotent: safe to re-run. Creates the role set, grants each role the
 * permissions for the clusters it already managed, and mirrors every user's
 * legacy `user.role` into the matching Shield role. The legacy column is kept
 * (not dropped) so the EnsureRole/hasRoleEither bridge keeps working during the
 * transition — this seeder only adds the spatie side.
 */
class ShieldRoleMapSeeder extends Seeder
{
    /** Shield `Action:Entity` permissions each department role should hold, keyed by entity suffix. */
    private const ENTITY_GRANTS = [
        'FINANCE'   => ['Payout', 'FinanceOverview'],
        'MARKETING' => ['Ad', 'Coupon', 'FeedItem', 'MarketingOverview'],
        'OPS'       => ['Event', 'Booking', 'LiveMatch', 'Venue', 'EventsOverview', 'GameHubOverview'],
        'PARTNER'   => ['LiveMatch', 'Venue', 'GameHubOverview'],
        'WORKER'    => [],
    ];

    /** Extra non-resource (dot-style) permissions per role, if present. */
    private const EXTRA_GRANTS = [
        'FINANCE'   => ['finance.view', 'finance.edit', 'finance.export', 'analytics.view'],
        'MARKETING' => ['analytics.view'],
        'OPS'       => ['events.create', 'events.edit', 'events.delete', 'events.view', 'gamehub.manage', 'partners.approve'],
        'PARTNER'   => ['gamehub.manage'],
        'WORKER'    => ['workers.assign', 'workers.remove'],
    ];

    /** Legacy `user.role` (upper-cased) → Shield role name. */
    private const LEGACY_MAP = [
        'ADMIN'     => 'super_admin',
        'COADMIN'   => 'super_admin',
        'FINANCE'   => 'FINANCE',
        'MARKETING' => 'MARKETING',
        'OPS'       => 'OPS',
        'PARTNER'   => 'PARTNER',
        'WORKER'    => 'WORKER',
    ];

    public function run(): void
    {
        $guard = config('auth.defaults.guard', 'web');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // super_admin bypasses every check via Shield's gate intercept, so it
        // needs no explicit permissions — just the role to assign users to.
        $superAdmin = Role::findOrCreate('super_admin', $guard);

        $allPermissions = Permission::query()->where('guard_name', $guard)->get();

        foreach (self::ENTITY_GRANTS as $roleName => $entities) {
            $role = Role::findOrCreate($roleName, $guard);

            $entitySet = array_flip($entities);
            $permNames = $allPermissions
                ->filter(function (Permission $p) use ($entitySet) {
                    $parts = explode(':', $p->name);
                    return isset($parts[1]) && isset($entitySet[$parts[1]]);
                })
                ->pluck('name')
                ->all();

            // Add any present extra (dot-style) permissions for this role.
            foreach (self::EXTRA_GRANTS[$roleName] ?? [] as $extra) {
                if ($allPermissions->firstWhere('name', $extra)) {
                    $permNames[] = $extra;
                }
            }

            $role->syncPermissions($permNames);
            $this->command?->info(sprintf('Role %s → %d permissions', $roleName, count($permNames)));
        }

        // Map every existing user from legacy role → spatie role.
        $assigned = 0;
        User::query()->whereNotNull('role')->chunkById(200, function ($users) use (&$assigned) {
            foreach ($users as $user) {
                $legacy = strtoupper((string) $user->role);
                $target = self::LEGACY_MAP[$legacy] ?? null;
                if ($target === null) {
                    continue; // plain USER / unknown → no admin role
                }
                if (! $user->hasRole($target)) {
                    $user->assignRole($target);
                    $assigned++;
                }
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->command?->info("Assigned Shield roles to {$assigned} user(s). super_admin id={$superAdmin->id}.");
    }
}
