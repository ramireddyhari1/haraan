<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
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
