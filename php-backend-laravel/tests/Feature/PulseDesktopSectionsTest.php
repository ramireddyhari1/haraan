<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Pages\PulseWebPage;
use App\Models\AdminAction;
use App\Models\LiveMatch;
use App\Models\User;
use App\Support\PlatformRules;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The sections under the desktop /gamehub grid and their /control page
 * (Platform → Pulse desktop page, the `pulse_web` rules).
 */
class PulseDesktopSectionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('control'));
    }

    private function admin(string $role = 'ADMIN'): User
    {
        return User::create([
            'name' => $role, 'email' => strtolower($role).random_int(1, 999999).'@haraan.test',
            'password' => bcrypt('secret123'), 'role' => $role, 'status' => 'active',
        ]);
    }

    private function match(array $overrides = []): LiveMatch
    {
        return LiveMatch::create(array_merge([
            'title' => 'Test', 'home' => 'RCB', 'away' => 'CSK', 'home_full' => 'Royal Strikers', 'away_full' => 'Chennai Kings',
            'home_score' => 40, 'away_score' => 0, 'overs' => '5.0', 'status' => 'Live', 'is_private' => false,
        ], $overrides));
    }

    public function test_default_page_shows_banners_questions_and_app_panel(): void
    {
        $html = $this->get('/gamehub')->assertOk()->getContent();

        $this->assertSame(4, substr_count($html, 'class="dpromo '));
        $this->assertSame(5, substr_count($html, 'class="dfaq__item"'));
        $this->assertStringContainsString('id="pulse-app"', $html);
        $this->assertStringContainsString('"@type":"FAQPage"', $html);
    }

    public function test_matches_row_lists_live_and_finished_but_never_private(): void
    {
        $this->match(['home_full' => 'Live Lions']);
        $this->match(['home_full' => 'Done Daredevils', 'status' => 'Completed', 'result' => 'Done Daredevils won by 4 runs', 'completed_at' => now()]);
        $this->match(['home_full' => 'Secret Sixers', 'is_private' => true]);

        $html = $this->get('/gamehub')->assertOk()->getContent();

        $this->assertStringContainsString('Live Lions', $html);
        $this->assertStringContainsString('Done Daredevils won by 4 runs', $html);
        $this->assertStringNotContainsString('Secret Sixers', $html);
    }

    public function test_empty_sections_hide_themselves(): void
    {
        $html = $this->get('/gamehub')->assertOk()->getContent();

        // No matches, posts or ranked players in a fresh database.
        $this->assertStringNotContainsString('class="dmatch', $html);
        $this->assertStringNotContainsString('class="dphoto"', $html);
        $this->assertStringNotContainsString('class="dplayer', $html);
    }

    public function test_admin_edits_apply_to_the_page_and_are_audited(): void
    {
        $this->actingAs($this->admin());
        $this->get(PulseWebPage::getUrl())->assertOk();

        Livewire::test(PulseWebPage::class)
            ->set('data.pulse_web__promos_enabled', false)
            ->set('data.pulse_web__faq', "Is parking free? | Yes, at every listed ground.\nnot a pair")
            ->set('data.pulse_web__app_enabled', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertFalse(PlatformRules::bool('pulse_web.promos_enabled'));
        $this->assertTrue(AdminAction::query()->where('action', 'pulse_web_rules.updated')->exists());

        $html = $this->get('/gamehub')->assertOk()->getContent();
        $this->assertStringNotContainsString('class="dpromo ', $html);
        $this->assertSame(1, substr_count($html, 'class="dfaq__item"'));
        $this->assertStringContainsString('Is parking free?', $html);
        $this->assertStringNotContainsString('id="pulse-app"', $html);
    }

    public function test_marketing_cannot_open_the_page(): void
    {
        $this->actingAs($this->admin('MARKETING'));
        $this->assertFalse(PulseWebPage::canAccess());
    }
}
