<?php

declare(strict_types=1);

namespace Tests\Feature\Ads;

use App\Models\Ad;
use App\Models\AdEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Impressions and clicks a sponsor can be shown: de-duplicated, bound to the placement the ad
 * was booked into, and never counted for an ad that isn't running.
 */
final class AdTrackingTest extends TestCase
{
    use RefreshDatabase;

    private function ad(array $attrs = []): Ad
    {
        return Ad::create(array_merge([
            'sponsor' => 'Gully Gear', 'title' => 'Bats 20% off', 'cta_text' => 'Shop',
            'cta_url' => 'https://example.com/bats', 'placement' => 'match_live',
            'is_active' => true, 'sort_order' => 0,
        ], $attrs));
    }

    private function beacon(Ad $ad, string $kind, string $viewer = 'install-1', array $body = []): \Illuminate\Testing\TestResponse
    {
        return $this->withHeader('X-Install-Id', $viewer)
            ->postJson("/api/ads/{$ad->id}/{$kind}", array_merge(['placement' => 'match_live', 'match_id' => 7], $body));
    }

    public function test_an_impression_counts_once_per_viewer_per_window(): void
    {
        $ad = $this->ad();

        $this->beacon($ad, 'impression')->assertOk()->assertJsonPath('counted', true);
        $this->beacon($ad, 'impression')->assertOk()->assertJsonPath('counted', false);
        $this->beacon($ad, 'impression', 'install-2')->assertOk()->assertJsonPath('counted', true);

        self::assertSame(2, $ad->fresh()->impressions_count);

        Carbon::setTestNow(now()->addMinutes(31));
        $this->beacon($ad, 'impression')->assertJsonPath('counted', true);
        Carbon::setTestNow();
        self::assertSame(3, $ad->fresh()->impressions_count);
    }

    public function test_a_double_tap_is_one_click(): void
    {
        $ad = $this->ad();

        $this->beacon($ad, 'click')->assertJsonPath('counted', true);
        $this->beacon($ad, 'click')->assertJsonPath('counted', false);

        self::assertSame(1, $ad->fresh()->clicks_count);
        self::assertSame(0, $ad->fresh()->impressions_count, 'a click never invents an impression');
        self::assertEqualsWithDelta(null, $ad->fresh()->ctr(), 0);
    }

    public function test_viewers_are_stored_hashed_never_raw(): void
    {
        $ad = $this->ad();
        $this->beacon($ad, 'impression', 'raw-install-id-123');

        $row = AdEvent::query()->first();
        self::assertNotSame('raw-install-id-123', $row->viewer_hash);
        self::assertSame(64, strlen((string) $row->viewer_hash));
        self::assertSame(7, (int) $row->match_id);
    }

    public function test_nothing_is_counted_without_a_viewer(): void
    {
        $ad = $this->ad();
        $this->postJson("/api/ads/{$ad->id}/impression", ['placement' => 'match_live'])->assertOk()->assertJsonPath('counted', false);
        self::assertSame(0, AdEvent::query()->count());
    }

    public function test_wrong_placement_and_ads_that_are_not_running_are_refused(): void
    {
        $ad = $this->ad();
        $this->beacon($ad, 'impression', 'v', ['placement' => 'events'])->assertStatus(422);

        $paused = $this->ad(['is_active' => false]);
        $this->beacon($paused, 'impression')->assertNotFound();

        $expired = $this->ad(['ends_at' => now()->subDay()]);
        $this->beacon($expired, 'click')->assertNotFound();

        self::assertSame(0, AdEvent::query()->count());
    }

    public function test_the_ads_feed_never_serves_a_non_http_link(): void
    {
        $this->ad(['cta_url' => 'javascript:alert(1)']);

        $this->getJson('/api/ads?placement=match_live')->assertOk()->assertJsonPath('data.0.cta_url', null);
    }

    public function test_a_web_click_is_counted_then_redirected(): void
    {
        $ad = $this->ad(['placement' => 'events']);

        $this->get("/go/ad/{$ad->id}")->assertRedirect('https://example.com/bats');
        self::assertSame(1, $ad->fresh()->clicks_count);
        self::assertSame('web', AdEvent::query()->value('surface'));
    }

    public function test_a_web_click_on_an_unsafe_or_stopped_ad_goes_nowhere_external(): void
    {
        $unsafe = $this->ad(['placement' => 'events', 'cta_url' => 'javascript:alert(1)']);
        $this->get("/go/ad/{$unsafe->id}")->assertRedirect('/events');

        $stopped = $this->ad(['placement' => 'events', 'is_active' => false]);
        $this->get("/go/ad/{$stopped->id}")->assertRedirect('/events');

        self::assertSame(0, AdEvent::query()->count());
    }
}
