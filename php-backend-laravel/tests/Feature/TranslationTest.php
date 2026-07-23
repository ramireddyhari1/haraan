<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Translation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TranslationTest extends TestCase
{
    use RefreshDatabase;

    private function seedStrings(): void
    {
        Translation::create(['group' => 'c', 'key' => 'a.greet', 'locale' => 'en', 'value' => 'Hello']);
        Translation::create(['group' => 'c', 'key' => 'a.bye', 'locale' => 'en', 'value' => 'Bye']);
        Translation::create(['group' => 'c', 'key' => 'a.greet', 'locale' => 'te', 'value' => 'నమస్తే']);
        // a.bye has no Telugu → should fall back to English.
    }

    public function test_bundle_falls_back_to_english(): void
    {
        $this->seedStrings();

        $te = Translation::bundle('te');

        $this->assertSame('నమస్తే', $te['a.greet']);
        $this->assertSame('Bye', $te['a.bye'], 'missing locale value falls back to English');
    }

    public function test_unknown_locale_resolves_to_english(): void
    {
        $this->seedStrings();

        $this->assertSame(Translation::bundle('en'), Translation::bundle('fr'));
    }

    public function test_cache_busts_on_write(): void
    {
        $this->seedStrings();
        $this->assertSame('నమస్తే', Translation::bundle('te')['a.greet']);

        Translation::where('key', 'a.greet')->where('locale', 'te')->update(['value' => 'మారింది']);
        Translation::flushCache(); // mass update bypasses model events

        $this->assertSame('మారింది', Translation::bundle('te')['a.greet']);
    }

    public function test_api_returns_bundle_with_version(): void
    {
        $this->seedStrings();

        $res = $this->getJson('/api/i18n/te');

        $res->assertOk()
            ->assertJsonPath('locale', 'te')
            ->assertJsonStructure(['locale', 'version', 'translations']);

        // Keys contain literal dots, so read the map directly rather than via dot-path.
        $translations = $res->json('translations');
        $this->assertSame('నమస్తే', $translations['a.greet']);
        $this->assertSame('Bye', $translations['a.bye']);
    }

    public function test_api_index_lists_supported_locales(): void
    {
        $this->getJson('/api/i18n')
            ->assertOk()
            ->assertJsonPath('fallback', 'en')
            ->assertJsonFragment(['locales' => Translation::LOCALES]);
    }
}
