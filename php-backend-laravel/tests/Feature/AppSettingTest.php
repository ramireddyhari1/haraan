<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AppSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_set_get_and_group(): void
    {
        AppSetting::set('app_name', 'Haraan', 'branding');
        AppSetting::set('primary_color', '#fff', 'branding');
        AppSetting::set('misc_key', 'x', 'general');

        $this->assertSame('Haraan', AppSetting::get('app_name'));
        $this->assertSame('fallback', AppSetting::get('missing', 'fallback'));

        $branding = AppSetting::group('branding');
        $this->assertSame(['app_name' => 'Haraan', 'primary_color' => '#fff'], $branding);
        $this->assertArrayNotHasKey('misc_key', $branding);
    }

    public function test_writes_bust_the_cache(): void
    {
        AppSetting::set('tagline', 'one', 'branding');
        $this->assertSame('one', AppSetting::get('tagline'));

        AppSetting::set('tagline', 'two', 'branding');
        $this->assertSame('two', AppSetting::get('tagline'), 'cache refreshed after write');
    }

    public function test_config_endpoint_includes_theme_with_logo_url(): void
    {
        AppSetting::set('app_name', 'Haraan', 'branding');
        AppSetting::set('logo', 'branding/logo.png', 'branding');

        $res = $this->getJson('/api/config');

        $res->assertOk()
            ->assertJsonPath('theme.app_name', 'Haraan')
            ->assertJsonStructure(['features', 'theme', 'server_time']);

        $this->assertStringContainsString('branding/logo.png', $res->json('theme.logo'));
        $this->assertStringStartsWith('http', $res->json('theme.logo'), 'logo path resolved to a URL');
    }
}
