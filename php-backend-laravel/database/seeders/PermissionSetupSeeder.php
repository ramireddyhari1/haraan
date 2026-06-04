<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSetupSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'events.view',
            'events.create',
            'events.edit',
            'events.delete',
            'events.edit.own',
            'events.delete.own',
            'gamehub.manage',
            'finance.view',
            'finance.edit',
            'finance.export',
            'users.suspend',
            'users.edit',
            'partners.approve',
            'workers.assign',
            'workers.remove',
            'analytics.view',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $admin = Role::findOrCreate('ADMIN', 'web');
        $coadmin = Role::findOrCreate('COADMIN', 'web');
        $partner = Role::findOrCreate('PARTNER', 'web');
        $worker = Role::findOrCreate('WORKER', 'web');

        $admin->syncPermissions($permissions);
        $coadmin->syncPermissions([
            'events.view',
            'events.create',
            'events.edit',
            'events.delete',
            'gamehub.manage',
            'workers.assign',
            'workers.remove',
            'analytics.view',
        ]);
        $partner->syncPermissions([
            'events.view',
            'events.create',
            'events.edit.own',
            'events.delete.own',
            'analytics.view',
        ]);
        $worker->syncPermissions([
            'events.view',
        ]);

        // Bridge legacy users to new roles by existing role column.
        User::query()->whereNotNull('role')->chunkById(200, function ($users): void {
            foreach ($users as $user) {
                $legacyRole = strtoupper((string) $user->role);
                if (in_array($legacyRole, ['ADMIN', 'COADMIN', 'PARTNER', 'WORKER'], true)) {
                    $user->syncRoles([$legacyRole]);
                }
            }
        });

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
