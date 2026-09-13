<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\SectionThemes\Pages\CreateSectionTheme;
use App\Filament\Resources\SectionThemes\Pages\EditSectionTheme;
use App\Filament\Resources\SectionThemes\Pages\ListSectionThemes;
use App\Models\SectionTheme;
use App\Models\User;
use App\Support\SectionThemeJson;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** The Campaign Themes console must mount, save, import and export — no browser needed. */
class SectionThemeAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::create([
            'name' => 'Admin', 'email' => 'admin@example.test',
            'password' => bcrypt('secret'), 'role' => 'ADMIN', 'status' => 'active',
        ]));
        Filament::setCurrentPanel(Filament::getPanel('control'));
    }

    public function test_create_page_renders_and_saves_a_linked_lottie(): void
    {
        Livewire::test(CreateSectionTheme::class)
            ->assertOk()
            ->assertSee('Decoration')
            ->fillForm([
                'section' => 'pulse',
                'campaign_name' => 'IPL Final',
                'accent_primary' => '#1D4ED8',
                'starts_at' => now()->addHour()->format('Y-m-d H:i'),
                'ends_at' => now()->addDay()->format('Y-m-d H:i'),
                'decoration_url' => 'https://cdn.test/confetti.json',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $theme = SectionTheme::sole();
        $this->assertSame('https://cdn.test/confetti.json', $theme->decoration);
        $this->assertSame('lottie', $theme->decorationType());
    }

    public function test_list_imports_a_theme_file(): void
    {
        $json = json_encode([
            'format' => 'haraan.section-theme', 'version' => 1, 'section' => 'events',
            'campaign_name' => 'Diwali Nights', 'colors' => ['primary' => '#C2410C', 'deep' => '#7C2D12'],
            'starts_at' => '2026-10-20T18:00:00+05:30', 'ends_at' => '2026-10-27T23:59:00+05:30',
        ]);

        Livewire::test(ListSectionThemes::class)
            ->assertOk()
            ->callAction('importJson', data: [
                'file' => UploadedFile::fake()->createWithContent('diwali.json', $json),
            ]);

        $this->assertSame('#7C2D12', SectionTheme::sole()->accent_deep);
    }

    private static function themeFile(): string
    {
        return json_encode([
            'format' => 'haraan.section-theme', 'version' => 1, 'section' => 'pulse',
            'campaign_name' => 'IPL Final', 'colors' => ['primary' => '#1D4ED8', 'deep' => '#1E3A8A'],
            'decoration_url' => 'https://cdn.test/confetti.webp',
            'starts_at' => '2026-10-01T18:00:00+05:30', 'ends_at' => '2026-10-02T23:30:00+05:30',
            'priority' => 2,
        ]);
    }

    private static function lottieFile(): string
    {
        return json_encode([
            'v' => '5.7.4', 'fr' => 30, 'ip' => 0, 'op' => 60, 'w' => 1080, 'h' => 240,
            'layers' => [['ty' => 4, 'nm' => 'dot', 'shapes' => [['ty' => 'fl', 'c' => ['a' => 0, 'k' => [1, 0.5, 0, 1]]]]]],
        ]);
    }

    public function test_create_page_import_fills_the_form_from_a_theme_file(): void
    {
        Livewire::test(CreateSectionTheme::class)
            ->callAction('importJson', data: [
                // A UTF-8 byte-order mark in front, the way Windows editors save it.
                'file' => UploadedFile::fake()->createWithContent('theme.json', "\xEF\xBB\xBF".self::themeFile()),
            ])
            ->assertNotified('Form filled from the theme file')
            ->assertFormSet([
                'section' => 'pulse',
                'campaign_name' => 'IPL Final',
                'accent_deep' => '#1E3A8A',
                'decoration_url' => 'https://cdn.test/confetti.webp',
                // UTC in state (the picker shows 18:00 IST); seconds are off on this picker.
                'starts_at' => '2026-10-01 12:30',
                'priority' => 2,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $theme = SectionTheme::sole();
        $this->assertSame('pulse', $theme->section);
        $this->assertSame('https://cdn.test/confetti.webp', $theme->decoration);
    }

    public function test_create_page_import_attaches_a_lottie_as_the_decoration(): void
    {
        Storage::fake('public');

        Livewire::test(CreateSectionTheme::class)
            ->callAction('importJson', data: [
                'file' => UploadedFile::fake()->createWithContent('lights.json', self::lottieFile()),
            ])
            ->assertNotified('Lottie attached as the decoration')
            ->fillForm([
                'section' => 'events',
                'campaign_name' => 'Diwali',
                'accent_primary' => '#C2410C',
                'starts_at' => now()->addHour()->format('Y-m-d H:i'),
                'ends_at' => now()->addDay()->format('Y-m-d H:i'),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $theme = SectionTheme::sole();
        $this->assertStringStartsWith('section-themes/', $theme->decoration);
        $this->assertSame('lottie', $theme->decorationType());
        Storage::disk('public')->assertExists($theme->decoration);
    }

    public function test_create_page_import_refuses_a_broken_lottie_and_stores_nothing(): void
    {
        Storage::fake('public');
        $broken = json_encode([
            'v' => '5.12.2', 'w' => 1080, 'h' => 240,
            'layers' => [['ty' => 4, 'shapes' => [['ty' => 'rc', 'p' => ['a' => 0, 'k' => ['a' => 0, 'k' => [1, 2]]]]]]],
        ]);

        Livewire::test(CreateSectionTheme::class)
            ->callAction('importJson', data: [
                'file' => UploadedFile::fake()->createWithContent('bad.json', $broken),
            ])
            ->assertNotified('Could not import this file');

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_list_import_explains_when_given_a_lottie(): void
    {
        Livewire::test(ListSectionThemes::class)
            ->callAction('importJson', data: [
                'file' => UploadedFile::fake()->createWithContent('lights.json', self::lottieFile()),
            ])
            ->assertNotified('Could not import this file');

        $this->assertSame(0, SectionTheme::count());
        $this->assertSame(SectionThemeJson::KIND_LOTTIE, SectionThemeJson::kind(self::lottieFile()));
        $this->assertStringContainsString(
            'This is a Lottie animation',
            rescue(fn () => SectionThemeJson::toAttributes(self::lottieFile()), fn ($e) => collect($e->errors())->flatten()->first(), false),
        );
    }

    public function test_edit_page_renders_with_export(): void
    {
        $theme = SectionTheme::create([
            'section' => 'events', 'campaign_name' => 'Diwali', 'accent_primary' => '#C2410C',
            'decoration' => 'https://cdn.test/lights.png',
            'starts_at' => now(), 'ends_at' => now()->addDay(),
        ]);

        Livewire::test(EditSectionTheme::class, ['record' => $theme->getKey()])
            ->assertOk()
            ->assertFormSet(['decoration_url' => 'https://cdn.test/lights.png'])
            ->assertActionExists('exportJson');
    }
}
