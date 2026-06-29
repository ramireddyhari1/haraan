<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FeatureFlag;
use App\Models\HomeBlock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeBlockTest extends TestCase
{
    use RefreshDatabase;

    private function block(array $attrs = []): HomeBlock
    {
        return HomeBlock::create(array_merge([
            'type' => 'feed_section',
            'is_active' => true,
            'sort_order' => 1,
        ], $attrs));
    }

    public function test_live_scope_respects_active_and_schedule(): void
    {
        $this->block(['is_active' => true]);
        $this->block(['is_active' => false]);
        $this->block(['starts_at' => now()->addDay()]);   // not started
        $this->block(['ends_at' => now()->subDay()]);      // already ended
        $this->block(['starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]); // in window

        $this->assertSame(2, HomeBlock::query()->live()->count());
    }

    public function test_feature_flag_gate(): void
    {
        FeatureFlag::create(['key' => 'k_on', 'name' => 'on', 'enabled' => true, 'rollout_percentage' => 100]);
        FeatureFlag::create(['key' => 'k_off', 'name' => 'off', 'enabled' => false, 'rollout_percentage' => 100]);

        $on = $this->block(['feature_flag_key' => 'k_on']);
        $off = $this->block(['feature_flag_key' => 'k_off']);
        $missing = $this->block(['feature_flag_key' => 'k_missing']);
        $plain = $this->block(['feature_flag_key' => null]);

        $user = User::create(['name' => 'U', 'email' => 'u@t.local', 'password' => bcrypt('x'), 'role' => 'USER']);

        $this->assertTrue($on->isVisibleFor($user));
        $this->assertFalse($off->isVisibleFor($user));
        $this->assertFalse($missing->isVisibleFor($user), 'unknown flag key hides the block');
        $this->assertTrue($plain->isVisibleFor($user));
    }

    public function test_layout_endpoint_returns_ordered_visible_blocks(): void
    {
        FeatureFlag::create(['key' => 'beta', 'name' => 'b', 'enabled' => false, 'rollout_percentage' => 100]);

        $this->block(['type' => 'hero', 'sort_order' => 2]);
        $this->block(['type' => 'sports_chips', 'sort_order' => 1]);
        $this->block(['type' => 'leaderboard', 'sort_order' => 3, 'feature_flag_key' => 'beta']); // gated off

        $res = $this->getJson('/api/home/layout');

        $res->assertOk()->assertJsonCount(2, 'blocks');
        $this->assertSame(['sports_chips', 'hero'], collect($res->json('blocks'))->pluck('type')->all());
    }
}
