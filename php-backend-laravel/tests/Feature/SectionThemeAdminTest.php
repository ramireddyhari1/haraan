<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\SectionThemes\Pages\CreateSectionTheme;
use App\Filament\Resources\SectionThemes\Pages\EditSectionTheme;
use App\Filament\Resources\SectionThemes\Pages\ListSectionThemes;
use App\Models\SectionTheme;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
