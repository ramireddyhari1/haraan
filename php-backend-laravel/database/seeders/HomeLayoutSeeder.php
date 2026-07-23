<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\HomeBlock;
use Illuminate\Database\Seeder;

/**
 * Phase 2 — default GameHub home composition. Idempotent (keyed on type+title).
 * Reorder / toggle / schedule these in /control → Marketing → Home layout.
 */
class HomeLayoutSeeder extends Seeder
{
    public function run(): void
    {
        $blocks = [
            ['type' => 'hero', 'title' => null, 'config' => [], 'sort_order' => 1],
            ['type' => 'sports_chips', 'title' => null, 'config' => [], 'sort_order' => 2],
            ['type' => 'actionboard', 'title' => 'ActionBoard', 'config' => [], 'sort_order' => 3],
            ['type' => 'feed_section', 'title' => 'For You', 'config' => ['section' => 'for_you'], 'sort_order' => 4],
            ['type' => 'ad_strip', 'title' => null, 'config' => ['placement' => 'home'], 'sort_order' => 5],
            ['type' => 'venues', 'title' => 'Popular venues', 'config' => [], 'sort_order' => 6],
            ['type' => 'feed_section', 'title' => 'Trending', 'config' => ['section' => 'trending'], 'sort_order' => 7],
            ['type' => 'leaderboard', 'title' => 'Top players', 'config' => ['scope' => 'district'], 'sort_order' => 8],
        ];

        foreach ($blocks as $block) {
            HomeBlock::updateOrCreate(
                ['type' => $block['type'], 'title' => $block['title']],
                $block + ['is_active' => true],
            );
        }

        $this->command?->info('Seeded '.count($blocks).' home block(s).');
    }
}
