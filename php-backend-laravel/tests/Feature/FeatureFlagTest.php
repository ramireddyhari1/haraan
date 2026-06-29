<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FeatureFlag;
use App\Models\OrganizationUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeatureFlagTest extends TestCase
{
    use RefreshDatabase;

    private function flag(array $attrs = []): FeatureFlag
    {
        return FeatureFlag::create(array_merge([
            'key' => 'f_'.uniqid(),
            'name' => 'F',
            'enabled' => true,
            'rollout_percentage' => 100,
        ], $attrs));
    }

    private function user(?int $orgId = null): User
    {
        return User::create([
            'name' => 'U',
            'email' => uniqid().'@t.local',
            'password' => bcrypt('x'),
            'role' => 'USER',
            'organization_id' => $orgId,
        ]);
    }

    public function test_master_switch_off_is_false(): void
    {
        $this->assertFalse($this->flag(['enabled' => false])->isEnabledFor($this->user()));
    }

    public function test_enabled_full_rollout_is_true(): void
    {
        $this->assertTrue($this->flag()->isEnabledFor($this->user()));
    }

    public function test_version_gate(): void
    {
        $flag = $this->flag(['min_app_version' => '1.4.0']);
        $user = $this->user();

        $this->assertFalse($flag->isEnabledFor($user, '1.0.0'));
        $this->assertTrue($flag->isEnabledFor($user, '1.4.0'));
        $this->assertTrue($flag->isEnabledFor($user, '2.0.0'));
        $this->assertTrue($flag->isEnabledFor($user, null)); // no version → gate skipped
    }

    public function test_organization_targeting_includes_subtree_only(): void
    {
        $state = OrganizationUnit::create(['type' => 'STATE', 'name' => 'S']);
        $distA = OrganizationUnit::create(['type' => 'DISTRICT', 'name' => 'A', 'parent_id' => $state->id]);
        $distB = OrganizationUnit::create(['type' => 'DISTRICT', 'name' => 'B', 'parent_id' => $state->id]);
        $areaA = OrganizationUnit::create(['type' => 'AREA', 'name' => 'AA', 'parent_id' => $distA->id]);

        $flag = $this->flag(['organization_ids' => [$distA->id]]);

        $this->assertTrue($flag->isEnabledFor($this->user($distA->id)));
        $this->assertTrue($flag->isEnabledFor($this->user($areaA->id)), 'subtree descendant included');
        $this->assertFalse($flag->isEnabledFor($this->user($distB->id)), 'other district excluded');
        $this->assertFalse($flag->isEnabledFor($this->user(null)), 'no-org user excluded from targeted flag');
    }

    public function test_rollout_is_deterministic_and_bounded(): void
    {
        $user = $this->user();

        $this->assertFalse($this->flag(['rollout_percentage' => 0])->isEnabledFor($user));
        $this->assertFalse($this->flag(['rollout_percentage' => 50])->isEnabledFor(null), 'anonymous only gets full rollouts');

        $half = $this->flag(['rollout_percentage' => 50]);
        $this->assertSame($half->isEnabledFor($user), $half->isEnabledFor($user), 'stable across calls');
    }

    public function test_config_endpoint_returns_resolved_map(): void
    {
        $this->flag(['key' => 'alpha', 'enabled' => true]);
        $this->flag(['key' => 'beta', 'enabled' => false]);

        $res = $this->getJson('/api/config');

        $res->assertOk()
            ->assertJsonPath('features.alpha', true)
            ->assertJsonPath('features.beta', false)
            ->assertJsonStructure(['features', 'server_time']);
    }
}
