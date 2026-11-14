<?php

declare(strict_types=1);

namespace Tests\Feature\Ads;

use App\Filament\Clusters\Marketing\Pages\AdNetworksPage;
use App\Models\Ad;
use App\Models\LiveMatch;
use App\Models\User;
use App\Support\PlatformRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Membership\MembershipFixtures;
use Tests\TestCase;

/**
 * Google ads in the ActionBoard (AdMob in the app, AdSense on the website): served only what
 * /control → Marketing → Ad networks switches on, never to an ad-free member, and always
 * behind a Haraan sponsor booked into the same spot.
 */
final class NetworkAdsTest extends TestCase
{
    use MembershipFixtures;
    use RefreshDatabase;

    private const FEED_UNIT = 'ca-app-pub-1234567890123456/1111111111';
    private const MATCH_UNIT = 'ca-app-pub-1234567890123456/2222222222';
    private const CLIENT = 'ca-pub-1234567890123456';

    private function appOn(array $extra = []): void
    {
        PlatformRules::save(array_merge([
            'network_ads.app_enabled' => true,
            'network_ads.admob_feed_unit_id' => self::FEED_UNIT,
            'network_ads.admob_match_unit_id' => self::MATCH_UNIT,
            'network_ads.app_feed_every' => 4,
        ], $extra));
    }

    private function webOn(array $extra = []): void
    {
        PlatformRules::save(array_merge([
            'network_ads.web_enabled' => true,
            'network_ads.adsense_client' => self::CLIENT,
            'network_ads.adsense_feed_slot' => '1234567890',
            'network_ads.adsense_match_slot' => '9876543210',
        ], $extra));
    }

    private function liveMatch(): LiveMatch
    {
        return LiveMatch::create([
            'title' => 'Derby', 'home' => 'KK', 'away' => 'NS', 'home_full' => 'Kadapa Kings', 'away_full' => 'North Stars',
            'home_score' => 0, 'away_score' => 0, 'status' => 'Live', 'sport' => 'cricket',
        ]);
    }

    private function admin(string $role): User
    {
        return User::create([
            'name' => $role, 'email' => strtolower($role).random_int(1, 999999).'@haraan.test',
            'password' => bcrypt('secret123'), 'role' => $role, 'status' => 'active',
        ]);
    }

    // ── App: /api/config → ads ─────────────────────────────────────────────────

    public function test_nothing_is_served_until_the_console_switches_it_on(): void
    {
        $this->getJson('/api/config')->assertOk()
            ->assertJsonPath('ads.feed', null)
            ->assertJsonPath('ads.match', null);

        // Units filled in but the master switch still off.
        PlatformRules::save(['network_ads.admob_feed_unit_id' => self::FEED_UNIT]);
        $this->getJson('/api/config')->assertJsonPath('ads.feed', null);
    }

    public function test_the_app_gets_the_units_and_spacing_from_the_console(): void
    {
        $this->appOn();

        $this->getJson('/api/config')->assertOk()
            ->assertJsonPath('ads.feed.unit_id', self::FEED_UNIT)
            ->assertJsonPath('ads.feed.every', 4)
            ->assertJsonPath('ads.match.unit_id', self::MATCH_UNIT);
    }

    public function test_each_spot_switches_off_on_its_own(): void
    {
        $this->appOn(['network_ads.app_match_enabled' => false]);
        $this->getJson('/api/config')
            ->assertJsonPath('ads.feed.unit_id', self::FEED_UNIT)
            ->assertJsonPath('ads.match', null);

        PlatformRules::save(['network_ads.app_match_enabled' => true, 'network_ads.admob_feed_unit_id' => '']);
        $this->getJson('/api/config')
            ->assertJsonPath('ads.feed', null)
            ->assertJsonPath('ads.match.unit_id', self::MATCH_UNIT);
    }

    public function test_the_emergency_stop_empties_every_google_spot(): void
    {
        $this->appOn();
        $this->webOn();
        PlatformRules::save(['ops.network_ads_disabled' => true]);

        $this->getJson('/api/config')->assertJsonPath('ads.feed', null)->assertJsonPath('ads.match', null);
        $this->liveMatch();
        $this->get('/gamehub/actionboard')->assertOk()->assertDontSee('adsbygoogle', false);
    }

