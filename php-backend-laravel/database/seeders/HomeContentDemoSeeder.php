<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Ad;
use App\Models\FeedItem;
use Illuminate\Database\Seeder;

/**
 * Demo content for the server-driven home's feed_section + ad_strip blocks, so they
 * render the moment `server_driven_home` is enabled. Idempotent (keyed on section+title
 * / placement+title). Edit or replace these in /control → Feed items and Ads.
 */
final class HomeContentDemoSeeder extends Seeder
{
    public function run(): void
    {
        $feed = [
            ['section' => 'for_you', 'title' => 'Sunday Box Cricket League', 'subtitle' => 'Andheri • 6 spots left', 'image' => 'https://images.unsplash.com/photo-1540747913346-19e32dc3e97e?w=600&q=80', 'badge' => 'Filling fast', 'rating' => '4.8', 'link_type' => 'url', 'link_id' => 'http://13.204.63.181', 'sort_order' => 1],
            ['section' => 'for_you', 'title' => 'Friday Night Football 7v7', 'subtitle' => 'Powai • Floodlit turf', 'image' => 'https://images.unsplash.com/photo-1522778526097-ce0a22ceb253?w=600&q=80', 'badge' => 'Popular', 'rating' => '4.6', 'sort_order' => 2],
            ['section' => 'trending', 'title' => 'District T20 Finals', 'subtitle' => 'Live this weekend', 'image' => 'https://images.unsplash.com/photo-1531415074968-036ba1b575da?w=600&q=80', 'badge' => 'Trending', 'rating' => '4.9', 'sort_order' => 1],
            ['section' => 'trending', 'title' => 'Indoor Badminton Open', 'subtitle' => 'Bandra • 6 courts', 'image' => 'https://images.unsplash.com/photo-1626224583764-f87db24ac4ea?w=600&q=80', 'badge' => null, 'rating' => '4.7', 'sort_order' => 2],
        ];

        foreach ($feed as $item) {
            FeedItem::updateOrCreate(
                ['section' => $item['section'], 'title' => $item['title']],
                $item + ['link_type' => null, 'link_id' => null, 'is_active' => true],
            );
        }

        $ads = [
            ['placement' => 'home', 'sponsor' => 'Stryker Turf', 'title' => 'Book a floodlit turf — 20% off weekday slots', 'subtitle' => 'Limited weekday offer', 'image' => 'https://images.unsplash.com/photo-1459865264687-595d652de67e?w=800&q=80', 'cta_text' => 'Book now', 'cta_url' => 'https://example.com', 'sort_order' => 1],
            ['placement' => 'home', 'sponsor' => 'Haraan Pro', 'title' => 'Go Pro — verified scoring & district leaderboards', 'subtitle' => 'Upgrade your game', 'image' => 'https://images.unsplash.com/photo-1551958219-acbc608c6377?w=800&q=80', 'cta_text' => 'Learn more', 'cta_url' => 'https://example.com', 'sort_order' => 2],
        ];

        foreach ($ads as $ad) {
            Ad::updateOrCreate(
                ['placement' => $ad['placement'], 'title' => $ad['title']],
                $ad + ['logo' => null, 'is_active' => true],
            );
        }

        $this->command?->info('Seeded '.count($feed).' feed item(s) and '.count($ads).' ad(s).');
    }
}
