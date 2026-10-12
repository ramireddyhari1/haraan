<?php

declare(strict_types=1);

namespace Tests\Feature\PlatformControl;

use App\Filament\Pages\AppUpdatesPage;
use App\Models\AdminAction;
use App\Models\User;
use App\Support\PlatformRules;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** /control → App updates and the in-app update sheet it feeds (GET /api/app/update). */
class AppUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_nothing_is_offered_until_an_admin_sets_a_latest_version(): void
    {
        $this->getJson('/api/app/update?app=member&version=1.0.52')->assertOk()
            ->assertJsonPath('available', false)
            ->assertJsonPath('required', false)
            ->assertJsonPath('apk_url', 'https://haraan.app/haraan.apk');

        $this->getJson('/api/app/update?app=partner&version=1.3')->assertOk()
            ->assertJsonPath('available', false)
            ->assertJsonPath('apk_url', 'https://haraan.app/partner.apk')
            ->assertJsonPath('store_url', null);
    }

    public function test_member_and_partner_versions_are_independent(): void
    {
        PlatformRules::save(['app.latest_version' => '1.0.53', 'partner_app.latest_version' => '1.4']);

        $this->getJson('/api/app/update?app=member&version=1.0.52')->assertJsonPath('available', true)->assertJsonPath('required', false);
        $this->getJson('/api/app/update?app=member&version=1.0.53')->assertJsonPath('available', false);
        $this->getJson('/api/app/update?app=partner&version=1.3')->assertJsonPath('available', true)->assertJsonPath('latest_version', '1.4');
        // A partner app at 1.3 is not "older" than the member app's 1.0.53, and vice versa.
        $this->getJson('/api/app/update?app=partner&version=1.4')->assertJsonPath('available', false);
    }

    public function test_whats_new_is_split_into_clean_lines(): void
    {
        PlatformRules::save(['partner_app.latest_version' => '1.4', 'partner_app.whats_new' => "• Court block from the walk-in sheet\n\n- ₹ totals on Home\n  Faster booking alerts  "]);

        $this->getJson('/api/app/update?app=partner&version=1.3')
            ->assertJsonPath('whats_new', ['Court block from the walk-in sheet', '₹ totals on Home', 'Faster booking alerts']);
    }

    public function test_force_update_makes_it_required_and_the_partner_api_refuses_old_apps(): void
    {
        PlatformRules::save(['partner_app.latest_version' => '1.4', 'partner_app.force_update' => true]);

        $this->getJson('/api/app/update?app=partner&version=1.3')->assertJsonPath('required', true)->assertJsonPath('min_version', '1.4');

        $this->getJson('/api/partner/overview', ['X-Partner-App-Version' => '1.3'])
            ->assertStatus(426)->assertJsonPath('error', 'update_required')->assertJsonPath('url', 'https://haraan.app/partner.apk');
        // Up to date, or a build that predates the updater (no header): not refused here.
        $this->getJson('/api/partner/overview', ['X-Partner-App-Version' => '1.4'])->assertStatus(401);
        $this->getJson('/api/partner/overview')->assertStatus(401);
        // The member app is untouched by the partner rule.
        $this->getJson('/api/app/update?app=member&version=1.0.1')->assertJsonPath('required', false);
    }

    public function test_the_update_check_stays_open_for_a_blocked_member_app(): void
    {
        PlatformRules::save(['app.min_version' => '1.0.60']);

        $this->getJson('/api/events', ['X-App-Version' => '1.0.52'])->assertStatus(426);
        $this->getJson('/api/app/update?app=member', ['X-App-Version' => '1.0.52'])->assertOk()->assertJsonPath('required', true);
    }

    public function test_an_apk_link_must_be_https(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PlatformRules::save(['partner_app.apk_url' => 'http://haraan.app/partner.apk']);
    }

    public function test_admin_page_is_routed_saves_and_audits(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'a@haraan.test', 'password' => bcrypt('secret123'), 'role' => 'ADMIN', 'status' => 'active']);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('control'));

        $this->get('/control/app-updates')->assertOk()->assertSee('Partner app updates');

        Livewire::test(AppUpdatesPage::class)
            ->set('data.partner_app__latest_version', '1.4')
            ->set('data.partner_app__whats_new', "Court block\nFaster alerts")
            ->call('save');

        $this->assertSame('1.4', PlatformRules::string('partner_app.latest_version'));
        $this->assertTrue(AdminAction::where('action', 'app_updates.updated')->exists());
    }

    public function test_non_admins_cannot_open_the_page(): void
    {
        $user = User::create(['name' => 'Fin', 'email' => 'f@haraan.test', 'password' => bcrypt('secret123'), 'role' => 'FINANCE', 'status' => 'active']);
        $this->actingAs($user);

        $this->get('/control/app-updates')->assertForbidden();
    }
}
