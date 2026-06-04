<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        $stateId = DB::table('organization_units')->insertGetId([
            'name' => 'Andhra Pradesh',
            'type' => 'STATE',
            'parent_id' => null,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $districtId = DB::table('organization_units')->insertGetId([
            'name' => 'Vijayawada',
            'type' => 'DISTRICT',
            'parent_id' => $stateId,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('organization_units')->insert([
            'name' => 'Central Zone',
            'type' => 'AREA',
            'parent_id' => $districtId,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
