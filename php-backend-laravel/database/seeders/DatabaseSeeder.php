<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Demo events with posters
        $this->call(EventSeeder::class);

        // Enterprise access bootstrap
        $this->call(PermissionSetupSeeder::class);
        $this->call(OrganizationSeeder::class);
    }
}
