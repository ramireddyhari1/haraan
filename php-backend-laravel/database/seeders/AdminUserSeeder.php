<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // A local-dev convenience only. Run on prod once, it left a live ADMIN with the
        // password "password" (found and locked 2026-11-26). Make real admins in /control.
        if (app()->isProduction()) {
            $this->command?->warn('AdminUserSeeder skipped: it never creates admin@local/password in production.');

            return;
        }

        if (User::where('email', 'admin@local')->exists()) {
            return;
        }

        User::create([
            'name' => 'Initial Admin',
            'email' => 'admin@local',
            'password' => bcrypt('password'),
            'role' => 'ADMIN',
            'status' => 'ACTIVE',
        ]);
    }
}
