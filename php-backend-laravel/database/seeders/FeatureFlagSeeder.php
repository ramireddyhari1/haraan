<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\FeatureFlag;
use Illuminate\Database\Seeder;

/**
 * Phase 2 — starter set of runtime flags. Idempotent (upsert by key). These map
 * to capabilities the team has discussed gating; edit/toggle them in /control.
 */
class FeatureFlagSeeder extends Seeder
{
    public function run(): void
    {
        $flags = [
            [
                'key' => 'local_league_creation',
                'name' => 'Local League creation',
                'description' => 'Allow players to create local leagues/tournaments from the app.',
                'enabled' => false,
                'rollout_percentage' => 100,
            ],
            [
                'key' => 'new_scorecard',
                'name' => 'New scorecard UI',
                'description' => 'Roll out the redesigned match scorecard.',
                'enabled' => true,
                'rollout_percentage' => 25, // gradual rollout
            ],
            [
                'key' => 'private_matches',
                'name' => 'Private matches',
                'description' => 'Create-flow toggle for private (unranked, share-code) matches.',
                'enabled' => false,
                'rollout_percentage' => 100,
            ],
            [
                'key' => 'multi_language',
                'name' => 'In-app language switch',
                'description' => 'Expose the language switcher across the whole app.',
                'enabled' => true,
                'rollout_percentage' => 100,
                'min_app_version' => '1.4.0',
            ],
            [
                'key' => 'server_driven_home',
                'name' => 'Server-driven home layout',
                'description' => 'Render the GameHub home from the /control Home layout (block order/visibility) instead of the built-in order. Off = built-in fallback.',
                'enabled' => false,
                'rollout_percentage' => 100,
            ],
        ];

        foreach ($flags as $flag) {
            FeatureFlag::updateOrCreate(['key' => $flag['key']], $flag);
        }

        $this->command?->info('Seeded '.count($flags).' feature flag(s).');
    }
}
