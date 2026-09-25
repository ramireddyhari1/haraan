<?php

declare(strict_types=1);

namespace Tests\Feature\PlatformControl;

use App\Filament\Pages\PlatformRulesPage;
use App\Models\AdminAction;
use App\Models\FeatureFlag;
use App\Models\User;
use App\Services\BookingService;
use App\Services\WaitlistService;
use App\Support\ActionboardXp;
use App\Support\PlatformRules;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * /control → Platform rules: defaults equal the old hardcoded behaviour, saved values take
 * effect on the next call, bad values and secrets are refused, sections are permissioned on
 * the server, and the audit log is exact and append-only.
 */
class PlatformRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('control'));
    }

    private function user(string $role): User
    {
        return User::create([
            'name' => $role, 'email' => strtolower($role).random_int(1, 999999).'@haraan.test',
            'password' => bcrypt('secret123'), 'role' => $role, 'status' => 'active',
        ]);
    }

    public function test_defaults_match_the_behaviour_before_rules_existed(): void
    {
        $this->assertSame(15, BookingService::holdMinutes());
        $this->assertSame(90, WaitlistService::offerWindowMinutes());
        $this->assertSame(3, WaitlistService::offersPerSlot());
        $this->assertSame(100, ActionboardXp::baseXpForType('tournament'));
        $this->assertSame(0.5, ActionboardXp::diversityMultiplier(3));
        $this->assertSame(0.15, ActionboardXp::winBonusFraction());
        $this->assertSame(300, PlatformRules::int('otp.ttl_seconds'));
        $this->assertSame('none', PlatformRules::string('fees.event_platform_fee_type'));
    }

    public function test_saved_values_apply_immediately(): void
    {
        PlatformRules::save(['xp.base_casual' => 40, 'waitlist.offers_per_slot' => 5, 'bookings.event_hold_minutes' => 20]);

        $this->assertSame(40, ActionboardXp::baseXpForType('casual'));
        $this->assertSame(5, WaitlistService::offersPerSlot());
        $this->assertSame(20, BookingService::holdMinutes());
    }

    public function test_out_of_range_and_unknown_values_are_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PlatformRules::save(['bookings.event_hold_minutes' => 2]); // below the 10-minute floor
    }

    public function test_secret_looking_keys_are_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PlatformRules::assertNotSecret('razorpay.key_secret');
    }

    public function test_finance_can_change_fees_but_not_other_sections(): void
    {
        $this->actingAs($this->user('FINANCE'));
        $this->assertTrue(PlatformRulesPage::canAccess());

        Livewire::test(PlatformRulesPage::class)
            ->set('data.fees__venue_commission_percent', 7.5)
            ->set('data.xp__base_casual', 999)          // not theirs — must be ignored on save
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(7.5, PlatformRules::float('fees.venue_commission_percent'));
        $this->assertSame(25, PlatformRules::int('xp.base_casual'));

        $log = AdminAction::query()->where('action', 'platform_rules.updated')->firstOrFail();
        $this->assertSame(['fees.venue_commission_percent'], array_keys($log->meta['changes']));
    }

    public function test_members_cannot_reach_the_page(): void
    {
        $this->actingAs($this->user('user'));
        $this->assertFalse(PlatformRulesPage::canAccess());
    }

    public function test_console_edits_to_sensitive_records_are_diff_logged_and_the_log_is_append_only(): void
    {
        $this->actingAs($this->user('ADMIN'));

        $flag = FeatureFlag::create(['key' => 'new_checkout', 'name' => 'New checkout', 'enabled' => false, 'rollout_percentage' => 100]);
        $flag->update(['enabled' => true]);

        $entry = AdminAction::query()->where('action', 'feature_flag.updated')->firstOrFail();
        $this->assertSame('FeatureFlag', $entry->subject_type);
        $this->assertSame($flag->id, $entry->subject_id);
        $this->assertSame(['from' => false, 'to' => true], $entry->meta['changes']['enabled']);
        $this->assertStringContainsString('enabled: off → on', $entry->summary());

        $this->expectException(\LogicException::class);
        $entry->update(['action' => 'tampered']);
    }

    public function test_secrets_are_redacted_from_audit_meta(): void
    {
        AdminAction::log('test.secret', ['api_key' => 'sk_live_123', 'nested' => ['password' => 'x', 'ok' => 'y']]);

        $meta = AdminAction::query()->latest('id')->first()->meta;
        $this->assertSame('[redacted]', $meta['api_key']);
        $this->assertSame('[redacted]', $meta['nested']['password']);
        $this->assertSame('y', $meta['nested']['ok']);
    }

    public function test_the_audit_log_page_renders_nested_details(): void
    {
        $this->actingAs($this->user('ADMIN'));
        AdminAction::log('membership_settings.updated', ['values' => ['a' => 1, 'b' => ['c' => 2]]]);

        $this->get('/control/admin-actions')->assertOk()->assertSee('membership_settings.updated');
    }
}
