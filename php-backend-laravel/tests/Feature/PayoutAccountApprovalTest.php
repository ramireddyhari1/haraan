<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PartnerPayoutAccount;
use App\Models\User;
use App\Support\JwtService;
use App\Support\PayoutAccountEditor;
use App\Support\PlatformRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Platform rule payouts.partner_change_mode: a venue owner's change to a VERIFIED
 * settlement account waits for Haraan finance ("approve", the default); money keeps
 * going to the verified account until finance approves. "haraan_only" locks owners out.
 */
final class PayoutAccountApprovalTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private function as(User $u)
    {
        $secret = config('app.jwt_secret') ?: env('JWT_SECRET', 'change_me');

        return $this->withHeader('Authorization', 'Bearer '.JwtService::issueForUser($u, $secret));
    }

    private function finance(): User
    {
        return User::factory()->create(['role' => 'ADMIN', 'name' => 'Finance Desk']);
    }

    private function verified(): PartnerPayoutAccount
    {
        return PartnerPayoutAccount::create([
            'partner_id' => $this->owner->id, 'method' => 'upi', 'account_holder' => 'Vadi Sports',
            'upi_vpa' => 'vadi@okhdfc', 'verified_at' => now(),
        ]);
    }

    private function askForBank(): \Illuminate\Testing\TestResponse
    {
        return $this->as($this->owner)->postJson('/api/partner/payouts/account', [
            'method' => 'bank', 'accountHolder' => 'Vadi Sports', 'bankName' => 'HDFC',
            'accountNumber' => '50100012344321', 'ifsc' => 'HDFC0001234',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['role' => 'partner', 'partner_type' => 'venue', 'status' => 'active']);
    }

    public function test_change_to_verified_account_waits_and_money_keeps_its_destination(): void
    {
        $acc = $this->verified();

        $this->askForBank()->assertOk()->assertJsonPath('result', 'pending');

        $acc->refresh();
        $this->assertNotNull($acc->verified_at);
        $this->assertSame('vadi@okhdfc', $acc->upi_vpa);
        $this->assertTrue($acc->hasPendingChange());
        // The full number is encrypted at rest, not stored in the clear.
        $raw = \DB::table('partner_payout_accounts')->where('id', $acc->id)->value('pending_payload');
        $this->assertStringNotContainsString('50100012344321', (string) $raw);

        $this->as($this->owner)->getJson('/api/partner/payouts')->assertOk()
            ->assertJsonPath('change_mode', 'approve')
            ->assertJsonPath('account.verified', true)
            ->assertJsonPath('pending.method', 'bank')
            ->assertJsonPath('pending.masked', fn ($m) => str_ends_with((string) $m, '4321') && ! str_contains((string) $m, '50100012'));
    }

    public function test_finance_approves_and_the_new_account_goes_live_verified(): void
    {
        $acc = $this->verified();
        $this->askForBank();

        app(PayoutAccountEditor::class)->approvePending($acc->refresh(), $this->finance());

        $acc->refresh();
        $this->assertSame('bank', $acc->method);
        $this->assertSame('50100012344321', $acc->account_number);
        $this->assertNull($acc->upi_vpa);
        $this->assertNotNull($acc->verified_at);
        $this->assertFalse($acc->hasPendingChange());
        $this->assertSame('partner', $acc->updated_by_kind);
    }

    public function test_finance_turns_it_down_and_the_partner_sees_why(): void
    {
        $acc = $this->verified();
        $this->askForBank();

        app(PayoutAccountEditor::class)->rejectPending($acc->refresh(), $this->finance(), 'Name on the cheque does not match.');

        $this->as($this->owner)->getJson('/api/partner/payouts')->assertOk()
            ->assertJsonPath('pending', null)
            ->assertJsonPath('pending_rejected', 'Name on the cheque does not match.')
            ->assertJsonPath('account.masked', fn ($m) => str_contains((string) $m, '@okhdfc'));
    }

    public function test_owner_can_withdraw_their_request(): void
    {
        $acc = $this->verified();
        $this->askForBank();

        $this->as($this->owner)->deleteJson('/api/partner/payouts/account/pending')->assertOk();
        $this->assertFalse($acc->refresh()->hasPendingChange());
    }

    public function test_unverified_account_is_replaced_directly(): void
    {
        PartnerPayoutAccount::create(['partner_id' => $this->owner->id, 'method' => 'upi', 'account_holder' => 'Vadi', 'upi_vpa' => 'old@ybl']);

        $this->askForBank()->assertOk()->assertJsonPath('result', 'saved');
        $this->assertDatabaseHas('partner_payout_accounts', ['partner_id' => $this->owner->id, 'method' => 'bank', 'pending_at' => null]);
    }

    public function test_direct_mode_changes_it_at_once(): void
    {
        PlatformRules::save(['payouts.partner_change_mode' => 'direct']);
        $acc = $this->verified();

        $this->askForBank()->assertOk()->assertJsonPath('result', 'saved');
        $acc->refresh();
        $this->assertSame('bank', $acc->method);
        $this->assertNull($acc->verified_at);
    }

    public function test_haraan_only_locks_the_owner_out(): void
    {
        PlatformRules::save(['payouts.partner_change_mode' => 'haraan_only']);
        $this->verified();

        $this->askForBank()->assertForbidden();
        $this->as($this->owner)->getJson('/api/partner/payouts')->assertOk()->assertJsonPath('can_edit', false);
        $this->assertDatabaseHas('partner_payout_accounts', ['partner_id' => $this->owner->id, 'upi_vpa' => 'vadi@okhdfc', 'pending_at' => null]);
    }

    public function test_control_lists_the_request_and_finance_approves_it_there(): void
    {
        $acc = $this->verified();
        $this->askForBank();
        $finance = User::factory()->create(['role' => 'FINANCE']);

        $this->actingAs($finance);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));
        $this->assertSame('1', \App\Filament\Resources\PartnerPayoutAccounts\PartnerPayoutAccountResource::getNavigationBadge());
        \Livewire\Livewire::test(\App\Filament\Resources\PartnerPayoutAccounts\Pages\ListPartnerPayoutAccounts::class)
            ->assertSee('50100012344321')
            ->assertTableActionVisible('approveChange', $acc)
            ->assertTableActionVisible('rejectChange', $acc)
            ->callTableAction('approveChange', $acc);

        $this->assertSame('bank', $acc->refresh()->method);
        $this->assertNotNull($acc->verified_at);
    }

    public function test_partner_console_shows_the_waiting_change(): void
    {
        $this->verified();
        $this->askForBank();

        $this->actingAs($this->owner);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('partner'));
        \Livewire\Livewire::test(\App\Filament\Pages\Partner\PartnerPayouts::class)
            ->assertSee('Change requested')
            ->assertSee('Withdraw')
            ->assertDontSee('50100012344321')
            ->call('cancelRequest')
            ->assertDontSee('Change requested');
    }

    public function test_haraan_edit_replaces_a_waiting_request(): void
    {
        $acc = $this->verified();
        $this->askForBank();

        app(PayoutAccountEditor::class)->save($this->owner->id, [
            'method' => 'upi', 'account_holder' => 'Vadi Sports', 'upi_vpa' => 'vadi2@okicici',
        ], $this->finance());

        $acc->refresh();
        $this->assertFalse($acc->hasPendingChange());
        $this->assertSame('vadi2@okicici', $acc->upi_vpa);
    }
}
