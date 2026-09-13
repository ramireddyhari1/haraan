<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\SectionThemes\SectionThemeResource;
use App\Models\SectionTheme;
use App\Support\SectionThemeJson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SectionThemeTest extends TestCase
{
    use RefreshDatabase;

    private function theme(array $attrs = []): SectionTheme
    {
        return SectionTheme::create(array_merge([
            'section' => 'events',
            'campaign_name' => 'Diwali Nights',
            'accent_primary' => '#f59e0b',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addDay(),
            'is_active' => true,
        ], $attrs));
    }

    public function test_ships_running_and_upcoming_but_not_ended_or_unpublished(): void
    {
        $running = $this->theme();
        $soon = $this->theme(['section' => 'pulse', 'starts_at' => now()->addDays(3), 'ends_at' => now()->addDays(4)]);
        $this->theme(['campaign_name' => 'ended', 'starts_at' => now()->subDays(3), 'ends_at' => now()->subDay()]);
        $this->theme(['campaign_name' => 'draft', 'is_active' => false]);
        $this->theme(['campaign_name' => 'too far', 'starts_at' => now()->addDays(30), 'ends_at' => now()->addDays(31)]);

        $response = $this->getJson('/api/section-themes')->assertOk();

        $this->assertEqualsCanonicalizing(
            [$running->id, $soon->id],
            collect($response->json('themes'))->pluck('id')->all(),
        );
        $this->assertIsInt($response->json('server_time'));
    }

    public function test_wire_shape_normalises_colours_and_uses_epoch_window(): void
    {
        $theme = $this->theme(['accent_deep' => 'not-a-colour', 'on_primary' => '#111827']);

        $row = $this->getJson('/api/section-themes')->json('themes.0');

        $this->assertSame('#F59E0B', $row['accent']['primary']);
        $this->assertNull($row['accent']['deep'], 'a malformed optional colour is dropped, not shipped');
        $this->assertSame('#111827', $row['accent']['on_primary']);
        $this->assertSame($theme->starts_at->getTimestamp(), $row['valid_from']);
        $this->assertSame($theme->ends_at->getTimestamp(), $row['valid_until']);
        $this->assertNull($row['decoration']);
    }

    public function test_decoration_type_follows_the_file(): void
    {
        $this->theme(['decoration' => 'https://cdn.test/lights.json']);
        $this->theme(['section' => 'pulse', 'decoration' => 'section-themes/garland.webp']);

        $rows = collect($this->getJson('/api/section-themes')->json('themes'))->keyBy('section');

        $this->assertSame(['url' => 'https://cdn.test/lights.json', 'type' => 'lottie'], $rows['events']['decoration']);
        $this->assertSame('image', $rows['pulse']['decoration']['type']);
        $this->assertStringEndsWith('/storage/section-themes/garland.webp', $rows['pulse']['decoration']['url']);
    }

    public function test_row_with_invalid_primary_is_never_shipped(): void
    {
        $this->theme(['accent_primary' => 'blue']);

        $this->getJson('/api/section-themes')->assertOk()->assertJsonCount(0, 'themes');
    }

    public function test_higher_priority_comes_first(): void
    {
        $this->theme(['campaign_name' => 'low', 'priority' => 0]);
        $this->theme(['campaign_name' => 'high', 'priority' => 5]);

        $this->assertSame('high', $this->getJson('/api/section-themes')->json('themes.0.campaign_name'));
    }

    public function test_saving_broadcasts_the_themes_domain(): void
    {
        $this->assertSame('themes', $this->theme()->contentDomain());
    }

    public function test_json_export_round_trips_through_import(): void
    {
        $original = $this->theme([
            'accent_deep' => '#7c2d12',
            'accent_tint' => '#fed7aa',
            'decoration' => 'https://cdn.test/lights.json',
            'starts_at' => '2026-10-20 12:30:00',
            'ends_at' => '2026-10-27 18:29:00',
            'priority' => 3,
        ]);

        $json = json_encode(SectionThemeJson::export($original));
        $attrs = SectionThemeJson::toAttributes($json);

        $this->assertSame('#7C2D12', $attrs['accent_deep']);
        $this->assertSame('https://cdn.test/lights.json', $attrs['decoration']);
        $this->assertSame($original->starts_at->getTimestamp(), $attrs['starts_at']->getTimestamp());
        $this->assertSame($original->ends_at->getTimestamp(), $attrs['ends_at']->getTimestamp());
        $this->assertSame(3, $attrs['priority']);
        $this->assertSame('2026-10-20T18:00:00+05:30', SectionThemeJson::export($original)['starts_at']);
    }

    public function test_import_without_offset_is_read_as_ist(): void
    {
        $attrs = SectionThemeJson::toAttributes(json_encode([
            'format' => 'haraan.section-theme', 'version' => 1, 'section' => 'pulse',
            'campaign_name' => 'IPL Final', 'colors' => ['primary' => '#1d4ed8'],
            'starts_at' => '2026-10-01 18:00', 'ends_at' => '2026-10-01 23:30',
        ]));

        $this->assertSame('2026-10-01 12:30:00', $attrs['starts_at']->format('Y-m-d H:i:s'));
        $this->assertTrue($attrs['is_active']);
        $this->assertNull($attrs['decoration']);
    }

    #[DataProvider('badImports')]
    public function test_import_rejects_bad_files_with_a_named_reason(string $json, string $expectedKey): void
    {
        try {
            SectionThemeJson::toAttributes($json);
            $this->fail('expected a validation error');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($expectedKey, $e->errors());
        }
    }

    public static function badImports(): array
    {
        $base = [
            'format' => 'haraan.section-theme', 'version' => 1, 'section' => 'events',
            'campaign_name' => 'X', 'colors' => ['primary' => '#112233'],
            'starts_at' => '2026-10-01T10:00:00+05:30', 'ends_at' => '2026-10-02T10:00:00+05:30',
        ];

        return [
            'not json' => ['{nope', 'file'],
            'wrong format' => [json_encode(['format' => 'other'] + $base), 'file'],
            'bad colour' => [json_encode(array_replace_recursive($base, ['colors' => ['primary' => 'red']])), 'colors.primary'],
            'bad lane' => [json_encode(['section' => 'movies'] + $base), 'section'],
            'ends first' => [json_encode(['ends_at' => '2026-09-30T10:00:00+05:30'] + $base), 'ends_at'],
            'not https decoration' => [json_encode(['decoration_url' => 'http://x.test/a.png'] + $base), 'decoration_url'],
            'wrong decoration type' => [json_encode(['decoration_url' => 'https://x.test/a.gif'] + $base), 'decoration_url'],
        ];
    }

    public function test_uploaded_json_that_is_not_lottie_is_refused_and_removed(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('section-themes/a.json', '{"hello":"world"}');
        Storage::disk('public')->put('section-themes/b.json', '{"v":"5.7.4","layers":[]}');

        try {
            SectionThemeResource::foldDecoration(['decoration' => 'section-themes/a.json']);
            $this->fail('expected a validation error');
        } catch (ValidationException) {
            Storage::disk('public')->assertMissing('section-themes/a.json');
        }

        $this->assertSame('section-themes/b.json', SectionThemeResource::foldDecoration(['decoration' => 'section-themes/b.json'])['decoration']);
        $this->assertSame('https://c.test/x.webp', SectionThemeResource::foldDecoration(['decoration' => null, 'decoration_url' => 'https://c.test/x.webp'])['decoration']);
    }
}