    public function test_a_member_whose_plan_hides_ads_gets_no_google_ads(): void
    {
        $this->configureRazorpay();
        $this->appOn();

        $free = $this->member();
        $this->asMember($free)->getJson('/api/config')->assertJsonPath('ads.feed.unit_id', self::FEED_UNIT);

        $pro = $this->member();
        $this->paidSubscription($pro, 'pro');
        $this->asMember($pro)->getJson('/api/config')
            ->assertJsonPath('ads.feed', null)
            ->assertJsonPath('ads.match', null);
    }

    public function test_a_malformed_unit_id_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PlatformRules::save(['network_ads.admob_feed_unit_id' => 'ca-app-pub-123~456']);
    }

    // ── House sponsors share the spots ─────────────────────────────────────────

    public function test_the_actionboard_list_is_a_bookable_house_placement(): void
    {
        self::assertArrayHasKey('actionboard_feed', Ad::PLACEMENTS);
        Ad::create([
            'sponsor' => 'Gully Gear', 'title' => 'Bats 20% off', 'cta_text' => 'Shop',
            'cta_url' => 'https://example.com', 'placement' => 'actionboard_feed', 'is_active' => true, 'sort_order' => 0,
        ]);

        $this->getJson('/api/ads?placement=actionboard_feed')->assertOk()->assertJsonCount(1, 'data');
    }

    // ── Website: AdSense ───────────────────────────────────────────────────────

    public function test_the_website_list_shows_adsense_when_switched_on(): void
    {
        $this->liveMatch();
        $this->get('/gamehub/actionboard')->assertOk()->assertDontSee('adsbygoogle', false);

        $this->webOn();
        $this->get('/gamehub/actionboard')->assertOk()
            ->assertSee('pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client='.self::CLIENT, false)
            ->assertSee('data-ad-slot="1234567890"', false);
    }

    public function test_a_house_sponsor_takes_the_website_list_spot_before_adsense(): void
    {
        $this->liveMatch();
        $this->webOn();
        $ad = Ad::create([
            'sponsor' => 'Gully Gear', 'title' => 'Bats 20% off', 'cta_text' => 'Shop',
            'cta_url' => 'https://example.com', 'placement' => 'actionboard_feed', 'is_active' => true, 'sort_order' => 0,
        ]);

        $this->get('/gamehub/actionboard')->assertOk()
            ->assertSee('Bats 20% off')
            ->assertSee(route('site.ad.click', $ad->id), false)
            ->assertDontSee('data-ad-slot="1234567890"', false);
        self::assertSame(1, $ad->fresh()->impressions_count);
    }

    public function test_match_pages_show_the_match_adsense_unit(): void
    {
        $m = $this->liveMatch();
        $this->webOn();

        $this->get("/gamehub/actionboard/match/{$m->id}")->assertOk()->assertSee('data-ad-slot="9876543210"', false);
        $this->get("/gamehub/actionboard/match/{$m->id}/scorecard")->assertOk()->assertSee('data-ad-slot="9876543210"', false);

        PlatformRules::save(['network_ads.web_match_enabled' => false]);
        $this->get("/gamehub/actionboard/match/{$m->id}")->assertOk()->assertDontSee('adsbygoogle', false);
    }

    public function test_ads_txt_files_are_written_from_the_console(): void
    {
        $this->get('/ads.txt')->assertOk()->assertSee('No ad networks configured');

        $this->appOn(['network_ads.ads_txt_extra' => "facebook.com, 1234, RESELLER, c3e20eee3f780d68\n"]);
        $this->webOn();

        $this->get('/ads.txt')->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('google.com, pub-1234567890123456, DIRECT, f08c47fec0942fa0')
            ->assertSee('facebook.com, 1234, RESELLER');
        $app = $this->get('/app-ads.txt')->assertOk()->getContent();
        self::assertSame(1, substr_count($app, 'google.com, pub-1234567890123456'), 'one line per publisher, not per unit');
    }

    // ── /control ───────────────────────────────────────────────────────────────

    public function test_marketing_manages_the_page_and_others_cannot(): void
    {
        $this->actingAs($this->admin('OPS'));
        $this->assertFalse(AdNetworksPage::canAccess());

        $this->actingAs($this->admin('MARKETING'));
        $this->get(AdNetworksPage::getUrl())->assertOk()->assertSee('No Google ad spot is live');

        Livewire::test(AdNetworksPage::class)
            ->set('data.network_ads__app_enabled', true)
            ->set('data.network_ads__admob_feed_unit_id', self::FEED_UNIT)
            ->call('save')
            ->assertHasNoErrors();

        $this->getJson('/api/config')->assertJsonPath('ads.feed.unit_id', self::FEED_UNIT);
    }
}
