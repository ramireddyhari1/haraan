<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Support\PlatformRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The fast phone app (/partner/app) and its admin switch.
 */
class PartnerFastAppTest extends TestCase
{
    use RefreshDatabase;

    private const IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1';
    private const DESKTOP = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Safari/605.1.15';

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::create([
            'name' => 'Ravi Kumar', 'email' => 'owner@haraan.test', 'password' => Hash::make('secret123'),
            'role' => 'PARTNER', 'partner_type' => 'venue', 'status' => 'active',
        ]);
    }

    private function fast(bool $on): void
    {
        PlatformRules::save(['partner_web_app.fast_app' => $on]);
    }

    public function test_switch_off_sends_the_app_back_to_the_console(): void
    {
        $this->fast(false);

        $this->actingAs($this->owner)->get('/partner/app')->assertRedirect('/partner');
    }

    public function test_signed_out_goes_to_the_partner_sign_in(): void
    {
        $this->fast(true);

        $this->get('/partner/app')->assertRedirect();
        $this->assertStringContainsString('/partner/login', (string) $this->get('/partner/app')->headers->get('Location'));
    }

    public function test_switch_on_draws_the_app_with_in_app_destinations(): void
    {
        $this->fast(true);

        $res = $this->actingAs($this->owner)->get('/partner/app/venues');

        $res->assertOk()
            ->assertSee('window.HaraanPartnerApp', false)
            ->assertSee('js/partner/app-shell.js', false)
            ->assertSee('css/partner/app-spa.css', false)
            ->assertDontSee('livewire.js', false);
        $this->assertStringContainsString('no-store', (string) $res->headers->get('Cache-Control'));
        $this->assertStringContainsString('"spa":true', $res->getContent());
        $this->assertStringContainsString('"venues":"http://localhost/partner/app/venues"', $res->getContent());
        // The day desk is drawn by the fast app too (app-bookings.js).
        $this->assertStringContainsString('"bookings":"http://localhost/partner/app/bookings"', $res->getContent());
        $res->assertSee('js/partner/app-bookings.js', false);
    }

    public function test_unknown_screen_paths_are_refused(): void
    {
        $this->fast(true);

        $this->actingAs($this->owner)->get('/partner/app/../control')->assertNotFound();
        $this->actingAs($this->owner)->get('/partner/app/Venues1')->assertNotFound();
    }

    public function test_phone_opening_console_home_is_sent_to_the_app(): void
    {
        $this->fast(true);

        $this->actingAs($this->owner)
            ->withHeader('User-Agent', self::IPHONE)
            ->get('/partner')
            ->assertRedirect('/partner/app');
    }

    public function test_desktop_and_console_override_keep_the_console(): void
    {
        $this->fast(true);

        $this->actingAs($this->owner)->withHeader('User-Agent', self::DESKTOP)->get('/partner')->assertOk();
        $this->actingAs($this->owner)->withHeader('User-Agent', self::IPHONE)->get('/partner?console=1')->assertOk();
    }

    public function test_switch_off_leaves_phones_on_the_console(): void
    {
        $this->fast(false);

        $this->actingAs($this->owner)->withHeader('User-Agent', self::IPHONE)->get('/partner')->assertOk();
    }
}
